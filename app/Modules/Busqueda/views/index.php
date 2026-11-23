<?php
/** @var string $q @var array $r @var array $catalogo @var bool $hayAlgo */
$datos = $r['datos']['items'] ?? [];
?>
<section class="card">
  <form method="get" action="<?= url('busqueda') ?>" class="formulario" role="search">
    <label for="busqueda-pagina-q">Qué quieres hacer o encontrar</label>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <input id="busqueda-pagina-q" type="search" name="q" value="<?= e($q) ?>" required minlength="2" maxlength="200" style="flex:1 1 260px" placeholder="Ej. constancia, reunión, firmar, calificación…">
      <button type="submit" class="btn btn-primario"><?= icono('buscar', 15) ?> Buscar</button>
    </div>
  </form>
</section>

<?php if ($q === ''): ?>
  <section class="card">
    <h2 class="subtitulo" style="margin-top:0">Lo que puedes abrir</h2>
    <p class="texto-suave">Estas son las opciones disponibles para tu cuenta. Escribe arriba para buscar entre ellas, en los avisos publicados y en las áreas responsables de cada trámite.</p>
    <?php foreach ($catalogo as $modulo => $lista): ?>
      <h3 style="margin:16px 0 4px;font-size:15px"><?= e($modulo) ?></h3>
      <ul style="margin:0;padding-left:18px">
        <?php foreach ($lista as $e): ?>
          <li style="margin:4px 0"><a href="<?= e(url($e['ruta'])) ?>"><?= e($e['titulo']) ?></a><?php if ($e['descripcion'] !== ''): ?> <span class="texto-suave">— <?= e(mb_strimwidth($e['descripcion'], 0, 110, '…')) ?></span><?php endif; ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endforeach; ?>
  </section>
<?php elseif (!$hayAlgo): ?>
  <section class="card">
    <h2 class="subtitulo" style="margin-top:0">No encontramos resultados para «<?= e($q) ?>»</h2>
    <p class="texto-suave">Prueba con otras palabras (por ejemplo, el nombre del trámite) o revisa <a href="<?= url('busqueda') ?>">todo lo que puedes abrir</a>. Si necesitas apoyo de un área, puedes <a href="<?= url('tickets/nuevo') ?>">pedirlo aquí</a>.</p>
  </section>
<?php else: ?>
  <?php if ($r['opciones']): ?>
  <section class="card">
    <h2 class="subtitulo" style="margin-top:0">Opciones del sitio</h2>
    <ul style="margin:0;padding-left:18px">
      <?php foreach ($r['opciones'] as $e): ?>
        <li style="margin:8px 0"><a href="<?= e(url($e['ruta'])) ?>"><strong><?= e($e['titulo']) ?></strong></a> <small class="texto-suave">· <?= e($e['modulo']) ?></small>
          <?php if ($e['descripcion'] !== ''): ?><br><span class="texto-suave"><?= e(mb_strimwidth($e['descripcion'], 0, 160, '…')) ?></span><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if ($datos): ?>
  <section class="card">
    <h2 class="subtitulo" style="margin-top:0"><?= e($r['datos']['titulo'] ?? 'Tus registros') ?></h2>
    <?php if (!empty($r['datos']['explicacion'])): ?><p class="texto-suave"><?= e($r['datos']['explicacion']) ?></p><?php endif; ?>
    <ul style="margin:0;padding-left:18px">
      <?php foreach ($datos as $i): ?><li style="margin:8px 0"><a href="<?= e(url($i['ruta'])) ?>"><?= e($i['titulo']) ?></a><?php if (!empty($i['detalle'])): ?><br><span class="texto-suave"><?= e($i['detalle']) ?></span><?php endif; ?></li><?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if ($r['avisos']): ?>
  <section class="card">
    <h2 class="subtitulo" style="margin-top:0">Avisos y eventos</h2>
    <ul style="margin:0;padding-left:18px">
      <?php foreach ($r['avisos'] as $i): ?><li style="margin:8px 0"><a href="<?= e(url($i['ruta'])) ?>"><?= e($i['titulo']) ?></a><?php if (!empty($i['detalle'])): ?><br><span class="texto-suave"><?= e($i['detalle']) ?></span><?php endif; ?></li><?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>

  <?php if ($r['funciones']): ?>
  <section>
    <h2 class="subtitulo">Áreas responsables más parecidas</h2>
    <div class="lista-resultados">
      <?php foreach ($r['funciones'] as $i => $f): ?>
        <article class="card resultado" style="--orden:<?= $i ?>">
          <header class="tarjeta-cabecera columna-movil">
            <div><h3 style="margin:0;font-size:16px"><?= e($f['depto']) ?></h3><small class="texto-suave">Coincidencia: <?= round($f['score'] * 100) ?>%</small></div>
            <a class="btn btn-primario" href="<?= url('tickets/nuevo?depto=' . $f['depto_id'] . '&ref=' . urlencode(mb_strimwidth($q, 0, 160))) ?>"><?= icono('enviar', 14) ?> Pedir apoyo a esta área</a>
          </header>
          <p style="margin:0"><?= e($f['descripcion']) ?></p>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
<?php endif; ?>
