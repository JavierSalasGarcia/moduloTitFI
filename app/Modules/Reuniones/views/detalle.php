<div class="barra-acciones"><a class="btn btn-mini" href="<?= url('reuniones') ?>">← Reuniones</a></div>
<p><a class="btn" href="<?= e(url('reuniones/minutas/nueva?reunion='.$reunion['id'])) ?>">Compartir minuta con los asistentes</a></p>
<div class="card" style="padding: 24px;">
  <h2 style="margin-top:0; color: #1e293b; font-size: 26px; font-weight: 700;"><?= e($reunion['asunto']) ?></h2>
  <p class="texto-secundario" style="margin-bottom: 20px;">Reunión <?= (int) $reunion['id'] ?> · <span class="chip"><?= e(\App\Core\InterfaceUi::status($reunion['estado'])) ?></span></p>
  <?php if (in_array($reunion['estado'], ['borrador','precargada'], true)): ?><p class="ux-helper">Siguiente paso: revisa los datos y pulsa «Enviar convocatoria» para invitar a las personas.</p><?php endif; ?>

  <!-- Barra de acciones destacada -->
  <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; padding: 12px 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; align-items: center;">
    <?php if ($reunion['fecha_confirmada']): ?>
      <a class="btn btn-oro" href="<?= url_archivo('reuniones/' . $reunion['id'] . '/calendario.ics') ?>" style="background: #856e0d; color: #ffffff; border-color: #6e5d0d;">📅 Descargar calendario ICS</a>
    <?php endif; ?>
    
    <?php if ($reunion['estado'] === 'finalizada'): ?>
      <a class="btn btn-primario" href="<?= url_archivo('reuniones/' . $reunion['id'] . '/respaldo') ?>">📦 Descargar expediente ZIP</a>
    <?php endif; ?>
    
    <?php if ((int) $reunion['organizador_id'] === \App\Core\Auth::id() && !in_array($reunion['estado'], ['finalizada', 'cancelada'], true)): ?>
      <form method="post" action="<?= url('reuniones/' . $reunion['id'] . '/finalizar') ?>" onsubmit="return confirm('¿Finalizar el asunto y bloquear nuevos mensajes y archivos?');" style="margin: 0;">
        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <button class="btn btn-peligro" type="submit">Finalizar reunión</button>
      </form>
    <?php endif; ?>

    <?php if ((int) $reunion['organizador_id'] === \App\Core\Auth::id() && in_array($reunion['estado'], ['borrador', 'precargada'], true)): ?>
      <form method="post" action="<?= url('reuniones/' . $reunion['id'] . '/convocar') ?>" style="margin: 0;">
        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <button class="btn btn-primario" type="submit">Enviar convocatoria</button>
      </form>
    <?php endif; ?>

    <?php if (!empty($categorias)): ?>
      <form method="post" action="<?= url('reuniones/' . $reunion['id'] . '/categoria') ?>" style="display:flex; gap:8px; align-items: center; margin: 0; margin-left: auto;">
        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <select name="categoria_id" required style="padding: 7px; border-radius: 6px; border: 1px solid #cbd5e1; outline: none; background: #fff; font-size: 13px;">
          <?php foreach ($categorias as $categoria): ?>
            <option value="<?= (int) $categoria['id'] ?>"><?= e($categoria['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 7px 12px; border-radius: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">Categorizar</button>
      </form>
    <?php endif; ?>
  </div>

  <!-- Banner de Reunión Confirmada -->
  <?php if ($reunion['estado'] === 'fecha_confirmada'): ?>
    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 18px 24px; display: flex; align-items: center; gap: 16px; margin-bottom: 24px;">
      <div style="background: #22c55e; color: #fff; width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 22px; font-weight: bold; flex-shrink: 0; box-shadow: 0 4px 6px -1px rgba(34, 197, 94, 0.2);">✓</div>
      <div>
        <h4 style="margin: 0 0 4px 0; color: #166534; font-size: 17px; font-weight: 700;">Reunión Confirmada</h4>
        <p style="margin: 0; color: #1e3a8a; font-weight: 600; font-size: 14px;">
          Fecha: <?= date('d/m/Y', strtotime($reunion['fecha_confirmada'])) ?> 
          · Horario: <?= date('H:i', strtotime($reunion['fecha_confirmada'] . ' ' . $reunion['hora_confirmada_inicio'])) ?> a <?= date('H:i', strtotime($reunion['fecha_confirmada'] . ' ' . $reunion['hora_confirmada_fin'])) ?>
        </p>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($reunion['descripcion']): ?><p style="margin-bottom: 24px; color: #475569; line-height: 1.5;"><?= nl2br(e($reunion['descripcion'])) ?></p><?php endif; ?>

  <?php if ($reunion['estado'] !== 'fecha_confirmada' && $reunion['estado'] !== 'finalizada'): ?>
    <h3>Disponibilidad (Franjas horarias propuestas)</h3>
    <p class="texto-secundario" style="margin-bottom: 12px;">
      <?php if ((int)$reunion['organizador_id'] === \App\Core\Auth::id() && !in_array($reunion['estado'], ['finalizada', 'cancelada', 'fecha_confirmada'], true)): ?>
        Haz clic en uno o más horarios para seleccionarlos (se marcarán con un borde verde grueso). Una vez que selecciones la franja o franjas deseadas, haz clic en el botón de confirmación que aparecerá arriba.
      <?php elseif ((int)$reunion['organizador_id'] === \App\Core\Auth::id()): ?>
        La reunión ya tiene un horario definitivo o está cerrada.
      <?php else: ?>
        Haz clic en una franja para alternar tu disponibilidad (borde azul significa que marcaste Disponible).
      <?php endif; ?>
      Las celdas <span style="background: #22c55e; color: #fff; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-weight: bold;">verdes</span> indican que todos coinciden.
    </p>

    <?php if ((int)$reunion['organizador_id'] === \App\Core\Auth::id() && !in_array($reunion['estado'], ['finalizada', 'cancelada', 'fecha_confirmada'], true)): ?>
      <form method="post" id="form-confirmar-horario" action="<?= url('reuniones/' . $reunion['id'] . '/confirmar') ?>" onsubmit="return confirm('¿Confirmar el rango de horarios seleccionado como definitivo?');" style="display: none; margin-bottom: 16px;">
        <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
        <div id="confirmar-slots-inputs"></div>
        <button type="submit" class="btn btn-primario" style="background: #22c55e; border-color: #16a34a; font-weight: bold; width: 100%; padding: 12px; border-radius: 8px; color: #fff; cursor: pointer;">
          ✓ Confirmar Horario Definitivo Seleccionado
        </button>
      </form>
    <?php endif; ?>

  <?php
  // Agrupar horarios por fecha
  $horariosPorDia = [];
  foreach ($reunion['horarios'] as $h) {
      $fecha = date('Y-m-d', strtotime($h['inicio']));
      $horariosPorDia[$fecha][] = $h;
  }
  ksort($horariosPorDia);
  $totalInvitados = (int) $reunion['participantes'];
  ?>

  <div style="display: flex; gap: 16px; overflow-x: auto; padding-bottom: 12px; margin-bottom: 20px;">
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
              $esConfirmada = ($reunion['estado'] === 'fecha_confirmada' && 
                               $reunion['fecha_confirmada'] === $fecha && 
                               date('H:i:s', strtotime($h['inicio'])) === $reunion['hora_confirmada_inicio']);
              if ($esConfirmada) {
                  $bg = '#1e3a8a';
                  $color = '#fff';
                  $border = '2px solid #1d4ed8';
              }
            ?>
            
            <?php if ((int)$reunion['organizador_id'] === \App\Core\Auth::id()): ?>
              <?php if (in_array($reunion['estado'], ['finalizada', 'cancelada'], true) || $reunion['estado'] === 'fecha_confirmada'): ?>
                <div style="background: <?= $bg ?>; color: <?= $color ?>; border: <?= $border ?>; padding: 10px 8px; text-align: center; border-radius: 6px; font-size: 12px; font-weight: 500;">
                  <?= date('H:i', strtotime($h['inicio'])) ?> - <?= date('H:i', strtotime($h['fin'])) ?> (<?= $votos ?>)
                </div>
              <?php else: ?>
                <button type="button" class="btn-slot-confirmar" data-id="<?= (int) $h['id'] ?>" style="width: 100%; background: <?= $bg ?>; color: <?= $color ?>; border: <?= $border ?>; padding: 10px 8px; text-align: center; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.15s ease; outline: none;">
                  <?= date('H:i', strtotime($h['inicio'])) ?> - <?= date('H:i', strtotime($h['fin'])) ?> (<?= $votos ?>)
                </button>
              <?php endif; ?>
            <?php else: ?>
              <?php if (in_array($reunion['estado'], ['finalizada', 'cancelada', 'fecha_confirmada'], true)): ?>
                <div style="background: <?= $bg ?>; color: <?= $color ?>; border: <?= $border ?>; padding: 10px 8px; text-align: center; border-radius: 6px; font-size: 12px; font-weight: 500;">
                  <?= date('H:i', strtotime($h['inicio'])) ?> - <?= date('H:i', strtotime($h['fin'])) ?> (<?= $votos ?>)
                </div>
              <?php else: ?>
                <form method="post" action="<?= url('reuniones/' . $reunion['id'] . '/disponibilidad') ?>" style="margin: 0;">
                  <input type="hidden" name="horario_id" value="<?= (int) $h['id'] ?>">
                  <input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>">
                  <input type="hidden" name="estado" value="<?= $miDisp ? 'no_disponible' : 'disponible' ?>">
                  <button type="submit" style="width: 100%; background: <?= $bg ?>; color: <?= $color ?>; border: <?= $border ?>; padding: 10px 8px; text-align: center; border-radius: 6px; font-size: 12px; font-weight: 500; cursor: pointer; transition: all 0.15s ease;">
                    <?= date('H:i', strtotime($h['inicio'])) ?> - <?= date('H:i', strtotime($h['fin'])) ?> (<?= $votos ?>)
                  </button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <script>
  (() => {
    const selectedSlots = new Set();
    const formConfirmar = document.getElementById('form-confirmar-horario');
    const inputsCont = document.getElementById('confirmar-slots-inputs');
    
    document.querySelectorAll('.btn-slot-confirmar').forEach(btn => {
      btn.addEventListener('click', () => {
        const id = btn.dataset.id;
        if (selectedSlots.has(id)) {
          selectedSlots.delete(id);
          btn.style.border = '1px solid #cbd5e1';
          btn.style.boxShadow = 'none';
        } else {
          selectedSlots.add(id);
          btn.style.border = '3px solid #22c55e';
          btn.style.boxShadow = '0 0 0 3px rgba(34, 197, 94, 0.2)';
        }
        
        // Actualizar formulario y botón
        if (selectedSlots.size > 0) {
          inputsCont.innerHTML = Array.from(selectedSlots).map(slotId => `<input type="hidden" name="horario_ids[]" value="${slotId}">`).join('');
          formConfirmar.style.display = 'block';
        } else {
          inputsCont.innerHTML = '';
          formConfirmar.style.display = 'none';
        }
      });
    });
  })();
  </script>
  <h3>Chat</h3>
  <?php if ($reunion['archivos']): ?><h3>Archivos</h3><?php foreach ($reunion['archivos'] as $archivo): ?><p><a href="<?= url_archivo('reuniones/' . $reunion['id'] . '/archivo/' . $archivo['id']) ?>"><?= e($archivo['nombre_original']) ?></a> · <?= e($archivo['tipo']) ?></p><?php endforeach; ?><?php endif; ?>
  <?php foreach ($reunion['mensajes'] as $mensaje): ?><p><strong><?= e($mensaje['nombre'] ?: $mensaje['correo']) ?>:</strong> <?= nl2br(e($mensaje['contenido'])) ?> <?php if ($mensaje['tipo'] === 'urgente'): ?><span class="chip">URGENTE</span><?php endif; ?></p><?php endforeach; ?>
  <?php if (!in_array($reunion['estado'], ['finalizada', 'cancelada'], true)): ?>
    <form method="post" action="<?= url('reuniones/' . $reunion['id'] . '/mensaje') ?>"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><textarea name="contenido" required rows="3" placeholder="Escribe un mensaje"></textarea><label style="font-weight: 500; font-size: 13px; color: #475569; display: flex; align-items: center; gap: 6px; margin: 8px 0;"><input type="checkbox" name="tipo" value="urgente"> Enviar como urgente (Notifica por email a los participantes con un enlace seguro de acceso; el texto del mensaje no va en el correo)</label><button class="btn btn-primario" type="submit">Enviar mensaje</button></form>
    <form method="post" enctype="multipart/form-data" action="<?= url('reuniones/' . $reunion['id'] . '/archivo') ?>"><input type="hidden" name="_csrf" value="<?= e(\App\Core\Auth::csrfToken()) ?>"><input type="file" name="archivo" required accept=".doc,.docx,.ppt,.pptx,.pdf"><button class="btn btn-mini" type="submit">Añadir archivo</button></form>
  <?php endif; ?>
</div>
