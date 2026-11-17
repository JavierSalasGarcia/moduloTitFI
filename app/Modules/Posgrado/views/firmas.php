<?php
use App\Core\Auth;
use App\Modules\Posgrado\Services\Firmas;

include __DIR__ . '/_estilos.php';
$pendientesPropias = array_filter($propias, static fn(array $f): bool => $f['estado'] === 'pendiente');
$hechasPropias = array_filter($propias, static fn(array $f): bool => $f['estado'] === 'firmada');
?>
<div class="pos-pila">
  <?php if ($veCoordinacion): ?>
  <section class="tarjeta">
    <h2 style="margin-top:0">Por firmar como Coordinación de Estudios Avanzados</h2>
    <?php if ($coord): ?>
      <p class="texto-secundario">Firmas como <strong><?= e($firmante) ?></strong>, <?= e($cargo) ?> (nombre y cargo de Posgrado → Configuración). Revisa cada PDF antes de firmar; al firmar se estampa tu sello en el bloque de firma de cada hoja.</p>
    <?php else: ?>
      <p class="pos-nota">Consulta: tu cuenta no firma por la Coordinación (solo firma la cuenta del correo configurado o quien tenga rol titular en la Coordinación de Estudios Avanzados).</p>
    <?php endif; ?>
    <?php if (!$pendientes): ?>
      <p class="texto-secundario">No hay documentos esperando la firma de la Coordinación.</p>
    <?php else: ?>
      <form method="post" action="<?= url('posgrado/firmas/coordinacion') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <div class="tabla-contenedor">
          <table class="tabla pos-tabla-firmas">
            <thead><tr><?php if ($coord): ?><th><span class="sr-only">Elegir</span></th><?php endif; ?><th>Documento</th><th>Egresado(a)</th><th>Folio</th><th>PDF</th></tr></thead>
            <tbody>
            <?php foreach ($pendientes as $p): ?>
              <tr>
                <?php if ($coord): ?><td><input type="checkbox" name="ids[]" value="<?= (int) $p['id'] ?>" id="fc-<?= (int) $p['id'] ?>" aria-label="Elegir <?= e(Firmas::nombreDocumento((string) $p['documento'])) ?> de <?= e((string) $p['alumno_nombre']) ?>"></td><?php endif; ?>
                <td><label for="fc-<?= (int) $p['id'] ?>" style="margin:0;font-weight:600"><?= e(Firmas::nombreDocumento((string) $p['documento'])) ?></label><br><small class="texto-secundario">Desde <?= e(fecha_corta((string) $p['creado_en'])) ?></small></td>
                <td><?= e((string) $p['alumno_nombre']) ?></td>
                <td><?= e((string) $p['folio']) ?></td>
                <td><a class="btn btn-mini" href="<?= url('posgrado/firmas/' . (int) $p['id'] . '/pdf') ?>" target="_blank" rel="noopener">Ver PDF</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($coord): ?>
          <p style="margin:12px 0 6px"><label class="pos-check"><input type="checkbox" id="pos-todos"> Elegir todos</label></p>
          <label class="pos-check" style="margin:4px 0 10px"><input type="checkbox" name="leido" value="1" required> Revisé los documentos seleccionados</label>
          <div><button class="btn btn-primario" type="submit">Firmar los seleccionados</button></div>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($propias || !$veCoordinacion): ?>
  <section class="tarjeta">
    <h2 style="margin-top:0">Firmas pedidas a tu correo</h2>
    <?php if (!$pendientesPropias): ?><p class="texto-secundario">No tienes firmas pendientes de evaluación de grado.</p><?php endif; ?>
    <ul class="pos-firmantes">
      <?php foreach ($pendientesPropias as $f): ?>
        <li><div><strong><?= e(Firmas::nombreDocumento((string) $f['documento'])) ?></strong> de <?= e((string) $f['alumno_nombre']) ?><br><small class="texto-secundario"><?= e(Firmas::etiquetaRol($f)) ?> · enviada el <?= e(date('d/m/Y', strtotime((string) $f['enviada_en']))) ?></small></div>
          <a class="btn btn-mini btn-primario" href="<?= url('posgrado/firma/mia/' . (int) $f['id']) ?>"><?= $f['documento'] === 'acuse' ? 'Ver y acusar de recibido' : 'Revisar y firmar' ?></a></li>
      <?php endforeach; ?>
    </ul>
    <?php if ($hechasPropias): ?>
      <details class="pos-grupo"><summary>Ya firmadas</summary>
        <ul class="pos-firmantes">
          <?php foreach ($hechasPropias as $f): ?>
            <li><div><?= e(Firmas::nombreDocumento((string) $f['documento'])) ?> de <?= e((string) $f['alumno_nombre']) ?><br><small class="texto-secundario">Firmada el <?= e(date('d/m/Y H:i', strtotime((string) $f['firmada_en']))) ?></small></div>
              <a class="btn btn-mini" href="<?= url('posgrado/firma/mia/' . (int) $f['id']) ?>">Ver</a></li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
  </section>
  <?php endif; ?>

  <?php if ($veCoordinacion && $firmadas): ?>
  <details class="pos-momento">
    <summary>Firmados recientemente por la Coordinación</summary>
    <div class="pos-momento-cuerpo">
      <?php foreach ($firmadas as $f): ?>
        <div class="pos-evento"><time><?= e(date('d/m/Y H:i', strtotime((string) $f['firmada_en']))) ?> · <?= e((string) ($f['firmo'] ?? '')) ?></time><?= e(Firmas::nombreDocumento((string) $f['documento'])) ?> · <?= e((string) $f['alumno_nombre']) ?> (<?= e((string) $f['folio']) ?>)</div>
      <?php endforeach; ?>
    </div>
  </details>
  <?php endif; ?>
  <p class="texto-secundario" style="font-size:13px"><?php foreach (\App\Modules\Posgrado\Services\Manuales::deLaSesion() as $manA => $manT): ?><a href="<?= url(\App\Modules\Posgrado\Services\Manuales::url($manA, 'consolidado')) ?>" download><?= e($manT) ?> (PDF)</a> · <?php endforeach; ?><a href="<?= url('posgrado/manuales') ?>">Todos los manuales</a></p>
</div>
<script>
(function () {
  var t = document.getElementById('pos-todos');
  if (!t) { return; }
  t.addEventListener('change', function () {
    document.querySelectorAll('input[name="ids[]"]').forEach(function (c) { c.checked = t.checked; });
  });
})();
</script>
