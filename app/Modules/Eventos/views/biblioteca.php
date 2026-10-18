<div class="barra-acciones">
  <p class="texto-suave">Memoria de eventos concluidos de la Facultad, agrupados por categoría.</p>
  <form method="get" action="<?= url('eventos/biblioteca') ?>" class="buscador">
    <?= icono('buscar', 16) ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar en la biblioteca…">
  </form>
</div>

<?php if (!$porCategoria): ?>
  <div class="card"><p class="texto-suave">No hay eventos concluidos que coincidan con la búsqueda.</p></div>
<?php endif; ?>

<?php foreach ($porCategoria as $cat => $eventos): ?>
  <section class="seccion-area">
    <h2 class="titulo-area"><?= e($categorias[$cat]) ?> · <?= count($eventos) ?></h2>
    <div class="carrusel" tabindex="0" aria-label="Carrusel de <?= e($categorias[$cat]) ?>">
      <?php foreach ($eventos as $ev): ?>
        <a class="evento-card cat-<?= e($cat) ?>" href="<?= url('eventos/' . $ev['id']) ?>">
          <span class="evento-card-fecha"><?= e(fecha_corta($ev['inicio'])) ?></span>
          <strong class="evento-card-titulo"><?= e($ev['titulo']) ?></strong>
          <span class="texto-suave evento-card-depto"><?= e($ev['depto']) ?></span>
          <span class="evento-card-desc"><?= e(mb_strimwidth($ev['descripcion'], 0, 110, '…')) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
