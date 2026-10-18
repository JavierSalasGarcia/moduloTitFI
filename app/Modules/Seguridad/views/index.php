<div style="max-width: 800px; margin: 0 auto; padding-top: 10px;">
  <p style="color: var(--gris-600); margin-bottom: 20px; font-size: 14px;">
    Elige una forma adicional de acceso para proteger tu cuenta. Puedes usar el desbloqueo de tu dispositivo o una aplicación de códigos.
  </p>

  <ol class="ux-progress-steps" aria-label="Cómo configurar el acceso"><li>1. Elige un método</li><li>2. Registra tu dispositivo o app</li><li>3. Confirma que funciona</li></ol>
  <!-- TARJETA DE BIOMETRÍA -->
  <div class="card" style="padding: 24px; background: #fff; border: 1px solid var(--gris-200); border-radius: var(--radio); margin-bottom: 24px;">
    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 15px;">
      <div>
        <h3 style="margin: 0; font-size: 15px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 6px;">👤 Dispositivos Biométricos (Huella / Rostro / Windows Hello)</h3>
        <p style="margin: 4px 0 0 0; font-size: 12.5px; color: var(--gris-600);">
          Registra el lector de huellas o reconocimiento facial de este dispositivo para iniciar sesión al instante sin contraseñas.
        </p>
      </div>
      <button type="button" class="btn" onclick="registrarEsteDispositivoBiometrico()" style="border-color: #0f6cbd; color: #0f6cbd; font-weight: 600; font-size: 12.5px; padding: 6px 12px;">+ Registrar dispositivo</button>
    </header>

    <div id="lista-biometria-vacia" style="padding: 20px; text-align: center; border: 1px dashed var(--gris-200); border-radius: 6px; color: var(--gris-500); font-size: 13px; <?= empty($biometria) ? '' : 'display: none;' ?>">
      No tienes ningún dispositivo biométrico registrado todavía.
    </div>

    <table class="tabla" id="tabla-biometria" style="width: 100%; border-collapse: collapse; text-align: left; <?= empty($biometria) ? 'display: none;' : '' ?>">
      <thead>
        <tr style="border-bottom: 1px solid var(--gris-200); font-size: 12px; color: var(--gris-500);">
          <th style="padding: 8px 12px;">Nombre del Dispositivo</th>
          <th style="padding: 8px 12px;">Fecha de Registro</th>
          <th style="padding: 8px 12px; text-align: right;">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($biometria as $bio): ?>
          <tr id="bio-fila-<?= $bio['id'] ?>" style="border-bottom: 1px solid var(--gris-100); font-size: 13px;">
            <td style="padding: 10px 12px; font-weight: 500; color: #1e293b;"><?= e($bio['alias']) ?></td>
            <td style="padding: 10px 12px; color: var(--gris-600);"><?= e($bio['creado_en']) ?></td>
            <td style="padding: 10px 12px; text-align: right;">
              <button type="button" class="btn btn-mini btn-peligro" onclick="eliminarDispositivoBiometrico(<?= $bio['id'] ?>)">Eliminar</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- TARJETA DE TOTP (AUTHENTICATOR) -->
  <div class="card" style="padding: 24px; background: #fff; border: 1px solid var(--gris-200); border-radius: var(--radio); margin-bottom: 24px;">
    <header style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 15px;">
      <div>
        <h3 style="margin: 0; font-size: 15px; font-weight: 600; color: #1e293b; display: flex; align-items: center; gap: 6px;">🔑 Aplicación de Autenticación (Microsoft / Google Authenticator)</h3>
        <p style="margin: 4px 0 0 0; font-size: 12.5px; color: var(--gris-600);">
          Configura códigos temporales de 6 dígitos que cambian constantemente en tu celular como una capa extra de seguridad.
        </p>
      </div>
      
      <?php if ($hasTotp): ?>
        <button type="button" class="btn btn-peligro" onclick="desvincularTotp()" style="font-size: 12.5px; padding: 6px 12px;">Desvincular Aplicación</button>
      <?php else: ?>
        <button type="button" class="btn" onclick="iniciarVinculacionTotp()" style="border-color: #0f6cbd; color: #0f6cbd; font-weight: 600; font-size: 12.5px; padding: 6px 12px;">+ Vincular Aplicación</button>
      <?php endif; ?>
    </header>

    <div style="padding: 15px; border-radius: 6px; border: 1px solid <?= $hasTotp ? '#bae6fd' : 'var(--gris-200)' ?>; background: <?= $hasTotp ? '#f0f9ff' : '#fafafa' ?>; display: flex; align-items: center; gap: 10px; font-size: 13.5px; color: <?= $hasTotp ? '#0369a1' : 'var(--gris-600)' ?>;">
      <span><?= $hasTotp ? '🛡️' : '⚠️' ?></span>
      <span>
        <?= $hasTotp 
          ? '<strong>Aplicación de autenticación vinculada correctamente.</strong> Puedes utilizar Microsoft Authenticator para iniciar sesión.' 
          : 'La aplicación de autenticación no está configurada.' 
        ?>
      </span>
    </div>
  </div>
