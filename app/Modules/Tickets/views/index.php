<div class="barra-acciones">
  <nav class="pestanas" aria-label="Bandejas">
    <a class="pestana<?= $bandeja === 'mios' ? ' activa' : '' ?>" href="<?= url('tickets?vista=' . $vistaFiltro) ?>">Enviadas por mí</a>
    <?php if ($esStaff): ?>
      <a class="pestana<?= $bandeja === 'recibidos' ? ' activa' : '' ?>" href="<?= url('tickets?bandeja=recibidos&vista=' . $vistaFiltro) ?>">Por atender</a>
    <?php endif; ?>
    
  </nav>
  <a class="btn btn-primario" href="<?= url('tickets/nuevo') ?>">Nueva solicitud</a>
</div>

<details class="ux-disclosure"><summary>Organizar por prioridad</summary><a href="<?= url('tickets?vista=matriz') ?>">Abrir matriz de prioridades</a></details>
<nav class="grupo-chips" aria-label="Filtro" style="margin-bottom:12px">
  <?php $base = 'tickets?' . ($bandeja === 'recibidos' ? 'bandeja=recibidos&' : ''); ?>
  <a class="chip<?= $vistaFiltro === 'activos' ? ' estado-en_atencion' : '' ?>" href="<?= url($base . 'vista=activos') ?>">En seguimiento</a>
  <a class="chip<?= $vistaFiltro === 'cerrados' ? ' estado-en_atencion' : '' ?>" href="<?= url($base . 'vista=cerrados') ?>">Finalizadas</a>
</nav>

<?php if (!$tickets): ?>
  <div class="card"><p class="texto-suave">No hay tickets <?= $vistaFiltro === 'activos' ? 'activos' : 'cerrados' ?> en esta bandeja.</p></div>
<?php else: ?>
  <div class="card sin-relleno tabla-contenedor">
    <table class="tabla">
      <thead>
        <tr>
          <th>Folio</th>
          <th>Asunto</th>
          <th class="oculta-movil">Categoría</th>
          <th class="oculta-movil"><?= $bandeja === 'recibidos' ? 'Solicitante' : 'Atiende' ?></th>
          <th>Estado</th>
          <th class="oculta-movil">Mi cuadrante</th>
          <th class="oculta-movil">Actualización</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
          <tr class="fila-enlace" data-href="<?= url('tickets/' . $t['id']) ?>">
            <td class="monoespacio"><?= e($t['folio']) ?></td>
            <td><a href="<?= url('tickets/' . $t['id']) ?>"><?= e($t['asunto']) ?></a></td>
            <td class="oculta-movil"><?= e($t['categoria']) ?></td>
            <td class="oculta-movil"><?= e($t[$bandeja === 'recibidos' ? 'solicitante' : 'destino']) ?></td>
            <td><span class="chip estado-<?= e($t['estado']) ?>"><?= e(\App\Core\InterfaceUi::status($t['estado'])) ?></span></td>
            <td class="oculta-movil">
              <?php if ($t['mi_cuadrante']): ?>
                <span class="chip cuadrante-<?= e($t['mi_cuadrante']) ?>"><?= e($t['mi_cuadrante']) ?></span>
              <?php else: ?>
                <span class="texto-suave">—</span>
              <?php endif; ?>
            </td>
            <td class="oculta-movil texto-suave"><?= e(fecha_corta($t['actualizado_en'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
