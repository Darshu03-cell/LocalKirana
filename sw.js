// Service worker for the installable Local Kirana app.
// Pages always come fresh from the server (carts, orders and payments must never be stale);
// only the app's own icons and the offline page are cached, so the app opens a friendly
// "You're offline" screen instead of a browser error when there is no internet.
var CACHE = 'kirana-app-v1';
var OFFLINE_URL = 'offline.html';
var PRECACHE = [OFFLINE_URL, 'icons/icon-192.png', 'icons/icon-512.png'];

self.addEventListener('install', function (event) {
  event.waitUntil(caches.open(CACHE).then(function (cache) { return cache.addAll(PRECACHE); }));
  self.skipWaiting();
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys().then(function (keys) {
      return Promise.all(keys.filter(function (k) { return k !== CACHE; }).map(function (k) { return caches.delete(k); }));
    }).then(function () { return self.clients.claim(); })
  );
});

self.addEventListener('fetch', function (event) {
  var req = event.request;
  // Leave form posts, payments (Razorpay) and other sites' files to the browser.
  if (req.method !== 'GET' || new URL(req.url).origin !== self.location.origin) return;

  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(function () { return caches.match(OFFLINE_URL); }));
    return;
  }

  if (PRECACHE.some(function (p) { return req.url.endsWith(p); })) {
    event.respondWith(caches.match(req).then(function (hit) { return hit || fetch(req); }));
  }
});
