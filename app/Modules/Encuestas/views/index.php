<div class="barra-acciones">
  <p class="texto-suave">Votaciones anónimas: cada voto genera un token verificable en un tablero público.</p>
  <?php if ($puedeCrear): ?>
    <a class="btn btn-primario" href="<?= url('encuestas/nueva') ?>">Nueva encuesta</a>
  <?php endif; ?>
</div>

<nav class="pestanas" aria-label="Filtrar encuestas"><?php foreach (['todas'=>'Todas','pendientes'=>'Por responder','respondidas'=>'Respondidas','cerradas'=>'Cerradas'] as $valor=>$etiqueta): ?><a class="pestana <?= $vistaFiltro===$valor?'activa':'' ?>" href="<?= url('encuestas?vista='.$valor) ?>"><?= $etiqueta ?></a><?php endforeach; ?></nav>
<?php if (!$encuestas): ?>
  <div class="card"><p class="texto-suave">No hay encuestas en esta vista. Puedes consultar las otras pestañas.</p></div>
<?php endif; ?>

<div class="rejilla-2col">
  <?php foreach ($encuestas as $e): ?>
    <article class="card">
      <header class="tarjeta-cabecera">
        <h2><a href="<?= url('encuestas/' . $e['id']) ?>"><?= e($e['titulo']) ?></a></h2>
        <span class="chip estado-<?= e($e['estado']) ?>"><?= $e['estado']==='cerrada'?'Cerrada':($e['ya_voto']?'Respondida':'Pendiente de tu respuesta') ?></span>
      </header>
      <p class="texto-suave" style="margin:0 0 8px">
        Organiza: <?= e($e['creador']) ?> · <?= e(fecha_corta($e['creado_en'])) ?> · <?= (int) $e['votos'] ?> votos
      </p>
      <div class="grupo-chips">
        <a class="btn btn-mini" href="<?= url('encuestas/' . $e['id']) ?>"><?= $e['estado']==='abierta'&&!$e['ya_voto']?'Responder encuesta':'Ver resultados' ?></a>
        <a class="btn btn-mini btn-oro" href="<?= url('encuestas/' . $e['id'] . '/tablero') ?>">Tablero público</a>
      </div>
    </article>
  <?php endforeach; ?>
</div>