</div>

<!-- MODAL VINCULAR TOTP -->
<div id="modal-vincular-totp" class="modal-fondo" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.4); z-index:1000; align-items:center; justify-content:center;">
  <div class="card" style="width:100%; max-width:420px; padding:24px; position:relative; background:#fff; border-radius: var(--radio);">
    <header class="tarjeta-cabecera" style="margin-bottom:15px; display:flex; justify-content:space-between; align-items:center;">
      <h2 style="margin:0; font-size:16px; font-weight:600;">Configurar Authenticator App</h2>
      <button type="button" class="btn btn-mini" onclick="cerrarModalTotp()" style="border:none; background:none; font-size:20px; cursor:pointer;">&times;</button>
    </header>
    
    <div style="font-size: 13px; color: var(--gris-700); margin-bottom: 12px;">
      1. En Microsoft Authenticator o Google Authenticator elige añadir una cuenta y escanea este código:
    </div>
    
    <img id="totp-qr-img" alt="Código para vincular tu aplicación de autenticación" style="display:block; margin:15px auto; width:180px; height:180px; border:1px solid #e2e8f0; padding:5px; border-radius:4px;">
    
    <div style="text-align:center; font-size:12px; color:var(--gris-500); margin-bottom:15px;">
      Clave de configuración manual:<br>
      <strong id="totp-secret-text" style="font-family:monospace; font-size:14px; color:#1e293b; letter-spacing:1px;"></strong>
    </div>

    <div style="font-size: 13px; color: var(--gris-700); margin-bottom: 10px;">
      2. Escribe el código de 6 dígitos que muestra tu app para confirmar:
    </div>

    <label style="margin-bottom:12px;">
      <input type="text" id="totp-codigo-verificacion" placeholder="000 000" maxlength="6" style="text-align: center; font-size: 18px; letter-spacing: 4px; font-weight: 600;">
    </label>

    <div id="totp-error-div" style="color: #c62828; font-size: 12.5px; margin-bottom: 12px; display: none;"></div>

    <div class="acciones-formulario" style="display:flex; justify-content:flex-end; gap:8px; margin-top:15px;">
      <button type="button" class="btn" onclick="cerrarModalTotp()">Cancelar</button>
      <button type="button" class="btn btn-primario" onclick="confirmarVinculacionTotp()">Activar y Vincular</button>
    </div>
  </div>
</div>

<script>
// Utilidades WebAuthn
function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);
  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

