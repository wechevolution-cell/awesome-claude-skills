/* ANV — layered landscape painter for the hero (used until real photos are uploaded).
   "live": clouds drift, hills shift with the pointer; pauses off-screen; static with reduced motion.
   "still": painted once (and on resize). */
(() => {
  'use strict';
  const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;

  const rng = seed => { let s = seed >>> 0 || 1; return () => { s = (Math.imul(s, 1664525) + 1013904223) >>> 0; return s / 4294967296; }; };
  const ridge = (seed, amp, base) => {
    const r = rng(seed), c = [];
    for (let i = 0; i < 4; i++) c.push({ f: (i + 1) * (0.7 + r() * 0.9), p: r() * 6.283, a: 1 / (i + 1.3) });
    const n = c.reduce((s, x) => s + x.a, 0);
    return u => base + amp * c.reduce((s, x) => s + x.a * Math.sin(x.f * u * 6.283 + x.p), 0) / n;
  };
  const makeTrees = (seed, n) => { const r = rng(seed); return Array.from({ length: n }, () => ({ u: r() * 1.4 - 0.2, s: 0.7 + r() * 0.6, k: r() < 0.45 ? 'c' : 'r' })); };
  const makeClouds = (seed, n) => { const r = rng(seed); return Array.from({ length: n }, () => ({ u: r() * 1.4 - 0.2, v: 0.06 + r() * 0.3, w: 0.08 + r() * 0.14, parts: Array.from({ length: 5 }, () => [r() - 0.5, r() * 0.5, 0.35 + r() * 0.5]) })); };

  function tree(ctx, x, y, h, col, kind) {
    ctx.fillStyle = col;
    if (kind === 'c') { ctx.beginPath(); ctx.ellipse(x, y - h * 0.52, h * 0.13, h * 0.52, 0, 0, 6.283); ctx.fill(); return; }
    ctx.fillRect(x - h * 0.03, y - h * 0.4, h * 0.06, h * 0.4);
    ctx.beginPath(); ctx.arc(x, y - h * 0.62, h * 0.27, 0, 6.283); ctx.arc(x - h * 0.2, y - h * 0.47, h * 0.2, 0, 6.283); ctx.arc(x + h * 0.2, y - h * 0.5, h * 0.21, 0, 6.283); ctx.fill();
  }

  const L = (c, base, amp, depth, extra) => Object.assign({ c, base, amp, depth }, extra || {});
  function scene(seed, drop) {
    const D = drop || 0;
    return {
      seed,
      sky: ['#9cc3e6', '#f1f0ea'],
      sun: { x: 0.22 + (seed % 5) * 0.12, y: 0.28, r: 0.04, c: '#fff8e6', glow: 'rgba(255,240,210,.5)' },
      clouds: { n: 9, c: 'rgba(255,255,255,.82)' },
      layers: [
        L('#b5c6dc', 0.55 + D, 0.06, 0.04, { mist: 'rgba(241,240,234,.75)' }),
        L('#9fb7c9', 0.62 + D, 0.05, 0.08, { mist: 'rgba(241,240,234,.45)' }),
        L('#7fa48c', 0.70 + D, 0.05, 0.14, { trees: { n: 18, c: '#6a917a', h: 0.05 } }),
        L('#5f8a6c', 0.82 + D, 0.06, 0.22, { trees: { n: 8, c: '#4a7458', h: 0.12 } }),
        L('#45705a', 0.97 + D, 0.05, 0.34, { trees: { n: 3, c: '#365d48', h: 0.2 } })
      ]
    };
  }

  function paint(ctx, W, H, sc, st) {
    const g = ctx.createLinearGradient(0, 0, 0, H * 0.75);
    g.addColorStop(0, sc.sky[0]); g.addColorStop(1, sc.sky[1]);
    ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
    const s = sc.sun, sx = W * (s.x - st.shift * 0.02), sy = H * s.y, R = Math.min(W, H) * s.r;
    const rg = ctx.createRadialGradient(sx, sy, 0, sx, sy, R * 6);
    rg.addColorStop(0, s.glow); rg.addColorStop(1, 'rgba(255,255,255,0)');
    ctx.fillStyle = rg; ctx.fillRect(0, 0, W, H);
    ctx.fillStyle = s.c; ctx.beginPath(); ctx.arc(sx, sy, R, 0, 6.283); ctx.fill();

    const cs = sc._clouds || (sc._clouds = makeClouds(sc.seed + 7, sc.clouds.n));
    ctx.fillStyle = sc.clouds.c;
    for (const c of cs) {
      const u = ((c.u + st.drift * 0.0012 / c.w - st.shift * 0.05) % 1.4 + 1.4) % 1.4 - 0.2;
      const cx = u * W, cy = c.v * H, cw = c.w * W;
      for (const p of c.parts) { ctx.beginPath(); ctx.ellipse(cx + p[0] * cw, cy + p[1] * cw * 0.25, cw * p[2] * 0.5, cw * p[2] * 0.2, 0, 0, 6.283); ctx.fill(); }
    }
    sc.layers.forEach((Ly, i) => {
      const f = Ly._f || (Ly._f = ridge(sc.seed + i * 31, Ly.amp, Ly.base)), off = st.shift * Ly.depth;
      ctx.fillStyle = Ly.c; ctx.beginPath(); ctx.moveTo(0, H);
      for (let x = 0; x <= W + 6; x += 6) ctx.lineTo(x, H * f(x / W + off));
      ctx.lineTo(W, H); ctx.closePath(); ctx.fill();
      if (Ly.mist) {
        const y = H * Ly.base, mg = ctx.createLinearGradient(0, y - H * 0.08, 0, y + H * 0.06);
        mg.addColorStop(0, 'rgba(255,255,255,0)'); mg.addColorStop(1, Ly.mist);
        ctx.fillStyle = mg; ctx.fillRect(0, y - H * 0.08, W, H * 0.14);
      }
      if (Ly.trees) {
        const ts = Ly._t || (Ly._t = makeTrees(sc.seed + i * 97, Ly.trees.n));
        for (const t of ts) {
          const u = ((t.u - off) % 1.4 + 1.4) % 1.4 - 0.2;
          tree(ctx, u * W, H * f(u + off) + 2, H * Ly.trees.h * t.s, Ly.trees.c, t.k);
        }
      }
    });
  }

  function init(cv) {
    const live = cv.getAttribute('data-horizon') === 'live' && !reduce;
    const sc = scene(parseInt(cv.getAttribute('data-seed'), 10) || 41, cv.getAttribute('data-horizon') === 'live' ? 0.06 : 0.14);
    const host = cv.parentElement;
    const st = { shift: 0, drift: 0, target: 0 };
    let ctx = null, W = 0, H = 0, raf = 0, last = 0, visible = true;

    const fit = () => {
      const r = cv.getBoundingClientRect();
      const dpr = Math.min(2, window.devicePixelRatio || 1);
      W = Math.max(1, Math.round(r.width * dpr)); H = Math.max(1, Math.round(r.height * dpr));
      if (cv.width !== W || cv.height !== H) { cv.width = W; cv.height = H; }
      ctx = cv.getContext('2d');
    };
    const draw = () => { if (ctx) paint(ctx, W, H, sc, st); };
    const loop = ts => {
      const dt = last ? Math.min(50, ts - last) : 16; last = ts;
      st.drift += dt / 16; st.shift += (st.target - st.shift) * 0.06;
      draw(); raf = requestAnimationFrame(loop);
    };
    const run = () => {
      const should = live && visible && !document.hidden;
      if (should && !raf) { last = 0; raf = requestAnimationFrame(loop); }
      if (!should && raf) { cancelAnimationFrame(raf); raf = 0; }
    };

    fit(); draw();
    let rz;
    new ResizeObserver(() => { clearTimeout(rz); rz = setTimeout(() => { fit(); draw(); }, 60); }).observe(cv);
    if (!live) return;
    host.addEventListener('pointermove', e => {
      if (e.pointerType !== 'mouse') return;
      const r = host.getBoundingClientRect();
      st.target = ((e.clientX - r.left) / r.width - 0.5) * 0.5;
    });
    host.addEventListener('pointerleave', () => { st.target = 0; });
    new IntersectionObserver(es => { visible = es[0].isIntersecting; run(); }).observe(cv);
    document.addEventListener('visibilitychange', run);
    run();
  }

  document.querySelectorAll('canvas[data-horizon]').forEach(init);
})();
