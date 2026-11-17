<?php
use App\Modules\Posgrado\Catalogo;

include __DIR__ . '/_estilos.php';
$momentos = Catalogo::momentos();
$enlace = static function (array $cambios) use ($filtros): string {
    $q = array_filter(array_merge(['q' => $filtros['q'], 'estado' => $filtros['estado'], 'momento' => $filtros['momento'] ?: '', 'nivel' => $filtros['nivel'], 'pendientes' => $filtros['pendientes'] ? '1' : ''], $cambios), static fn($v): bool => $v !== '' && $v !== null);
    return url('posgrado/gestion') . ($q ? '?' . http_build_query($q) : '');
};
?>
<div class="pos-cabecera">
  <p class="texto-secundario">Valida los documentos de los egresados, coteja originales, programa exámenes y emite oficios y constancias.</p>
  <span><?php if (\App\Modules\Posgrado\PosgradoAccess::tieneFirmas()): ?><a class="btn btn-mini" href="<?= url('posgrado/firmas') ?>">Firmas</a> <?php endif; ?><a class="btn btn-mini" href="<?= url('posgrado/configuracion') ?>"><?= icono('engrane', 16) ?> Configuración</a></span>
</div>

<div class="pos-contadores">
  <a class="pos-contador <?= $filtros['pendientes'] ? 'activo' : '' ?>" href="<?= e($enlace(['pendientes' => '1', 'momento' => '', 'estado' => ''])) ?>"><b><?= (int) $resumen['por_validar'] ?></b><span>Documentos por validar</span></a>
  <?php foreach ($momentos as $n => $m): ?>
    <a class="pos-contador <?= $filtros['momento'] === $n ? 'activo' : '' ?>" href="<?= e($enlace(['momento' => $n, 'pendientes' => '', 'estado' => ''])) ?>"><b><?= (int) $resumen['momentos'][$n] ?></b><span><?= (int) $n ?>. <?= e($m['breve']) ?></span></a>
  <?php endforeach; ?>
  <a class="pos-contador <?= $filtros['estado'] === 'concluido' ? 'activo' : '' ?>" href="<?= e($enlace(['estado' => 'concluido', 'momento' => '', 'pendientes' => ''])) ?>"><b><?= (int) $resumen['concluidos'] ?></b><span>Concluidos</span></a>
</div>

<section class="tarjeta">
  <form class="pos-filtros" method="get" action="<?= url('posgrado/gestion') ?>">
    <label>Buscar<input type="search" name="q" value="<?= e($filtros['q']) ?>" placeholder="Folio, nombre, correo o título"></label>
    <label>Momento
      <select name="momento"><option value="">Todos</option><?php foreach ($momentos as $n => $m): ?><option value="<?= (int) $n ?>"<?= $filtros['momento'] === $n ? ' selected' : '' ?>><?= (int) $n ?>. <?= e($m['breve']) ?></option><?php endforeach; ?></select></label>
    <label>Estado
      <select name="estado"><option value="">Todos</option><?php foreach (Catalogo::estados() as $k => $v): ?><option value="<?= e($k) ?>"<?= $filtros['estado'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
    <label>Nivel
      <select name="nivel"><option value="">Todos</option><?php foreach (Catalogo::niveles() as $k => $v): ?><option value="<?= e($k) ?>"<?= $filtros['nivel'] === $k ? ' selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?></select></label>
    <label class="pos-check" style="flex:0 0 auto"><input type="checkbox" name="pendientes" value="1"<?= $filtros['pendientes'] ? ' checked' : '' ?>> Con documentos por validar</label>
    <button class="btn btn-primario" type="submit">Filtrar</button>
    <?php if ($filtros['q'] !== '' || $filtros['estado'] !== '' || $filtros['momento'] || $filtros['nivel'] !== '' || $filtros['pendientes']): ?><a class="btn" href="<?= url('posgrado/gestion') ?>">Quitar filtros</a><?php endif; ?>
  </form>

  <?php if (!$filas): ?>
    <p class="texto-secundario">No hay trámites con esos criterios.</p>
  <?php else: ?>
  <div class="tabla-contenedor">
    <table class="tabla">
      <thead><tr><th>Folio</th><th>Egresado</th><th>Nivel</th><th>Momento</th><th>Por validar</th><th>Atiende</th><th>Actualizado</th></tr></thead>
      <tbody>
      <?php foreach ($filas as $f): ?>
        <tr>
          <td><a href="<?= url('posgrado/gestion/' . (int) $f['id']) ?>"><?= e((string) $f['folio']) ?></a></td>
          <td><?= e((string) $f['alumno_nombre']) ?><br><span class="texto-secundario"><?= e((string) $f['alumno_email']) ?></span></td>
          <td><?= e(Catalogo::niveles()[$f['nivel']] ?? $f['nivel']) ?></td>
          <td><?= $f['estado'] === 'abierto'
              ? e((int) $f['momento'] . '. ' . ($momentos[(int) $f['momento']]['breve'] ?? '')) . ($f['resultado'] === 'aplazado' ? ' <span class="pos-estado pos-e-mal">aplazado</span>' : '')
              : '<span class="estado estado-' . e($f['estado'] === 'concluido' ? 'concluida' : $f['estado']) . '">' . e(Catalogo::estados()[$f['estado']] ?? $f['estado']) . '</span>' ?></td>
          <td><?= (int) $f['por_validar'] > 0 ? '<span class="pos-estado pos-e-rev">' . (int) $f['por_validar'] . '</span>' : '—' ?>
            <?= (int) $f['respuestas_no'] > 0 ? '<br><span class="pos-estado pos-e-mal">«No» en visto bueno</span>' : '' ?>
            <?= (int) $f['en_firma_coord'] > 0 ? '<br><span class="pos-estado pos-e-pend">' . (int) $f['en_firma_coord'] . ' en firma de la Coordinación</span>' : '' ?></td>
          <td><?= e((string) ($f['responsable_nombre'] ?? '—')) ?></td>
          <td><?= e(fecha_corta((string) $f['actualizado_en'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</section>
