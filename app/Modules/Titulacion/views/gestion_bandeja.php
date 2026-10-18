<?php
use App\Modules\Titulacion\Catalogo;
include __DIR__ . '/_estilos.php';
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario">Revisa los documentos que suben los pasantes, devuélvelos con observaciones o valídalos, y registra las fechas y oficios de cada etapa.</p>
  </div>
</div>

<div class="rejilla-stats" style="margin-bottom:18px">
  <div class="stat"><div class="stat-cifra"><?= (int) $resumen['por_validar'] ?></div><div class="stat-etiqueta">Documentos por validar</div></div>
  <div class="stat"><div class="stat-cifra"><?= (int) $resumen['abiertos'] ?></div><div class="stat-etiqueta">Trámites en proceso</div></div>
  <div class="stat"><div class="stat-cifra"><?= (int) $resumen['concluidos'] ?></div><div class="stat-etiqueta">Concluidos</div></div>
</div>

<section class="tarjeta">
  <form class="form-en-linea" method="get" action="<?= url('titulacion/gestion') ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;margin-bottom:12px">
    <label>Buscar <input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Folio, nombre, correo o título"></label>
    <label>Estado
      <select name="estado"><option value="">Todos</option>
        <?php foreach (Catalogo::estadosTramite() as $k => $v): ?><option value="<?= e($k) ?>" <?= $filtros['estado'] === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
      </select></label>
    <label>Opción
      <select name="modalidad"><option value="">Todas</option>
        <?php foreach ($modalidades as $k => $m): ?><option value="<?= e($k) ?>" <?= $filtros['modalidad'] === $k ? 'selected' : '' ?>><?= e($m['nombre']) ?></option><?php endforeach; ?>
      </select></label>
    <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="pendientes" value="1" <?= $filtros['pendientes'] ? 'checked' : '' ?>> Solo con documentos por validar</label>
    <button class="btn btn-primario" type="submit">Filtrar</button>
  </form>

  <?php if (!$filas): ?>
    <div class="ux-empty">No hay trámites con esos criterios.</div>
  <?php else: ?>
  <div class="tabla-contenedor">
  <table class="tabla">
    <thead><tr><th>Folio</th><th>Pasante</th><th>Opción</th><th>Etapa</th><th>Por validar</th><th>Responsable</th><th>Actualizado</th></tr></thead>
    <tbody>
    <?php foreach ($filas as $f): $m = $modalidades[$f['modalidad']] ?? ['nombre' => $f['modalidad']]; ?>
      <tr>
        <td><a href="<?= url('titulacion/gestion/' . (int) $f['id']) ?>"><?= e($f['folio']) ?></a></td>
        <td><?= e($f['alumno_nombre']) ?><br><span class="texto-secundario"><?= e($f['alumno_email']) ?></span></td>
        <td><?= e($m['nombre']) ?></td>
        <td><?= $f['estado'] === 'abierto' ? e($f['etapa_actual']) : '<span class="estado estado-' . e($f['estado']) . '">' . e(Catalogo::estadosTramite()[$f['estado']] ?? $f['estado']) . '</span>' ?></td>
        <td><?= (int) $f['por_validar'] > 0 ? '<span class="tit-chip rev">' . (int) $f['por_validar'] . '</span>' : '—' ?></td>
        <td><?= e((string) ($f['responsable_nombre'] ?? '—')) ?></td>
        <td><?= e(fecha_corta($f['actualizado_en'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php endif; ?>
</section>
