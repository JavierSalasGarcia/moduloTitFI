<?php
/** @var array $grupos @var int $total @var bool $recortado @var string $q @var ?int $anio @var int[] $anios @var array $alcance @var bool $puedeSolicitar */
include __DIR__ . '/_estilos.php';
$filtrando = $q !== '' || $anio !== null;
$ve = $alcance['todas'] ? 'Ves todas las constancias de la Facultad, agrupadas por el departamento que las emitió.'
    : ($alcance['deptos'] ? 'Ves las constancias que recibiste y todas las que emitió tu departamento.' : 'Ves las constancias que se te extendieron, agrupadas por el departamento que las emitió.');
?>
<div class="const-pila">
  <section class="tarjeta">
    <div class="const-cabecera">
      <p class="const-frase"><?= e($ve) ?></p>
      <?php if ($puedeSolicitar): ?><a class="btn btn-primario" href="<?= e(url('constancias/solicitar')) ?>">Solicitar una constancia</a><?php endif; ?>
    </div>
    <form class="const-filtros" method="get" action="<?= e(url('constancias')) ?>" role="search" style="margin-top:12px">
      <label class="const-q">Buscar por nombre o folio
        <input type="search" name="q" value="<?= e($q) ?>" maxlength="80" placeholder="Ej. Pérez o CEA/CONST/004">
      </label>
      <label>Año
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

  <?php if (!$grupos): ?>
    <section class="tarjeta const-vacio">
      <?php if ($filtrando): ?>
        <h2>No encontramos constancias con esa búsqueda</h2>
        <p>Revisa cómo escribiste el nombre o el folio, o quita el filtro de año.</p>
      <?php else: ?>
        <h2>Aún no tienes constancias</h2>
        <p>Cuando la Facultad te extienda una constancia (por ejemplo, por participar en un comité de titulación o en un sínodo) aparecerá aquí, agrupada por el departamento que la emitió, lista para descargar.</p>
      <?php endif; ?>
    </section>
  <?php endif; ?>

  <?php $i = 0; foreach ($grupos as $depto => $filas): ?>
    <details class="const-depto"<?= ($i++ === 0 || $filtrando || count($grupos) <= 3) ? ' open' : '' ?>>
      <summary><span><?= e($depto) ?></span> <span class="const-cuenta"><?= count($filas) ?> <?= count($filas) === 1 ? 'constancia' : 'constancias' ?></span></summary>
      <div class="const-depto-cuerpo">
        <table class="const-lista">
          <thead><tr><th>Constancia</th><th>Se extiende a</th><th>Fecha</th><th>Folio</th><th>Estado</th><th><span class="sr-only">PDF</span></th></tr></thead>
          <tbody>
          <?php foreach ($filas as $f): ?>
            <tr>
              <td><strong><?= e($f['tipo']) ?></strong><br><small><?= e($f['detalle']) ?></small></td>
              <td data-et="Se extiende a"><?= e($f['persona'] !== '' ? $f['persona'] : '—') ?></td>
              <td data-et="Fecha"><?= e(date('d/m/Y', strtotime($f['fecha']))) ?></td>
              <td data-et="Folio"><?= e($f['folio']) ?></td>
              <td><span class="const-estado <?= e($f['estado']) ?>"><?= $f['estado'] === 'firmada' ? 'Firmada' : 'En firma' ?></span></td>
              <td class="const-acc"><?php if ($f['estado'] === 'firmada'): ?><a class="btn btn-mini" href="<?= e(url('constancias/descargar/' . $f['fuente'] . '/' . $f['id'])) ?>" target="_blank" rel="noopener">Descargar PDF</a><?php elseif ($f['fuente'] === 'constancias'): ?><a class="btn btn-mini" href="<?= e(url('constancias/solicitud/' . $f['id'])) ?>">Ver</a><?php endif; ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </details>
  <?php endforeach; ?>
</div>
