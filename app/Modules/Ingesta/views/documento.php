<?php
use App\Core\Auth;
use App\Modules\Ingesta\Ingesta;

$enlaceDoc = $d['origen'] === 'teams' ? (string) $d['enlace'] : url_archivo('ingesta/documento/' . (int) $d['id'] . '/archivo');
?>
<p><a href="<?= url($d['carpeta_id'] ? 'ingesta/carpeta/' . (int) $d['carpeta_id'] : 'ingesta') ?>">← <?= e($d['carpeta'] ?: 'Documentos indizados') ?></a></p>
<section class="card">
  <p class="ing-origen"><?= $d['origen'] === 'teams' ? 'Archivo de Teams / SharePoint' : 'PDF' ?> · #<?= (int) $d['id'] ?></p>
  <?php if ($d['resumen'] !== ''): ?><p><?= nl2br(e($d['resumen'])) ?></p><?php endif; ?>
  <dl>
    <dt>Tipo de documento</dt><dd><?= e($d['tipo_documento'] ?: 'Sin especificar') ?></dd>
    <dt>Autor o emisor</dt><dd><?= e($d['autor'] ?: 'Sin especificar') ?></dd>
    <dt>Fecha del documento</dt><dd><?= e($d['fecha_documento'] ?: 'Sin especificar') ?></dd>
    <dt>Palabras clave</dt><dd><?= e($d['palabras_clave'] ?: 'Sin especificar') ?></dd>
    <?php if ($d['carpeta']): ?><dt>Carpeta</dt><dd><a href="<?= url('ingesta/carpeta/' . (int) $d['carpeta_id']) ?>"><?= e($d['carpeta']) ?></a><?= $d['ruta_relativa'] !== '' ? ' › ' . e($d['ruta_relativa']) : '' ?></dd><?php endif; ?>
    <dt>Archivo</dt><dd><?= e($d['nombre_original'] ?: '—') ?><?= $d['paginas'] ? ' · ' . (int) $d['paginas'] . ' páginas' : '' ?><?= $d['bytes'] ? ' · ' . e(number_format($d['bytes'] / 1048576, 2)) . ' MB' : '' ?></dd>
    <?php if ($d['origen'] === 'pdf'): ?>
      <dt>Huella SHA-256</dt><dd><code><?= e((string) $d['sha256']) ?></code></dd>
      <dt>Texto para búsqueda</dt><dd><?= $d['texto'] !== null && $d['texto'] !== '' ? 'Sí, se extrajo texto del PDF (' . number_format(mb_strlen((string) $d['texto'])) . ' caracteres).' : 'No se pudo extraer texto (PDF escaneado o protegido): se encuentra por título, palabras clave y resumen.' ?></dd>
    <?php endif; ?>
    <dt>Ingresado por</dt><dd><?= e($d['ingresado_por']) ?> · <?= e(fecha_corta($d['creado_en'])) ?></dd>
    <dt>Lo pueden consultar</dt><dd><?= e(Ingesta::ALCANCES[$d['alcance']] ?? '') ?><?= $d['alcance'] === 'departamento' && $d['departamento'] ? ': ' . e($d['departamento']) : '' ?></dd>
  </dl>
  <p class="ux-toolbar">
    <?php if ($d['origen'] === 'teams'): ?>
      <a class="btn btn-primario" href="<?= e($enlaceDoc) ?>" target="_blank" rel="noopener noreferrer">Abrir en Teams ↗</a>
    <?php else: ?>
      <a class="btn btn-primario" href="<?= e($enlaceDoc) ?>?vista=1" target="_blank" rel="noopener noreferrer">Abrir PDF ↗</a>
      <a class="btn" href="<?= e($enlaceDoc) ?>">Descargar</a>
    <?php endif; ?>
  </p>
  <?php if ($d['origen'] === 'teams'): ?><p class="ayuda-form">El archivo vive en Microsoft 365: para abrirlo necesitas tener permiso allá.</p><?php endif; ?>
</section>

<?php if ($esDueno): ?>
<section class="card">
  <h2>Editar metadatos</h2>
  <form method="post" class="formulario" action="<?= url('ingesta/documento/' . (int) $d['id']) ?>">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <label>Título<input name="titulo" maxlength="250" required value="<?= e($d['titulo']) ?>"></label>
    <label>Tipo de documento<input name="tipo_documento" maxlength="100" value="<?= e($d['tipo_documento']) ?>"></label>
    <label>Autor o emisor<input name="autor" maxlength="200" value="<?= e($d['autor']) ?>"></label>
    <label>Fecha del documento<input type="date" name="fecha_documento" value="<?= e((string) $d['fecha_documento']) ?>"></label>
    <label>Palabras clave<input name="palabras_clave" maxlength="2000" value="<?= e($d['palabras_clave']) ?>"></label>
    <label>Resumen<textarea name="resumen" rows="4" maxlength="4000"><?= e($d['resumen']) ?></textarea></label>
    <button class="btn btn-primario">Guardar metadatos</button>
  </form>
  <form method="post" action="<?= url('ingesta/documento/' . (int) $d['id'] . '/eliminar') ?>" onsubmit="return confirm('¿Eliminar este documento del índice? No se puede deshacer.')">
    <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
    <button class="btn">Eliminar del índice</button>
  </form>
</section>
<?php endif; ?>
