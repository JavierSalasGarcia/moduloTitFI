<?php
/** @var array $cola @var bool $rfcRegistrado @var int $certificadosSat */
use App\Core\Auth;
?>
<p style="margin:0 0 10px"><a href="<?= e(url('oficios/subdireccion?estado=en_firma')) ?>">← Oficios de la Subdirección</a></p>

<?php if (!$cola): ?>
  <section class="card"><p class="texto-suave" style="margin:0">No hay oficios esperando tu firma.</p></section>
<?php else: ?>
<section class="card sin-relleno">
  <div style="padding:14px 16px;display:flex;gap:12px;flex-wrap:wrap;align-items:center;justify-content:space-between">
    <div>
      <h2 style="margin:0 0 2px;font-size:18px">Cola de firma</h2>
      <p class="texto-suave" style="margin:0"><span id="lt-cuenta"><?= count($cola) ?></span> seleccionados de <?= count($cola) ?> · se firman en orden de número.</p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
      <input type="search" id="lt-filtro" placeholder="Filtrar la cola…" aria-label="Filtrar la cola" style="min-width:220px">
      <button type="button" class="btn" id="lt-todos">Seleccionar todos</button>
      <button type="button" class="btn" id="lt-ninguno">Quitar selección</button>
    </div>
  </div>
  <div class="tabla-contenedor" style="max-height:420px;overflow:auto">
    <table class="tabla" data-no-cards>
      <thead><tr><th style="width:44px"><input type="checkbox" id="lt-marca" checked aria-label="Seleccionar todos los visibles"></th><th>Número</th><th>Asunto</th><th>Destinatarios</th><th>Estado</th><th><span class="sr-only">Editar</span></th></tr></thead>
      <tbody>
      <?php foreach ($cola as $f): $dest = json_decode((string) $f['destinatarios'], true) ?: []; ?>
        <tr data-id="<?= (int) $f['id'] ?>" data-texto="<?= e(mb_strtolower($f['numero_texto'] . ' ' . $f['asunto'] . ' ' . implode(' ', array_map(static fn($d) => (string) $d['nombre'], $dest)))) ?>">
          <td><input type="checkbox" class="lt-sel" value="<?= (int) $f['id'] ?>" checked aria-label="Firmar <?= e($f['numero_texto']) ?>"></td>
          <td class="monoespacio"><a href="<?= e(url('oficios/subdireccion/' . (int) $f['id'])) ?>" target="_blank" rel="noopener"><?= e($f['numero_texto']) ?></a></td>
          <td><?= e($f['asunto']) ?></td>
          <td><?= e(implode('; ', array_map(static fn($d) => (string) $d['nombre'], array_slice($dest, 0, 2)))) ?><?= count($dest) > 2 ? ' (+' . (count($dest) - 2) . ')' : '' ?></td>
          <td class="lt-estado texto-suave">Pendiente</td>
          <td><a href="<?= e(url('oficios/subdireccion/' . (int) $f['id'] . '/editar')) ?>" target="_blank" rel="noopener">Editar</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<section class="card" id="bloque-firma">
  <h3 style="margin:0 0 6px;font-size:18px">Firmar la selección con tu e.firma (SAT)</h3>
  <p style="margin:0 0 10px;background:#f3f8f3;border:1px solid var(--gris-100,#dfe6e0);border-radius:8px;padding:10px 12px">
    <strong>Tu llave nunca sale de este navegador.</strong> Se abre una sola vez para toda la selección y se descarta al terminar; el servidor solo recibe tu certificado público y cada firma. Se pide en cada lote.
  </p>
  <?php if (!$rfcRegistrado): ?>
    <p class="texto-suave" style="background:#fffbeb;border:1px solid #f1e1a6;border-radius:8px;padding:10px 12px"><strong>Falta un paso:</strong> registra tu RFC en <a href="<?= e(url('oficios/subdireccion/configuracion')) ?>">Configuración</a>.</p>
  <?php endif; ?>
  <form id="form-firma" autocomplete="off" onsubmit="return false" style="display:grid;gap:12px;max-width:560px">
    <label>Certificado (.cer)<input type="file" id="f-cer" accept=".cer" required></label>
    <label>Llave privada (.key)<input type="file" id="f-key" accept=".key" required></label>
    <label>Contraseña de la llave<input type="password" id="f-pass" autocomplete="off" required></label>
    <div><button class="btn btn-primario" type="button" id="btn-firmar"<?= $rfcRegistrado ? '' : ' disabled' ?>>Firmar oficios seleccionados</button>
      <button class="btn" type="button" id="btn-detener" hidden>Detener</button></div>
  </form>
  <div id="lt-progreso" hidden style="margin-top:14px;max-width:560px">
    <progress id="lt-barra" value="0" max="1" style="width:100%"></progress>
    <p id="firma-estado" role="status" aria-live="polite" style="margin:6px 0 0"></p>
  </div>
  <?php if ($certificadosSat === 0): ?><p class="texto-suave" style="font-size:13px;margin:12px 0 0">Aún no hay certificados del SAT cargados: la cadena quedará como «no verificada» hasta cargarlos en Configuración.</p><?php endif; ?>
</section>

