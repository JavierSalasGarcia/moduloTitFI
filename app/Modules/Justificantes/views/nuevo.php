<?php
use App\Core\Auth;
use App\Modules\Justificantes\Justificantes;
$v = static fn(string $k, string $defecto = ''): string => e((string) ($previo[$k] ?? $defecto));
$primero = array_key_first($firmantes);
$elegido = (string) ($previo['firmante'] ?? $primero);
?>
<p><a href="<?= url('justificantes') ?>">← Oficios de justificación</a></p>
<form method="post" action="<?= url('justificantes') ?>" enctype="multipart/form-data" class="tarjeta formulario" style="max-width:820px" data-justificante>
  <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">

  <label>Firma
    <select name="firmante" data-firmante required>
      <?php foreach ($firmantes as $k => $f): ?>
        <option value="<?= e($k) ?>" <?= $k === $elegido ? 'selected' : '' ?>><?= e($f['nombre']) ?> · <?= e($f['cargo']) ?></option>
      <?php endforeach; ?>
    </select>
    <small>El nombre y el cargo de quien firma se precargan desde Organización; el oficio sale en la hoja membretada de la Facultad.</small>
  </label>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,230px),1fr));gap:14px">
    <label>Número de oficio
      <input name="numero" maxlength="80" data-numero value="<?= $v('numero', $sugeridos[$elegido] ?? '') ?>">
      <small>Se sugiere el siguiente; puedes corregirlo.</small>
    </label>
    <label>Fecha del oficio
      <input type="date" name="fecha" required value="<?= $v('fecha', date('Y-m-d')) ?>">
    </label>
  </div>

  <label>Asunto
    <input name="asunto" maxlength="200" required value="<?= $v('asunto', Justificantes::ASUNTO) ?>">
  </label>

  <fieldset style="border:1px solid var(--gris-200,#ddd);border-radius:8px;padding:12px 14px">
    <legend>Dirigido a</legend>
    <label>Profesor(a) en específico <small>(déjalo vacío para un oficio general a las y los profesores)</small>
      <input name="destinatario" maxlength="200" data-destinatario placeholder="Ej. Dr. Juan Pérez Gómez" value="<?= $v('destinatario') ?>">
    </label>
    <label>Saludo
      <input name="saludo" maxlength="200" data-saludo value="<?= $v('saludo', Justificantes::SALUDO) ?>">
      <small>Por omisión «<?= e(Justificantes::SALUDO) ?>»; si indicas a un profesor, se cambia a «Estimado/a …» y puedes editarlo.</small>
    </label>
  </fieldset>

  <label>Mensaje
    <textarea name="cuerpo" rows="11" maxlength="4000" required><?= $v('cuerpo', Justificantes::CUERPO) ?></textarea>
    <small>Un párrafo por bloque (separa con una línea en blanco). El primer párrafo va antes de los datos y de la lista de alumnos; los demás, después.</small>
  </label>

  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,300px),1fr));gap:14px">
    <label>Periodo de inasistencia
      <input name="periodo" maxlength="250" required placeholder="Ej. Del 21 al 23 de octubre de 2026" value="<?= $v('periodo') ?>">
    </label>
    <label>Motivo
      <input name="motivo" maxlength="250" required placeholder="Ej. Representación de la Facultad en un evento académico" value="<?= $v('motivo') ?>">
    </label>
  </div>

  <label>Alumnos (uno por renglón: nombre, número de cuenta, grupo)
    <textarea name="alumnos" rows="6" maxlength="6000" required placeholder="María José Hernández Peña, 1912345, ICO-5"><?= $v('alumnos') ?></textarea>
  </label>

  <label>Archivos adjuntos <small>(opcional: PDF, JPG o PNG; hasta <?= (int) Justificantes::MAX_ADJUNTOS ?>)</small>
    <input type="file" name="adjuntos[]" accept="application/pdf,image/jpeg,image/png" multiple>
    <small>Se incluyen al final del mismo PDF del oficio, y el oficio los enlista. Máximo <?= e(ini_get('upload_max_filesize')) ?> por archivo.</small>
  </label>

  <button class="btn btn-primario" type="submit">Generar oficio en PDF</button>
</form>
<script>
(function () {
  var f = document.querySelector('[data-justificante]');
  if (!f) { return; }
  var sugeridos = <?= json_encode($sugeridos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var sel = f.querySelector('[data-firmante]'), num = f.querySelector('[data-numero]');
  var dest = f.querySelector('[data-destinatario]'), saludo = f.querySelector('[data-saludo]');
  var numEditado = num.value !== '' && num.value !== (sugeridos[sel.value] || ''), saludoEditado = saludo.value !== <?= json_encode(Justificantes::SALUDO) ?>;
  num.addEventListener('input', function () { numEditado = true; });
  saludo.addEventListener('input', function () { saludoEditado = true; });
  sel.addEventListener('change', function () { if (!numEditado) { num.value = sugeridos[sel.value] || ''; } });
  dest.addEventListener('input', function () {
    if (saludoEditado) { return; }
    var n = dest.value.trim();
    saludo.value = n ? 'Estimado/a ' + n + ':' : <?= json_encode(Justificantes::SALUDO) ?>;
  });
})();
</script>
