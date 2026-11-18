<?php // App instalable (PWA): manifiesto, iconos y registro del service worker (página «Sin conexión» y notificaciones push). ?>
  <link rel="manifest" href="<?= url('manifest.webmanifest') ?>">
  <meta name="theme-color" content="#1f4d36">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= url('assets/img/icono-192.png') ?>">
  <link rel="apple-touch-icon" href="<?= url('assets/img/apple-touch-icon.png') ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-title" content="Traza FI">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <script>if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {})); }</script>
