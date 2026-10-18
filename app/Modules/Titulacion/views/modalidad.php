<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/licenciatura') ?>">← Opciones de titulación</a></p>
    <h1><?= e($m['nombre']) ?></h1>
    <p class="texto-secundario"><?= e($m['tipo'] === 'sin_trabajo' ? 'Sin trabajo escrito' : 'Con trabajo escrito') ?> · <?= e($m['art']) ?></p>
  </div>
</div>

<div class="tit-dos">
  <div>
    <section class="tarjeta" style="margin-bottom:18px">
      <h2>¿En qué consiste?</h2>
      <p><?= e($m['resumen']) ?></p>
      <h3>Requisitos para esta opción</h3>
      <ul class="tit-lista-simple">
        <?php foreach ($m['requisitos'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
      </ul>
    </section>

    <section class="tarjeta">
      <h2>Así será tu trámite</h2>
      <ol class="tit-lista-simple">
        <?php foreach ($etapas as $e): ?><li><strong><?= e($e['titulo']) ?></strong><br><span class="texto-secundario"><?= e($e['descripcion'] ?? ($e['personal']['descripcion'] ?? '')) ?></span></li><?php endforeach; ?>
      </ol>
    </section>
  </div>

  <aside style="display:flex;flex-direction:column;gap:18px">
    <section class="tarjeta">
      <h2 style="font-size:16px">Costos de referencia</h2>
      <ul class="tit-lista-simple">
        <?php if ($m['tipo'] === 'con_trabajo'): ?>
          <li>Evaluación profesional con trabajo escrito: $<?= number_format($costos['evaluacion_trabajo']) ?></li>
        <?php else: ?>
          <li>Toma de protesta: $<?= number_format($costos['toma_protesta']) ?></li>
        <?php endif; ?>
        <li>Expedición de título: $<?= number_format($costos['titulo']) ?></li>
        <li>Cada constancia de no adeudo: $<?= number_format($costos['constancia']) ?></li>
      </ul>
      <p class="texto-secundario" style="font-size:12px">Sujetos a cambio según lo determine la UAEM.</p>
    </section>

    <section class="tarjeta">
      <h2 style="font-size:16px">Iniciar trámite</h2>
      <?php if (!$puedeIniciar): ?>
        <p class="texto-secundario">Inicia sesión con tu cuenta de pasante para comenzar.</p>
      <?php elseif ($abierto): ?>
        <p class="tit-aviso">Ya tienes el trámite <strong><?= e($abierto['folio']) ?></strong> en proceso. Termínalo o cancélalo para iniciar otro.</p>
        <a class="btn btn-primario" href="<?= url('titulacion/licenciatura/tramite/' . (int) $abierto['id']) ?>">Continuar mi trámite</a>
      <?php else: ?>
        <form class="formulario" method="post" action="<?= url('titulacion/licenciatura/modalidad/' . $clave) ?>">
          <input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
          <?php if (!empty($m['variantes'])): ?>
            <fieldset style="border:0;padding:0;margin:0 0 8px">
              <legend><strong>Convocatoria</strong></legend>
              <?php foreach ($m['variantes'] as $v => $et): ?>
                <label style="display:flex;gap:8px;align-items:flex-start;margin:6px 0"><input type="radio" name="variante" value="<?= e($v) ?>" required> <span><?= e($et) ?></span></label>
              <?php endforeach; ?>
              <span class="ayuda-form">El resultado solo es reconocido si presentas el EGEL en sede UAEM.</span>
            </fieldset>
          <?php endif; ?>
          <label style="display:flex;gap:8px;align-items:flex-start"><input type="checkbox" name="acepto" value="1" required>
            <span>Leí el <a href="<?= url('titulacion/licenciatura/blanco/privacidad') ?>">aviso de privacidad</a> y acepto que mis datos y documentos se usen para este trámite.</span></label>
          <div class="acciones-formulario"><button class="btn btn-primario" type="submit">Iniciar mi trámite</button></div>
        </form>
      <?php endif; ?>
    </section>
  </aside>
</div>
