<?php
/** @var array $filas @var string $orden @var string $dir @var bool $recortado @var string $q @var ?int $anio @var int[] $anios @var array $alcance @var bool $puedeSolicitar */
include __DIR__ . '/_estilos.php';
// Encabezado ordenable: al volver a pulsar la columna activa se invierte el sentido; fechas empiezan por la más reciente.
$enlace = static function (string $clave, string $texto) use ($orden, $dir, $q, $anio): string {
    $sigue = $orden === $clave ? ($dir === 'asc' ? 'desc' : 'asc') : ($clave === 'depto' ? 'asc' : 'desc');
    $consulta = http_build_query(array_filter(['q' => $q, 'anio' => $anio, 'orden' => $clave, 'dir' => $sigue], static fn($v) => $v !== '' && $v !== null));
    $flecha = $orden === $clave ? ($dir === 'asc' ? ' ▲' : ' ▼') : ' ⇅';
    return '<a href="' . e(url('constancias?' . $consulta)) . '" title="Ordenar por ' . e(mb_strtolower($texto)) . '"' . ($orden === $clave ? ' aria-current="true"' : '') . '>' . e($texto) . $flecha . '</a>';
};
$fecha = static fn(string $f): string => $f !== '' ? date('d/m/Y', strtotime($f)) : '—';
?>
<div class="const-pila">
  <?php if (!$filas): ?>
    <section class="tarjeta const-vacio">
      <h2>Aún no hay constancias firmadas</h2>
      <p>Cuando se firme una constancia que se te extendió, aparecerá aquí.</p>
    </section>
  <?php else: ?>
  <section class="tarjeta">
    <div class="const-scroll"><table class="const-lista const-tabla">
      <thead><tr>
        <th>Constancia</th>
        <th><?= $enlace('depto', 'Departamento') ?></th>
        <th><?= $enlace('emision', 'Emisión') ?></th>
        <th><?= $enlace('firma', 'Firma') ?></th>
        <th>Folio</th>
      </tr></thead>
      <tbody>
      <?php foreach ($filas as $f): ?>
        <tr>
          <td><a href="<?= e(url('constancias/descargar/' . $f['fuente'] . '/' . $f['id'])) ?>" target="_blank" rel="noopener" title="Descargar el PDF"><strong><?= e($f['tipo']) ?></strong></a><br><small><?= e($f['detalle']) ?></small></td>
          <td data-et="Departamento"><?= e($f['departamento']) ?></td>
          <td data-et="Emisión"><?= e($fecha((string) $f['fecha_emision'])) ?></td>
          <td data-et="Firma"><?= e($fecha((string) $f['fecha_firma'])) ?></td>
          <td data-et="Folio"><?= e($f['folio']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>
</div>
