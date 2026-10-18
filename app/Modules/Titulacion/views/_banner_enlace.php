<?php /** Banner de enlace personal. Variable: $banner (['tipo','email']) o null. */ ?>
<?php if (!empty($banner)): ?>
  <div class="tit-banner" role="status">
    <?php if ($banner['tipo'] === 'registro'): ?>
      <strong>Estás usando tu enlace personal. Para firmar necesitas registrarte.</strong>
      Puedes leer todo ahora. Para firmar, crea tu cuenta con <b><?= e($banner['email']) ?></b>.
      <div style="margin-top:8px"><a class="btn btn-primario btn-mini" href="<?= url('registro') ?>">Crear mi cuenta</a> <a class="btn btn-mini" href="<?= url('login') ?>">Ya tengo cuenta</a></div>
    <?php elseif ($banner['tipo'] === 'login'): ?>
      <strong>Ya existe una cuenta con tu correo. Inicia sesión para firmar.</strong>
      <div style="margin-top:8px"><a class="btn btn-primario btn-mini" href="<?= url('login') ?>">Iniciar sesión</a></div>
    <?php elseif ($banner['tipo'] === 'otra'): ?>
      <strong>Tu sesión es de otra cuenta.</strong> Este enlace se envió a <b><?= e($banner['email']) ?></b>; inicia sesión con ese correo para firmar.
    <?php else: ?>
      <strong>Verifica tu correo para firmar.</strong> <a href="<?= url('cuenta/verificar') ?>">Verificar ahora</a>
    <?php endif; ?>
  </div>
<?php endif; ?>
