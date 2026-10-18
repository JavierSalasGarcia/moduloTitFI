<?php
declare(strict_types=1);

namespace App\Modules\Seguridad;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Auth;
use App\Core\WebAuthn;
use App\Core\Totp;

final class SeguridadController extends Controller
{
    public function index(): void
    {
        $u = Auth::usuario();
        $usrDb = Database::una('SELECT totp_secret FROM usuarios WHERE id = ?', [$u['id']]);
        
        $this->vista('index', [
            'titulo' => 'Mi Seguridad (MFA)',
            'biometria' => Database::todas('SELECT id, alias, creado_en FROM usuarios_biometria WHERE usuario_id = ?', [$u['id']]),
            'hasTotp' => !empty($usrDb['totp_secret']),
            'moduloActivo' => 'seguridad'
        ]);
    }

    public function biometriaRegistroChallenge(): void
    {
        header('Content-Type: application/json');
        $challenge = WebAuthn::generarChallenge();
        echo json_encode([
            'challenge' => $challenge['b64'],
            'user' => [
                'id' => Auth::id(),
                'name' => Auth::usuario()['email'],
                'displayName' => Auth::usuario()['nombre']
            ]
        ]);
        exit;
    }

    public function registrarBiometria(): void
    {
        header('Content-Type: application/json');
        $u = Auth::usuario();
        
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $credentialId = trim($data['id'] ?? '');
        $publicKeyPem = trim($data['publicKeyPem'] ?? '');
        $alias = trim($data['alias'] ?? 'Mi Dispositivo Passkey');

        if ($credentialId === '' || $publicKeyPem === '') {
            echo json_encode(['exito' => false, 'error' => 'Datos de registro de credencial incompletos.']);
            exit;
        }

        try {
            Database::ejecutar('DELETE FROM usuarios_biometria WHERE credential_id = ?', [$credentialId]);
            Database::ejecutar(
                'INSERT INTO usuarios_biometria (usuario_id, credential_id, public_key_pem, alias) VALUES (?, ?, ?, ?)',
                [$u['id'], $credentialId, $publicKeyPem, $alias]
            );
            echo json_encode(['exito' => true, 'mensaje' => 'Dispositivo biométrico registrado con éxito.']);
        } catch (\Throwable $e) {
            echo json_encode(['exito' => false, 'error' => 'Error al guardar la credencial: ' . $e->getMessage()]);
        }
        exit;
    }

    public function eliminarBiometria(): void
    {
        header('Content-Type: application/json');
        $u = Auth::usuario();
        $id = (int)($_POST['id'] ?? 0);
        
        try {
            Database::ejecutar('DELETE FROM usuarios_biometria WHERE id = ? AND usuario_id = ?', [$id, $u['id']]);
            echo json_encode(['exito' => true, 'mensaje' => 'Dispositivo eliminado con éxito.']);
        } catch (\Throwable $e) {
            echo json_encode(['exito' => false, 'error' => 'Error al eliminar: ' . $e->getMessage()]);
        }
        exit;
    }

    public function totpGenerar(): void
    {
        header('Content-Type: application/json');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $secret = Totp::generarSecreto();
        $_SESSION['temp_totp_secret'] = $secret;

        $email = Auth::usuario()['email'];
        $uri = Totp::uri($email, $secret);
        
        echo json_encode([
            'secret' => $secret,
            'uri' => $uri
        ]);
        exit;
    }

    public function totpVincular(): void
    {
        header('Content-Type: application/json');
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $u = Auth::usuario();
        $codigo = trim($_POST['codigo'] ?? '');
        $secret = $_SESSION['temp_totp_secret'] ?? '';

        if ($secret === '') {
            echo json_encode(['exito' => false, 'error' => 'Sección expirada. Genera el QR de nuevo.']);
            exit;
        }

        if ($codigo === '') {
            echo json_encode(['exito' => false, 'error' => 'Ingresa el código de la aplicación.']);
            exit;
        }

        $ok = Totp::verificar($secret, $codigo);
        if (!$ok) {
            echo json_encode(['exito' => false, 'error' => 'Código incorrecto. Vuelve a intentarlo.']);
            exit;
        }

        try {
            Database::ejecutar('UPDATE usuarios SET totp_secret = ? WHERE id = ?', [$secret, $u['id']]);
            unset($_SESSION['temp_totp_secret']);
            echo json_encode(['exito' => true, 'mensaje' => 'Aplicación de autenticación vinculada correctamente.']);
        } catch (\Throwable $e) {
            echo json_encode(['exito' => false, 'error' => 'Error al guardar configuración: ' . $e->getMessage()]);
        }
        exit;
    }

    public function totpDesvincular(): void
    {
        header('Content-Type: application/json');
        $u = Auth::usuario();

        try {
            Database::ejecutar('UPDATE usuarios SET totp_secret = NULL WHERE id = ?', [$u['id']]);
            echo json_encode(['exito' => true, 'mensaje' => 'Aplicación de autenticación desvinculada.']);
        } catch (\Throwable $e) {
            echo json_encode(['exito' => false, 'error' => 'Error al desvincular: ' . $e->getMessage()]);
        }
        exit;
    }
}
