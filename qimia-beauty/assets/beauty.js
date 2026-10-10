(() => {
  'use strict';
  const brandSearch=document.getElementById('qby-brand-search');
  if(brandSearch){const cards=[...document.querySelectorAll('[data-qby-brand-name]')];let department='all';
    const filter=()=>{const q=brandSearch.value.trim().normalize('NFKC').toLocaleLowerCase();let count=0;cards.forEach(card=>{const match=card.dataset.qbyBrandName.normalize('NFKC').toLocaleLowerCase().includes(q) && (department==='all' || card.dataset.qbyBrandGroups.split(' ').includes(department));card.hidden=!match;if(match)count++;});const status=document.getElementById('qby-brand-status');if(status)status.textContent=`${count} ${window.QBYNavigation?.brandCountLabel || 'brands'}`;const empty=document.querySelector('[data-qby-brand-empty]');if(empty)empty.hidden=count>0;const featured=document.querySelector('.qby-featured-brands');if(featured)featured.hidden=q!=='' || department!=='all';};
    brandSearch.addEventListener('input',filter);document.querySelectorAll('[data-qby-brand-filter]').forEach(button=>button.addEventListener('click',()=>{department=button.dataset.qbyBrandFilter;document.querySelectorAll('[data-qby-brand-filter]').forEach(b=>b.setAttribute('aria-pressed',String(b===button)));filter();}));filter();
  }
  document.addEventListener('qimia:search-rendered',event=>{
    const query=String(event.detail?.query||'');const config=window.QBYNavigation;
    if(!config || !/hair|shampoo|conditioner|شعر|شامبو|شامپو|بلسم/i.test(query))return;
    const content=document.querySelector('[data-qil-search-fallback]');if(!content || content.querySelector('.qby-search-categories'))return;
    const wrap=document.createElement('div');wrap.className='qby-search-categories';const title=document.createElement('strong');title.textContent=config.categoryLabel;wrap.append(title);
    const links=/shampoo|conditioner|شامبو|شامپو|بلسم/i.test(query)?config.searchLinks.slice(0,1):config.searchLinks;
    links.forEach(item=>{try{const url=new URL(item.url,location.origin);if(url.origin!==location.origin)return;const a=document.createElement('a');a.href=url.href;a.textContent=item.label;wrap.append(a);}catch{}});content.prepend(wrap);
  });
})();

