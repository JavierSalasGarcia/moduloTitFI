<?php
use App\Modules\Titulacion\Services\Verificacion;
include __DIR__ . '/_estilos.php';
?>
<section class="tarjeta" style="max-width:780px;margin:20px auto">
  <h2>Verificación de documentos de titulación</h2>
  <p class="texto-secundario">Facultad de Ingeniería · Universidad Autónoma del Estado de México</p>
  <?php if ($r === null): ?>
    <?php if ($codigo !== ''): ?>
      <p class="tit-obs"><strong>No se encontró ningún documento con el código <?= e($codigo) ?>.</strong> El documento no fue emitido por la plataforma o el código no corresponde.</p>
    <?php endif; ?>
    <form method="get" action="<?= url('verificar') ?>">
      <label for="cod">Escanee el código QR del documento o escriba el código de verificación:</label>
      <input id="cod" name="codigo" maxlength="20" autocomplete="off" style="text-transform:uppercase;max-width:260px" value="<?= e($codigo) ?>">
      <button class="btn btn-primario" type="submit">Verificar</button>
    </form>
  <?php else: ?>
    <div class="<?= $r['vigente'] ? 'tit-ok' : 'tit-obs' ?>" style="padding:10px 12px;border-radius:8px;margin:12px 0">
      <strong style="font-size:1.1em"><?= $r['vigente'] ? '✓' : '✕' ?> <?= e($r['estado']) ?></strong>
      — documento emitido por la plataforma de titulación de la Facultad de Ingeniería.
      <?php if ($r['aviso']): ?><br><?= e($r['aviso']) ?><?php endif; ?>
    </div>
    <div class="tabla-contenedor"><table class="tabla">
      <tr><th style="width:34%">Documento</th><td><strong><?= e($r['nombre']) ?></strong></td></tr>
      <tr><th>ID del proceso de titulación</th><td><?= e($r['folio']) ?></td></tr>
      <tr><th>Código de verificación</th><td><code><?= e($codigo) ?></code></td></tr>
      <tr><th>Generado</th><td><?= e(Verificacion::fechaHora($r['emitido'])) ?></td></tr>
    </table></div>

    <?php if ($r['firmas']): ?>
      <h3 style="margin-top:18px">Firmas registradas en la plataforma</h3>
      <div class="tabla-contenedor"><table class="tabla">
        <thead><tr><th>Firmante</th><th>Calidad</th><th>Fecha</th></tr></thead>
        <tbody>
        <?php foreach ($r['firmas'] as $f): ?>
          <tr><td><?= e($f['quien'] !== '' ? $f['quien'] : '—') ?></td><td><?= e($f['como']) ?></td>
            <td><?= $f['en'] ? e(Verificacion::fechaHora($f['en'])) : '<span class="tit-chip rev">Pendiente</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>

    <h3 style="margin-top:18px">Contenido del documento</h3>
    <p class="texto-secundario">Compare con el documento impreso: debe decir lo mismo.</p>
    <div style="border:1px solid var(--borde, #ccc);border-radius:8px;padding:14px 16px;line-height:1.5">
      <?php foreach ($r['contenido'] as $c): ?>
        <?php if ($c[0] === 't'): ?>
          <p style="text-align:center;font-weight:700;margin:8px 0"><?= e($c[1]) ?></p>
        <?php elseif ($c[0] === 'd'): ?>
          <div style="display:flex;gap:10px;border-bottom:1px dotted #bbb;padding:3px 0"><span style="flex:0 0 38%" class="texto-secundario"><?= e($c[1]) ?></span><strong style="flex:1"><?= e($c[2]) ?></strong></div>
        <?php else: ?>
          <p style="margin:6px 0"><?= e($c[1]) ?></p>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
