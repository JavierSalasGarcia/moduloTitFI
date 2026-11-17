<?php
/** @var ?array $r */
include __DIR__ . '/_estilos.php';
?>
<div class="const-verif">
  <section class="tarjeta">
    <?php if ($r === null): ?>
      <p class="const-sello no">No encontramos esta constancia</p>
      <p>El código no corresponde a ninguna constancia emitida por la Facultad de Ingeniería. Revisa que hayas escaneado el QR completo.</p>
    <?php else: ?>
      <p class="const-sello <?= $r['vigente'] ? 'si' : 'no' ?>"><?= $r['vigente'] ? 'Constancia válida' : 'Esta constancia no tiene validez' ?></p>
      <dl class="const-datos">
        <div><dt>Folio</dt><dd><?= e($r['folio'] !== '' ? $r['folio'] : '—') ?></dd></div>
        <div><dt>Estado</dt><dd><?= e($r['estado']) ?></dd></div>
        <div><dt>Asunto</dt><dd><?= e($r['asunto']) ?></dd></div>
        <div><dt>Se extiende a</dt><dd><?= e($r['persona'] !== '' ? $r['persona'] : '—') ?></dd></div>
        <div><dt>Firma</dt><dd><?= e($r['firmante']) ?><br><small style="font-weight:400"><?= e($r['cargo']) ?></small></dd></div>
        <div><dt>Emite</dt><dd><?= e($r['departamento']) ?>, Facultad de Ingeniería, UAEMéx</dd></div>
        <?php if ($r['fecha']): ?><div><dt>Firmada el</dt><dd><?= e(date('d/m/Y H:i', strtotime($r['fecha']))) ?> h</dd></div><?php endif; ?>
      </dl>
      <?php if ($r['huella']): ?><p class="const-ayuda">Huella SHA-256 del PDF firmado: <span class="const-huella"><?= e($r['huella']) ?></span></p><?php endif; ?>
      <?php if (\App\Modules\Titulacion\Services\SelloPdf::modoInterno() && $r['vigente']): ?><p class="const-ayuda">La firma es un registro interno de conformidad mientras los Consejos aprueban la firma electrónica.</p><?php endif; ?>
    <?php endif; ?>
  </section>
</div>
