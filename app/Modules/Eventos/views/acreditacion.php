<?php
use App\Modules\Eventos\Clasificacion;
$siglas = array_column($programas, 'siglas', 'id');
$qs = http_build_query(array_filter($f));
?>
<p class="texto-secundario">Eventos de formación integral con sus asistentes validados y la bitácora de evidencias, por carrera, para los organismos acreditadores.</p>
<form method="get" action="<?= url('eventos/acreditacion') ?>" class="card ux-toolbar" style="margin-bottom:14px;flex-wrap:wrap">
  <label>Carrera<select name="programa"><option value="">Todas</option><?php foreach ($programas as $p): ?><option value="<?= (int) $p['id'] ?>" <?= $f['programa'] === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['siglas'] . ' · ' . $p['nombre']) ?></option><?php endforeach; ?></select></label>
  <label>Desde<input type="date" name="desde" value="<?= e($f['desde']) ?>"></label>
  <label>Hasta<input type="date" name="hasta" value="<?= e($f['hasta']) ?>"></label>
  <button class="btn btn-primario" type="submit">Consultar</button>
  <a class="btn" href="<?= url_archivo('eventos/acreditacion/excel') ?>?<?= e($qs) ?>">Descargar Excel</a>
</form>
<section class="card" style="margin-bottom:14px">
  <h3 style="margin-top:0"><?= count($r['eventos']) ?> evento(s) · <?= count($r['asistentes']) ?> asistencia(s) validada(s) · <?= count($r['evidencias']) ?> evidencia(s)</h3>
  <?php if (!$r['eventos']): ?><div class="ux-empty">Sin eventos en el periodo.</div><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Fecha</th><th>Evento</th><th>Tema</th><th>Dirigido a</th><th>Horas</th><th>Asistentes</th><th>Evidencias</th><th>Post</th><th>Publicación de evidencia</th></tr></thead>
    <tbody><?php foreach ($r['eventos'] as $e): $c = $r['conteo'][(int) $e['id']] ?? []; ?>
      <tr><td><?= e(date('d/m/Y', strtotime((string) $e['inicio']))) ?></td><td><a href="<?= url('eventos/' . (int) $e['id']) ?>"><?= e($e['titulo']) ?></a><br><small class="texto-suave"><?= e($categorias[$e['categoria']] ?? '') ?></small></td>
        <td><?= e(Clasificacion::TEMAS[$e['tema']] ?? '') ?></td><td><small><?= e(Clasificacion::destino($e, $siglas)) ?></small></td><td><?= $e['horas'] ? e((string) (float) $e['horas']) : '' ?></td>
        <td><?= (int) ($c['asistentes'] ?? 0) ?></td><td><?= (int) ($c['evidencias'] ?? 0) ?></td>
        <td><?= $e['post_url'] ? '<a href="' . e($e['post_url']) . '" target="_blank" rel="noopener noreferrer">Ver</a>' : '<span class="badge badge-oro">Falta</span>' ?></td>
        <td><?= !empty($e['evidencia_post_url']) ? '<a href="' . e($e['evidencia_post_url']) . '" target="_blank" rel="noopener noreferrer">Ver</a>' : '<span class="texto-suave">—</span>' ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php if ($r['ranking']): ?>
<section class="card"><h3 style="margin-top:0">Quienes más participan (<?= e(Clasificacion::periodo($f['hasta'])) ?>)</h3>
  <p class="ayuda-form">Para el reconocimiento por carrera y la publicación en redes.</p>
  <ol><?php foreach ($r['ranking'] as $x): ?><li><?= e($x['nombre']) ?> · <?= e((string) $x['siglas']) ?> · <strong><?= (int) $x['puntos'] ?> puntos</strong> (<?= (int) $x['eventos'] ?> eventos)</li><?php endforeach; ?></ol>
</section>
<?php endif; ?>
