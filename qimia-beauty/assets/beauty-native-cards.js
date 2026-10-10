/* QIL owns card markup, prices, stock, cart, options and the existing AI entry. */
(() => {
  'use strict';
  if (window.QimiaBeautyNativeCards) return;
  const limit = 48;
  const valid = row => Number.isSafeInteger(Number(row?.id)) && Number(row.id) > 0 && row?.beauty?.schema === 1 && ['beauty','accessory'].includes(row.productClass);
  function records(scope) {
    const bulk = new Map();
    const node = scope.querySelector('script.qby-qil-records[type="application/json"]');
    if (node && node.textContent.length <= 1500000) try {
      const rows=JSON.parse(node.textContent);
      if(Array.isArray(rows)) rows.slice(0,limit).filter(valid).forEach(row=>bulk.set(Number(row.id),row));
    } catch (_) {}
    if (!scope.matches('[data-qby-qil-shelf]')) return {rows:[...bulk.values()],cards:[]};
    const cards=[], byId=new Map();
    // Select the next unprocessed Beauty cards, so native load-more can append
    // beyond 48 without revisiting old cards or starving behind supplement cards.
    for (const card of scope.querySelectorAll('.wd-product[data-id]:not([data-qby-qil-ready]),li.product[data-product-id]:not([data-qby-qil-ready]),.product[data-product-id]:not([data-qby-qil-ready])')) {
      const id=Number(card.dataset.id||card.dataset.productId);let row=bulk.get(id);
      const json=card.querySelector('script.qby-qil-card-record[type="application/json"]');
      if(json && json.textContent.length <= 60000) try { const fresh=JSON.parse(json.textContent);if(valid(fresh) && Number(fresh.id)===id)row=fresh; } catch (_) {}
      if(!valid(row))continue;
      cards.push(card);byId.set(id,row);if(cards.length>=limit)break;
    }
    return {rows:[...byId.values()],cards};
  }
  function hydrate(scope) {
    const api = window.QILCards;
    if (api?.beautySchema !== 1 || typeof api.render !== 'function' || typeof api.upsert !== 'function') return;
    const {rows,cards} = records(scope); if (!rows.length) return;
    api.upsert(rows);
    const byId = new Map(rows.map(row=>[Number(row.id), row]));
    // Product-page button reuses QIL's existing exact-product draft flow.
    scope.querySelectorAll('[data-qby-product-ask]').forEach(button => {
      const id = Number(button.dataset.qbyProductAsk); if (!byId.has(id)) return;
      button.dataset.qimiaAiOpen = ''; button.dataset.qilAiIntent = 'product'; button.dataset.qimiaProductId = String(id);
    });
    if (!scope.matches('[data-qby-qil-shelf]')) return;
    let changed = false;
    cards.forEach((card,index) => {
      const id = Number(card.dataset.id || card.dataset.productId), row = byId.get(id);
      if (!row) return;
      const wrapper = card.querySelector('.product-wrapper'); if (!wrapper) return;
      const template = document.createElement('template'); template.innerHTML = api.render(row,index,'beauty');
      const article = template.content.firstElementChild;
      if (!article?.matches('.qil-product-card') || Number(article.dataset.productId) !== id) return;
      // Only after a valid native card exists do we replace the functional Woo fallback.
      wrapper.replaceChildren(article); wrapper.classList.add('qil-shell'); card.dataset.qbyQilReady = '1'; card.classList.add('qby-qil-cell');
      const grid = card.parentElement;
      if (grid?.matches('.products,.wd-products')) grid.setAttribute('data-qil-collection-grid','');
      changed = true;
    });
    if (changed) { scope.classList.add('qby-qil-ready'); api.refresh?.(); }
  }
  function refresh() {
    // Some native AJAX responses replace just the grid; adopt only grids carrying
    // exact Beauty records, without an observer or a new catalogue request.
    document.querySelectorAll('script.qby-qil-card-record').forEach(node=>{
      const grid=node.closest('.products,.wd-products');
      if(grid && !grid.closest('[data-qby-qil-shelf]')) { grid.dataset.qbyQilShelf=''; grid.classList.add('qby-qil-shelf'); }
    });
    document.querySelectorAll('[data-qby-qil-shelf],#qby-product-details').forEach(hydrate);
  }
  // Native AJAX can emit several completion events for one update. Hydrate once per frame.
  let queued = false;
  function scheduleRefresh() {
    if (queued) return;
    queued = true;
    requestAnimationFrame(() => { queued = false; refresh(); });
  }
  window.QimiaBeautyNativeCards = Object.freeze({version:1,refresh:scheduleRefresh});
  document.addEventListener('qil:cards-ready',scheduleRefresh);
  document.addEventListener('qby:shelf-ready',scheduleRefresh);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',refresh,{once:true}); else refresh();
  if (window.jQuery) {
    window.jQuery(document).on('updated_wc_div.qbyNativeCards pjax:end.qbyNativeCards wdLoadMoreLoadProducts.qbyNativeCards',scheduleRefresh);
    window.jQuery(document).on('ajaxComplete.qbyNativeCards',(_event,_xhr,settings) => {
      // Basket/session refreshes do not replace a product shelf.
      const url = String(settings?.url || '');
      if (/wc-ajax=(?:add_to_cart|remove_from_cart|get_refreshed_fragments|update_order_review)/.test(url)) return;
      if (document.querySelector('[data-qby-qil-shelf],script.qby-qil-card-record')) scheduleRefresh();
    });
  }
})();
