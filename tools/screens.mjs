// Storefront screenshots for design review (Intelligence Lab + Qimia Beauty).
//   node tools/screens.mjs <outdir> [surface ...]   (no surface names = the whole fixed list)
// Starts the browser router (tests/browser/server.php: the real plugins over the
// WordPress/WooCommerce doubles) on a free port and captures a fixed, named list of
// surfaces at desktop 1440x900 and mobile 390x844, in English and Arabic:
//   <outdir>/<surface>-<lang>-<viewport>.png  and  <outdir>/index.json
// Every surface except home-qil is captured with the cookie qt_beauty=1, i.e. with both
// plugins on Beauty's staging host. Motion is left on (prefers-reduced-motion:
// no-preference); fonts and images are awaited and animations/caret are frozen only at
// the capture instant (Playwright's animations:'disabled', caret:'hide').
//
// Environment: PLAYWRIGHT_PATH (module), CHROMIUM_PATH (browser executable), PHP (binary).
import { spawn } from 'node:child_process';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { createServer } from 'node:net';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { crc32, deflateSync, inflateSync } from 'node:zlib';

const require = createRequire(import.meta.url);
const { chromium } = (() => {
	if (process.env.PLAYWRIGHT_PATH) return require(process.env.PLAYWRIGHT_PATH);
	try { return require('playwright'); } catch (_) { return require('/opt/node22/lib/node_modules/playwright'); }
})();
const repo = join(dirname(fileURLToPath(import.meta.url)), '..');
const out = process.argv[2] ? resolve(process.argv[2]) : '';
if (!out) { console.error('usage: node tools/screens.mjs <outdir>'); process.exit(2); }
mkdirSync(out, { recursive: true });
const chromiumPath = process.env.CHROMIUM_PATH || ['/opt/pw-browsers/chromium-1194/chrome-linux/chrome'].find(existsSync);

