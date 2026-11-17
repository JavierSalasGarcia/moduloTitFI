<?php
/** @var array $lista */
include __DIR__ . '/_estilos.php';
$pendientes = array_filter($lista, static fn(array $s): bool => $s['estado'] === 'en_firma');
$firmadas = array_filter($lista, static fn(array $s): bool => $s['estado'] === 'firmada');
?>
<div class="const-pila">
  <section class="tarjeta">
    <h2 style="margin-top:0;font-size:18px">Esperan tu firma</h2>
    <?php if (!$pendientes): ?>
      <p class="const-frase">No tienes constancias por firmar. Cuando alguien te envíe una, te avisaremos por correo y aparecerá aquí.</p>
    <?php else: ?>
      <table class="const-lista">
        <thead><tr><th>Se extiende a</th><th>Solicitó</th><th>Folio</th><th>Enviada</th><th><span class="sr-only">Revisar</span></th></tr></thead>
        <tbody>
        <?php foreach ($pendientes as $s): ?>
          <tr>
            <td><strong><?= e($s['persona_nombre'] !== '' ? $s['persona_nombre'] : $s['asunto']) ?></strong><br><small><?= e($s['departamento']) ?></small></td>
            <td data-et="Solicitó"><?= e($s['solicitante_nombre']) ?></td>
            <td data-et="Folio"><?= e($s['folio']) ?></td>
            <td data-et="Enviada"><?= e(fecha_corta((string) $s['enviada_en'])) ?></td>
            <td class="const-acc"><a class="btn btn-mini btn-primario" href="<?= e(url('constancias/firmar/' . (int) $s['id'])) ?>">Revisar y firmar</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
  <?php if ($firmadas): ?>
  <section class="tarjeta">
    <h2 style="margin-top:0;font-size:16px">Firmadas recientemente</h2>
    <table class="const-lista">
      <thead><tr><th>Se extiende a</th><th>Folio</th><th>Firmada</th><th><span class="sr-only">PDF</span></th></tr></thead>
      <tbody>
      <?php foreach ($firmadas as $s): ?>
        <tr>
          <td><strong><?= e($s['persona_nombre'] !== '' ? $s['persona_nombre'] : $s['asunto']) ?></strong></td>
          <td data-et="Folio"><?= e($s['folio']) ?></td>
          <td data-et="Firmada"><?= e(fecha_corta((string) $s['firmada_en'])) ?></td>
          <td class="const-acc"><a class="btn btn-mini" href="<?= e(url('constancias/solicitud/' . (int) $s['id'] . '/pdf')) ?>" target="_blank" rel="noopener">PDF</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
  <?php endif; ?>
</div>
