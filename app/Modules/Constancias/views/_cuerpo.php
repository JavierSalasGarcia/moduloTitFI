<?php
/** Vista previa en HTML del cuerpo (bloques de Services\Cuerpo). @var array $bloques @var string $dirigido */
?>
<div class="const-hoja" aria-label="Texto de la constancia">
  <?php $lineas = array_values(array_filter(array_map('trim', explode("\n", (string) $dirigido)), static fn(string $l): bool => $l !== '')); ?>
  <p class="const-dest"><?php foreach ($lineas as $k => $l): ?><?= $k === 0 ? '<strong>' . e($l) . '</strong>' : '<br>' . e($l) ?><?php endforeach; ?><?php if (count($lineas) > 1 || !preg_match('/^a quien corresponda:?$/iu', $lineas[0] ?? '')): ?><br><strong>P R E S E N T E</strong><?php endif; ?></p>
  <?php foreach ($bloques as $b): ?>
    <?php if ($b['t'] === 'p'): ?>
      <p class="<?= e($b['alinear']) ?>"><?php foreach ($b['runs'] as [$t, $n]): ?><?= $n ? '<strong>' . e($t) . '</strong>' : e($t) ?><?php endforeach; ?></p>
    <?php else: ?>
      <div class="const-tabla-envoltura"><table>
        <?php foreach ($b['filas'] as $k => $fila): ?>
          <tr><?php foreach ($fila as $c): $th = $k === 0 && $b['encabezado']; ?><<?= $th ? 'th' : 'td' ?>><?= $c['negrita'] && !$th ? '<strong>' . e($c['texto']) . '</strong>' : e($c['texto']) ?></<?= $th ? 'th' : 'td' ?>><?php endforeach; ?></tr>
        <?php endforeach; ?>
      </table></div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
