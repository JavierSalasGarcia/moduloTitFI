<?php
use App\Core\Auth;
$parrafos = array_filter(array_map('trim', preg_split('/\R\s*\R/u', (string) $f['descripcion']) ?: []));
?>
<style>
.frm-ok{background:#F0FDF4;border-left:4px solid var(--ok);padding:12px 14px;border-radius:4px;margin:0 0 14px}
.frm-req label{display:flex;gap:10px;align-items:flex-start;font-weight:400;margin:6px 0}
.frm-req input{margin-top:4px;flex:0 0 auto}
.frm-mayus{text-transform:uppercase}
</style>
<section class="tarjeta" style="max-width:820px;margin:0 auto 16px">
  <h2><?= e($f['titulo']) ?></h2>
  <?php foreach ($parrafos as $p): ?><p><?= nl2br(e($p)) ?></p><?php endforeach; ?>
</section>
<section class="tarjeta" style="max-width:820px;margin:0 auto">
  <?php if ($enviado !== null): ?>
    <div class="frm-ok" role="status"><strong>Registro recibido<?= $enviado !== '' ? ', ' . e($enviado) : '' ?>.</strong> Gracias. Ya no necesitas hacer nada más.</div>
  <?php endif; ?>
  <?php if (!$abierto): ?>
    <p><strong>Este formulario ya no recibe registros.</strong></p>
  <?php else: ?>
  <form method="post" action="<?= url('f/' . $f['slug']) ?>" class="formulario">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <div style="position:absolute;left:-9999px" aria-hidden="true"><label>No llenar <input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
    <?php $hayReq = false; foreach ($campos as $c): $v = (string) ($previo[$c['k']] ?? $c['defecto'] ?? ''); ?>
      <?php if (($c['tipo'] ?? '') === 'confirmar'): if (!$hayReq): $hayReq = true; ?><fieldset class="frm-req" style="margin-top:12px"><legend><strong>Requisitos</strong> (marca cada uno para confirmar que lo cumples)</legend><?php endif; ?>
        <label><input type="checkbox" name="d[<?= e($c['k']) ?>]" value="1" <?= $v !== '' ? 'checked' : '' ?> <?= !empty($c['req']) ? 'required' : '' ?>> <span><?= e($c['label']) ?></span></label>
      <?php elseif (($c['tipo'] ?? '') === 'select'): ?>
        <label><?= e($c['label']) ?><?= !empty($c['req']) ? ' *' : '' ?>
          <select name="d[<?= e($c['k']) ?>]" <?= !empty($c['req']) ? 'required' : '' ?>><option value="">Elige…</option><?php foreach ($c['opciones'] as $k => $n): ?><option value="<?= e((string) $k) ?>" <?= (string) $k === $v ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select></label>
      <?php else: ?>
        <label><?= e($c['label']) ?><?= !empty($c['req']) ? ' *' : '' ?>
          <input type="<?= ($c['tipo'] ?? '') === 'email' ? 'email' : 'text' ?>" name="d[<?= e($c['k']) ?>]" maxlength="<?= (int) ($c['max'] ?? 200) ?>" value="<?= e($v) ?>" <?= !empty($c['req']) ? 'required' : '' ?> <?= !empty($c['mayusculas']) ? 'class="frm-mayus"' : '' ?> autocomplete="off"></label>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if ($hayReq): ?></fieldset><?php endif; ?>
    <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Enviar registro</button></div>
  </form>
  <?php endif; ?>
</section>
