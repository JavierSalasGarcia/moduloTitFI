<?php use App\Core\Auth; ?>
<section class="card" style="max-width:560px"><h2>Confirma tu dirección de correo</h2>
<?php if($pendiente): ?><p>Introduce el código de 6 dígitos enviado a <strong><?= e(Auth::usuario()['email']) ?></strong>. Vence en 10 minutos.</p>
<form method="post" action="<?= url('cuenta/verificar') ?>" class="formulario"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><label>Código de verificación<input type="text" name="codigo" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required></label><button type="submit" class="btn btn-primario">Verificar y habilitar escritura</button></form>
<form method="post" action="<?= url('cuenta/reenviar') ?>" style="margin-top:16px"><input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>"><button type="submit" class="btn">Enviar otro código</button></form><p class="ayuda-form">Hasta verificarlo puedes navegar en modo lectura. Revisa tu carpeta de spam.</p>
<?php else: ?><p>Tu cuenta ya tiene habilitado el acceso de escritura según sus permisos.</p><?php endif; ?><a href="<?= url('') ?>">Volver al inicio</a></section>
