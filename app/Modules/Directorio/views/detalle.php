<p><a class="enlace" href="<?= url('directorio') ?>"><?= icono('flecha-izq', 14) ?> Volver al directorio</a></p>

<div class="rejilla-detalle">
  <section class="tarjeta">
    <header class="tarjeta-cabecera columna-movil">
      <div>
        <h2><?= e($depto['nombre']) ?></h2>
        <small class="texto-suave"><?= e($depto['area']) ?> · <?= e($depto['origen_mo']) ?></small>
      </div>
      <a class="btn btn-primario" href="<?= url('tickets/nuevo?depto=' . (int)$depto['id']) ?>"><?= icono('enviar', 15) ?> Enviar solicitud a esta área</a>
    </header>

    <h3 class="subtitulo">Qué resuelve esta área</h3>
    <ol class="lista-funciones compacta">
      <?php foreach ($funciones as $f): ?>
        <li><span class="num-funcion"><?= (int) $f['numero'] ?></span><p><?= e($f['descripcion']) ?></p></li>
      <?php endforeach; ?>
    </ol>
  </section>

  <aside class="columna-lateral">
    <?php if ($titulares): ?>
      <section class="tarjeta">
        <header class="tarjeta-cabecera"><h2><?= icono('usuario', 16) ?> Personal registrado</h2></header>
        <ul class="lista-canales">
          <?php foreach ($titulares as $t): ?>
            <li><strong><?= e($t['nombre']) ?></strong>
              <small class="texto-suave"><a href="mailto:<?= e($t['email']) ?>"><?= e($t['email']) ?></a> · <?= e($t['rol']) ?></small></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="tarjeta">
      <header class="tarjeta-cabecera"><h2><?= icono('enviar', 16) ?> Canales que inicia</h2></header>
      <?php if (!$canalesSalida): ?><p class="texto-suave">Sin canales registrados.</p><?php else: ?>
        <ul class="lista-canales">
          <?php foreach ($canalesSalida as $c): ?>
            <li><a href="<?= url('directorio/' . $c['id']) ?>"><strong><?= e($c['nombre']) ?></strong></a>
              <small class="texto-suave"><?= e($c['descripcion']) ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="tarjeta">
      <header class="tarjeta-cabecera"><h2><?= icono('entrada', 16) ?> Canales que recibe</h2></header>
      <?php if (!$canalesEntrada): ?><p class="texto-suave">Sin canales registrados.</p><?php else: ?>
        <ul class="lista-canales">
          <?php foreach ($canalesEntrada as $c): ?>
            <li><a href="<?= url('directorio/' . $c['id']) ?>"><strong><?= e($c['nombre']) ?></strong></a>
              <small class="texto-suave"><?= e($c['descripcion']) ?></small></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</div>
