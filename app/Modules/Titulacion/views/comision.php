<?php
include __DIR__ . '/_estilos.php';
$estados = ['en_comision' => 'Por enviar a revisión', 'en_evaluacion' => 'En revisión', 'por_dictaminar' => 'Revisiones completas', 'dictaminada' => 'Resuelta'];
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Protocolos de tu comité (<?= e(implode(', ', array_map(static fn(string $c): string => $carreras[$c]['nombre'] ?? $c, $misCarreras))) ?>). Envía cada uno a sus revisores, vigila los plazos con el semáforo y, cuando todos terminen, revisa la compilación de observaciones y envía el resultado al pasante.</p>
</div></div>

<section class="tarjeta" style="margin-bottom:16px" aria-label="Estado de las revisiones">
  <h2 style="font-size:16px">Estado de las revisiones</h2>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:6px">
    <div><span class="sem verde" aria-hidden="true"></span><strong><?= (int) $resumen['verde'] ?></strong> pendientes en plazo</div>
    <div><span class="sem rojo" aria-hidden="true"></span><strong><?= (int) $resumen['rojo'] ?></strong> pendientes con plazo vencido</div>
    <div><span class="sem listo" aria-hidden="true"></span><strong><?= (int) $resumen['listo'] ?></strong> revisiones firmadas</div>
    <div><strong><?= (int) $resumen['por_enviar'] ?></strong> protocolo(s) por enviar</div>
    <div><strong><?= (int) $resumen['por_dictaminar'] ?></strong> listo(s) para resolver</div>
  </div>
</section>

<?php if (!$rondas): ?><div class="ux-empty">No hay protocolos en tu comité por ahora.</div><?php endif; ?>
<?php foreach ($rondas as $r): ?>
<section class="tarjeta" style="margin-bottom:14px">
  <div class="tarjeta-cabecera">
    <div>
      <h2 style="font-size:16px"><a href="<?= url('titulacion/comision/' . (int) $r['id']) ?>"><?= e($r['folio']) ?></a> · <?= e($r['alumno_nombre']) ?> <small class="texto-secundario">(<?= e(\App\Modules\Titulacion\Services\Mensajes::ordinal((int) $r['numero'])) ?> vuelta)</small></h2>
      <p class="texto-secundario" style="margin:0">«<?= e(mb_strimwidth((string) $r['titulo'], 0, 110, '…')) ?>»</p>
    </div>
    <span class="tit-chip <?= $r['estado'] === 'en_comision' ? 'pend' : ($r['estado'] === 'dictaminada' ? 'ok' : 'rev') ?>"><?= e($estados[$r['estado']] ?? $r['estado']) ?><?= $r['estado'] === 'en_comision' && (int) $r['numero'] > 1 ? ' (versión corregida)' : '' ?></span>
  </div>
  <?php if ($r['asignaciones']): ?>
    <ul style="list-style:none;padding:0;margin:6px 0 0;display:flex;flex-wrap:wrap;gap:6px 22px">
      <?php $n = 0; foreach ($r['asignaciones'] as $a): if ($a['estado'] === 'cancelada') continue; $n++; ?>
        <li style="font-size:13px"><strong>Revisor <?= $n ?></strong> (<?= e($a['miembro_nombre']) ?>) · <?php include __DIR__ . '/_semaforo.php'; ?></li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <p style="margin:10px 0 0"><a class="btn btn-mini btn-primario" href="<?= url('titulacion/comision/' . (int) $r['id']) ?>"><?= $r['estado'] === 'en_comision' ? ((int) $r['numero'] > 1 ? 'Revisar corrección' : 'Enviar a revisores') : ($r['estado'] === 'por_dictaminar' ? 'Ver observaciones y responder' : 'Abrir') ?></a></p>
</section>
<?php endforeach; ?>
