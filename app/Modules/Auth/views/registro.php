<?php use App\Core\Auth; $flash=flash_leer(); ?>
<div class="auth-panel"><section class="auth-tarjeta"><h1>Crea tu cuenta</h1><p>Todo a la mano, menos trámites y más claridad.</p>
<?php if($flash): ?><p role="status" class="flash flash-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></p><?php endif; ?>
<form method="post" action="<?= url('registro') ?>" class="formulario">
<input type="hidden" name="_csrf" value="<?= e(Auth::csrfToken()) ?>">
<label>Nombre completo<input name="nombre" type="text" autocomplete="name" required maxlength="120"></label>
<fieldset id="bloque-profesor" hidden style="border:1px solid var(--gris-200,#ddd);border-radius:8px;padding:12px">
<legend>Tu nombre coincide con el directorio de profesores</legend>
<label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="es_profesor" id="es-profesor" value="1"> Soy profesor(a) de la Facultad de Ingeniería</label>
<div id="bloque-posgrado" hidden style="margin-top:8px">
<label style="display:flex;gap:8px;align-items:center;font-weight:400"><input type="checkbox" name="es_estudiante_posgrado" id="es-posgrado" value="1"> También estudio un posgrado en la Facultad</label>
<label id="etiqueta-posgrado" hidden>Posgrado que estudias<select name="posgrado_id" id="posgrado-id"><option value="">Elige…</option><?php foreach (\App\Core\Profesores::posgrados() as $pg): ?><option value="<?= (int) $pg['id'] ?>"><?= e($pg['siglas'] . ' · ' . $pg['nombre']) ?></option><?php endforeach; ?></select></label>
<small>Tu cuenta quedará asociada a ambos: como profesor y como estudiante de ese posgrado. Recursos Humanos confirma que eres el profesor del directorio.</small>
</div></fieldset>
<script>(function(){var n=document.querySelector('[name=nombre]'),b=document.getElementById('bloque-profesor'),p=document.getElementById('es-profesor'),bp=document.getElementById('bloque-posgrado'),e=document.getElementById('es-posgrado'),et=document.getElementById('etiqueta-posgrado'),s=document.getElementById('posgrado-id'),t;
function actual(){bp.hidden=!p.checked;if(!p.checked){e.checked=false}et.hidden=!(p.checked&&e.checked);s.required=p.checked&&e.checked}
function revisar(){var v=n.value.trim();if(v.split(/\s+/).length<2){b.hidden=true;p.checked=false;actual();return}
fetch('<?= url('registro/profesor') ?>?nombre='+encodeURIComponent(v),{headers:{Accept:'application/json'}}).then(function(r){return r.json()}).then(function(j){b.hidden=!j.coincide;if(!j.coincide){p.checked=false}actual()}).catch(function(){})}
n.addEventListener('input',function(){clearTimeout(t);t=setTimeout(revisar,500)});n.addEventListener('blur',revisar);p.addEventListener('change',actual);e.addEventListener('change',actual);revisar()})();</script>
<label>Grado académico (opcional)<input name="grado" type="text" maxlength="30" list="grados-lista" placeholder="Ej. Dr., Mtra., Ing."><small>Se antepone a tu nombre en oficios y correos.</small></label>
<datalist id="grados-lista"><?php foreach (\App\Core\Persona::GRADOS as $g): ?><option value="<?= e($g) ?>"><?php endforeach; ?></datalist>
<label>Género<select name="genero" required><option value="">Elige…</option><option value="masculino">Masculino</option><option value="femenino">Femenino</option><option value="no_binario">No binario</option></select><small>Para dirigirnos a ti correctamente en oficios y correos.</small></label>
<label>Correo electrónico UAEMEX<input name="email" type="email" autocomplete="email" value="<?= e((string) ($_SESSION['_registro_email'] ?? '')) ?>" required maxlength="75" pattern="[^@\s]+@(?:[A-Za-z0-9-]+\.)*[uU][aA][eE][mM][eE][xX]\.[mM][xX]" title="Usa un correo de uaemex.mx o de uno de sus subdominios" placeholder="usuario@alumno.uaemex.mx"><small>Se admiten uaemex.mx y sus subdominios, por ejemplo alumno.uaemex.mx o profesor.uaemex.mx.</small></label>
<p class="flash flash-error" role="alert" style="margin:4px 0"><strong>IMPORTANTE: NO uses la misma contraseña que en tu correo institucional.</strong> Crea una contraseña distinta y exclusiva para esta plataforma.</p>
<label>Contraseña<input name="clave" type="password" autocomplete="new-password" minlength="10" maxlength="72" required><small>Al menos 10 caracteres. Debe ser diferente a la de tu correo institucional.</small></label>
<label>Confirma tu contraseña<input name="confirmacion" type="password" autocomplete="new-password" minlength="10" maxlength="72" required></label>
<p class="ayuda-form">Podrás consultar la plataforma desde el inicio. Para escribir, verifica tu correo con el código que te enviaremos. Los permisos administrativos los asigna la administración.</p>
<div style="display:flex;flex-direction:column;gap:16px;margin-top:18px"><button class="btn btn-primario btn-bloque" type="submit">Crear cuenta</button><a href="<?= url('login') ?>" style="text-align:center">Ya tengo cuenta · Iniciar sesión</a><a href="<?= url('registro/egresado') ?>" style="text-align:center">¿Ya no tienes correo institucional? Solicita acceso solo a Titulación</a></div>
</form></section></div>
