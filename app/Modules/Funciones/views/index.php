<section class="tarjeta">
  <header class="tarjeta-cabecera columna-movil">
    <div>
      <h2><?= e($depto['nombre']) ?></h2>
      <small class="texto-suave"><?= e($depto['area']) ?> · <?= e($depto['origen_mo']) ?></small>
    </div>
    <form method="get" action="<?= url('funciones') ?>" class="buscador">
      <?= icono('buscar', 16) ?>
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Filtrar funciones...">
    </form>
  </header>

  <?php if (!$funciones): ?>
    <div class="ux-empty"><?php if ($q !== ''): ?>No encontramos funciones para «<?= e($q) ?>». <a href="<?= url('funciones') ?>">Quitar filtro</a><?php else: ?>Tu área aún no tiene funciones asignadas. <a href="<?= url('directorio') ?>">Consultar otras áreas</a><?php endif; ?></div>
  <?php else: ?>
    <ol class="lista-funciones">
      <?php foreach ($funciones as $f): ?>
        <li>
          <span class="num-funcion"><?= (int) $f['numero'] ?></span>
          <p><?= e($f['descripcion']) ?></p>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>
</section>
