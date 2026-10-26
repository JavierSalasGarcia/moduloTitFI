<?php use App\Modules\Concursos\Concursos; ?>
<?php include __DIR__ . '/_estilos.php'; ?>
<p class="texto-secundario"><?= $pasados ? 'Concursos que ya concluyeron, con sus resultados.' : 'Concursos con convocatoria abierta, en revisión o en votación. Revisa las bases de cada uno para participar.' ?></p>
<?php if (!$lista): ?>
  <div class="ux-empty"><?= $pasados ? 'Aún no hay concursos concluidos.' : 'Por ahora no hay concursos abiertos.' ?></div>
<?php endif; ?>
<?php foreach ($lista as $c): ?>
  <section class="tarjeta" style="margin-bottom:14px">
    <div class="tarjeta-cabecera">
      <h2><a href="<?= url('concursos/' . $c['slug']) ?>"><?= e($c['titulo']) ?></a></h2>
      <span class="tit-chip"><?= e(Concursos::FASES[$c['fase']]) ?><?= $c['estado'] === 'borrador' ? ' · borrador' : '' ?></span>
    </div>
    <p><?= e($c['resumen']) ?></p>
    <p class="texto-secundario" style="font-size:14px">
      <?php if ($c['fase'] === 'recepcion'): ?>Recepción hasta el <?= e(Concursos::fechaHora($c['recepcion_fin'])) ?>.
      <?php elseif (in_array($c['fase'], ['revision', 'votacion'], true)): ?>Votación del <?= e(Concursos::fechaHora($c['votacion_inicio'])) ?> al <?= e(Concursos::fechaHora($c['votacion_fin'])) ?>.
      <?php elseif ($c['fase'] === 'computo'): ?>Resultados y premiación el <?= e(Concursos::fechaHora($c['premiacion'])) ?>.
      <?php elseif ($c['fase'] === 'resultados'): ?>Premiación del <?= e(Concursos::fechaHora($c['premiacion'])) ?>.
      <?php else: ?>Se publica el <?= e(Concursos::fechaHora($c['publicacion'])) ?>.<?php endif; ?>
    </p>
    <a class="btn btn-primario btn-mini" href="<?= url('concursos/' . $c['slug']) ?>"><?= $pasados ? 'Ver resultados' : 'Ver convocatoria' ?></a>
  </section>
<?php endforeach; ?>
