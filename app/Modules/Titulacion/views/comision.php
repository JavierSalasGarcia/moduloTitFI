<?php
include __DIR__ . '/_estilos.php';
$estados = ['en_comision' => 'Por asignar evaluadores', 'en_evaluacion' => 'En evaluación', 'por_dictaminar' => 'Por dictaminar', 'dictaminada' => 'Dictaminada'];
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Protocolos turnados a tu comisión (<?= e(implode(', ', array_map(static fn(string $c): string => $carreras[$c]['nombre'] ?? $c, $misCarreras))) ?>). Elige a quiénes se envía cada uno, vigila los plazos con el semáforo y firma el dictamen cuando todas las revisiones estén firmadas.</p>
</div></div>
<?php if (!$rondas): ?><div class="ux-empty">No hay protocolos en tu comisión por ahora.</div><?php endif; ?>
<?php foreach ($rondas as $r): ?>
<section class="tarjeta" style="margin-bottom:14px">
  <div class="tarjeta-cabecera">
    <div>
      <h2 style="font-size:16px"><a href="<?= url('titulacion/comision/' . (int) $r['id']) ?>"><?= e($r['folio']) ?></a> · <?= e($r['alumno_nombre']) ?> <small class="texto-secundario">(<?= (int) $r['numero'] ?>ª revisión)</small></h2>
      <p class="texto-secundario" style="margin:0">«<?= e(mb_strimwidth((string) $r['titulo'], 0, 110, '…')) ?>»</p>
    </div>
    <span class="tit-chip <?= $r['estado'] === 'en_comision' ? 'pend' : ($r['estado'] === 'dictaminada' ? 'ok' : 'rev') ?>"><?= e($estados[$r['estado']] ?? $r['estado']) ?></span>
  </div>
  <?php if ($r['asignaciones']): ?>
    <ul style="list-style:none;padding:0;margin:6px 0 0;display:flex;flex-wrap:wrap;gap:6px 22px">
      <?php foreach ($r['asignaciones'] as $a): if ($a['estado'] === 'cancelada') continue; ?>
        <li style="font-size:13px"><strong><?= e($a['miembro_nombre']) ?></strong> · <?php include __DIR__ . '/_semaforo.php'; ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p style="margin:10px 0 0"><a class="btn btn-mini btn-primario" href="<?= url('titulacion/comision/' . (int) $r['id']) ?>"><?= $r['estado'] === 'en_comision' ? 'Elegir evaluadores' : ($r['estado'] === 'por_dictaminar' ? 'Dictaminar' : 'Abrir') ?></a></p>
</section>
<?php endforeach; ?>
