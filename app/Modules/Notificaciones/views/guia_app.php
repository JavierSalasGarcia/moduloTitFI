<?php use App\Core\Auth; ?>
<p style="margin:0 0 10px"><a href="<?= e(url('perfil')) ?>">← Mi perfil</a></p>
<h1 style="font-size:22px;margin:0 0 6px">Traza FI en tu teléfono</h1>
<p class="texto-suave" style="margin:0 0 18px">Dos pasos: instala Traza FI como app (sin tienda de aplicaciones) y activa las notificaciones para enterarte al momento de avisos, exámenes, convocatorias, eventos y de lo que llega a tu bandeja.</p>

<section class="card paso-app" id="instalar">
  <h2><span class="paso-num">1</span> Instalar Traza FI</h2>
  <p id="inst-listo" class="paso-ok" hidden>✅ Traza FI ya está instalada y la estás usando como app.</p>
  <div id="inst-boton" hidden>
    <button type="button" class="btn btn-primario btn-grande" id="btn-instalar">📲 Instalar Traza FI</button>
    <p class="texto-suave" style="margin:8px 0 0;font-size:13px">Aparecerá una ventana de tu teléfono o computadora: toca <strong>Instalar</strong>. El icono de Traza FI quedará junto a tus demás apps.</p>
  </div>
  <div id="inst-hecho" hidden><p class="paso-ok">✅ Instalada. Ábrela desde su icono y vuelve aquí (Mi perfil → Traza FI en tu teléfono) para el paso 2.</p></div>
  <div id="inst-ios" hidden>
    <p style="margin:0 0 8px">En iPhone y iPad se instala desde Safari:</p>
    <ol class="guia-pasos">
      <li>Toca el botón <strong>Más</strong> <span class="icono-ios" aria-hidden="true"><svg width="18" height="18" viewBox="0 0 18 18" fill="currentColor"><circle cx="3.5" cy="9" r="1.6"/><circle cx="9" cy="9" r="1.6"/><circle cx="14.5" cy="9" r="1.6"/></svg></span> (tres puntos) de la barra de Safari, abajo en iPhone.</li>
      <li>Toca <strong>Compartir</strong> <span class="icono-ios" aria-hidden="true"><svg width="16" height="18" viewBox="0 0 16 18" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M8 11V1M4.5 4.5 8 1l3.5 3.5"/><path d="M5 7H2v10h12V7h-3"/></svg></span> y, en la lista, <strong>«Agregar a pantalla de inicio»</strong>.</li>
      <li>Deja activado <strong>«Abrir como app web»</strong> y toca <strong>Agregar</strong>.</li>
      <li>Abre <strong>Traza FI</strong> desde su icono nuevo y vuelve a esta página para el paso 2 (en iPhone las notificaciones solo funcionan desde el icono).</li>
    </ol>
    <p class="texto-suave" style="margin:8px 0 0;font-size:13px">Si tu Safari muestra el botón <strong>Compartir</strong> directamente en la barra (iPad o versiones anteriores a iOS 26), tócalo y continúa con «Agregar a pantalla de inicio». Si no ves «Agregar a pantalla de inicio», desliza hacia abajo la lista de opciones.</p>
  </div>
  <div id="inst-safari" hidden><p style="margin:0">En iPhone y iPad abre <strong>fingenieria.mx</strong> en <strong>Safari</strong> para poder instalarla (requiere iOS 16.4 o posterior).</p></div>
  <div id="inst-manual" hidden>
    <p style="margin:0 0 6px">Tu navegador no ofrece la instalación directa en este momento. Ábrela en <strong>Google Chrome</strong> (Android o computadora) o <strong>Microsoft Edge</strong>:</p>
    <p style="margin:0 0 6px"><a class="btn" href="<?= e(url('notificaciones/app')) ?>" target="_blank" rel="noopener">Abrir esta página en una ventana nueva</a></p>
    <p class="texto-suave" style="margin:0;font-size:13px">Si ya estás en Chrome y no aparece el botón, puede que ya esté instalada: búscala entre tus apps como «Traza FI».</p>
  </div>
</section>

