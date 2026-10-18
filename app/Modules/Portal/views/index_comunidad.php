<?php use App\Core\Auth; $u = Auth::usuario(); ?>
<p class="saludo">Hola, <strong><?= e($u['nombre']) ?></strong><?= $u['programa'] ? ' · ' . e($u['programa_siglas']) . ' — ' . e($u['programa']) : '' ?>.</p>

<div class="rejilla-stats">
  <a class="stat" href="<?= url('comunicados') ?>">
    <span class="stat-icono oro"><?= icono('entrada', 20) ?></span>
    <span class="stat-cifra"><?= (int) $stats['comunicados'] ?></span>
    <span class="stat-etiqueta">Comunicados (14 días)</span>
  </a>
  <a class="stat" href="<?= url('tickets') ?>">
    <span class="stat-icono verde"><?= icono('lista', 20) ?></span>
    <span class="stat-cifra"><?= (int) $stats['mis_tickets'] ?></span>
    <span class="stat-etiqueta">Mis tickets activos</span>
  </a>
  <a class="stat" href="<?= url('encuestas') ?>">
    <span class="stat-icono verde"><?= icono('ok', 20) ?></span>
    <span class="stat-cifra"><?= (int) $stats['encuestas'] ?></span>
    <span class="stat-etiqueta">Encuestas abiertas</span>
  </a>
  <a class="stat" href="<?= url('foro') ?>">
    <span class="stat-icono oro"><?= icono('usuarios', 20) ?></span>
    <span class="stat-cifra"><?= (int) $stats['peticiones'] ?></span>
    <span class="stat-etiqueta">Publicaciones en el foro</span>
  </a>
  <a class="stat" href="<?= url('teams') ?>">
    <span class="stat-icono verde"><?= icono('portafolio', 20) ?></span>
    <span class="stat-cifra"><?= (int) $stats['teams_sitios'] ?></span>
    <span class="stat-etiqueta">Explorar Teams</span>
  </a>
  <a class="stat" href="<?= url('reuniones') ?>">
    <span class="stat-icono verde"><?= icono('alerta', 20) ?></span>
    <span class="stat-cifra"><?= (int) $stats['reuniones_notificaciones'] ?></span>
    <span class="stat-etiqueta">Notificaciones Reuniones</span>
  </a>
</div>

<div class="rejilla-2col">
  <section class="tarjeta">
    <header class="tarjeta-cabecera">
      <h2>Comunicados para ti</h2>
      <a class="enlace" href="<?= url('comunicados') ?>">Ver todos</a>
    </header>
    <?php if (!$comunicados): ?>
      <p class="texto-suave">No hay comunicados recientes.</p>
    <?php else: ?>
      <ul class="lista-canales">
        <?php foreach ($comunicados as $c): ?>
          <li>
            <strong><?= e($c['titulo']) ?></strong>
            <small class="texto-suave"><?= e($c['depto']) ?> · <?= e(fecha_corta($c['creado_en'])) ?></small>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>

  <section class="tarjeta">
    <header class="tarjeta-cabecera">
      <h2>Mis tickets</h2>
      <a class="btn btn-mini btn-primario" href="<?= url('tickets/nuevo') ?>">Nuevo ticket</a>
    </header>
    <?php if (!$tickets): ?>
      <p class="texto-suave">No has levantado tickets. Usa «Nuevo ticket» para iniciar un trámite o reporte.</p>
    <?php else: ?>
      <ul class="lista-solicitudes">
        <?php foreach ($tickets as $t): ?>
          <li>
            <a href="<?= url('tickets/' . $t['id']) ?>" class="fila-solicitud">
              <span class="chip estado-<?= e($t['estado']) ?>"><?= e(str_replace('_', ' ', $t['estado'])) ?></span>
              <span class="fila-cuerpo">
                <strong><?= e($t['asunto']) ?></strong>
                <small class="texto-suave">Atiende: <?= e($t['destino']) ?> · <?= e($t['folio']) ?></small>
              </span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
