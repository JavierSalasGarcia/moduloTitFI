<div class="card" style="max-width: 800px; margin: 30px auto; padding: 24px;">
  <h1 style="color: #1e293b; font-size: 24px; font-weight: 700; margin-top: 0;"><?= e($invitacion['asunto']) ?></h1>
  <p class="texto-secundario" style="margin-bottom: 20px;">Reunión #<?= (int) $invitacion['id'] ?> · Rango: <?= e($invitacion['rango_inicio']) ?> a <?= e($invitacion['rango_fin']) ?></p>
  
  <?php if ($invitacion['fecha_confirmada']): ?>
    <!-- Banner de confirmación y descarga de ICS -->
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 18px 24px; display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-bottom: 24px;">
      <div style="display: flex; align-items: center; gap: 16px;">
        <div style="background: #22c55e; color: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold; flex-shrink: 0; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);">✓</div>
        <div>
          <h4 style="margin: 0 0 4px 0; color: #166534; font-size: 17px; font-weight: 700;">Reunión Confirmada</h4>
          <p style="margin: 0; color: #1e3a8a; font-weight: 600; font-size: 14px;">
            Fecha: <?= date('d/m/Y', strtotime($invitacion['fecha_confirmada'])) ?> 
            · Horario: <?= date('H:i', strtotime($invitacion['fecha_confirmada'] . ' ' . $invitacion['hora_confirmada_inicio'])) ?> a <?= date('H:i', strtotime($invitacion['fecha_confirmada'] . ' ' . $invitacion['hora_confirmada_fin'])) ?>
          </p>
        </div>
      <a class="btn btn-oro" href="<?= url('reuniones/invitacion/' . rawurlencode($token) . '/calendario.ics') ?>" style="background: #856e0d; color: #ffffff; border-color: #6e5d0d;">📅 Descargar calendario ICS</a>
    </div>
  <?php endif; ?>

  <?php if ($invitacion['descripcion']): ?><p style="margin-bottom: 20px; color: #475569;"><?= nl2br(e($invitacion['descripcion'])) ?></p><?php endif; ?>
  
  <?php if ($invitacion['estado'] !== 'fecha_confirmada' && $invitacion['estado'] !== 'finalizada'): ?>
    <h3 style="margin-top: 24px; color: #334155;">Disponibilidad (Franjas horarias propuestas)</h3>
    <p class="texto-secundario" style="margin-bottom: 16px;">
      Haz clic en una franja para alternar tu disponibilidad (borde azul significa que marcaste Disponible). 
      Las celdas <span style="background: #22c55e; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold;">verdes</span> indican que todos coinciden.
    </p>

    <?php
    // Agrupar horarios por fecha
    $horariosPorDia = [];
    foreach ($invitacion['horarios'] as $h) {
        $fecha = date('Y-m-d', strtotime($h['inicio']));
        $horariosPorDia[$fecha][] = $h;
    }
    ksort($horariosPorDia);
    $totalInvitados = (int) $invitacion['participantes'];
    ?>

    <div style="display: flex; gap: 16px; overflow-x: auto; padding-bottom: 12px; margin-bottom: 24px;">
    <?php foreach ($horariosPorDia as $fecha => $slots): ?>
      <div style="flex: 1; min-width: 160px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column;">
        <div style="background: #e2e8f0; padding: 8px; text-align: center; font-weight: bold; font-size: 13px; color: #334155;">
          <?= date('d M Y', strtotime($fecha)) ?>
        </div>
        <div style="padding: 10px; display: flex; flex-direction: column; gap: 8px; flex: 1;">
          <?php foreach ($slots as $h): ?>
            <?php
              $votos = (int) $h['disponibles'] + 1; // Sumamos voto implícito del organizador
              $coincidenciaTotal = ($votos >= $totalInvitados);
              $miDisp = ($h['mi_estado'] === 'disponible');
              
              // Definir colores
              $bg = '#fff';
              $color = '#475569';
              $border = '1px solid #cbd5e1';
              
              if ($coincidenciaTotal) {
                  $bg = '#22c55e';
                  $color = '#fff';
                  $border = '1px solid #16a34a';
              } elseif ($miDisp) {
                  $border = '2px solid #2563eb';
              }
              
              // Verificar si esta franja es la confirmada
              $esConfirmada = ($invitacion['estado'] === 'fecha_confirmada' && 
                               $invitacion['fecha_confirmada'] === $fecha && 
                               date('H:i:s', strtotime($h['inicio'])) === $invitacion['hora_confirmada_inicio']);
              if ($esConfirmada) {
                  $bg = '#1e3a8a';
                  $color = '#fff';
                  $border = '2px solid #1d4ed8';
              }
            ?>
            
            <?php if (in_array($invitacion['estado'], ['finalizada', 'cancelada', 'fecha_confirmada'], true)): ?>
              <div style="background: <?= $bg ?>; color: <?= $color ?>; border: <?= $border ?>; padding: 10px 8px; text-align: center; border-radius: 6px; font-size: 12px; font-weight: 500;">
                <?= date('H:i', strtotime($h['inicio'])) ?> - <?= date('H:i', strtotime($h['fin'])) ?> (<?= $votos ?>)
              </div>
            <?php else: ?>
              <form method="post" action="<?= url('reuniones/invitacion/' . rawurlencode($token) . '/disponibilidad') ?>" style="margin: 0;">
                <input type="hidden" name="horario_id" value="<?= (int) $h['id'] ?>">
                <input type="hidden" name="estado" value="<?= $miDisp ? 'no_disponible' : 'disponible' ?>">
                <button type="submit" style="width: 100%; background: <?= $bg ?>; color: <?= $color ?>; border: <?= $border ?>; padding: 10px 8px; text-align: center; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.15s ease;">
                  <?= date('H:i', strtotime($h['inicio'])) ?> - <?= date('H:i', strtotime($h['fin'])) ?> (<?= $votos ?>)
                </button>
              </form>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <h2 style="color: #334155; margin-top: 30px;">Chat de Coordinación</h2>
  <div style="max-height: 300px; overflow-y: auto; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 20px;">
    <?php if (empty($invitacion['mensajes'])): ?>
      <p class="texto-secundario" style="text-align: center;">No hay mensajes en esta reunión.</p>
    <?php else: ?>
      <?php foreach ($invitacion['mensajes'] as $mensaje): ?>
        <p style="margin-bottom: 8px; font-size: 14px;">
          <strong><?= e($mensaje['nombre'] ?: $mensaje['correo']) ?>:</strong> 
          <?= nl2br(e($mensaje['contenido'])) ?>
        </p>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php if (!in_array($invitacion['estado'], ['finalizada', 'cancelada'], true)): ?>
    <form method="post" action="<?= url('reuniones/invitacion/' . rawurlencode($token) . '/mensaje') ?>" style="display: flex; flex-direction: column; gap: 10px;">
      <textarea name="contenido" required rows="3" placeholder="Escribe un mensaje en el expediente..." style="padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; outline: none;"></textarea>
      <label style="font-weight: 600; color: #475569; display: flex; align-items: center; gap: 6px;">
        <input type="checkbox" name="urgente" value="1"> Marcar como mensaje urgente
      </label>
      <button class="btn btn-primario" type="submit" style="align-self: flex-end; padding: 8px 16px;">Enviar mensaje</button>
    </form>
  <?php endif; ?>
</div>
