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
      <p class="texto-secundario" style="margin:0 0 8px">Las reuniones en gris no están activas: son borradores, canceladas o ya pasaron.</p>
      <div class="tabla-contenedor">
        <table class="tabla tabla-reuniones">
          <thead><tr><th>Reunión</th><th>Estado</th><th>Fecha / periodo</th><th class="oculta-movil">Respuestas</th><th class="oculta-movil">Mensajes</th><th><span class="sr-only">Minuta</span></th></tr></thead>
          <tbody>
          <?php foreach ($reuniones as $reunion): ?>
            <?php
              $confirmada = in_array($reunion['estado'], ['fecha_confirmada', 'finalizada'], true) && $reunion['fecha_confirmada'];
              // Inactiva: borrador o precargada, cancelada, finalizada o con fecha confirmada ya pasada.
              $inactiva = in_array($reunion['estado'], ['borrador', 'precargada', 'cancelada', 'finalizada'], true)
                  || ($reunion['fecha_confirmada'] && $reunion['fecha_confirmada'] < date('Y-m-d'))
                  || (!$reunion['fecha_confirmada'] && !empty($reunion['rango_fin']) && $reunion['rango_fin'] < date('Y-m-d'));
              $hasNotification = (int) \App\Core\Database::valor(
                  'SELECT COUNT(*) FROM reuniones_participantes rp
                    WHERE rp.reunion_id = ? AND rp.usuario_id = ? AND rp.estado_invitacion <> \'revocada\'
                      AND (rp.respondio_en IS NULL OR ? > rp.respondio_en)',
                  [(int) $reunion['id'], \App\Core\Auth::id(), $reunion['actualizado_en']]
              ) > 0;
            ?>
            <tr class="<?= $inactiva ? 'reunion-inactiva' : '' ?>">
              <td>
                <?php if ($hasNotification && !$inactiva): ?><span title="Tienes notificaciones pendientes" aria-label="Pendiente de tu respuesta">🔔</span> <?php endif; ?>
                <strong><a href="<?= url('reuniones/' . $reunion['id']) ?>"><?= e($reunion['asunto']) ?></a></strong>
                <br><small class="texto-secundario">ID <?= (int) $reunion['id'] ?></small>
              </td>
              <td><span class="chip"><?= e(\App\Core\InterfaceUi::status($reunion['estado'])) ?></span><?php if ($inactiva): ?> <small class="texto-secundario">(no activa)</small><?php endif; ?></td>
              <td>
                <?php if ($confirmada): ?>
                  <?= e(date('d/m/Y', strtotime($reunion['fecha_confirmada']))) ?><br><small><?= e(date('H:i', strtotime($reunion['fecha_confirmada'] . ' ' . $reunion['hora_confirmada_inicio']))) ?> h</small>
                <?php else: ?>
                  <?= e($reunion['rango_inicio']) ?> a <?= e($reunion['rango_fin']) ?>
                <?php endif; ?>
              </td>
              <td class="oculta-movil"><?= (int) $reunion['contestaron'] ?>/<?= (int) $reunion['participantes'] ?><?php if (!$confirmada && !$inactiva): ?><br><small>Mejor horario: <?= (int) $reunion['max_coincidencias'] + 1 ?> personas</small><?php endif; ?></td>
              <td class="oculta-movil"><?= (int) $reunion['mensajes'] ?></td>
              <td><a class="btn btn-mini" href="<?= e(url('reuniones/minutas/nueva?reunion=' . $reunion['id'])) ?>">Compartir minuta</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>
<?php $token=''; require __DIR__.'/minutas.php'; ?>
