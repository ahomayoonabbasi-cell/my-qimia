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
    const orb = grid.querySelector('[data-qil-hero-orb]');
    const avatar = visual.querySelector('[data-qil-hero-avatar]');
    const rail = visual.querySelector('.qil-hero-weekly');
    // On a short landscape viewport, keep the form after the complete rail
    // without resizing the couple or the product cards.
    const tail = window.matchMedia('(max-width:760px)').matches ? 20 : 24;
    setPx(grid, mobileProps[3], Math.max(tail, rail ? rail.offsetTop + rail.offsetHeight - visual.offsetHeight + tail : tail));
    if (orb && avatar) {
      // offset geometry ignores the existing float animations. object-fit:contain
      // with left/bottom alignment leaves unused height INSIDE the image element;
      // anchor above the painted picture, not the top of that empty rectangle.
      const nw = avatar.naturalWidth || Number(avatar.getAttribute('width'));
      const nh = avatar.naturalHeight || Number(avatar.getAttribute('height'));
      const size = orb.offsetWidth;
      if (nw > 0 && nh > 0 && size > 0 && avatar.offsetWidth > 0 && avatar.offsetHeight > 0) {
        const scale = Math.min(avatar.offsetWidth / nw, avatar.offsetHeight / nh);
        const paintedWidth = nw * scale, paintedHeight = nh * scale;
        const paintedTop = avatar.offsetTop + avatar.offsetHeight - paintedHeight;
        const clearance = 34; // Includes the existing 8px scene / 9px avatar float.
        const room = Math.max(0, Math.ceil(size + clearance + 12 - paintedTop));
        const top = room + paintedTop - size - clearance;
        const wantedLeft = avatar.offsetLeft + paintedWidth / 2 - size / 2;
        // The product rail stays on the physical right in both languages.
        const railLimit = rail && rail.offsetWidth ? rail.offsetLeft - size - 24 : grid.clientWidth - size - 8;
        const maxLeft = Math.max(8, Math.min(grid.clientWidth - size - 8, railLimit));
        const left = Math.max(8, Math.min(wantedLeft, maxLeft));
        setPx(grid, mobileProps[0], room);
        setPx(grid, mobileProps[1], left);
        setPx(grid, mobileProps[2], top);
      }
    } else {
      mobileProps.slice(0, 3).forEach(name => grid.style.removeProperty(name));
    }
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
