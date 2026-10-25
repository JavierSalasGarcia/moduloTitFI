<?php
include __DIR__ . '/_estilos.php';
?>
<div class="tit-cabecera"><div>
  <p class="texto-secundario">Resultados de la revisión de tu protocolo y avisos del Departamento de Evaluación Profesional.</p>
</div></div>
<div class="tit-dos">
  <section class="tarjeta">
    <?php if ($abierto): ?>
      <h2 style="margin-top:0"><?= e($abierto['asunto']) ?></h2>
      <p class="texto-secundario"><?= e($abierto['remitente']) ?> · <?= e(fecha_corta((string) $abierto['creado_en'])) ?> · Folio <?= e($abierto['folio']) ?></p>
      <div style="white-space:pre-wrap;line-height:1.55"><?= e((string) $abierto['cuerpo']) ?></div>
      <?php if (!empty($ronda) && $ronda['estado'] === 'dictaminada'): ?>
        <p style="margin-top:14px">
          <a class="btn btn-mini" href="<?= url('titulacion/licenciatura/tramite/' . (int) $ronda['tramite_id'] . '/dictamen/' . (int) $ronda['id']) ?>" target="_blank" rel="noopener">Ver el dictamen firmado (PDF)</a>
          <?php if ($ronda['dictamen'] === 'no_aprobado'): ?>
            <a class="btn btn-mini btn-primario" href="<?= url('titulacion/licenciatura/tramite/' . (int) $ronda['tramite_id'] . '?etapa=protocolo') ?>">Corregir y enviar mi protocolo</a>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    <?php elseif (!$mensajes): ?>
      <div class="ux-empty">Aún no tienes mensajes.</div>
    <?php else: ?>
      <p class="texto-secundario">Elige un mensaje para leerlo.</p>
    <?php endif; ?>
  </section>
  <aside class="tarjeta">
    <h2 style="font-size:16px">Bandeja</h2>
    <?php foreach ($mensajes as $m): ?>
      <div class="tit-evento" style="<?= $abierto && (int) $abierto['id'] === (int) $m['id'] ? 'background:var(--verde-050);' : '' ?>">
        <a href="<?= url('titulacion/mensajes/' . (int) $m['id']) ?>" style="<?= $m['leido_en'] ? '' : 'font-weight:700' ?>"><?= e($m['asunto']) ?></a>
        <time><?= e($m['remitente']) ?> · <?= e(fecha_corta((string) $m['creado_en'])) ?><?= $m['leido_en'] ? '' : ' · nuevo' ?></time>
      </div>
    <?php endforeach; ?>
  </aside>
</div>
