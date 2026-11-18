<?php use App\Core\Auth; ?>
<p style="margin:0 0 10px"><a href="<?= e(url('perfil')) ?>">← Mi perfil</a></p>
<h1 style="font-size:22px;margin:0 0 6px">Traza FI en tu teléfono</h1>
<p class="texto-suave" style="margin:0 0 18px">Instala Traza FI como una app (sin tienda de aplicaciones) y activa las notificaciones para enterarte al momento de avisos, exámenes, convocatorias, eventos y de lo que llega a tu bandeja.</p>

<section class="card" id="activar-push" style="padding:20px;margin-bottom:16px">
  <h2 style="font-size:17px;margin:0 0 8px">🔔 Notificaciones en este dispositivo</h2>
  <p id="push-estado" class="texto-suave" style="margin:0 0 12px">Comprobando…</p>
  <div class="grupo-chips" style="gap:8px">
    <button type="button" class="btn btn-primario" id="push-activar" hidden>Activar notificaciones en este dispositivo</button>
    <button type="button" class="btn" id="push-desactivar" hidden>Desactivar en este dispositivo</button>
  </div>
  <?php if ($dispositivos > 0): ?><p class="texto-suave" style="margin:10px 0 0;font-size:13px">Tienes <?= (int) $dispositivos ?> dispositivo<?= $dispositivos > 1 ? 's' : '' ?> con notificaciones activas.</p><?php endif; ?>
</section>

<div class="guia-app">
  <section class="card" style="padding:20px">
    <h2 style="font-size:17px;margin:0 0 10px">🤖 Android (Chrome)</h2>
    <ol class="guia-pasos">
      <li>Abre <strong>fingenieria.mx</strong> en <strong>Chrome</strong> e inicia sesión.</li>
      <li>Toca el menú <strong>⋮</strong> (arriba a la derecha) y elige <strong>«Instalar aplicación»</strong> o <strong>«Agregar a la pantalla principal»</strong>. Confirma con <strong>Instalar</strong>.</li>
      <li>Abre <strong>Traza FI</strong> desde el icono que apareció en tu pantalla.</li>
      <li>Entra a <strong>Mi perfil → Traza FI en tu teléfono</strong> y toca <strong>«Activar notificaciones en este dispositivo»</strong>. Acepta el permiso.</li>
    </ol>
    <p class="texto-suave" style="font-size:13px;margin:8px 0 0">Si no ves la opción, actualiza Chrome. Si antes bloqueaste las notificaciones: mantén presionado el icono → <em>Información de la app</em> → <em>Notificaciones</em> → Permitir.</p>
  </section>
  <section class="card" style="padding:20px">
    <h2 style="font-size:17px;margin:0 0 10px">🍎 iPhone y iPad (Safari)</h2>
    <ol class="guia-pasos">
      <li>Abre <strong>fingenieria.mx</strong> en <strong>Safari</strong> (iOS 16.4 o posterior) e inicia sesión.</li>
      <li>Toca el botón <strong>Compartir</strong> <span aria-hidden="true">(el cuadro con flecha ↑)</span> y elige <strong>«Agregar a la pantalla de inicio»</strong>. Toca <strong>Agregar</strong>.</li>
      <li>Cierra Safari y abre <strong>Traza FI</strong> desde el icono nuevo. <strong>En iPhone las notificaciones solo funcionan desde el icono</strong>, no desde Safari.</li>
      <li>Entra a <strong>Mi perfil → Traza FI en tu teléfono</strong>, toca <strong>«Activar notificaciones en este dispositivo»</strong> y elige <strong>Permitir</strong>.</li>
    </ol>
    <p class="texto-suave" style="font-size:13px;margin:8px 0 0">Para revisarlas después: <em>Configuración → Notificaciones → Traza FI</em>.</p>
  </section>
  <section class="card" style="padding:20px">
    <h2 style="font-size:17px;margin:0 0 10px">💻 Computadora (Chrome o Edge)</h2>
    <ol class="guia-pasos">
      <li>Con la sesión iniciada, toca el icono de <strong>instalar</strong> en la barra de direcciones (o menú → <em>Instalar Traza FI</em>).</li>
      <li>Activa las notificaciones con el botón de arriba.</li>
    </ol>
  </section>
</div>
<style>
.guia-app{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px}
.guia-pasos{margin:0;padding-left:20px;line-height:1.6}
.guia-pasos li{margin-bottom:6px}
</style>
<script>
window.trazaPush = {clave: <?= json_encode($clavePush) ?>, csrf: <?= json_encode(Auth::csrfToken()) ?>,
  suscribir: <?= json_encode(url('notificaciones/push/suscribir')) ?>, cancelar: <?= json_encode(url('notificaciones/push/cancelar')) ?>};
</script>
<script src="<?= e(url('assets/js/push.js?v=' . filemtime(dirname(__DIR__, 4) . '/public/assets/js/push.js'))) ?>"></script>
