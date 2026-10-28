<?php
use App\Core\Auth;
use App\Modules\Formularios\Formularios;
$parrafos = array_filter(array_map('trim', preg_split('/\R\s*\R/u', (string) $f['descripcion']) ?: []));
$conArchivos = in_array('evidencia', array_column($campos, 'tipo'), true);
?>
<style>
.frm-ok{background:#F0FDF4;border-left:4px solid var(--ok);padding:12px 14px;border-radius:4px;margin:0 0 14px}
.frm-req label{display:flex;gap:10px;align-items:flex-start;font-weight:400;margin:6px 0}
.frm-req input{margin-top:4px;flex:0 0 auto}
.frm-mayus{text-transform:uppercase}
.frm-aviso{background:#FFFBEB;border-left:4px solid var(--oro,#9C8412);padding:10px 14px;border-radius:4px;margin:0 0 14px}
.frm-ev{border:1px solid var(--borde,#d7dbd8);border-radius:6px;padding:10px 12px;margin:10px 0}
.frm-ev .frm-ev-modo{display:flex;flex-wrap:wrap;gap:6px 18px;margin:4px 0 8px}
.frm-ev .frm-ev-modo label{display:flex;gap:6px;align-items:center;font-weight:400;margin:0}
.frm-ev [hidden]{display:none}
</style>
<section class="tarjeta" style="max-width:820px;margin:0 auto 16px">
  <h2><?= e($f['titulo']) ?></h2>
  <?php foreach ($parrafos as $p): ?><p><?= nl2br(e($p)) ?></p><?php endforeach; ?>
</section>
<section class="tarjeta" style="max-width:820px;margin:0 auto">
  <?php if ($enviado !== null): ?>
    <div class="frm-ok" role="status"><strong>Registro recibido<?= $enviado !== '' ? ', ' . e($enviado) : '' ?>.</strong> Gracias. <?= !empty($f['varios_por_correo']) ? 'Si tienes otro, regístralo en un nuevo envío con este mismo formulario.' : 'Ya no necesitas hacer nada más.' ?></div>
  <?php endif; ?>
  <?php if (!$cuenta && $abierto): ?>
    <div class="frm-aviso">Puedes llenar este formulario sin cuenta, pero te recomendamos <a href="<?= url('registro') ?>">registrarte en Traza FI</a> (o <a href="<?= url('login') ?>">iniciar sesión</a> si ya tienes cuenta): tus datos se llenan solos y recibirás en tu bandeja los avisos de la Facultad.</div>
  <?php endif; ?>
  <?php if (!$abierto): ?>
    <p><strong>Este formulario ya no recibe registros.</strong></p>
  <?php else: ?>
  <form method="post" action="<?= url('f/' . $f['slug']) ?>" class="formulario"<?= $conArchivos ? ' enctype="multipart/form-data"' : '' ?>>
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div style="position:absolute;left:-9999px" aria-hidden="true"><label>No llenar <input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
    <?php $hayReq = false; foreach ($campos as $c): $v = (string) ($previo[$c['k']] ?? (isset($c['de_cuenta']) && $cuenta ? ($cuenta[$c['de_cuenta']] ?? '') : null) ?? $c['defecto'] ?? ''); ?>
      <?php if (($c['tipo'] ?? '') === 'confirmar'): if (!$hayReq): $hayReq = true; ?><fieldset class="frm-req" style="margin-top:12px"><legend><strong>Requisitos</strong> (marca cada uno para confirmar que lo cumples)</legend><?php endif; ?>
        <label><input type="checkbox" name="d[<?= e($c['k']) ?>]" value="1" <?= $v !== '' ? 'checked' : '' ?> <?= !empty($c['req']) ? 'required' : '' ?>> <span><?= e($c['label']) ?></span></label>
      <?php elseif (($c['tipo'] ?? '') === 'evidencia'): $modo = (string) ($previo[$c['k'] . '_modo'] ?? ''); $mb = round(Formularios::maxBytes($c) / 1048576); ?>
        <fieldset class="frm-ev" data-max="<?= Formularios::maxBytes($c) ?>">
          <legend><strong><?= e($c['label']) ?></strong><?= !empty($c['req']) ? ' *' : '' ?></legend>
          <p class="texto-secundario" style="margin:0">Entrégala de una sola forma: un archivo comprimido o un enlace a una carpeta.</p>
          <div class="frm-ev-modo">
            <label><input type="radio" name="d[<?= e($c['k']) ?>_modo]" value="archivo" <?= $modo === 'archivo' ? 'checked' : '' ?> <?= !empty($c['req']) ? 'required' : '' ?>> Archivo .zip (máximo <?= $mb ?> MB)</label>
            <label><input type="radio" name="d[<?= e($c['k']) ?>_modo]" value="enlace" <?= $modo === 'enlace' ? 'checked' : '' ?>> Enlace a una carpeta (Drive, OneDrive…)</label>
          </div>
          <label data-modo="archivo" <?= $modo === 'archivo' ? '' : 'hidden' ?>>Archivo .zip con la evidencia
            <input type="file" name="<?= e(Formularios::campoArchivo($c)) ?>" accept=".zip,application/zip"></label>
          <label data-modo="enlace" <?= $modo === 'enlace' ? '' : 'hidden' ?>>Enlace a la carpeta (debe poder abrirlo cualquier persona con el enlace)
            <input type="url" name="d[<?= e($c['k']) ?>_enlace]" maxlength="500" placeholder="https://" value="<?= $modo === 'enlace' ? e((string) ($previo[$c['k']] ?? '')) : '' ?>" autocomplete="off"></label>
        </fieldset>
      <?php elseif (($c['tipo'] ?? '') === 'fecha'): ?>
        <label><?= e($c['label']) ?><?= !empty($c['req']) ? ' *' : '' ?>
          <input type="date" name="d[<?= e($c['k']) ?>]" value="<?= e($v) ?>" <?= isset($c['min']) ? 'min="' . e((string) $c['min']) . '"' : '' ?> <?= isset($c['max_fecha']) ? 'max="' . e((string) $c['max_fecha']) . '"' : '' ?> <?= !empty($c['req']) ? 'required' : '' ?>></label>
      <?php elseif (($c['tipo'] ?? '') === 'select'): ?>
        <label><?= e($c['label']) ?><?= !empty($c['req']) ? ' *' : '' ?>
          <select name="d[<?= e($c['k']) ?>]" <?= !empty($c['req']) ? 'required' : '' ?>><option value="">Elige…</option><?php foreach ($c['opciones'] as $k => $n): ?><option value="<?= e((string) $k) ?>" <?= (string) $k === $v ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
      <?php else: ?>
        <label><?= e($c['label']) ?><?= !empty($c['req']) ? ' *' : '' ?>
          <input type="<?= ($c['tipo'] ?? '') === 'email' ? 'email' : 'text' ?>" name="d[<?= e($c['k']) ?>]" maxlength="<?= (int) ($c['max'] ?? 200) ?>" value="<?= e($v) ?>" <?= !empty($c['req']) ? 'required' : '' ?> <?= !empty($c['mayusculas']) ? 'class="frm-mayus"' : '' ?> autocomplete="off"></label>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($hayReq): ?></fieldset><?php endif; ?>
    <p class="texto-secundario" style="font-size:13px;margin-top:12px"><strong>Aviso de privacidad:</strong> la Facultad de Ingeniería de la UAEMEX usará los datos de este formulario únicamente para la finalidad descrita arriba; cuando otra instancia solicita la información, se entregan a esa instancia. Se conservan mientras dura la actividad y después se archivan o eliminan. Puedes ejercer tus derechos de acceso, rectificación, cancelación u oposición ante la Unidad de Transparencia de la Facultad de Ingeniería. Al enviar el registro aceptas este uso de tus datos.</p>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Enviar registro</button></div>
  </form>
  <?php if ($conArchivos): ?>
  <script>
  // Evidencia: archivo o enlace, nunca ambos. Muestra solo el campo elegido y revisa el tamaño del .zip antes de enviar.
  document.querySelectorAll('.frm-ev').forEach(function (fs) {
    var archivo = fs.querySelector('[data-modo="archivo"]'), enlace = fs.querySelector('[data-modo="enlace"]');
    var file = archivo.querySelector('input'), url = enlace.querySelector('input');
    function aplicar() {
      var m = (fs.querySelector('input[type=radio]:checked') || {}).value || '';
      archivo.hidden = m !== 'archivo'; enlace.hidden = m !== 'enlace';
      file.required = m === 'archivo'; url.required = m === 'enlace';
      if (m !== 'archivo') { file.value = ''; }
      if (m !== 'enlace') { url.value = ''; }
    }
    fs.querySelectorAll('input[type=radio]').forEach(function (r) { r.addEventListener('change', aplicar); });
    file.addEventListener('change', function () {
      var f = file.files[0], max = +fs.dataset.max;
      file.setCustomValidity(f && !/\.zip$/i.test(f.name) ? 'La evidencia debe ser un archivo .zip.'
        : f && f.size > max ? 'El archivo pasa de ' + Math.round(max / 1048576) + ' MB: reduce su tamaño o comparte un enlace.' : '');
      file.reportValidity();
    });
    aplicar();
  });
  </script>
  <?php endif; ?>
  <?php endif; ?>
</section>
