<?php
declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\{AccessControl, App, Auth, Controller, Database, Totp, WebAuthn};

final class AuthController extends Controller
{
    public function formulario(): void
    {
        if (Auth::usuario()) {
            redirigir(Auth::consumirDestino());
        }
        header('Cache-Control: no-store, private');
        $this->vista('login', ['titulo' => 'Iniciar sesión'], 'auth');
    }

    public function entrar(): void
    {
        // El acceso debe recorrer los endpoints por pasos. Esta ruta se conserva
        // para formularios antiguos, pero nunca omite la verificación adicional.
        flash('error', 'Por seguridad, completa el ingreso paso a paso.');
        redirigir('login');
    }

    public function salir(): void
    {
        Auth::salir();
        redirigir('login');
    }

    public function biometriaChallenge(): void
    {
        $flujo = $this->flujoPendiente('mfa');
        if (!$flujo) {
            $this->json(['exito' => false, 'error' => 'La verificación de contraseña expiró. Vuelve a empezar.'], 401);
        }
        $credenciales = Database::todas(
            'SELECT credential_id FROM usuarios_biometria WHERE usuario_id = ?',
            [$flujo['usuario_id']]
        );
        if (!$credenciales) {
            $this->json(['exito' => false, 'error' => 'Esta cuenta no tiene una huella o llave de acceso registrada.'], 404);
        }
        $challenge = WebAuthn::generarChallenge();
        $this->json([
            'exito' => true,
            'challenge' => $challenge['b64'],
            'rp_id' => $this->rpId(),
            'credenciales' => array_column($credenciales, 'credential_id'),
        ]);
    }

    public function loginBiometrico(): void
    {
        $flujo = $this->flujoPendiente('mfa');
        if (!$flujo) {
            $this->json(['exito' => false, 'error' => 'La verificación de contraseña expiró. Vuelve a empezar.'], 401);
        }
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $credentialId = trim((string) ($data['id'] ?? ''));
        $clientDataJSON = trim((string) ($data['clientDataJSON'] ?? ''));
        $authenticatorData = trim((string) ($data['authenticatorData'] ?? ''));
        $signature = trim((string) ($data['signature'] ?? ''));

        if (!preg_match('/^[A-Za-z0-9_-]{16,512}$/D', $credentialId)
            || !preg_match('/^[A-Za-z0-9_-]{20,4096}$/D', $clientDataJSON)
            || !preg_match('/^[A-Za-z0-9_-]{20,2048}$/D', $authenticatorData)
            || !preg_match('/^[A-Za-z0-9_-]{20,2048}$/D', $signature)) {
            $this->json(['exito' => false, 'error' => 'La respuesta de la llave de acceso está incompleta.'], 422);
        }

        $cred = Database::una(
            'SELECT * FROM usuarios_biometria WHERE credential_id = ? AND usuario_id = ?',
            [$credentialId, $flujo['usuario_id']]
        );
        if (!$cred) {
            $this->json(['exito' => false, 'error' => 'La llave de acceso no pertenece a esta cuenta.'], 403);
        }

        try {
            $ok = WebAuthn::verificar(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $cred['public_key_pem'],
                $this->origenEsperado(),
                $this->rpId()
            );
        } catch (\Throwable) {
            $ok = false;
        }
        if (!$ok) {
            $this->json(['exito' => false, 'error' => 'La verificación por huella/rostro falló o expiró.'], 403);
        }
        $this->completarIngreso((int) $flujo['usuario_id']);
    }