/* Bounded Beauty rows in the existing assistant comparison sheet. No transport or second modal. */
(() => {
  'use strict';
  if (window.QimiaBeautyCompare) return;
  const attached = new WeakSet();
  const text = (value, limit = 16000) => typeof value === 'string' || typeof value === 'number' ? String(value).trim().slice(0, limit) : '';
  const make = (tag, className, content) => { const n = document.createElement(tag); if (className) n.className = className; if (content !== undefined) n.textContent = content; return n; };
  function decorate(chat, products, data) {
    const contract = data?.qby_beauty_comparison;
    if (contract?.schema !== 1 || !Array.isArray(products) || products.length !== 2 || !Array.isArray(contract.rows)) return;
    const sheet = chat?.panel?.querySelector('.aaice-compare-sheet');
    const scroll = sheet?.querySelector('.aaice-compare-scroll');
    if (!scroll || scroll.querySelector('[data-qby-beauty-comparison]')) return;
    const ar = chat.uiLanguage?.() === 'ar', root = chat.root || document.getElementById('aaice-host')?.shadowRoot;
    if (root && !root.querySelector('#qby-beauty-compare-style')) {
      const style = make('style'); style.id = 'qby-beauty-compare-style';
      style.textContent = '.qby-compare-beauty{margin:18px 0 8px;border:1px solid #d9e8df;border-radius:16px;background:#fbfdfb;overflow:hidden}.qby-compare-beauty h3{font-family:inherit;font-size:14px;font-weight:700;line-height:1.5;margin:0;padding:15px 14px;border-bottom:1px solid #d9e8df;color:#204b48}.qby-compare-beauty .aaice-compare-row{grid-template-columns:minmax(88px,.7fr) repeat(2,minmax(0,1fr))}.qby-compare-beauty .aaice-compare-cell{overflow-wrap:anywhere;white-space:pre-wrap;font-size:12px;line-height:1.65}.qby-compare-beauty details{border-top:1px solid #d9e8df}.qby-compare-beauty summary{padding:13px 14px;min-height:44px;cursor:pointer;color:#205d53;font-size:12px;font-weight:650}.qby-compare-beauty details .aaice-compare-row{border-top:0}.qby-compare-note{margin:0;padding:12px 14px;font-size:11px;line-height:1.65;color:#657974}.qby-compare-beauty .qby-compare-canonical{font-size:10.5px;line-height:1.85;text-align:left;direction:ltr}.qby-compare-beauty summary:focus-visible{outline:2px solid #499c84;outline-offset:-3px}@media(max-width:480px){.qby-compare-beauty .aaice-compare-row{grid-template-columns:minmax(72px,.65fr) repeat(2,minmax(0,1fr))}.qby-compare-beauty .aaice-compare-cell{font-size:11px;padding:9px 7px}.qby-compare-beauty .aaice-compare-label{font-size:10px;padding:9px 7px}.qby-compare-beauty .qby-compare-canonical{font-size:10px}}';
      root.append(style);
    }
    const section = make('section', 'qby-compare-beauty'); section.dataset.qbyBeautyComparison = '1';
    section.append(make('h3', '', ar ? 'تفاصيل الجمال والعناية' : 'Beauty & care details'));
    const blank = ar ? 'غير مدرج' : 'Not listed';
    for (const entry of contract.rows.slice(0, 24)) {
      if (!Array.isArray(entry?.values) || entry.values.length !== 2) continue;
      const label = text(ar ? entry.label_ar : entry.label_en, 80);
      const values = entry.values.map(value => text(value?.[ar ? 'ar' : 'en']));
      if (!label || !values.some(Boolean)) continue;
      const row = make('div', 'aaice-compare-row'); row.setAttribute('role', 'group'); row.setAttribute('aria-label', label);
      const heading = make('div', 'aaice-compare-label', label); row.append(heading);
      values.forEach((value, index) => {
        const cell = make('div', 'aaice-compare-cell' + (entry.canonical ? ' qby-compare-canonical' : ''), value || blank);
        cell.setAttribute('role', 'group'); cell.setAttribute('aria-label', `${text(products[index]?.name, 180)}: ${label}`);
        if (entry.canonical && value) { cell.dir = 'ltr'; cell.lang = 'en'; }
        row.append(cell);
      });
      if (entry.detail) { const details = make('details'); details.append(make('summary', '', label), row); section.append(details); }
      else section.append(row);
    }
    section.append(make('p', 'qby-compare-note', text(ar ? contract.note_ar : contract.note_en, 400)));
    const nativeTable = scroll.querySelector('.aaice-compare-table');
    if (nativeTable) nativeTable.after(section); else scroll.append(section);
  }
  function attach() {
    const chat = window.AmirAIChat;
    if (!chat || typeof chat.renderCompareSheet !== 'function' || attached.has(chat)) return false;
    const render = chat.renderCompareSheet;
    chat.renderCompareSheet = function(products, data) { const result = render.apply(this, arguments); try { decorate(this, products, data); } catch (_) { /* Keep native comparison usable if its markup changes. */ } return result; };
    attached.add(chat);
    // A native comparison may have completed before this adapter was attached.
    if (chat.compareResponseCache instanceof Map) {
      // Native chat.js keys its 20-second table cache by currency and sorted IDs.
      // The payload includes both language branches; decorate selects the live UI locale.
      const ids = Array.isArray(chat.compareIds) && chat.compareIds.length === 2 ? chat.compareIds.map(Number).sort((a,b)=>a-b).join(':') : '';
      const currency = String(chat.bootstrapData?.market?.currency || '').toUpperCase();
      const cached = ids && /^[A-Z]{3}$/.test(currency) ? chat.compareResponseCache.get(`${currency}|${ids}`) : null;
      const age = cached ? Date.now() - Number(cached.time || 0) : Infinity;
      const products = cached?.data?.products;
      if (age >= 0 && age < 20000 && Array.isArray(products) && products.length === 2
        && products.map(p=>Number(p.id)).sort((a,b)=>a-b).join(':') === ids
        && products.every(p=>String(p.price?.currency || '').toUpperCase() === currency)) {
        try { decorate(chat, products, cached.data); } catch (_) { /* Native sheet remains usable. */ }
      }
    }
    return true;
  }
  window.QimiaBeautyCompare = Object.freeze({version:1, attach});
  // Existing smart card compare awaits open(), calls attach(), then compare(ids).
  // Other entry points attach through a one-shot load event or their explicit click.
  document.addEventListener('click', event => {
    const path = event.composedPath?.() || [event.target];
    if (!path.some(node => node instanceof Element && (node.id === 'aaice-launcher' || node.matches('[data-qimia-ai-open],[data-kimia-ai-open],[data-qby-smart-ask],[data-qby-smart-run],.aaice-product-ask,.aaice-compare-dock-action')))) return;
    if (attach()) return;
    // The native loader appends chat.js only after the same explicit click.
    // Script load is a DOM readiness event; no polling or MutationObserver is needed.
    queueMicrotask(() => {
      if (attach()) return;
      const source = [...document.scripts].find(script => /\/qimia-ai-commerce[^/]*\/assets\/js\/chat\.js(?:\?|$)/.test(script.src));
      if (source && source.dataset.qbyCompareReady !== '1') { source.dataset.qbyCompareReady = '1'; source.addEventListener('load', attach, {once:true}); }
    });
  }, true);
})();

