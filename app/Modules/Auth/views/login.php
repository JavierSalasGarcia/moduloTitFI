<?php use App\Core\Auth; $flash = flash_leer(); $csrf = Auth::csrfToken(); ?>
<div class="auth-panel">
  <div class="auth-tarjeta">
    <div class="auth-marca">
      <div style="width:72px;height:72px;margin:0 auto 12px;background:url('<?= url('assets/img/logo.png') ?>') no-repeat center/cover;border-radius:50%;border:2px solid var(--gris-200)"></div>
      <h1>Traza FI</h1>
      <p class="texto-suave">Menos trámites. Todo a la mano y con seguimiento.</p>
      <p class="ayuda-form">Acceso para la comunidad de la Facultad de Ingeniería.</p>
    </div>

    <?php if ($flash): ?>
      <div class="flash flash-<?= e($flash['tipo']) ?>"><?= e($flash['mensaje']) ?></div>
    <?php endif; ?>

    <ol aria-label="Progreso del inicio de sesión" style="display:flex;list-style:none;padding:0;margin:20px 0;gap:8px">
      <?php foreach ([1 => 'Correo', 2 => 'Contraseña', 3 => 'Verificación'] as $numero => $etiqueta): ?>
        <li id="progreso-<?= $numero ?>" style="flex:1;text-align:center;font-size:11px;color:var(--gris-400);border-bottom:2px solid var(--gris-100);padding-bottom:7px">
          <?= $numero ?>. <?= e($etiqueta) ?>
        </li>
      <?php endforeach; ?>
    </ol>

    <div id="login-mensaje" class="flash flash-error" role="alert" style="display:none"></div>

    <section id="paso-correo" aria-labelledby="titulo-correo">
      <form class="formulario" id="form-correo" novalidate>
        <label id="titulo-correo" for="email-login">Correo institucional</label>
        <input type="text" id="email-login" name="email" required autofocus maxlength="75" inputmode="email" autocomplete="username" autocapitalize="none" spellcheck="false" aria-label="Correo institucional" placeholder="usuario@alumno.uaemex.mx">
        <small>Usa tu cuenta institucional UAEMEX.</small>
        <button type="submit" class="btn btn-primario btn-bloque" id="btn-correo" style="margin-top:16px">Continuar</button>
      </form>
      <p style="text-align:center;margin:16px 0 0"><button type="button" class="enlace" id="abrir-reset" style="background:none;border:0;cursor:pointer">¿Olvidaste tu contraseña?</button></p>
      <div id="panel-reset" class="formulario" style="display:none;margin-top:14px;border-top:1px solid var(--gris-100);padding-top:14px">
        <h3 style="font-size:14px">Restablecer contraseña</h3>
        <p class="texto-suave">Primero escribe arriba tu correo institucional. Enviaremos ahí un código de 6 dígitos.</p>
        <label id="reset-codigo-label" hidden>Código recibido
          <input type="text" id="reset-codigo" maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="6 dígitos">
        </label>
        <label id="reset-clave-label" hidden>Nueva contraseña
          <input type="password" id="reset-clave" minlength="10" maxlength="72" autocomplete="new-password" placeholder="Mínimo 10 caracteres">
        </label>
        <button type="button" class="btn btn-primario btn-bloque" id="btn-reset-solicitar">Enviar código</button>
        <button type="button" class="btn btn-bloque" id="btn-reset-confirmar" style="display:none;margin-top:8px">Verificar código</button>
        <div id="reset-msg" class="texto-suave" role="status" style="margin-top:8px"></div>
      </div>
    </section>

    <section id="paso-clave" aria-labelledby="titulo-clave" hidden>
      <form class="formulario" id="form-clave">
        <button type="button" class="enlace" data-reiniciar style="background:none;border:0;padding:0;cursor:pointer">← Cambiar correo</button>
        <h2 id="titulo-clave" style="font-size:17px;margin:14px 0 6px">Ahora, tu contraseña</h2>
        <p class="texto-suave">Cuenta: <strong id="correo-confirmado"></strong></p>
        <label for="clave-login">Contraseña de Traza FI</label>
        <input type="password" id="clave-login" name="clave" required maxlength="1024" autocomplete="current-password" placeholder="••••••••">
        <button type="button" id="mostrar-clave" class="enlace" aria-pressed="false" style="display:block;margin:8px 0 0 auto;padding:2px;border:0;background:none;cursor:pointer">Mostrar contraseña</button>
        <p class="ayuda-form">Es la contraseña de Traza FI; no debe ser la contraseña de tu correo institucional.</p>
        <label for="permanecer-sesion" style="display:flex;align-items:flex-start;gap:10px;margin-top:16px;cursor:pointer">
          <input type="checkbox" id="permanecer-sesion" name="permanecer" value="1" style="margin-top:4px">
          <span>
            <strong style="display:block">Mantener mi sesión abierta durante 5 días</strong>
            <small class="texto-suave">Úsalo sólo en un equipo personal. En equipos compartidos, déjalo desactivado.</small>
          </span>
        </label>
        <button type="submit" class="btn btn-primario btn-bloque" id="btn-clave" style="margin-top:14px">Continuar</button>
      </form>
    </section>

    <section id="paso-mfa" aria-labelledby="titulo-mfa" hidden>
      <div class="formulario">
        <button type="button" class="enlace" data-reiniciar style="background:none;border:0;padding:0;cursor:pointer">← Empezar de nuevo</button>
        <h2 id="titulo-mfa" style="font-size:17px;margin:14px 0 6px">Verificación en dos pasos</h2>
        <p class="texto-suave">Tu contraseña ya fue validada. Completa ahora un segundo factor.</p>
        <div id="aviso-configurar-mfa" style="display:none;background:#fffbeb;border:1px solid #f3d58b;color:#8a5600;padding:12px;border-radius:6px;font-size:12.5px;line-height:1.45;margin:12px 0">
          Al entrar, registra tu huella/rostro o aplicación autenticadora desde <strong>Menú → Mi seguridad</strong>. Traza FI no almacena tu huella; el dispositivo conserva la llave.
        </div>
        <div id="metodos-mfa" style="display:grid;gap:9px;margin-top:14px">
          <button type="button" class="btn btn-bloque" id="btn-passkey">Usar huella o rostro</button>
          <button type="button" class="btn btn-bloque" id="btn-totp">Usar aplicación autenticadora</button>
          <button type="button" class="btn btn-bloque" id="btn-otp">Recibir código en mi correo</button>
        </div>

        <div id="panel-codigo" hidden style="margin-top:16px">
          <p id="instruccion-codigo" class="texto-suave"></p>
          <label for="codigo-mfa">Código de 6 dígitos</label>
          <input type="text" id="codigo-mfa" inputmode="numeric" autocomplete="one-time-code" maxlength="7" pattern="[0-9 ]{6,7}" placeholder="000 000" style="text-align:center;font-size:18px;letter-spacing:4px;font-weight:600">
          <button type="button" class="btn btn-primario btn-bloque" id="btn-verificar-codigo" style="margin-top:12px">Verificar y entrar</button>
          <button type="button" class="btn btn-bloque" id="btn-otro-metodo" style="margin-top:8px;background:transparent">Elegir otro método</button>
        </div>
      </div>
    </section>

    <div style="text-align:center;margin-top:20px;font-size:12px"><a href="<?= url('registro') ?>">Crear mi cuenta</a></div>
    <details style="margin-top:18px;font-size:11px;color:var(--gris-500)">
      <summary>Aviso sobre datos biométricos</summary>
      <p>Traza FI no recibe ni almacena imágenes de huellas o rostros. Windows Hello, Touch ID, Face ID o el bloqueo del dispositivo verifican tu identidad localmente y entregan únicamente una firma criptográfica.</p>
    </details>
    <details style="margin-top:10px;font-size:11px;color:var(--gris-500)">
      <summary>Aviso de Privacidad Integral (ARCO)</summary>
      <p><strong>Responsable:</strong> la Facultad de Ingeniería de la Universidad Autónoma del Estado de México protege los datos personales de sus usuarios conforme a la normatividad aplicable.</p>
      <p><strong>Origen y estado de la plataforma:</strong> Traza FI es una plataforma creada y operada por el Dr. Javier Salas García. Actualmente está en trámite para su uso oficial en la Facultad de Ingeniería de la UAEMEX.</p>
      <p>Traza FI no almacena datos biométricos ni copia archivos de Microsoft Teams; únicamente conserva llaves públicas y referencias necesarias para prestar sus funciones.</p>
      <p><strong>Concursos y formularios temporales:</strong> Cuando participas en un concurso o llenas un formulario temporal de la Facultad (por ejemplo, el registro de profesores para clasificaciones externas de programas académicos), Traza FI usa los datos que proporcionas (nombre, correo, la obra que envías y, en los formularios, los datos de contacto que se solicitan) únicamente para la finalidad de esa convocatoria: registrar y revisar tu participación, avisarte de su estado, publicar los nombres de quienes ganen y, en los registros para instituciones externas, entregarlos a la institución que realiza la clasificación. En las votaciones anónimas no se guarda tu identidad junto con tu voto: solo que ya votaste y tu recibo de voto. Estos datos se conservan mientras dura la actividad y el tiempo necesario para su comprobación; después se archivan o se eliminan.</p>
      <p>Para ejercer los derechos de acceso, rectificación, cancelación u oposición, presenta una solicitud ante la Unidad de Transparencia de la Facultad de Ingeniería o mediante el correo oficial de soporte.</p>
    </details>
  </div>
