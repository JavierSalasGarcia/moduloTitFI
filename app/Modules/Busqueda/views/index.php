<section class="card">
  <p class="texto-suave" style="margin-top:0">¿No sabes a qué departamento dirigir tu petición?
  Describe lo que necesitas y buscaremos las funciones oficiales más parecidas del Manual de Organización.</p>

  <form method="get" action="<?= url('busqueda') ?>" class="formulario">
    <label>Describe tu necesidad
      <textarea name="q" rows="2" required minlength="4" placeholder="Ej. necesito corregir una calificación que aparece mal en mi acta…"><?= e($q) ?></textarea>
    </label>
    <div class="acciones-formulario">
      <button type="submit" class="btn btn-primario"><?= icono('buscar', 15) ?> Buscar departamento</button>
    </div>
  </form>
  <p class="ayuda-form">
    Motor activo: <?= $metodo === 'embeddings' ? 'embeddings con IA (API configurada)' : 'similitud semántica local (sin API externa)' ?>.
  </p>
</section>

<?php if ($q !== ''): ?>
  <?php if (!$resultados): ?>
    <div class="card"><p class="texto-suave">No se encontraron funciones parecidas. Intenta describirlo con otras palabras
    o levanta un <a href="<?= url('tickets/nuevo') ?>">ticket por categoría</a>.</p></div>
  <?php else: ?>
    <h2 class="subtitulo">Funciones más parecidas a tu descripción</h2>
    <div class="lista-resultados">
      <?php foreach ($resultados as $i => $r): ?>
        <article class="card resultado" style="--orden:<?= $i ?>">
          <header class="tarjeta-cabecera columna-movil">
            <div>
              <h2><?= e($r['depto']) ?></h2>
              <small class="texto-suave">Coincidencia: <?= round($r['score'] * 100) ?>%</small>
            </div>
            <a class="btn btn-primario"
               href="<?= url('tickets/nuevo?depto=' . $r['depto_id'] . '&ref=' . urlencode(mb_strimwidth($q, 0, 160))) ?>">
              <?= icono('enviar', 14) ?> Crear ticket aquí
            </a>
          </header>
          <p style="margin:0"><?= e($r['descripcion']) ?></p>
          <div class="progreso" style="margin-top:10px"><div class="progreso-barra" style="width:<?= max(4, round($r['score'] * 100)) ?>%"></div></div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
<?php endif; ?>
