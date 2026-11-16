/* App behaviour. No build step, no framework. Every feature checks for its markup first. */
(function () {
  'use strict';
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };
  var APP = window.APP || {};
  var reduced = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }

  function post(url, data) {
    var fd = new FormData();
    Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
    fd.append('_csrf', APP.csrf);
    return fetch(url, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.json().then(function (j) { j._status = r.status; return j; }); });
  }

  var toastEl = $('[data-toast]'), toastT;
  function toast(msg) {
    if (!toastEl) return;
    toastEl.textContent = msg;
    toastEl.classList.add('show');
    clearTimeout(toastT);
    toastT = setTimeout(function () { toastEl.classList.remove('show'); }, 2600);
  }

  /* ---------- mood: auto → night → day ---------- */
  $$('[data-mood-toggle]').forEach(function (b) {
    b.addEventListener('click', function () {
      var root = document.documentElement;
      var cur = root.getAttribute('data-mood');
      var dark = cur ? cur === 'night' : matchMedia('(prefers-color-scheme: dark)').matches;
      var next = dark ? 'day' : 'night';
      root.setAttribute('data-mood', next);
      store('mood', next);
      toast(next === 'day' ? 'Εμφάνιση: Ημέρα' : 'Εμφάνιση: Νύχτα');
    });
  });

  /* ---------- cart drawer ---------- */
  var drawer = $('#cart-drawer'), scrim = $('[data-scrim]');
  function money(s) { return s; }
  function renderCart(j) {
    $$('[data-cart-count]').forEach(function (el) { el.textContent = j.count; });
    if (!drawer) return;
    var ul = $('[data-cart-lines]', drawer);
    ul.innerHTML = '';
    if (!j.items.length) { ul.innerHTML = '<li class="muted">Το καλάθι είναι άδειο.</li>'; }
    j.items.forEach(function (it) {
      var li = document.createElement('li');
      li.className = 'line';
      var th = document.createElement('div'); th.className = 'thumb';
      if (it.image) { var im = document.createElement('img'); im.src = it.image; im.alt = ''; im.width = 56; im.height = 56; th.appendChild(im); }
      var mid = document.createElement('div');
      var a = document.createElement('a'); a.href = it.url; a.style.textDecoration = 'none';
      var b = document.createElement('b'); b.textContent = it.title; a.appendChild(b);
      var sm = document.createElement('span'); sm.className = 'small muted'; sm.textContent = it.business + ' · ' + it.line;
      mid.appendChild(a); mid.appendChild(sm);
      var q = document.createElement('div'); q.className = 'qty';
      var minus = document.createElement('button'); minus.type = 'button'; minus.textContent = '−'; minus.setAttribute('aria-label', 'Λιγότερα');
      var n = document.createElement('span'); n.textContent = it.qty;
      var plus = document.createElement('button'); plus.type = 'button'; plus.textContent = '+'; plus.setAttribute('aria-label', 'Περισσότερα');
      if (it.qty >= it.max) plus.disabled = true;
      minus.onclick = function () { post('/kalathi/update', { key: it.key, qty: it.qty - 1 }).then(renderCart); };
      plus.onclick = function () { post('/kalathi/update', { key: it.key, qty: it.qty + 1 }).then(renderCart); };
      q.appendChild(minus); q.appendChild(n); q.appendChild(plus);
      li.appendChild(th); li.appendChild(mid); li.appendChild(q);
      ul.appendChild(li);
    });
    $('[data-cart-total]', drawer).textContent = j.total;
    var sv = $('[data-cart-save]', drawer);
    if (j.savings) { sv.hidden = false; sv.lastElementChild.textContent = j.savings; } else { sv.hidden = true; }
  }
  function openCart(open) {
    if (!drawer) return;
    drawer.classList.toggle('open', open);
    scrim.classList.toggle('open', open);
    drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
    if (open) {
      drawer.focus();
      fetch('/kalathi', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(renderCart);
    }
  }
  $$('[data-cart-open]').forEach(function (a) { a.addEventListener('click', function (e) { if (!drawer) return; e.preventDefault(); openCart(true); }); });
  $$('[data-cart-close]').forEach(function (b) { b.addEventListener('click', function () { openCart(false); }); });
  if (scrim) scrim.addEventListener('click', function () { openCart(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { openCart(false); var s = $('.pl-side.open'); if (s) s.classList.remove('open'); } });

  function bumpBag() {
    $$('.bag').forEach(function (b) { b.classList.remove('bump'); void b.offsetWidth; b.classList.add('bump'); });
  }
  function addToCart(id, variant, btn) {
    var data = { service_id: id, qty: 1 };
    if (variant) data.variant_id = variant;
    return post('/kalathi/add', data).then(function (j) {
      if (!j.ok) { toast(j.error || 'Δεν προστέθηκε.'); return j; }
      renderCart(j);
      bumpBag();
      toast(j.message || 'Προστέθηκε στο καλάθι.');
      if (btn) { btn.classList.add('done'); btn.textContent = '✓ Στο καλάθι'; }
      return j;
    }).catch(function () { toast('Δεν υπάρχει σύνδεση. Δοκίμασε ξανά.'); });
  }
  document.addEventListener('click', function (e) {
    var add = e.target.closest('[data-add]');
    if (add) { e.preventDefault(); addToCart(add.getAttribute('data-add'), null, add); return; }
    var fav = e.target.closest('[data-fav]');
    if (fav) {
      e.preventDefault();
      post('/agapimena/' + fav.getAttribute('data-fav'), {}).then(function (j) {
        if (j._status === 401) { toast('Συνδέσου για να κρατάς αγαπημένα.'); setTimeout(function () { location.href = '/eisodos'; }, 900); return; }
        fav.setAttribute('aria-pressed', j.on ? 'true' : 'false');
        fav.textContent = j.on ? '♥' : '♡';
        toast(j.on ? 'Προστέθηκε στα αγαπημένα.' : 'Αφαιρέθηκε από τα αγαπημένα.');
      });
    }
  });

  /* product page: variant price + ajax add + sticky buy bar */
  var addForm = $('[data-add-form]');
  if (addForm) {
    function fmt(c) { return (c % 100 === 0 ? (c / 100).toLocaleString('el-GR') : (c / 100).toLocaleString('el-GR', { minimumFractionDigits: 2 })) + ' €'; }
    $$('input[name="variant_id"]', addForm).forEach(function (r) {
      r.addEventListener('change', function () {
        var p = +r.getAttribute('data-price-cents'), g = +r.getAttribute('data-regular-cents');
        $('[data-price]', addForm).textContent = p ? fmt(p) : '0 €';
        var mini = $('[data-price-mini]'); if (mini) mini.textContent = p ? fmt(p) : '0 €';
        var s = $('[data-regular]', addForm); if (s) s.textContent = g > p ? fmt(g) : '';
        var pct = $('[data-pct]'); if (pct && g > p) pct.textContent = '−' + Math.round(100 - p / g * 100) + '%';
      });
    });
    addForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var v = $('input[name="variant_id"]:checked', addForm);
      addToCart(addForm.querySelector('[name=service_id]').value, v ? v.value : null).then(function (j) { if (j && j.ok) openCart(true); });
    });
    var sticky = $('[data-sticky-buy]'), buy = $('[data-buy]');
    if (sticky && buy && 'IntersectionObserver' in window) {
      new IntersectionObserver(function (en) { sticky.classList.toggle('show', !en[0].isIntersecting); }).observe(buy);
      $('[data-sticky-submit]', sticky).addEventListener('click', function () { addForm.requestSubmit ? addForm.requestSubmit() : addForm.submit(); });
    }
  }

  /* filters submit on change */
  $$('form[data-autosubmit] select').forEach(function (s) { s.addEventListener('change', function () { s.form.submit(); }); });

  /* ---------- QR codes (vendor/qrcode.js loaded on demand) ---------- */
  var qrs = $$('[data-qr]');
  function drawQrs() {
    qrs.forEach(function (box) {
      try {
        var qr = window.qrcode(0, 'M');
        qr.addData(box.getAttribute('data-qr'));
        qr.make();
        box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
      } catch (e) { box.textContent = ''; }
    });
  }
  if (qrs.length) {
    var sc = document.createElement('script');
    sc.src = '/assets/js/vendor/qrcode.js';
    sc.onload = drawQrs;
    document.head.appendChild(sc);
  }

  /* keep the screen awake while a code is shown at the counter */
  if ($('[data-wakelock]') && navigator.wakeLock) {
    var lock = function () { navigator.wakeLock.request('screen').catch(function () {}); };
    lock();
    document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'visible') lock(); });
  }

  /* ---------- hero: night city that lights up ---------- */
  var cv = document.getElementById('city');
  if (cv && cv.getContext) {
    var ctx = cv.getContext('2d'), W = cv.width, H = cv.height, range = document.getElementById('cityr');
    var seed = 11; function rnd() { seed = (seed * 1664525 + 1013904223) >>> 0; return seed / 4294967296; }
    var lit = ['#F2C14E', '#FF4F3A', '#2BD4A4', '#F7D774', '#FF8A5B'];
    var stars = []; for (var i = 0; i < 40; i++) stars.push([rnd() * W, rnd() * H * 0.35, rnd() * 1.4 + 0.4]);
    var bs = [], x = -10;
    while (x < W) { var w = 46 + rnd() * 60, h = 120 + rnd() * 260; bs.push({ x: x, w: w, h: h, c: lit[Math.floor(rnd() * lit.length)], t: rnd(), offer: rnd() < 0.35, win: [] }); x += w + 6; }
    bs.forEach(function (b) { for (var wy = H - b.h + 16; wy < H - 30; wy += 22) for (var wx = b.x + 8; wx < b.x + b.w - 12; wx += 14) b.win.push([wx, wy, rnd()]); });
    var mx = -999, my = -999;
    var rr = function (x, y, w, h, r) { ctx.beginPath(); ctx.moveTo(x + r, y); ctx.arcTo(x + w, y, x + w, y + h, r); ctx.arcTo(x + w, y + h, x, y + h, r); ctx.arcTo(x, y + h, x, y, r); ctx.arcTo(x, y, x + w, y, r); ctx.closePath(); };
    var draw = function () {
      var p = range.value / 100;
      var g = ctx.createLinearGradient(0, 0, 0, H); g.addColorStop(0, '#0E0716'); g.addColorStop(1, '#2A1238'); ctx.fillStyle = g; ctx.fillRect(0, 0, W, H);
      ctx.fillStyle = 'rgba(255,244,230,.7)'; stars.forEach(function (s) { ctx.beginPath(); ctx.arc(s[0], s[1], s[2], 0, 7); ctx.fill(); });
      ctx.fillStyle = '#F7D774'; ctx.beginPath(); ctx.arc(W - 90, 70, 26, 0, 7); ctx.fill(); ctx.fillStyle = '#0E0716'; ctx.beginPath(); ctx.arc(W - 80, 62, 24, 0, 7); ctx.fill();
      bs.forEach(function (b) {
        var on = b.t < p || (Math.abs(b.x + b.w / 2 - mx) < 70 && my > H - b.h - 30);
        ctx.fillStyle = on ? '#2E1A3E' : '#1C1027'; rr(b.x, H - b.h, b.w, b.h + 10, 8); ctx.fill();
        b.win.forEach(function (w) { var lw = on && w[2] < 0.75; ctx.fillStyle = lw ? b.c : 'rgba(255,244,230,.06)'; ctx.globalAlpha = lw ? 0.55 + w[2] * 0.45 : 1; ctx.fillRect(w[0], w[1], 7, 10); ctx.globalAlpha = 1; });
        if (on && b.offer) {
          var tx = b.x + b.w / 2, ty = H - b.h - 26;
          ctx.save(); ctx.shadowColor = b.c; ctx.shadowBlur = 18; ctx.fillStyle = b.c; rr(tx - 26, ty - 12, 52, 24, 12); ctx.fill(); ctx.restore();
          ctx.fillStyle = '#1E0F2A'; ctx.font = '800 12px "Noto Sans Display", sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText('ΠΡΟΣΦΟΡΑ', tx, ty + 1);
        }
      });
    };
    var queued = false, req = function () { if (queued) return; queued = true; requestAnimationFrame(function () { queued = false; draw(); }); };
    range.addEventListener('input', req);
    cv.addEventListener('pointermove', function (e) { var r = cv.getBoundingClientRect(); mx = (e.clientX - r.left) * W / r.width; my = (e.clientY - r.top) * H / r.height; req(); });
    cv.addEventListener('pointerleave', function () { mx = my = -999; req(); });
    draw();
    if (document.fonts && document.fonts.ready) document.fonts.ready.then(draw);
    // gentle one-time sweep on load to show the idea; skipped with reduced motion
    if (!reduced) {
      var t0 = null, from = 10, to = +range.value;
      range.value = from;
      var step = function (ts) { if (!t0) t0 = ts; var k = Math.min(1, (ts - t0) / 1600); range.value = from + (to - from) * (1 - Math.pow(1 - k, 3)); draw(); if (k < 1) requestAnimationFrame(step); };
      requestAnimationFrame(step);
    }
  }

  /* ---------- cookie consent + GA4 (loads only after "Αποδοχή") ---------- */
  var ck = $('[data-cookie]');
  function loadGA() {
    if (!APP.ga || window.__ga) return;
    window.__ga = true;
    var s = document.createElement('script'); s.async = true; s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(APP.ga);
    document.head.appendChild(s);
    window.dataLayer = window.dataLayer || [];
    window.gtag = function () { window.dataLayer.push(arguments); };
    window.gtag('js', new Date()); window.gtag('config', APP.ga, { anonymize_ip: true });
  }
  if (ck) {
    var choice = store('cookie_consent');
    if (choice === 'all') loadGA(); else if (!choice) ck.hidden = false;
    $('[data-cookie-accept]', ck).addEventListener('click', function () { store('cookie_consent', 'all'); ck.hidden = true; loadGA(); });
    $('[data-cookie-reject]', ck).addEventListener('click', function () { store('cookie_consent', 'necessary'); ck.hidden = true; });
    $$('[data-cookie-reopen]').forEach(function (b) { b.addEventListener('click', function () { ck.hidden = false; }); });
  }

  /* ---------- panels ---------- */
  var side = $('#pl-side');
  $$('[data-side-toggle]').forEach(function (b) { b.addEventListener('click', function () { side.classList.toggle('open'); }); });
  $$('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var src = $(b.getAttribute('data-copy'));
      var done = function () { toast('Αντιγράφηκε.'); };
      if (navigator.clipboard) navigator.clipboard.writeText(src.value).then(done, function () { src.select(); });
      else { src.select(); }
    });
  });
  var vbox = $('[data-variants]');
  if (vbox) {
    vbox.addEventListener('click', function (e) {
      if (e.target.closest('[data-variant-remove]')) {
        var row = e.target.closest('.vrow');
        if ($$('.vrow', vbox).length > 2) row.remove(); else $$('input', row).forEach(function (i) { i.value = ''; });
      }
      if (e.target.closest('[data-variant-add]')) {
        var rows = $$('.vrow', vbox), last = rows[rows.length - 1], clone = last.cloneNode(true);
        $$('input', clone).forEach(function (i) { i.value = ''; });
        last.after(clone);
      }
    });
  }

  /* ---------- redemption: camera scan (BarcodeDetector or jsQR) + live check ---------- */
  var scan = $('[data-scanner]');
  if (scan) {
    var input = $('#code'), form = $('[data-redeem-form]'), result = $('[data-result]');
    var show = function (j) {
      result.hidden = false;
      result.className = 'result ' + j.type;
      $('[data-r-title]', result).textContent = { ok: 'Εξαργυρώθηκε', err: 'Δεν εξαργυρώνεται', info: 'Έγκυρος κωδικός' }[j.type] || '';
      $('[data-r-msg]', result).textContent = j.message;
      $('[data-r-details]', result).textContent = j.service ? (j.service + ' · Πελάτης: ' + j.customer + ' · Λήξη: ' + j.expires) : '';
    };
    var check = function () {
      if (!input.value.trim()) { input.focus(); return; }
      post('/business/redeem/check', { code: input.value }).then(function (j) { input.value = j.code || input.value; show(j); });
    };
    $('[data-check]', scan).addEventListener('click', check);
    var params = new URLSearchParams(location.search);
    if (params.get('code') && !result.textContent.trim()) check();

    var camBox = $('[data-cam]', scan), video = camBox ? $('video', camBox) : null, startBtn = $('[data-cam-start]', scan), note = $('[data-cam-note]', scan);
    var stream = null, running = false, detector = null, canvas = document.createElement('canvas'), cctx = canvas.getContext('2d', { willReadFrequently: true });
    var stop = function () { running = false; if (stream) stream.getTracks().forEach(function (t) { t.stop(); }); stream = null; camBox.hidden = true; startBtn.textContent = 'Άνοιγμα κάμερας για σάρωση QR'; };
    var found = function (txt) {
      var m = /[?&]code=([A-Za-z0-9\-]+)/.exec(txt);
      input.value = (m ? m[1] : txt).toUpperCase();
      if (navigator.vibrate) navigator.vibrate(80);
      stop();
      check();
    };
    var tick = function () {
      if (!running) return;
      if (video.readyState >= 2) {
        if (detector) {
          detector.detect(video).then(function (codes) { if (codes.length) found(codes[0].rawValue); else requestAnimationFrame(tick); }).catch(function () { requestAnimationFrame(tick); });
          return;
        }
        if (window.jsQR) {
          var w = video.videoWidth, h = video.videoHeight, s = Math.min(1, 640 / w);
          canvas.width = w * s; canvas.height = h * s;
          cctx.drawImage(video, 0, 0, canvas.width, canvas.height);
          var img = cctx.getImageData(0, 0, canvas.width, canvas.height);
          var res = window.jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
          if (res && res.data) { found(res.data); return; }
        }
      }
      requestAnimationFrame(tick);
    };
    startBtn.addEventListener('click', function () {
      if (running) { stop(); return; }
      if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { note.textContent = 'Η συσκευή δεν υποστηρίζει κάμερα στον browser. Γράψε τον κωδικό στο πεδίο.'; return; }
      navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false }).then(function (s) {
        stream = s; video.srcObject = s; camBox.hidden = false; running = true; startBtn.textContent = 'Κλείσιμο κάμερας';
        video.play();
        if ('BarcodeDetector' in window) { try { detector = new window.BarcodeDetector({ formats: ['qr_code'] }); } catch (e) { detector = null; } }
        requestAnimationFrame(tick);
      }).catch(function () { note.textContent = 'Δεν δόθηκε άδεια για την κάμερα. Επίτρεψέ τη από τις ρυθμίσεις του browser ή γράψε τον κωδικό.'; });
    });
    form.addEventListener('submit', function () { stop(); });
  }

  /* ---------- PWA ---------- */
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost')) {
    window.addEventListener('load', function () { navigator.serviceWorker.register('/sw.js').catch(function () {}); });
  }
  // on logout, drop cached account pages so the next person on this device sees nothing
  $$('form[action="/exodos"]').forEach(function (f) {
    f.addEventListener('submit', function () {
      if (!window.caches) return;
      caches.keys().then(function (ks) { ks.forEach(function (k) { caches['delete'](k); }); });
    });
  });
})();
