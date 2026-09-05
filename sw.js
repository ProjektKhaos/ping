'use strict';

const VERSION = '1.2.4';
const CACHE = `ping-flood-watch-shell-v${VERSION}`;
const SCOPE = new URL('./', self.location.href).pathname;
const SHELL = [
  `${SCOPE}offline.php`, `${SCOPE}manifest.webmanifest`,
  `${SCOPE}assets/css/app.css?layout=desktop-1&v=${VERSION}`, `${SCOPE}assets/js/theme-init.js?v=${VERSION}`,
  `${SCOPE}assets/js/app.js?v=${VERSION}`,
  `${SCOPE}assets/js/offline.js?v=${VERSION}`, `${SCOPE}assets/js/home.js?v=${VERSION}`,
  `${SCOPE}assets/js/stations.js?v=${VERSION}`, `${SCOPE}assets/js/station.js?v=${VERSION}`,
  `${SCOPE}assets/js/alerts.js?v=${VERSION}`, `${SCOPE}assets/js/push.js?v=${VERSION}`,
  `${SCOPE}assets/vendor/leaflet.css?v=${VERSION}`, `${SCOPE}assets/vendor/leaflet.js?v=${VERSION}`,
  `${SCOPE}assets/vendor/MarkerCluster.css?v=${VERSION}`, `${SCOPE}assets/vendor/MarkerCluster.Default.css?v=${VERSION}`,
  `${SCOPE}assets/vendor/leaflet.markercluster.js?v=${VERSION}`,
  `${SCOPE}assets/vendor/chart.umd.min.js?v=${VERSION}`, `${SCOPE}assets/icons/icon.svg`,
  `${SCOPE}assets/icons/icon-192.png`, `${SCOPE}assets/icons/icon-512.png`,
  `${SCOPE}assets/fonts/roboto-latin-wght-normal.woff2`, `${SCOPE}assets/fonts/noto-sans-thai-thai-wght-normal.woff2`,
  `${SCOPE}assets/fonts/material-symbols-rounded.woff2?v=icons-1`
];

self.addEventListener('install', event => event.waitUntil(
  caches.open(CACHE).then(cache => cache.addAll(SHELL)).then(() => self.skipWaiting())
));
self.addEventListener('activate', event => event.waitUntil(
  caches.keys().then(keys => Promise.all(keys.filter(key => key !== CACHE).map(key => caches.delete(key)))).then(() => self.clients.claim())
));
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  if (request.method !== 'GET' || url.origin !== self.location.origin || url.pathname.startsWith(`${SCOPE}api/`)) return;
  if (request.mode === 'navigate') {
    event.respondWith(fetch(request).catch(() => caches.match(`${SCOPE}offline.php`)));
    return;
  }
  if (url.pathname.startsWith(`${SCOPE}assets/`) || url.pathname === `${SCOPE}manifest.webmanifest`) {
    event.respondWith(caches.match(request).then(hit => hit || fetch(request)));
  }
});

self.addEventListener('push', event => {
  let payload = {};
  try { payload = event.data?.json() || {}; } catch (_) {}
  const title = typeof payload.title === 'string' && payload.title ? payload.title : 'Ping Flood Watch';
  const candidate = typeof payload.url === 'string' ? payload.url : `${SCOPE}alerts.php`;
  let target = `${SCOPE}alerts.php`;
  try {
    const parsed = new URL(candidate, self.location.origin);
    if (parsed.origin === self.location.origin && parsed.pathname.startsWith(SCOPE)) target = parsed.href;
  } catch (_) {}
  const timestamp = Date.parse(payload.timestamp);
  event.waitUntil(self.registration.showNotification(title, {
    body: typeof payload.body === 'string' ? payload.body : '',
    icon: `${SCOPE}assets/icons/icon-192.png`, badge: `${SCOPE}assets/icons/icon-192.png`,
    tag: typeof payload.tag === 'string' ? payload.tag.slice(0, 64) : 'pfw-alert', renotify: true,
    timestamp: Number.isFinite(timestamp) ? timestamp : Date.now(),
    data: {url: target, alertId: Number(payload.alert_id) || null}
  }));
});

self.addEventListener('notificationclick', event => {
  event.notification.close();
  let target = new URL(`${SCOPE}alerts.php`, self.location.origin);
  try {
    const candidate = new URL(event.notification.data?.url || target.href, self.location.origin);
    if (candidate.origin === self.location.origin && candidate.pathname.startsWith(SCOPE)) target = candidate;
  } catch (_) {}
  event.waitUntil(self.clients.matchAll({type: 'window', includeUncontrolled: true}).then(async clients => {
    for (const client of clients) {
      const current = new URL(client.url);
      if (current.origin === self.location.origin && current.pathname.startsWith(SCOPE)) {
        await client.navigate(target.href);
        return client.focus();
      }
    }
    return self.clients.openWindow(target.href);
  }));
});
