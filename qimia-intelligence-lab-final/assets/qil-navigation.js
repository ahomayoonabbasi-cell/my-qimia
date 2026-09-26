(() => {
 'use strict';
 if (window.__qilNavigationReady) return;
 window.__qilNavigationReady = true;
 const config = () => window.QIMIA_NAV || {};
 const drop = ['add-to-cart','remove_item','undo_item','_wpnonce','wc-ajax','qaatm_preview','qaatm_prepare','qaatm_lang','_qaatm_compiler','_qaatm_variant','_qaatm_bust'];
 const clean = value => { const u = new URL(value, location.href); drop.forEach(k => u.searchParams.delete(k)); return u; };
 function samePageLanguage(lang) {
  const u = clean(location.href), base = String(config().basePath || '').replace(/\/+$/, '');
  let route = u.pathname;
  if (base && (route === base || route.startsWith(base + '/'))) route = route.slice(base.length);
  route = ('/' + route.replace(/^\/+/, '')).replace(/^\/ar(?=\/|$)/i, '') || '/';
  u.pathname = base + (lang === 'ar' ? '/ar' : '') + (route === '/' ? '/' : route);
  return u;
 }
 function invalidateFragments() {
  for (const name of ['sessionStorage','localStorage']) {
   try {
    const store = window[name];
    for (let i = store.length - 1; i >= 0; i--) {
     const key = store.key(i);
     if (/^(wc_fragments(?:_|$)|wc_cart_hash(?:_|$)|wc_cart_created$)/.test(key || '')) store.removeItem(key);
    }
   } catch (_) { /* Storage refusal does not prevent navigation. */ }
  }
 }
 function enabled(picker) {
  return config().currencySwitchEnabled === true || picker?.dataset.qilSwitchEnabled === '1';
 }
 function allowed(code) {
  if (!/^[A-Z]{3}$/.test(code)) return false;
  const codes = config().currencyCodes;
  if (Array.isArray(codes) && codes.length) return codes.includes(code);
  return Array.from(document.querySelectorAll('[data-qil-currency-code]')).some(link => link.dataset.qilCurrencyCode === code);
 }
 function noCacheURL(code) {
  const u = clean(location.href);
  u.searchParams.delete('qcur');
  u.searchParams.set('qimia_currency', code);
  u.searchParams.set('qimiac_ts', String(Date.now()));
  return u;
 }
 let busy = false;
 window.addEventListener('click', event => {
  const link = event.target instanceof Element ? event.target.closest('.qil-locale-control [data-qil-currency-code],.qil-locale-control a[data-qil-language]') : null;
  if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.hasAttribute('download') || link.target === '_blank') return;
  const code = link.dataset.qilCurrencyCode;
  if (code) {
   const picker = link.closest('[data-qil-currency-picker]');
   if (!enabled(picker) || !allowed(code)) return;
   event.preventDefault(); event.stopImmediatePropagation();
   if (busy) return;
   busy = true;
   picker?.setAttribute('aria-busy', 'true');
   const status = picker?.querySelector('[data-qil-currency-status]');
   if (status) { status.hidden = false; status.textContent = String(config().locale || document.documentElement.lang).startsWith('ar') ? 'جارٍ تحديث العملة…' : 'Updating currency…'; }
   invalidateFragments();
   // One real navigation: the installed engine sets its HttpOnly selection,
   // varies cache and recalculates prices/cart. No nonce or extra AJAX round trip.
   location.assign(noCacheURL(code).href);
  } else if (['en','ar'].includes(link.dataset.qilLanguage)) {
   event.preventDefault(); event.stopImmediatePropagation();
   invalidateFragments(); location.assign(samePageLanguage(link.dataset.qilLanguage).href);
  }
 }, true);
 function cookieCurrency() {
  try {
   const key = String(config().varyCookieKey || '_lscache_vary_qimia_currency');
   const cookie = String(document.cookie || '').split(';').find(part => part.trim().startsWith(key + '='));
   return cookie ? decodeURIComponent(cookie.slice(cookie.indexOf('=') + 1)).toUpperCase() : '';
  } catch (_) { return ''; }
 }
 function reconcileCachedCurrency() {
  const cfg = config(), cookie = cookieCurrency(), current = String(cfg.currency || '').toUpperCase();
  if (!enabled() || !cookie || !current || cookie === current || !allowed(cookie)) return;
  const url = new URL(location.href);
  if (url.searchParams.get('qimia_currency') === cookie && url.searchParams.has('qimiac_ts')) return;
  const key = 'qil-currency-repair:' + url.pathname + ':' + cookie;
  try {
   if (Date.now() - Number(sessionStorage.getItem(key) || 0) < 20000) return;
   sessionStorage.setItem(key, String(Date.now()));
  } catch (_) { /* URL marker limits repair if storage is unavailable. */ }
  invalidateFragments(); location.replace(noCacheURL(cookie).href);
 }
 if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', reconcileCachedCurrency, {once:true});
 else reconcileCachedCurrency();
 window.addEventListener('pageshow', event => {
  busy = false;
  document.querySelectorAll('[data-qil-currency-picker]').forEach(picker => {
   picker.removeAttribute('aria-busy');
   const status = picker.querySelector('[data-qil-currency-status]');
   if (status) { status.hidden = true; status.textContent = ''; }
  });
  if (event.persisted) reconcileCachedCurrency();
 });
})();