/* ---------------- server ---------------- */
const port = await new Promise((done, fail) => {
	const probe = createServer().once('error', fail).listen(0, '127.0.0.1', () => { const { port } = probe.address(); probe.close(() => done(port)); });
});
const BASE = `http://127.0.0.1:${port}`;
const server = spawn(process.env.PHP || 'php', ['-S', `127.0.0.1:${port}`, join(repo, 'tests', 'browser', 'server.php')], { cwd: repo, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = '';
server.stderr.on('data', chunk => { serverLog += chunk; });
const stop = () => { try { server.kill(); } catch (_) { /* already gone */ } };
process.on('exit', stop);
for (let i = 0; ; i++) {
	try { if ((await fetch(`${BASE}/favicon.ico`)).status) break; } catch (_) { /* not listening yet */ }
	if (i > 50) { console.error(`server did not start on ${BASE}\n${serverLog}`); process.exit(1); }
	await new Promise(r => setTimeout(r, 100));
}

/* ---------------- surfaces ---------------- */
const viewports = {
	desktop: { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1, isMobile: false, hasTouch: false },
	mobile: { viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true },
};
// Fixed chrome drawn over an element capture (header, skip link, AI launcher, compare dock) is hidden for that capture
// only. opacity as well as visibility: the header's bag icon forces visibility:visible!important on itself.
const hideFixed = '.qil-skip,.qil-header,.qil-topbar,[data-qil-ai-launcher],.qil-compare-dock{visibility:hidden!important;opacity:0!important}';
const cards = '.qil-product-card';
const surfaces = [
	{ name: 'home', path: '/', capture: 'full', ready: '.qil-flash-rail ' + cards, shows: 'Homepage, full length, with both plugins (QIL home + Beauty home section before the flash sale).' },
	{ name: 'home-qil', path: '/', beauty: false, capture: 'full', ready: '.qil-flash-rail ' + cards, shows: 'Homepage, full length, Intelligence Lab only (no Beauty), as the existing browser suite and production render it.' },
	{ name: 'hero', path: '/', capture: 'viewport', ready: '.qil-hero', shows: 'First screen: top bar, header and the hero portal, as the shopper lands.' },
	{ name: 'flash', path: '/', capture: '#qil-flash-drop', ready: '.qil-flash-rail ' + cards, shows: 'Flash sale section (evergreen offers, best sellers first) with its card carousel.' },
	{ name: 'stacks', path: '/', capture: '#qil-stacks', ready: '#qil-stacks ' + cards, shows: 'Stacks (bundles) section with cashback badges and theme product cards.' },
	{ name: 'cards', path: '/', capture: '#qil-goal-results', ready: '#qil-goal-results ' + cards, shows: 'Product card grid close-up: the goal engine results grid (shared QIL product card).' },
	{ name: 'search-protein', path: '/', capture: 'viewport', search: 'protein', shows: 'Header search open with the live dropdown for "protein" (QIL results).' },
	{ name: 'search-shampoo', path: '/', capture: 'viewport', search: 'shampoo', shows: 'Header search open with the live dropdown for "shampoo" (Beauty adapter: Beauty products, category link, sold-out badge).' },
	{ name: 'cart', path: '/cart/', cookies: { qt_cart: '102:0:1' }, capture: 'full', ready: '.qil-boost-band ' + cards, shows: 'Classic cart page (WoodMart stand-in) with the cashback ladder panel and "Complete your stack" band.' },
	{ name: 'minicart', path: '/', cookies: { qt_cart: '101:1011:1' }, capture: 'viewport', minicart: true, shows: 'Mini cart drawer open over the homepage, with the cashback ladder and picks.' },
	{ name: 'member', path: '/', cookies: { qt_user: '7' }, capture: '#qil-member', ready: '[data-qil-member]:not([hidden]) ' + cards, shows: 'Signed-in member hub: cashback wallet, credits, "Running low" reorder and picks.' },
	{ name: 'beauty-home', path: '/', capture: '#qbx-home', ready: '#qbx-home', shows: 'Beauty home section (qil_home_before_flash, Beauty staging experience qbx_home).' },
	{ name: 'beauty-page', path: '/beauty/', capture: 'full', ready: '.qbx-experience', shows: 'Beauty department page /beauty/ ([qimia_beauty] inside QIL chrome on a WoodMart page stand-in), full length.' },
];

/* ---------------- PNG stitching (Chromium cannot capture more than 16384 device px in one go) ---------------- */
function decodePng(buffer) {
	let at = 8, width = 0, height = 0, depth = 0, type = 0, interlace = 0;
	const data = [];
	while (at < buffer.length) {
		const length = buffer.readUInt32BE(at), name = buffer.toString('ascii', at + 4, at + 8), body = buffer.subarray(at + 8, at + 8 + length);
		if (name === 'IHDR') { width = body.readUInt32BE(0); height = body.readUInt32BE(4); depth = body[8]; type = body[9]; interlace = body[12]; }
		else if (name === 'IDAT') data.push(body);
		else if (name === 'IEND') break;
		at += 12 + length;
	}
	if (depth !== 8 || ![2, 6].includes(type) || interlace) throw new Error(`unsupported PNG (depth ${depth}, colour type ${type})`);
	const bpp = type === 6 ? 4 : 3, stride = width * bpp, raw = inflateSync(Buffer.concat(data)), pixels = Buffer.alloc(stride * height);
	for (let y = 0; y < height; y++) {
		const filter = raw[y * (stride + 1)], from = y * (stride + 1) + 1, row = y * stride;
		for (let x = 0; x < stride; x++) {
			const a = x >= bpp ? pixels[row + x - bpp] : 0, b = y ? pixels[row - stride + x] : 0, c = x >= bpp && y ? pixels[row - stride + x - bpp] : 0;
			let value = raw[from + x];
			if (filter === 1) value += a;
			else if (filter === 2) value += b;
			else if (filter === 3) value += (a + b) >> 1;
			else if (filter === 4) { const p = a + b - c, pa = Math.abs(p - a), pb = Math.abs(p - b), pc = Math.abs(p - c); value += pa <= pb && pa <= pc ? a : pb <= pc ? b : c; }
			pixels[row + x] = value & 255;
		}
	}
	return { width, height, type, bpp, pixels };
}
function encodePng({ width, height, type, bpp, pixels }) {
	const stride = width * bpp, raw = Buffer.alloc((stride + 1) * height);
	for (let y = 0; y < height; y++) {
		const to = y * (stride + 1), row = y * stride;
		raw[to] = 2; // "Up" filter: compresses screenshots well.
		for (let x = 0; x < stride; x++) raw[to + 1 + x] = (pixels[row + x] - (y ? pixels[row - stride + x] : 0)) & 255;
	}
	const chunk = (name, body) => {
		const head = Buffer.alloc(4), tail = Buffer.alloc(4), named = Buffer.concat([Buffer.from(name, 'ascii'), body]);
		head.writeUInt32BE(body.length); tail.writeUInt32BE(crc32(named) >>> 0);
		return Buffer.concat([head, named, tail]);
	};
	const header = Buffer.alloc(13);
	header.writeUInt32BE(width, 0); header.writeUInt32BE(height, 4); header[8] = 8; header[9] = type;
	return Buffer.concat([Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]), chunk('IHDR', header), chunk('IDAT', deflateSync(raw, { level: 9 })), chunk('IEND', Buffer.alloc(0))]);
}
/** A full-page capture; a page taller than one capture can hold is taken in clipped bands and stitched. */
async function fullPage(page, shot, scale) {
	const { width, height } = await page.evaluate(() => ({ width: innerWidth, height: Math.ceil(document.documentElement.scrollHeight) }));
	if (height * scale <= 16000) { await page.screenshot({ ...shot, fullPage: true }); return 1; }
	const band = Math.floor(8000 / scale), parts = [];
	for (let y = 0; y < height; y += band) parts.push(decodePng(await page.screenshot({ animations: shot.animations, caret: shot.caret, fullPage: true, clip: { x: 0, y, width, height: Math.min(band, height - y) } })));
	if (parts.some(part => part.width !== parts[0].width || part.type !== parts[0].type)) throw new Error('bands differ in width or colour type');
	writeFileSync(shot.path, encodePng({ ...parts[0], height: parts.reduce((sum, part) => sum + part.height, 0), pixels: Buffer.concat(parts.map(part => part.pixels)) }));
	return parts.length;
}

