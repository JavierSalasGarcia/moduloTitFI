<?php use App\Core\Auth; $flash=flash_leer(); ?>
<div class="auth-panel"><section class="auth-tarjeta"><h1>Crea tu cuenta</h1><p>Todo a la mano, menos trámites y más claridad.</p>
<?php if($flash): ?><p role="status" class="flash flash-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></p><?php endif; ?>
<form method="post" action="<?= url('registro') ?>" class="formulario">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
<label>Nombre completo<input name="nombre" type="text" autocomplete="name" required maxlength="120"></label>
<label>Grado académico (opcional)<input name="grado" type="text" maxlength="30" list="grados-lista" placeholder="Ej. Dr., Mtra., Ing."><small>Se antepone a tu nombre en oficios y correos.</small></label>
<datalist id="grados-lista"><?php foreach (\App\Core\Persona::GRADOS as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
<label>Género<select name="genero" required><option value="">Elige…</option><option value="masculino">Masculino</option><option value="femenino">Femenino</option><option value="no_binario">No binario</option></select><small>Para dirigirnos a ti correctamente en oficios y correos.</small></label>
<label>Correo electrónico UAEMEX<input name="email" type="email" autocomplete="email" value="<?= e((string) ($_SESSION['_registro_email'] ?? '')) ?>" required maxlength="75" pattern="[^@\s]+@(?:[A-Za-z0-9-]+\.)*[uU][aA][eE][mM][eE][xX]\.[mM][xX]" title="Usa un correo de uaemex.mx o de uno de sus subdominios" placeholder="usuario@alumno.uaemex.mx"><small>Se admiten uaemex.mx y sus subdominios, por ejemplo alumno.uaemex.mx o profesor.uaemex.mx.</small></label>
<p class="flash flash-error" role="alert" style="margin:4px 0"><strong>IMPORTANTE: NO uses la misma contraseña que en tu correo institucional.</strong> Crea una contraseña distinta y exclusiva para esta plataforma.</p>
<label>Contraseña<input name="clave" type="password" autocomplete="new-password" minlength="10" maxlength="72" required><small>Al menos 10 caracteres. Debe ser diferente a la de tu correo institucional.</small></label>
<label>Confirma tu contraseña<input name="confirmacion" type="password" autocomplete="new-password" minlength="10" maxlength="72" required></label>
<p class="ayuda-form">Podrás consultar la plataforma desde el inicio. Para escribir, verifica tu correo con el código que te enviaremos. Los permisos administrativos los asigna la administración.</p>
<div style="display:flex;flex-direction:column;gap:16px;margin-top:18px"><button class="btn btn-primario btn-bloque" type="submit">Crear cuenta</button><a href="<?= url('login') ?>" style="text-align:center">Ya tengo cuenta · Iniciar sesión</a></div>
</form></section></div>
