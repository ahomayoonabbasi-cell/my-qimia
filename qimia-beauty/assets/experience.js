(() => {
  'use strict';
  const roots = [...document.querySelectorAll('[data-qbx-experience]')];
  if (!roots.length) return;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  let paused = reduce.matches;
  const scenes = [...document.querySelectorAll('[data-qbx-parallax]')];
  const visibleScenes = new Set();
  let frame = 0;
  const setMotion = () => {
    roots.forEach(root => {
      root.classList.toggle('qbx-paused', paused);

    });
    if (paused) {
      cancelAnimationFrame(frame); frame = 0;
      scenes.forEach(scene => scene.style.removeProperty('--qbx-shift'));
      document.querySelectorAll('.qbx-waiting').forEach(el => el.classList.remove('qbx-waiting'));
    }
  };
  setMotion();
  reduce.addEventListener('change', event => { paused = event.matches; setMotion(); });
  if ('IntersectionObserver' in window) {
    const reveal = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) { entry.target.classList.remove('qbx-waiting'); reveal.unobserve(entry.target); }
    }), { threshold: .08, rootMargin: '0px 0px 35px 0px' });
    roots.forEach(root => {
      root.classList.add('qbx-motion-ready');
      root.querySelectorAll('[data-qbx-reveal]').forEach(el => {
        if (!paused && el.getBoundingClientRect().top > innerHeight) el.classList.add('qbx-waiting');
        reveal.observe(el);
      });
    });
    const sceneObserver = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) visibleScenes.add(entry.target); else visibleScenes.delete(entry.target);
    }));
    scenes.forEach(scene => sceneObserver.observe(scene));
    const paint = () => {
      frame = 0;
      if (paused || document.hidden) return;
      visibleScenes.forEach(scene => {
        const box = scene.getBoundingClientRect();
        const amount = Math.max(-18, Math.min(18, (innerHeight / 2 - box.top - box.height / 2) * .055));
        scene.style.setProperty('--qbx-shift', `${amount.toFixed(1)}px`);
      });
    };
    addEventListener('scroll', () => { if (!paused && visibleScenes.size && !frame) frame = requestAnimationFrame(paint); }, { passive: true });
    document.addEventListener('visibilitychange', () => { if(document.hidden) { cancelAnimationFrame(frame); frame=0; } });
  }
  roots.forEach(root => {
    root.querySelectorAll('[data-qbx-ritual-tab]').forEach(button => button.addEventListener('click', () => {
      root.querySelectorAll('[data-qbx-ritual-tab]').forEach(tab => tab.setAttribute('aria-pressed', String(tab === button)));
      root.querySelectorAll('[data-qbx-ritual-path]').forEach(path => { path.hidden = path.dataset.qbxRitualPath !== button.dataset.qbxRitualTab; });
    }));
  });

  const root = document.querySelector('.qbx-experience');
  const form = root?.querySelector('[data-qbx-filters]');
  const results = root?.querySelector('[data-qbx-results]');
  if (!form || !results || !window.fetch || !window.DOMParser) return;
  const ar = root.dataset.qbxLanguage === 'ar';
  const status = root.querySelector('[data-qbx-status]');
  const shop = root.querySelector('#qby-shop');
  const fields = ['search','department','brand','orderby','stock','collection','qby_page','care','skin_type','hair_type','finish','coverage','shade','concentration'];
  let controller, timer, sequence = 0;
  const sync = url => {
    fields.forEach(key => {
      const input = form.elements.namedItem(key); if(!input) return;
      const value = url.searchParams.get(key) || ({department:'beauty',orderby:'menu_order',collection:'all',qby_page:'1'}[key] || '');
      if (input.type === 'checkbox') input.checked = value === input.value;
      else input.value = value;
    });
    const current = url.searchParams.get('collection') || 'all';
    root.querySelectorAll('[data-qbx-collection]').forEach(link => {
      if(link.dataset.qbxCollection === current) link.setAttribute('aria-current','true'); else link.removeAttribute('aria-current');
    });
  };
  const formURL = () => {
    const url = new URL(form.action, location.href);
    url.search = '';
    new FormData(form).forEach((value,key) => { if(fields.includes(key) && value) url.searchParams.set(key,value); });
    return url;
  };
  const load = async (url, {historyMode='push', scroll=false}={}) => {
    if (url.origin !== location.origin || url.pathname !== new URL(form.action).pathname) { location.href = url.href; return; }
    clearTimeout(timer);controller?.abort();controller = new AbortController();
    const ownSequence = ++sequence;
    results.setAttribute('aria-busy','true');
    status.textContent = ar ? 'جارٍ تحديث المنتجات…' : 'Updating products…';
    try {
      // Reuse the native server-rendered page, locale, session and currency. No new public endpoint.
      const response = await fetch(url.href.split('#')[0], { signal: controller.signal, credentials:'same-origin', headers:{'X-Requested-With':'QimiaBeauty'} });
      if(!response.ok) throw new Error('Catalogue unavailable');
      const page = new DOMParser().parseFromString(await response.text(),'text/html');
      const next = page.querySelector('[data-qbx-results]');
      if(!next || ownSequence !== sequence) throw new Error('Unexpected catalogue');
      // Native cards are server-rendered. Event owners use delegated handlers; no fetched scripts execute.
      results.replaceChildren(...[...next.childNodes].map(node => document.importNode(node,true)));
      results.querySelectorAll('script:not([type="application/json"])').forEach(script => script.remove());
      sync(url);
      if(historyMode==='push') history.pushState({qbx:true},'',url);
      if(historyMode==='replace') history.replaceState({qbx:true},'',url);
      status.textContent = results.querySelector('#qbx-count')?.textContent || (ar?'تم تحديث المنتجات.':'Products updated.');
      if(scroll) shop.scrollIntoView({behavior:paused?'auto':'smooth',block:'start'});
      document.dispatchEvent(new CustomEvent('qby:shelf-ready'));
    } catch(error) {
      if(error.name === 'AbortError' || ownSequence !== sequence) return;
      // Standard GET navigation remains a complete fallback, including native card initialization.
      status.textContent = ar ? 'جارٍ فتح نتائج البحث…' : 'Opening your search results…';
      location.assign(url.href);
    } finally {
      if(ownSequence === sequence) results.removeAttribute('aria-busy');
    }
  };
  form.addEventListener('submit', event => { event.preventDefault(); load(formURL()); });
  form.addEventListener('change', () => { form.elements.qby_page.value='1'; load(formURL()); });
  form.elements.search.addEventListener('input', () => { clearTimeout(timer);timer=setTimeout(() => {form.elements.qby_page.value='1';load(formURL(),{historyMode:'replace'});},400); });
  root.addEventListener('click', event => {
    const target=event.target.closest('a');if(!target || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button!==0) return;
    if(target.matches('[data-qbx-collection]')){
      event.preventDefault();form.elements.collection.value=target.dataset.qbxCollection;form.elements.qby_page.value='1';load(formURL());
    } else if(target.matches('[data-qbx-department]')){
      event.preventDefault();const url=new URL(target.href);load(url,{scroll:true});
    } else if(target.matches('[data-qbx-reset],.qbx-pagination a')){
      event.preventDefault();load(new URL(target.href),{scroll:target.matches('.qbx-pagination a')});
    }
  });
  addEventListener('popstate', () => { sync(new URL(location.href));load(new URL(location.href),{historyMode:'none'}); });
})();
