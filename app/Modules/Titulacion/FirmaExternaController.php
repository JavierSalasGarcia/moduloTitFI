<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Modules\Titulacion\Services\FirmasExternas;
use RuntimeException;
use Throwable;

/** Asesor: revisa el protocolo del pasante y lo firma desde el enlace personal de su correo. */
final class FirmaExternaController extends TitulacionBase
{
    private function desdeToken(string $token): array
    {
        $f = FirmasExternas::porToken($token);
        if ($f === null) {
            Audit::record('titulacion::token', 'denegado', 'Enlace de firma no válido o vencido');
            if (AccessControl::ready() && AccessControl::limited('tit_token_fallo:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 30, 900)) {
                http_response_code(429);
                header('Retry-After: 900');
                exit('Demasiados intentos. Intenta de nuevo en unos minutos.');
            }
            http_response_code(404);
            View::render('titulacion:revisor_enlace_invalido', ['titulo' => 'Enlace no disponible'], 'publico');
            exit;
        }
        return $f;
    }

    public function ver(string $token): void
    {
        $this->privado();
        $f = $this->desdeToken($token);
        $t = TitulacionRepository::obtener((int) $f['tramite_id']);
        if (!$t) {
            $this->noEncontrado();
        }
        $correo = TitulacionAccess::correoSesion();
        $propia = Auth::usuario() !== null && $correo === strtolower((string) $f['email']);
        $banner = null;
        if (!$propia) {
            $existe = (int) Database::valor('SELECT COUNT(*) FROM usuarios WHERE email = ? AND activo = 1', [strtolower((string) $f['email'])]) > 0;
            $banner = ['tipo' => Auth::usuario() ? 'otra' : ($existe ? 'login' : 'registro'), 'email' => $f['email']];
            $_SESSION['_registro_email'] = (string) $f['email'];
        } elseif (AccessControl::pending()) {
            $banner = ['tipo' => 'verificar', 'email' => $f['email']];
        }
        $this->vista('firma_externa', [
            'titulo' => 'Firma de protocolo ' . $t['folio'],
            'f' => $f, 'token' => $token, 'banner' => $banner,
            'puedeFirmar' => $propia && !AccessControl::pending() && $f['estado'] === 'pendiente',
            'alumno' => (string) ($t['datos']['datos']['nombre'] ?? ''),
            'tema' => (string) ($t['datos']['protocolo']['titulo'] ?? ''),
            'folio' => $t['folio'],
        ], Auth::usuario() ? 'app' : 'publico');
    }

    public function documento(string $token): void
    {
        $f = $this->desdeToken($token);
        $d = TitulacionRepository::documento((int) $f['documento_id']);
        if (!$d || empty($d['ruta'])) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::protocolo_descarga', 'atendido', 'Firma externa ' . (int) $f['id']);
        $this->enviarArchivo((string) $d['ruta'], 'protocolo.pdf', true);
    }

    public function firmar(string $token): void
    {
        $this->privado();
        $f = $this->desdeToken($token);
        $usuario = Auth::usuario();
        if (!$usuario || strtolower((string) $usuario['email']) !== strtolower((string) $f['email'])) {
            Auth::recordarDestino('titulacion/firma/t/' . $token);
            flash('error', 'Para firmar necesitas una cuenta verificada con ' . $f['email'] . '.');
            redirigir('titulacion/firma/t/' . $token);
        }
        try {
            if (AccessControl::pending()) {
                throw new RuntimeException('Verifica tu correo para poder firmar.');
            }
            FirmasExternas::firmar($f, $usuario);
            flash('exito', 'Protocolo firmado. ¡Gracias! El pasante ya puede continuar con su trámite.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Titulación: error al firmar como asesor: ' . $e->getMessage());
            flash('error', 'No se pudo firmar. Intenta de nuevo.');
        }
        redirigir('titulacion/firma/t/' . $token);
    }
}
