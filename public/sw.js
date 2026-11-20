// Service worker de Traza FI: página «Sin conexión» y notificaciones push. No guarda datos personales en caché.
const CACHE = 'trazafi-v3';
const OFFLINE = '/offline.html';
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE, '/assets/img/icono-192.png'])).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
// Solo las navegaciones: si no hay internet se muestra la página «Sin conexión»; todo lo demás va directo a la red.
self.addEventListener('fetch', (e) => {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE)));
});
self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) { d = {cuerpo: e.data ? e.data.text() : ''}; }
  // Punto en el ícono de la app instalada; la página pone el número exacto al abrirse.
  try { if ('setAppBadge' in self.navigator) self.navigator.setAppBadge().catch(() => {}); } catch (x) {}
  e.waitUntil(self.registration.showNotification(d.titulo || 'Traza FI', {
    body: d.cuerpo || '', icon: '/assets/img/icono-192.png', badge: '/assets/img/icono-insignia.png',
    data: {url: d.url || '/'}, tag: d.url || undefined, renotify: true,
    // Sonido y vibración del sistema; la notificación permanece hasta que la persona la toque o la descarte.
    silent: false, vibrate: [200, 100, 200, 100, 300], requireInteraction: true,
  }));
});
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = new URL((e.notification.data && e.notification.data.url) || '/', self.location.origin).href;
  e.waitUntil(clients.matchAll({type: 'window', includeUncontrolled: true}).then((ws) => {
    for (const w of ws) { if (w.url.startsWith(self.location.origin) && 'focus' in w) { w.navigate(url); return w.focus(); } }
    return clients.openWindow(url);
  }));
});