    public function solicitarOtp(): void
    {
        $flujo = $this->flujoPendiente('mfa');
        if (!$flujo) {
            $this->json(['exito' => false, 'error' => 'La verificación de contraseña expiró. Vuelve a empezar.'], 401);
        }
        $u = Database::una('SELECT id, nombre, email FROM usuarios WHERE id = ? AND activo = 1', [$flujo['usuario_id']]);
        if (!$u) {
            $this->json(['exito' => false, 'error' => 'La cuenta ya no está disponible.'], 403);
        }
        if (AccessControl::ready() && AccessControl::limited('login-otp:' . $u['id'], 5, 3600)) {
            $this->json(['exito' => false, 'error' => 'Se solicitaron demasiados códigos. Intenta más tarde.'], 429);
        }

        $codigo = (string) random_int(100000, 999999);
        $expiracion = date('Y-m-d H:i:s', time() + 600);

        Database::ejecutar('DELETE FROM usuarios_codigos_otp WHERE usuario_id = ?', [$u['id']]);
        Database::ejecutar(
            'INSERT INTO usuarios_codigos_otp (usuario_id, codigo, expira_en) VALUES (?, ?, ?)',
            [$u['id'], password_hash($codigo, PASSWORD_DEFAULT), $expiracion]
        );

        // Enviar correo real utilizando la API de Hostinger
        $enviado = $this->enviarCorreoHostinger($u['email'], $u['nombre'], $codigo);

        if ($enviado) {
            $this->json([
                'exito' => true,
                'mensaje' => 'Código de verificación de 6 dígitos enviado a tu correo institucional.'
            ]);
        } else {
            $this->json([
                'exito' => false,
                'error' => 'No se pudo enviar el correo de verificación. Por favor contacta al administrador.'
            ], 502);
        }
    }

    public function verificarOtp(): void
    {
        $flujo = $this->flujoPendiente('mfa');
        $codigo = trim((string) ($_POST['codigo'] ?? ''));
        if (!$flujo || !preg_match('/^\d{6}$/D', $codigo)) {
            $this->json(['exito' => false, 'error' => 'Escribe los 6 dígitos del código.'], 422);
        }
        $u = Database::una('SELECT id FROM usuarios WHERE id = ? AND activo = 1', [$flujo['usuario_id']]);
        if (!$u) {
            $this->json(['exito' => false, 'error' => 'La cuenta ya no está disponible.'], 403);
        }

        if (AccessControl::ready() && AccessControl::limited('login-otp-verificar:' . $u['id'], 10, 900)) {
            $this->json(['exito' => false, 'error' => 'Demasiados intentos. Solicita un código nuevo más tarde.'], 429);
        }
        $otp = Database::una(
            'SELECT * FROM usuarios_codigos_otp WHERE usuario_id = ? AND expira_en > NOW() ORDER BY id DESC LIMIT 1',
            [$u['id']]
        );

        if (!$otp || !password_verify($codigo, (string) $otp['codigo'])) {
            $this->json(['exito' => false, 'error' => 'Código incorrecto o expirado.'], 403);
        }
        Database::ejecutar('DELETE FROM usuarios_codigos_otp WHERE usuario_id = ?', [$u['id']]);
        $this->completarIngreso((int) $u['id']);
    }

    public function loginTotp(): void
    {
        $flujo = $this->flujoPendiente('mfa');
        $codigo = preg_replace('/\s+/', '', (string) ($_POST['codigo'] ?? ''));
        if (!$flujo || !preg_match('/^\d{6}$/D', $codigo)) {
            $this->json(['exito' => false, 'error' => 'Escribe los 6 dígitos de tu aplicación.'], 422);
        }
        $u = Database::una('SELECT id, totp_secret FROM usuarios WHERE id = ? AND activo = 1', [$flujo['usuario_id']]);
        if (!$u || empty($u['totp_secret'])) {
            $this->json(['exito' => false, 'error' => 'La aplicación de autenticación no está configurada para esta cuenta.'], 403);
        }

        $ok = Totp::verificar($u['totp_secret'], $codigo);
        if (!$ok) {
            $this->json(['exito' => false, 'error' => 'Código incorrecto o expirado.'], 403);
        }
        $this->completarIngreso((int) $u['id']);
    }

