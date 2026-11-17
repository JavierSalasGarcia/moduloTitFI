<?php
/**
 * Qué pasará con un trámite al dejarlo: borrarse (nadie más intervino) o quedar en pausa (con los motivos).
 * Recibe $x = ['t' => trámite, 'plan' => Abandono::plan(), 'nombre' => nombre de la modalidad] y $propio (true: «tu trámite»).
 */
$pt = $x['t'];
$pp = $x['plan'];
?>
<?php if ($pp['accion'] === 'borrar'): ?>
  <p class="tit-lead">Nadie más ha intervenido en tu trámite <strong>«<?= e($x['nombre']) ?>»</strong> (folio <?= e((string) $pt['folio']) ?>), así que <strong>se borrará definitivamente</strong>. No se podrá recuperar.</p>
  <h3>Se borrará</h3>
  <ul><?php foreach ($pp['borra'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?></ul>
<?php else: ?>
  <p class="tit-lead">Tu trámite <strong>«<?= e($x['nombre']) ?>»</strong> (folio <?= e((string) $pt['folio']) ?>) <strong>no se puede borrar</strong> porque otras personas ya intervinieron en él. <strong>Pasará a «Abandonadas»</strong>, en pausa, y podrás retomarlo justo donde se quedó.</p>
  <h3>Por qué no se puede borrar</h3>
  <ul><?php foreach ($pp['motivos'] as $mo): ?><li><?= e($mo) ?></li><?php endforeach; ?></ul>
  <h3>Qué se conserva</h3>
  <ul><?php foreach ($pp['conserva'] as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<?php if ($pp['examen']): ?>
  <p class="tit-banner" style="margin-top:14px"><strong>Ya tienes fecha de examen</strong>Tienes programado <?= e($pp['examen']['texto']) ?>. <?= $pp['examen']['convocado'] ? 'El sínodo ya fue convocado.' : 'Ya apartaste el espacio y el Coordinador de Docencia recibió tu propuesta de sínodo.' ?> <?= $pp['accion'] === 'borrar' ? 'Al borrar el trámite se libera el espacio.' : 'Avisaremos al Departamento de Evaluación Profesional para que lo atienda.' ?></p>
<?php endif; ?>
