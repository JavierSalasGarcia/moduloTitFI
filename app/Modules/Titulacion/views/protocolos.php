<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$estados = ['recibida' => 'Por turnar', 'en_comision' => 'En la comisión', 'en_evaluacion' => 'En evaluación', 'por_dictaminar' => 'Por dictaminar', 'dictaminada' => 'Dictaminada', 'cancelada' => 'Cancelada'];
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Protocolos que suben los pasantes. Turna cada uno a la comisión de su licenciatura; el encargado elige a los evaluadores. Aquí ves el semáforo de cada revisión y puedes enviar recordatorios.</p>
</div>
<div><a class="btn <?= $estado === 'activos' ? 'btn-primario' : '' ?>" href="<?= url('titulacion/protocolos') ?>">En trámite</a>
<a class="btn <?= $estado === 'recibida' ? 'btn-primario' : '' ?>" href="<?= url('titulacion/protocolos?estado=recibida') ?>">Por turnar</a>
<a class="btn <?= $estado === 'todos' ? 'btn-primario' : '' ?>" href="<?= url('titulacion/protocolos?estado=todos') ?>">Todos</a></div></div>

<?php if (!$rondas): ?><div class="ux-empty">No hay protocolos en esta vista.</div><?php endif; ?>
<?php foreach ($rondas as $r): $c = $carreras[$r['carrera']] ?? null; ?>
<section class="tarjeta" style="margin-bottom:14px">
  <div class="tarjeta-cabecera">
    <div>
      <h2 style="font-size:16px"><a href="<?= url('titulacion/gestion/' . (int) $r['tramite_id']) ?>"><?= e($r['folio']) ?></a> · <?= e($r['alumno_nombre']) ?> <small class="texto-secundario">(<?= (int) $r['numero'] ?>ª revisión)</small></h2>
      <p class="texto-secundario" style="margin:0">«<?= e(mb_strimwidth((string) $r['titulo'], 0, 110, '…')) ?>» · <?= e($modalidades[$r['modalidad']]['nombre'] ?? $r['modalidad']) ?> · recibido <?= e(fecha_corta($r['fecha_recepcion'])) ?></p>
    </div>
    <span class="tit-chip <?= $r['estado'] === 'recibida' ? 'pend' : ($r['estado'] === 'dictaminada' ? 'ok' : 'rev') ?>"><?= e($estados[$r['estado']] ?? $r['estado']) ?></span>
  </div>
  <?php if ($r['estado'] === 'recibida'): ?>
    <form class="formulario" method="post" action="<?= url('titulacion/protocolos/' . (int) $r['id'] . '/enviar') ?>" style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <label>Comisión
        <select name="carrera" required>
          <?php foreach ($carreras as $k => $cc): ?><option value="<?= e($k) ?>" <?= $r['carrera'] === $k ? 'selected' : '' ?>><?= e(implode('/', $cc['siglas'])) ?> · <?= e($cc['nombre']) ?></option><?php endforeach; ?>
        </select></label>
      <a class="btn btn-mini" href="<?= url('titulacion/protocolos/' . (int) $r['id'] . '/documento') ?>" target="_blank" rel="noopener">Ver protocolo (PDF)</a>
      <button class="btn btn-primario" type="submit">Turnar a la comisión</button>
    </form>
  <?php else: ?>
    <p class="texto-secundario" style="margin:4px 0">Comisión: <strong><?= e($c ? implode('/', $c['siglas']) : '—') ?></strong>
      <?php if ($r['total'] > 0): ?> · <?= (int) $r['firmadas'] ?> de <?= (int) $r['total'] ?> revisiones firmadas<?php endif; ?>
      <a href="<?= url('titulacion/protocolos/' . (int) $r['id'] . '/documento') ?>" target="_blank" rel="noopener" style="margin-left:8px">Ver protocolo</a></p>
    <?php if (!empty($r['asignaciones'])): ?>
    <div class="tabla-contenedor"><table class="tabla">
      <thead><tr><th>Evaluador</th><th>Límite</th><th>Semáforo</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($r['asignaciones'] as $a): if ($a['estado'] === 'cancelada') continue; ?>
        <tr><td><?= e($a['miembro_nombre']) ?><br><span class="texto-secundario"><?= e($a['miembro_email']) ?></span></td>
          <td><?= e(date('d/m/Y', strtotime((string) $a['fecha_limite']))) ?></td>
          <td><?php include __DIR__ . '/_semaforo.php'; ?></td>
          <td><?php if (in_array($a['estado'], ['pendiente', 'borrador'], true)): ?>
            <form method="post" action="<?= url('titulacion/protocolos/' . (int) $r['id'] . '/recordatorio/' . (int) $a['id']) ?>"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn btn-mini" type="submit">Enviar recordatorio</button></form>
            <?php endif; ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  <?php endif; ?>
</section>
<?php endforeach; ?>
