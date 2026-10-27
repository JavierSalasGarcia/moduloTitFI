<?php
$consulta = fn(array $extra = []) => http_build_query(array_filter(array_merge($filtros, $extra), fn($v) => $v !== '' && $v !== 0));
?>
<div class="barra-acciones">
  <p class="texto-suave">Memorándums, oficios y circulares que tu coordinación ha enviado a otras dependencias, profesores o alumnos.</p>
  <a class="btn btn-primario" href="<?= url('oficios/nuevo') ?>"><?= icono('mas', 15) ?> Archivar oficio</a>
</div>

<form method="get" class="tarjeta formulario" style="max-width:1000px">
  <label>Buscar por folio, asunto o destinatario
    <input type="search" name="q" value="<?= e($filtros['q']) ?>" maxlength="120" placeholder="Ej. SA/002/FI/2026, salas de cómputo, profesores">
  </label>
  <details <?= array_filter([$filtros['tipo'], $filtros['desde'], $filtros['hasta'], $filtros['departamento']]) ? 'open' : '' ?>>
    <summary>Filtros</summary>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,210px),1fr));gap:14px;margin-block:16px">
      <?php if ($esAdmin): ?>
        <label>Coordinación o departamento
          <select name="departamento"><option value="">Todos</option>
            <?php foreach ($departamentos as $d): ?><option value="<?= (int) $d['id'] ?>" <?= (int) $d['id'] === $filtros['departamento'] ? 'selected' : '' ?>><?= e($d['nombre']) ?></option><?php endforeach; ?>
          </select>
        </label>
      <?php endif; ?>
      <label>Tipo
        <select name="tipo"><option value="">Todos</option>
          <?php foreach ($tipos as $clave => $nombre): ?><option value="<?= e($clave) ?>" <?= $clave === $filtros['tipo'] ? 'selected' : '' ?>><?= e($nombre) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Desde<input type="date" name="desde" value="<?= e($filtros['desde']) ?>"></label>
      <label>Hasta<input type="date" name="hasta" value="<?= e($filtros['hasta']) ?>"></label>
    </div>
  </details>
  <div><button class="btn btn-primario">Buscar</button> <a href="<?= url('oficios') ?>">Limpiar filtros</a></div>
</form>

<?php if (!$filas): ?>
  <div class="tarjeta"><p class="texto-suave">No hay oficios archivados con estos criterios.</p></div>
<?php else: ?>
  <div class="tarjeta sin-relleno">
    <table class="tabla">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Asunto</th>
          <th class="oculta-movil">Destinatarios</th>
          <?php if ($esAdmin): ?><th class="oculta-movil">Coordinación</th><?php endif; ?>
          <th>Fecha</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($filas as $f): ?>
          <tr class="fila-enlace" data-href="<?= url('oficios/' . $f['id']) ?>">
            <td class="monoespacio"><?= e($f['folio']) ?><br><span class="texto-suave"><?= e($tipos[$f['tipo']] ?? '') ?></span></td>
            <td><a href="<?= url('oficios/' . $f['id']) ?>"><?= e($f['asunto']) ?></a></td>
            <td class="oculta-movil"><?= e($f['destinatarios']) ?></td>
            <?php if ($esAdmin): ?><td class="oculta-movil"><?= e($f['departamento']) ?></td><?php endif; ?>
            <td class="texto-suave"><?= e(date('d/m/Y', strtotime($f['fecha']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <nav aria-label="Páginas de oficios">
    <?php if ($pagina > 1): ?><a class="btn" href="<?= url('oficios?' . $consulta(['pagina' => $pagina - 1])) ?>">Anterior</a><?php endif; ?>
    <span>Página <?= (int) $pagina ?></span>
    <?php if ($hayMas): ?><a class="btn" href="<?= url('oficios?' . $consulta(['pagina' => $pagina + 1])) ?>">Siguiente</a><?php endif; ?>
  </nav>
<?php endif; ?>
