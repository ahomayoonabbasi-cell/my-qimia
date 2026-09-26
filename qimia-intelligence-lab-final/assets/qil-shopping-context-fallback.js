/* Current-task continuity only. No network, cookie, persistent history, medical data or financial authority. */
(() => {
  'use strict';
  if (window.QimiaShoppingContext) return;
  const cleanIds = value => [...new Set((Array.isArray(value) ? value : []).map(Number))].filter(n => Number.isSafeInteger(n) && n > 0).slice(0, 8);
  const cleanQuery = value => String(value || '').replace(/[\u0000-\u001f\u007f<>]/g, ' ').trim().slice(0, 120);
  let state = { revision: 0, at: 0, query: '', products: [], product_id: 0, variation_id: 0, compare: [] };
  let used = 0;
  const update = patch => { state = { ...state, ...patch, revision: state.revision + 1, at: Date.now() }; if (!window.QimiaShopping) document.dispatchEvent(new CustomEvent('qimia:shopping-context')); };
  const clear = (shared=true) => { if(shared)window.QimiaShopping?.update?.({query:'',result_ids:[],compare_ids:[],selected_product:0,selected_variation:0}); used = 0; state = { revision: state.revision + 1, at: 0, query: '', products: [], product_id: 0, variation_id: 0, compare: [] }; };
  const snapshot = () => state.at && Date.now() - state.at < 15 * 60 * 1000 ? { ...state, products: [...state.products], compare: [...state.compare] } : null;
  window.QimiaShoppingContext = Object.freeze({
    version: 1,
    search(query, products = []) {
      const q = cleanQuery(query), ids = cleanIds(products);
      if (!q) { clear(); return; }
      window.QimiaShopping?.search?.(q, ids);
      update({ query: q, products: ids, product_id: 0, variation_id: 0, compare: [] });
    },
    select(productId, variationId = 0) {
      const id = cleanIds([productId])[0]; if (!id) return;
      window.QimiaShopping?.select?.(id,cleanIds([variationId])[0]||0);
      update({ product_id: id, variation_id: cleanIds([variationId])[0] || 0, products: [id], compare: [] });
    },
    compare(ids) {
      const selected = cleanIds(ids).slice(0, 2); if (!selected.length) return;
      window.QimiaShopping?.update?.({compare_id:crypto.randomUUID(),compare_ids:selected,result_ids:selected,selected_product:0,selected_variation:0});
      update({ products: selected, compare: selected, product_id: 0, variation_id: 0 });
    },
    consume() {
      const task = snapshot(); if (!task || task.revision === used) return null;
      used = task.revision; return task;
    },
    snapshot, clear
  });
  const inputFor = form => form?.querySelector('input[name="s"],[data-qil-search]');
  document.addEventListener('submit', event => {
    const form = event.target;
    if (form.matches?.('form[role="search"],.searchform')) {
      const query = inputFor(form)?.value;
      if (query && query !== state.query) window.QimiaShoppingContext.search(query);
    }
  }, true);
  document.addEventListener('click', event => {
    const target = event.target instanceof Element ? event.target : null; if (!target) return;
    if (target.closest('a[href*="customer-logout"],a[href*="action=logout"]')) { clear(); return; }
    const item = target.closest('.wd-suggestion,[data-qimia-product-id],[data-qil-product-id]');
    if (item) {
      const id = item.dataset.productId || item.dataset.qimiaProductId || item.dataset.qilProductId;
      window.QimiaShoppingContext.select(id, item.dataset.variationId || 0);
    }
    if (target.closest('[data-qil-open-compare]')) {
      const ids = [...document.querySelectorAll('[data-compare-id][aria-pressed="true"]')].map(n => n.dataset.compareId);
      window.QimiaShoppingContext.compare(ids);
    }
  }, true);
  // Native Woo variation events carry validated selection IDs as hints. Server still verifies every action.
  if (window.jQuery) window.jQuery(document).on('found_variation.qimiaContext', 'form.variations_form', function (_e, variation) {
    window.QimiaShoppingContext.select(this.dataset.product_id, variation?.variation_id);
  }).on('reset_data.qimiaContext', 'form.variations_form', function () {
    window.QimiaShoppingContext.select(this.dataset.product_id, 0);
  });
  // No carry-over between accounts or a browser-restored private page.
  window.addEventListener('pagehide', () => clear(false));
  window.addEventListener('pageshow', event => { if (event.persisted) clear(); });
})();
