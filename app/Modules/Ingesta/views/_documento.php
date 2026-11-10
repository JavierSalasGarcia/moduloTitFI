<?php
/** Tarjeta de un documento indizado: metadatos y enlace de consulta. Espera $r (fila de Ingesta::buscar). */
$enlaceDoc = $r['origen'] === 'teams' ? (string) $r['enlace'] : url_archivo('ingesta/documento/' . (int) $r['id'] . '/archivo') . '?vista=1';
?>
<article class="card ing-doc">
  <h3><a href="<?= url('ingesta/documento/' . (int) $r['id']) ?>"><?= e($r['titulo']) ?></a></h3>
  <p class="ing-origen"><?= $r['origen'] === 'teams' ? 'Teams / SharePoint' : 'PDF' ?><?= $r['tipo_documento'] !== '' ? ' · ' . e($r['tipo_documento']) : '' ?><?= $r['paginas'] ? ' · ' . (int) $r['paginas'] . ' pág.' : '' ?><?= $r['bytes'] ? ' · ' . e(number_format($r['bytes'] / 1048576, 1)) . ' MB' : '' ?></p>
  <?php if ($r['resumen'] !== ''): ?><p><?= e(mb_strimwidth($r['resumen'], 0, 260, '…')) ?></p><?php endif; ?>
  <dl>
    <?php if (!empty($r['carpeta'])): ?><dt>Carpeta</dt><dd><a href="<?= url('ingesta/carpeta/' . (int) $r['carpeta_id']) ?>"><?= e($r['carpeta']) ?></a><?= $r['ruta_relativa'] !== '' ? ' › ' . e($r['ruta_relativa']) : '' ?></dd><?php endif; ?>
    <?php if ($r['autor'] !== ''): ?><dt>Autor o emisor</dt><dd><?= e($r['autor']) ?></dd><?php endif; ?>
    <?php if ($r['fecha_documento']): ?><dt>Fecha del documento</dt><dd><?= e($r['fecha_documento']) ?></dd><?php endif; ?>
    <?php if ($r['palabras_clave'] !== ''): ?><dt>Palabras clave</dt><dd><?= e(mb_strimwidth($r['palabras_clave'], 0, 160, '…')) ?></dd><?php endif; ?>
    <dt>Ingresado por</dt><dd><?= e($r['ingresado_por']) ?> · <?= e(fecha_corta($r['creado_en'])) ?></dd>
    <dt>Lo pueden consultar</dt><dd><?= e(\App\Modules\Ingesta\Ingesta::ALCANCES[$r['alcance']] ?? $r['alcance']) ?><?= $r['alcance'] === 'departamento' && !empty($r['departamento']) ? ': ' . e($r['departamento']) : '' ?></dd>
  </dl>
  <p><a class="btn" href="<?= e($enlaceDoc) ?>" target="_blank" rel="noopener noreferrer"><?= $r['origen'] === 'teams' ? 'Abrir en Teams ↗' : 'Abrir PDF ↗' ?></a></p>
</article>
