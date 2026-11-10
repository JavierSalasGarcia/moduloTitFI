<?php if (!$migrada): ?>
  <div class="card"><p>Falta aplicar la migración <code>044_ingesta_documental</code>. Mientras tanto no hay documentos indizados.</p></div>
<?php else: ?>
<p>Consulta los metadatos de los documentos y carpetas indizados (PDF y Teams) y abre su enlace. Solo aparece lo que tu cuenta puede consultar.</p>
<p class="ux-toolbar">
  <?php if ($puedeIngresar): ?><a class="btn btn-primario" href="<?= url('ingesta/nueva') ?>">Ingresar documentos</a><?php endif; ?>
  <a class="btn" href="<?= url('ingesta/grafo') ?>">Mi grafo de relaciones</a>
</p>
<form method="get" class="card formulario" style="max-width:900px">
  <label>Buscar por título, palabras clave, contenido, carpeta o nombre de archivo
    <input type="search" name="q" value="<?= e($q) ?>" maxlength="160" placeholder="Ej. reglamento de titulación, acta de consejo, plan de estudios">
  </label>
  <div><button class="btn btn-primario">Buscar</button> <?php if ($q !== ''): ?><a href="<?= url('ingesta') ?>">Limpiar</a><?php endif; ?></div>
</form>

<?php if ($carpetas): ?>
  <h2>Carpetas</h2>
  <div class="ing-rejilla">
    <?php foreach ($carpetas as $c): ?>
      <article class="card">
        <h3><a href="<?= url('ingesta/carpeta/' . (int) $c['id']) ?>"><?= e($c['nombre']) ?></a></h3>
        <p class="ing-origen"><?= $c['origen'] === 'teams' ? 'Teams / SharePoint' : 'PDF' ?> · <?= (int) $c['documentos'] ?> documento(s)</p>
        <?php if ($c['descripcion'] !== ''): ?><p><?= e(mb_strimwidth($c['descripcion'], 0, 200, '…')) ?></p><?php endif; ?>
        <small><?= e($c['ingresado_por']) ?> · <?= e(\App\Modules\Ingesta\Ingesta::ALCANCES[$c['alcance']] ?? '') ?></small>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h2><?= $q !== '' ? 'Resultados' : 'Documentos recientes' ?></h2>
<?php if (!$documentos): ?>
  <p><?= $q !== '' ? 'No hay documentos que coincidan dentro de tu acceso.' : 'Aún no hay documentos indizados que puedas consultar.' ?></p>
<?php endif; ?>
<div class="ing-rejilla">
  <?php foreach ($documentos as $r) { include __DIR__ . '/_documento.php'; } ?>
</div>
<nav class="ux-toolbar" aria-label="Páginas">
  <?php if ($pagina > 1): ?><a class="btn" href="<?= url('ingesta?' . http_build_query(['q' => $q, 'pagina' => $pagina - 1])) ?>">Anterior</a><?php endif; ?>
  <?php if ($hayMas): ?><a class="btn" href="<?= url('ingesta?' . http_build_query(['q' => $q, 'pagina' => $pagina + 1])) ?>">Siguiente</a><?php endif; ?>
</nav>
<?php endif; ?>
