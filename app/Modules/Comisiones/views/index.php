<div class="barra-acciones">
  <p class="texto-suave">Seguimiento auditable de asuntos derivados de reuniones o de peticiones del foro.</p>
  <?php if ($puedeCrear): ?>
    <a class="btn btn-primario" href="<?= url('comisiones/nueva') ?>">Formar comisión</a>
  <?php endif; ?>
</div>

<?php if (!$comisiones): ?>
  <div class="card"><p class="texto-suave">No hay comisiones registradas.</p></div>
<?php endif; ?>

<?php foreach ($comisiones as $c): ?>
  <article class="card">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><a href="<?= url('comisiones/' . $c['id']) ?>"><?= e($c['titulo']) ?></a></h2>
        <small class="texto-suave">
          Responsable: <?= e($c['depto']) ?> · creada por <?= e($c['creador']) ?> · origen: <?= $c['origen'] === 'peticion' ? 'petición del foro' : 'reunión convocada' ?>
        </small>
      </div>
      <div class="grupo-chips">
        <?php if ($c['mi_estado'] === 'invitado'): ?>
          <span class="badge badge-ambar">Invitación pendiente</span>
        <?php elseif ($c['mi_estado'] === 'aceptado'): ?>
          <span class="badge badge-verde">Eres miembro</span>
        <?php endif; ?>
        <span class="badge badge-oro"><?= (int) $c['miembros'] ?> miembros</span>
        <span class="chip estado-<?= e($c['estado']) ?>"><?= e(str_replace('_', ' ', $c['estado'])) ?></span>
      </div>
    </header>
    <p class="ux-helper"><?= $c['mi_estado']==='invitado'?'Siguiente paso: confirma si participarás en esta comisión.':($c['estado']==='concluida'?'Comisión finalizada. Consulta sus acuerdos y evidencias.':'Consulta los acuerdos y registra los avances del equipo.') ?></p>
    <a class="btn" href="<?= url('comisiones/'.$c['id']) ?>"><?= $c['mi_estado']==='invitado'?'Responder invitación':'Ver acuerdos y avance' ?></a>
  </article>
<?php endforeach; ?>
