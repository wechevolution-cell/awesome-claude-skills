/* ANV — site behaviour. No dependencies. */
(() => {
  'use strict';
  const d = document;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ---------- mobile menu ---------- */
  const nav = d.getElementById('nav');
  const toggle = nav && nav.querySelector('.menu-toggle');
  if (toggle) {
    const close = () => { nav.classList.remove('open'); toggle.setAttribute('aria-expanded', 'false'); };
    toggle.addEventListener('click', () => {
      const open = nav.classList.toggle('open');
      toggle.setAttribute('aria-expanded', String(open));
      if (open) { const first = nav.querySelector('#nav-list a'); first && first.focus(); }
    });
    d.addEventListener('keydown', e => { if (e.key === 'Escape' && nav.classList.contains('open')) { close(); toggle.focus(); } });
    d.addEventListener('click', e => { if (nav.classList.contains('open') && !nav.contains(e.target)) close(); });
  }

  /* ---------- open now (Europe/Athens) ---------- */
  d.querySelectorAll('[data-hours]').forEach(panel => {
    const badge = panel.querySelector('[data-open-badge]');
    if (!badge) return;
    let hours;
    try { hours = JSON.parse(panel.getAttribute('data-hours')); } catch (e) { return; }
    const parts = new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Athens', weekday: 'short', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }).formatToParts(new Date());
    const get = t => (parts.find(p => p.type === t) || {}).value;
    const dow = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 }[get('weekday')];
    const now = get('hour') + ':' + get('minute');
    const slots = hours[dow] || [];
    const cur = slots.find(s => now >= s[0] && now < s[1]);
    badge.hidden = false;
    if (cur) { badge.className = 'badge badge-open'; badge.textContent = 'Ανοιχτά τώρα · έως ' + cur[1]; }
    else {
      const later = slots.find(s => now < s[0]);
      badge.className = 'badge badge-closed';
      badge.textContent = later ? 'Κλειστά τώρα · ανοίγει ' + later[0] : 'Κλειστά τώρα';
    }
    panel.querySelectorAll('.hours tr').forEach((tr, i) => tr.classList.toggle('today', i + 1 === dow));
  });

  /* ---------- cookie consent: GA4 + Maps only after acceptance ---------- */
  const COOKIE = 'anv_consent';
  const getConsent = () => (d.cookie.match(/(?:^|; )anv_consent=([^;]+)/) || [])[1] || '';
  const setConsent = v => {
    const secure = location.protocol === 'https:' ? '; Secure' : '';
    d.cookie = COOKIE + '=' + v + '; Max-Age=' + (60 * 60 * 24 * 182) + '; Path=/; SameSite=Lax' + secure;
  };
  const banner = d.getElementById('consent');
  const loadGA = () => {
    const id = d.body.getAttribute('data-ga');
    if (!id || window.__gaLoaded) return;
    window.__gaLoaded = true;
    const s = d.createElement('script');
    s.async = true;
    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
    d.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { dataLayer.push(arguments); };
    gtag('js', new Date());
    gtag('config', id, { anonymize_ip: true });
  };
  const loadMaps = () => d.querySelectorAll('[data-map]').forEach(loadMap);
  const apply = v => { if (v === 'all') { loadGA(); loadMaps(); } };
  const c = getConsent();
  if (!c && banner) banner.hidden = false; else apply(c);
  d.querySelectorAll('[data-consent]').forEach(b => b.addEventListener('click', () => {
    const v = b.getAttribute('data-consent');
    setConsent(v);
    banner.hidden = true;
    apply(v);
  }));
  d.querySelectorAll('[data-consent-open]').forEach(b => b.addEventListener('click', () => {
    banner.hidden = false;
    const f = banner.querySelector('button'); f && f.focus();
  }));

  function loadMap(el) {
    const q = el.getAttribute('data-map');
    if (!q || el.querySelector('iframe')) return;
    const f = d.createElement('iframe');
    f.src = 'https://www.google.com/maps?q=' + encodeURIComponent(q) + '&hl=el&z=16&output=embed';
    f.title = 'Χάρτης με τη θέση του γραφείου';
    f.loading = 'lazy';
    f.referrerPolicy = 'no-referrer-when-downgrade';
    el.appendChild(f);
    const ph = el.querySelector('.map-ph'); ph && ph.remove();
  }
  d.querySelectorAll('[data-map-load]').forEach(b => b.addEventListener('click', () => loadMap(b.closest('[data-map]'))));

  /* ---------- reveal on scroll (below-the-fold only, never hides what is already visible) ---------- */
  if (!reduce && 'IntersectionObserver' in window) {
    const els = [...d.querySelectorAll('.reveal')].filter(el => el.getBoundingClientRect().top > innerHeight);
    els.forEach(el => el.classList.add('pre'));
    const io = new IntersectionObserver(entries => entries.forEach(en => {
      if (en.isIntersecting) { en.target.classList.remove('pre'); io.unobserve(en.target); }
    }), { rootMargin: '0px 0px -8% 0px' });
    els.forEach(el => io.observe(el));
  }

  /* ---------- gentle parallax for photo heroes ---------- */
  const media = d.querySelector('[data-parallax] img');
  if (media && !reduce) {
    let ticking = false;
    const onScroll = () => {
      if (ticking) return; ticking = true;
      requestAnimationFrame(() => {
        const y = Math.min(scrollY, innerHeight);
        media.style.transform = 'scale(1.06) translate3d(0,' + (y * 0.12).toFixed(1) + 'px,0)';
        ticking = false;
      });
    };
    addEventListener('scroll', onScroll, { passive: true });
  }
})();
