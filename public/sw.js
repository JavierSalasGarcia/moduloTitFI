// Service worker de Traza FI: página «Sin conexión» y notificaciones push. No guarda datos personales en caché.
const CACHE = 'trazafi-v4';
// Contador de la insignia del ícono (iOS y Android solo muestran número si lo recibe setAppBadge). La página lo fija exacto al abrirse;
// mientras la app está cerrada, cada push lo aumenta en uno y el service worker lo conserva aquí.
const BADGE = 'trazafi-badge';
async function leerBadge() {
  try { const c = await caches.open(BADGE); const r = await c.match('/__badge'); return r ? (parseInt(await r.text(), 10) || 0) : 0; } catch (x) { return 0; }
}
async function guardarBadge(n) {
  try { const c = await caches.open(BADGE); await c.put('/__badge', new Response(String(n))); } catch (x) {}
}
const OFFLINE = '/offline.html';
self.addEventListener('install', (e) => {
  e.waitUntil(caches.open(CACHE).then((c) => c.addAll([OFFLINE, '/assets/img/icono-192.png'])).then(() => self.skipWaiting()));
});
self.addEventListener('activate', (e) => {
  e.waitUntil(caches.keys().then((ks) => Promise.all(ks.filter((k) => k !== CACHE && k !== BADGE).map((k) => caches.delete(k)))).then(() => self.clients.claim()));
});
// Solo las navegaciones: si no hay internet se muestra la página «Sin conexión»; todo lo demás va directo a la red.
self.addEventListener('fetch', (e) => {
  if (e.request.mode !== 'navigate') return;
  e.respondWith(fetch(e.request).catch(() => caches.match(OFFLINE)));
});
self.addEventListener('push', (e) => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (x) { d = {cuerpo: e.data ? e.data.text() : ''}; }
  e.waitUntil((async () => {
    // Número en el ícono de la app instalada: el que mande el servidor (d.badge) o el anterior + 1.
    const n = Number.isFinite(d.badge) ? Math.max(0, Math.floor(d.badge)) : (await leerBadge()) + 1;
    await guardarBadge(n);
    try { if ('setAppBadge' in self.navigator) await self.navigator.setAppBadge(n); } catch (x) {}
    await self.registration.showNotification(d.titulo || 'Traza FI', {
      body: d.cuerpo || '', icon: '/assets/img/icono-192.png', badge: '/assets/img/icono-insignia.png',
      data: {url: d.url || '/'}, tag: d.url || undefined, renotify: true,
      // Sonido y vibración del sistema; la notificación permanece hasta que la persona la toque o la descarte.
      silent: false, vibrate: [200, 100, 200, 100, 300], requireInteraction: true,
    });
  })());
});
self.addEventListener('notificationclick', (e) => {
  e.notification.close();
  const url = new URL((e.notification.data && e.notification.data.url) || '/', self.location.origin).href;
  e.waitUntil(clients.matchAll({type: 'window', includeUncontrolled: true}).then((ws) => {
    for (const w of ws) { if (w.url.startsWith(self.location.origin) && 'focus' in w) { w.navigate(url); return w.focus(); } }
    return clients.openWindow(url);
  }));
});
