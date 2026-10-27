<?php use App\Core\Auth; ?>
<p><a href="<?= url('oficios') ?>">← Oficios enviados</a></p>
<form method="post" action="<?= url('oficios') ?>" enctype="multipart/form-data" class="tarjeta formulario" style="max-width:760px">
  <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
  <?php include __DIR__ . '/_campos.php'; ?>
  <label>PDF del oficio firmado <span class="req">*</span>
    <input type="file" name="archivo" accept="application/pdf" required>
  </label>
  <p class="ayuda-form">Un solo archivo PDF (incluye los anexos dentro del mismo archivo). Máximo 10 MB; límite de PHP: <?= e(ini_get('upload_max_filesize')) ?>.</p>
  <button class="btn btn-primario">Archivar oficio</button>
</form>
