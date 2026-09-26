/* Qimia 1.18 sales surfaces: cart ladder and stack interactions, the flash
   drop, and the signed-in member hub (cashback wallet + Running low?).
   No polling. The flash clock ticks only while its section is on screen and
   the tab is visible. The member request is made once, in browser idle time,
   and only for signed-in shoppers. Without this script every control is a
   normal link to WooCommerce. */
(() => {
	'use strict';
	const B = window.QIL_BOOST || {}, C = window.QIMIA_LAB || {};
	if (window.QILBoost) return;
	const lang = () => String(B.locale || C.locale || document.documentElement.lang || 'en').toLowerCase().startsWith('ar') ? 'ar' : 'en';
	const t = (en, ar) => lang() === 'ar' ? ar : en;
	const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[ch]));
	const num = value => lang() === 'ar' ? String(value).replace(/\d/g, d => '٠١٢٣٤٥٦٧٨٩'[d]) : String(value);
	const cards = () => window.QILCards || null;
	// The theme's card renderer comes from qil.js. Wait for it without relying
	// on script order or on the load event (optimizers may change both).
	const whenCards = callback => {
		let done = false, tries = 0;
		const run = () => { if (done || !cards()?.render) return; done = true; callback(); };
		run();
		if (done) return;
		document.addEventListener('qil:cards-ready', run, {once: true});
		window.addEventListener('load', run, {once: true});
		const poll = () => { run(); if (!done && ++tries < 60) window.setTimeout(poll, 250); };
		window.setTimeout(poll, 250);
	};
	const $ = (selector, scope = document) => scope.querySelector(selector);
	const $$ = (selector, scope = document) => [...scope.querySelectorAll(selector)];
	// Sections carry their own data, so they never depend on page data that an
	// optimizer may run late or out of order.
	const sectionData = (section, selector) => {
		try { const node = $(selector, section); return node ? JSON.parse(node.textContent || 'null') : null; } catch (_) { return null; }
	};
	const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
	const idle = (fn, timeout = 1500) => ('requestIdleCallback' in window ? window.requestIdleCallback(fn, {timeout}) : window.setTimeout(fn, 400));
	const safeUrl = value => { try { const url = new URL(String(value || ''), location.href); return /^https?:$/.test(url.protocol) ? url.href : ''; } catch (_) { return ''; } };
	const endpoint = name => {
		try {
			const raw = String(C.wcAjaxUrl || '').replace(/%%endpoint%%|%25%25endpoint%25%25/g, name);
			const url = new URL(raw, location.href);
			return raw && url.origin === location.origin ? url.href : '';
		} catch (_) { return ''; }
	};
	const post = (name, fields, timeout = 15000) => {
		const url = endpoint(name);
		if (!url) return Promise.reject(new Error('endpoint'));
		const controller = new AbortController();
		const timer = window.setTimeout(() => controller.abort(), timeout);
		return fetch(url, {
			method: 'POST', credentials: 'same-origin', cache: 'no-store', signal: controller.signal,
			headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-Qimia-Request': 'shopping/1', 'X-Qimia-Language': lang()},
			body: new URLSearchParams(fields)
		}).then(async response => {
			const data = await response.json().catch(() => ({}));
			if (!response.ok || data.error) { const error = new Error(String(data.message || 'request')); error.data = data; throw error; }
			return data;
		}).finally(() => window.clearTimeout(timer));
	};
	const refreshFragments = () => { if (window.jQuery) window.jQuery(document.body).trigger('wc_fragment_refresh'); };

	/* ---------------------------------------------------------------
	   1. Cart ladder and "Complete your stack"
	   --------------------------------------------------------------- */

	// A variable pick opens the existing Quick View sheet in place. Its compact
	// public record joins the page index before qil.js resolves the product.
	document.addEventListener('click', event => {
		const trigger = event.target instanceof Element ? event.target.closest('.qil-boost-add[data-qil-boost-record]') : null;
		const api = cards();
		if (!trigger || !api?.upsert || api.product(trigger.dataset.qilQuickViewId)) return;
		try {
			const record = JSON.parse(trigger.dataset.qilBoostRecord || 'null');
			if (record && Number(record.id) === Number(trigger.dataset.qilQuickViewId)) api.upsert([record]);
		} catch (_) { /* The link still opens the product page. */ }
	}, true);

	// WooCommerce's own AJAX add runs for simple picks. In the mini cart the
	// fragments replace everything; on the cart page Woo reloads the table and
	// totals, so only the card outside them is marked here.
	function markAdded(node) {
		if (!(node instanceof Element) || !node.closest('.qil-boost')) return;
		node.closest('.qil-boost-card, .qil-boost-pick')?.classList.add('is-added');
		// Woo appends its "View cart" link in its own handler; we are on the cart already.
		window.setTimeout(() => node.parentElement?.querySelectorAll('a.added_to_cart').forEach(link => link.remove()), 0);
		const label = node.querySelector('span');
		if (label) label.textContent = t('Added', 'أُضيف');
		node.classList.remove('loading');
		node.classList.add('is-done');
		node.setAttribute('aria-disabled', 'true');
	}
	if (window.jQuery) {
		window.jQuery(document.body).on('added_to_cart', (event, fragments, hash, button) => markAdded(button?.jquery ? button.get(0) : button));
	}

	// "Complete your stack" on the cart page is the theme's own product card.
	// WooCommerce replaces the cart form after an update; a fresh band renders again.
	function renderBands() {
		const api = cards();
		if (!api?.render) return;
		$$('[data-qil-boost-band]').forEach(band => {
			if (band.dataset.qilBandReady) return;
			band.dataset.qilBandReady = '1';
			const data = sectionData(band, 'script[data-qil-boost-band-data]');
			const grid = $('[data-qil-boost-band-grid]', band);
			const records = Array.isArray(data?.records) ? data.records.filter(record => Number(record?.id) > 0) : [];
			if (!grid || records.length < 2) { band.closest('.qil-boost-band-shell')?.setAttribute('hidden', ''); return; }
			api.upsert(records);
			grid.innerHTML = records.map((product, index) => api.render(product, index, 'boost-stack')).join('');
			$$('.qil-product-card', grid).forEach(card => {
				const label = data.labels?.[String(card.dataset.productId || '')];
				const badge = $('.qil-product-badge', card);
				if (badge && label) { badge.textContent = label; badge.title = label; }
			});
			api.refresh?.();
		});
	}
	if ($('[data-qil-boost-band]')) whenCards(renderBands);
	if (window.jQuery) window.jQuery(document.body).on('updated_wc_div updated_cart_totals cart_page_refreshed', () => whenCards(renderBands));

	// Block-based cart: WooCommerce Blocks own the cart; refresh the ladder
	// fragment when their store's totals change (debounced, no polling).
	const blockCart = $('[data-qil-boost-block-cart]');
	if (blockCart) {
		let refreshTimer = 0, lastKey = '';
		const schedule = () => { window.clearTimeout(refreshTimer); refreshTimer = window.setTimeout(refreshFragments, 700); };
		['wc-blocks_added_to_cart', 'wc-blocks_removed_from_cart'].forEach(name => document.body.addEventListener(name, schedule));
		const data = window.wp?.data;
		if (data?.subscribe && data.select) {
			data.subscribe(() => {
				try {
					const store = data.select('wc/store/cart');
					const totals = store?.getCartTotals?.();
					const key = totals ? `${totals.total_price}|${totals.total_discount}|${store.getCartData?.()?.itemsCount ?? ''}` : '';
					if (!key || key === lastKey) return;
					const first = !lastKey;
					lastKey = key;
					if (!first) schedule();
				} catch (_) { /* Store not ready. */ }
			});
		}
	}

	/* ---------------------------------------------------------------
	   2. Flash drop
	   --------------------------------------------------------------- */

	const clockParts = seconds => {
		const s = Math.max(0, Math.floor(seconds));
		return {d: Math.floor(s / 86400), h: Math.floor((s % 86400) / 3600), m: Math.floor((s % 3600) / 60), s: s % 60};
	};
	const pad = value => String(value).padStart(2, '0');
	const shortClock = seconds => {
		const p = clockParts(seconds);
		return num(p.d > 0 ? `${p.d}${t('d', 'ي')} ${pad(p.h)}:${pad(p.m)}:${pad(p.s)}` : `${pad(p.h)}:${pad(p.m)}:${pad(p.s)}`);
	};
	// Window ends are absolute timestamps. The page may come from a page cache,
	// so its own generation time is never used to correct the browser clock.
	const now = () => Date.now() / 1000;

	// Quantity is the point of a flash drop: one chip on the product image
	// (the card itself stays the theme card, so every card keeps its height).
	function flashStockChip(meta, fresh, dropEnd) {
		const low = meta?.low, stock = meta?.stock;
		let text = '', tone = '', level = null;
		if (low && Number(low.qty) > 0 && String(low.option || '')) {
			const qty = Number(low.qty);
			text = fresh ? t(`Only ${qty} left in ${low.option}`, `بقي ${num(qty)} فقط من ${low.option}`) : t(`Low stock in ${low.option}`, `كمية محدودة من ${low.option}`);
			tone = 'hot'; level = fresh ? Math.max(10, Math.min(100, qty * 20)) : null;
		} else if (typeof stock === 'number' && stock > 0 && stock < 100) {
			if (stock <= 10) { text = fresh ? t(`Only ${stock} left`, `بقي ${num(stock)} فقط`) : t('Low stock', 'كمية محدودة'); tone = 'hot'; }
			else if (fresh) { text = t(`${stock} left`, `بقي ${num(stock)}`); tone = 'warm'; }
			level = fresh && text ? Math.max(8, Math.min(100, Math.round((stock / 50) * 100))) : null;
		}
		const ends = Number(meta?.endsAt || 0);
		// A product's own sale end, only when it comes before the drop's end.
		const endsLine = ends > now() && ends - now() < 7 * 86400 && !(dropEnd > 0 && ends >= dropEnd - 60)
			? `<small>${esc(t('Sale ends in', 'ينتهي الخصم بعد'))} <b dir="ltr" data-qil-flash-card-end="${ends}">${esc(shortClock(ends - now()))}</b></small>` : '';
		if (!text && !endsLine) return '';
		return `<span class="qil-flash-stock${tone ? ` is-${tone}` : ''}" data-qil-flash-stock>${text ? `<b>${esc(text)}</b>` : ''}${level !== null ? `<span class="qil-flash-bar" aria-hidden="true"><i style="width:${level}%"></i></span>` : ''}${endsLine}</span>`;
	}
	const flashUrgency = meta => (meta?.low || (typeof meta?.stock === 'number' && meta.stock <= 10)) ? 0 : (typeof meta?.stock === 'number' && meta.stock < 100 ? 1 : 2);

	function renderFlash(section, flash) {
		const api = cards();
		const grid = $('[data-qil-flash-grid]', section);
		if (!api?.render || !grid) return false;
		const fresh = [], records = [];
		(Array.isArray(flash.records) ? flash.records : []).forEach(item => {
			if (!item || !(Number(item.id) > 0)) return;
			if (item.ref === true) { const known = api.product(item.id); if (known) records.push(known); }
			else { fresh.push(item); records.push(item); }
		});
		if (fresh.length) api.upsert(fresh);
		if (records.length < 4) { section.hidden = true; return false; }
		// Counts are exact while the page is recent; stock changes purge the cached page.
		const recent = now() - Number(flash.generatedAt || 0) < 12 * 3600;
		const dropEnd = Number(section.dataset.qilFlashEnd || flash.end || 0);
		const order = records.map((product, index) => ({product, index, urgency: flashUrgency(flash.meta?.[String(product.id)])}))
			.sort((a, b) => a.urgency - b.urgency || a.index - b.index).map(row => row.product);
		grid.innerHTML = order.map((product, index) => api.render(product, index, 'flash-drop')).join('');
		$$('.qil-product-card', grid).forEach(card => {
			const meta = flash.meta?.[String(card.dataset.productId || '')] || {};
			const badge = $('.qil-product-badge', card);
			if (badge) badge.textContent = t('Flash drop', 'مجموعة سريعة');
			if (!$('[data-qil-sale-badge]', card) && Number(meta.pct) > 0) {
				$('.qil-label-stack', card)?.insertAdjacentHTML('beforeend', `<span class="qil-sale-badge qil-flash-sale-badge" data-qil-sale-badge="flash">${esc((meta.upTo ? t('Up to ', 'حتى ') : '') + '−' + num(Math.round(meta.pct)) + (lang() === 'ar' ? '٪' : '%'))}</span>`);
			}
			const chip = flashStockChip(meta, recent, dropEnd);
			if (chip) { card.classList.add('has-flash-stock'); $('.qil-product-image', card)?.insertAdjacentHTML('beforeend', chip); }
		});
		api.refresh?.();
		return true;
	}

	function setupFlashClock(section, end) {
		const units = {d: $('[data-qil-flash-unit="d"]', section), h: $('[data-qil-flash-unit="h"]', section), m: $('[data-qil-flash-unit="m"]', section), s: $('[data-qil-flash-unit="s"]', section)};
		let timer = 0, visible = false, ended = false;
		const paint = () => {
			const left = end - now();
			if (left <= 0 && !ended) {
				ended = true;
				section.classList.add('is-ended');
				const clock = $('[data-qil-flash-clock]', section);
				if (clock) clock.innerHTML = `<small>${esc(t('THIS DROP HAS ENDED', 'انتهت هذه المجموعة'))}</small><button type="button" class="qil-flash-next" data-qil-flash-reload>${esc(t('See the new drop', 'شاهد المجموعة الجديدة'))}</button>`;
				stop();
				return;
			}
			const p = clockParts(left);
			if (units.d) units.d.textContent = num(p.d);
			if (units.h) units.h.textContent = num(pad(p.h));
			if (units.m) units.m.textContent = num(pad(p.m));
			if (units.s) units.s.textContent = num(pad(p.s));
			$$('[data-qil-flash-card-end]', section).forEach(node => {
				const remaining = Number(node.dataset.qilFlashCardEnd) - now();
				if (remaining <= 0) { node.closest('small')?.replaceChildren(document.createTextNode(t('Sale ended — the cart shows the current price', 'انتهى الخصم — تظهر السلة السعر الحالي'))); return; }
				node.textContent = shortClock(remaining);
			});
		};
		const tick = () => { paint(); if (!ended) timer = window.setTimeout(tick, 1000 - (Date.now() % 1000) + 5); };
		const start = () => { if (!timer && !ended && visible && document.visibilityState === 'visible') tick(); };
		function stop() { window.clearTimeout(timer); timer = 0; }
		paint();
		if ('IntersectionObserver' in window) {
			new IntersectionObserver(entries => entries.forEach(entry => {
				visible = entry.isIntersecting;
				section.classList.toggle('is-live', visible && !reduceMotion.matches);
				if (visible) start(); else stop();
			}), {rootMargin: '120px 0px'}).observe(section);
		} else { visible = true; start(); }
		document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') { paint(); start(); } else stop(); });
		section.addEventListener('click', event => {
			if (event.target instanceof Element && event.target.closest('[data-qil-flash-reload]')) location.reload();
		});
	}

	function waitForChat(timeout = 12000) {
		return new Promise((resolve, reject) => {
			const started = Date.now();
			const check = () => {
				const chat = window.AmirAIChat;
				if (chat?.panel && chat?.input && chat?.bootstrapData?.rest?.base && !chat?.bootstrapPromise) return resolve(chat);
				if (Date.now() - started >= timeout) return reject(new Error('qimia_ai_timeout'));
				window.setTimeout(check, 80);
			};
			check();
		});
	}

	function setupFlashAI(section, flash) {
		const button = $('[data-qil-flash-ai]', section);
		if (!button) return;
		button.addEventListener('click', async () => {
			if (button.dataset.busy === '1') return;
			button.dataset.busy = '1';
			const ids = (Array.isArray(flash.records) ? flash.records : []).map(row => Number(row.id)).filter(id => id > 0).slice(0, 12);
			const endsText = $('[data-qil-flash-ends]', section)?.textContent || '';
			try {
				const chat = await waitForChat();
				chat.open?.();
				if (typeof chat.setLanguage === 'function') chat.setLanguage(lang(), false);
				const context = {schemaVersion: 1, goal: 'flash-drop', filters: [], query: '', market: {currency: String(C.currency || ''), country: String(C.country || '')}, productIds: ids};
				chat.qimiaRecommendationContext = context;
				if (typeof chat.setRecommendationContext === 'function') chat.setRecommendationContext(context);
				if (chat.state) chat.state.currentProductIds = [...ids];
				const prompt = t(
					`Show me the current Qimia flash drop. Use only these live product IDs: ${ids.join(', ')}. For each one, verify the live price, the previous (regular) price, the real discount and stock before answering. ${endsText}. Then help me choose what fits my goal and budget.`,
					`اعرض لي مجموعة كيميا السريعة الحالية. استخدم فقط أرقام المنتجات المباشرة هذه: ${ids.join('، ')}. تحقّق لكل منتج من السعر الحالي والسعر السابق ونسبة الخصم الحقيقية والمخزون قبل الإجابة. ${endsText}. ثم ساعدني في اختيار ما يناسب هدفي وميزانيتي.`
				);
				await chat.send(prompt.slice(0, 1800), t('Today’s flash drop', 'المجموعة السريعة اليوم'), {languageOverride: lang(), source: 'qimia-flash-drop'});
			} catch (_) {
				const note = $('.qil-flash-truth span', section);
				if (note) note.textContent = t('Qimia AI is not available right now. Every product here is still live.', 'ذكاء كيميا غير متاح الآن. كل المنتجات هنا ما زالت متاحة.');
			} finally { window.setTimeout(() => { delete button.dataset.busy; }, 900); }
		});
	}

	const flashSection = $('[data-qil-flash-drop]');
	if (flashSection) {
		const flash = sectionData(flashSection, 'script[data-qil-flash-data]');
		if (!flash || !Array.isArray(flash.records) || flash.records.length < 4) flashSection.hidden = true;
		else whenCards(() => {
			// A cached page can outlive its drop; the clock then says so honestly.
			const end = Number(flashSection.dataset.qilFlashEnd || flash.end || 0);
			if (renderFlash(flashSection, flash)) { setupFlashClock(flashSection, end); setupFlashAI(flashSection, flash); }
		});
	}

	/* ---------------------------------------------------------------
	   2b. Cashback stacks: the theme's cards; stack facts fill the card's
	   own slots (badge, per-serving line, two fact boxes).
	   --------------------------------------------------------------- */
	function renderStacks(section) {
		const api = cards(), grid = $('[data-qil-stacks-grid]', section);
		const data = sectionData(section, 'script[data-qil-stacks-data]');
		const records = Array.isArray(data?.records) ? data.records.filter(record => Number(record?.id) > 0) : [];
		if (!api?.render || !grid) return;
		if (records.length < 2) { section.hidden = true; return; }
		api.upsert(records);
		grid.innerHTML = records.map((product, index) => api.render(product, index, 'stacks')).join('');
		const fill = (slot, label, value, title = value) => {
			if (!slot || !value) return;
			const name = $('.qil-fact-label', slot), text = $('.qil-fact-value', slot);
			if (name) name.textContent = label;
			if (text) { text.textContent = value; text.title = title; }
		};
		$$('.qil-product-card', grid).forEach(card => {
			const fact = data.facts?.[String(card.dataset.productId || '')] || {};
			const badge = $('.qil-product-badge', card);
			if (badge && fact.reward) { badge.textContent = t(`${fact.reward} cashback`, `${fact.reward} كاش باك`); badge.title = badge.textContent; badge.classList.add('is-cashback'); }
			else if (badge) badge.textContent = t('Stack', 'مجموعة');
			if (fact.separately) $('.qil-card-price', card)?.insertAdjacentHTML('beforeend', `<span class="qil-per-serving qil-stack-separately">${esc(t('Separately', 'منفصلة'))} <del dir="ltr">${esc(fact.separately)}</del></span>`);
			const slots = $$('.qil-fact', card);
			fill(slots[0], t('Inside', 'بداخلها'), fact.inside, fact.insideFull || fact.inside);
			fill(slots[1], fact.save ? t('You save', 'توفّر') : t('Cashback', 'كاش باك'), fact.save || fact.reward);
		});
		api.refresh?.();
	}
	const stacksSection = $('#qil-stacks');
	if (stacksSection && $('script[data-qil-stacks-data]', stacksSection)) whenCards(() => renderStacks(stacksSection));

	/* ---------------------------------------------------------------
	   3. Member hub: wallet, Running low?, best ways to use it
	   --------------------------------------------------------------- */

	const member = {data: null, busy: new Set(), requests: new Map()};

	function recentHints() {
		try {
			const row = String(document.cookie || '').split(';').map(v => v.trim()).find(v => v.startsWith('woocommerce_recently_viewed='));
			if (!row) return [];
			return decodeURIComponent(row.slice(row.indexOf('=') + 1)).split('|').reverse().map(Number).filter(n => Number.isSafeInteger(n) && n > 0).slice(0, 12);
		} catch (_) { return []; }
	}

	// Arabic counted days after a preposition: يوم واحد، يومين، ٣–١٠ أيام، ١١+ يوماً.
	const arDays = days => days === 1 ? 'يوم واحد' : days === 2 ? 'يومين' : days <= 10 ? `${num(days)} أيام` : `${num(days)} يوماً`;

	function relativeDays(days) {
		if (days === null || days === undefined) return '';
		if (days <= 0) return t('expires today', 'تنتهي اليوم');
		if (days === 1) return t('expires tomorrow', 'تنتهي غداً');
		return t(`expires in ${days} days`, `تنتهي خلال ${arDays(days)}`);
	}

	function creditDays(days) {
		if (days === null || days === undefined) return '';
		if (days <= 0) return t('Ends today', 'ينتهي اليوم');
		if (days === 1) return t('1 day left', 'ينتهي غداً');
		return t(`${days} days left`, `ينتهي بعد ${arDays(days)}`);
	}

	function walletText(wallet) {
		const best = wallet.best || {};
		const title = wallet.count > 1
			? t(`You have ${wallet.total} cashback`, `لديك كاش باك ${wallet.total}`)
			: t(`You have ${best.amount} cashback`, `لديك كاش باك ${best.amount}`);
		const expires = relativeDays(best.daysLeft);
		let sub = wallet.count > 1
			? t(`${best.amount} is waiting for you — one per order, ${expires}.`, `${best.amount} بانتظارك — واحد لكل طلب، ${expires}.`)
			: t(`${best.amount} is waiting for you — ${expires}.`, `${best.amount} بانتظارك — ${expires}.`);
		if (best.minimum) sub += ' ' + t(`Minimum order ${best.minimum}.`, `الحد الأدنى للطلب ${best.minimum}.`);
		return {title, sub};
	}

	function walletCta(status) {
		if (status === 'applied') return t('Applied — keep shopping', 'مطبّق — تابع التسوق');
		if (status === 'pending') return t('Ready — applies at checkout', 'جاهز — يُطبَّق عند الدفع');
		return t('Shop with cashback', 'تسوّق بالكاش باك');
	}

	function renderTopbar(wallet) {
		const line = $('[data-qil-delivery-line]');
		if (!line || !wallet) return;
		if (!line.dataset.qilOriginal) line.dataset.qilOriginal = line.textContent || '';
		const target = $('#qil-member') ? '#qil-member' : safeUrl(C.cartUrl) || '#';
		line.innerHTML = `<a class="qil-topbar-wallet" href="${esc(target)}">${esc(t(`${wallet.best.amount} cashback waiting for you`, `كاش باك ${wallet.best.amount} بانتظارك`))} <span aria-hidden="true">·</span> <b>${esc(t('Shop with cashback', 'تسوّق بالكاش باك'))}</b></a>`;
	}

	function paintWallet(card, wallet, message = '') {
		if (!card || !wallet) return;
		const text = walletText(wallet);
		$('[data-qil-wallet-title]', card).textContent = text.title;
		$('[data-qil-wallet-sub]', card).textContent = text.sub;
		const button = $('[data-qil-wallet-apply]', card);
		$('[data-qil-wallet-cta]', button).textContent = walletCta(wallet.status);
		button.dataset.status = wallet.status;
		card.dataset.status = wallet.status;
		if (message) $('[data-qil-wallet-status]', card).textContent = message;
		// One credit per order: list them (amount and days left, never a code).
		const credits = $('[data-qil-wallet-credits]', card);
		const list = Array.isArray(wallet.credits) ? wallet.credits.slice(0, 4) : [];
		if (credits) {
			credits.innerHTML = list.map((credit, index) => `<li${index === 0 ? ' class="is-next"' : ''}><b>${esc(credit.amount)}</b> <span>${esc([creditDays(credit.daysLeft), credit.minimum ? t(`Min. order ${credit.minimum}`, `حد أدنى للطلب ${credit.minimum}`) : ''].filter(Boolean).join(' · '))}</span></li>`).join('');
			credits.hidden = list.length < 2;
		}
		card.hidden = false;
	}

	async function applyWallet(button, card) {
		const wallet = member.data?.wallet;
		if (!wallet || button.getAttribute('aria-busy') === 'true') return;
		const status = $('[data-qil-wallet-status]', card);
		const picks = $('[data-qil-wallet-picks]');
		if (wallet.status !== 'ready') { if (picks && !picks.hidden) picks.scrollIntoView({behavior: reduceMotion.matches ? 'auto' : 'smooth', block: 'start'}); return; }
		button.setAttribute('aria-busy', 'true'); button.disabled = true;
		try {
			const data = await post('qil_wallet_apply', {nonce: member.data.walletNonce || '', coupon: String(wallet.best.id)});
			wallet.status = data.status === 'applied' ? 'applied' : 'pending';
			paintWallet(card, wallet, String(data.message || ''));
			refreshFragments();
			if (picks && !picks.hidden) picks.scrollIntoView({behavior: reduceMotion.matches ? 'auto' : 'smooth', block: 'start'});
		} catch (error) {
			if (status) status.textContent = String(error?.data?.message || t('Your cashback could not be applied. Please try again.', 'تعذّر تطبيق الكاش باك. حاول مرة أخرى.'));
		} finally { button.removeAttribute('aria-busy'); button.disabled = false; }
	}

	function runningEstimate(row) {
		const days = Number(row.daysLeft);
		const servings = num(row.servings);
		const basis = t(`${servings} servings from your order`, `${servings} حصة من طلبك`);
		if (days > 1) return `${t(`Runs out in about ${days} days`, `تنفد خلال ${arDays(days)} تقريباً`)} · ${basis}`;
		if (days >= 0) return `${t('Runs out about now', 'تنفد تقريباً الآن')} · ${basis}`;
		return `${t(`Likely ran out ${Math.abs(days)} ${Math.abs(days) === 1 ? 'day' : 'days'} ago`, `نفدت على الأرجح قبل ${arDays(Math.abs(days))}`)} · ${basis}`;
	}

	function renderRunning(root, rows) {
		const box = $('[data-qil-running]', root), list = $('[data-qil-running-list]', root);
		if (!box || !list || !rows.length) return;
		list.innerHTML = rows.map(row => {
			const image = row.image?.src ? `<img src="${esc(safeUrl(row.image.src))}"${row.image.srcset ? ` srcset="${esc(row.image.srcset)}" sizes="72px"` : ''} width="72" height="72" alt="" loading="lazy" decoding="async">` : '';
			return `<div class="qil-running-row" data-qil-running-key="${esc(row.key)}">
				<a class="qil-running-media" href="${esc(safeUrl(row.url))}" tabindex="-1" aria-hidden="true">${image}</a>
				<div class="qil-running-body"><a class="qil-running-name" href="${esc(safeUrl(row.url))}">${esc(row.name)}</a>${row.selection ? `<span class="qil-running-choice" dir="auto">${esc(row.selection)}</span>` : ''}<span class="qil-running-when" data-urgent="${Number(row.daysLeft) <= 3 ? '1' : '0'}">${esc(runningEstimate(row))}</span></div>
				<div class="qil-running-buy"><span class="qil-running-price" dir="ltr">${row.price || ''}</span><button type="button" class="qil-buy qil-running-add" data-qil-running-buy="${esc(row.key)}" aria-label="${esc(t('Buy again', 'اشترِ مجدداً') + ': ' + row.name + (row.selection ? ' — ' + row.selection : ''))}"><span>${esc(t('Buy again', 'اشترِ مجدداً'))}</span><svg aria-hidden="true"><use href="#qil-i-cart-plus"/></svg></button></div>
			</div>`;
		}).join('');
		box.hidden = false;
	}

	async function buyAgain(button) {
		const key = String(button.dataset.qilRunningBuy || '');
		const row = (member.data?.running || []).find(item => item.key === key);
		if (!row || member.busy.has(key) || !member.data?.nonce) return;
		member.busy.add(key);
		button.disabled = true; button.setAttribute('aria-busy', 'true');
		const label = $('span', button); if (label) label.textContent = t('Adding…', 'جارٍ الإضافة…');
		// One request id per item: an ambiguous network failure is never retried as a second add.
		let requestId = member.requests.get(key);
		if (!requestId) { requestId = window.crypto?.randomUUID?.() || `${Date.now().toString(36)}_${Math.random().toString(36).slice(2)}_${Math.random().toString(36).slice(2)}`; member.requests.set(key, requestId); }
		try {
			const url = endpoint('qil_buy_again');
			const response = await fetch(url, {method: 'POST', credentials: 'same-origin', cache: 'no-store',
				headers: {'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-Qimia-Language': lang()},
				body: new URLSearchParams({nonce: member.data.nonce, order_id: String(row.orderId), item_id: String(row.itemId), locale: lang(), request_id: requestId})});
			const data = await response.json().catch(() => ({}));
			if (!response.ok || data.error) {
				if (['login', 'nonce', 'unavailable'].includes(data.code)) member.requests.delete(key);
				throw new Error(String(data.message || t('Could not add it. Check your cart before trying again.', 'تعذّرت الإضافة. راجع السلة قبل المحاولة مرة أخرى.')));
			}
			member.requests.delete(key);
			if (window.jQuery) window.jQuery(document.body).trigger('added_to_cart', [data.fragments, data.cart_hash, window.jQuery(button)]);
			if (label) label.textContent = t('Added', 'أُضيف');
			button.closest('.qil-running-row')?.classList.add('is-added');
		} catch (error) {
			if (label) label.textContent = t('Buy again', 'اشترِ مجدداً');
			button.disabled = false;
			const note = button.closest('[data-qil-running]')?.querySelector('.qil-running-note');
			if (note) note.textContent = String(error?.message || '');
		} finally { member.busy.delete(key); button.removeAttribute('aria-busy'); }
	}

	function renderPicks(picks, wallet) {
		const box = $('[data-qil-wallet-picks]'), grid = $('[data-qil-wallet-grid]'), api = cards();
		const records = Array.isArray(picks?.records) ? picks.records : [];
		if (!box || !grid || !api?.render || !records.length) return;
		api.upsert(records);
		grid.innerHTML = records.map((product, index) => api.render(product, index, 'wallet-picks')).join('');
		// The reason gets its own line: the card's pill badge centres and clips long
		// text. The badge's goal-position label (Foundation/Optional) means nothing here.
		$$('.qil-product-card', grid).forEach(card => {
			const reason = picks.reasons?.[card.dataset.productId];
			const badge = $('.qil-product-badge', card);
			if (badge) badge.textContent = t('Cashback pick', 'اختيار لرصيدك');
			if (reason) $('.qil-product-info h3', card)?.insertAdjacentHTML('afterend', `<p class="qil-wallet-reason" title="${esc(reason)}">${esc(reason)}</p>`);
		});
		const title = $('[data-qil-wallet-picks-title]', box);
		if (title) title.textContent = t(`Best ways to use your ${wallet.best.amount}`, `أفضل طرق استخدام ${wallet.best.amount}`);
		box.hidden = false;
		api.refresh?.();
	}

	function renderCartWallet(wallet) {
		if (!wallet || $('.qil-boost-wallet-bar')) return;
		const anchor = $('.woocommerce-cart-form') || $('[data-qil-boost-block-cart]') || $('.wp-block-woocommerce-cart');
		if (!anchor) return;
		const bar = document.createElement('div');
		bar.className = 'qil-boost qil-boost-wallet-bar';
		bar.dir = lang() === 'ar' ? 'rtl' : 'ltr'; bar.lang = lang();
		bar.setAttribute('translate', 'no');
		bar.innerHTML = `<span class="qil-boost-wallet-coin" aria-hidden="true"></span><div><strong data-qil-wallet-title></strong><span data-qil-wallet-sub></span></div><button type="button" class="qil-boost-wallet-apply" data-qil-wallet-apply><span data-qil-wallet-cta></span></button><p class="qil-boost-wallet-status" data-qil-wallet-status role="status" aria-live="polite"></p>`;
		bar.dataset.qilWallet = '';
		anchor.parentNode.insertBefore(bar, anchor);
		paintWallet(bar, wallet);
		bar.querySelector('[data-qil-wallet-apply]').addEventListener('click', event => applyWallet(event.currentTarget, bar));
	}

	async function loadMember() {
		const surface = B.member?.surface === 'cart' ? 'cart' : 'home';
		let compare = [];
		try { compare = cards()?.context?.().compare || []; } catch (_) { compare = []; }
		let data;
		try { data = await post('qil_member', {locale: lang(), surface, hints: JSON.stringify({recent: recentHints(), compare})}); }
		catch (_) { return; }
		if (data.authenticated !== true || String(data.currency || '').toUpperCase() !== String(C.currency || '').toUpperCase()) return;
		member.data = data;
		const wallet = data.wallet || null;
		if (surface === 'cart') { renderCartWallet(wallet); return; }
		const root = $('[data-qil-member]');
		if (!root) return;
		if (wallet) { paintWallet($('[data-qil-wallet]', root), wallet); renderTopbar(wallet); }
		renderRunning(root, Array.isArray(data.running) ? data.running : []);
		if (wallet) renderPicks(data.picks, wallet);
		const any = !!wallet || !!$('[data-qil-running]:not([hidden])', root);
		root.hidden = !any;
		root.classList.toggle('has-wallet', !!wallet);
		root.classList.toggle('has-running', !$('[data-qil-running]', root)?.hidden);
		root.addEventListener('click', event => {
			const target = event.target instanceof Element ? event.target : null;
			const apply = target?.closest('[data-qil-wallet-apply]');
			if (apply) { applyWallet(apply, apply.closest('[data-qil-wallet]')); return; }
			const buy = target?.closest('[data-qil-running-buy]');
			if (buy) { event.preventDefault(); buyAgain(buy); }
		});
	}

	if (B.member && B.signedIn === true && typeof C.restNonce === 'string' && C.restNonce.length > 0 && document.visibilityState !== 'prerender') {
		idle(() => { void loadMember(); });
	}

	window.QILBoost = Object.freeze({version: 1});
})();
