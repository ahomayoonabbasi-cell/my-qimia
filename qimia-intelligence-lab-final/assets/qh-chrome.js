/* Site navigation only. No membership requests, account data or header badges. */
(() => {
  'use strict';
  document.addEventListener('click', event => {
    document.querySelectorAll('[data-qil-pages][open]').forEach(menu => {
      if (!menu.contains(event.target) || event.target.closest('a,button')) menu.open = false;
    });
  });
  document.addEventListener('keydown', event => {
    if (event.key !== 'Escape') return;
    document.querySelectorAll('[data-qil-pages][open]').forEach(menu => {
      menu.open = false;
      menu.querySelector('summary')?.focus();
    });
  });


  // Some touch browsers do not enter :active until touchend. Start only the
  // existing CSS sheen on press; never prevent, delay or replace navigation.
  let pressedEntry = null, pressTimer = 0;
  const clearEntryPress = () => {
    if (pressedEntry) pressedEntry.classList.remove('qil-myqimia-pressed');
    pressedEntry = null;
    window.clearTimeout(pressTimer);
    pressTimer = 0;
  };
  document.addEventListener('pointerdown', event => {
    if (event.pointerType === 'mouse' || event.isPrimary === false) return;
    const link = event.target instanceof Element
      ? event.target.closest('a[data-qil-myqimia-cta]') : null;
    if (!link) return;
    clearEntryPress();
    pressedEntry = link;
    link.classList.add('qil-myqimia-pressed');
    pressTimer = window.setTimeout(clearEntryPress, 700);
  }, { passive: true });
  document.addEventListener('pointercancel', clearEntryPress, { passive: true });
  window.addEventListener('pagehide', clearEntryPress);

  // A restored workspace must still recheck its private plan state. No account
  // status is fetched or displayed by the header, and no wellness data is passed.
  window.addEventListener('pageshow', event => {
    if (event.persisted && document.getElementById('qimia-health')) {
      document.dispatchEvent(new CustomEvent('qh:membership:request'));
    }
  });
})();
