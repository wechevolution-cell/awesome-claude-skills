/* Service worker: offline shell + cache for static assets. Pages always come from the network first,
   so prices, codes and stock are never stale. Bump VERSION on each release. */
const VERSION = 'v1';
const SHELL = ['/offline', '/assets/css/app.css', '/assets/css/fonts.css', '/assets/js/app.js', '/assets/js/vendor/qrcode.js', '/icon-192.png'];

self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(VERSION).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((keys) => Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});

self.addEventListener('fetch', (e) => {
  const req = e.request;
  const url = new URL(req.url);
  if (req.method !== 'GET' || url.origin !== location.origin) return;
  if (url.pathname.startsWith('/admin') || url.pathname.startsWith('/business') || url.pathname.startsWith('/api/') || url.pathname.startsWith('/agent')) return;

  // static assets: cache first
  if (url.pathname.startsWith('/assets/') || url.pathname.startsWith('/uploads/') || /^\/icon-\d+\.png$/.test(url.pathname)) {
    e.respondWith(caches.match(req).then((hit) => hit || fetch(req).then((res) => {
      if (res.ok) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return res;
    })));
    return;
  }

  // pages: network first; keep a copy of "my codes" pages so a code can be shown with weak signal
  if (req.mode === 'navigate') {
    e.respondWith(fetch(req).then((res) => {
      if (res.ok && url.pathname.startsWith('/logariasmos/kodik')) { const copy = res.clone(); caches.open(VERSION).then((c) => c.put(req, copy)); }
      return res;
    }).catch(() => caches.match(req).then((hit) => hit || caches.match('/offline'))));
  }
});
