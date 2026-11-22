<?php
/** @var array $s @var array $historial @var array $evidencia */
use App\Core\Auth;

include __DIR__ . '/_estilos.php';
$base = 'constancias/firmar/' . (int) $s['id'];
?>
<div class="const-pila">
  <p><a href="<?= e(url('constancias/firmar')) ?>">← Constancias por firmar</a></p>
  <section class="tarjeta">
    <div class="const-cabecera">
      <h2><?= e($s['folio']) ?></h2>
      <span class="const-estado <?= e($s['estado']) ?>"><?= $s['estado'] === 'firmada' ? 'Firmada' : ($s['estado'] === 'devuelta' ? 'Devuelta' : 'Espera tu firma') ?></span>
    </div>
    <dl class="const-datos" style="margin-top:14px">
      <div><dt>Solicitó (y a quien se extiende)</dt><dd><?= e($s['solicitante_nombre']) ?></dd></div>
      <div><dt>Firmas como</dt><dd><?= e($s['firmante_nombre']) ?><br><small style="font-weight:400"><?= e($s['firmante_cargo']) ?></small></dd></div>
      <div><dt>Se archiva en</dt><dd><?= e($s['departamento']) ?></dd></div>
    </dl>
    <?php if (!empty($evidencia)): ?>
      <div style="margin-top:12px"><strong>Evidencia adjunta</strong>
        <ul style="margin:4px 0 0;padding-left:18px">
          <?php foreach ($evidencia as $a): ?>
            <li><a href="<?= e(url('constancias/adjunto/' . (int) $a['id'])) ?>" target="_blank" rel="noopener"><?= e($a['descripcion'] !== '' ? $a['descripcion'] : $a['nombre']) ?></a> <small>(<?= e($a['nombre']) ?>)</small></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
    <?php if ($s['estado'] === 'en_firma'): ?>
      <form method="post" action="<?= e(url($base)) ?>" style="margin-top:14px">
        <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
        <p><a href="<?= e(url('constancias/solicitud/' . (int) $s['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Abrir el PDF tal como se imprimirá</a></p>
        <label style="display:flex;gap:8px;align-items:flex-start;font-weight:600"><input type="checkbox" name="revisada" value="1" required> Revisé la constancia y estoy conforme con su contenido</label>
        <p class="const-ayuda">Al firmar se estampa tu sello en el bloque de firma <?= \App\Modules\Titulacion\Services\SelloPdf::modoInterno() ? '(rotulado «CONFORMIDAD · registro interno» mientras los Consejos no aprueben la firma electrónica)' : '' ?> y se avisa a quien la solicitó.</p>
        <div class="const-acciones"><button class="btn btn-primario" type="submit">Firmar constancia</button></div>
      </form>
      <div style="margin-top:18px;padding-top:14px;border-top:1px solid var(--gris-100)">
        <h3 style="margin:0 0 6px;font-size:16px">¿Falta evidencia o información? Devuélvela sin firmar</h3>
        <p class="const-ayuda" style="margin:0 0 8px">Quien la solicitó recibe tu petición por correo, puede corregir el texto o adjuntar evidencia y volver a enviártela. No se firma hasta entonces.</p>
        <form method="post" action="<?= e(url($base . '/devolver')) ?>" class="formulario">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <label>¿Qué evidencia o datos necesitas antes de firmar?
            <textarea name="observacion" rows="3" maxlength="1000" required minlength="5" placeholder="Ej. Adjunta el oficio de comisión; indica el periodo exacto…"></textarea>
          </label>
          <button class="btn" type="submit">Devolver sin firmar y pedir evidencia o datos</button>
        </form>
      </div>
    <?php elseif ($s['estado'] === 'firmada'): ?>
      <div class="const-acciones"><a class="btn btn-primario" href="<?= e(url('constancias/solicitud/' . (int) $s['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Descargar la constancia firmada</a></div>
    <?php else: ?>
      <p class="const-ayuda" style="margin-top:12px">La devolviste sin firmar pidiendo evidencia o datos; volverá a tu bandeja cuando la reenvíen.</p>
    <?php endif; ?>
  </section>
  <section>
    <h2 style="font-size:16px;margin:0 0 8px;color:var(--verde-900)">Texto de la constancia</h2>
    <?php $bloques = $s['bloques']; $dirigido = $s['dirigido_a']; include __DIR__ . '/_cuerpo.php'; ?>
  </section>
</div>
