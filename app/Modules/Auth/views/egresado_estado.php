<?php use App\Core\Auth; $flash = flash_leer(); $base = url('registro/egresado/s/' . $token); $est = $s['estado']; $csrf = e(Auth::csrfToken()); ?>
<div class="auth-panel"><section class="auth-tarjeta"><h1>Solicitud de acceso</h1>
<p class="texto-suave"><?= e($s['nombre']) ?> · cuenta <?= e($s['cuenta']) ?> · <?= e($s['email']) ?></p>
<?php if ($flash): ?><p role="status" class="flash flash-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></p><?php endif; ?>

<?php if ($est === 'sin_verificar'): ?>
  <p>Escribe el código de 6 dígitos que enviamos a <strong><?= e($s['email']) ?></strong>. Vence en 10 minutos.</p>
  <form method="post" action="<?= e($base) ?>/codigo" class="formulario"><input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label>Código<input type="text" name="codigo" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required autofocus></label>
    <button class="btn btn-primario" type="submit">Verificar correo</button></form>
  <form method="post" action="<?= e($base) ?>/reenviar" style="margin-top:10px"><input type="hidden" name="_csrf" value="<?= $csrf ?>"><button class="btn" type="submit">Enviar otro código</button></form>

<?php elseif ($est === 'requiere_docs'): ?>
  <p>Tu correo está verificado. Para validar tu identidad necesitamos dos documentos (PDF, JPG o PNG, hasta 5 MB cada uno):</p>
  <?php if (!empty($s['motivo'])): ?><p class="flash flash-alerta"><strong>Observación del personal:</strong> <?= e($s['motivo']) ?></p><?php endif; ?>
  <ul class="texto-suave"><li>Identificación oficial vigente (INE, pasaporte o cédula).</li><li>Comprobante de que fuiste alumno de la Facultad: historial académico, constancia de terminación de estudios o carta de pasante.</li></ul>
  <form method="post" action="<?= e($base) ?>/documentos" enctype="multipart/form-data" class="formulario"><input type="hidden" name="_csrf" value="<?= $csrf ?>">
    <label>Identificación oficial <?= isset($docs['identificacion']) ? '(ya enviada; puedes reemplazarla)' : '' ?><input type="file" name="identificacion" accept=".pdf,.jpg,.jpeg,.png" <?= isset($docs['identificacion']) ? '' : 'required' ?>></label>
    <label>Comprobante de estudios <?= isset($docs['comprobante']) ? '(ya enviado; puedes reemplazarlo)' : '' ?><input type="file" name="comprobante" accept=".pdf,.jpg,.jpeg,.png" <?= isset($docs['comprobante']) ? '' : 'required' ?>></label>
    <button class="btn btn-primario" type="submit">Enviar documentos</button></form>
  <p class="ayuda-form">Solo el personal de Titulación los ve. Se borran del servidor cuando se resuelve tu solicitud.</p>

<?php elseif ($est === 'pendiente'): ?>
  <p class="flash flash-exito">Tu solicitud está en revisión por el personal del Departamento de Evaluación Profesional. Te avisaremos por correo a <?= e($s['email']) ?>.</p>
  <p class="ayuda-form">Puedes cerrar esta página; el enlace de tu correo sigue funcionando.</p>

<?php elseif ($est === 'aprobada'): ?>
  <?php if ($s['clave_expira'] && strtotime((string) $s['clave_expira']) > time()): ?>
    <p>Tu identidad fue validada. Fija la contraseña con la que entrarás (distinta a la de tu correo).</p>
    <form method="post" action="<?= e($base) ?>/clave" class="formulario"><input type="hidden" name="_csrf" value="<?= $csrf ?>">
      <label>Contraseña<input name="clave" type="password" autocomplete="new-password" minlength="10" maxlength="72" required><small>Al menos 10 caracteres.</small></label>
      <label>Confirma tu contraseña<input name="confirmacion" type="password" autocomplete="new-password" minlength="10" maxlength="72" required></label>
      <button class="btn btn-primario" type="submit">Guardar contraseña</button></form>
  <?php else: ?>
    <p class="flash flash-error">El enlace para fijar tu contraseña venció. Acude al Departamento de Evaluación Profesional para que te lo envíen de nuevo.</p>
  <?php endif; ?>

<?php elseif ($est === 'activa'): ?>
  <p class="flash flash-exito">Tu acceso ya está activo.</p><p><a class="btn btn-primario" href="<?= url('login') ?>">Iniciar sesión</a></p>

<?php else: ?>
  <p class="flash flash-error">Tu solicitud no fue aprobada.<?= !empty($s['motivo']) ? ' Motivo: ' . e($s['motivo']) : '' ?></p>
  <p class="ayuda-form">Si crees que es un error, acude al Departamento de Evaluación Profesional.</p>
<?php endif; ?>
<p style="margin-top:16px"><a href="<?= url('login') ?>">Volver al inicio de sesión</a></p>
</section></div>
