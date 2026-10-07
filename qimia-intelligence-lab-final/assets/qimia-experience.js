/* Storefront hero geometry only. No service calls or automatic image analysis. */
(() => {
  'use strict';
  const hero = document.querySelector('[data-qil-portal]');
  let frame = 0;
  const mobileProps = ['--qil-mobile-orb-room', '--qil-mobile-orb-left', '--qil-mobile-orb-top', '--qil-mobile-scene-tail'];
  function setPx(node, name, value) {
    const next = Math.round(value) + 'px';
    if (node.style.getPropertyValue(name) !== next) node.style.setProperty(name, next);
  }
  function layoutHero() {
    frame = 0; if (!hero) return;
    const grid = hero.querySelector('.qil-hero-grid');
    const title = hero.querySelector('#qil-portal-title'), visual = hero.querySelector('.qil-hero-visual');
    if (!grid || !title || !visual) return;
    if (!window.matchMedia('(max-width:980px)').matches) {
      mobileProps.forEach(name => grid.style.removeProperty(name));
      return;
    }
    // Mobile scene and its three-card shelf now use normal flow in CSS.
    // Remove obsolete offsets instead of reserving room for a vertical rail.
    mobileProps.forEach(name => grid.style.removeProperty(name));
    const h = hero.getBoundingClientRect(), t = title.getBoundingClientRect(), v = visual.getBoundingClientRect();
    // Art begins at the headline; the gradient remains light behind actual copy.
    const top = Math.max(0, Math.round(t.top - h.top - 36));
    hero.style.setProperty('--qil-art-top', top + 'px');
    hero.style.setProperty('--qil-art-height', Math.max(420, Math.round(v.bottom - h.top - top + 80)) + 'px');
  }
  const schedule = () => { if (!frame) frame = requestAnimationFrame(layoutHero); };
  if (hero) {
    schedule(); window.addEventListener('resize', schedule, { passive: true });
    document.fonts?.ready.then(schedule);
    hero.querySelectorAll('[data-qil-hero-avatar],.qil-hero-orb-mark').forEach(img => img.addEventListener('load', schedule, { passive: true }));
    if ('ResizeObserver' in window) {
      const observer = new ResizeObserver(schedule);
      hero.querySelectorAll('#qil-portal-title,.qil-hero-lead,.qil-myqimia-hero-action,.qil-hero-visual,[data-qil-hero-avatar],.qil-hero-weekly').forEach(n => observer.observe(n));
    }
  }
})();