/* ---------------- capture ---------------- */
const browser = await chromium.launch({ executablePath: chromiumPath || undefined, args: ['--no-sandbox'] });
const index = [];
let failures = 0;

async function settle(page) {
	await page.evaluate(() => document.fonts.ready.then(() => true));
	// Walk down the page a frame at a time, as a shopper would, so scroll-reveal sections and
	// in-view effects reach their final state, then return to the top.
	// Scrolls are instant (the theme may set scroll-behavior:smooth), and the capture waits until the
	// page is really back at the top so fixed/sticky chrome sits where a shopper first sees it.
	await page.evaluate(async () => {
		const frame = () => new Promise(done => requestAnimationFrame(() => requestAnimationFrame(done)));
		const go = top => scrollTo({ top, left: 0, behavior: 'instant' });
		const step = Math.max(200, Math.floor(innerHeight * 0.75));
		for (let y = 0; y < document.documentElement.scrollHeight; y += step) { go(y); await frame(); await new Promise(done => setTimeout(done, 40)); }
		go(document.documentElement.scrollHeight); await frame();
		go(0); await frame();
	});
	await page.waitForFunction(() => scrollY === 0, null, { timeout: 5000 }).catch(() => {});
	// Images the shopper would have loaded on the way down: load every lazy one for the capture.
	await page.evaluate(() => document.querySelectorAll('img[loading="lazy"]').forEach(img => { img.loading = 'eager'; }));
	await page.waitForFunction(() => [...document.images].every(img => img.complete), null, { timeout: 10000 }).catch(() => {});
	await page.evaluate(() => document.fonts.ready.then(() => true));
	await page.waitForTimeout(400); // Let the header settle back into its at-top state.
	return page.evaluate(() => [...document.images].filter(img => img.getClientRects().length && (!img.complete || !img.naturalWidth)).map(img => (img.currentSrc || img.src).replace(location.origin, '')).slice(0, 10));
}