    public function solicitarRestablecimiento(): void
    {
        $email = Auth::normalizarCorreoInstitucional((string) ($_POST['email'] ?? '')) ?? '';
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (AccessControl::ready() && AccessControl::limited('reset-ip:' . $ip, 8, 3600)) {
            $this->json(['exito' => false, 'error' => 'Se alcanzó el límite de solicitudes. Intenta más tarde.'], 429);
        }
        $u = $email !== '' ? Database::una('SELECT id, nombre, email FROM usuarios WHERE email = ? AND activo = 1', [$email]) : null;
        if ($u && (!AccessControl::ready() || !AccessControl::limited('reset-cuenta:' . $u['id'], 5, 3600))) {
            $codigo = (string) random_int(100000, 999999);
            Database::ejecutar('DELETE FROM usuarios_codigos_otp WHERE usuario_id = ?', [$u['id']]);
            Database::ejecutar(
                'INSERT INTO usuarios_codigos_otp (usuario_id, codigo, expira_en) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))',
                [$u['id'], password_hash($codigo, PASSWORD_DEFAULT)]
            );
            $this->enviarCorreoHostinger($email, $u['nombre'], $codigo, true);
        }
        $_SESSION['restablecer_email'] = $email;
        $this->json(['exito' => true, 'mensaje' => 'Si la cuenta existe, recibirás un código de recuperación.']);
    }

    public function verificarRestablecimiento(): void
    {
        $email = Auth::normalizarCorreoInstitucional((string) ($_POST['email'] ?? '')) ?? '';
        $codigo = trim((string) ($_POST['codigo'] ?? ''));
        $u = $email !== '' ? Database::una('SELECT id FROM usuarios WHERE email = ? AND activo = 1', [$email]) : null;
        if ($u && AccessControl::ready() && AccessControl::limited('reset-verificar:' . $u['id'], 10, 900)) {
            $this->json(['exito' => false, 'error' => 'Demasiados intentos. Solicita un código nuevo más tarde.'], 429);
        }
        $otp = $u ? Database::una(
            'SELECT id, codigo FROM usuarios_codigos_otp WHERE usuario_id = ? AND expira_en > NOW() ORDER BY id DESC LIMIT 1',
            [$u['id']]
        ) : null;
        if (!$otp || !preg_match('/^\d{6}$/D', $codigo) || !password_verify($codigo, (string) $otp['codigo'])) {
            $this->json(['exito' => false, 'error' => 'Código incorrecto o expirado.'], 403);
        }
        Database::ejecutar('DELETE FROM usuarios_codigos_otp WHERE usuario_id = ?', [$u['id']]);
        $_SESSION['restablecer_autorizado'] = (int) $u['id'];
        $_SESSION['restablecer_autorizado_en'] = time();
        $this->json(['exito' => true]);
    }

    public function restablecerContrasena(): void
    {
        $id = (int) ($_SESSION['restablecer_autorizado'] ?? 0);
        $autorizadoEn = (int) ($_SESSION['restablecer_autorizado_en'] ?? 0);
        $clave = (string) ($_POST['clave'] ?? '');
        if (!$id || $autorizadoEn < time() - 600 || strlen($clave) < 10 || strlen($clave) > 72) {
            $this->json(['exito' => false, 'error' => 'La autorización expiró o la contraseña no tiene entre 10 y 72 caracteres.'], 422);
        }
        Database::ejecutar('UPDATE usuarios SET password_hash = ?, permite_contrasena = 1, logins_contrasena_restantes = 20 WHERE id = ?', [password_hash($clave, PASSWORD_DEFAULT), $id]);
        unset($_SESSION['restablecer_autorizado'], $_SESSION['restablecer_autorizado_en'], $_SESSION['restablecer_email']);
        $this->json(['exito' => true]);
    }

    public function consultarPermisoContrasena(): void
    {
        $email = Auth::normalizarCorreoInstitucional((string) ($_POST['email'] ?? ''));
        unset(
            $_SESSION['login_flujo'],
            $_SESSION['webauthn_challenge_hex'],
            $_SESSION['webauthn_challenge_b64'],
            $_SESSION['webauthn_challenge_creado_en']
        );
        if ($email === null) {
            $this->json(['exito' => false, 'error' => 'Escribe tu usuario o un correo de uaemex.mx (por ejemplo, @alumno.uaemex.mx).'], 422);
        }

        $_SESSION['login_flujo'] = [
            'email' => $email,
            'etapa' => 'identificado',
            'creado_en' => time(),
        ];
        $this->json([
            'exito' => true,
            'email' => $email,
        ]);
    }

