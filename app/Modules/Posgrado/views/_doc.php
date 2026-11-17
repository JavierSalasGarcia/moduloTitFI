<?php
/**
 * Renglón de un requisito para el egresado.
 * Espera: $fila (clave, nombre, ayuda, estado, doc, tipo?, formato?, nota?), $t, $ev, $prefijo ('req-'), $puedeSubir (bool),
 * $editable (bool), $maxMb.
 */
use App\Core\Auth;
use App\Modules\Posgrado\Catalogo;

[$clase, $ico, $texto] = Catalogo::estadoDocumento((string) $fila['estado']);
$doc = $fila['doc'] ?? null;
$tipo = $fila['tipo'] ?? 'pdf';
$formato = (string) ($fila['formato'] ?? '');
$generado = $formato !== '' ? ($ev['generados'][$formato] ?? null) : null;
$docFirmas = $formato !== '' ? ($ev['firmas'][$formato] ?? null) : null;
$cotejado = isset($ev['hitos']['cotejo']);
$csrfDoc = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
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
  <?php if ($tipo === 'firma' && $docFirmas && $fila['estado'] !== 'listo'): ?>
    <div style="grid-column:2"><?php $gestiona = $editable; $volver = $prefijo . $fila['clave']; $fechaExamen = ''; include __DIR__ . '/_firmantes.php'; ?></div>
  <?php endif; ?>
  <div class="pos-doc-acciones">
    <?php if ($tipo === 'firma'): ?>
      <?php if ($fila['estado'] === 'por_pedir'): ?>
        <a class="btn btn-mini" href="<?= url('posgrado/tramite/' . (int) $t['id'] . '/formato/' . $formato) ?>" target="_blank" rel="noopener">Vista previa</a>
        <?php if ($editable && !$cotejado): ?>
          <form method="post" action="<?= url('posgrado/tramite/' . (int) $t['id'] . '/voto') ?>"><?= $csrfDoc ?><button class="btn btn-mini btn-primario" type="submit">Pedir el voto aprobatorio a mi director</button></form>
        <?php endif; ?>
      <?php elseif ($generado): ?>
        <a class="btn btn-mini" href="<?= url('posgrado/generado/' . (int) $generado['id']) ?>" target="_blank" rel="noopener"><?= $fila['estado'] === 'listo' ? 'Ver el voto firmado' : 'Ver el documento' ?></a>
      <?php else: ?>
        <span class="texto-secundario">Se pide al completar <a href="#datos">tus datos</a> (con el correo de tu director).</span>
      <?php endif; ?>
    <?php elseif ($tipo === 'generado' && $formato === 'datos_interesado'): ?>
      <?php if ($fila['estado'] === 'listo' && $generado): ?>
        <a class="btn btn-mini" href="<?= url('posgrado/generado/' . (int) $generado['id']) ?>" target="_blank" rel="noopener">Ver mi formato firmado</a>
      <?php elseif ($fila['estado'] === 'por_firmar'): ?>
        <a class="btn btn-mini" href="<?= url('posgrado/tramite/' . (int) $t['id'] . '/formato/datos_interesado') ?>" target="_blank" rel="noopener">Revisar mi formato</a>
        <?php if ($editable && !$cotejado): ?>
          <form method="post" action="<?= url('posgrado/tramite/' . (int) $t['id'] . '/firmar-datos') ?>"><?= $csrfDoc ?>
            <label class="pos-check"><input type="checkbox" name="leido" value="1" required> Revisé mi formato y mis datos son correctos</label>
            <button class="btn btn-mini btn-primario" type="submit">Firmar mi formato</button></form>
        <?php endif; ?>
      <?php else: ?><span class="texto-secundario">Se genera al completar <a href="#datos">tus datos</a>.</span><?php endif; ?>
    <?php elseif ($tipo === 'generado'): ?>
      <?php if ($fila['estado'] === 'listo' && $generado): ?>
        <a class="btn btn-mini" href="<?= url('posgrado/generado/' . (int) $generado['id']) ?>" target="_blank" rel="noopener">Ver PDF</a>
      <?php elseif ($fila['estado'] === 'en_firma'): ?>
        <span class="texto-secundario">En firma de la Coordinación; aparecerá aquí al firmarse.</span>
      <?php endif; ?>
    <?php elseif ($tipo === 'fisico'): ?>
      <span class="texto-secundario"><?= $fila['estado'] === 'recibido' ? 'Apoyo a Grado ya lo recibió.' : 'Lo entregas en persona; Apoyo a Grado lo marca al recibirlo.' ?></span>
    <?php endif; ?>
    <?php if ($doc && !empty($doc['ruta'])): ?>
      <a class="btn btn-mini" href="<?= url('posgrado/documento/' . (int) $doc['id']) ?>" target="_blank" rel="noopener">Ver lo que subí</a>
    <?php endif; ?>
    <?php if ($tipo === 'pdf' && $puedeSubir && $fila['estado'] !== 'validado'): $plegar = $fila['estado'] === 'subido'; ?>
      <?php if ($plegar): ?><details class="pos-reemplazar"><summary>Reemplazar el PDF</summary><?php endif; ?>
      <form method="post" enctype="multipart/form-data" action="<?= url('posgrado/tramite/' . (int) $t['id'] . '/documento/' . $fila['clave']) ?>">
        <?= $csrfDoc ?>
        <label class="sr-only" for="f-<?= e($fila['clave']) ?>">PDF de <?= e($fila['nombre']) ?></label>
        <input id="f-<?= e($fila['clave']) ?>" type="file" name="archivo" accept=".pdf,application/pdf" data-sin-mejora data-max-mb="<?= (int) $maxMb ?>" required>
        <button class="btn btn-mini <?= in_array($fila['estado'], ['pendiente', 'devuelto'], true) ? 'btn-primario' : '' ?>" type="submit"><?= $doc ? 'Reemplazar' : 'Subir PDF' ?></button>
        <small class="texto-secundario">PDF de hasta <?= (int) $maxMb ?> MB</small>
      </form>
      <?php if ($plegar): ?></details><?php endif; ?>
    <?php endif; ?>
  </div>
</li>
