<div class="barra-acciones">
  <nav class="pestanas" aria-label="Bandejas">
    <a class="pestana<?= $bandeja === 'recibidas' ? ' activa' : '' ?>" href="<?= url('solicitudes?bandeja=recibidas') ?>"><?= icono('entrada', 15) ?> Recibidas</a>
    <a class="pestana<?= $bandeja === 'enviadas' ? ' activa' : '' ?>" href="<?= url('solicitudes?bandeja=enviadas') ?>"><?= icono('enviar', 15) ?> Enviadas</a>
  </nav>
  <a class="btn btn-primario" href="<?= url('solicitudes/nueva') ?>"><?= icono('mas', 15) ?> Nueva solicitud</a>
</div>

<?php if (!$solicitudes): ?>
  <div class="tarjeta">
    <p class="texto-suave">No hay solicitudes <?= $bandeja === 'recibidas' ? 'recibidas de otros departamentos' : 'enviadas por tu departamento' ?>.</p>
  </div>
<?php else: ?>
  <div class="tarjeta sin-relleno">
    <table class="tabla">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Asunto</th>
          <th class="oculta-movil"><?= $bandeja === 'recibidas' ? 'Origen' : 'Destino' ?></th>
          <th class="oculta-movil">Prioridad</th>
          <th>Estado</th>
          <th class="oculta-movil">Actualización</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($solicitudes as $s): ?>
          <tr class="fila-enlace" data-href="<?= url('solicitudes/' . $s['id']) ?>">
            <td class="monoespacio"><?= e($s['folio']) ?></td>
            <td><a href="<?= url('solicitudes/' . $s['id']) ?>"><?= e($s['asunto']) ?></a></td>
            <td class="oculta-movil"><?= e($bandeja === 'recibidas' ? $s['origen'] : $s['destino']) ?></td>
            <td class="oculta-movil"><span class="chip prioridad-<?= e($s['prioridad']) ?>"><?= e($s['prioridad']) ?></span></td>
            <td><span class="chip estado-<?= e($s['estado']) ?>"><?= e(str_replace('_', ' ', $s['estado'])) ?></span></td>
            <td class="oculta-movil texto-suave"><?= e(fecha_corta($s['actualizado_en'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