</div>

<script>
(() => {
  const csrf = <?= json_encode($csrf) ?>;
  const endpoints = {
    correo: <?= json_encode(url('login/consultar-permiso-contrasena')) ?>,
    clave: <?= json_encode(url('login/verificar-primer-paso')) ?>,
    challenge: <?= json_encode(url('login/biometria-challenge')) ?>,
    passkey: <?= json_encode(url('login/biometria')) ?>,
    solicitarOtp: <?= json_encode(url('login/solicitar-otp')) ?>,
    verificarOtp: <?= json_encode(url('login/verificar-otp')) ?>,
    totp: <?= json_encode(url('login/totp')) ?>,
    resetSolicitar: <?= json_encode(url('login/solicitar-restablecimiento')) ?>,
    resetVerificar: <?= json_encode(url('login/verificar-restablecimiento')) ?>,
    resetGuardar: <?= json_encode(url('login/restablecer-contrasena')) ?>
  };
  let emailCanonico = '';
  let tipoCodigo = '';
  let resetVerificado = false;

  const $ = id => document.getElementById(id);
  const mensaje = (texto = '') => {
    $('login-mensaje').textContent = texto;
    $('login-mensaje').style.display = texto ? 'block' : 'none';
  };
  const cargando = (boton, activo, texto) => {
    if (!boton.dataset.texto) boton.dataset.texto = boton.textContent;
    boton.disabled = activo;
    boton.textContent = activo ? texto : boton.dataset.texto;
  };
  const datos = objeto => {
    const form = new FormData();
    form.append('_csrf', csrf);
    Object.entries(objeto).forEach(([k, v]) => form.append(k, v));
    return form;
  };
  async function peticion(url, objeto = {}, metodo = 'POST') {
    const opciones = {method: metodo, headers: {'Accept': 'application/json'}};
    if (metodo === 'POST') opciones.body = datos(objeto);
    const respuesta = await fetch(url, opciones);
    const tipo = respuesta.headers.get('content-type') || '';
    if (!tipo.includes('application/json')) throw new Error('La sesión expiró. Recarga la página.');
    return respuesta.json();
  }
  function mostrarPaso(numero) {
    ['correo', 'clave', 'mfa'].forEach((nombre, indice) => $('paso-' + nombre).hidden = indice + 1 !== numero);
    [1, 2, 3].forEach(i => {
      const activo = i === numero;
      $('progreso-' + i).style.color = activo ? 'var(--verde-700)' : 'var(--gris-400)';
      $('progreso-' + i).style.borderBottomColor = activo ? 'var(--verde-700)' : 'var(--gris-100)';
      $('progreso-' + i).style.fontWeight = activo ? '700' : '400';
    });
    mensaje();
  }
  function entrar(resultado) {
    window.location.assign(resultado.redirect || <?= json_encode(url('')) ?>);
  }

  $('form-correo').addEventListener('submit', async event => {
    event.preventDefault();
    const boton = $('btn-correo');
    const entrada = $('email-login').value.trim();
    if (!entrada) { mensaje('Escribe tu correo institucional.'); $('email-login').focus(); return; }
    cargando(boton, true, 'Verificando…');
    try {
      const r = await peticion(endpoints.correo, {email: entrada});
      if (!r.exito) { mensaje(r.error); return; }
      emailCanonico = r.email;
      $('correo-confirmado').textContent = emailCanonico;
      mostrarPaso(2);
      $('clave-login').focus();
    } catch (e) { mensaje(e.message || 'No fue posible verificar la cuenta.'); }
    finally { cargando(boton, false); }
  });

  $('mostrar-clave').addEventListener('click', event => {
    const campo = $('clave-login');
    const mostrar = campo.type === 'password';
    campo.type = mostrar ? 'text' : 'password';
    campo.style.webkitTextSecurity = mostrar ? 'none' : '';
    event.currentTarget.textContent = mostrar ? 'Ocultar contraseña' : 'Mostrar contraseña';
    event.currentTarget.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
    campo.focus();
  });
  $('form-clave').addEventListener('submit', async event => {
    event.preventDefault();
    const boton = $('btn-clave');
    const clave = $('clave-login').value;
    if (!clave) { mensaje('Escribe tu contraseña de Traza FI.'); return; }
    cargando(boton, true, 'Verificando…');
    try {
      const permanecer = $('permanecer-sesion').checked ? '1' : '0';
      const r = await peticion(endpoints.clave, {clave, permanecer});
      if (!r.exito) { mensaje(r.error); return; }
      if (r.redirect) { entrar(r); return; }
      $('btn-passkey').style.display = r.metodos.passkey ? 'block' : 'none';
      $('btn-totp').style.display = r.metodos.totp ? 'block' : 'none';
      $('btn-otp').style.display = r.metodos.correo ? 'block' : 'none';
      $('aviso-configurar-mfa').style.display = r.configuracion_pendiente ? 'block' : 'none';
      $('clave-login').value = '';
      mostrarPaso(3);
    } catch (e) { mensaje(e.message || 'No fue posible validar la contraseña.'); }
    finally { cargando(boton, false); }
  });

  function base64urlBytes(valor) {
    const base64 = valor.replace(/-/g, '+').replace(/_/g, '/') + '='.repeat((4 - valor.length % 4) % 4);
    return Uint8Array.from(atob(base64), c => c.charCodeAt(0));
  }
  function bytesBase64url(buffer) {
    let binario = '';
    new Uint8Array(buffer).forEach(byte => binario += String.fromCharCode(byte));
    return btoa(binario).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
  }
  $('btn-passkey').addEventListener('click', async () => {
    const boton = $('btn-passkey');
    if (!window.PublicKeyCredential || !navigator.credentials) { mensaje('Este navegador o dispositivo no admite llaves de acceso.'); return; }
    cargando(boton, true, 'Esperando al dispositivo…');
    try {
      const reto = await peticion(endpoints.challenge, {}, 'GET');
      if (!reto.exito) { mensaje(reto.error); return; }
      const credencial = await navigator.credentials.get({publicKey: {
        challenge: base64urlBytes(reto.challenge),
        rpId: reto.rp_id,
        allowCredentials: reto.credenciales.map(id => ({type: 'public-key', id: base64urlBytes(id)})),
        userVerification: 'required',
        timeout: 60000
      }});
      const r = await peticion(endpoints.passkey, {
        id: credencial.id,
        clientDataJSON: bytesBase64url(credencial.response.clientDataJSON),
        authenticatorData: bytesBase64url(credencial.response.authenticatorData),
        signature: bytesBase64url(credencial.response.signature)
      });
      if (!r.exito) { mensaje(r.error); return; }
      entrar(r);
    } catch (e) {
      mensaje(e.name === 'NotAllowedError' ? 'La verificación fue cancelada o agotó el tiempo.' : (e.message || 'No fue posible usar la huella o el rostro.'));
    } finally { cargando(boton, false); }
  });

  function abrirCodigo(tipo, instruccion) {
    tipoCodigo = tipo;
    $('metodos-mfa').style.display = 'none';
    $('panel-codigo').hidden = false;
    $('instruccion-codigo').textContent = instruccion;
    $('codigo-mfa').value = '';
    $('codigo-mfa').focus();
    mensaje();
  }
  $('btn-totp').addEventListener('click', () => abrirCodigo('totp', 'Abre la aplicación autenticadora vinculada desde Menú → Mi seguridad y escribe el código vigente.'));
  $('btn-otp').addEventListener('click', async () => {
    const boton = $('btn-otp');
    cargando(boton, true, 'Enviando…');
    try {
      const r = await peticion(endpoints.solicitarOtp);
      if (!r.exito) { mensaje(r.error); return; }
      abrirCodigo('correo', 'Enviamos un código a ' + emailCanonico + '. Revisa también la carpeta de correo no deseado.');
    } catch (e) { mensaje(e.message || 'No fue posible enviar el código.'); }
    finally { cargando(boton, false); }
  });
  $('btn-otro-metodo').addEventListener('click', () => {
    $('panel-codigo').hidden = true;
    $('metodos-mfa').style.display = 'grid';
    mensaje();
  });
  $('btn-verificar-codigo').addEventListener('click', async () => {
    const codigo = $('codigo-mfa').value.replace(/\s/g, '');
    if (!/^\d{6}$/.test(codigo)) { mensaje('Escribe exactamente los 6 dígitos.'); return; }
    const boton = $('btn-verificar-codigo');
    cargando(boton, true, 'Verificando…');
    try {
      const r = await peticion(tipoCodigo === 'totp' ? endpoints.totp : endpoints.verificarOtp, {codigo});
      if (!r.exito) { mensaje(r.error); return; }
      entrar(r);
    } catch (e) { mensaje(e.message || 'No fue posible verificar el código.'); }
    finally { cargando(boton, false); }
  });
  $('codigo-mfa').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); $('btn-verificar-codigo').click(); } });
  document.querySelectorAll('[data-reiniciar]').forEach(boton => boton.addEventListener('click', () => window.location.reload()));

  $('abrir-reset').addEventListener('click', () => { $('panel-reset').style.display = 'block'; $('email-login').focus(); });
  $('btn-reset-solicitar').addEventListener('click', async () => {
    const boton = $('btn-reset-solicitar');
    const email = $('email-login').value.trim();
    if (!email) { $('reset-msg').textContent = 'Escribe arriba tu correo institucional.'; return; }
    cargando(boton, true, 'Enviando…');
    try {
      const r = await peticion(endpoints.resetSolicitar, {email});
      $('reset-msg').textContent = r.exito ? (r.mensaje || 'Si la cuenta existe, recibirás un código de recuperación.') : r.error;
      if (r.exito) {
        $('email-login').readOnly = true;
        boton.style.display = 'none';
        $('reset-codigo-label').hidden = false;
        $('btn-reset-confirmar').style.display = 'block';
        $('reset-codigo').focus();
      }
    } catch (e) { $('reset-msg').textContent = e.message; }
    finally { cargando(boton, false); }
  });
  $('btn-reset-confirmar').addEventListener('click', async () => {
    const boton = $('btn-reset-confirmar');
    cargando(boton, true, 'Procesando…');
    try {
      if (!resetVerificado) {
        const codigo = $('reset-codigo').value.trim();
        if (!/^\d{6}$/.test(codigo)) { $('reset-msg').textContent = 'Introduce los 6 dígitos.'; return; }
        const r = await peticion(endpoints.resetVerificar, {email: $('email-login').value.trim(), codigo});
        if (!r.exito) { $('reset-msg').textContent = r.error; return; }
        resetVerificado = true;
        $('reset-codigo-label').hidden = true;
        $('reset-clave-label').hidden = false;
        boton.dataset.texto = 'Guardar nueva contraseña';
        $('reset-msg').textContent = 'Código verificado. Elige una nueva contraseña.';
        $('reset-clave').focus();
        return;
      }
      const clave = $('reset-clave').value;
      if (clave.length < 10 || clave.length > 72) { $('reset-msg').textContent = 'Usa entre 10 y 72 caracteres.'; return; }
      const r = await peticion(endpoints.resetGuardar, {clave});
      $('reset-msg').textContent = r.exito ? 'Contraseña actualizada. Ya puedes iniciar sesión.' : r.error;
      if (r.exito) setTimeout(() => window.location.reload(), 1000);
    } catch (e) { $('reset-msg').textContent = e.message; }
    finally { cargando(boton, false); }
  });

  mostrarPaso(1);
})();
</script>
