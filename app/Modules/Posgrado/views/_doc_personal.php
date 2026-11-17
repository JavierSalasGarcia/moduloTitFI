<?php
/**
 * Renglón de un documento para el personal de Apoyo a Grado.
 * Espera: $fila (clave, nombre, ayuda, estado, doc, tipo?, formato?), $t, $ev, $prefijo, $bloqueado (bool: ya cotejado o cerrado).
 */
use App\Core\Auth;
use App\Modules\Posgrado\Catalogo;

[$clase, $ico, $texto] = Catalogo::estadoDocumento((string) $fila['estado']);
$doc = $fila['doc'] ?? null;
$tipo = $fila['tipo'] ?? 'pdf';
$formato = (string) ($fila['formato'] ?? '');
$generado = $formato !== '' ? ($ev['generados'][$formato] ?? null) : null;
$gbase = 'posgrado/gestion/' . (int) $t['id'];
$docFirmas = $formato !== '' ? ($ev['firmas'][$formato] ?? null) : null;
$opera = \App\Modules\Posgrado\PosgradoAccess::esPersonalOperativo();
?>
<li class="pos-doc" id="<?= e($prefijo . $fila['clave']) ?>">
  <span class="pos-icono <?= e($clase) ?>" aria-hidden="true"><?= $ico !== '' ? icono($ico, 18) : '' ?></span>
  <div>
    <h4><?= isset($fila['n']) ? '<small class="texto-secundario">' . e((string) $fila['n']) . '.</small> ' : '' ?><?= e($fila['nombre']) ?> <span class="pos-estado <?= e($clase) ?>"><?= e($texto) ?></span></h4>
    <?php if ($doc && !empty($doc['revisor_nombre']) && in_array($fila['estado'], ['validado', 'devuelto', 'recibido'], true)): ?>
      <p class="pos-legal"><?= e(['validado' => 'Validado', 'devuelto' => 'Devuelto', 'recibido' => 'Recibido'][$fila['estado']]) ?> por <?= e((string) $doc['revisor_nombre']) ?> · <?= e(fecha_corta((string) $doc['revisado_en'])) ?></p>
    <?php elseif ($doc && $fila['estado'] === 'subido'): ?>
      <p class="pos-legal">Subido el <?= e(fecha_corta((string) $doc['creado_en'])) ?></p>
    <?php endif; ?>
  </div>
  <?php if (!empty($doc['comentario'])): ?><div class="pos-obs"><?= e((string) $doc['comentario']) ?></div><?php endif; ?>
  <?php if ($docFirmas && in_array($tipo, ['firma', 'generado'], true)): ?>
    <div style="grid-column:2"><?php $gestiona = $opera && $t['estado'] === 'abierto'; $volver = $prefijo . $fila['clave']; $fechaExamen = ''; include __DIR__ . '/_firmantes.php'; ?></div>
  <?php endif; ?>
  <div class="pos-doc-acciones">
    <?php if ($doc && !empty($doc['ruta'])): ?>
      <a class="btn btn-mini" href="<?= url('posgrado/documento/' . (int) $doc['id']) ?>" target="_blank" rel="noopener">Ver PDF</a>
    <?php endif; ?>
    <?php if ($tipo === 'pdf' && $doc && !$bloqueado && $opera && in_array($fila['estado'], ['subido', 'validado', 'devuelto'], true)): ?>
      <?php if ($fila['estado'] !== 'validado'): ?>
        <form method="post" action="<?= url($gbase . '/documento/' . (int) $doc['id']) ?>">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <input type="hidden" name="decision" value="validado">
          <button class="btn btn-mini btn-primario" type="submit">Validar</button>
        </form>
      <?php endif; ?>
      <form method="post" action="<?= url($gbase . '/documento/' . (int) $doc['id']) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <input type="hidden" name="decision" value="devuelto">
        <label class="sr-only" for="obs-<?= (int) $doc['id'] ?>">Observación para el egresado</label>
        <textarea id="obs-<?= (int) $doc['id'] ?>" name="comentario" rows="1" maxlength="900" placeholder="Qué debe corregir"></textarea>
        <button class="btn btn-mini btn-peligro" type="submit">Devolver</button>
      </form>
    <?php elseif ($tipo === 'pdf' && !$doc): ?>
      <span class="texto-secundario">El egresado aún no lo sube.</span>
    <?php elseif ($tipo === 'fisico' && !$bloqueado && $opera): ?>
      <form method="post" action="<?= url($gbase . '/fisico/' . $fila['clave']) ?>">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <?php if ($fila['estado'] === 'recibido'): ?>
          <input type="hidden" name="accion" value="quitar"><button class="btn btn-mini" type="submit">Desmarcar</button>
        <?php else: ?>
          <input type="hidden" name="accion" value="marcar"><button class="btn btn-mini btn-primario" type="submit">Marcar como recibido</button>
        <?php endif; ?>
      </form>
    <?php elseif ($tipo === 'firma' || ($tipo === 'generado' && $formato === 'datos_interesado')): ?>
      <?php if ($generado): ?><a class="btn btn-mini" href="<?= url('posgrado/generado/' . (int) $generado['id']) ?>" target="_blank" rel="noopener">Ver PDF</a><?php endif; ?>
      <?php if (!$generado): ?><span class="texto-secundario"><?= $fila['estado'] === 'pendiente' ? 'Faltan datos del interesado.' : ($tipo === 'firma' ? 'El egresado aún no lo pide.' : 'El egresado aún no lo firma.') ?></span>
        <?php if ($fila['estado'] !== 'pendiente'): ?><a class="btn btn-mini" href="<?= url('posgrado/tramite/' . (int) $t['id'] . '/formato/' . $formato) ?>" target="_blank" rel="noopener">Vista previa</a><?php endif; ?>
      <?php endif; ?>
      <?php if ($generado && $fila['estado'] === 'listo' && !$bloqueado && $opera): ?>
        <details class="pos-reemplazar"><summary>Anular para que el egresado corrija</summary>
          <form method="post" action="<?= url($gbase . '/anular/' . $formato) ?>">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <label class="sr-only" for="anular-<?= e($formato) ?>">Motivo</label>
            <textarea id="anular-<?= e($formato) ?>" name="justificacion" rows="1" maxlength="300" placeholder="Qué debe corregir" required></textarea>
            <button class="btn btn-mini btn-peligro" type="submit">Anular firmas</button>
          </form>
        </details>
      <?php endif; ?>
    <?php elseif ($tipo === 'generado'): ?>
      <?php if ($formato !== '' && isset(Catalogo::emitibles()[$formato])): ?>
        <?php if ($generado): ?><a class="btn btn-mini" href="<?= url('posgrado/generado/' . (int) $generado['id']) ?>" target="_blank" rel="noopener">Ver PDF</a><?php endif; ?>
        <?php if ($t['estado'] === 'abierto' && $opera && ($formato !== 'integracion' || $ev['aprobado'])): ?>
          <form method="post" action="<?= url($gbase . '/emitir/' . $formato) ?>">
            <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
            <button class="btn btn-mini <?= $generado ? '' : 'btn-primario' ?>" type="submit"><?= $generado ? 'Volver a emitir' : 'Emitir' ?></button>
          </form>
        <?php elseif ($formato === 'integracion'): ?>
          <span class="texto-secundario">Se emite después del examen.</span>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</li>
