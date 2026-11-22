<?php
/** @var ?array $s @var array $v @var ?string $error @var array $firmantes @var bool $docx @var array $mias @var array $evidencia */
use App\Core\Auth;
use App\Modules\Constancias\Services\Solicitudes;

include __DIR__ . '/_estilos.php';
$porDepto = [];
foreach ($firmantes as $f) {
    $porDepto[$f['departamento']][] = $f;
}
$accion = $s ? 'constancias/solicitud/' . (int) $s['id'] : 'constancias/solicitar';
$modo = $docx ? $v['modo'] : 'texto';
?>
<div class="const-pila">
  <section class="tarjeta">
    <p class="const-ayuda" style="margin-top:0">La constancia se extiende a ti. Redacta el cuerpo con libertad, elige a quién la firma y, si hace falta, adjunta evidencia.</p>
    <?php if ($s && $s['estado'] === 'devuelta' && $s['observacion'] !== ''): ?>
      <div class="const-nota" style="margin-bottom:12px"><strong><?= e($s['firmante_nombre']) ?> la devolvió sin firmar y pidió esto:</strong><br><?= nl2br(e($s['observacion'])) ?><br><small>Corrige el texto o adjunta la evidencia que pide y vuelve a enviarla a firma.</small></div>
    <?php endif; ?>
    <?php if ($error !== null): ?><div class="const-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form class="formulario const-form" method="post" action="<?= e(url($accion)) ?>" enctype="multipart/form-data">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <div class="const-doble">
        <label>Dirigida a
          <textarea name="dirigido_a" rows="2" maxlength="600" required><?= e($v['dirigido_a']) ?></textarea>
          <span class="const-ayuda">Por omisión «A QUIEN CORRESPONDA». Para una persona, escribe su nombre y en otro renglón su cargo.</span>
        </label>
        <label>Asunto
          <input type="text" name="asunto" value="<?= e($v['asunto']) ?>" maxlength="160" required>
        </label>
      </div>

      <?php if ($docx): ?>
      <fieldset class="const-modo">
        <legend style="font-weight:600;margin-bottom:6px">Cuerpo de la constancia</legend>
        <label><input type="radio" name="modo" value="texto"<?= $modo === 'texto' ? ' checked' : '' ?>> Escribir el texto</label>
        <label><input type="radio" name="modo" value="docx"<?= $modo === 'docx' ? ' checked' : '' ?>> Subir un archivo de Word</label>
      </fieldset>
      <?php else: ?>
      <p class="const-nota">Este servidor no puede leer archivos de Word por ahora; escribe o pega el texto en el campo.</p>
      <?php endif; ?>

      <div id="const-por-texto"<?= $modo === 'docx' ? ' hidden' : '' ?>>
        <label>Texto
          <textarea class="const-texto" name="texto" maxlength="20000" placeholder="Quien suscribe, … hace constar que …"><?= e($v['texto']) ?></textarea>
        </label>
        <p class="const-ayuda">Separa los párrafos con una línea en blanco. Para <strong>negritas</strong> escribe <code>**así**</code>. Para una tabla, pega los renglones copiados de Excel o Word, o sepáralos con <code>|</code>: <code>| Nombre | Horas |</code>.</p>
      </div>
      <?php if ($docx): ?>
      <div id="const-por-docx"<?= $modo === 'docx' ? '' : ' hidden' ?>>
        <label>Archivo de Word (.docx, hasta 2 MB)
          <input type="file" name="archivo" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        </label>
        <p class="const-ayuda">Solo el cuerpo: sin membrete, folio ni firma, que la plataforma agrega. Se conservan párrafos, negritas, alineación y tablas; las imágenes y los estilos especiales se omiten y te avisamos.<?php if ($s && $s['origen'] === 'docx' && $s['archivo'] !== ''): ?> Si no eliges otro archivo, se conserva el cuerpo de «<?= e($s['archivo']) ?>».<?php endif; ?></p>
      </div>
      <?php endif; ?>

      <label>Firma
        <select name="firmante" required data-sin-filtro>
          <option value="">Elige a quien firma…</option>
          <?php foreach ($porDepto as $depto => $lista): ?>
            <optgroup label="<?= e($depto) ?>">
              <?php foreach ($lista as $f): ?><option value="<?= e($f['clave']) ?>"<?= $v['firmante'] === $f['clave'] ? ' selected' : '' ?>><?= e($f['nombre'] . ' — ' . $f['cargo']) ?></option><?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
        <span class="const-ayuda">Titulares de las coordinaciones, Subdirector Académico o Director. La constancia queda archivada en el departamento de quien firma, con su folio. Quien firma puede devolvértela sin firmar para pedirte evidencias o más datos.</span>
      </label>
      <?php if (!$firmantes): ?><p class="const-nota">Aún no hay titulares de coordinaciones, Subdirección ni Dirección registrados. Pide a Administración que los capture en «Estructura académica».</p><?php endif; ?>

      <fieldset class="const-modo" style="margin-top:14px">
        <legend style="font-weight:600;margin-bottom:6px">Evidencia (opcional)</legend>
        <?php if ($evidencia): ?>
          <ul style="margin:0 0 8px;padding-left:18px">
            <?php foreach ($evidencia as $a): ?>
              <li><a href="<?= e(url('constancias/adjunto/' . (int) $a['id'])) ?>" target="_blank" rel="noopener"><?= e($a['descripcion'] !== '' ? $a['descripcion'] : $a['nombre']) ?></a>
                <small>(<?= e($a['nombre']) ?>)</small>
                <button class="btn btn-mini" type="submit" formaction="<?= e(url('constancias/adjunto/' . (int) $a['id'] . '/quitar')) ?>" formnovalidate formmethod="post" onclick="return confirm('¿Quitar este archivo?')">Quitar</button></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <div id="const-evid"></div>
        <button type="button" class="btn btn-mini" id="const-evid-mas">+ Agregar archivo</button>
        <span class="const-ayuda">PDF, imagen (JPG o PNG), Word o Excel; hasta 15 MB cada uno. La descripción es opcional. Solo la ven quien firma y tú.</span>
      </fieldset>

      <div class="const-acciones">
        <button class="btn btn-primario" type="submit">Ver la vista previa</button>
        <?php if ($s): ?><a class="btn" href="<?= e(url('constancias/solicitud/' . (int) $s['id'])) ?>">Volver sin cambios</a><?php endif; ?>
      </div>
      <p class="const-ayuda">Aún no se envía: primero revisas el PDF y después la envías a firma.</p>
    </form>
  </section>

  <?php if ($mias): ?>
  <section class="tarjeta">
    <h2 style="margin-top:0;font-size:18px">Mis solicitudes</h2>
    <table class="const-lista">
      <thead><tr><th>Constancia</th><th>Firma</th><th>Folio</th><th>Estado</th><th><span class="sr-only">Abrir</span></th></tr></thead>
      <tbody>
      <?php foreach ($mias as $m): ?>
        <tr>
          <td><strong><?= e($m['asunto']) ?></strong><br><small><?= e(fecha_corta((string) $m['creado_en'])) ?></small></td>
          <td data-et="Firma"><?= e($m['firmante_nombre']) ?><br><small><?= e($m['departamento']) ?></small></td>
          <td data-et="Folio"><?= e($m['folio'] ?: '—') ?></td>
          <td><span class="const-estado <?= e($m['estado']) ?>"><?= e(Solicitudes::ESTADOS[$m['estado']] ?? $m['estado']) ?></span></td>
          <td class="const-acc"><a class="btn btn-mini" href="<?= e(url('constancias/solicitud/' . (int) $m['id'])) ?>">Abrir</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>
</div>
<script>
(function () {
  var radios = document.querySelectorAll('.const-modo input[name="modo"]');
  var texto = document.getElementById('const-por-texto');
  var docx = document.getElementById('const-por-docx');
  radios.forEach(function (r) {
    r.addEventListener('change', function () {
      var w = r.value === 'docx' && r.checked;
      if (texto) texto.hidden = w;
      if (docx) docx.hidden = !w;
    });
  });
  // Evidencia: filas de archivo + descripción opcional.
  var caja = document.getElementById('const-evid');
  var mas = document.getElementById('const-evid-mas');
  function fila() {
    var d = document.createElement('div');
    d.style.cssText = 'display:grid;grid-template-columns:minmax(0,1fr) minmax(0,1fr);gap:8px;margin:0 0 8px';
    d.innerHTML = '<input type="file" name="adjuntos[]" accept=".pdf,.jpg,.jpeg,.png,.docx,.xlsx" aria-label="Archivo de evidencia">'
      + '<input type="text" name="adjuntos_desc[]" maxlength="200" placeholder="De qué es (opcional)" aria-label="Descripción de la evidencia">';
    caja.appendChild(d);
  }
  if (caja && mas) { fila(); mas.addEventListener('click', fila); }
})();
</script>