<script src="<?= e(url('assets/js/vendor/forge.min.js?v=1.3.1')) ?>"></script>
<script src="<?= e(url('assets/js/oficios-firma.js?v=' . (string) filemtime(dirname(__DIR__, 4) . '/public/assets/js/oficios-firma.js'))) ?>"></script>
<script>
(function () {
  var csrf = <?= json_encode(Auth::csrfToken()) ?>;
  var base = <?= json_encode(url('oficios/subdireccion/')) ?>;
  var filas = Array.prototype.slice.call(document.querySelectorAll('tbody tr[data-id]'));
  var estado = document.getElementById('firma-estado'), btn = document.getElementById('btn-firmar'), stop = document.getElementById('btn-detener');
  var barra = document.getElementById('lt-barra'), cuenta = document.getElementById('lt-cuenta'), detener = false;
  if (!btn) return;

  function visibles() { return filas.filter(function (f) { return !f.hidden; }); }
  function sel(f) { return f.querySelector('.lt-sel'); }
  function actualizar() { cuenta.textContent = filas.filter(function (f) { return sel(f).checked; }).length; }
  function marcar(lista, v) { lista.forEach(function (f) { sel(f).checked = v; }); actualizar(); }
  document.getElementById('lt-todos').addEventListener('click', function () { marcar(visibles(), true); });
  document.getElementById('lt-ninguno').addEventListener('click', function () { marcar(filas, false); });
  document.getElementById('lt-marca').addEventListener('change', function () { marcar(visibles(), this.checked); });
  filas.forEach(function (f) { sel(f).addEventListener('change', actualizar); });
  document.getElementById('lt-filtro').addEventListener('input', function () {
    var q = this.value.trim().toLowerCase();
    filas.forEach(function (f) { f.hidden = q !== '' && f.getAttribute('data-texto').indexOf(q) === -1; });
  });
  stop.addEventListener('click', function () { detener = true; stop.disabled = true; msg('Deteniendo al terminar el oficio en curso…'); });

  function msg(t, error) { estado.textContent = t; estado.style.color = error ? '#b91c1c' : ''; }
  function post(url, datos) {
    var cuerpo = new URLSearchParams(); cuerpo.set('_csrf', csrf); cuerpo.set('lote', '1');
    Object.keys(datos).forEach(function (k) { cuerpo.set(k, datos[k]); });
    return fetch(url, { method: 'POST', credentials: 'same-origin', body: cuerpo, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })
      .then(function (r) { return r.json().then(function (d) { if (!r.ok || !d.ok) throw new Error(d.error || 'No se pudo completar.'); return d; }); });
  }
  function ponerEstado(fila, texto, color) { var c = fila.querySelector('.lt-estado'); c.textContent = texto; c.style.color = color || ''; }

  btn.addEventListener('click', function () {
    var elegidas = filas.filter(function (f) { return sel(f).checked; });
    var cer = document.getElementById('f-cer').files[0], key = document.getElementById('f-key').files[0], pass = document.getElementById('f-pass').value;
    document.getElementById('lt-progreso').hidden = false;
    if (!elegidas.length) { msg('Selecciona al menos un oficio.', true); return; }
    if (!cer || !key || !pass) { msg('Elige el .cer, el .key y escribe la contraseña.', true); return; }
    if (!window.forge || !window.OficioFiel) { msg('No se cargó el módulo de firma. Recarga la página.', true); return; }
    if (!confirm('Se firmarán ' + elegidas.length + ' oficio(s) con tu e.firma. ¿Continuar?')) return;
    btn.disabled = true; stop.hidden = false; stop.disabled = false; detener = false;
    barra.max = elegidas.length; barra.value = 0;
    var fiel = null, hechos = 0, fallos = [];
    msg('Abriendo tu e.firma…');
    Promise.all([OficioFiel.leerArchivo(cer), OficioFiel.leerArchivo(key)]).then(function (a) {
      fiel = OficioFiel.abrir(a[0], a[1], pass);
      pass = ''; document.getElementById('f-pass').value = '';
      var i = 0;
      function siguiente() {
        if (detener || i >= elegidas.length) return Promise.resolve();
        var fila = elegidas[i++], id = fila.getAttribute('data-id');
        ponerEstado(fila, 'Firmando…');
        return post(base + id + '/firma/preparar', { certificado: fiel.resumen.certificadoDer }).then(function (p) {
          var bin = atob(p.pdf), r = p.rango;
          var hex = OficioFiel.firmar(fiel, bin.substr(r[0], r[1]) + bin.substr(r[2], r[3]));
          return post(base + id + '/firma/guardar', { firma: hex });
        }).then(function () {
          hechos++; ponerEstado(fila, 'Firmado', '#166534'); sel(fila).checked = false; sel(fila).disabled = true;
        }).catch(function (e) {
          fallos.push(e.message); ponerEstado(fila, 'Error: ' + e.message, '#b91c1c');
          // Si falla el primero, lo normal es un problema de la e.firma: no se insiste con todos.
          if (hechos === 0 && fallos.length >= 2) detener = true;
        }).then(function () {
          barra.value = hechos + fallos.length; msg('Firmados ' + hechos + ' de ' + elegidas.length + (fallos.length ? ' · con error: ' + fallos.length : '') + '…');
          actualizar();
          return siguiente();
        });
      }
      return siguiente();
    }).then(function () {
      fiel = null; stop.hidden = true; btn.disabled = false; actualizar();
      msg((detener ? 'Detenido. ' : 'Listo. ') + 'Firmados: ' + hechos + (fallos.length ? ' · con error: ' + fallos.length + ' (' + fallos[0] + ')' : '') + '. Los firmados salen de la cola al recargar.', fallos.length > 0);
    }).catch(function (e) {
      fiel = null; stop.hidden = true; btn.disabled = false; msg(e.message, true);
    });
  });
  window.addEventListener('pagehide', function () { ['f-cer', 'f-key', 'f-pass'].forEach(function (i) { var x = document.getElementById(i); if (x) x.value = ''; }); });
})();
</script>
<?php endif; ?>
