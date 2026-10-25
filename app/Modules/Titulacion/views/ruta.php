<?php
include __DIR__ . '/_estilos.php';
?>
<div class="tit-cabecera">
  <div>
    <h1 style="margin:0 0 4px"><?= e($modalidad['nombre'] ?? $t['modalidad']) ?></h1>
    <p class="texto-secundario" style="margin:0">ID del proceso <strong><?= e($t['folio']) ?></strong> · <?= e((string) ($t['datos']['datos']['nombre'] ?? $t['alumno_nombre'])) ?><?= $t['titulo'] ? ' · «' . e(mb_strimwidth((string) $t['titulo'], 0, 110, '…')) . '»' : '' ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if ($esDueno): ?><a class="btn" href="<?= url('titulacion/licenciatura/tramite/' . (int) $t['id']) ?>">Ir a mi trámite</a><?php endif; ?>
    <?php if ($esPersonal): ?>
      <a class="btn" href="<?= url('titulacion/gestion/' . (int) $t['id']) ?>">Abrir en validación</a>
      <a class="btn btn-primario" href="<?= url(\App\Modules\Titulacion\Services\Archivos::url('expediente', (int) $t['id'])) ?>">Descargar todo el expediente (ZIP)</a>
    <?php endif; ?>
  </div>
</div>
<section class="tarjeta">
  <h2>Ruta del trámite</h2>
  <p class="texto-secundario">Pasos palomeados: hechos. Resaltado: el paso actual. Atenuados: los que faltan. Descarga el documento de cada paso para seguimiento o auditoría.</p>
  <?php include __DIR__ . '/_ruta.php'; ?>
</section>
