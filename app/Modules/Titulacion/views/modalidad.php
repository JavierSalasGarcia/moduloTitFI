<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';

// Los «momentos» agrupan las etapas del catálogo (no cambian): cada clave de etapa cae en un momento.
if ($m['tipo'] === 'con_trabajo') {
    $momentos = [
        ['t' => 'Registras tus datos y tu protocolo', 'f' => 'Capturas tus datos una sola vez y propones el tema de tu trabajo con tu asesor.', 'c' => ['datos', 'protocolo']],
        ['t' => 'Desarrollas y revisas tu trabajo', 'f' => 'Escribes tu trabajo, lo mandas a revisión y atiendes las correcciones.', 'c' => ['desarrollo', 'revision', 'sustentacion']],
        ['t' => 'Reúnes tus documentos', 'f' => 'Subes en PDF lo que pide la Facultad y entregas los originales en Apoyo a Titulación.', 'c' => ['final']],
        ['t' => 'Examen y resultado', 'f' => 'Eliges fecha, presentas tu evaluación y te avisamos el resultado.', 'c' => ['fecha', 'resultado']],
    ];
} else {
    $momentos = [
        ['t' => 'Registras tus datos', 'f' => 'Los capturas una sola vez; con ellos se llenan tus formatos.', 'c' => ['datos']],
        ['t' => 'Presentas tu solicitud', 'f' => 'Generas la solicitud de tu opción y subes los documentos que la respaldan.', 'c' => ['solicitud', 'ceneval', 'estudios']],
        ['t' => 'Reúnes tus documentos', 'f' => 'Subes en PDF lo que pide la Facultad y entregas los originales en Apoyo a Titulación.', 'c' => ['protesta']],
        ['t' => 'Toma de protesta y resultado', 'f' => 'Apoyo a Titulación te da fecha y lugar; después se registra tu resultado.', 'c' => ['fecha', 'resultado']],
    ];
}
$asignadas = [];
foreach ($etapas as $et) {
    $destino = count($momentos) - 1;
    foreach ($momentos as $i => $mo) {
        if (in_array($et['clave'], $mo['c'], true)) { $destino = $i; break; }
    }
    $asignadas[$destino][] = $et['titulo'];
}
?>
<div class="tit-cabecera">
  <div>
    <p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/licenciatura') ?>">← Opciones de titulación</a></p>
    <h1><?= e($m['nombre']) ?></h1>
    <p class="texto-secundario"><?= e($m['tipo'] === 'sin_trabajo' ? 'Sin trabajo escrito' : 'Con trabajo escrito') ?> · <?= e($m['art']) ?></p>
    <p style="margin:6px 0 0;max-width:680px"><?= e($m['resumen']) ?></p>
  </div>
</div>

<div class="tit-dos">
  <div>
    <section class="tit-necesitas" aria-labelledby="tit-nec-t">
      <h2 id="tit-nec-t">Qué necesitas para empezar hoy</h2>
      <p style="margin:0">El primer paso es solo capturar tus datos. Ten a la mano:</p>
      <ul>
        <li>Tu nombre completo, tal como debe aparecer en el título.</li>
        <li>Tu número de cuenta y la clave de tu plan de estudios.</li>
        <li>Tus años de ingreso y de egreso.</li>
        <li>Un teléfono y tus correos, el institucional y uno personal.</li>
      </ul>

      <?php if (!$puedeIniciar): ?>
        <p class="texto-secundario" style="margin-top:12px">Inicia sesión con tu cuenta de pasante para comenzar.</p>
      <?php elseif ($abierto): ?>
        <p class="tit-aviso" style="margin-top:12px">Ya tienes el trámite <strong><?= e($abierto['folio']) ?></strong> en proceso. Termínalo o cancélalo para iniciar otro.</p>
        <a class="btn btn-primario" href="<?= url('titulacion/licenciatura/tramite/' . (int) $abierto['id']) ?>">Continuar mi trámite</a>
      <?php else: ?>
        <form class="formulario" style="margin-top:12px" method="post" action="<?= url('titulacion/licenciatura/modalidad/' . $clave) ?>">
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
          <div class="acciones-formulario"><button class="tit-boton" type="submit">Iniciar mi trámite</button></div>
        </form>
      <?php endif; ?>
    </section>

    <section class="tarjeta" style="margin-bottom:18px">
      <h2>Así será tu trámite</h2>
      <ol class="tit-momentos">
        <?php foreach ($momentos as $i => $mo): if (empty($asignadas[$i])) continue; ?>
          <li><strong><?= e($mo['t']) ?></strong><?= e($mo['f']) ?>
            <span style="display:block;margin-top:2px;font-size:12.5px">Etapas: <?= e(implode(' · ', $asignadas[$i])) ?></span></li>
        <?php endforeach; ?>
      </ol>
    </section>

    <details class="tit-pliegue">
      <summary>Ver todos los requisitos</summary>
      <div class="tit-cuerpo">
        <h3 style="margin-top:0">Requisitos para esta opción</h3>
        <ul class="tit-lista-simple">
          <?php foreach ($m['requisitos'] as $r): ?><li><?= e($r) ?></li><?php endforeach; ?>
        </ul>
        <h3>Etapas, una por una</h3>
        <ol class="tit-lista-simple">
          <?php foreach ($etapas as $e): ?><li><strong><?= e($e['titulo']) ?></strong><br><span class="texto-secundario"><?= e($e['descripcion'] ?? ($e['personal']['descripcion'] ?? '')) ?></span></li><?php endforeach; ?>
        </ol>
      </div>
    </details>
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
  </aside>
</div>
