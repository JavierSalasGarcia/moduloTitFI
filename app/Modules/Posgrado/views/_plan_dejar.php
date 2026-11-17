<?php
/** Qué pasará con un trámite al dejarlo. Recibe $x = ['t' => trámite, 'plan' => Abandono::plan(), 'nombre' => modalidad]. */
$pt = $x['t'];
$pp = $x['plan'];
?>
<?php if ($pp['accion'] === 'borrar'): ?>
  <p class="pos-lead">Nadie más ha intervenido en tu trámite <strong><?= e((string) $pt['folio']) ?></strong> (<?= e($x['nombre']) ?>), así que <strong>se borrará definitivamente</strong>. No se podrá recuperar.</p>
  <h3>Se borrará</h3>
  <ul><?php foreach ($pp['borra'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
<?php else: ?>
  <p class="pos-lead">Tu trámite <strong><?= e((string) $pt['folio']) ?></strong> (<?= e($x['nombre']) ?>) <strong>no se puede borrar</strong> porque otras personas ya intervinieron en él. <strong>Pasará a «Abandonadas»</strong>, en pausa, y podrás retomarlo justo donde se quedó.</p>
  <h3>Por qué no se puede borrar</h3>
  <ul><?php foreach ($pp['motivos'] as $mo): ?><li><?= e($mo) ?></li><?php endforeach; ?></ul>
  <h3>Qué se conserva</h3>
  <ul><?php foreach ($pp['conserva'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<?php if ($pp['examen']): ?>
  <p class="pos-alerta" style="margin-top:14px"><strong>Ya tienes fecha de examen.</strong> Tienes programado <?= e($pp['examen']['texto']) ?> y el sínodo ya fue convocado. Avisaremos a Apoyo a Grado para que lo atienda.</p>
<?php endif; ?>
