<?php use App\Core\Auth; ?>
<p><a class="enlace" href="<?= url($concluido ? 'eventos/biblioteca' : 'eventos') ?>"><?= icono('flecha-izq', 14) ?> Volver</a></p>

<section class="card" style="max-width:760px">
  <header class="tarjeta-cabecera columna-movil">
    <div>
      <h2><?= e($ev['titulo']) ?></h2>
      <small class="texto-suave">Publica: <?= e($ev['depto']) ?> · <?= e($ev['autor']) ?></small>
    </div>
    <div class="grupo-chips">
      <span class="badge badge-oro"><?= e($categorias[$ev['categoria']]) ?></span>
      <?php if ($concluido): ?><span class="chip estado-cerrada">concluido</span><?php endif; ?>
    </div>
  </header>

  <div class="ruta-flujo">
    <?= icono('reloj', 15) ?> <?= e(fecha_corta($ev['inicio'])) ?>
    <?php if ($ev['fin']): ?> — <?= e(fecha_corta($ev['fin'])) ?><?php endif; ?>
    <?php if ($ev['lugar']): ?> · <?= icono('edificio', 15) ?> <?= e($ev['lugar']) ?><?php endif; ?>
  </div>

  <p class="descripcion-solicitud"><?= nl2br(e($ev['descripcion'])) ?></p>

  <?php 
  $paras = array_filter($destinatarios, fn($d) => $d['tipo'] === 'para');
  $ccs = array_filter($destinatarios, fn($d) => $d['tipo'] === 'cc');
  ?>
  <?php if (!empty($paras)): ?>
    <div style="margin-top: 15px; border-top: 1px solid var(--gris-100); padding-top: 10px; margin-bottom: 10px;">
      <strong style="font-size: 12px; color: var(--texto); display: block; margin-bottom: 4px;">👥 Para (Información):</strong>
      <div style="display: flex; flex-wrap: wrap; gap: 6px;">
        <?php foreach ($paras as $d): ?>
          <span style="font-size: 11px; background: var(--verde-050); color: var(--verde-900); padding: 3px 8px; border-radius: 20px; border: 1px solid #d1fae5; font-weight: 500;">
            <?= $d['destinatario_type'] === 'departamento' ? '🏢' : '👤' ?> <?= e($d['destinatario_nombre']) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!empty($ccs)): ?>
    <div style="margin-top: 10px; margin-bottom: 15px;">
      <strong style="font-size: 12px; color: var(--texto); display: block; margin-bottom: 4px;">👥 CC (Con copia):</strong>
      <div style="display: flex; flex-wrap: wrap; gap: 6px;">
        <?php foreach ($ccs as $d): ?>
          <span style="font-size: 11px; background: #f1f5f9; color: #334155; padding: 3px 8px; border-radius: 20px; border: 1px solid #e2e8f0; font-weight: 500;">
            <?= $d['destinatario_type'] === 'departamento' ? '🏢' : '👤' ?> <?= e($d['destinatario_nombre']) ?>
          </span>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endif; ?>

  <?php if (!$concluido): ?>
    <div style="display: flex; gap: 8px; align-items: center; margin-top: 15px; flex-wrap: wrap;">
      <form method="post" action="<?= url('eventos/' . $ev['id'] . '/suscribir') ?>" style="margin: 0;">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="volver" value="eventos/<?= (int) $ev['id'] ?>">
        <button type="submit" class="btn <?= $ev['suscrito'] ? '' : 'btn-primario' ?>">
          <?= $ev['suscrito'] ? '✓ Quitar de mi calendario' : '+ Agregar a mi calendario' ?>
        </button>
      </form>

      <a href="<?= url('eventos/' . $ev['id'] . '/exportar-ics') ?>" class="btn" style="display: inline-flex; align-items: center; gap: 6px; border-color: var(--gris-200); background: #fafafa; color: var(--gris-700);">
        📅 Exportar a Teams / Outlook (.ics)
      </a>
    </div>
  <?php endif; ?>
</section>
