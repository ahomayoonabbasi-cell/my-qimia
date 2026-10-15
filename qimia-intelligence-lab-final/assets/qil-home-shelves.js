/* Homepage discovery only; native cards and scroll-snap carousel controls. */
(() => {
  'use strict';
  const home = document.querySelector('[data-qil-home-shelves]');
  const section = home?.querySelector('[data-qil-home-discover]');
  if (!section) return;
  let data;
  try { data = JSON.parse(section.querySelector('[data-qil-home-discovery-data]').textContent); } catch (_) { return; }
  const config = window.QIMIA_LAB || {};
  const ar = String(config.locale || document.documentElement.lang).startsWith('ar');
  const emptyText = ar
    ? {new:'لا توجد منتجات وصلت حديثاً حالياً.', restock:'لا توجد منتجات متوفرة من جديد حالياً.'}
    : {new:'No new arrivals available right now.', restock:'No restocked products available right now.'};
  const tabs = [...section.querySelectorAll('[data-qil-home-tab]')];
  const tablist = section.querySelector('[role="tablist"]');
  const panel = section.querySelector('[role="tabpanel"]');
  const grid = section.querySelector('[data-qil-home-grid]');
  const empty = section.querySelector('[data-qil-home-empty]');
  const now = () => window.QILCards.inventoryNow();
  // New Arrivals is the default for EVERY visitor, including signed-in accounts.
  let active = 'new', signature = '', ready = false, expiry = 0;

  function choices(kind) {
    const flag = kind === 'new' ? 'newArrival' : 'backInStock';
    const until = kind === 'new' ? 'newUntil' : 'restockUntil';
    return (Array.isArray(data[kind]) ? data[kind] : []).map(id => window.QILCards.product(id)).filter(p => {
      const inv = window.QILCards.inventory(p?.id) || p?.inventory;
      return p && inv?.inStock === true && inv[flag] === true && Number(inv[until]) > now()
        && p.purchase?.purchasable === true && Number(p.price?.value) > 0
        && String(p.price.currency) === String(config.currency);
    });
  }
  function render() {
    if (!ready) return;
    const pools = {new: choices('new'), restock: choices('restock')};
    const rows = pools[active];
    section.hidden = !pools.new.length && !pools.restock.length;
    tablist.hidden = section.hidden;
    for (const tab of tabs) {
      const selected = tab.dataset.qilHomeTab === active;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
    }
    panel.setAttribute('aria-labelledby', `qil-discover-${active}-tab`);
    const next = active + ':' + rows.map(p => {
      const inv = window.QILCards.inventory(p.id) || p.inventory;
      return `${p.id}:${inv.inStock}:${inv.newArrival}:${inv.backInStock}:${inv.newUntil}:${inv.restockUntil}`;
    }).join('|');
    if (signature !== next) {
      const switched = !signature.startsWith(active + ':');
      const position = grid.scrollLeft;
      grid.innerHTML = rows.map((p, i) => window.QILCards.render(p, i, 'home-discovery')).join('');
      grid.querySelectorAll('.qil-product-card').forEach(card => card.classList.add('is-visible'));
      grid.scrollLeft = switched ? 0 : position;
      signature = next;
      // Refresh native arrows after stock changes alter the scrollable content.
      requestAnimationFrame(() => window.QILCards.refresh());
    }
    empty.hidden = rows.length > 0;
    empty.textContent = rows.length ? '' : emptyText[active];
    clearTimeout(expiry);
    const ends = Object.entries(pools).flatMap(([kind, products]) => products.map(p => Number((window.QILCards.inventory(p.id) || p.inventory)?.[kind === 'new' ? 'newUntil' : 'restockUntil']))).filter(n => n > now());
    const end = Math.min(...ends);
    if (Number.isFinite(end) && document.visibilityState !== 'hidden') expiry = setTimeout(refresh, Math.min(2147480000, Math.max(100, (end - now()) * 1000 + 100)));
  }
  function refresh() { render(); window.QILCards.refresh(); }
  function select(tab, focus = false) {
    if (!ready || !tab) return;
    active = tab.dataset.qilHomeTab; refresh();
    if (focus) tab.focus();
  }
  tabs.forEach(tab => tab.addEventListener('click', () => select(tab)));
  tablist.addEventListener('keydown', event => {
    const index = tabs.indexOf(document.activeElement);
    if (index < 0 || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
    event.preventDefault();
    const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? (ar ? -1 : 1) : (ar ? 1 : -1)) + tabs.length) % tabs.length;
    select(tabs[next], true);
  });
  function start() {
    if (ready || !window.QILCards?.render || !window.QILCards?.upsert || !window.QILCards?.inventory || !window.QILCards?.inventoryNow) return;
    window.QILCards.upsert(Array.isArray(data.products) ? data.products : []);
    window.QILCards.upsert(Object.entries(data.inventory || {}).map(([id, inventory]) => {
      const product = window.QILCards.product(id);
      return product ? {...product, inventory} : null;
    }).filter(Boolean));
    ready = true; refresh();
  }
  document.addEventListener('qil:cards-ready', start, {once: true});
  document.addEventListener('qil:home-inventory-painted', render);
  document.addEventListener('visibilitychange', () => { if (ready) refresh(); });
  window.addEventListener('pagehide', () => clearTimeout(expiry));
  window.addEventListener('pageshow', () => { if (ready) refresh(); });
  start();
})();