    private function enviarCorreoHostinger(string $destinatario, string $nombreDestinatario, string $codigo, bool $restablecimiento = false): bool
    {
        $token = (string) App::config('correo.api_token', '');
        $senderEmail = (string) App::config('correo.remitente', 'contacto@fingenieria.mx');
        $senderName = (string) App::config('correo.nombre_remitente', 'Traza FI');
        $saludoPersona = \App\Core\Persona::saludo(\App\Core\Persona::completar(['nombre' => $nombreDestinatario], $destinatario));
        if ($token === '') {
            return false;
        }

        // 1. Obtener el resourceId del mailbox
        $ch = curl_init('https://api.mail.hostinger.com/api/v1/me');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status !== 200 || !$response) {
            return false;
        }

        $resData = json_decode($response, true);
        $mailboxes = $resData['data']['mailboxes'] ?? [];
        $mailboxResourceId = null;
        foreach ($mailboxes as $mb) {
            if (strcasecmp($mb['address'], $senderEmail) === 0) {
                $mailboxResourceId = $mb['resourceId'];
                break;
            }
        }

        if (!$mailboxResourceId && !empty($mailboxes)) {
            $mailboxResourceId = $mailboxes[0]['resourceId'];
        }

        if (!$mailboxResourceId) {
            return false;
        }

        // 2. Enviar el correo usando el endpoint POST /api/v1/mailboxes/{mailboxResourceId}/send
        $urlSend = "https://api.mail.hostinger.com/api/v1/mailboxes/{$mailboxResourceId}/send";
        $payload = [
            'to' => [$destinatario],
            'displayName' => $senderName,
            'subject' => $restablecimiento ? 'Código para restablecer contraseña - Traza FI' : 'Código de Seguridad - Traza FI',
            'text' => $saludoPersona . ",\n\nTu código de verificación de 6 dígitos para acceder a Traza FI es: $codigo\n\nEste código es de un solo uso y expira en 10 minutos.\n\nAtentamente,\nFacultad de Ingeniería · UAEMEX",
            'html' => "<div style='font-family: sans-serif; max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px;'>" .
                      "<h2 style='color: #0f172a; margin-top: 0;'>Código de verificación (Traza FI)</h2>" .
                      "<p style='color: #475569; font-size: 14px;'><strong>" . htmlspecialchars($saludoPersona) . "</strong>,</p>" .
                      "<p style='color: #475569; font-size: 14px;'>Tu código de acceso temporal para acceder a Traza FI es:</p>" .
                      "<div style='background: #f1f5f9; padding: 15px; border-radius: 6px; text-align: center; font-size: 24px; font-weight: bold; letter-spacing: 4px; color: #0f172a; margin: 20px 0;'>$codigo</div>" .
                      "<p style='color: #64748b; font-size: 12.5px;'>Este código expira en 10 minutos y es de un solo uso.</p>" .
                      "<hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>" .
                      "<p style='color: #94a3b8; font-size: 11px; text-align: center; margin-bottom: 0;'>Traza FI · Facultad de Ingeniería · UAEMEX</p>" .
                      "</div>"
        ];

        $chSend = curl_init($urlSend);
        curl_setopt($chSend, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($chSend, CURLOPT_POST, true);
        curl_setopt($chSend, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($chSend, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "Content-Type: application/json"
        ]);
        curl_exec($chSend);
        $statusSend = curl_getinfo($chSend, CURLINFO_HTTP_CODE);
        curl_close($chSend);

        return ($statusSend === 204 || $statusSend === 200);
    }