function bufferToBase64url(buffer) {
  const binary = String.fromCharCode.apply(null, new Uint8Array(buffer));
  return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function bufferToPem(buffer) {
  const binary = String.fromCharCode.apply(null, new Uint8Array(buffer));
  const base64 = window.btoa(binary);
  return `-----BEGIN PUBLIC KEY-----\n${base64.match(/.{1,64}/g).join('\n')}\n-----END PUBLIC KEY-----`;
}

async function registrarEsteDispositivoBiometrico() {
  const alias = prompt("Ingresa un nombre identificable para este dispositivo (ej: Mi Celular, Laptop Oficina):", "Mi Dispositivo");
  if (!alias) return;

  try {
    const resChallenge = await fetch('<?= url("seguridad/biometria/registro-challenge") ?>');
    const challengeData = await resChallenge.json();

    const challengeBytes = urlBase64ToUint8Array(challengeData.challenge);
    const userIdBytes = new TextEncoder().encode(challengeData.user.id.toString());

    const publicKeyOptions = {
      challenge: challengeBytes,
      rp: { name: "Plataforma FI" },
      user: {
        id: userIdBytes,
        name: challengeData.user.name,
        displayName: challengeData.user.displayName
      },
      pubKeyCredParams: [
        { type: "public-key", alg: -7 },   // ES256
        { type: "public-key", alg: -257 }  // RS256
      ],
      authenticatorSelection: {
        userVerification: "required"
      },
      timeout: 60000
    };

    const credential = await navigator.credentials.create({ publicKey: publicKeyOptions });
    if (!credential) {
      alert("No se recibió credencial.");
      return;
    }

    const credentialId = bufferToBase64url(credential.rawId);
    
    let publicKeyPem = '';
    if (typeof credential.response.getPublicKey === 'function') {
      const pubKeyBuffer = credential.response.getPublicKey();
      publicKeyPem = bufferToPem(pubKeyBuffer);
    } else {
      alert("Este navegador no soporta la extracción directa de la llave pública. Usa Chrome, Edge, Firefox o Safari.");
      return;
    }

    const form = new FormData();
    form.append('_csrf', '<?= \App\Core\Auth::csrfToken() ?>');
    form.append('id', credentialId);
    form.append('publicKeyPem', publicKeyPem);
    form.append('alias', alias);

    const saveRes = await fetch('<?= url("seguridad/biometria/registrar") ?>', {
      method: 'POST',
      body: form
    });

    const saveResult = await saveRes.json();
    if (saveResult.exito) {
      alert(saveResult.mensaje);
      window.location.reload();
    } else {
      alert("Error al guardar dispositivo: " + saveResult.error);
    }
  } catch (err) {
    console.error(err);
    alert("No se pudo completar el registro: " + err.message);
  }
}

async function eliminarDispositivoBiometrico(id) {
  if (!confirm("¿Seguro que deseas eliminar este dispositivo biométrico? Ya no podrás iniciar sesión con él.")) return;

  try {
    const form = new FormData();
    form.append('_csrf', '<?= \App\Core\Auth::csrfToken() ?>');
    form.append('id', id);
    
    const res = await fetch('<?= url("seguridad/biometria/eliminar") ?>', {
      method: 'POST',
      body: form
    });
    const result = await res.json();
    if (result.exito) {
      alert(result.mensaje);
      window.location.reload();
    } else {
      alert("Error: " + result.error);
    }
  } catch (err) {
    alert("Error al eliminar: " + err.message);
  }
}

async function iniciarVinculacionTotp() {
  try {
    const res = await fetch('<?= url("seguridad/totp/generar") ?>');
    const data = await res.json();
    
    document.getElementById('totp-qr-img').src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(data.uri)}`;
    document.getElementById('totp-secret-text').textContent = data.secret;
    document.getElementById('totp-error-div').style.display = 'none';
    document.getElementById('totp-codigo-verificacion').value = '';
    
    document.getElementById('modal-vincular-totp').style.display = 'flex';
  } catch (err) {
    alert("Error al iniciar vinculación: " + err.message);
  }
}

function cerrarModalTotp() {
  document.getElementById('modal-vincular-totp').style.display = 'none';
}

async function confirmarVinculacionTotp() {
  const codigo = document.getElementById('totp-codigo-verificacion').value.trim();
  const errorDiv = document.getElementById('totp-error-div');
  
  if (codigo === '') {
    errorDiv.textContent = 'Ingresa el código de 6 dígitos.';
    errorDiv.style.display = 'block';
    return;
  }

  errorDiv.style.display = 'none';

  try {
    const form = new FormData();
    form.append('_csrf', '<?= \App\Core\Auth::csrfToken() ?>');
    form.append('codigo', codigo);
    
    const res = await fetch('<?= url("seguridad/totp/vincular") ?>', {
      method: 'POST',
      body: form
    });
    
    const result = await res.json();
    if (result.exito) {
      alert(result.mensaje);
      window.location.reload();
    } else {
      errorDiv.textContent = result.error;
      errorDiv.style.display = 'block';
    }
  } catch (err) {
    errorDiv.textContent = 'Error: ' + err.message;
    errorDiv.style.display = 'block';
  }
}

async function desvincularTotp() {
  if (!confirm("¿Seguro que deseas desvincular la aplicación de autenticación? Se desactivará este segundo factor de seguridad.")) return;
  
  try {
    const form = new FormData();
    form.append('_csrf', '<?= \App\Core\Auth::csrfToken() ?>');
    
    const res = await fetch('<?= url("seguridad/totp/desvincular") ?>', {
      method: 'POST',
      body: form
    });
    const result = await res.json();
    if (result.exito) {
      alert(result.mensaje);
      window.location.reload();
    } else {
      alert("Error: " + result.error);
    }
  } catch (err) {
    alert("Error: " + err.message);
  }
}

// Cerrar modales al hacer clic afuera
document.querySelectorAll('.modal-fondo').forEach(modal => {
  modal.addEventListener('click', function(e) {
    if (e.target === this) {
      this.style.display = 'none';
    }
  });
});
</script>
