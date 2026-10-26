<?php use App\Modules\Concursos\Concursos; ?>
<?php include __DIR__ . '/_estilos.php'; ?>
<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px">
  <p class="texto-secundario" style="margin:0">Concursos temporales: se configuran aquí, con sus fechas, premios y bases. El prefiltro de cada concurso se hace desde «Prefiltro».</p>
  <a class="btn btn-primario" href="<?= url('concursos/gestion/nuevo') ?>">Nuevo concurso</a>
</div>
<section class="tarjeta">
  <?php if (!$lista): ?><div class="ux-empty">Sin concursos.</div><?php else: ?>
  <div class="tabla-contenedor"><table class="tabla">
    <thead><tr><th>Concurso</th><th>Estado</th><th>Fase</th><th>Obras</th><th>Votantes</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($lista as $c): ?>
      <tr>
        <td><strong><?= e($c['titulo']) ?></strong><br><small class="texto-secundario">Premiación: <?= e(Concursos::fechaHora($c['premiacion'])) ?></small></td>
        <td><span class="tit-chip <?= $c['estado'] === 'publicado' ? 'ok' : ($c['estado'] === 'cancelado' ? 'mal' : 'rev') ?>"><?= e($c['estado']) ?></span></td>
        <td><?= e(Concursos::FASES[$c['fase']]) ?></td>
        <td><?= (int) $c['obras'] ?><?= (int) $c['por_revisar'] ? ' · <strong>' . (int) $c['por_revisar'] . ' por revisar</strong>' : '' ?></td>
        <td><?= (int) $c['votantes'] ?></td>
        <td style="white-space:nowrap"><a class="btn btn-mini" href="<?= url('concursos/gestion/' . (int) $c['id']) ?>">Configurar</a> <a class="btn btn-mini" href="<?= url('concursos/' . $c['slug'] . '/prefiltro') ?>">Prefiltro</a> <a class="btn btn-mini" href="<?= url('concursos/' . $c['slug']) ?>">Ver</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</section>
