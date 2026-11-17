<?php
/** @var array $grupo @var array $bloques */
?>
<div class="sec-pagina">
  <p class="sec-frase"><?= e($grupo['frase']) ?></p>
  <?php foreach ($bloques as $bloque): ?>
    <section class="sec-bloque"<?= $bloque['subtitulo'] !== '' ? ' aria-labelledby="sec-' . e(substr(md5($bloque['subtitulo']), 0, 8)) . '"' : '' ?>>
      <?php if ($bloque['subtitulo'] !== ''): ?>
        <h2 class="sec-subtitulo" id="sec-<?= e(substr(md5($bloque['subtitulo']), 0, 8)) ?>"><?= e($bloque['subtitulo']) ?></h2>
      <?php endif; ?>
      <ul class="sec-rejilla">
        <?php foreach ($bloque['tarjetas'] as $t): ?>
          <li>
            <a class="sec-tarjeta" href="<?= e(url($t['ruta'])) ?>">
              <span class="sec-icono"><?= icono($grupo['icono'], 20) ?></span>
              <span class="sec-titulo"><?= e($t['titulo']) ?>
                <?php if ($t['contador'] > 0): ?><span class="nav-badge"><span aria-hidden="true"><?= $t['contador'] ?></span><span class="nav-sr"><?= $t['contador'] ?> por atender</span></span><?php endif; ?>
              </span>
              <?php if ($t['descripcion'] !== ''): ?><span class="sec-desc"><?= e($t['descripcion']) ?></span><?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endforeach; ?>
</div>