const only = process.argv.slice(3);
const unknown = only.filter(name => !surfaces.some(surface => surface.name === name));
if (unknown.length) { console.error(`unknown surface(s): ${unknown.join(', ')}; known: ${surfaces.map(surface => surface.name).join(', ')}`); stop(); process.exit(2); }
for (const surface of surfaces.filter(surface => !only.length || only.includes(surface.name))) {
	for (const lang of ['en', 'ar']) {
		for (const [vpName, vp] of Object.entries(viewports)) {
			const file = `${surface.name}-${lang}-${vpName}.png`;
			const url = (lang === 'ar' ? '/ar' : '') + surface.path;
			const beauty = surface.beauty !== false;
			const context = await browser.newContext({ ...vp, reducedMotion: 'no-preference', locale: lang === 'ar' ? 'ar-OM' : 'en-GB' });
			const cookies = { ...(beauty ? { qt_beauty: '1' } : {}), ...(surface.cookies || {}) };
			await context.addCookies(Object.entries(cookies).map(([name, value]) => ({ name, value: String(value), url: BASE })));
			const page = await context.newPage();
			const errors = [];
			page.on('console', message => { if (message.type() === 'error' && !/Failed to load resource/.test(message.text())) errors.push(message.text()); });
			page.on('pageerror', error => errors.push(String(error)));
			const row = { file, surface: surface.name, lang, viewport: vpName, size: `${vp.viewport.width}x${vp.viewport.height}@${vp.deviceScaleFactor}x`, url, plugins: beauty ? 'Intelligence Lab + Qimia Beauty (staging host qimialab.qimia.om)' : 'Intelligence Lab only', cookies, capture: surface.capture === 'full' ? 'full page' : surface.capture === 'viewport' ? 'viewport' : `element ${surface.capture}`, shows: surface.shows };
			try {
				await page.goto(BASE + url, { waitUntil: 'load' });
				if (surface.ready) await page.waitForSelector(surface.ready, { timeout: 10000 });
				const missing = await settle(page);
				if (missing.length) row.imagesNotLoaded = missing;
				if (surface.search) {
					await page.click('[data-qil-search-toggle]');
					await page.waitForSelector('[data-qil-searchbar]:not([hidden]) [data-qil-search]', { timeout: 5000 });
					await page.evaluate(() => { window.__qtSearch = null; document.addEventListener('qimia:search-rendered', event => { window.__qtSearch = event.detail; }); });
					await page.locator('[data-qil-search]').pressSequentially(surface.search, { delay: 40 });
					await page.waitForFunction(() => window.__qtSearch && document.querySelector('.wd-search-results.wd-opened [data-qil-search-fallback]'), null, { timeout: 10000 });
					await page.waitForTimeout(300); // Beauty decorates the dropdown on the next frame.
					await page.waitForFunction(() => [...document.querySelectorAll('.wd-search-results img')].every(img => img.complete), null, { timeout: 5000 }).catch(() => {});
					row.state = `search "${surface.search}" → products ${JSON.stringify(await page.evaluate(() => window.__qtSearch?.products || []))}`;
				}
				if (surface.minicart) {
					await page.click('.qil-bag > a');
					await page.waitForSelector('.cart-widget-side.wd-opened', { timeout: 5000 });
					await page.waitForTimeout(700); // The drawer slides in over .5s.
					await page.waitForFunction(() => [...document.querySelectorAll('.cart-widget-side img')].every(img => img.complete), null, { timeout: 5000 }).catch(() => {});
				}
				const shot = { path: join(out, file), animations: 'disabled', caret: 'hide' };
				if (surface.capture === 'full') { const bands = await fullPage(page, shot, vp.deviceScaleFactor); if (bands > 1) row.stitched = `${bands} bands`; }
				else if (surface.capture === 'viewport') await page.screenshot(shot);
				else {
					const target = page.locator(surface.capture).first();
					await target.scrollIntoViewIfNeeded();
					await page.waitForTimeout(150);
					await target.screenshot({ ...shot, style: hideFixed });
				}
				const size = await page.evaluate(selector => {
					const node = selector ? document.querySelector(selector) : document.documentElement, box = node?.getBoundingClientRect();
					return selector ? `${Math.round(box?.width || 0)}x${Math.round(box?.height || 0)}` : `${innerWidth}x${document.documentElement.scrollHeight}`;
				}, surface.capture.startsWith('#') ? surface.capture : null);
				row.cssSize = surface.capture === 'viewport' ? `${vp.viewport.width}x${vp.viewport.height}` : size;
				row.overflowX = await page.evaluate(width => document.documentElement.scrollWidth > width + 1, vp.viewport.width);
				console.log(`  ✓ ${file}  ${row.cssSize}${errors.length ? `  (${errors.length} console error${errors.length > 1 ? 's' : ''})` : ''}`);
			} catch (error) {
				failures++;
				row.failed = String(error?.message || error).split('\n')[0];
				console.log(`  ✗ ${file}  → ${row.failed}`);
			}
			row.errors = errors.slice(0, 5);
			index.push(row);
			await context.close();
		}
	}
}
await browser.close();
stop();

const phpErrors = serverLog.split('\n').filter(line => /PHP (Fatal|Warning|Notice|Deprecated)/.test(line));
writeFileSync(join(out, 'index.json'), JSON.stringify({
	generated: new Date().toISOString(),
	command: 'node tools/screens.mjs <outdir>',
	server: 'tests/browser/server.php (qt_beauty=1 loads Qimia Beauty via tests/browser/beauty-stage.php)',
	viewports: { desktop: '1440x900 @1x', mobile: '390x844 @2x, touch' },
	motion: 'prefers-reduced-motion: no-preference; animations and caret frozen at capture',
	phpErrors: phpErrors.slice(0, 20),
	shots: index,
}, null, 2) + '\n');
console.log(`\n${index.length - failures}/${index.length} screenshots in ${out}${phpErrors.length ? `; ${phpErrors.length} PHP error line(s), see index.json` : ''}`);
process.exit(failures ? 1 : 0);
