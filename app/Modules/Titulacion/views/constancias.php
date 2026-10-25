<?php
include __DIR__ . '/_estilos.php';
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Constancias de revisión de los protocolos de titulación en que participaste. Cada constancia se emite cuando el protocolo queda aprobado, sin importar en cuántas vueltas, y la firma la Subdirección Académica.</p>
</div></div>
<section class="tarjeta" style="margin-bottom:16px">
  <h2>Constancias</h2>
  <?php if (!$constancias): ?><div class="ux-empty">Aún no tienes constancias.</div>
  <?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Protocolo</th><th>Pasante</th><th>Vueltas</th><th>Constancia</th></tr></thead>
    <tbody>
    <?php foreach ($constancias as $c): ?>
      <tr>
        <td><?= e($c['folio']) ?><br><span class="texto-secundario">«<?= e(mb_strimwidth((string) $c['titulo'], 0, 90, '…')) ?>»</span></td>
        <td><?= e($c['alumno_nombre']) ?></td>
        <td><?= e(implode(', ', array_map(static fn(string $n): string => $n . 'ª', explode(',', (string) $c['vueltas'])))) ?></td>
        <td><?php if ($c['subdir_ruta']): ?><a class="btn btn-mini btn-primario" href="<?= url('titulacion/constancias/' . (int) $c['id']) ?>" target="_blank" rel="noopener">Descargar</a><?php else: ?><span class="tit-chip rev">En firma de la Subdirección</span><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php if ($enCurso): ?>
<section class="tarjeta">
  <h2>Revisiones en curso</h2>
  <p class="texto-secundario">Ya firmaste tu revisión de estos protocolos; su constancia aparecerá aquí cuando se aprueben.</p>
  <ul class="tit-lista-simple"><?php foreach ($enCurso as $x): ?><li><?= e($x['folio']) ?> · <?= e($x['alumno_nombre']) ?> · «<?= e(mb_strimwidth((string) $x['titulo'], 0, 90, '…')) ?>»</li><?php endforeach; ?></ul>
</section>
<?php endif; ?>
