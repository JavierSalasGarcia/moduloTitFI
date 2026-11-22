<?php
/** @var array $filas @var string $orden @var string $dir @var bool $recortado @var string $q @var ?int $anio @var int[] $anios @var array $alcance @var bool $puedeSolicitar */
include __DIR__ . '/_estilos.php';
$filtrando = $q !== '' || $anio !== null;
$ve = 'Aquí ves las constancias firmadas que se te extendieron a ti. No aparecen las que tú firmas o solicitas para otras personas.';
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
  <section class="tarjeta">
    <div class="const-cabecera">
      <p class="const-frase"><?= e($ve) ?></p>
      <?php if ($puedeSolicitar): ?><a class="btn btn-primario" href="<?= e(url('constancias/solicitar')) ?>">Solicitar una constancia</a><?php endif; ?>
    </div>
    <form class="const-filtros" method="get" action="<?= e(url('constancias')) ?>" role="search" style="margin-top:12px">
      <input type="hidden" name="orden" value="<?= e($orden) ?>"><input type="hidden" name="dir" value="<?= e($dir) ?>">
      <label class="const-q">Buscar por nombre o folio
        <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Ej. Pérez o CEA/CONST/004">
      </label>
      <label>Año de firma
        <select name="anio">
          <option value="">Todos</option>
          <?php foreach ($anios as $a): ?><option value="<?= $a ?>"<?= $anio === $a ? ' selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
        </select>
      </label>
      <button class="btn" type="submit">Buscar</button>
      <?php if ($filtrando): ?><a class="btn" href="<?= e(url('constancias')) ?>">Ver todas</a><?php endif; ?>
    </form>
    <?php if ($recortado): ?><p class="const-alcance">Hay muchas constancias: se muestran las más recientes de cada origen. Usa la búsqueda o el año para encontrar otras.</p><?php endif; ?>
  </section>

  <?php if (!$filas): ?>
    <section class="tarjeta const-vacio">
      <?php if ($filtrando): ?>
        <h2>No encontramos constancias con esa búsqueda</h2>
        <p>Revisa cómo escribiste el nombre o el folio, o quita el filtro de año.</p>
      <?php else: ?>
        <h2>Aún no hay constancias firmadas</h2>
        <p>Cuando se firme una constancia que se te extendió, aparecerá aquí lista para descargar.</p>
      <?php endif; ?>
    </section>
  <?php else: ?>
  <section class="tarjeta">
    <p class="const-ayuda" style="margin:0 0 8px"><?= count($filas) ?> <?= count($filas) === 1 ? 'constancia firmada' : 'constancias firmadas' ?>. Pulsa el título subrayado de <strong>Departamento</strong>, <strong>Emisión</strong> o <strong>Firma</strong> para ordenar (⇅); si pulsas otra vez, se invierte el orden.</p>
    <div class="const-scroll"><table class="const-lista const-tabla">
      <thead><tr>
        <th>Constancia</th><th>Se extiende a</th>
        <th><?= $enlace('depto', 'Departamento') ?></th>
        <th><?= $enlace('emision', 'Emisión') ?></th>
        <th><?= $enlace('firma', 'Firma') ?></th>
        <th>Folio</th><th><span class="sr-only">PDF</span></th>
      </tr></thead>
      <tbody>
      <?php foreach ($filas as $f): ?>
        <tr>
          <td><strong><?= e($f['tipo']) ?></strong><br><small><?= e($f['detalle']) ?></small></td>
          <td data-et="Se extiende a"><?= e($f['persona'] !== '' ? $f['persona'] : '—') ?></td>
          <td data-et="Departamento"><?= e($f['departamento']) ?></td>
          <td data-et="Emisión"><?= e($fecha((string) $f['fecha_emision'])) ?></td>
          <td data-et="Firma"><?= e($fecha((string) $f['fecha_firma'])) ?></td>
          <td data-et="Folio"><?= e($f['folio']) ?></td>
          <td class="const-acc"><a class="btn btn-mini" href="<?= e(url('constancias/descargar/' . $f['fuente'] . '/' . $f['id'])) ?>" target="_blank" rel="noopener">Descargar PDF</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <?php endif; ?>
</div>
