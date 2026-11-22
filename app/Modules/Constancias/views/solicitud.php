<?php
/** @var array $s @var array $historial @var bool $esSolicitante @var bool $esFirmante @var bool $editable @var string $folioPrevisto @var array $evidencia */
use App\Core\Auth;
use App\Modules\Constancias\Services\Solicitudes;

include __DIR__ . '/_estilos.php';
$acciones = ['creada' => 'Creada', 'editada' => 'Corregida', 'enviada' => 'Enviada a firma', 'devuelta' => 'Devuelta sin firmar (evidencia o datos)', 'reenviada' => 'Reenviada a firma', 'firmada' => 'Firmada', 'cancelada' => 'Descartada'];
$firmada = $s['estado'] === 'firmada';
?>
<div class="const-pila">
  <section class="tarjeta">
    <div class="const-cabecera">
      <h2><?= e($s['folio'] ?: 'Vista previa') ?></h2>
      <span class="const-estado <?= e($s['estado']) ?>"><?= e(Solicitudes::ESTADOS[$s['estado']] ?? $s['estado']) ?></span>
    </div>
    <?php if ($s['estado'] === 'devuelta' && $s['observacion'] !== ''): ?>
      <div class="const-nota" style="margin-top:12px"><strong><?= e($s['firmante_nombre']) ?> la devolvió sin firmar y pidió evidencia o más datos:</strong><br><?= nl2br(e($s['observacion'])) ?></div>
    <?php endif; ?>
    <?php if ($editable && $s['avisos']): ?>
      <div class="const-nota" style="margin-top:12px"><strong>Del archivo de Word «<?= e($s['archivo']) ?>»:</strong>
        <ul><?php foreach ($s['avisos'] as $a): ?><li><?= e($a) ?></li><?php endforeach; ?></ul>
        Revisa que la vista previa diga lo que necesitas.</div>
    <?php endif; ?>
    <dl class="const-datos" style="margin-top:14px">
      <div><dt>Firma</dt><dd><?= e($s['firmante_nombre']) ?><br><small style="font-weight:400"><?= e($s['firmante_cargo']) ?></small></dd></div>
      <div><dt>Departamento que la emite</dt><dd><?= e($s['departamento']) ?></dd></div>
      <div><dt>Folio</dt><dd><?= e($s['folio'] ?: $folioPrevisto . ' (se asigna al enviarla)') ?></dd></div>
      <div><dt>Solicitó</dt><dd><?= e($s['solicitante_nombre']) ?></dd></div>
      <?php if ($firmada): ?><div><dt>Firmada</dt><dd><?= e(fecha_corta((string) $s['firmada_en'])) ?></dd></div><?php endif; ?>
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
    <div class="const-acciones">
      <?php if ($editable): ?>
        <form method="post" action="<?= e(url('constancias/solicitud/' . (int) $s['id'] . '/enviar')) ?>">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <button class="btn btn-primario" type="submit"><?= $s['estado'] === 'devuelta' ? 'Reenviar a firma' : 'Enviar a firma' ?></button>
        </form>
        <a class="btn" href="<?= e(url('constancias/solicitud/' . (int) $s['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Ver el PDF</a>
        <a class="btn" href="<?= e(url('constancias/solicitud/' . (int) $s['id'] . '/editar')) ?>">Corregir</a>
        <form method="post" action="<?= e(url('constancias/solicitud/' . (int) $s['id'] . '/cancelar')) ?>" onsubmit="return confirm('¿Descartar esta constancia?')">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <button class="btn btn-mini btn-peligro" type="submit">Descartar</button>
        </form>
      <?php elseif ($firmada): ?>
        <a class="btn btn-primario" href="<?= e(url('constancias/solicitud/' . (int) $s['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Descargar la constancia firmada</a>
      <?php else: ?>
        <?php if ($esFirmante && $s['estado'] === 'en_firma'): ?><a class="btn btn-primario" href="<?= e(url('constancias/firmar/' . (int) $s['id'])) ?>">Revisar y firmar</a><?php endif; ?>
        <a class="btn" href="<?= e(url('constancias/solicitud/' . (int) $s['id'] . '/pdf')) ?>" target="_blank" rel="noopener">Ver el PDF (sin firma)</a>
        <?php if ($s['estado'] === 'en_firma' && !$esFirmante): ?><span class="const-ayuda">Espera la firma de <?= e($s['firmante_nombre']) ?>. Te avisaremos por correo.</span><?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <section>
    <h2 style="font-size:16px;margin:0 0 8px;color:var(--verde-900)">Texto de la constancia</h2>
    <?php $bloques = $s['bloques']; $dirigido = $s['dirigido_a']; include __DIR__ . '/_cuerpo.php'; ?>
  </section>

  <?php if ($historial): ?>
  <section class="tarjeta">
    <h2 style="margin-top:0;font-size:16px">Historial</h2>
    <ul class="const-historial">
      <?php foreach ($historial as $h): ?>
        <li><strong><?= e($acciones[$h['accion']] ?? $h['accion']) ?></strong> · <?= e(fecha_corta((string) $h['creado_en'])) ?><?= $h['nombre'] ? ' · ' . e($h['nombre']) : '' ?><?php if ($h['nota'] !== ''): ?><q><?= e($h['nota']) ?></q><?php endif; ?></li>
      <?php endforeach; ?>
    </ul>
  </section>
  <?php endif; ?>
</div>
