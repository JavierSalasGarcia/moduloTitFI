<?php use App\Core\Auth; use App\Modules\Ingesta\Ingesta; ?>
<p><a href="<?= url('ingesta') ?>">← Documentos indizados</a></p>
<section class="card">
  <p class="ing-origen"><?= $carpeta['origen'] === 'teams' ? 'Carpeta de Teams / SharePoint' : 'Carpeta de PDFs' ?> · <?= count($documentos) ?> documento(s)</p>
  <?php if ($carpeta['descripcion'] !== ''): ?><p><?= nl2br(e($carpeta['descripcion'])) ?></p><?php endif; ?>
  <dl>
    <dt>Ingresada por</dt><dd><?= e($carpeta['ingresado_por']) ?> · <?= e(fecha_corta($carpeta['creado_en'])) ?></dd>
    <dt>La pueden consultar</dt><dd><?= e(Ingesta::ALCANCES[$carpeta['alcance']] ?? '') ?><?= $carpeta['alcance'] === 'departamento' && $carpeta['departamento'] ? ': ' . e($carpeta['departamento']) : '' ?></dd>
  </dl>
  <?php if ($carpeta['enlace']): ?><p><a class="btn" href="<?= e($carpeta['enlace']) ?>" target="_blank" rel="noopener noreferrer">Abrir la carpeta en Teams ↗</a></p><?php endif; ?>
</section>

<form method="get" class="card formulario" style="max-width:900px">
  <label>Buscar dentro de la carpeta<input type="search" name="q" value="<?= e($q) ?>" maxlength="160"></label>
  <div><button class="btn btn-primario">Buscar</button> <?php if ($q !== ''): ?><a href="<?= url('ingesta/carpeta/' . (int) $carpeta['id']) ?>">Limpiar</a><?php endif; ?></div>
</form>

<?php if (!$documentos): ?><p>No hay documentos<?= $q !== '' ? ' que coincidan' : ' en esta carpeta' ?>.</p><?php endif; ?>
<div class="ing-rejilla">
  <?php foreach ($documentos as $r) { include __DIR__ . '/_documento.php'; } ?>
</div>

<?php if ($esDueno): ?>
  <section class="card" style="margin-top:24px">
    <h2>Administrar la carpeta</h2>
    <?php if ($carpeta['origen'] === 'teams'): ?>
      <form method="post" class="formulario" action="<?= url('ingesta/carpeta/' . (int) $carpeta['id'] . '/enlaces') ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <label>Agregar enlaces de archivos (uno por línea; opcionalmente «Título | enlace»)
          <textarea name="enlaces" rows="5" required placeholder="Acta de sesión 12 | https://…sharepoint.com/…/acta12.pdf"></textarea>
        </label>
        <button class="btn btn-primario">Agregar enlaces</button>
      </form>
    <?php else: ?>
      <div class="formulario" data-ingesta-lote data-url="<?= url('ingesta/carpeta/' . (int) $carpeta['id'] . '/archivo') ?>" data-csrf="<?= e(Auth::csrfToken()) ?>">
        <label>Agregar más PDFs a la carpeta
          <input type="file" accept="application/pdf,.pdf" multiple data-ingesta-archivos>
        </label>
        <p role="status" data-ingesta-estado></p>
      </div>
    <?php endif; ?>
    <form method="post" action="<?= url('ingesta/carpeta/' . (int) $carpeta['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar la carpeta y todos sus documentos del índice? No se puede deshacer.')">
      <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
      <button class="btn">Eliminar carpeta</button>
    </form>
  </section>
  <?php include __DIR__ . '/_lote_js.php'; ?>
<?php endif; ?>