    public function verificarPrimerPaso(): void
    {
        $flujo = $this->flujoPendiente('identificado');
        $clave = (string) ($_POST['clave'] ?? '');
        $permanecer = (string) ($_POST['permanecer'] ?? '') === '1';
        if (!$flujo || $clave === '' || strlen($clave) > 1024) {
            $this->json(['exito' => false, 'error' => 'La solicitud expiró o la contraseña no es válida. Vuelve a empezar.'], 422);
        }
        $email = (string) ($flujo['email'] ?? '');
        if (AccessControl::ready() && AccessControl::limited('login-clave:' . ($_SERVER['REMOTE_ADDR'] ?? '') . ':' . hash('sha256', $email), 10, 900)) {
            $this->json(['exito' => false, 'error' => 'Demasiados intentos. Espera unos minutos antes de volver a intentar.'], 429);
        }
        $u = Database::una(
            'SELECT id, password_hash, permite_contrasena, logins_contrasena_restantes, totp_secret FROM usuarios WHERE email = ? AND activo = 1',
            [$email]
        );
        if (!$u || !password_verify($clave, $u['password_hash'])) {
            $this->json(['exito' => false, 'error' => 'El correo o la contraseña son incorrectos.'], 403);
        }

        $tienePasskey = (bool) Database::valor('SELECT COUNT(*) FROM usuarios_biometria WHERE usuario_id = ?', [$u['id']]);
        $tieneTotp = !empty($u['totp_secret']);
        $requiereMfa = $tienePasskey || $tieneTotp
            || (int) $u['permite_contrasena'] !== 1
            || (int) $u['logins_contrasena_restantes'] <= 0;

        $_SESSION['login_flujo']['permanecer'] = $permanecer;

        if (!$requiereMfa) {
            flash('alerta', 'Protege tu cuenta: registra tu huella o aplicación autenticadora en Menú → Mi seguridad.');
            $this->completarIngreso((int) $u['id']);
        }

        $_SESSION['login_flujo']['etapa'] = 'mfa';
        $_SESSION['login_flujo']['usuario_id'] = (int) $u['id'];
        $_SESSION['login_flujo']['password_en'] = time();
        $this->json([
            'exito' => true,
            'requiere_2fa' => true,
            'metodos' => [
                'passkey' => $tienePasskey,
                'totp' => $tieneTotp,
                'correo' => true,
            ],
            'configuracion_pendiente' => !$tienePasskey && !$tieneTotp,
        ]);
    }

    private function flujoPendiente(string $etapa): ?array
    {
        $flujo = $_SESSION['login_flujo'] ?? null;
        if (!is_array($flujo)
            || ($flujo['etapa'] ?? '') !== $etapa
            || (($etapa === 'identificado' && empty($flujo['email'])) || ($etapa === 'mfa' && empty($flujo['usuario_id'])))
            || time() - (int) ($flujo['creado_en'] ?? 0) > 600
            || ($etapa === 'mfa' && time() - (int) ($flujo['password_en'] ?? 0) > 300)) {
            unset($_SESSION['login_flujo']);
            return null;
        }
        return $flujo;
    }

    private function completarIngreso(int $usuarioId): never
    {
        $permanecer = !empty($_SESSION['login_flujo']['permanecer']);
        $u = AccessControl::user($usuarioId);
        if (!$u) {
            unset($_SESSION['login_flujo']);
            $this->json(['exito' => false, 'error' => 'La cuenta ya no está disponible.'], 403);
        }
        unset(
            $_SESSION['login_flujo'],
            $_SESSION['webauthn_challenge_hex'],
            $_SESSION['webauthn_challenge_b64'],
            $_SESSION['webauthn_challenge_creado_en']
        );
        Auth::iniciarSesion($u, $permanecer);
        $this->json(['exito' => true, 'redirect' => url(Auth::consumirDestino())]);
    }

    private function origenEsperado(): string
    {
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $hostSinPuerto = explode(':', $host, 2)[0];
        if (in_array($hostSinPuerto, ['localhost', '127.0.0.1', '::1'], true)) {
            $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            return ($https ? 'https' : 'http') . '://' . $host;
        }
        $publica = (string) App::config('app.url_publica', 'https://fingenieria.mx');
        $scheme = parse_url($publica, PHP_URL_SCHEME) ?: 'https';
        $publicHost = parse_url($publica, PHP_URL_HOST) ?: 'fingenieria.mx';
        $port = parse_url($publica, PHP_URL_PORT);
        return $scheme . '://' . $publicHost . ($port ? ':' . $port : '');
    }

    private function rpId(): string
    {
        return (string) parse_url($this->origenEsperado(), PHP_URL_HOST);
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
