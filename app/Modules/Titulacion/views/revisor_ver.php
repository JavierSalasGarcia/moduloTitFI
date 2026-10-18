<?php
use App\Core\Auth;
include __DIR__ . '/_estilos.php';
$csrf = e(Auth::csrfToken());
$firmada = $a['estado'] === 'firmada';
?>
<?php if ($banner): ?>
  <div class="tit-banner" role="status">
    <?php if ($banner['tipo'] === 'registro'): ?>
      <strong>Estás revisando con tu enlace personal. Para firmar necesitas registrarte.</strong>
      Puedes leer el protocolo y redactar tus recomendaciones ahora; se guardan como borrador. Para firmar, crea tu cuenta con <b><?= e($banner['email']) ?></b>.
      <div style="margin-top:8px"><a class="btn btn-primario btn-mini" href="<?= url('registro') ?>">Crear mi cuenta</a> <a class="btn btn-mini" href="<?= url('login') ?>">Ya tengo cuenta</a></div>
    <?php elseif ($banner['tipo'] === 'login'): ?>
      <strong>Ya existe una cuenta con tu correo. Inicia sesión para firmar.</strong>
      Mientras tanto puedes redactar tus recomendaciones con este enlace; se guardan como borrador.
      <div style="margin-top:8px"><a class="btn btn-primario btn-mini" href="<?= url('login') ?>">Iniciar sesión</a></div>
    <?php elseif ($banner['tipo'] === 'otra'): ?>
      <strong>Tu sesión es de otra cuenta.</strong> Esta revisión se asignó a <b><?= e($banner['email']) ?></b>; para firmarla inicia sesión con ese correo.
    <?php else: ?>
      <strong>Verifica tu correo para firmar.</strong> Tu cuenta aún no está verificada. <a href="<?= url('cuenta/verificar') ?>">Verificar ahora</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="tit-cabecera"><div>
  <?php if ($modo === 'sesion'): ?><p class="texto-secundario" style="margin:0"><a href="<?= url('titulacion/revision') ?>">← Protocolos por evaluar</a></p><?php endif; ?>
  <h2 style="margin:4px 0">Revisión de protocolo · <?= e($r['folio']) ?> <small class="texto-secundario">(<?= (int) $r['numero'] ?>ª revisión)</small></h2>
  <p class="texto-secundario" style="margin:0"><?= e($carrera) ?> · <?= e($modalidad) ?></p>
</div>
<div style="text-align:right"><?php include __DIR__ . '/_semaforo.php'; ?><br><span class="texto-secundario" style="font-size:13px">Fecha límite: <strong><?= e(date('d/m/Y', strtotime((string) $a['fecha_limite']))) ?></strong></span></div></div>

<section class="tarjeta" style="margin-bottom:16px">
  <dl style="display:grid;grid-template-columns:150px 1fr;gap:6px 14px;margin:0">
    <dt><strong>Pasante</strong></dt><dd style="margin:0"><?= e((string) $alumno) ?></dd>
    <dt><strong>Título</strong></dt><dd style="margin:0"><?= e((string) $r['titulo']) ?></dd>
    <dt><strong>Recibido</strong></dt><dd style="margin:0"><?= e(fecha_corta((string) $r['fecha_recepcion'])) ?></dd>
  </dl>
  <p style="margin:12px 0 0"><a class="btn btn-primario" href="<?= url($base . '/documento') ?>" target="_blank" rel="noopener">Abrir el protocolo (PDF)</a></p>
</section>

<section class="tarjeta">
  <h2>Tu dictamen</h2>
  <?php if ($firmada): ?>
    <p class="tit-ok">Firmaste esta revisión el <?= e(fecha_corta((string) $a['firmada_en'])) ?>. Ya la recibió el encargado de la comisión.</p>
    <p><strong><?= $a['propuesta'] === 'aprobado' ? 'Se aprueba' : 'No se aprueba' ?></strong> · Requiere revisión del coordinador: <?= (int) $a['requiere_coord'] === 1 ? 'Sí' : 'No' ?></p>
    <?php if ($a['forma']): ?><p><strong>Forma:</strong> <?= nl2br(e((string) $a['forma'])) ?></p><?php endif; ?>
    <?php if ($a['fondo']): ?><p><strong>Fondo:</strong> <?= nl2br(e((string) $a['fondo'])) ?></p><?php endif; ?>
  <?php elseif (!$editable): ?>
    <p class="tit-aviso">Esta revisión ya no admite cambios.</p>
  <?php else: ?>
    <form class="formulario" method="post" action="<?= url($base . '/guardar') ?>" id="form-revision">
      <input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <div class="tit-campos">
        <fieldset style="border:0;padding:0"><legend><strong>Dictamen del protocolo</strong></legend>
          <label style="display:flex;gap:6px"><input type="radio" name="propuesta" value="aprobado" <?= $a['propuesta'] === 'aprobado' ? 'checked' : '' ?>> Se aprueba</label>
          <label style="display:flex;gap:6px"><input type="radio" name="propuesta" value="no_aprobado" <?= $a['propuesta'] === 'no_aprobado' ? 'checked' : '' ?>> No se aprueba</label></fieldset>
        <fieldset style="border:0;padding:0"><legend><strong>Requiere revisión del coordinador</strong></legend>
          <label style="display:flex;gap:6px"><input type="radio" name="requiere_coord" value="no" <?= $a['requiere_coord'] !== null && (int) $a['requiere_coord'] === 0 ? 'checked' : '' ?>> No</label>
          <label style="display:flex;gap:6px"><input type="radio" name="requiere_coord" value="si" <?= (int) ($a['requiere_coord'] ?? 0) === 1 ? 'checked' : '' ?>> Sí</label></fieldset>
      </div>
      <label>Recomendaciones de <strong>forma</strong> (redacción, estructura, referencias, formato)
        <textarea name="forma" rows="5" maxlength="2500"><?= e((string) $a['forma']) ?></textarea></label>
      <label>Recomendaciones de <strong>fondo</strong> (planteamiento, justificación, objetivos, metodología, alcance)
        <textarea name="fondo" rows="6" maxlength="2500"><?= e((string) $a['fondo']) ?></textarea></label>
      <p class="texto-secundario" style="font-size:13px">Entre ambos campos caben unos 1,900 caracteres en el formato del dictamen (aprox. 7 renglones largos).</p>
      <?php if ($puedeFirmar && $nivelFirma === 'pades'): ?>
        <label>Código de 6 dígitos de tu app de autenticación (firma PAdES) <input type="text" name="codigo_2fa" inputmode="numeric" maxlength="6" style="max-width:200px"></label>
      <?php endif; ?>
      <div class="acciones-formulario" style="display:flex;gap:10px;flex-wrap:wrap">
        <button class="btn" type="submit">Guardar borrador</button>
        <button class="btn btn-primario" type="submit" formaction="<?= url($base . '/firmar') ?>" <?= $puedeFirmar ? '' : 'title="Necesitas una cuenta verificada para firmar"' ?>>Firmar y enviar</button>
      </div>
      <?php if (!$puedeFirmar): ?><p class="texto-secundario" style="font-size:13px">«Firmar y enviar» guarda tu borrador y te lleva a iniciar sesión o registrarte.</p><?php endif; ?>
    </form>
  <?php endif; ?>
</section>
