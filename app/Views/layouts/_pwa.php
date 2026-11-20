<?php // App instalable (PWA): manifiesto, iconos y registro del service worker (página «Sin conexión» y notificaciones push). ?>
  <link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
  <meta name="theme-color" content="#1f4d36">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= url('assets/img/icono-192.png') ?>">
  <link rel="apple-touch-icon" href="<?= url('assets/img/apple-touch-icon.png') ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Traza FI">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <script>if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {})); }
  // Botón «Instalar Traza FI» de la guía: se guarda el aviso de instalación del navegador (Chrome/Edge) para usarlo con un toque.
  window.addEventListener('beforeinstallprompt', (e) => { if (location.pathname.startsWith('/notificaciones/app')) { e.preventDefault(); window.__trazaInstalar = e; document.dispatchEvent(new Event('traza-instalable')); } });
  // En la app instalada, si aún no se han activado las notificaciones, una franja discreta lleva al paso 2 (se puede cerrar).
  window.addEventListener('load', () => {
    try {
      const app = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
      if (!app || !('Notification' in window) || Notification.permission !== 'default' || localStorage.getItem('trazaAvisoPush') || location.pathname.startsWith('/notificaciones/app') || !document.body.dataset.user) return;
      const d = document.createElement('div');
      d.setAttribute('role', 'status');
      d.style.cssText = 'position:fixed;left:12px;right:12px;bottom:12px;z-index:9999;background:#1f4d36;color:#fff;border-radius:12px;padding:12px 14px;display:flex;gap:10px;align-items:center;box-shadow:0 6px 20px rgba(0,0,0,.25);font-size:14px';
      d.innerHTML = '<span style="flex:1">🔔 Activa las notificaciones para enterarte de avisos, exámenes y eventos.</span><a href="/notificaciones/app#activar-push" style="background:#fff;color:#1f4d36;border-radius:8px;padding:8px 12px;font-weight:700;text-decoration:none">Activar</a><button type="button" aria-label="Cerrar" style="background:none;border:0;color:#fff;font-size:20px;cursor:pointer">×</button>';
      d.querySelector('button').onclick = () => { localStorage.setItem('trazaAvisoPush', '1'); d.remove(); };
      document.body.appendChild(d);
    } catch (e) {}
  });</script>
