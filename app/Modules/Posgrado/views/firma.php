<?php
/**
 * Página de firma de un documento del trámite de grado (por enlace personal o con sesión del mismo correo).
 * Muestra solo lo necesario de ESE documento: egresado, programa, título, qué se firma, el PDF y, en el visto bueno,
 * las tres preguntas y la vigencia de la firma electrónica UAEMéx.
 */
use App\Core\Auth;
use App\Modules\Perfil\FirmaUaemex;
use App\Modules\Posgrado\Catalogo;

include __DIR__ . '/_estilos.php';
$esVisto = $f['documento'] === 'visto_bueno';
$esAcuse = $conOficio;
$firmada = $f['estado'] === 'firmada';
$csrf = '<input type="hidden" name="_csrf" value="' . e(Auth::csrfToken()) . '">';
$ayudaFise = 'Puedes verificar la fecha de validez de tu firma entrando a ';
?>
<section class="tarjeta" style="max-width:860px;margin:16px auto">
  <p class="pos-paso-etiqueta"><?= $esAcuse ? 'Invitación al sínodo' : 'Firma en línea' ?></p>
  <h2 style="margin-top:0"><?= e($documento) ?></h2>
  <dl class="pos-dl">
    <dt>Egresado(a)</dt><dd><?= e($alumno) ?></dd>
    <dt>Programa</dt><dd><?= e($programa) ?></dd>
    <dt>Trabajo de grado</dt><dd>«<?= e($tituloTrabajo) ?>»</dd>
    <dt>Firmas como</dt><dd><?= e($comoQuien) ?> · <?= e((string) $f['nombre']) ?></dd>
    <?php if (($esVisto || $esAcuse) && $fechaExamen !== ''): ?><dt><?= $esAcuse ? 'Fecha del examen' : 'Fecha propuesta del examen' ?></dt><dd><?= e(\App\Modules\Posgrado\Flujo::fechaLarga($fechaExamen)) ?></dd><?php endif; ?>
  </dl>

  <div class="pos-firma-doc">
    <?php if ($esAcuse): ?>
      <p style="margin:6px 0 0">La Coordinación de Estudios Avanzados le invita a integrar el sínodo. Lea su oficio de invitación y acuse de recibido.</p>
      <p><a class="btn btn-primario" href="<?= url($base . '/oficio') ?>" target="_blank" rel="noopener noreferrer">Ver mi oficio de invitación (PDF)</a>
        <a class="btn" href="<?= url($base . '/pdf') ?>" target="_blank" rel="noopener noreferrer">Ver el acuse de recibido (PDF)</a></p>
    <?php else: ?>
      <p style="margin:6px 0 0">Se le pide firmar <?= e($que) ?>. Léalo antes de firmar.</p>
      <iframe class="pos-visor" src="<?= url($base . '/pdf') ?>" title="<?= e($documento) ?>"></iframe>
      <p><a class="btn" href="<?= url($base . '/pdf') ?>" target="_blank" rel="noopener noreferrer">Abrir o descargar el PDF</a></p>
    <?php endif; ?>
  </div>

  <?php if ($firmada): ?>
    <div class="pos-ok"><strong><?= $esAcuse ? 'Ya acusaste de recibido' : 'Ya firmaste este documento' ?></strong> el <?= e(date('d/m/Y', strtotime((string) $f['firmada_en']))) ?> a las <?= e(date('H:i', strtotime((string) $f['firmada_en']))) ?> horas. No hay nada más que hacer. Puedes descargar el documento con tu sello en el botón de arriba.</div>
    <?php if ($esVisto): ?>
      <p style="font-size:14px"><?php foreach (Catalogo::preguntasVistoBueno() as $k => $p): ?><?= e($p) ?>: <strong><?= ($respuestas[$k] ?? '') === 'si' ? 'Sí' : 'No' ?></strong> · <?php endforeach; ?>
        Vigencia declarada de tu firma electrónica UAEMéx: <strong><?= e(FirmaUaemex::fechaLarga((string) ($respuestas['vigencia'] ?? ''))) ?></strong></p>
      <?php if (!$alcanza): ?>
        <div class="pos-alerta" role="alert" id="vigencia"><strong>Tu firma electrónica UAEMéx vence el <?= e(FirmaUaemex::fechaLarga((string) ($respuestas['vigencia'] ?? ''))) ?> y no alcanza.</strong>
          Para la fecha del examen debe estar vigente al menos hasta el <strong><?= e(FirmaUaemex::fechaLarga($requerida)) ?></strong> (un mes después). Renuévala en
          <a href="<?= e(FirmaUaemex::URL) ?>" target="_blank" rel="noopener noreferrer">https://fise.uaemex.mx/</a> y vuelve a este enlace para actualizar la fecha. Mientras tanto no se puede programar el examen. No necesitas volver a firmar el visto bueno.</div>
      <?php endif; ?>
      <?php if ($abierto): ?>
      <details class="pos-grupo" <?= $alcanza ? '' : 'open' ?>>
        <summary>Actualizar la vigencia de mi firma electrónica UAEMéx</summary>
        <form class="formulario" method="post" action="<?= url($base . '/vigencia') ?>">
          <?= $csrf ?>
          <label>Vigencia de mi firma electrónica UAEMéx<input type="date" name="vigencia" required min="2000-01-01" max="2100-12-31" value="<?= e((string) ($respuestas['vigencia'] ?? '')) ?>">
            <small class="texto-secundario" style="display:block;font-weight:400"><?= e($ayudaFise) ?><a href="<?= e(FirmaUaemex::URL) ?>" target="_blank" rel="noopener noreferrer">https://fise.uaemex.mx/</a></small></label>
          <button class="btn btn-primario" type="submit">Actualizar la fecha</button>
        </form>
      </details>
      <?php endif; ?>
    <?php endif; ?>
  <?php elseif (!$abierto): ?>
    <p class="pos-nota">Este documento ya no está en firma.</p>
  <?php else: ?>
    <form class="formulario" method="post" action="<?= url($base . '/firmar') ?>">
      <?= $csrf ?>
      <?php if ($esVisto): ?>
        <fieldset class="pos-fieldset">
          <legend>Su visto bueno respecto a</legend>
          <?php foreach (Catalogo::preguntasVistoBueno() as $k => $p): ?>
            <div class="pos-pregunta" role="radiogroup" aria-label="<?= e($p) ?>"><span><?= e($p) ?></span>
              <span class="pos-radios"><label><input type="radio" name="r_<?= e($k) ?>" value="si" required> Sí</label><label><input type="radio" name="r_<?= e($k) ?>" value="no"> No</label></span></div>
          <?php endforeach; ?>
        </fieldset>
        <fieldset class="pos-fieldset">
          <legend>Vigencia de su firma electrónica UAEMéx</legend>
          <p class="texto-secundario" style="margin:0 0 6px;font-size:13px">Es la firma institucional con la que el sínodo firma el acta en el SICDE de Control Escolar. Debe estar vigente al menos hasta el <strong><?= e($requerida !== '' ? FirmaUaemex::fechaLarga($requerida) : '—') ?></strong> (un mes después de la fecha propuesta del examen).</p>
          <label>Fecha de vigencia de mi firma electrónica UAEMéx<input type="date" name="vigencia" required min="2000-01-01" max="2100-12-31" value="<?= e((string) ($vigencia ?? '')) ?>">
            <small class="texto-secundario" style="display:block;font-weight:400"><?= e($ayudaFise) ?><a href="<?= e(FirmaUaemex::URL) ?>" target="_blank" rel="noopener noreferrer">https://fise.uaemex.mx/</a></small></label>
        </fieldset>
      <?php endif; ?>
      <label class="pos-check" style="margin:8px 0"><input type="checkbox" name="leido" value="1" required> He leído el documento</label>
      <p class="texto-secundario" style="font-size:13px">Al <?= $esAcuse ? 'acusar de recibido' : 'firmar' ?> se estampa su sello electrónico en el documento y se registran su correo, la fecha y la hora, su dirección IP, su navegador y la huella SHA-256 del documento.</p>
      <button class="btn btn-primario" type="submit"><?= $esAcuse ? 'Acusar de recibido' : 'Firmar' ?></button>
    </form>
  <?php endif; ?>
  <p class="texto-secundario" style="font-size:12px;margin-top:16px">Este enlace es personal; no lo comparta. Dudas: Apoyo a Grado de la Coordinación de Estudios Avanzados, Facultad de Ingeniería.</p>
</section>
