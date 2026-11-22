<div class="barra-acciones">
  <nav class="pestanas" aria-label="Bandejas">
    <a class="pestana<?= $bandeja === 'firmar' ? ' activa' : '' ?>" href="<?= url('firmas?vista=' . $vistaFiltro) ?>">Por firmar</a>
    <a class="pestana<?= $bandeja === 'mias' ? ' activa' : '' ?>" href="<?= url('firmas?bandeja=mias&vista=' . $vistaFiltro) ?>">Mis solicitudes</a>
  </nav>
  <div class="grupo-chips">
    <a class="btn" href="<?= url('firmas/mi-firma') ?>"><?= icono('usuario', 15) ?> Mi firma</a>
    <a class="btn" href="<?= url('firmas/ayuda') ?>">Cómo funciona</a>
    <a class="btn" href="<?= url('firmas/ayuda#instalacion') ?>">Ayuda de instalación</a>
    <a class="btn" href="<?= url('firmas/certificado') ?>">Descargar certificado</a>
    <a class="btn" href="<?= url('firmas/grupos') ?>"><?= icono('usuarios', 15) ?> Grupos</a>
    <?php if ($puedeCrear): ?>
      <a class="btn btn-primario" href="<?= url('firmas/nuevo') ?>">Nueva solicitud</a>
    <?php endif; ?>
  </div>
</div>

<nav class="grupo-chips" aria-label="Filtro" style="margin-bottom:12px">
  <?php $base = 'firmas' . ($bandeja === 'mias' ? '?bandeja=mias&' : '?'); ?>
  <a class="chip<?= $vistaFiltro === 'activas' ? ' estado-en_atencion' : '' ?>" href="<?= url($base . 'vista=activas') ?>">Activas</a>
  <a class="chip<?= $vistaFiltro === 'terminadas' ? ' estado-en_atencion' : '' ?>" href="<?= url($base . 'vista=terminadas') ?>">Terminadas</a>
</nav>

<?php if (!empty($otros)): ?>
  <div class="card sin-relleno tabla-contenedor" style="margin-bottom:12px">
    <p class="texto-suave" style="margin:12px 16px 0"><strong>Constancias y trámites que esperan tu firma</strong> (de cualquier departamento).</p>
    <table class="tabla">
      <thead><tr><th>Origen</th><th>Qué firmar</th><th class="oculta-movil">Desde</th><th><span class="sr-only">Abrir</span></th></tr></thead>
      <tbody>
        <?php foreach ($otros as $o): ?>
          <tr class="fila-enlace" data-href="<?= url($o['enlace']) ?>">
            <td><span class="chip estado-abierto"><?= e($o['tipo']) ?></span><br><small class="texto-suave"><?= e($o['origen']) ?></small></td>
            <td><a href="<?= url($o['enlace']) ?>"><?= e($o['texto']) ?></a></td>
            <td class="oculta-movil texto-suave"><?= $o['fecha'] ? e(fecha_corta($o['fecha'])) : '—' ?></td>
            <td><a class="btn btn-mini" href="<?= url($o['enlace']) ?>">Firmar</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
<?php if (!$solicitudes): ?>
  <div class="card"><p class="texto-suave">
    <?= $bandeja === 'firmar' ? (empty($otros) ? 'No tienes documentos ni constancias por firmar.' : 'No tienes más solicitudes de firma de documentos en esta vista.') : 'No has creado solicitudes en esta vista.' ?>
  </p></div>
<?php else: ?>
  <div class="card sin-relleno tabla-contenedor">
    <table class="tabla">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Título</th>
          <?php if ($bandeja === 'firmar'): ?>
            <th class="oculta-movil">Solicita</th>
            <th>Mi parte</th>
          <?php else: ?>
            <th class="oculta-movil">Avance</th>
          <?php endif; ?>
          <th>Modo</th>
          <th>Estado</th>
          <th class="oculta-movil">Actualización</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($solicitudes as $s): ?>
          <tr class="fila-enlace" data-href="<?= url('firmas/' . $s['id']) ?>">
            <td class="monoespacio"><?= e($s['folio']) ?></td>
            <td><a href="<?= url('firmas/' . $s['id']) ?>"><?= e($s['titulo']) ?></a></td>
            <?php if ($bandeja === 'firmar'): ?>
              <td class="oculta-movil"><?= e($s['creador']) ?></td>
              <td>
                <?php if ($s['me_toca']): ?>
                  <span class="chip estado-abierto">te toca firmar</span>
                <?php else: ?>
                  <span class="chip estado-<?= $s['mi_estado'] === 'firmado' ? 'resuelto' : ($s['mi_estado'] === 'rechazado' ? 'cerrado' : 'pendiente') ?>"><?= e($s['mi_estado']) ?></span>
                <?php endif; ?>
              </td>
            <?php else: ?>
              <td class="oculta-movil texto-suave"><?= (int) $s['firmados'] ?>/<?= (int) $s['total_firmantes'] ?> firmas</td>
            <?php endif; ?>
            <td class="texto-suave"><?= e($s['modo']) ?></td>
            <td><span class="chip estado-<?= $s['estado'] === 'en_ruta' ? 'en_atencion' : ($s['estado'] === 'completada' ? 'resuelto' : 'cerrado') ?>"><?= e(str_replace('_', ' ', $s['estado'])) ?></span></td>
            <td class="oculta-movil texto-suave"><?= e(fecha_corta($s['actualizado_en'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
