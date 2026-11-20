// Página «Traza FI en tu teléfono»: paso 1, instalar la app (botón nativo o instrucciones según el dispositivo); paso 2, notificaciones push.
(() => {
  const ver = (id, si) => { const n = document.getElementById(id); if (n) n.hidden = !si; };
  const esIOS = /iphone|ipad|ipod/i.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  const esSafari = /safari/i.test(navigator.userAgent) && !/crios|fxios|edgios|chrome|android/i.test(navigator.userAgent);
  const yaApp = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  let aviso = window.__trazaInstalar || null;   // el evento puede llegar antes de que cargue este archivo (ver _pwa.php)
  if (yaApp) ver('inst-listo', true);
  else if (esIOS) ver(esSafari ? 'inst-ios' : 'inst-safari', true);
  else {
    // Chrome y Edge ofrecen la instalación con un evento; si no llega en unos segundos, se muestran las alternativas.
    const listo = () => { aviso = window.__trazaInstalar || aviso; if (aviso) { ver('inst-manual', false); ver('inst-boton', true); } };
    const t = setTimeout(() => { if (!aviso) ver('inst-manual', true); }, 3000);
    listo();
    document.addEventListener('traza-instalable', () => { clearTimeout(t); listo(); });
    window.addEventListener('appinstalled', () => { ver('inst-boton', false); ver('inst-manual', false); ver('inst-hecho', true); });
    const b = document.getElementById('btn-instalar');
    if (b) b.addEventListener('click', async () => { if (!aviso) return; aviso.prompt(); const r = await aviso.userChoice; aviso = null; if (r.outcome === 'accepted') { ver('inst-boton', false); ver('inst-hecho', true); } });
  }
})();
(() => {
  const cfg = window.trazaPush; if (!cfg) return;
  const estado = document.getElementById('push-estado'), on = document.getElementById('push-activar'), off = document.getElementById('push-desactivar');
  const decir = (t) => { estado.textContent = t; };
  const ios = /iphone|ipad|ipod/i.test(navigator.userAgent);
  const instalada = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
    decir(ios && !instalada ? 'En iPhone primero agrega Traza FI a tu pantalla de inicio (paso 1, arriba) y ábrela desde el icono; ahí podrás activar las notificaciones.'
                            : 'Este navegador no admite notificaciones. Usa Chrome (Android/computadora) o la app instalada en iPhone.');
    return;
  }
  const clave = (b64) => { const p = '='.repeat((4 - b64.length % 4) % 4); const r = atob((b64 + p).replace(/-/g, '+').replace(/_/g, '/')); return Uint8Array.from([...r].map(c => c.charCodeAt(0))); };
  const enviar = (url, datos) => { const f = new FormData(); f.append('_csrf', cfg.csrf); Object.entries(datos).forEach(([k, v]) => f.append(k, v)); return fetch(url, {method: 'POST', body: f, headers: {'Accept': 'application/json'}}); };
  const pintar = async () => {
    const reg = await navigator.serviceWorker.ready; const sub = await reg.pushManager.getSubscription();
    if (Notification.permission === 'denied') { decir('Las notificaciones están bloqueadas para Traza FI en este dispositivo. Permítelas en los ajustes del navegador o del teléfono y vuelve a esta página.'); on.hidden = off.hidden = true; return; }
    on.hidden = !!sub; off.hidden = !sub;
    decir(sub ? '✅ Este dispositivo recibe notificaciones de Traza FI.' : 'Este dispositivo aún no recibe notificaciones.');
  };
  on.addEventListener('click', async () => {
    on.disabled = true; decir('Pidiendo permiso…');
    try {
      const permiso = await Notification.requestPermission();
      if (permiso !== 'granted') { decir('No se dio el permiso. Puedes intentarlo de nuevo.'); return; }
      const reg = await navigator.serviceWorker.ready;
      const sub = await reg.pushManager.subscribe({userVisibleOnly: true, applicationServerKey: clave(cfg.clave)});
      const j = sub.toJSON();
      const r = await enviar(cfg.suscribir, {endpoint: j.endpoint, p256dh: j.keys.p256dh, auth: j.keys.auth, probar: '1'});
      if (!r.ok) throw new Error();
      await pintar(); decir('✅ Listo. Te enviamos una notificación de prueba.');
    } catch (e) { decir('No fue posible activar las notificaciones. Revisa tu conexión e inténtalo otra vez.'); }
    finally { on.disabled = false; }
  });
  off.addEventListener('click', async () => {
    const reg = await navigator.serviceWorker.ready; const sub = await reg.pushManager.getSubscription();
    if (sub) { await enviar(cfg.cancelar, {endpoint: sub.endpoint}); await sub.unsubscribe(); }
    pintar();
  });
  navigator.serviceWorker.register('/sw.js').then(pintar).catch(() => decir('No fue posible preparar las notificaciones en este navegador.'));
})();
