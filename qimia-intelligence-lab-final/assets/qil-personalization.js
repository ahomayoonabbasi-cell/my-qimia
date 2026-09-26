/* Qimia unified shelves. One request in flight, bounded memory, no duplicate
   browsing store. My Qimia owns account/session recent views; WooCommerce's
   browser-local recent cookie is a fallback hint only. No AI dependency. */
(() => {
  'use strict';
  const C = window.QIMIA_LAB || {}, cards = window.QILCards;
  if (!C.personalizationEnabled || !cards || window.QILPersonalization) return;
  const anchors = [...document.querySelectorAll('[data-qil-personal-anchor]')];
  if (!anchors.length) return;
  const hosts = anchors.map(a => a.querySelector('[data-qil-personal]')).filter(Boolean);
  const surface = hosts[0]?.dataset.qilPersonal || 'home';
  const lang = () => String(C.locale || document.documentElement.lang || 'en').startsWith('ar') ? 'ar' : 'en';
  const t = (en, ar) => lang() === 'ar' ? ar : en;
  const ids = (input, max = 64) => [...new Set((Array.isArray(input) ? input : []).map(Number))].filter(n => Number.isSafeInteger(n) && n > 0).slice(0, max);
  const uuid = () => window.crypto?.randomUUID?.() || 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {const n=Math.random()*16|0;return (c==='x'?n:(n&3|8)).toString(16);});
  const endpoint = name => {
    try { const u = new URL(String(C.wcAjaxUrl || '').replace(/%%endpoint%%|%25%25endpoint%25%25/g, name), location.href); return C.wcAjaxUrl && u.origin === location.origin ? u.href : ''; }
    catch (_) { return ''; }
  };
  let payload = null, active = '', activeByUser = false, ready = false, stopped = false, epoch = 0, binding = '', privacyDenied = false;
  let inFlight = null, timer = 0, dirty = false, forced = false, lastKey = '', acceptedAt = 0, errors = 0, requestCount = 0;
  let controller = null, visibleAt = Date.now(), observer = null, entryObserver = null;
  let queue = [], eventTimer = 0, sending = false, retries = 0;
  const hiddenIds = new Set(), pendingOrigins = new Map(), dwell = new Map(), observed = new WeakSet();
  const labels = () => ({
    discover:t('Explore the store','اكتشف المتجر'), signin:t('Buy again','اشترِ مجدداً'),
    again: t('Buy again','اشترِ مجدداً'), recent: t('Recently viewed','شاهدتها مؤخراً'),
    categories: t('More from your categories','المزيد من فئاتك'),
    related: t('Related to your interests','مرتبطة باهتماماتك'), cart: t('Complete your selection','أكمل اختياراتك')
  });
  const descriptions = () => ({
    discover:t('Available picks from the store.','اختيارات متوفرة من المتجر.'),
    signin:t('Sign in to find your previous purchases.','سجّل الدخول للاطلاع على مشترياتك السابقة.'),
    again: t('Your previous selections, available now. One item at the current price.','اختياراتك السابقة المتوفرة الآن. قطعة واحدة بالسعر الحالي.'),
    recent: t('Return to products you actually opened.','ارجع إلى المنتجات التي فتحتها مؤخراً.'),
    categories: t('More available products from categories you viewed or bought from.','منتجات متوفرة أخرى من الفئات التي شاهدتها أو اشتريت منها.'),
    related: t('Selected from what you are exploring now.','اختيارات مرتبطة بما تتصفحه الآن.'),
    cart: t('Relevant items outside your cart. Your checkout stays unchanged.','اختيارات مرتبطة ليست في سلتك. أكمل الدفع كالمعتاد.')
  });
  function myQimiaRecentlyViewed() {
    if(surface==='cart')return [];
    try {
      const rows=window.QimiaShopping?.recentViews?.()
        || window.QimiaMyQimiaContext?.recentViews?.(8)
        || window.QimiaShoppingContext?.recent?.()
        || [];
      return ids((Array.isArray(rows)?rows:[]).map(row=>row&&typeof row==='object'?row.product_id:row),12);
    } catch (_) { return []; }
  }
  function wooRecentlyViewed() {
    if(surface==='cart')return [];
    try {
      const row=String(document.cookie||'').split(';').map(v=>v.trim()).find(v=>v.startsWith('woocommerce_recently_viewed='));
      if(!row)return [];
      const raw=decodeURIComponent(row.slice(row.indexOf('=')+1));
      // Woo stores a pipe-delimited list. Reverse it so the newest verified
      // page view is considered first; the server still validates every ID.
      return ids(raw.split('|').reverse(),12);
    } catch (_) { return []; }
  }
  function recentHints(){return privacyDenied?[]:ids([...myQimiaRecentlyViewed(),...wooRecentlyViewed()],12);}
  function context() {
    const task = window.QimiaShopping?.snapshot?.() || window.QimiaShoppingContext?.snapshot?.() || {};
    const excluded = [...hiddenIds];
    if (surface === 'product') {
      excluded.push(...ids(C.collections?.related || []));
      document.querySelectorAll('.related.products [data-product_id],.related.products [data-product-id]').forEach(n => excluded.push(n.dataset.product_id || n.dataset.productId));
    }
    return {surface, locale:lang(), current:Number(C.productId || 0),
      selected:Number(task.selected_product || task.product_id || 0),
      compare:ids([...cards.context().compare, ...(task.compare_ids || task.compare || [])], 3),
      results:ids(task.result_ids || task.products || [], 8), recent:recentHints(), filters:cards.filters(),
      exclude:ids(excluded), dismissed:ids([...hiddenIds],24), task:String(task.task_id || '')};
  }
  const keyOf = c => JSON.stringify({...c, task:'', currency:String(C.currency || '')});
  // Guests: the server result is public catalogue data for this exact page
  // context, so one copy per tab is reused for a few minutes. Signed-in
  // shoppers (restNonce present) always read live; account data is never
  // stored in the browser.
  const GUEST_STORE = 'qil_personal_guest_v1', GUEST_TTL = 300000;
  let storeBlocked = !!C.restNonce;
  const cookie = name => { try { const row = String(document.cookie || '').split(';').map(v => v.trim()).find(v => v.startsWith(name + '=')); return row ? decodeURIComponent(row.slice(name.length + 1)) : ''; } catch (_) { return ''; } };
  const guestKey = key => `${C.version || ''}|${C.currency || ''}|${C.country || ''}|${key}|${cookie('woocommerce_cart_hash')}`;
  function guestRead(key) {
    if (storeBlocked) return null;
    try {
      const store = JSON.parse(sessionStorage.getItem(GUEST_STORE) || '[]'), id = guestKey(key);
      const row = Array.isArray(store) ? store.find(r => r && r.k === id) : null;
      return row && Date.now() - Number(row.at) < GUEST_TTL && typeof row.d === 'string' ? JSON.parse(row.d) : null;
    } catch (_) { return null; }
  }
  function guestWrite(key, data) {
    if (storeBlocked || data?.authenticated !== false || data?.personal) return;
    try {
      const id = guestKey(key), now = Date.now();
      let store = JSON.parse(sessionStorage.getItem(GUEST_STORE) || '[]');
      store = (Array.isArray(store) ? store : []).filter(r => r && r.k !== id && now - Number(r.at) < GUEST_TTL).slice(-5);
      store.push({k:id, at:now, d:JSON.stringify(data)});
      sessionStorage.setItem(GUEST_STORE, JSON.stringify(store));
    } catch (_) { /* Storage refusal only means the next page reads live. */ }
  }
  // A first-time guest on Home with no cart, history or task: the server can
  // only answer "no groups" (then the page's own public selections are shown),
  // so that answer is produced here without a PHP request.
  function emptyGuestAnswer(c) {
    if (storeBlocked || surface !== 'home' || cookie('woocommerce_items_in_cart') || cookie('woocommerce_cart_hash')) return null;
    if (c.current || c.selected || c.compare.length || c.results.length || c.recent.length) return null;
    return {schema:'qil-shopping/1', surface, locale:c.locale, filters:c.filters, currency:String(C.currency || ''), country:'',
      authenticated:false, personal:false, binding:'', nonce:'', eventNonce:'', groups:[], products:[], purchases:[],
      serverTime:Math.floor(Date.now() / 1000), diagnostics:{local:true}};
  }
  function disconnectCards() {
    observer?.disconnect(); observer = null;
    dwell.forEach(clearTimeout); dwell.clear();
  }
  function hide() {
    disconnectCards();
    hosts.forEach(host => {host.hidden=true;host.querySelector('[data-qil-personal-grid]')?.replaceChildren();host.querySelector('[data-qil-personal-tabs]')?.replaceChildren();});
    document.documentElement.classList.remove('qil-unified-shopping-ready');
  }
  function reset() {
    storeBlocked = true; // Account, consent or restore transitions always read live.
    epoch++; controller?.abort(); clearTimeout(timer); timer=0; dirty=false;forced=false;
    payload=null;lastKey='';binding='';active='';activeByUser=false;acceptedAt=0;queue=[];retries=0;errors=0;
    clearTimeout(eventTimer);eventTimer=0;pendingOrigins.clear();hiddenIds.clear();hide();cards.clear();
  }
  function moveBelowRelated() {
    if (surface !== 'product') return;
    const anchor=anchors[0];
    // Includes native Woo related products where the theme renders them after
    // the Qimia band. Move only our own node, never the theme's existing blocks.
    const options=[...document.querySelectorAll('#qil-product-alternatives,.related.products,.wd-products-related')].filter(n=>!n.closest('[data-qil-personal-anchor]'));
    const last=options.reduce((a,n)=>!a || a.compareDocumentPosition(n)&Node.DOCUMENT_POSITION_FOLLOWING?n:a,null);
    if (!last || !anchor || anchor.contains(last)) return;
    if (anchor.compareDocumentPosition(last)&Node.DOCUMENT_POSITION_FOLLOWING) {
      let shell=anchor.closest('.qil-shell');
      if (!last.closest('.qil-shell')) {
        // Retain the exact QIL card styling scope when the native related block
        // lies outside the band. Do not move the product tools or their forms.
        const wrap=document.createElement('div');wrap.className='qil-shell qil-personal-pdp-shell notranslate';
        wrap.dir=lang()==='ar'?'rtl':'ltr';wrap.lang=lang();wrap.dataset.qilShell='';wrap.dataset.qilLocale=lang();
        last.after(wrap);wrap.append(anchor);
      } else last.after(anchor);
    }
  }
  function guestSelections(data,c) {
    if(surface!=='home' || data.authenticated!==false)return;
    // General store selections are never presented as a purchase or view history.
    const available=new Set(data.products.map(p=>Number(p.id)));
    if(!data.groups.some(g=>g.items?.some(row=>available.has(Number(row.id))))){
      const records=cards.publicSelections?.(c.exclude)||[];
      if(records.length){data.products=records;data.groups=[{key:'discover',items:records.map(p=>({id:p.id}))}];}
    }
    try {
      const account=new URL(C.accountUrl||'',location.href);
      if(C.accountUrl && account.origin===location.origin)
        data.groups.push({key:'signin',items:[],accountUrl:account.href});
    } catch (_) {}
  }
  function groupFor(key) { return (payload?.groups || []).find(g=>g.key===key); }
  function render() {
    disconnectCards();
    if(!payload || !Array.isArray(payload.groups) || !payload.groups.length){hide();return;}
    const names=labels(),text=descriptions();
    const byId=new Map(payload.products.map(p=>[Number(p.id),p]));
    const groups=payload.groups.filter(g=>g?.key && Array.isArray(g.items) && (g.key==='signin' || g.items.some(row=>byId.has(Number(row.id)))));
    if(!groups.length){hide();return;}
    // Prefer the current-interest view whenever it has eligible products.
    // Keep the existing tab order unchanged, and never override a tab the shopper
    // deliberately selected during background refreshes. If Related is unavailable,
    // fall back to Buy Again, then Recently Viewed on Home, then the first valid group.
    const preferred=groups.find(g=>g.key==='related')
      || groups.find(g=>g.key==='again')
      || (surface==='home' ? groups.find(g=>g.key==='recent') : null) || groups[0];
    if(!activeByUser || !groups.some(g=>g.key===active)){
      active=preferred.key;activeByUser=false;
    }
    const selected=groups.find(g=>g.key===active);
    let cardsChanged=false;
    hosts.forEach(host=>{
      const tabs=host.querySelector('[data-qil-personal-tabs]'),grid=host.querySelector('[data-qil-personal-grid]');
      if(!tabs || !grid)return;
      const prefix=`qil-personal-${host.dataset.qilPersonal}`;
      const focusKey=tabs.contains(document.activeElement)?document.activeElement.dataset.qilPersonalTab:null;
      const tabKeys=groups.map(group=>group.key).join('|');
      const rebuildTabs=tabs.dataset.qilTabKeys!==tabKeys || tabs.children.length!==groups.length;
      if(rebuildTabs){tabs.replaceChildren();tabs.dataset.qilTabKeys=tabKeys;}
      groups.forEach(group=>{
        if(!rebuildTabs){
          const button=[...tabs.children].find(n=>n.dataset.qilPersonalTab===group.key);
          if(button){button.setAttribute('aria-selected',String(group.key===active));button.tabIndex=group.key===active?0:-1;}
          return;
        }
        const button=document.createElement('button');button.type='button';button.dataset.qilPersonalTab=group.key;
        button.id=`${prefix}-tab-${group.key}`;button.setAttribute('role','tab');button.setAttribute('aria-controls',`${prefix}-panel`);
        button.setAttribute('aria-selected',String(group.key===active));button.tabIndex=group.key===active?0:-1;
        button.textContent=names[group.key]||names.related;tabs.append(button);
      });
      tabs.hidden=groups.length===1 && surface==='cart';
      const sameTab=grid.dataset.qilActive===active, scroll=grid.scrollLeft;
      const existing=new Map([...grid.children].map(card=>[Number(card.dataset.productId),card]));
      const next=[];
      grid.setAttribute('aria-labelledby',`${prefix}-tab-${active}`);
      selected.items.slice(0,surface==='cart'?4:12).forEach((row,index)=>{
        const product=byId.get(Number(row.id));if(!product || hiddenIds.has(Number(row.id)))return;
        const cardRole=row.key?'buy-again':(active==='categories'?'personal-related':`personal-${active}`);
        // Ignore changing analytics tokens when deciding whether UI needs replacing.
        const signature=JSON.stringify([product,row.key||'',cardRole,row.reason||'']);
        let card=existing.get(Number(row.id));
        if(!card || card._qilCardSignature!==signature){
          const template=document.createElement('template');
          template.innerHTML=cards.render(product,index,cardRole,row.key||'');
          card=template.content.firstElementChild;if(!card)return;
          card._qilCardSignature=signature;cardsChanged=true;
        }
        card.dataset.qilPersonalCard=active;
        if(selected.presentation)card.dataset.qilPresentation=selected.presentation;
        else delete card.dataset.qilPresentation;
        card.setAttribute('aria-description',row.key?text.again:(row.reason==='merchant_cross_sell'?t('Related item selected by the store.','منتج مرتبط اختاره المتجر.'):text[active]));
        next.push(card);
      });
      if(active==='signin'){
        let panel=grid.querySelector('.qil-personal-signin');
        if(!panel){
          panel=document.createElement('div');panel.className='qil-personal-signin';
          const title=document.createElement('strong');title.textContent=t('Your favourites, ready for another order','منتجاتك المفضلة، جاهزة لطلب جديد');
          const note=document.createElement('p');note.textContent=t('Sign in to see items from your orders and buy them again.','سجّل الدخول لعرض منتجات طلباتك وشرائها مجدداً.');
          const link=document.createElement('a');link.className='qil-button qil-button-primary';link.href=selected.accountUrl;
          link.textContent=t('Sign in to your account','تسجيل الدخول إلى حسابك');panel.append(title,note,link);
        }
        next.push(panel);
      }
      grid.classList.toggle('qil-signin-panel',active==='signin');
      host.querySelector('[data-qil-rail-nav]')?.toggleAttribute('hidden',active==='signin');
      next.forEach((card,index)=>{if(grid.children[index]!==card){grid.insertBefore(card,grid.children[index]||null);cardsChanged=true;}});
      while(grid.children.length>next.length){grid.lastElementChild.remove();cardsChanged=true;}
      const description=host.querySelector('[data-qil-personal-description]');if(description)description.textContent=text[active]||text.related;
      const status=host.querySelector('[data-qil-repeat-status]');if(status)status.textContent='';
      host.hidden=!grid.children.length;grid.dataset.qilActive=active;grid.scrollLeft=sameTab?scroll:0;
      if(focusKey)tabs.querySelector(`[data-qil-personal-tab="${focusKey}"]`)?.focus({preventScroll:true});
    });
    if(!hosts.every(h=>h.hidden))document.documentElement.classList.add('qil-unified-shopping-ready');
    if(cardsChanged)cards.refresh();observeVisible();
  }
  function schedule(force = false, delay = 220) {
    if(stopped)return;
    if(!ready){forced ||= force;return;}
    if(force)forced=true;
    dirty=true;clearTimeout(timer);
    timer=setTimeout(()=>{timer=0;void load();},delay);
  }
  function accept(data,c,key,live) {
    if(data.error || data.schema!=='qil-shopping/1' || data.surface!==surface || data.locale!==lang()
      || String(data.currency)!==String(C.currency) || JSON.stringify(data.filters)!==JSON.stringify(c.filters)
      || !Array.isArray(data.products) || !Array.isArray(data.groups))throw Error('personalization_context');
    if(live)guestWrite(key,data);
    if(binding && binding!==data.binding){queue=[];pendingOrigins.clear();cards.clear();active='';activeByUser=false;hide();}
    if(privacyDenied){data.personal=false;data.eventNonce='';data.groups=data.groups.filter(g=>g.key==='again'||g.key==='cart');data.groups.forEach(g=>delete g.presentation);}
    guestSelections(data,c);
    binding=String(data.binding||'');payload=data;lastKey=key;acceptedAt=Date.now();errors=0;
    if(!data.personal){queue=[];clearTimeout(eventTimer);eventTimer=0;}
    cards.hydrate(data.products,data.purchases||[],data.authenticated?data.nonce:'');render();
  }
  var automatedAgent=/googlebot|googleother|google-inspectiontool|adsbot-google|bingbot|bingpreview|applebot|yandex|baiduspider|duckduckbot|ahrefsbot|semrushbot|petalbot|bytespider|gptbot|oai-searchbot|chatgpt-user|claudebot|facebookexternalhit|meta-externalagent/i.test(navigator.userAgent||'');
  async function load() {
    if(stopped || !ready || document.hidden || inFlight)return;
    const c=context(),key=keyOf(c);
    if(!forced && key===lastKey && acceptedAt && Date.now()-acceptedAt<60000){dirty=false;return;}
    const known=guestRead(key)||emptyGuestAnswer(c);
    if(known){
      try{forced=false;dirty=false;accept(known,c,key,false);return;}
      catch(_){/* Fall through to a live read. */}
    }
    // Search/preview crawlers render the cached page only; a personal shelf
    // has no value to them and each read is a full PHP request.
    if(automatedAgent)return;
    const url=endpoint('qil_personalize');if(!url)return;
    const generation=epoch;forced=false;dirty=false;requestCount++;
    // Do not leave contradictory old recommendations visible while filters or
    // currency change. Ordinary catalogue cards remain untouched.
    // Keep the current shelf in place during ordinary context refreshes.
    // Explicit filter, currency, consent and account transitions still clear
    // their old context in their existing handlers below.
    const current=new AbortController();controller=current;
    const timeout=setTimeout(()=>current.abort(),8500);
    inFlight=(async()=>{
      try{
        const response=await fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',signal:current.signal,
          headers:{Accept:'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Qimia-Request':'shopping/1'},
          body:new URLSearchParams({context:JSON.stringify(c)})});
        if(!response.ok)throw Object.assign(Error('personalization_unavailable'),{status:response.status});
        const data=await response.json();if(generation!==epoch||stopped)return;
        if(key!==keyOf(context())){dirty=true;return;}
        accept(data,c,key,true);
      }catch(error){
        if(generation===epoch&&!stopped){
          // Keep accepted content during a transient refresh failure only when
          // its account, filters and currency context still match this request.
          if(!payload || lastKey!==key || error?.status===401 || error?.status===403 || error?.message==='personalization_context'){payload=null;hide();cards.clear();}
          // A mobile timeout deserves the same single retry as a network error.
          // Aborts from reset/logout have a different epoch and cannot retry here.
          if(errors++<1)schedule(true,1800);
        }
      }finally{
        clearTimeout(timeout);inFlight=null;
        if(dirty&&!stopped&&!timer)schedule(forced,240);
      }
    })();
    return inFlight;
  }
  function enqueue(type,card) {
    if(!payload?.personal || !payload.eventNonce || !card?.dataset.qilPresentation)return;
    const product=Number(card.dataset.productId);if(!product)return;
    if(queue.length>=50)queue.shift();
    queue.push({id:uuid(),type,product,presentation:card.dataset.qilPresentation});
    if(!eventTimer)eventTimer=setTimeout(()=>flush(false),type==='recommendation_view'?1000:80);
  }
  async function flush(unload=false) {
    clearTimeout(eventTimer);eventTimer=0;
    if(sending || !queue.length || !payload?.personal || !payload.eventNonce || navigator.onLine===false)return;
    const events=queue.slice(0,10),nonce=payload.eventNonce,generation=epoch,url=endpoint('qil_personal_events');if(!url)return;
    sending=true;const abort=new AbortController(),timeout=setTimeout(()=>abort.abort(),6500);
    try{
      const response=await fetch(url,{method:'POST',credentials:'same-origin',cache:'no-store',keepalive:unload,signal:abort.signal,
        headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8','X-Qimia-Request':'shopping/1'},
        body:new URLSearchParams({nonce,events:JSON.stringify(events)})});
      if(generation!==epoch)return;
      if(response.status===401||response.status===403){queue=[];if(payload)payload.personal=false;return;}
      if(!response.ok)throw Error('activity_unavailable');
      const result=await response.json();if(generation!==epoch)return;
      if(result.error)throw Error('activity_unavailable');
      // Invalid/expired presentation references are not retried indefinitely.
      const sent=new Set(events.map(e=>e.id));queue=queue.filter(e=>!sent.has(e.id));retries=0;
    }catch(_){if(generation===epoch&&++retries>2)queue=queue.filter(e=>!events.some(sent=>sent.id===e.id));}
    finally{clearTimeout(timeout);sending=false;if(queue.length&&!unload&&generation===epoch&&!stopped)eventTimer=setTimeout(()=>flush(false),retries?1200*retries:80);}
  }
  function observeVisible() {
    if(!payload?.personal || !('IntersectionObserver'in window))return;
    observer=new IntersectionObserver(entries=>entries.forEach(entry=>{
      clearTimeout(dwell.get(entry.target));dwell.delete(entry.target);
      if(!entry.isIntersecting||entry.intersectionRatio<.55||document.hidden||observed.has(entry.target))return;
      dwell.set(entry.target,setTimeout(()=>{
        dwell.delete(entry.target);if(!entry.target.isConnected||document.hidden||observed.has(entry.target))return;
        observed.add(entry.target);enqueue('recommendation_view',entry.target);observer?.unobserve(entry.target);
      },950));
    }),{threshold:[0,.55]});
    document.querySelectorAll('[data-qil-personal-card]').forEach(card=>observer.observe(card));
  }
  function tab(key,focus=false){
    if(!groupFor(key))return;active=key;activeByUser=true;render();
    if(focus)document.querySelector(`[data-qil-personal-tab="${key}"]`)?.focus({preventScroll:true});
    if(Date.now()-acceptedAt>60000)schedule(true);
  }
  document.addEventListener('keydown',event=>{
    const button=event.target.closest?.('[data-qil-personal-tab]');if(!button)return;
    const tabs=[...button.parentElement.querySelectorAll('[data-qil-personal-tab]')],index=tabs.indexOf(button);let next=index;
    if(event.key==='Home')next=0;else if(event.key==='End')next=tabs.length-1;
    else if(event.key==='ArrowRight')next=(index+(lang()==='ar'?-1:1)+tabs.length)%tabs.length;
    else if(event.key==='ArrowLeft')next=(index+(lang()==='ar'?1:-1)+tabs.length)%tabs.length;else return;
    event.preventDefault();tab(tabs[next].dataset.qilPersonalTab,true);
  });
  window.addEventListener('click',event=>{
    const target=event.target instanceof Element?event.target:null;if(!target)return;
    if(target.closest('a[href*="customer-logout"],a[href*="action=logout"]')){reset();stopped=true;return;}
    const button=target.closest('[data-qil-personal-tab]');if(button){event.preventDefault();tab(button.dataset.qilPersonalTab);return;}
    const card=target.closest('[data-qil-personal-card]');
    if(card && target.closest('a,button'))enqueue(target.closest('[data-qil-repeat-buy]')?'reorder_click':'recommendation_click',card);
    const quick=target.closest('[data-qil-quick-view-id]');
    if(quick){const pid=Number(quick.dataset.qilQuickViewId);pendingOrigins.delete(pid);if(card?.dataset.qilPresentation)pendingOrigins.set(pid,card.dataset.qilPresentation);}
    const remove=target.closest('.remove[data-product_id],.remove_from_cart_button[data-product_id]');
    if(remove){hiddenIds.add(Number(remove.dataset.product_id));while(hiddenIds.size>24)hiddenIds.delete(hiddenIds.values().next().value);}
  },true);
  const changed=()=>schedule(false,320);
  document.addEventListener('qimia:shopping-context',changed);
  document.addEventListener('qimia:recent-product-view',changed);
  document.addEventListener('qil:filters-changed',()=>{hide();schedule(true,250);});
  document.addEventListener('qimia:search-rendered',changed);
  document.addEventListener('qimia:connection-saved',()=>{reset();schedule(true,300);});
  document.addEventListener('qmq:account-changed',()=>{reset();schedule(true,300);});
  document.addEventListener('qimia_currency_changed',()=>{hide();lastKey='';schedule(true,450);});
  document.addEventListener('click',event=>{
    const consent=event.target.closest?.('[data-personal-consent]');
    if(consent){
      privacyDenied=consent.dataset.personalConsent!=='yes';
      reset();schedule(true,1200);
    }
  },true);
  if(window.jQuery){
    window.jQuery(document.body)
      .on('adding_to_cart.qilPersonal',(_e,button,data)=>{
        const el=button?.[0]||button;const ref=el?.closest?.('[data-qil-presentation]')?.dataset.qilPresentation;
        if(ref && data && typeof data==='object')data.qil_presentation=ref;
      })
      .on('added_to_cart.qilPersonal',(_e,_fragments,_hash,button)=>{
        const el=button?.[0]||button;const pid=Number(el?.dataset?.qilParentId||el?.dataset?.product_id||0);
        if(pid)hiddenIds.add(pid);schedule(true,280);
      })
      .on('removed_from_cart.qilPersonal updated_wc_div.qilPersonal updated_cart_totals.qilPersonal',()=>schedule(true,350))
      .on('qimia_currency_changed.qilPersonal',()=>{hide();lastKey='';schedule(true,450);});
  }
  document.body.addEventListener('wc-blocks_added_to_cart',()=>schedule(true,400));
  document.body.addEventListener('wc-blocks_removed_from_cart',()=>schedule(true,400));
  window.addEventListener('pagehide',()=>{void flush(true);stopped=true;reset();});
  window.addEventListener('pageshow',event=>{if(event.persisted){stopped=false;reset();ready=true;schedule(true,80);}});
  document.addEventListener('visibilitychange',()=>{
    if(document.hidden){visibleAt=Date.now();dwell.forEach(clearTimeout);dwell.clear();return;}
    // A scheduled context/currency refresh can be deferred while this tab is
    // hidden. Resume that one pending read even after a short background stay.
    if(dirty || forced || !payload || Date.now()-visibleAt>60000)schedule(true,150);else{disconnectCards();observeVisible();}
  });
  window.addEventListener('online',()=>{if(errors)schedule(true);if(queue.length)void flush(false);});
  window.QILPersonalization=Object.freeze({version:1,originFor:id=>pendingOrigins.get(Number(id))||'',refresh:()=>schedule(true),
    diagnostics:()=>({surface,loaded:!!payload,inFlight:!!inFlight,queued:queue.length,requests:requestCount,errors,server:payload?.diagnostics||null})});
  moveBelowRelated();
  if('IntersectionObserver'in window){
    entryObserver=new IntersectionObserver(entries=>{
      if(entries.some(e=>e.isIntersecting)){ready=true;entryObserver.disconnect();schedule(true,80);}
    },{rootMargin:'800px 0px'});anchors.forEach(a=>entryObserver.observe(a));
  }else{ready=true;schedule(true,120);}
})();
