(() => {
 'use strict';
 const config = window.QIMIA_LAB || {};
 if (!config.productId || !document.body.classList.contains('qil-product-experience')) return;
 const ar = config.locale === 'ar';
 const product = (config.products || []).find(item => Number(item.id) === Number(config.productId));
 if (!product) return;
 const query = selector => document.querySelector(selector);
 const all = selector => [...document.querySelectorAll(selector)];
 const nativeForm = () => {
  const forms = [...document.querySelectorAll('form.cart')];
  return forms.find(form => {
   const id = form.dataset.product_id || form.querySelector('[name="product_id"]')?.value || form.querySelector('[name="add-to-cart"]')?.value;
   return Number(id) === Number(config.productId);
  }) || forms.find(form => form.closest('.summary,.single-product-page,.wd-single-add-cart,.product')) || null;
 };
 const reduced = () => matchMedia('(prefers-reduced-motion: reduce)').matches;
 function goToPurchase() {
  const form = nativeForm();
  const target = form || query('[data-qil-variation-panel],.product_title,.wd-single-title');
  target?.scrollIntoView({behavior:reduced() ? 'auto' : 'smooth',block:'center'});
  const control = form?.querySelector('select:not([disabled]),input.qty,.single_add_to_cart_button');
  if (control) { try {control.focus({preventScroll:true});} catch (_) {control.focus();} }
 }
 // Place once for custom templates; do not observe the entire document on scroll.
 const hub = document.getElementById('qil-product-world');
 function originalSource(key) {
  const selector=key==='facts'?'.qimia-sf-classic':'.qimia-ai-box-wrap';
  const nodes=[...document.querySelectorAll(selector)];
  return nodes.find(node=>!hub?.contains(node))||nodes[0]||null;
 }
 function mountSavedKnowledge() {
  if(!hub)return;
  for(const key of ['box','facts']) {
   const template=hub.querySelector(`[data-qil-source-fallback="${key}"]`),slot=hub.querySelector(`[data-qil-source-mount="${key}"]`);
   if(template && slot && !originalSource(key)) slot.append(template.content.cloneNode(true));
   template?.remove();
  }
  if(hub.dataset.qilPlacement!=='server') {
   const boundary=[...document.querySelectorAll('.wd-single-meta,.product_meta,.wd-single-tabs,.woocommerce-tabs,.wd-single-content')].find(node=>!hub.contains(node)&&!node.closest('.summary,.summary-inner,.wd-single-summary'));
   const form=nativeForm();
   if(boundary && form && (form.compareDocumentPosition(boundary)&Node.DOCUMENT_POSITION_FOLLOWING)) {
    let target=boundary;
    while(target.parentElement && !target.parentElement.contains(form) && !target.parentElement.contains(hub) && !target.parentElement.matches('main,.site-content,.single-product-page,.product'))target=target.parentElement;
    if(target.parentElement && !target.contains(hub)){target.before(hub);hub.dataset.qilPlacement='client';}
   }
  }
  // When the theme supplies its own related rail, reuse it rather than show two.
  const nativeRelated=[...document.querySelectorAll('.related.products,.wd-single-related-products')].find(node=>!hub.contains(node));
  const description=[...document.querySelectorAll('.woocommerce-tabs,.wd-single-tabs,.wd-single-content')].find(node=>!hub.contains(node));
  if(nativeRelated && description && !nativeRelated.contains(description) && !description.contains(nativeRelated)){
   hub.querySelector('.qil-pdp-related')?.remove();
   let rail=nativeRelated;
   // Keep the owning Elementor widget together with its styles and navigation.
   const widget=nativeRelated.closest('.elementor-widget');
   if(widget && !widget.contains(description))rail=widget;
   if(!(rail.compareDocumentPosition(description)&Node.DOCUMENT_POSITION_FOLLOWING))description.before(rail);
  }
 }
 function setBoxLanguage(wrap,lang) {
  if (!wrap.querySelector(`.qimia-ai-lang-switch button[data-lang="${lang}"]`)) return;
  wrap.dataset.lang=lang;wrap.dir=lang==='ar'?'rtl':'ltr';
  wrap.querySelectorAll('[data-qimia-lang]').forEach(node=>node.classList.toggle('qimia-hidden',node.dataset.qimiaLang!==lang));
  wrap.querySelectorAll('.qimia-ai-lang-switch button').forEach(button=>{const active=button.dataset.lang===lang;button.classList.toggle('active',active);button.setAttribute('aria-pressed',String(active));});
 }
 function openSavedItem(wrap,item) {
  wrap.querySelectorAll('.qimia-ai-acc-item').forEach(node=>{const open=node===item;node.classList.toggle('open',open);node.querySelector('.qimia-ai-acc-trigger')?.setAttribute('aria-expanded',String(open));});
 }
 function findSavedItem(key) {
  const wrap=originalSource('box');
  const items=[...(wrap?.querySelectorAll('.qimia-ai-acc-item')||[])];
  if (key==='overview') return items[0] || null;
  const pattern=key==='usage'?/^(serving|directions|how to use|طريقة الاستخدام)/i:/^(faq|common questions|frequently asked|الأسئلة الشائعة)/i;
  return items.find(item=>[...item.querySelectorAll('.qimia-ai-acc-trigger [data-qimia-lang]')].some(label=>pattern.test(label.textContent.trim()))) || null;
 }
 function setupSavedKnowledge() {
  if (!hub) return;
  [originalSource('box')].filter(Boolean).forEach(wrap=>{
   setBoxLanguage(wrap,ar?'ar':'en');
   wrap.querySelectorAll('.qimia-ai-acc-item').forEach((item,index)=>{
    const trigger=item.querySelector('.qimia-ai-acc-trigger'), panel=item.querySelector('.qimia-ai-acc-panel');
    if (!trigger || !panel) return;
    if(!panel.id) panel.id=`qil-saved-section-${config.productId}-${index}`;
    trigger.setAttribute('aria-controls',panel.id);
   });
   if (wrap.dataset.qimiaInit === '1') return;
   // Native Product Box uses this exact init marker and skips duplicate bindings.
   wrap.dataset.qimiaInit='1';
   wrap.querySelectorAll('.qimia-ai-acc-trigger').forEach(button=>button.addEventListener('click',()=>{const item=button.closest('.qimia-ai-acc-item');openSavedItem(wrap,item?.classList.contains('open')?null:item);}));
   wrap.querySelectorAll('.qimia-ai-lang-switch button').forEach(button=>button.addEventListener('click',()=>setBoxLanguage(wrap,button.dataset.lang)));
  });
  ['usage','faq'].forEach(key=>{const button=hub.querySelector(`[data-qil-local-panel="${key}"]`);if(button && !findSavedItem(key)) button.hidden=true;});
 }
 mountSavedKnowledge();setupSavedKnowledge();
 document.addEventListener('click',event=>{
  const button=event.target instanceof Element?event.target.closest('[data-qil-local-panel]'):null;
  if(!button || !hub?.contains(button))return;
  event.preventDefault();
  const key=button.dataset.qilLocalPanel;
  const target=key==='facts'?originalSource('facts'):findSavedItem(key);
  if(!target)return;
  if(key!=='facts')openSavedItem(target.closest('.qimia-ai-box-wrap'),target);
  hub.querySelectorAll('[data-qil-local-panel]').forEach(node=>{if(node===button)node.setAttribute('aria-current','true');else node.removeAttribute('aria-current');});
  target.scrollIntoView({behavior:reduced()?'auto':'smooth',block:'start'});
  const focusTarget=target.querySelector('.qimia-ai-acc-trigger')||target;
  try{focusTarget.focus({preventScroll:true});}catch(_){focusTarget.focus();}
 });
 window.addEventListener('load',mountSavedKnowledge,{once:true});
 // price_html comes from WooCommerce, but retain only passive inline markup.
 // This preserves the installed currency classes without copying executable HTML.
 function safePrice(markup) {
  const template = document.createElement('template'); template.innerHTML = String(markup || '');
  template.content.querySelectorAll('script,style,iframe,object,embed,link,meta,svg,math,img').forEach(node => node.remove());
  template.content.querySelectorAll('*').forEach(node => {
   if (!['SPAN','B','STRONG','SMALL','DEL','INS','BDI','S'].includes(node.tagName)) { node.replaceWith(...node.childNodes); return; }
   [...node.attributes].forEach(attribute => { if (!['class','dir','aria-hidden','aria-label'].includes(attribute.name)) node.removeAttribute(attribute.name); });
  });
  return template.innerHTML;
 }
 // The compact bar shows the current sale price or the native range on one
 // line. Keep Woo's complete price description available to assistive tech.
 function renderBuybarPrice(markup) {
  const target=query('[data-qil-buybar-price]');
  if (!target) return;
  const safe=safePrice(markup);
  const source=document.createElement('template');source.innerHTML=safe;
  source.content.querySelectorAll('.screen-reader-text,del,s,small').forEach(node=>node.remove());
  const visual=document.createElement('span');visual.className='qil-buybar-price-visual';visual.setAttribute('aria-hidden','true');
  const amounts=[...source.content.querySelectorAll('.woocommerce-Price-amount,.amount')].filter(node=>!node.parentElement?.closest('.woocommerce-Price-amount,.amount'));
  if (amounts.length) {
   amounts.forEach((amount,index)=>{
    const copy=amount.cloneNode(true);
    if (index) {
     visual.append(document.createTextNode(' – '));
     const firstSymbol=amounts[0].querySelector('.woocommerce-Price-currencySymbol');
     const repeated=copy.querySelector('.woocommerce-Price-currencySymbol');
     if (firstSymbol && repeated && firstSymbol.textContent===repeated.textContent) repeated.remove();
    }
    visual.append(copy);
   });
  } else visual.textContent=source.content.textContent.replace(/\s+/g,' ').trim();
  const accessible=document.createElement('span');accessible.className='qil-buybar-price-a11y';accessible.innerHTML=safe;
  target.replaceChildren(visual,accessible);
 }
 const parentPrice = query('[data-qil-product-live-price]')?.innerHTML || '';
 const parentName = product.name || '';
 let selectedVariation = null;
 const isTrue = value => value === true || value === 1 || value === '1' || value === 'yes' || value === 'true';
 function plainText(markup) { const node=document.createElement('template');node.innerHTML=String(markup||'');return (node.content.textContent || '').replace(/\s+/g,' ').trim(); }
 function selectionLabel(form) { return [...(form?.querySelectorAll('select[name^="attribute_"]') || [])].map(select => select.value ? select.options[select.selectedIndex]?.text : '').filter(Boolean).join(' · '); }
 function setSelection(variation, form) {
  selectedVariation = variation && Number(variation.variation_id) > 0 ? variation : null;
  const name=query('[data-qil-selection-name]'), price=query('[data-qil-product-live-price]'), stock=query('[data-qil-product-live-stock]');
  const status=query('[data-qil-product-option-status]'), expiry=query('[data-qil-product-variation-expiry]');
  const bar=query('[data-qil-buybar-purchase]');
  if (name) name.textContent=parentName + (selectedVariation && selectionLabel(form) ? ` · ${selectionLabel(form)}` : '');
  if (price) {
   if (!selectedVariation) price.innerHTML=parentPrice;
   else if (selectedVariation.price_html) price.innerHTML=safePrice(selectedVariation.price_html);
   else {
    // Woo omits price_html when every variant shares one price. Wait for its
    // native renderer; otherwise retain the store's parent price, not FX math.
    const nativePrice=form?.querySelector('.woocommerce-variation-price .price');
    if (nativePrice?.innerHTML.trim()) price.innerHTML=safePrice(nativePrice.innerHTML);
    else price.innerHTML=parentPrice;
   }
  }
  const inStock=selectedVariation ? isTrue(selectedVariation.qil?.inventory?.inStock ?? selectedVariation.is_in_stock) : product.stock?.inStock === true;
  const purchasable=selectedVariation ? isTrue(selectedVariation.qil?.inventory?.purchasable ?? selectedVariation.is_purchasable) : product.purchase?.purchasable === true;
  if (stock) stock.textContent=inStock ? (ar ? 'متوفر' : 'In stock') : (ar ? 'غير متوفر' : 'Out of stock');
  if (status) status.textContent=selectedVariation ? (ar ? 'السعر والتوفر لهذا الخيار المحدد. تتم المراجعة عند الإضافة للسلة.' : 'Price and availability for this selected option. Rechecked when adding to bag.') : (product.type === 'variable' ? (ar ? 'اختر جميع الخيارات في نموذج المنتج.' : 'Choose every option in the product form.') : (ar ? 'يُعاد التحقق عند الإضافة للسلة.' : 'Rechecked when adding to bag.'));
  if (expiry) {
   const value=String(selectedVariation?.qil?.promotion?.expiry || '');
   expiry.hidden=!/^\d{4}-\d{2}-\d{2}$/.test(value);
   expiry.textContent=expiry.hidden ? '' : `${ar ? 'تاريخ الانتهاء المدرج' : 'Listed expiry'}: ${value}`;
  }
  if (bar) {
   bar.disabled=selectedVariation ? (!inStock || !purchasable) : product.stock?.inStock !== true || product.purchase?.purchasable !== true;
   bar.setAttribute('aria-disabled',String(bar.disabled));
   const label=bar.querySelector('span');
   if (label) label.textContent=bar.disabled ? (ar ? 'غير متوفر للشراء' : 'Unavailable') : (product.type === 'variable' && !selectedVariation ? (ar ? 'اختر الخيارات' : 'Choose options') : (ar ? 'أضف إلى السلة' : 'Add to bag'));
  }
  if (price) renderBuybarPrice(price.innerHTML);
 }
 document.addEventListener('click',event => {
  const target=event.target instanceof Element ? event.target : null;
  if (!target) return;
  if (target.closest('[data-qil-pdp-jump="purchase"]')) {event.preventDefault();goToPurchase();return;}
  const buy=target.closest('[data-qil-buybar-purchase]');
  if (!buy) return;
  event.preventDefault();
  if (buy.disabled) return;
  const form=nativeForm();
  const submit=form?.querySelector('.single_add_to_cart_button');
  const variationId=Number(form?.querySelector('[name="variation_id"]')?.value || 0);
  if (!form || !submit || submit.disabled || submit.classList.contains('disabled') || (product.type === 'variable' && !variationId)) {goToPurchase();return;}
  if (typeof form.reportValidity === 'function' && !form.reportValidity()) {goToPurchase();return;}
  // Exactly one native click. No custom endpoint, quantity overwrite or second cart.
  submit.click();
 });
 if (window.jQuery) {
  window.jQuery(document).on('found_variation.qilWorkspace','form.variations_form',function(event,variation) {
   if (this !== nativeForm()) return;
   const form=this; requestAnimationFrame(() => setSelection(variation,form));
  }).on('reset_data.qilWorkspace hide_variation.qilWorkspace','form.variations_form',function() {
   if (this === nativeForm()) setSelection(null,this);
  });
 }
 // A cached/default variation may already have resolved before this footer script.
 const form=nativeForm();
 const initialId=Number(form?.querySelector('[name="variation_id"]')?.value || 0);
 const initial=window.jQuery && form ? window.jQuery(form).data('product_variations') : null;
 setSelection(Array.isArray(initial) ? initial.find(variation => Number(variation.variation_id) === initialId) : null,form);
 // A native variation/currency renderer can finish after this script. Observe
 // only its displayed price; bar visibility never changes the page's height.
 const livePrice=query('[data-qil-product-live-price]');
 if (livePrice) new MutationObserver(()=>renderBuybarPrice(livePrice.innerHTML)).observe(livePrice,{childList:true,subtree:true,characterData:true});
})();
