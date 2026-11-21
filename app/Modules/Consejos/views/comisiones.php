<?php
/** @var array $reuniones @var bool $gestor */
include __DIR__ . '/_estilos.php';
$grupo = ['profesor' => 'Caso de profesor', 'alumno' => 'Caso de alumno', 'general' => 'Asunto general'];
?>
<div class="cns-pila">
  <section class="tarjeta">
    <div class="cns-cab">
      <div>
        <h1>Comisiones</h1>
        <p class="cns-sub">Dictámenes y comisiones de los HH. Consejos Académico y de Gobierno en los que se te menciona. El escaneado firmado aparece cuando la Subdirección Académica lo sube.</p>
      </div>
      <?php if ($gestor): ?><a class="btn" href="<?= e(url('consejos')) ?>">Gestionar Consejos</a><?php endif; ?>
    </div>
  </section>

  <?php if (!$reuniones): ?>
    <section class="tarjeta" style="text-align:center;padding:28px 16px">
      <h2 style="margin:0 0 6px;font-size:18px">Por ahora no tienes comisiones ni dictámenes</h2>
      <p class="cns-sub" style="max-width:520px;margin:0 auto">Cuando un asunto en que se te mencione sea tratado por los HH. Consejos, aparecerá aquí con su dictamen.</p>
    </section>
  <?php endif; ?>

  <?php foreach ($reuniones as $rn): ?>
    <section class="tarjeta">
      <div class="cns-cab" style="margin-bottom:8px">
        <div>
          <h2>Sesión <?= e(mb_strtolower($rn['tipo_sesion'])) ?> · <?= e($rn['etiqueta']) ?></h2>
          <p class="cns-sub">Consejo Académico: <?= $rn['academico_fecha'] ? e(date('d/m/Y', strtotime($rn['academico_fecha']))) : '—' ?> · Consejo de Gobierno: <?= $rn['gobierno_fecha'] ? e(date('d/m/Y', strtotime($rn['gobierno_fecha']))) : '—' ?></p>
        </div>
        <?php if ($rn['actas']): ?><a class="btn btn-primario" href="<?= e(url('consejos/comisiones/' . $rn['id'] . '/actas')) ?>">Descargar acta del Consejo de Gobierno y acta completa asociada</a><?php endif; ?>
      </div>
      <?php foreach ($rn['asuntos'] as $a): ?>
        <article class="cns-res" style="margin-top:10px">
          <strong><span class="cns-clave"><?= e($a['clave']) ?></span> · <?= e($grupo[$a['grupo']]) ?></strong>
          <p><?= e($a['resumen']) ?></p>
          <?php if (trim((string) $a['acuerdo']) !== ''): ?><p><small>Acuerdo:</small> <?= e($a['acuerdo']) ?></p><?php endif; ?>
          <p class="cns-acciones">
            <?php if ($a['dictamenes']): foreach ($a['dictamenes'] as $d): ?>
              <a class="btn btn-mini" href="<?= e(url('consejos/archivo/' . $d['id'])) ?>" target="_blank" rel="noopener">📄 Ver dictamen firmado</a>
              <a class="btn btn-mini" href="<?= e(url('consejos/archivo/' . $d['id'] . '?descargar=1')) ?>">Descargar</a>
            <?php endforeach; else: ?>
              <small class="cns-sub">El dictamen firmado aún no está disponible.</small>
            <?php endif; ?>
          </p>
        </article>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>
</div>
