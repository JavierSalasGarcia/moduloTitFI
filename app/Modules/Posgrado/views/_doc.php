<?php
/**
 * Renglón de un documento para el egresado.
 * Espera: $fila (clave, nombre, ayuda, estado, doc, tipo?, formato?, nota?), $t, $ev, $prefijo ('req-' | 'doc-'),
 * $puedeSubir (bool), $maxMb.
 */
use App\Core\Auth;
use App\Modules\Posgrado\Catalogo;

[$clase, $ico, $texto] = Catalogo::estadoDocumento((string) $fila['estado']);
$doc = $fila['doc'] ?? null;
$tipo = $fila['tipo'] ?? 'pdf';
$formato = (string) ($fila['formato'] ?? '');
$generado = $formato !== '' ? ($ev['generados'][$formato] ?? null) : null;
?>
<li class="pos-doc" id="<?= e($prefijo . $fila['clave']) ?>">
  <span class="pos-icono <?= e($clase) ?>" aria-hidden="true"><?= $ico !== '' ? icono($ico, 18) : '' ?></span>
  <div>
    <h4><?= e($fila['nombre']) ?> <span class="pos-estado <?= e($clase) ?>"><?= e($texto) ?></span></h4>
    <p class="pos-ayuda"><?= e($fila['ayuda']) ?></p>
    <?php if (!empty($fila['nota'])): ?><p class="pos-legal"><?= e($fila['nota']) ?></p><?php endif; ?>
  </div>
  <?php if ($fila['estado'] === 'devuelto' && !empty($doc['comentario'])): ?>
    <div class="pos-obs" role="note"><strong>Observación de Apoyo a Grado:</strong> <?= e((string) $doc['comentario']) ?></div>
  <?php endif; ?>
  <div class="pos-doc-acciones">
    <?php if ($tipo === 'pdf' && $formato !== '' && isset(Catalogo::formatosEgresado()[$formato])): ?>
      <a class="btn btn-mini" href="<?= url('posgrado/tramite/' . (int) $t['id'] . '/formato/' . $formato) ?>">Descargar formato llenado</a>
    <?php endif; ?>
    <?php if ($tipo === 'generado' && $formato === 'datos_interesado'): ?>
      <?php if ($fila['estado'] === 'listo'): ?><a class="btn btn-mini" href="<?= url('posgrado/tramite/' . (int) $t['id'] . '/formato/datos_interesado') ?>">Descargar para firmar</a>
      <?php else: ?><span class="texto-secundario">Se genera al completar <a href="#datos">tus datos</a>.</span><?php endif; ?>
    <?php elseif ($tipo === 'generado' && $generado): ?>
      <a class="btn btn-mini" href="<?= url('posgrado/generado/' . (int) $generado['id']) ?>" target="_blank" rel="noopener">Ver PDF</a>
    <?php elseif ($tipo === 'fisico'): ?>
      <span class="texto-secundario"><?= $fila['estado'] === 'recibido' ? 'Apoyo a Grado ya lo recibió.' : 'Lo entregas en persona; Apoyo a Grado lo marca al recibirlo.' ?></span>
    <?php endif; ?>
    <?php if ($doc && !empty($doc['ruta'])): ?>
      <a class="btn btn-mini" href="<?= url('posgrado/documento/' . (int) $doc['id']) ?>" target="_blank" rel="noopener">Ver lo que subí</a>
    <?php endif; ?>
    <?php if ($tipo === 'pdf' && $puedeSubir && $fila['estado'] !== 'validado'): $plegar = $fila['estado'] === 'subido'; ?>
      <?php if ($plegar): ?><details class="pos-reemplazar"><summary>Reemplazar el PDF</summary><?php endif; ?>
      <form method="post" enctype="multipart/form-data" action="<?= url('posgrado/tramite/' . (int) $t['id'] . '/documento/' . $fila['clave']) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <label class="sr-only" for="f-<?= e($fila['clave']) ?>">PDF de <?= e($fila['nombre']) ?></label>
        <input id="f-<?= e($fila['clave']) ?>" type="file" name="archivo" accept=".pdf,application/pdf" data-sin-mejora data-max-mb="<?= (int) $maxMb ?>" required>
        <button class="btn btn-mini <?= in_array($fila['estado'], ['pendiente', 'devuelto'], true) ? 'btn-primario' : '' ?>" type="submit"><?= $doc ? 'Reemplazar' : 'Subir PDF' ?></button>
        <small class="texto-secundario">PDF de hasta <?= (int) $maxMb ?> MB</small>
      </form>
      <?php if ($plegar): ?></details><?php endif; ?>
    <?php endif; ?>
  </div>
</li>
