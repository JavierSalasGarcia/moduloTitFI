<?php include __DIR__ . '/_estilos.php'; ?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Protocolos que te asignaron para revisión. Escribe tus recomendaciones de forma y de fondo y firma tu dictamen antes de la fecha límite.</p>
</div></div>
<?php if (!$asignaciones): ?><div class="ux-empty">No tienes protocolos asignados.</div><?php else: ?>
<section class="tarjeta">
<div class="tabla-contenedor"><table class="tabla">
  <thead><tr><th>Protocolo</th><th>Licenciatura</th><th>Fecha límite</th><th>Semáforo</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($asignaciones as $a): ?>
    <tr>
      <td><strong><?= e($a['folio']) ?></strong> · <?= e($a['alumno_nombre']) ?><br><span class="texto-secundario">«<?= e(mb_strimwidth((string) $a['titulo'], 0, 80, '…')) ?>» (<?= (int) $a['numero'] ?>ª revisión)</span></td>
      <td><?= e($carreras[$a['carrera']]['nombre'] ?? $a['carrera']) ?></td>
      <td><?= e(date('d/m/Y', strtotime((string) $a['fecha_limite']))) ?></td>
      <td><?php include __DIR__ . '/_semaforo.php'; ?></td>
      <td><a class="btn btn-mini <?= $a['estado'] === 'firmada' ? '' : 'btn-primario' ?>" href="<?= url('titulacion/revision/' . (int) $a['id']) ?>"><?= $a['estado'] === 'firmada' ? 'Ver' : ($a['estado'] === 'borrador' ? 'Continuar' : 'Evaluar') ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
</section>
<?php endif; ?>