<section class="card paso-app" id="activar-push">
  <h2><span class="paso-num">2</span> Activar las notificaciones</h2>
  <p id="push-estado" class="texto-suave" style="margin:0 0 12px">Comprobando…</p>
  <div class="grupo-chips" style="gap:8px">
    <button type="button" class="btn btn-primario btn-grande" id="push-activar" hidden>🔔 Activar notificaciones en este dispositivo</button>
    <button type="button" class="btn" id="push-desactivar" hidden>Desactivar en este dispositivo</button>
  </div>
  <?php if ($dispositivos > 0): ?><p class="texto-suave" style="margin:10px 0 0;font-size:13px">Tienes <?= (int) $dispositivos ?> dispositivo<?= $dispositivos > 1 ? 's' : '' ?> con notificaciones activas.</p><?php endif; ?>
  <p class="texto-suave" style="margin:10px 0 0;font-size:13px">Si las bloqueaste antes, permítelas en los ajustes: Android, mantén presionado el icono → Información de la app → Notificaciones; iPhone, Configuración → Notificaciones → Traza FI.</p>
</section>
<section class="card paso-app" id="insignia">
  <h2><span class="paso-num">3</span> Número en el icono</h2>
  <p id="badge-estado" class="texto-suave" style="margin:0 0 12px">Comprobando…</p>
  <button type="button" class="btn" id="badge-probar" hidden>Probar el número en el icono</button>
  <p class="texto-suave" style="margin:10px 0 0;font-size:13px">En iPhone el número solo aparece si instalaste Traza FI desde Safari, la abres desde su icono, permitiste las notificaciones (paso 2) y en <strong>Configuración → Notificaciones → Traza FI</strong> está activa la opción <strong>Insignias</strong>. Android lo muestra como un punto o un número según tu teléfono.</p>
</section>
<script>
(function () {
  var est = document.getElementById('badge-estado'), btn = document.getElementById('badge-probar');
  if (!est || !btn) return;
  var soporta = 'setAppBadge' in navigator;
  var instalada = (window.matchMedia && matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;
  function diagnostico() {
    if (!soporta) return 'Este navegador no puede mostrar el número en el icono.';
    if (!instalada) return 'Abre Traza FI desde el icono de tu pantalla de inicio (no desde Safari o Chrome) para que el número aparezca.';
    if (!('Notification' in window) || Notification.permission !== 'granted') return 'Falta permitir las notificaciones (paso 2): sin ese permiso el teléfono no deja poner el número en el icono.';
    return 'Todo listo. Si aun así no ves el número, revisa que «Insignias» esté activa en los ajustes de notificaciones de Traza FI.';
  }
  est.textContent = diagnostico();
  if (soporta) btn.hidden = false;
  btn.addEventListener('click', function () {
    navigator.setAppBadge(7).then(function () {
      est.textContent = 'Puse el número 7 en el icono como prueba. Sal de la app y míralo en tu pantalla de inicio; se corregirá solo la próxima vez que abras Traza FI.';
    }).catch(function (x) {
      est.textContent = 'El teléfono rechazó la prueba (' + (x && x.name ? x.name : 'error') + '). ' + diagnostico();
    });
  });
})();
</script>
<style>
.paso-app{padding:20px;margin-bottom:16px}
.paso-app h2{font-size:18px;margin:0 0 12px;display:flex;align-items:center;gap:10px}
.paso-num{display:inline-flex;width:30px;height:30px;border-radius:50%;background:var(--verde-700,#2c6b4a);color:#fff;align-items:center;justify-content:center;font-size:16px}
.btn-grande{font-size:16px;padding:12px 20px}
.paso-ok{margin:0;font-weight:600;color:var(--verde-700,#2c6b4a)}
.guia-pasos{margin:0;padding-left:20px;line-height:1.7}
.icono-ios{display:inline-flex;vertical-align:middle;border:1px solid #cbd5e1;border-radius:6px;padding:2px 4px;color:#1d4ed8}
</style>
<script>
window.trazaPush = {clave: <?= json_encode($clavePush) ?>, csrf: <?= json_encode(Auth::csrfToken()) ?>,
  suscribir: <?= json_encode(url('notificaciones/push/suscribir')) ?>, cancelar: <?= json_encode(url('notificaciones/push/cancelar')) ?>};
</script>
<script src="<?= e(url('assets/js/push.js?v=' . filemtime(dirname(__DIR__, 4) . '/public/assets/js/push.js'))) ?>"></script>
