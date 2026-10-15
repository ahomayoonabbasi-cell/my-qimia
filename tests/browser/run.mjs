// Chromium checks for Qimia Intelligence Lab 1.18 sales surfaces.
//   node tests/browser/run.mjs [results.json] [screenshot-dir]
// Starts the PHP router (real plugin over WordPress/WooCommerce doubles) and
// drives the real homepage template, mini cart and cart page in Chromium.
import { spawn } from 'node:child_process';
import { createHmac } from 'node:crypto';
import { createRequire } from 'node:module';
import { existsSync, mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
// Playwright: PLAYWRIGHT_PATH, else a local install, else the global one in this build image.
const { chromium } = (() => {
	if (process.env.PLAYWRIGHT_PATH) return require(process.env.PLAYWRIGHT_PATH);
	try { return require('playwright'); } catch (_) { return require('/opt/node22/lib/node_modules/playwright'); }
})();
const here = dirname(fileURLToPath(import.meta.url));
const repo = join(here, '..', '..');
const resultsFile = process.argv[2] || '';
// The card text floor ships with the qil.css readability block (1.18.24 and later):
// no card text under 11px on phones, under 10px on desktop.
const readabilityFloor = /readability floor/.test(readFileSync(join(repo, 'qimia-intelligence-lab-final', 'assets', 'qil.css'), 'utf8'));
const shots = process.argv[3] || join(repo, 'tests', 'browser', 'screenshots');
mkdirSync(shots, { recursive: true });
const PORT = 8766, BASE = `http://127.0.0.1:${PORT}`;

const results = { passed: 0, failed: 0, tests: [], requests: {} };
const check = (name, ok, detail = '') => {
	results[ok ? 'passed' : 'failed']++;
	results.tests.push({ name, ok: !!ok, detail: ok ? '' : String(detail).slice(0, 600) });
	console.log(`${ok ? '  ✓' : '  ✗'} ${name}${ok ? '' : `  → ${String(detail).slice(0, 300)}`}`);
};

const server = spawn('php', ['-S', `127.0.0.1:${PORT}`, join(here, 'server.php')], { cwd: repo, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = '';
server.stderr.on('data', chunk => { serverLog += chunk; });
await new Promise(resolve => setTimeout(resolve, 700));

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || undefined });
const viewports = { desktop: { width: 1440, height: 900 }, mobile: { width: 390, height: 844, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }, narrow: { width: 360, height: 780, isMobile: true, hasTouch: true, deviceScaleFactor: 2 } };

async function open(path, { viewport = 'desktop', cookies = {}, reducedMotion = 'no-preference', init = null, before = null } = {}) {
	const context = await browser.newContext({ viewport: viewports[viewport], isMobile: !!viewports[viewport].isMobile, hasTouch: !!viewports[viewport].hasTouch, deviceScaleFactor: viewports[viewport].deviceScaleFactor || 1, reducedMotion });
	if (init) await context.addInitScript(init);
	await context.addCookies(Object.entries(cookies).map(([name, value]) => ({ name, value: String(value), url: BASE })));
	const page = await context.newPage();
	const errors = [], requests = [];
	page.on('console', message => { if (message.type() === 'error' && !/Failed to load resource/.test(message.text())) errors.push(message.text()); });
	page.on('pageerror', error => errors.push(String(error)));
	page.on('request', request => { const url = request.url(); if (/wc-ajax=|wp-json/.test(url)) requests.push(url.replace(BASE, '')); });
	if (before) await before(page);
	await page.goto(BASE + path, { waitUntil: 'load' });
	return { page, context, errors, requests };
}
// Element screenshots taller than the viewport draw fixed chrome mid-image; hide it for the capture only.
const clean = { style: '.qil-skip,.qil-header,.qil-topbar,[data-qil-ai-launcher],.qil-compare-dock{visibility:hidden!important}' };
// Mobile Chromium zooms out to fit wide content (innerWidth grows with it), so
// compare with the device width and require an unzoomed visual viewport.
const noOverflow = page => page.evaluate(width => document.documentElement.scrollWidth <= width + 1 && (window.visualViewport?.scale ?? 1) >= 0.999, page.viewportSize().width);
const text = (page, selector) => page.locator(selector).first().innerText().catch(() => '');
// Cards fully inside a rail's visible box: the carousel's "per view".
const perView = (page, rail) => page.evaluate(selector => {
	const node = document.querySelector(selector), box = node?.getBoundingClientRect();
	if (!box) return -1;
	return [...node.querySelectorAll(':scope > .qil-product-card')].filter(card => { const r = card.getBoundingClientRect(); return r.width > 0 && r.left >= box.left - 1 && r.right <= box.right + 1; }).length;
}, rail);
const flashData = page => page.evaluate(() => { try { return JSON.parse(document.querySelector('script[data-qil-flash-data]').textContent); } catch (_) { return null; } });

/* 1.18.24 design layer: helpers for the card, label and motion checks. */
// Walk down the page with instant scrolls (the theme scrolls smoothly), as a shopper would, then back to the top.
const walk = page => page.evaluate(async () => {
	const frame = () => new Promise(done => requestAnimationFrame(() => requestAnimationFrame(done)));
	const go = top => scrollTo({ top, left: 0, behavior: 'instant' });
	const step = Math.max(200, Math.floor(innerHeight * 0.75));
	for (let y = 0; y < document.documentElement.scrollHeight; y += step) { go(y); await frame(); await new Promise(done => setTimeout(done, 40)); }
	go(document.documentElement.scrollHeight); await frame();
	go(0); await frame();
});
// Bring a block into view and wait until its cards finished their entrance (for screenshots).
const settleCards = async (page, selector) => {
	await page.locator(selector).first().scrollIntoViewIfNeeded().catch(() => {});
	await page.waitForFunction(selector => !document.querySelector(selector)?.querySelector('[data-qil-reveal]'), selector, { timeout: 2500 }).catch(() => {});
};
// Card text: anything under the floor, and buy labels that wrap (cards in the Lab, or in any shell).
const cardType = (page, floor, scope = '#qimia-lab') => page.evaluate(([floor, scope]) => {
	const cards = [...document.querySelectorAll(`${scope} .qil-product-card`)].filter(card => card.getClientRects().length);
	const tiny = [];
	for (const card of cards) for (const node of card.querySelectorAll('*')) {
		if ([...node.childNodes].some(child => child.nodeType === 3 && child.textContent.trim()) && node.getClientRects().length && parseFloat(getComputedStyle(node).fontSize) < floor) tiny.push(`${node.className || node.tagName} ${getComputedStyle(node).fontSize}`);
	}
	const wrapped = cards.flatMap(card => [...card.querySelectorAll('.qil-buy span')]).filter(span => span.getClientRects().length > 1 || span.getBoundingClientRect().height > parseFloat(getComputedStyle(span).fontSize) * 1.9).map(span => span.textContent.trim());
	return { cards: cards.length, tiny: [...new Set(tiny)].slice(0, 6), wrapped: [...new Set(wrapped)] };
}, [floor, scope]);
// Card buttons fit on one line: a visible label span has one line box and no clipped text; a short
// label drawn by ::before (data-mobile-label) is measured with canvas against the room its button
// leaves it (the grid track it sits in, or the width less padding, visible icons and gaps).
const labelFit = page => page.evaluate(() => {
	const canvas = document.createElement('canvas').getContext('2d'), px = value => parseFloat(value) || 0, bad = [];
	let buttons = 0;
	for (const card of document.querySelectorAll('.qil-product-card')) {
		if (!card.getClientRects().length) continue;
		for (const button of card.querySelectorAll('.qil-buy, .qil-ask-product')) {
			if (!button.getClientRects().length) continue;
			buttons++;
			const cs = getComputedStyle(button), kind = button.classList.contains('qil-ask-product') ? 'ask' : button.classList.contains('qil-buy-secondary') ? 'secondary' : 'add';
			for (const span of button.querySelectorAll(':scope > span')) if (span.getClientRects().length && (span.getClientRects().length > 1 || span.scrollWidth > span.clientWidth + 1)) bad.push(`${kind} "${span.textContent.trim()}" ${span.scrollWidth}/${span.clientWidth}px`);
			const before = getComputedStyle(button, '::before'), label = /^".+"$/.test(before.content) ? before.content.slice(1, -1) : '';
			if (!label || before.display === 'none') continue;
			canvas.font = `${before.fontStyle} ${before.fontWeight} ${before.fontSize} ${before.fontFamily}`;
			const need = canvas.measureText(label).width + px(before.letterSpacing) * [...label].length;
			const icons = [...button.querySelectorAll(':scope > svg')].filter(svg => svg.getClientRects().length), column = parseInt(before.gridColumnStart, 10);
			const room = cs.display.includes('grid') && column > 0
				? px(cs.gridTemplateColumns.split(/\s+/)[column - 1])
				: button.clientWidth - px(cs.paddingLeft) - px(cs.paddingRight) - icons.reduce((sum, svg) => sum + svg.getBoundingClientRect().width, 0) - px(cs.columnGap) * icons.length;
			if (need > room + 0.5) bad.push(`${kind} "${label}" needs ${need.toFixed(1)}px, has ${room.toFixed(1)}px`);
		}
	}
	return { buttons, bad: [...new Set(bad)].slice(0, 6) };
});
// Every card's buy and ask buttons name their product (the short labels are visual only).
const unnamedButtons = page => page.evaluate(() => [...document.querySelectorAll('.qil-product-card')].flatMap(card => {
	const name = card.querySelector('.qil-product-info h3')?.textContent.replace(/\s+/g, ' ').trim() || '';
	return [...card.querySelectorAll('.qil-buy, .qil-ask-product')].filter(button => !name || !(button.getAttribute('aria-label') || '').includes(name)).map(button => `${button.className}: "${button.getAttribute('aria-label') || ''}" / "${name}"`);
}).slice(0, 4));
// Label in Name (WCAG 2.5.3): every label a card button can show is part of its accessible name, so a
// voice-control user can say the words they see. The forms are the full label (its span: "Add to cart",
// "Buy again", or "Adding…" while busy), the short one (data-mobile-label, drawn by ::before on narrow
// cards) and whatever ::before draws now, whatever the card's width. Case and spacing do not count.
const labelInName = page => page.evaluate(() => {
	const norm = value => String(value || '').replace(/\s+/g, ' ').trim().toLocaleLowerCase();
	const bad = [];
	let buttons = 0, repeat = 0;
	for (const button of document.querySelectorAll('.qil-product-card .qil-buy, .qil-product-card .qil-ask-product')) {
		buttons++;
		const kind = button.hasAttribute('data-qil-repeat-buy') ? 'repeat' : button.classList.contains('qil-ask-product') ? 'ask' : 'buy';
		if (kind === 'repeat') repeat++;
		const name = norm(button.getAttribute('aria-label') || button.textContent), before = getComputedStyle(button, '::before').content;
		const forms = [...[...button.querySelectorAll(':scope > span')].map(span => span.textContent), button.dataset.mobileLabel, /^".+"$/.test(before) ? before.slice(1, -1) : ''];
		for (const form of new Set(forms.map(norm).filter(Boolean))) if (!name.includes(form)) bad.push(`${kind} "${form}" is not in "${name}"`);
	}
	return { buttons, repeat, bad: [...new Set(bad)].slice(0, 6) };
});
// Signed-in pages: bring "Your choices" into view so its Buy again cards (qil_personalize) render.
const buyAgainCards = async page => {
	await page.locator('[data-qil-personal-anchor]').first().scrollIntoViewIfNeeded().catch(() => {});
	return page.waitForSelector('.qil-product-card [data-qil-repeat-buy]', { timeout: 8000 }).then(() => true, () => false);
};
// Keyframes of the 1.18.24 motion layer: none may run under reduced motion.
const motionNames = ['qil-card-in', 'qil-pop', 'qil-added-ring', 'qil-sweep', 'qil-sweep-rtl', 'qil-fill', 'qil-float-y', 'qil-sheet-in', 'qbx-busy'];
const reducedState = page => page.evaluate(names => ({
	armed: document.querySelectorAll('[data-qil-reveal="armed"]').length,
	faded: [...document.querySelectorAll('.qil-product-card')].filter(card => getComputedStyle(card).opacity !== '1').length,
	running: [...new Set(document.getAnimations().map(animation => animation.animationName).filter(name => names.includes(name)))],
}), motionNames);

try {
	/* ---------------- Guest homepage ---------------- */
	for (const [viewport, path] of [['desktop', '/'], ['mobile', '/'], ['desktop', '/ar/'], ['mobile', '/ar/']]) {
		const label = `guest ${path === '/' ? 'EN' : 'AR'} ${viewport}`;
		console.log(`\n${label}`);
		const { page, context, errors, requests } = await open(path, { viewport });
		const flash = page.locator('[data-qil-flash-drop]');
		await page.waitForSelector('.qil-flash-rail .qil-product-card', { timeout: 6000 });
		const armed = await page.evaluate(() => { const cards = [...document.querySelectorAll('.qil-product-card[data-qil-reveal="armed"]')]; return { armed: cards.length, aboveFold: cards.filter(card => card.getBoundingClientRect().top < innerHeight).map(card => card.dataset.productId) }; });
		check(`${label}: first load: cards below the fold wait for their entrance, none above the fold is hidden`, armed.armed > 0 && armed.aboveFold.length === 0, JSON.stringify(armed));
		check(`${label}: flash drop renders 10 real products with the shared card`, (await page.locator('.qil-flash-rail .qil-product-card').count()) === 10);
		const badges = await page.locator('.qil-flash-rail .qil-product-card').evaluateAll(cards => cards.map(card => {
			const stack = card.querySelector('.qil-label-stack'), sale = stack?.querySelector('.qil-sale-badge');
			return { role: !!stack?.querySelector('.qil-product-badge'), sales: stack ? stack.querySelectorAll('.qil-sale-badge').length : 0, rolling: !!stack?.classList.contains('has-multiple'), animation: sale ? getComputedStyle(sale).animationName : '', opacity: sale ? getComputedStyle(sale).opacity : '', text: sale?.textContent || '' };
		}));
		check(`${label}: each flash card shows one discount badge, still and in view (no role label taking turns with it)`, badges.length === 10 && badges.every(b => !b.role && b.sales === 1 && !b.rolling && b.animation === 'none' && b.opacity === '1' && /−\d+(%|٪)/.test(b.text)), JSON.stringify(badges.slice(0, 3)));
		// Since 1.18.12 the homepage shows evergreen offers (best sellers first); the timed drop with its clock is the shortcode/category variant, covered by the PHP scenarios.
		check(`${label}: homepage offers are evergreen: no timer, no end time`, (await page.locator('[data-qil-flash-clock]').count()) === 0 && (await flash.getAttribute('data-qil-flash-end')) === null);
		check(`${label}: header says FLASH SALE — BEST SELLERS FIRST`, /FLASH SALE|تخفيضات سريعة/.test(await text(page, '#qil-flash-title')) && /BEST SELLERS FIRST|الأكثر مبيعاً أولاً/.test(await text(page, '#qil-flash-title')), await text(page, '#qil-flash-title'));
		if (path.startsWith('/ar')) check(`${label}: Arabic uses the Western digits of the prices throughout the drop`, !/[٠-٩]/.test(await page.locator('#qil-flash-drop').innerText()));
		check(`${label}: the drop shows ${viewport === 'desktop' ? 4 : 2} theme cards per view, the rest on the carousel`, (await perView(page, '.qil-flash-rail')) === (viewport === 'desktop' ? 4 : 2), await perView(page, '.qil-flash-rail'));
		const chips = await page.locator('.qil-flash-rail .qil-product-card').evaluateAll(cards => cards.map(card => ({ id: card.dataset.productId, chip: card.querySelector('.qil-product-info h3 + [data-qil-flash-stock] > b')?.textContent || '', onImage: !!card.querySelector('.qil-product-image [data-qil-flash-stock]'), height: Math.round(card.querySelector('[data-qil-flash-stock]')?.getBoundingClientRect().height || 0) })));
		check(`${label}: every flash card has its stock line right under the product name (not on the image)`, chips.length === 10 && chips.every(row => row.chip && !row.onImage), JSON.stringify(chips.filter(row => !row.chip || row.onImage)));
		check(`${label}: the stock lines share one height (cards keep one shape)`, new Set(chips.map(row => row.height)).size === 1, JSON.stringify(chips.map(row => row.height)));
		check(`${label}: best sellers lead: the rail follows total sales (201 → 210), discounts and low stock never reorder it`, chips.map(row => row.id).join() === '201,202,203,204,205,206,207,208,209,210', chips.map(row => row.id).join());
		check(`${label}: the kicker counts what is almost gone`, /ALMOST GONE|على وشك النفاد/.test(await text(page, '.qil-flash-kicker')));
		check(`${label}: flash cards use the theme's own Add to cart button`, (await page.locator('.qil-flash-rail .qil-product-card .qil-buy').count()) === 10);
		const floor = viewport === 'desktop' ? 10 : 11;
		const type = await cardType(page, floor);
		check(`${label}: product cards render and their buttons stay on one line`, type.cards > 0 && type.wrapped.length === 0, JSON.stringify(type));
		if (readabilityFloor) check(`${label}: product cards are readable (no card text under ${floor}px)`, type.tiny.length === 0, JSON.stringify(type.tiny));
		else console.log(`  - ${label}: card text-size floor skipped, qil.css in this build has no readability floor (smallest: ${type.tiny.join(', ') || 'none'})`);
		const unnamed = await unnamedButtons(page);
		check(`${label}: every card's buy and ask buttons carry the product name in their accessible name`, unnamed.length === 0, JSON.stringify(unnamed));
		const named = await labelInName(page);
		check(`${label}: every label a card's buy and ask buttons show, full or short, is part of their accessible name`, named.buttons > 0 && named.bad.length === 0, JSON.stringify(named));
		if (path.startsWith('/ar')) check(`${label}: Arabic cards use the Western digits of their prices`, !/[٠-٩]/.test((await page.locator('#qimia-lab .qil-product-card').allInnerTexts()).join(' ')));
		const edge = await page.evaluate(() => {
			const flash = document.querySelector('#qil-flash-drop'), match = document.querySelector('#qil-match'), stage = flash?.querySelector('.qil-flash-stage');
			const shadow = stage ? getComputedStyle(stage).boxShadow : '';
			return { outerShadow: shadow.split(/,(?![^(]*\))/).some(part => part.trim() !== 'none' && !part.includes('inset')), prev: flash?.previousElementSibling?.id, ground: getComputedStyle(flash).backgroundColor, fade: getComputedStyle(match).backgroundImage, gap: Math.round(stage.getBoundingClientRect().top - match.getBoundingClientRect().bottom) };
		});
		check(`${label}: the flash stage follows the goal engine, casts no shadow and sits on the ice tone the goal section fades into (no two-tone edge)`, edge.prev === 'qil-match' && !edge.outerShadow && edge.ground === 'rgb(244, 250, 249)' && /rgb\(244, 250, 249\) 100%\)$/.test(edge.fade), JSON.stringify(edge));
		check(`${label}: compact space between Show more and the stage (${edge.gap}px)`, edge.gap >= 12 && edge.gap <= 32, edge.gap);
		const light = await page.evaluate(() => {
			const drift = [];
			const walk = (rules, media) => { for (const rule of rules) { if (rule.cssRules && rule.media) walk(rule.cssRules, rule.media.mediaText); else if (/qil-flash-aurora/.test(rule.selectorText || '') && /qil-flash-drift/.test(rule.cssText)) drift.push(media); } };
			for (const sheet of document.styleSheets) { try { walk(sheet.cssRules, ''); } catch (_) { /* cross-origin */ } }
			return { filter: getComputedStyle(document.querySelector('.qil-flash-aurora')).filter, drift };
		});
		check(`${label}: the flash stage has no blur filter, and its light drifts only on screens with a mouse`, light.filter === 'none' && light.drift.length > 0 && light.drift.every(media => /hover:\s*hover/.test(media) && /pointer:\s*fine/.test(media)), JSON.stringify(light));
		await page.waitForSelector('#qil-stacks .qil-product-card', { timeout: 6000 }).catch(() => {});
		check(`${label}: stacks are theme product cards with the theme's Add to cart`, (await page.locator('#qil-stacks .qil-product-card').count()) === 3 && (await page.locator('#qil-stacks .qil-product-card .qil-buy').count()) === 3);
		const stackCard = await page.locator('#qil-stacks .qil-product-card[data-product-id="301"]').evaluate(card => ({
			reward: card.querySelector('.qil-product-image .qil-bundle-reward')?.textContent.replace(/\s+/g, ' ').trim() || '',
			roleBadge: !!card.querySelector('.qil-product-badge'),
			facts: [...card.querySelectorAll('.qil-fact')].map(f => `${f.querySelector('.qil-fact-label')?.textContent}: ${f.querySelector('.qil-fact-value')?.textContent}`),
			separately: card.querySelector('.qil-stack-separately')?.textContent || '',
		})).catch(() => null);
		check(`${label}: stack card: the 1.18.0 cashback badge, what is inside, saving, separately`, !!stackCard && !stackCard.roleBadge && (path === '/' ? stackCard.reward === 'CASHBACK +3 OMR' && stackCard.facts[0] === 'Inside: Protein + Creatine' && /^You save: 0\.980/.test(stackCard.facts[1]) && /Separately\s+25\.880/.test(stackCard.separately) : /كاش باك/.test(stackCard.reward) && /بداخلها/.test(stackCard.facts[0])), JSON.stringify(stackCard));
		const corners = await page.locator('#qil-stacks .qil-product-card[data-product-id="303"]').evaluate((card, rtl) => {
			const image = card.querySelector('.qil-product-image').getBoundingClientRect();
			const sale = card.querySelector('.qil-sale-badge'), reward = card.querySelector('.qil-bundle-reward');
			if (!sale || !reward) return { sale: !!sale, reward: !!reward };
			const a = sale.getBoundingClientRect(), b = reward.getBoundingClientRect();
			const overlap = !(a.right <= b.left || b.right <= a.left || a.bottom <= b.top || b.bottom <= a.top);
			const middle = image.left + image.width / 2;
			return { sale: true, reward: true, overlap, saleStart: rtl ? a.left > middle : a.right < middle, rewardEnd: rtl ? b.right < middle : b.left > middle, still: getComputedStyle(sale).animationName === 'none' && getComputedStyle(sale).opacity === '1' };
		}, path !== '/');
		check(`${label}: on a discounted stack the discount keeps its corner, still, and the cashback sits in the other corner without overlap`, corners.sale && corners.reward && !corners.overlap && corners.saleStart && corners.rewardEnd && corners.still, JSON.stringify(corners));
		const stackWidth = await page.evaluate(() => { const rail = document.querySelector('.qil-stacks-rail'), card = rail?.querySelector('.qil-product-card'); return rail && card ? card.getBoundingClientRect().width / rail.getBoundingClientRect().width : 0; });
		check(`${label}: stack cards are sized ${viewport === 'desktop' ? 'four' : 'two'} per view`, viewport === 'desktop' ? stackWidth > 0.22 && stackWidth < 0.26 : stackWidth > 0.46 && stackWidth < 0.51, stackWidth);
		check(`${label}: member hub stays hidden for guests`, await page.locator('[data-qil-member]').isHidden());
		check(`${label}: no private request for guests (no qil_member)`, !requests.some(url => url.includes('qil_member')), requests.join(' '));
		check(`${label}: no horizontal overflow`, await noOverflow(page));
		check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
		if (path === '/ar/') check(`${label}: RTL shell`, (await page.getAttribute('#qimia-lab', 'dir')) === 'rtl');
		results.requests[label] = requests;
		await flash.scrollIntoViewIfNeeded();
		const entered = await page.waitForFunction(() => {
			const cards = [...document.querySelectorAll('.qil-flash-rail .qil-product-card')];
			return cards.length === 10 && cards.every(card => card.classList.contains('is-visible') && !card.hasAttribute('data-qil-reveal') && getComputedStyle(card).opacity === '1');
		}, null, { timeout: 1500 }).then(() => true, () => false);
		check(`${label}: once the drop scrolls into view its cards finish their entrance within 1.5s (visible, settled, opaque)`, entered, JSON.stringify(await page.locator('.qil-flash-rail .qil-product-card').evaluateAll(cards => cards.map(card => `${card.className} ${card.getAttribute('data-qil-reveal')} ${getComputedStyle(card).opacity}`).slice(0, 3))));
		if (viewport === 'mobile') {
			// The 34px arrows take a 44px touch target: centre ±21px still lands on the same button.
			// Measured before any element screenshot: in this Chromium a screenshot drops the touch
			// emulation, and (pointer: coarse), which carries the target, stops matching.
			const arrows = await page.evaluate(async () => {
				const frame = () => new Promise(done => requestAnimationFrame(() => requestAnimationFrame(done)));
				const out = [];
				if (!matchMedia('(pointer: coarse)').matches) return [{ coarse: false }];
				for (const button of document.querySelectorAll('[data-qil-rail-nav="flash-drop"] button, [data-qil-rail-nav="stacks"] button')) {
					for (let i = 0; i < 2; i++) { button.scrollIntoView({ block: 'center', behavior: 'instant' }); await frame(); }
					const box = button.getBoundingClientRect(), x = box.left + box.width / 2, y = box.top + box.height / 2;
					const hits = [[x - 21, y], [x + 21, y], [x, y - 21], [x, y + 21]].map(([px, py]) => document.elementFromPoint(px, py)?.closest('[data-qil-rail-prev],[data-qil-rail-next]') === button);
					out.push({ nav: button.parentElement.dataset.qilRailNav, size: `${Math.round(box.width)}x${Math.round(box.height)}`, hits: hits.map(Number).join('') });
				}
				scrollTo({ top: 0, behavior: 'instant' });
				return out;
			});
			check(`${label}: rail arrows have a 44px touch target`, arrows.length === 4 && arrows.every(row => row.hits === '1111'), JSON.stringify(arrows));
		}
		await flash.screenshot({ path: join(shots, `flash-${path === '/' ? 'en' : 'ar'}-${viewport}.png`), ...clean });
		await settleCards(page, '#qil-stacks');
		await page.locator('#qil-stacks').screenshot({ path: join(shots, `stacks-${path === '/' ? 'en' : 'ar'}-${viewport}.png`), ...clean });
		if (viewport === 'desktop') {
			await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
			await page.waitForTimeout(400);
			check(`${label}: flash animation pauses off screen`, !(await flash.evaluate(node => node.classList.contains('is-live'))));
		}
		await context.close();
	}

	/* ---------------- Reduced motion ---------------- */
	{
		const { page, context } = await open('/', { reducedMotion: 'reduce', cookies: { qt_cart: '101:1011:1' } });
		await page.waitForSelector('.qil-flash-rail .qil-product-card');
		const running = await page.locator('.qil-flash-aurora').evaluate(node => getComputedStyle(node).animationName);
		check('reduced motion: no aurora animation', running === 'none', running);
		const sheen = await page.locator('.qil-boost-meter > span').first().evaluate(node => getComputedStyle(node, '::after').animationName);
		check('reduced motion: no meter sheen either (pseudo-elements included)', sheen === 'none', sheen);
		await walk(page);
		const still = await reducedState(page);
		check('reduced motion: after a scroll down the page no card waits for an entrance, every card is opaque and no 1.18.24 motion runs', still.armed === 0 && still.faded === 0 && still.running.length === 0, JSON.stringify(still));
		await context.close();
	}
	{
		const { page, context } = await open('/ar/', { viewport: 'mobile', reducedMotion: 'reduce' });
		await page.waitForSelector('.qil-flash-rail .qil-product-card');
		await walk(page);
		const still = await reducedState(page);
		check('reduced motion AR mobile: no card waits for an entrance, every card is opaque and no 1.18.24 motion runs', still.armed === 0 && still.faded === 0 && still.running.length === 0, JSON.stringify(still));
		await context.close();
	}

	/* ---------------- Optimizer-proof: missing page data, reversed scripts ---------------- */
	for (const [name, rewrite] of [
		['inline page data removed (an optimizer delayed it)', html => html.replace(/<script>window\.QIL_BOOST = [\s\S]*?<\/script>/, '')],
		['Qimia scripts run in the wrong order', html => {
			const main = html.match(/<script src="[^"]*qil\.min\.js[^"]*"><\/script>/)?.[0], boost = html.match(/<script src="[^"]*qil-boost\.min\.js[^"]*"><\/script>/)?.[0];
			return main && boost ? html.replace(main, '__MAIN__').replace(boost, main).replace('__MAIN__', boost) : html;
		}],
	]) {
		for (const viewport of ['desktop', 'mobile']) {
			const context = await browser.newContext({ viewport: viewports[viewport], isMobile: !!viewports[viewport].isMobile, hasTouch: !!viewports[viewport].hasTouch });
			const page = await context.newPage();
			const errors = [];
			page.on('pageerror', error => errors.push(String(error)));
			await page.route(`${BASE}/`, async route => { const response = await route.fetch(); await route.fulfill({ response, body: rewrite(await response.text()) }); });
			await page.goto(`${BASE}/`);
			await page.waitForSelector('.qil-flash-rail .qil-product-card', { timeout: 8000 }).catch(() => {});
			check(`flash drop still shows on ${viewport} when ${name}`, (await page.locator('.qil-flash-rail .qil-product-card').count()) === 10 && await page.locator('[data-qil-flash-drop]').isVisible(), errors.join(' | '));
			await context.close();
		}
	}

	/* ---------------- Signed-in homepage: wallet + Running low ---------------- */
	for (const viewport of ['desktop', 'mobile']) {
		const label = `member ${viewport}`;
		console.log(`\n${label}`);
		const { page, context, errors, requests } = await open('/', { viewport, cookies: { qt_user: 7 } });
		await page.waitForSelector('[data-qil-member]:not([hidden])', { timeout: 8000 });
		check(`${label}: wallet says "You have 9 OMR cashback"`, /You have 9 OMR cashback/.test(await text(page, '[data-qil-wallet-title]')), await text(page, '[data-qil-wallet-title]'));
		check(`${label}: best coupon and its real expiry`, /4 OMR is waiting for you/.test(await text(page, '[data-qil-wallet-sub]')) && /5 days/.test(await text(page, '[data-qil-wallet-sub]')), await text(page, '[data-qil-wallet-sub]'));
		check(`${label}: no coupon code is shown`, !/CB-7/.test(await page.content()));
		const credits = await page.locator('[data-qil-wallet-credits] li').evaluateAll(items => items.map(item => item.textContent.replace(/\s+/g, ' ').trim()));
		check(`${label}: each credit listed with its days left (first to be used highlighted)`, credits.length === 3 && /^4 OMR 5 days left$/.test(credits[0]) && /^3 OMR 20 days left$/.test(credits[1]) && /^2 OMR Min\. order 10 OMR$/.test(credits[2]) && (await page.locator('[data-qil-wallet-credits] li.is-next').count()) === 1, JSON.stringify(credits));
		check(`${label}: top bar turns into the cashback reminder`, /4 OMR cashback waiting for you/.test(await text(page, '[data-qil-delivery-line]')));
		check(`${label}: Running low lists 2 exact previous items`, (await page.locator('.qil-running-row').count()) === 2);
		check(`${label}: exact flavour shown`, /Fruit Punch/.test(await text(page, '[data-qil-running-list]')));
		check(`${label}: best ways to use it (cards with reasons)`, (await page.locator('[data-qil-wallet-grid] .qil-product-card').count()) >= 2);
		const reasons = await page.locator('[data-qil-wallet-grid] .qil-product-card').evaluateAll(cards => cards.map(card => {
			const node = card.querySelector('.qil-wallet-reason');
			return node ? { text: node.textContent, clipped: node.scrollWidth > node.clientWidth + 1 } : null;
		}));
		check(`${label}: every pick says why, in full (no clipped label)`, reasons.every(row => row && /You compared|You viewed|Pairs with/.test(row.text) && !row.clipped), JSON.stringify(reasons));
		check(`${label}: the picks show ${viewport === 'desktop' ? 4 : 2} theme cards per view`, (await perView(page, '[data-qil-wallet-grid]')) === (viewport === 'desktop' ? 4 : 2), await perView(page, '[data-qil-wallet-grid]'));
		check(`${label}: Buy again is the theme's Add to cart button`, (await page.locator('.qil-running .qil-buy[data-qil-running-buy]').count()) === 2);
		const memberEdge = await page.evaluate(() => { const member = document.querySelector('#qil-member'), prev = member?.previousElementSibling; return { image: getComputedStyle(member).backgroundImage, ground: getComputedStyle(member).backgroundColor, prev: prev ? getComputedStyle(prev).backgroundColor : '' }; });
		// 1.18.5: the member area continues on the flash sale's ice ground.
		check(`${label}: the wallet section continues on the ice ground of the section above (no two-tone edge)`, memberEdge.ground === memberEdge.prev && memberEdge.prev === 'rgb(244, 250, 249)' && memberEdge.image === 'none', JSON.stringify(memberEdge));
		check(`${label}: exactly one private request (qil_member)`, requests.filter(url => url.includes('qil_member')).length === 1, requests.join(' '));
		if (viewport === 'mobile') {
			// Phones show the role label and the discount side by side, still (the desktop roll stays).
			const stacks = await page.evaluate(() => [...document.querySelectorAll('.qil-product-card .qil-label-stack.has-multiple')].filter(stack => stack.getClientRects().length).map(stack => {
				const parts = [...stack.querySelectorAll(':scope > .qil-product-badge, :scope > .qil-sale-badge')].map(node => ({ style: getComputedStyle(node), box: node.getBoundingClientRect() }));
				const [a, b] = parts.map(part => part.box);
				return { parts: parts.length, still: parts.every(part => part.style.animationName === 'none'), shown: parts.every(part => part.style.opacity === '1' && part.style.visibility === 'visible' && part.style.display !== 'none' && part.box.width > 0), apart: !!a && !!b && (a.right <= b.left || b.right <= a.left || a.bottom <= b.top || b.bottom <= a.top) };
			}));
			check(`${label}: on a phone the role label and the discount sit side by side, still and both readable`, stacks.length > 0 && stacks.every(row => row.parts === 2 && row.still && row.shown && row.apart), JSON.stringify(stacks));
		}
		const again = await buyAgainCards(page), named = await labelInName(page);
		check(`${label}: every label a card's buy, Buy again and ask buttons show, full or short, is part of their accessible name`, again && named.repeat > 0 && named.bad.length === 0, JSON.stringify(named));
		await settleCards(page, '[data-qil-wallet-grid]');
		await page.locator('[data-qil-member]').screenshot({ path: join(shots, `member-${viewport}.png`), ...clean });
		if (viewport === 'desktop') {
			await page.click('[data-qil-wallet-apply]');
			await page.waitForFunction(() => document.querySelector('[data-qil-wallet-status]')?.textContent.length > 0, null, { timeout: 5000 });
			check(`${label}: Shop with cashback (empty cart) → ready, applies automatically`, /applies automatically/.test(await text(page, '[data-qil-wallet-status]')), await text(page, '[data-qil-wallet-status]'));
			await page.click('[data-qil-running-buy]');
			await page.waitForSelector('.qil-running-row.is-added', { timeout: 5000 }).catch(() => {});
			check(`${label}: Buy again adds the exact item in one tap`, (await page.locator('.qil-running-row.is-added').count()) === 1, await text(page, '.qil-running-note'));
			const cookies = await context.cookies();
			const cart = decodeURIComponent(cookies.find(c => c.name === 'qt_cart')?.value || '');
			check(`${label}: cart holds the exact product (and the pending cashback applied)`, /^104:0:1/.test(cart) && /cb-7-a/i.test(decodeURIComponent(cookies.find(c => c.name === 'qt_coupons')?.value || '')), `${cart} ${JSON.stringify(cookies.map(c => c.name + '=' + c.value))}`);
		}
		check(`${label}: no horizontal overflow`, await noOverflow(page));
		check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
		await context.close();
	}

	/* ---------------- Mini cart drawer ---------------- */
	for (const [viewport, path] of [['desktop', '/'], ['mobile', '/'], ['mobile', '/ar/']]) {
		const label = `mini cart ${path === '/' ? 'EN' : 'AR'} ${viewport}`;
		console.log(`\n${label}`);
		const { page, context, errors } = await open(path, { viewport, cookies: { qt_cart: '101:1011:1' } });
		await page.click('.qil-bag > a');
		await page.waitForSelector('.cart-widget-side.wd-opened', { timeout: 4000 });
		await page.waitForTimeout(450);
		const ladder = await text(page, '.qil-boost-mini');
		check(`${label}: header leads with this cart's reward, then "Add 0.101 more → 3 OMR cashback"`, path === '/' ? /Cashback on this order\s*2 OMR/.test(ladder) && /Add\s+0\.101.*more\s*→\s*3 OMR cashback/s.test(ladder) : /أضف/.test(ladder) && /0\.101/.test(ladder), ladder);
		check(`${label}: three related picks that reach the next band`, (await page.locator('.cart-widget-side .qil-boost-pick').count()) === 3);
		const fit = await page.evaluate(() => {
			const drawer = document.querySelector('.cart-widget-side'), box = drawer.getBoundingClientRect();
			const rows = [...drawer.querySelectorAll('.qil-boost-ladder, .qil-boost-pick, .qil-boost-add')].map(node => node.getBoundingClientRect());
			return { width: Math.round(box.width), overflow: drawer.scrollWidth > drawer.clientWidth + 1, outside: rows.filter(r => r.left < box.left - 1 || r.right > box.right + 1).length };
		});
		check(`${label}: ladder and picks fit the ${viewport === 'desktop' ? '340' : '300'}px WoodMart drawer`, !fit.overflow && fit.outside === 0 && fit.width === (viewport === 'desktop' ? 340 : 300), JSON.stringify(fit));
		const buttons = await page.evaluate(() => {
			const style = node => { if (!node) return null; const cs = getComputedStyle(node), after = getComputedStyle(node, '::after'); return { image: cs.backgroundImage, radius: cs.borderTopLeftRadius, shadow: cs.boxShadow !== 'none', color: cs.color, sweep: after.content !== 'none' && after.backgroundImage.includes('gradient') }; };
			return { drawer: style(document.querySelector('.cart-widget-side .qil-boost-add')), theme: style(document.querySelector('.qil-flash-rail .qil-buy')) };
		});
		check(`${label}: drawer Add buttons are the homepage's Add to cart (gradient, pill, shadow, light sweep)`, !!buttons.drawer && !!buttons.theme && buttons.drawer.image === buttons.theme.image && buttons.drawer.color === buttons.theme.color && parseFloat(buttons.drawer.radius) >= 99 && buttons.drawer.shadow && buttons.drawer.sweep, JSON.stringify(buttons));
		if (path !== '/') check(`${label}: Arabic ladder and picks use the Western digits of the prices beside them`, !/[٠-٩]/.test((await page.locator('.cart-widget-side .qil-boost').allInnerTexts()).join(' ')));
		await page.locator('.cart-widget-side').screenshot({ path: join(shots, `minicart-${path === '/' ? 'en' : 'ar'}-${viewport}.png`) });
		if (path === '/' && viewport === 'desktop') {
			await page.click('.cart-widget-side .qil-boost-pick .ajax_add_to_cart >> nth=0');
			await page.waitForFunction(() => /4\.121/.test(document.querySelector('.qil-boost-mini')?.textContent || ''), null, { timeout: 5000 }).catch(() => {});
			const after = await text(page, '.qil-boost-mini');
			check(`${label}: one tap adds creatine; ladder moves on (add 4.121 → 4 OMR)`, /4\.121/.test(after) && /4 OMR/.test(after), after);
		}
		check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
		await context.close();
	}

	/* ---------------- Quick view from a variable pick ---------------- */
	{
		console.log('\nvariable pick → Quick View');
		const { page, context, errors } = await open('/', { cookies: { qt_cart: '102:0:1' } });
		await page.click('.qil-bag > a');
		await page.waitForSelector('.cart-widget-side.wd-opened');
		const options = page.locator('.cart-widget-side .qil-boost-add.is-options').first();
		check('variable pick offers "Options" (no product page needed)', await options.count() === 1);
		await options.click();
		await page.waitForSelector('[data-qil-quick-view-modal]:not([hidden]) [data-qil-variation-attribute]', { timeout: 5000 }).catch(() => {});
		check('Quick View opens in place with the option picker', (await page.locator('[data-qil-quick-view-modal]:not([hidden]) [data-qil-variation-attribute]').count()) > 0, await text(page, '[data-qil-quick-view-modal]'));
		const select = page.locator('[data-qil-quick-view-modal] [data-qil-variation-attribute]').first();
		if (await select.count()) {
			await select.selectOption({ index: 1 });
			await page.waitForSelector('[data-qil-qv-add][aria-disabled="false"]', { timeout: 5000 }).catch(() => {});
			await page.click('[data-qil-qv-add][aria-disabled="false"]').catch(() => {});
			await page.waitForTimeout(900);
			const cart = decodeURIComponent((await context.cookies()).find(c => c.name === 'qt_cart')?.value || '');
			check('chosen variation is added to the order', /101:101\d:1/.test(cart), cart);
		}
		check('no JavaScript errors (quick view flow)', errors.length === 0, errors.join(' | '));
		await context.close();
	}

	/* ---------------- Shared card in a .qil-shell outside #qimia-lab (Beauty shelf) ---------------- */
	{
		console.log('\nshared card outside #qimia-lab');
		const { page, context, errors, requests } = await open('/');
		await page.waitForSelector('.qil-flash-rail .qil-product-card', { timeout: 6000 });
		// Like Beauty: after qil.js ran, render into a theme wrapper, mark it .qil-shell, then QILCards.refresh().
		const shelf = () => page.evaluate(() => {
			const product = (window.QIMIA_LAB?.products || []).find(p => p?.purchase?.action === 'select');
			if (!product) return '';
			let grid = document.querySelector('[data-qt-outside-shelf]');
			if (!grid) { grid = document.createElement('div'); grid.dataset.qtOutsideShelf = ''; document.body.append(grid); }
			grid.innerHTML = '<div class="product-grid-item"><div class="product-wrapper"></div></div>';
			const wrapper = grid.querySelector('.product-wrapper'), template = document.createElement('template');
			template.innerHTML = window.QILCards.render({ ...product, promotion: { isFlash: true, discountPct: 20 } }, 0, 'beauty');
			wrapper.replaceChildren(template.content.firstElementChild);
			wrapper.classList.add('qil-shell');
			window.QILCards.refresh();
			wrapper.scrollIntoView({ block: 'center' });
			return String(product.id);
		});
		const rolling = async () => {
			await page.waitForFunction(() => document.querySelector('[data-qt-outside-shelf] .qil-label-stack')?.dataset.qilMotion === 'running', null, { timeout: 3000 }).catch(() => {});
			return page.evaluate(() => {
				const stack = document.querySelector('[data-qt-outside-shelf] .qil-label-stack'), sale = stack?.querySelector('.qil-sale-badge');
				return { outside: !!stack && !stack.closest('#qimia-lab'), multiple: !!stack?.classList.contains('has-multiple'), motion: stack?.dataset.qilMotion || '', play: sale ? getComputedStyle(sale).animationPlayState : '', animation: sale ? getComputedStyle(sale).animationName : '' };
			});
		};
		const id = await shelf();
		const first = await rolling();
		check('a .qil-shell card outside #qimia-lab rolls its sale label after QILCards.refresh', !!id && first.outside && first.multiple && first.motion === 'running' && first.play === 'running' && first.animation !== 'none', JSON.stringify(first));
		await shelf(); // A native AJAX update replaces the grid with new cards.
		const again = await rolling();
		check('after the shelf is replaced (AJAX refresh) the new card rolls its sale label too', again.multiple && again.motion === 'running' && again.play === 'running', JSON.stringify(again));
		const prefetched = page.waitForRequest(request => request.url().includes(`/qimia-lab/v1/quick-view/${id}`), { timeout: 4000 }).catch(() => null);
		const early = requests.some(url => url.includes(`/qimia-lab/v1/quick-view/${id}`));
		await page.hover('[data-qt-outside-shelf] [data-qil-quick-view-id]');
		check('hovering that card\'s Select options prefetches its Quick View', !early && !!(await prefetched), requests.filter(url => url.includes('quick-view')).join(' '));
		check('no JavaScript errors (card outside #qimia-lab)', errors.length === 0, errors.join(' | '));
		await context.close();
	}

	/* ---------------- Cart page ---------------- */
	for (const [viewport, path] of [['desktop', '/cart/'], ['mobile', '/cart/'], ['desktop', '/ar/cart/']]) {
		const label = `cart page ${path.startsWith('/ar') ? 'AR' : 'EN'} ${viewport}`;
		console.log(`\n${label}`);
		const { page, context, errors } = await open(path, { viewport, cookies: { qt_cart: '102:0:1' } });
		check(`${label}: ladder panel with six steps in the totals, after the checkout button (1.18.6)`, (await page.locator('.cart_totals .qil-boost-steps li').count()) === 6 && await page.evaluate(() => { const go = document.querySelector('.cart_totals .checkout-button'), panel = document.querySelector('.cart_totals .qil-boost'); return !!go && !!panel && !!(go.compareDocumentPosition(panel) & Node.DOCUMENT_POSITION_FOLLOWING); }));
		const ladderIds = await page.locator('.cart_totals [data-qil-boost-product]').evaluateAll(nodes => nodes.map(n => n.dataset.qilBoostProduct));
		await page.waitForSelector('.qil-boost-band .qil-product-card', { timeout: 6000 }).catch(() => {});
		const bandIds = await page.locator('.qil-boost-band .qil-product-card').evaluateAll(nodes => nodes.map(n => n.dataset.productId));
		check(`${label}: 3 products that reach the next band`, ladderIds.length === 3, ladderIds);
		check(`${label}: Complete your stack: 2–4 theme product cards with the theme's Add to cart`, bandIds.length >= 2 && bandIds.length <= 4 && (await page.locator('.qil-boost-band .qil-product-card .qil-buy').count()) === bandIds.length, bandIds);
		check(`${label}: ${viewport === 'desktop' ? 3 : 2} cards per view in the cart column, labels in full`, (await perView(page, '[data-qil-boost-band-grid]')) === Math.min(bandIds.length, viewport === 'desktop' ? 3 : 2) && (await page.locator('.qil-boost-band .qil-buy span').evaluateAll(spans => spans.every(span => span.scrollWidth <= span.clientWidth + 1))), await perView(page, '[data-qil-boost-band-grid]'));
		check(`${label}: each card says what it pairs with or the cashback it unlocks`, (await page.locator('.qil-boost-band .qil-product-badge').allInnerTexts()).every(label => /Pairs with|cashback|يكمّل|كاش باك/.test(label)));
		check(`${label}: nothing shown twice`, !bandIds.some(id => ladderIds.includes(id)), `${ladderIds} / ${bandIds}`);
		const panelButtons = await page.evaluate(() => { const b = document.querySelector('.cart_totals .qil-boost-add'), t = document.querySelector('.qil-boost-band .qil-buy'); return b && t ? [getComputedStyle(b).backgroundImage, getComputedStyle(t).backgroundImage] : []; });
		check(`${label}: ladder picks use the same Add to cart button as the theme cards`, panelButtons.length === 2 && panelButtons[0] === panelButtons[1], JSON.stringify(panelButtons));
		const order = await page.evaluate(rtl => [...document.querySelectorAll('.qil-boost-price')].filter(row => row.querySelector('small') && row.querySelector('strong')).map(row => {
			const a = row.querySelector('small').getBoundingClientRect(), b = row.querySelector('strong').getBoundingClientRect();
			if (b.top >= a.bottom - 1) return true; // wrapped: the price is on a later line
			if (a.top >= b.bottom - 1) return false;
			return rtl ? a.right > b.right : a.left < b.left;
		}), path.startsWith('/ar'));
		check(`${label}: "From" reads before the price in this language`, order.length > 0 && order.every(Boolean), JSON.stringify(order));
		const shell = await page.evaluate(() => { const node = document.querySelector('.qil-personal-cart-shell'); const section = node?.querySelector('[data-qil-personal]'); return node ? { height: Math.round(node.getBoundingClientRect().height), empty: !section || section.hidden } : null; });
		check(`${label}: an empty personal shelf reserves no screen-tall gap`, !shell || !shell.empty || shell.height < 40, JSON.stringify(shell));
		check(`${label}: no horizontal overflow`, await noOverflow(page));
		const placement = await page.evaluate(() => {
			const band = document.querySelector('.qil-boost-band'), totals = document.querySelector('.cart_totals');
			return { bandInForm: !!band?.closest('.woocommerce-cart-form'), bandAfterTotals: !!band && band.getBoundingClientRect().top >= totals.getBoundingClientRect().bottom - 1 };
		});
		check(viewport === 'desktop' ? `${label}: side by side, the band stays under the cart table` : `${label}: on a phone the ladder, totals and checkout come first, the suggestions after them`, viewport === 'desktop' ? placement.bandInForm && !placement.bandAfterTotals : placement.bandAfterTotals && !placement.bandInForm, JSON.stringify(placement));
		if (path.startsWith('/ar')) check(`${label}: Arabic ladder, steps and cards use the Western digits of the prices`, !/[٠-٩]/.test((await page.locator('.qil-boost-cart-panel, .qil-boost-band').allInnerTexts()).join(' ')));
		if (viewport === 'mobile') {
			// WooCommerce's cart update: a new form (with its own band) replaces the old one.
			const updated = await page.evaluate(async () => {
				const html = await (await fetch(location.href, { credentials: 'same-origin' })).text();
				const fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('.woocommerce-cart-form');
				document.querySelector('.woocommerce-cart-form').replaceWith(document.importNode(fresh, true));
				window.jQuery(document.body).trigger('updated_wc_div');
				await new Promise(resolve => setTimeout(resolve, 500));
				const bands = [...document.querySelectorAll('.qil-boost-band')], totals = document.querySelector('.cart_totals').getBoundingClientRect();
				return { bands: bands.length, cards: bands[0]?.querySelectorAll('.qil-product-card').length || 0, afterTotals: !!bands[0] && bands[0].getBoundingClientRect().top >= totals.bottom - 1 };
			});
			check(`${label}: after WooCommerce updates the cart, one fresh band, still after the totals`, updated.bands === 1 && updated.cards >= 2 && updated.afterTotals, JSON.stringify(updated));
		}
		const unnamed = await unnamedButtons(page);
		check(`${label}: the band's buy and ask buttons carry the product name in their accessible name`, unnamed.length === 0, JSON.stringify(unnamed));
		const named = await labelInName(page);
		check(`${label}: every label the band's buy and ask buttons show, full or short, is part of their accessible name`, named.buttons > 0 && named.bad.length === 0, JSON.stringify(named));
		await walk(page);
		await page.waitForFunction(() => !document.querySelector('[data-qil-reveal]'), null, { timeout: 2500 }).catch(() => {});
		await page.screenshot({ path: join(shots, `cart-${path.startsWith('/ar') ? 'ar' : 'en'}-${viewport}.png`), fullPage: true });
		if (viewport === 'desktop' && !path.startsWith('/ar')) {
			const button = page.locator('.qil-boost-band .qil-buy.ajax_add_to_cart').first();
			const id = await button.getAttribute('data-product_id');
			await button.click();
			await page.waitForFunction(pid => new RegExp(`(^|,)${pid}:`).test(decodeURIComponent(document.cookie.split('; ').find(c => c.startsWith('qt_cart='))?.slice(8) || '')), id, { timeout: 6000 }).catch(() => {});
			await page.waitForTimeout(300); // The theme removes WooCommerce's "View cart" link on the next tick.
			const cart = decodeURIComponent((await context.cookies()).find(c => c.name === 'qt_cart')?.value || '');
			check(`${label}: the theme card adds to this order without leaving the cart`, new RegExp(`(^|,)${id}:0:1`).test(cart) && (await page.locator('.qil-boost-band a.added_to_cart').count()) === 0, `${id} → ${cart}`);
		}
		check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
		await context.close();
	}

	/* ---------------- 1.18.24: narrow phone (360px): labels fit their cards ---------------- */
	for (const [name, path, cookies, ready] of [['home EN', '/', {}, '#qil-stacks .qil-product-card'], ['home AR', '/ar/', {}, '#qil-stacks .qil-product-card'], ['member', '/', { qt_user: 7 }, '[data-qil-wallet-grid] .qil-product-card'], ['member AR', '/ar/', { qt_user: 7 }, '[data-qil-wallet-grid] .qil-product-card'], ['cart', '/cart/', { qt_cart: '102:0:1' }, '.qil-boost-band .qil-product-card']]) {
		const label = `narrow 360 ${name}`;
		console.log(`\n${label}`);
		const { page, context, errors } = await open(path, { viewport: 'narrow', cookies });
		await page.waitForSelector(ready, { timeout: 8000 }).catch(() => {});
		await walk(page);
		const again = cookies.qt_user ? await buyAgainCards(page) : true;
		await page.evaluate(() => document.fonts.ready.then(() => true));
		const fit = await labelFit(page);
		check(`${label}: every card's buy and ask labels fit on one line, short labels included`, fit.buttons > 0 && fit.bad.length === 0, JSON.stringify(fit));
		const named = await labelInName(page);
		check(`${label}: every label a card's buttons show, full or short, is part of their accessible name${cookies.qt_user ? ' (Buy again included)' : ''}`, again && named.buttons > 0 && (!cookies.qt_user || named.repeat > 0) && named.bad.length === 0, JSON.stringify(named));
		const type = await cardType(page, 11, ':is(#qimia-lab,.qil-shell)');
		check(`${label}: product cards render and their buttons stay on one line`, type.cards > 0 && type.wrapped.length === 0, JSON.stringify(type));
		if (readabilityFloor) check(`${label}: product cards are readable (no card text under 11px)`, type.tiny.length === 0, JSON.stringify(type.tiny));
		check(`${label}: no horizontal overflow`, await noOverflow(page));
		check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
		await context.close();
	}

	/* ---------------- 1.18.24: Buy again while it adds: its busy label is in its name too ---------------- */
	// The Buy Again rail of a store that runs without personalization (qil_personalization_enabled off):
	// qil.js renders it from qil_repeat_context and owns its add. The add is held until the busy state is read.
	for (const path of ['/', '/ar/']) {
		const label = `buy again busy ${path === '/' ? 'EN' : 'AR'} narrow`;
		console.log(`\n${label}`);
		let release = () => {};
		const held = new Promise(resolve => { release = resolve; });
		const { page, context, errors } = await open(path, { viewport: 'narrow', cookies: { qt_user: 7 }, before: async page => {
			await page.route(BASE + path, async route => { const response = await route.fetch(); await route.fulfill({ response, body: (await response.text()).replace('"personalizationEnabled":true', '"personalizationEnabled":false') }); });
			await page.route(/wc-ajax=qil_buy_again/, async route => { await held; await route.continue(); });
		} });
		const shown = await page.waitForSelector('.qil-product-card [data-qil-repeat-buy]', { timeout: 8000 }).then(() => true, () => false);
		const button = page.locator('.qil-product-card [data-qil-repeat-buy]').first();
		let busy = { buttons: 0, repeat: 0, bad: ['no Buy again card'] }, state = null;
		if (shown) {
			await button.click();
			state = await page.waitForFunction(() => { const node = document.querySelector('.qil-product-card [data-qil-repeat-buy][aria-busy="true"]'); return node && { span: node.querySelector('span')?.textContent, disabled: node.disabled }; }, null, { timeout: 3000 }).then(handle => handle.jsonValue(), () => null);
			busy = await labelInName(page);
		}
		release();
		check(`${label}: while it adds, "${state?.span || '?'}" is part of the button's accessible name, as are its full and short labels`, shown && !!state?.disabled && busy.repeat > 0 && busy.bad.length === 0, JSON.stringify({ state, busy }));
		const done = await page.waitForFunction(() => !document.querySelector('.qil-product-card [data-qil-repeat-buy][aria-busy="true"]'), null, { timeout: 6000 }).then(() => true, () => false);
		const after = await labelInName(page), name = shown ? await button.getAttribute('aria-label') : '';
		check(`${label}: after the add its name is back to "Buy again: <product>"`, done && after.bad.length === 0 && /^(Buy again|اشترِ مجدداً): /.test(name || ''), JSON.stringify({ done, name, after }));
		check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
		await context.close();
	}

	/* ---------------- 1.18.24: Buy again adds from "Your choices" (personalization on) ---------------- */
	// Unified personalization supplies the rows through QILCards.hydrate; in 1.18.22 no click handler
	// was attached in that mode, so its Buy again buttons did nothing.
	for (const path of ['/', '/ar/']) {
		const label = `personal buy again ${path === '/' ? 'EN' : 'AR'}`;
		console.log(`\n${label}`);
		const { page, context, errors } = await open(path, { viewport: 'desktop', cookies: { qt_user: 7 } });
		await page.locator('[data-qil-personal-anchor]').first().scrollIntoViewIfNeeded().catch(() => {});
		const shown = await page.waitForSelector('[data-qil-personal-anchor] [data-qil-repeat-buy]', { timeout: 8000 }).then(() => true, () => false);
		let added = null;
		if (shown) {
			const request = page.waitForResponse(/wc-ajax=qil_buy_again/, { timeout: 6000 }).catch(() => null);
			await page.locator('[data-qil-personal-anchor] [data-qil-repeat-buy]').first().click();
			const response = await request;
			added = response ? { status: response.status(), ok: !(await response.json().catch(() => ({ error: true }))).error } : null;
		}
		check(`${label}: a Buy again button in "Your choices" adds the item to the cart`, shown && added?.status === 200 && added.ok, JSON.stringify({ shown, added }));
		check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
		await context.close();
	}

	/* ---------------- 1.18.24: motion moves nothing; one confirmation at a time ---------------- */
	// Layout shifts (Layout Instability API) during load, a scroll down the page, a Quick View and an
	// add from a card. Transform and opacity motion never shifts layout; shifts right after a tap are
	// left out, as in CLS (the Quick View panel grows with its options, as it did in 1.18.23).
	const watchShifts = () => {
		window.__qtShifts = { motion: 0, rows: [] };
		const moving = '.qil-product-card,.qil-modal-panel,.qil-toast,.qil-boost-meter,.qbx-world-grid,.qbx-filters';
		try {
			new PerformanceObserver(list => list.getEntries().forEach(entry => {
				if (entry.hadRecentInput) return;
				const nodes = (entry.sources || []).map(source => source.node).map(node => (node && node.nodeType !== 1 ? node.parentElement : node)).filter(node => node?.closest?.(moving));
				if (nodes.length) { window.__qtShifts.motion += entry.value; window.__qtShifts.rows.push(`${entry.value.toFixed(4)} ${nodes.map(node => String(node.className)).join(' | ')}`); }
			})).observe({ type: 'layout-shift', buffered: true });
		} catch (_) { window.__qtShifts.unsupported = true; }
	};
	for (const path of ['/', '/ar/']) {
		const label = `motion ${path === '/' ? 'EN' : 'AR'} mobile`;
		console.log(`\n${label}`);
		const { page, context, errors } = await open(path, { viewport: 'mobile', init: watchShifts });
		await page.waitForSelector('.qil-flash-rail .qil-product-card', { timeout: 6000 });
		await walk(page);
		await page.locator('#qimia-lab .qil-product-card .qil-select-options').first().click();
		await page.waitForSelector('[data-qil-quick-view-modal]:not([hidden])', { timeout: 5000 }).catch(() => {});
		await page.waitForTimeout(700);
		await page.keyboard.press('Escape');
		await page.waitForSelector('[data-qil-quick-view-modal]', { state: 'hidden', timeout: 3000 }).catch(() => {});
		await page.locator('#qimia-lab .qil-product-card .qil-buy.ajax_add_to_cart').first().click();
		const opened = await page.waitForSelector('.cart-widget-side.wd-opened', { timeout: 6000 }).then(() => true, () => false);
		await page.waitForTimeout(400);
		const toast = await page.evaluate(() => { const node = document.querySelector('[data-qil-toast]'); return node ? { hidden: node.hidden, className: node.className, shown: node.getClientRects().length > 0 && getComputedStyle(node).display !== 'none' } : null; });
		check(`${label}: while the drawer is open after an add, the toast stays hidden (the drawer is the confirmation)`, opened && !!toast && !toast.shown, JSON.stringify({ opened, toast }));
		const shifts = await page.evaluate(() => window.__qtShifts);
		check(`${label}: cards, Quick View, toast and meters cause no layout shift through load, scroll, Quick View and an add`, !shifts.unsupported && shifts.motion === 0, JSON.stringify(shifts));
		check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
		await context.close();
	}

	/* ---------------- 1.18.24: Arabic search prices in Western digits ---------------- */
	{
		const label = 'search AR mobile';
		console.log(`\n${label}`);
		const { page, context, errors } = await open('/ar/', { viewport: 'mobile' });
		await page.click('[data-qil-search-toggle]');
		await page.waitForSelector('[data-qil-searchbar]:not([hidden]) [data-qil-search]', { timeout: 5000 });
		await page.evaluate(() => { window.__qtSearch = null; document.addEventListener('qimia:search-rendered', event => { window.__qtSearch = event.detail; }); });
		await page.locator('[data-qil-search]').pressSequentially('protein', { delay: 40 });
		const results = await page.waitForFunction(() => window.__qtSearch && document.querySelector('.wd-search-results.wd-opened [data-qil-search-fallback]'), null, { timeout: 10000 }).then(() => page.locator('.wd-search-results.wd-opened').innerText(), () => '');
		check(`${label}: search results show prices, in the Western digits the cards use`, /\d/.test(results) && !/[٠-٩]/.test(results), results.slice(0, 200));
		check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
		await context.close();
	}

	// Qimia Refill ships from 1.19.0; a 1.18 build has no refill pages to check.
	if (existsSync(join(repo, 'qimia-intelligence-lab-final', 'includes', 'refill.php'))) {
		/* ---------------- Qimia Refill: order received → reminder → My Account → email link → checkout ---------------- */
		// The router keeps plans, cart and notices between requests for a named state (qt_state cookie).
		const refillLink = (user, plans, issued) => {
			const payload = `${user}.${plans.join('~')}.${issued}`;
			return `${BASE}/?wc-ajax=qil_refill&t=${payload}.${createHmac('sha256', 'test-salt-auth|qil-refill').update(payload).digest('hex').slice(0, 32)}`;
		};
		const refillLayout = page => page.evaluate(() => {
			const root = document.querySelector('[data-qil-refill]');
			const texts = [...root.querySelectorAll('*')].filter(node => [...node.childNodes].some(child => child.nodeType === 3 && child.textContent.trim()) && node.getClientRects().length && !node.closest('.screen-reader-text'));
			const small = texts.map(node => ({ text: node.textContent.trim().slice(0, 30), size: parseFloat(getComputedStyle(node).fontSize) })).filter(row => row.size < 13);
			const targets = [...root.querySelectorAll('button, select, a.qil-refill-button')].filter(node => node.getClientRects().length && !node.classList.contains('qil-refill-link')).map(node => Math.round(node.getBoundingClientRect().height)).filter(height => height < 44);
			const box = root.getBoundingClientRect();
			const outside = [...root.querySelectorAll('*')].filter(node => { const r = node.getBoundingClientRect(); return r.width && (r.right > box.right + 1 || r.left < box.left - 1); }).map(node => node.className || node.tagName).slice(0, 5);
			return { small, targets, outside };
		});
		for (const viewport of ['desktop', 'mobile']) {
			const label = `refill ${viewport}`;
			console.log(`\n${label}`);
			const state = `refill${viewport}${Date.now()}`;
			const { page, context, errors } = await open('/checkout/order-received/5001/', { viewport, cookies: { qt_user: 7, qt_state: state } });
			const offers = page.locator('.qil-refill-thankyou .qil-refill-start');
			check(`${label}: order received invites a reminder for the three consumables`, (await offers.count()) === 3 && /Want a reminder before it runs out\?/.test(await text(page, '#qil-refill-ty-title')));
			check(`${label}: the pre-workout's interval comes from its label (42 days)`, (await page.locator('.qil-refill-thankyou select').first().inputValue()) === '42');
			await page.locator('.qil-refill-thankyou').screenshot({ path: join(shots, `refill-received-${viewport}.png`) });
			await Promise.all([page.waitForNavigation(), offers.first().locator('button').click()]);
			check(`${label}: Remind me → back on the page with a confirmation`, /Done\. We will remind you every 42 days\./.test(await page.locator('.woocommerce-message').innerText().catch(() => '')) && /Reminder on · every 42 days/.test(await text(page, '.qil-refill-thankyou .is-on')));
			await page.goto(`${BASE}/my-account/qimia-refills/`);
			check(`${label}: My Account shows Refills after Orders`, (await page.locator('.woocommerce-MyAccount-navigation li').allInnerTexts()).join('|').startsWith('Dashboard|Orders|Refills'));
			check(`${label}: the plan is listed and due (runs low in 4 days): Refill now leads`, (await page.locator('.qil-refill-plan.is-due').count()) === 1 && /Fruit Punch/.test(await text(page, '.qil-refill-plan.is-due')) && (await page.locator('.qil-refill-plan.is-due .qil-refill-button.is-primary').innerText()) === 'Refill now');
			check(`${label}: other purchases are offered as one-tap reminders`, (await page.locator('.qil-refill-offer').count()) >= 3);
			const layout = await refillLayout(page);
			check(`${label}: readable (no text under 13px) and every control at least 44px tall`, layout.small.length === 0 && layout.targets.length === 0, JSON.stringify(layout));
			check(`${label}: nothing overflows the account column`, layout.outside.length === 0 && await noOverflow(page), JSON.stringify(layout.outside));
			await page.locator('[data-qil-refill]').screenshot({ path: join(shots, `refill-account-${viewport}.png`) });
			await page.selectOption('.qil-refill-plan.is-due select', '30');
			await Promise.all([page.waitForNavigation(), page.locator('.qil-refill-plan.is-due .qil-refill-interval button').click()]);
			check(`${label}: interval saved`, /Saved\. Every 30 days/.test(await page.locator('.woocommerce-message').innerText().catch(() => '')) && (await page.locator('.qil-refill-plan select').first().inputValue()) === '30');
			await Promise.all([page.waitForNavigation(), page.locator('.qil-refill-plan .qil-refill-button', { hasText: 'Refill now' }).first().click()]);
			check(`${label}: Refill now → checkout with the exact flavour in the cart`, /\/checkout\/$/.test(page.url()) && (await page.locator('[data-qt-checkout] tr[data-product="103"][data-variation="1031"]').count()) === 1 && /Your refill is ready/.test(await page.locator('.woocommerce-message').innerText().catch(() => '')), page.url());
			// The email's link in another, signed-out browser.
			const guest = await browser.newContext({ viewport: viewports[viewport] });
			await guest.addCookies([{ name: 'qt_state', value: state, url: BASE }]);
			const mail = await guest.newPage();
			await mail.goto(refillLink(7, ['103-1031'], Math.floor(Date.now() / 1000) - 3600));
			check(`${label}: the email's Refill now (signed out) lands on checkout with the item`, /\/checkout\/$/.test(mail.url()) && (await mail.locator('[data-qt-checkout] tr[data-product="103"]').count()) === 1, mail.url());
			await mail.goto(refillLink(7, ['103-1031'], Math.floor(Date.now() / 1000) - 3600).replace(/.$/, c => (c === 'a' ? 'b' : 'a')));
			check(`${label}: a tampered link adds nothing and explains itself`, /qimia-refills/.test(mail.url()) && /expired/.test(await mail.locator('.woocommerce-info').innerText().catch(() => '')), mail.url());
			await guest.close();
			check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
			await context.close();
		}
		{
			const label = 'refill AR mobile';
			console.log(`\n${label}`);
			const { page, context, errors } = await open('/ar/my-account/qimia-refills/', { viewport: 'mobile', cookies: { qt_user: 7, qt_state: `refillar${Date.now()}` } });
			check(`${label}: right to left, in Arabic`, (await page.getAttribute('[data-qil-refill]', 'dir')) === 'rtl' && /لا تدع روتينك ينفد/.test(await text(page, '.qil-refill-head h2')) && /ذكّرني/.test(await text(page, '.qil-refill-offer button')));
			const layout = await refillLayout(page);
			check(`${label}: readable, 44px controls, no overflow`, layout.small.length === 0 && layout.targets.length === 0 && layout.outside.length === 0 && await noOverflow(page), JSON.stringify(layout));
			await page.locator('[data-qil-refill]').screenshot({ path: join(shots, 'refill-account-ar-mobile.png') });
			check(`${label}: no JavaScript errors`, errors.length === 0, errors.join(' | '));
			await context.close();
		}
	} else {
		console.log('\nrefill: skipped, includes/refill.php is not in this build');
	}
} catch (error) {
	check('browser run completed', false, error?.stack || error);
} finally {
	await browser.close();
	server.kill();
}

const phpErrors = serverLog.split('\n').filter(line => /PHP (Fatal|Warning|Notice|Deprecated)/.test(line));
check('router: no PHP errors, warnings or notices during the run', phpErrors.length === 0, phpErrors.slice(0, 5).join('\n'));
console.log(`\n${results.passed} passed, ${results.failed} failed. Screenshots: ${shots}`);
if (resultsFile) writeFileSync(resultsFile, JSON.stringify(results, null, 2) + '\n');
process.exit(results.failed ? 1 : 0);
