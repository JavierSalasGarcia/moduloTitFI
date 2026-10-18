<div class="barra-acciones" style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
  <div class="grupo-chips" role="tablist" aria-label="Secciones de reuniones">
    <a class="btn btn-mini<?= ($pestana === 'convocar') ? ' btn-primario' : '' ?>" href="<?= url('reuniones?tab=convocar') ?>">Mis reuniones</a>
    <a class="btn btn-mini<?= ($pestana === 'disponibilidad') ? ' btn-primario' : '' ?>" href="<?= url('reuniones?tab=disponibilidad') ?>">Responder disponibilidad</a>
  </div>
  <a class="btn btn-primario" href="<?= url('reuniones/nueva') ?>">+ Convocar reunión</a>
  <a class="btn" href="<?= e(url('reuniones/minutas/nueva')) ?>">+ Compartir minuta de reunión externa</a>
</div>

<?php if ($pestana === 'disponibilidad'): ?>
  <div class="card">
    <h2 style="margin-top:0;">Disponibilidad pendiente</h2>
    <?php if (!$pendientesDisponibilidad): ?><p class="ux-empty">Estás al día. No tienes invitaciones pendientes de respuesta.</p><?php endif; ?>
    <?php foreach ($pendientesDisponibilidad as $pendiente): ?>
      <article class="card">
        <h3><?= e($pendiente['asunto']) ?></h3>
        <p>Organiza: <?= e($pendiente['organizador'] ?? 'Tu área') ?> · <?= e($pendiente['rango_inicio']) ?> a <?= e($pendiente['rango_fin']) ?></p>
        <a class="btn btn-primario" href="<?= url('reuniones/' . $pendiente['id']) ?>">Elegir horarios</a>
      </article>
    <?php endforeach; ?>
  </div>
<?php else: ?>
  <div class="card">
    <h2 style="margin-top:0;">Mis reuniones</h2>
    <form method="get" action="<?= url('reuniones') ?>" style="display:flex;gap:8px;flex-wrap:wrap;margin:12px 0;">
      <input type="hidden" name="tab" value="convocar"><input type="search" name="q" value="<?= e($busqueda) ?>" placeholder="Buscar asunto o chat...">
      <select name="categoria"><option value="0">Todas las categorías</option><?php foreach ($categorias as $categoria): ?><option value="<?= (int) $categoria['id'] ?>"<?= $categoriaSeleccionada === (int) $categoria['id'] ? ' selected' : '' ?>><?= e($categoria['nombre']) ?></option><?php endforeach; ?></select>
      <button class="btn" type="submit">Buscar</button>
    </form>
    <details class="ux-disclosure"><summary>Organizar categorías</summary><form method="post" action="<?= url('reuniones/categorias') ?>" class="ux-toolbar"><input type="hidden" name="_csrf" value="<?= e(App\Core\Auth::csrfToken()) ?>"><label>Nombre de la categoría<input name="nombre" maxlength="80" required></label><button class="btn" type="submit">Crear categoría</button></form></details>
    <?php if (empty($reuniones)): ?>
      <p class="texto-secundario">Todavía no tienes reuniones convocadas o asignadas.</p>
    <?php else: ?>
      <div style="display:grid;gap:10px;">
        <?php foreach ($reuniones as $reunion): ?>
          <?php
            $bColor = '#ef4444'; // rojo por defecto
            if (in_array($reunion['estado'], ['fecha_confirmada', 'finalizada'], true)) {
                $bColor = '#10b981'; // verde
            } elseif (in_array($reunion['estado'], ['convocada', 'en_respuesta'], true) && (int) $reunion['pendientes'] === 0) {
                $bColor = '#f59e0b'; // amarillo/ámbar
            }
            
            // Check if user has notifications/pending on this specific meeting
            $hasNotification = (int) \App\Core\Database::valor(
                'SELECT COUNT(*) FROM reuniones_participantes rp
                  WHERE rp.reunion_id = ? AND rp.usuario_id = ? AND rp.estado_invitacion <> \'revocada\'
                    AND (rp.respondio_en IS NULL OR ? > rp.respondio_en)',
                [(int) $reunion['id'], \App\Core\Auth::id(), $reunion['actualizado_en']]
            ) > 0;
          ?>
          <article style="border:1px solid var(--gris-200); border-left: 6px solid <?= $bColor ?>; border-radius:var(--radio); padding:14px; background: #fff;">
            <div style="display: flex; align-items: center; gap: 14px;">
              <?php if ($hasNotification): ?>
                <div style="background: var(--error); color: #fff; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; box-shadow: 0 4px 6px -1px rgba(185, 28, 28, 0.2);" title="Tienes notificaciones pendientes">🔔</div>
              <?php endif; ?>
              
              <div style="flex: 1;">
                <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;">
                  <strong><a href="<?= url('reuniones/' . $reunion['id']) ?>"><?= e($reunion['asunto']) ?></a></strong>
                  <a class="btn btn-mini" href="<?= e(url('reuniones/minutas/nueva?reunion='.$reunion['id'])) ?>">Compartir minuta</a>
                  <span class="chip"><?= e(\App\Core\InterfaceUi::status($reunion['estado'])) ?></span>
                </div>
                <div class="texto-secundario" style="margin-top:7px; display: flex; gap: 12px; flex-wrap: wrap; font-size: 13px;">
                  <?php if (in_array($reunion['estado'], ['fecha_confirmada', 'finalizada'], true) && $reunion['fecha_confirmada']): ?>
                    <span>ID <?= (int) $reunion['id'] ?> · 📅 Fecha: <?= date('d/m/Y', strtotime($reunion['fecha_confirmada'])) ?> · ⏰ Horario: <?= date('H:i', strtotime($reunion['fecha_confirmada'] . ' ' . $reunion['hora_confirmada_inicio'])) ?> a <?= date('H:i', strtotime($reunion['fecha_confirmada'] . ' ' . $reunion['hora_confirmada_fin'])) ?></span>
                  <?php else: ?>
                    <span>ID <?= (int) $reunion['id'] ?> · <?= e($reunion['rango_inicio']) ?> a <?= e($reunion['rango_fin']) ?></span>
                    <span>• Respondieron: <?= (int)$reunion['contestaron'] ?>/<?= (int)$reunion['participantes'] ?></span>
                    <span>• Mejor horario: <?= (int)$reunion['max_coincidencias'] + 1 ?> personas disponibles</span>
                  <?php endif; ?>
                  <span>• <?= (int) $reunion['mensajes'] ?> mensajes</span>
                </div>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php $token=''; require __DIR__.'/minutas.php'; ?>
