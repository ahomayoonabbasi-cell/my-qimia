/* One consented taxonomy view per account binding; no polling or new request. */
(()=>{'use strict';let sent='';document.addEventListener('qh:account-ready',e=>{const d=e.detail||{},v=window.QIL_BRAIN_VIEW;if(!d.personal||!d.binding||sent===d.binding||!v||!window.QimiaActivity)return;sent=d.binding;window.QimiaActivity.event(v.type,{surface:'search',term_id:Number(v.term_id||0)});});})();
