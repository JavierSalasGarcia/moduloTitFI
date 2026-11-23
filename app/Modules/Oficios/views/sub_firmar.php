<?php
/** @var array $o @var bool $rfcRegistrado @var int $certificadosSat */
use App\Core\Auth;
$id = (int) $o['id'];
?>
<p style="margin:0 0 10px"><a href="<?= e(url('oficios/subdireccion/' . $id)) ?>">← Oficio</a></p>
<section class="card">
  <h2 style="margin:0 0 6px;font-size:20px"><?= e($o['numero_texto']) ?> · <?= e($o['asunto']) ?></h2>
  <p class="texto-suave" style="margin:0 0 12px">Revisa el oficio y fírmalo con tu e.firma del SAT. Si necesitas corregir algo, puedes <a href="<?= e(url('oficios/subdireccion/' . $id . '/editar')) ?>"><strong>editarlo</strong></a>; conserva su número y regresas aquí para firmarlo.</p>
  <iframe title="Vista previa del oficio" src="<?= e(url('oficios/subdireccion/' . $id . '/pdf')) ?>" style="width:100%;height:560px;border:1px solid var(--gris-300,#cbd5e1);border-radius:8px"></iframe>
</section>

<section class="card" id="bloque-firma">
  <h3 style="margin:0 0 6px;font-size:18px">Firmar con e.firma (SAT)</h3>
  <p style="margin:0 0 10px;background:#f3f8f3;border:1px solid var(--gris-100,#dfe6e0);border-radius:8px;padding:10px 12px">
    <strong>Tu llave nunca sale de este navegador.</strong> El certificado (.cer), la llave (.key) y la contraseña se usan aquí para firmar y se descartan; el servidor solo recibe tu certificado público y la firma. Se piden cada vez que firmas.
  </p>
  <?php if (!$rfcRegistrado): ?>
    <p class="texto-suave" style="background:#fffbeb;border:1px solid #f1e1a6;border-radius:8px;padding:10px 12px"><strong>Falta un paso:</strong> registra tu RFC en <a href="<?= e(url('oficios/subdireccion/configuracion')) ?>">Configuración</a> para que la plataforma compruebe que la e.firma es la tuya. No se muestra en ningún documento.</p>
  <?php endif; ?>
  <form id="form-firma" autocomplete="off" onsubmit="return false" style="display:grid;gap:12px;max-width:560px">
    <label>Certificado (.cer)<input type="file" id="f-cer" accept=".cer" required></label>
    <label>Llave privada (.key)<input type="file" id="f-key" accept=".key" required></label>
    <label>Contraseña de la llave<input type="password" id="f-pass" autocomplete="off" required></label>
    <div><button class="btn btn-primario" type="button" id="btn-firmar"<?= $rfcRegistrado ? '' : ' disabled' ?>>Firmar oficio</button>
      <span id="firma-estado" role="status" aria-live="polite" class="texto-suave" style="margin-left:8px"></span></div>
  </form>
  <?php if ($certificadosSat === 0): ?><p class="texto-suave" style="font-size:13px;margin:12px 0 0">Aún no hay certificados del SAT cargados: la firma se hará, pero la cadena del certificado quedará como «no verificada» hasta que se carguen en Configuración.</p><?php endif; ?>
</section>

<section class="card">
  <h3 style="margin:0 0 6px;font-size:16px">¿Hay algo que corregir? Devuélvelo sin firmar</h3>
  <form method="post" action="<?= e(url('oficios/subdireccion/' . $id . '/devolver')) ?>" style="display:grid;gap:8px;max-width:560px">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <label>Qué se debe corregir<textarea name="observacion" rows="3" maxlength="500" required minlength="5"></textarea></label>
    <div><button class="btn" type="submit">Devolver a quien lo redactó</button></div>
  </form>
</section>

<script src="<?= e(url('assets/js/vendor/forge.min.js?v=1.3.1')) ?>"></script>
<script src="<?= e(url('assets/js/oficios-firma.js?v=' . (string) filemtime(dirname(__DIR__, 4) . '/public/assets/js/oficios-firma.js'))) ?>"></script>
<script>
(function () {
  var csrf = <?= json_encode(Auth::csrfToken()) ?>;
  var urlPreparar = <?= json_encode(url('oficios/subdireccion/' . $id . '/firma/preparar')) ?>;
  var urlGuardar = <?= json_encode(url('oficios/subdireccion/' . $id . '/firma/guardar')) ?>;
  var estado = document.getElementById('firma-estado'), btn = document.getElementById('btn-firmar');
  function msg(t, error) { estado.textContent = t; estado.style.color = error ? '#b91c1c' : ''; }
  function post(url, datos) {
    var cuerpo = new URLSearchParams(); cuerpo.set('_csrf', csrf);
    Object.keys(datos).forEach(function (k) { cuerpo.set(k, datos[k]); });
    return fetch(url, { method: 'POST', credentials: 'same-origin', body: cuerpo, headers: { 'Content-Type': 'application/x-www-form-urlencoded' } })
      .then(function (r) { return r.json().then(function (d) { if (!r.ok || !d.ok) throw new Error(d.error || 'No se pudo completar.'); return d; }); });
  }
  btn.addEventListener('click', function () {
    var cer = document.getElementById('f-cer').files[0], key = document.getElementById('f-key').files[0], pass = document.getElementById('f-pass').value;
    if (!cer || !key || !pass) { msg('Elige el .cer, el .key y escribe la contraseña.', true); return; }
    if (!window.forge || !window.OficioFiel) { msg('No se cargó el módulo de firma. Recarga la página.', true); return; }
    btn.disabled = true; msg('Abriendo tu e.firma…');
    var fiel = null;
    Promise.all([OficioFiel.leerArchivo(cer), OficioFiel.leerArchivo(key)]).then(function (a) {
      fiel = OficioFiel.abrir(a[0], a[1], pass);
      pass = ''; document.getElementById('f-pass').value = '';
      msg('Preparando el documento…');
      return post(urlPreparar, { certificado: fiel.resumen.certificadoDer });
    }).then(function (p) {
      var bin = atob(p.pdf), r = p.rango;                                  // [0, a, b, c]
      var contenido = bin.substr(r[0], r[1]) + bin.substr(r[2], r[3]);
      msg('Firmando con ' + p.resumen.nombre + ' (certificado ' + p.resumen.serie + ')…');
      var hex = OficioFiel.firmar(fiel, contenido);
      fiel = null;
      msg('Guardando la firma…');
      return post(urlGuardar, { firma: hex });
    }).then(function (d) {
      msg('Oficio firmado. ' + d.cadena);
      setTimeout(function () { location.href = d.url; }, 1200);
    }).catch(function (e) {
      fiel = null; btn.disabled = false; msg(e.message, true);
    });
  });
  // La llave y la contraseña no se conservan: al salir de la página se vacían los campos.
  window.addEventListener('pagehide', function () { ['f-cer', 'f-key', 'f-pass'].forEach(function (i) { var x = document.getElementById(i); if (x) x.value = ''; }); });
})();
</script>
