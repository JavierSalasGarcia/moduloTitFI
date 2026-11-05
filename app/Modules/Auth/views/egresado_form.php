<?php use App\Core\Auth; $flash = flash_leer(); $f = $_SESSION['_egr_form'] ?? []; ?>
<div class="auth-panel"><section class="auth-tarjeta"><h1>Acceso para egresados</h1>
<p>¿Ya cancelaron tu correo institucional y tu titulación sigue en trámite? Solicita aquí tu acceso.</p>
<p class="flash flash-alerta" role="note" style="margin:8px 0"><strong>Importante:</strong> con un correo que no es institucional <strong>solo podrás usar el módulo de Titulación</strong>. Tu acceso se activa cuando el personal del Departamento de Evaluación Profesional valide tu identidad; no se crea ninguna cuenta antes.</p>
<?php if ($flash): ?><p role="status" class="flash flash-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></p><?php endif; ?>
<form method="post" action="<?= url('registro/egresado') ?>" class="formulario" autocomplete="on">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
<div style="position:absolute;left:-9999px" aria-hidden="true"><label>No llenes este campo<input name="sitio_web" tabindex="-1" autocomplete="off"></label></div>
<label>Nombre completo<input name="nombre" type="text" autocomplete="name" required minlength="8" maxlength="120" value="<?= e((string) ($f['nombre'] ?? '')) ?>"><small>Como aparece en tus documentos de la Facultad.</small></label>
<label>Género<select name="genero" required><option value="">Elige…</option><?php foreach (['masculino' => 'Masculino', 'femenino' => 'Femenino', 'no_binario' => 'No binario'] as $k => $n): ?><option value="<?= e($k) ?>" <?= ($f['genero'] ?? '') === $k ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?></select><small>Para dirigirnos a ti correctamente en oficios y correos.</small></label>
<label>Licenciatura cursada<select name="carrera" required><option value="">Elige…</option><?php foreach ($carreras as $k => $c): ?><option value="<?= e($k) ?>" <?= ($f['carrera'] ?? '') === $k ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?></select></label>
<label>Número de cuenta<input name="cuenta" type="text" inputmode="numeric" required pattern="[0-9]{6,8}" maxlength="8" placeholder="Ej. 1234567"></label>
<label>Correo personal<input name="email" type="email" autocomplete="email" required maxlength="120" value="<?= e((string) ($f['email'] ?? '')) ?>"><small>Te enviaremos un código para comprobar que es tuyo. Será el correo con el que inicies sesión.</small></label>
<label>Teléfono (opcional)<input name="telefono" type="tel" autocomplete="tel" maxlength="20" value="<?= e((string) ($f['telefono'] ?? '')) ?>"></label>
<label style="flex-direction:row;gap:8px;align-items:flex-start"><input type="checkbox" name="aviso" value="1" required style="margin-top:4px"><span>Entiendo que mi acceso será <strong>solo al módulo de Titulación</strong>, que el personal validará mi identidad (si es necesario, con una identificación oficial y un comprobante de que fui alumno), que esos archivos se borran al resolver la solicitud y que el acceso vence tras 24 meses sin uso.</span></label>
<div style="display:flex;flex-direction:column;gap:16px;margin-top:18px"><button class="btn btn-primario btn-bloque" type="submit">Enviar solicitud</button><a href="<?= url('login') ?>" style="text-align:center">Volver al inicio de sesión</a></div>
</form></section></div>
