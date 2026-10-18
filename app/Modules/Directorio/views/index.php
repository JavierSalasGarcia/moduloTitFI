<div class="barra-acciones">
  <p class="texto-suave">Estructura orgánica de la Facultad conforme al Manual de Organización (v2, 2026).</p>
  <form method="get" action="<?= url('directorio') ?>" class="buscador">
    <?= icono('buscar', 16) ?>
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar área, persona o necesidad…"><button class="btn" type="submit">Buscar</button>
  </form>
</div>

<?php if (!$porArea): ?>
  <div class="tarjeta"><p class="texto-suave">Ningún departamento coincide con «<?= e($q) ?>».</p></div>
<?php endif; ?>

<?php foreach ($porArea as $area => $deptos): ?>
  <section class="seccion-area">
    <h2 class="titulo-area"><?= e($area) ?></h2>
    <div class="rejilla-deptos">
      <?php foreach ($deptos as $d): ?>
        <a class="tarjeta tarjeta-depto" href="<?= url('directorio/' . $d['id']) ?>">
          <span class="depto-icono"><?= icono('edificio', 20) ?></span>
          <strong><?= e($d['nombre']) ?></strong>
          <small class="texto-suave"><?= (int) $d['num_funciones'] ?> funciones · <?= e($d['origen_mo']) ?></small>
        </a>
      <?php endforeach; ?>
    </div>
  </section>
<?php endforeach; ?>
