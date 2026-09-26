(() => {
 'use strict';
 const root=document.getElementById('qil-hero-studio'); if(!root)return;
 const cfg=window.QIL_HERO_STUDIO||{}, preview=root.querySelector('[data-qil-hero-preview-image]'), mode=root.querySelector('#qil-hero-preview-mode');
 const urls={desktop_id:Number(root.querySelector('#qil-hero-desktop_id').value)>0?preview.src:'',mobile_id:''};
 const input=key=>root.querySelector('#qil-hero-'+key);
 function render(){
  const mobile=mode.value==='mobile',prefix=mobile?'mobile_':'';
  root.querySelector('[data-qil-hero-preview]').dataset.mode=mode.value;
  for(const key of ['width','height','left','bottom']) preview.style[key]=input(prefix+key).value+'%';
  preview.src=mobile?(urls.mobile_id||urls.desktop_id||cfg.mobile.url):(urls.desktop_id||cfg.desktop.url);
 }
 root.addEventListener('input',e=>{
  const number=e.target.dataset.qilHeroNumber,range=e.target.dataset.qilHeroRange;
  if(number)root.querySelector('[data-qil-hero-range="'+number+'"]').value=e.target.value;
  if(range)input(range).value=e.target.value;
  render();
 });
 mode.addEventListener('change',render);
 root.addEventListener('click',e=>{
  const choose=e.target.closest('[data-qil-hero-media]'),clear=e.target.closest('[data-qil-hero-clear]');
  if(choose && window.wp?.media){
   const key=choose.dataset.qilHeroMedia,frame=wp.media({title:'Choose Qimia hero',button:{text:'Use this image'},library:{type:'image'},multiple:false});
   frame.on('select',()=>{const item=frame.state().get('selection').first().toJSON();input(key).value=item.id;urls[key]=item.url;root.querySelector('[data-qil-hero-name="'+key+'"]').textContent=item.filename||item.title||String(item.id);render();});frame.open();
  }
  if(clear){const key=clear.dataset.qilHeroClear;input(key).value='0';urls[key]='';root.querySelector('[data-qil-hero-name="'+key+'"]').textContent='Built-in / default';render();}
  if(e.target.closest('[data-qil-hero-reset]')){for(const [key,value] of Object.entries(cfg.defaults||{})){if(key.endsWith('_id'))continue;input(key).value=value;root.querySelector('[data-qil-hero-range="'+key+'"]').value=value;}render();}
 });
 // Read persisted mobile choice through the media model only when it is set.
 const mobileId=Number(input('mobile_id').value);
 if(mobileId && window.wp?.media){const item=wp.media.attachment(mobileId);item.fetch().then(()=>{urls.mobile_id=item.get('url');render();}).catch(()=>render());}
 render();
})();