// Three product cutouts follow page scrolling; no animation loop while idle/offscreen.
(() => {
  'use strict';
  const scene=document.querySelector('[data-qby-scroll-scene]');
  if(!scene || !('IntersectionObserver' in window))return;
  const motion=window.matchMedia('(prefers-reduced-motion: reduce)');
  let visible=false, listening=false, frame=0;
  const paint=()=>{frame=0;if(!visible || motion.matches || document.hidden)return;const rect=scene.getBoundingClientRect();const progress=Math.max(-1,Math.min(1,((innerHeight-rect.top)/(innerHeight+rect.height)-.5)*2));scene.style.setProperty('--qby-scroll',progress.toFixed(3));};
  const schedule=()=>{if(!frame && !document.hidden)frame=requestAnimationFrame(paint);};
  const sync=()=>{const active=visible&&!motion.matches;if(active&&!listening){window.addEventListener('scroll',schedule,{passive:true});window.addEventListener('resize',schedule,{passive:true});listening=true;}else if(!active&&listening){window.removeEventListener('scroll',schedule);window.removeEventListener('resize',schedule);listening=false;}if(active)schedule();else{cancelAnimationFrame(frame);frame=0;scene.style.removeProperty('--qby-scroll');}};
  new IntersectionObserver(entries=>{visible=entries[0].isIntersecting;sync();},{rootMargin:'80px'}).observe(scene);
  motion.addEventListener('change',sync);
  document.addEventListener('visibilitychange',()=>{if(!document.hidden&&listening)schedule();});
})();

// Finite, progressive entrance motion. Content remains visible when JS or the observer is unavailable.
(() => {
  'use strict';
  const root = document.querySelector('.qby-beauty');
  if (!root || !('IntersectionObserver' in window) || !Element.prototype.animate) return;
  const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const active = new Set();
  const seen = new WeakSet();
  const reveal = element => {
    if (seen.has(element) || motion.matches || document.hidden) return;
    seen.add(element);
    const delay = Math.min(180, Number(element.dataset.qbyRevealOrder || 0) * 45);
    const animation = element.animate([
      {opacity: 0.35, transform: 'translateY(18px)'},
      {opacity: 1, transform: 'translateY(0)'}
    ], {duration: 620, delay, easing: 'cubic-bezier(.2,.65,.25,1)'});
    active.add(animation);
    animation.finished.catch(() => {}).finally(() => active.delete(animation));
  };
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => { if (entry.isIntersecting) { reveal(entry.target); observer.unobserve(entry.target); } });
  }, {threshold: 0.08, rootMargin: '0px 0px -24px 0px'});
  const targets = root.querySelectorAll('.qby-hero-copy,.qby-hero-art,.qby-section-head,.qby-department-card,.qby-guide-grid>article,.qby-inside,.qby-brand-feature,.qby-ai-callout');
  root.querySelectorAll('.qby-department-grid,.qby-guide-grid').forEach(grid => {
    [...grid.children].forEach((element, index) => { element.dataset.qbyRevealOrder = String(index % 3); });
  });
  const sync = () => {
    if (motion.matches) { observer.disconnect(); active.forEach(animation => animation.cancel()); active.clear(); }
    else targets.forEach(element => { if (!seen.has(element)) observer.observe(element); });
  };
  const stop = () => { if (document.hidden) { active.forEach(animation => animation.cancel()); active.clear(); } };
  motion.addEventListener('change', sync);
  document.addEventListener('visibilitychange', stop);
  sync();
})();
