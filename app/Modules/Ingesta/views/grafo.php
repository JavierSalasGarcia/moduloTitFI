<p><a href="<?= url('ingesta') ?>">← Documentos indizados</a></p>
<p><?= $propio ? 'Todo lo que la plataforma relaciona con tu cuenta' : 'Relaciones de ' . e($grafo['persona']['nombre']) ?>: adscripción, programas, órganos académicos, estudiantes, protocolos, constancias y documentos. <?= e($grafo['persona']['detalle']) ?></p>

<?php if ($puedeBuscar): ?>
  <form method="get" class="card formulario" style="max-width:700px" action="<?= url('ingesta/grafo') ?>">
    <label><?= $veTodas ? 'Buscar a cualquier persona de la plataforma (nombre o correo)' : 'Personas a tu cargo: filtra por nombre o correo' ?><input type="search" name="persona" value="<?= e($busqueda) ?>" maxlength="80"></label>
    <div><button class="btn">Buscar persona</button> <?php if (!$propio): ?><a href="<?= url('ingesta/grafo') ?>">Volver a mi grafo</a><?php endif; ?></div>
    <?php if ($otros): ?><ul><?php foreach ($otros as $o): ?><li><a href="<?= url('ingesta/grafo/' . (int) $o['id']) ?>"><?= e($o['nombre']) ?></a> <small><?= e($o['email']) ?> · <?= e($o['rol']) ?></small></li><?php endforeach; ?></ul>
    <?php elseif ($busqueda !== ''): ?><p>Sin coincidencias entre las personas que puedes consultar.</p><?php endif; ?>
    <?php if (!$veTodas): ?><p class="ayuda-form">Ves los grafos de las personas del órgano, programa o dependencia que coordinas, y los que te hayan concedido temporalmente.</p><?php endif; ?>
  </form>
<?php endif; ?>

<div class="grafo-marco">
  <div class="grafo-lienzo" data-grafo="<?= e(json_encode(\App\Core\GrafoPersona::datos($grafo), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>">
    <noscript><?= $svg /* alternativa sin JavaScript: generada y escapada en el servidor (GrafoPersona::svg) */ ?></noscript>
  </div>
  <p class="grafo-ayuda">Arrastra los nodos, usa la rueda para acercar, arrastra el fondo para moverte y haz clic en un elemento para abrir su sección.</p>
</div>
<script src="<?= url('assets/js/grafo.js') ?>?v=<?= (int) @filemtime(dirname(__DIR__, 4) . '/public/assets/js/grafo.js') ?>" defer></script>

<h2>Detalle</h2>
<div class="ing-rejilla">
  <?php foreach ($grafo['grupos'] as $g): ?>
    <section class="card">
      <h3><?= e($g['titulo']) ?> <small>(<?= (int) $g['total'] ?>)</small></h3>
      <p class="ing-origen"><?= e($g['modulo']) ?></p>
      <ul>
        <?php foreach ($g['items'] as $i): ?>
          <li><?php if ($i['enlace']): ?><a href="<?= url($i['enlace']) ?>"><?= e($i['etiqueta']) ?></a><?php else: ?><?= e($i['etiqueta']) ?><?php endif; ?><?= $i['detalle'] !== '' ? '<br><small>' . e($i['detalle']) . '</small>' : '' ?></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($g['total'] > count($g['items'])): ?><small>Se muestran <?= count($g['items']) ?> de <?= (int) $g['total'] ?>.</small><?php endif; ?>
    </section>
  <?php endforeach; ?>
</div>
<p class="ayuda-form">Los enlaces abren la sección original, que aplica sus propios permisos.</p>
