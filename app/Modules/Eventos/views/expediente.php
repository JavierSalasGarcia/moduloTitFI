<?php
use App\Modules\Eventos\Asistencias;
$actual = $resumen[$periodo] ?? ['puntos' => 0, 'horas' => 0.0, 'eventos' => 0, 'nivel' => ''];
$sig = Asistencias::siguiente((int) $actual['puntos']);
$estados = ['validada' => ['Validada', 'badge-verde'], 'pendiente' => ['En revisión', 'badge-oro'], 'rechazada' => ['No validada', '']];
$u = \App\Core\Auth::usuario();
?>
<style>
.exp-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px}.exp-kpi{padding:14px}.exp-kpi strong{display:block;font-size:24px;color:var(--verde-900)}
.exp-barra{height:8px;background:var(--gris-100);border-radius:6px;overflow:hidden;margin-top:6px}.exp-barra span{display:block;height:100%;background:var(--verde-700)}
.exp-solo-impresion{display:none}
@media(max-width:760px){.exp-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media print{.menu-lateral,.barra-superior,.no-imprimir,nav.migas{display:none!important}.exp-solo-impresion{display:block}.card{box-shadow:none;border:1px solid #ccc}}
</style>
<div class="exp-solo-impresion"><h2>Expediente de formación integral · Facultad de Ingeniería, UAEMEX</h2><p><?= e((string) ($u['nombre'] ?? '')) ?> · <?= e((string) ($u['programa'] ?? '')) ?> · Emitido el <?= e(date('d/m/Y')) ?>. Cada folio puede verificarse en <?= e(rtrim((string) \App\Core\App::config('app.url_publica', ''), '/')) ?>/verificar/asistencia/FOLIO</p></div>
<p class="texto-secundario no-imprimir">Tus asistencias a conferencias, talleres y actividades de la Facultad. Cada una tiene un folio verificable que puedes mostrar a tus profesores o poner en tu CV.</p>
<div class="exp-kpis">
  <section class="card exp-kpi">Puntos <?= e($periodo) ?><strong><?= (int) $actual['puntos'] ?></strong></section>
  <section class="card exp-kpi">Nivel<strong><?= e($actual['nivel'] ?: '—') ?></strong>
    <?php if ($sig): ?><small class="texto-suave">Te faltan <?= (int) $sig['faltan'] ?> para <?= e($sig['nivel']) ?></small><div class="exp-barra"><span style="width:<?= min(100, (int) round((int) $actual['puntos'] / ((int) $actual['puntos'] + (int) $sig['faltan']) * 100)) ?>%"></span></div><?php endif; ?></section>
  <section class="card exp-kpi">Horas de formación<strong><?= e((string) (float) $actual['horas']) ?></strong></section>
  <section class="card exp-kpi">Eventos validados<strong><?= (int) $actual['eventos'] ?></strong></section>
</div>
<p class="ayuda-form no-imprimir">Niveles por periodo: <?php foreach (array_reverse(Asistencias::NIVELES, true) as $min => $n): ?><?= e($n) ?> (<?= $min ?>+) · <?php endforeach; ?>quienes más participan en cada carrera reciben un reconocimiento de la Facultad y se publican en sus redes.</p>
<section class="card">
  <div class="tarjeta-cabecera"><h3 style="margin:0">Asistencias</h3><button class="btn btn-mini no-imprimir" type="button" onclick="window.print()">Imprimir o guardar en PDF</button></div>
  <?php if (!$asistencias): ?><div class="ux-empty">Aún no tienes asistencias. Busca en <a href="<?= url('eventos') ?>">Avisos y eventos</a> los que dicen «+ puntos».</div><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Fecha</th><th>Evento</th><th>Tema</th><th>Horas</th><th>Puntos</th><th>Estado</th><th>Folio</th></tr></thead>
    <tbody><?php foreach ($asistencias as $a): [$et, $cl] = $estados[$a['estado']]; ?>
      <tr><td><?= e(date('d/m/Y', strtotime((string) $a['inicio']))) ?></td><td><a href="<?= url('eventos/' . (int) $a['evento_id']) ?>"><?= e($a['titulo']) ?></a><?= $a['organizador'] ? '<br><small class="texto-suave">' . e($a['organizador']) . '</small>' : '' ?></td>
        <td><?= e($temas[$a['tema']] ?? '') ?></td><td><?= $a['horas'] ? e((string) (float) $a['horas']) : '' ?></td><td><?= $a['estado'] === 'validada' ? (int) $a['puntos'] : '' ?></td>
        <td><span class="badge <?= $cl ?>"><?= $et ?></span><?= $a['estado'] === 'rechazada' && $a['motivo'] ? '<br><small>' . e($a['motivo']) . '</small>' : '' ?></td>
        <td><a href="<?= url('verificar/asistencia/' . $a['folio']) ?>" target="_blank"><?= e($a['folio']) ?></a></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php if (count($resumen) > 1): ?>
<section class="card" style="margin-top:14px"><h3 style="margin-top:0">Periodos anteriores</h3>
  <p><?php foreach ($resumen as $p => $r): if ($p === $periodo) { continue; } ?><?= e($p) ?>: <strong><?= (int) $r['puntos'] ?> puntos</strong> (<?= e($r['nivel']) ?>, <?= e((string) (float) $r['horas']) ?> h) · <?php endforeach; ?></p>
</section>
<?php endif; ?>
