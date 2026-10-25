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

/**
 * Asesores, coasesores y revisores firman protocolo, solicitudes con Vo. Bo. y voto aprobatorio: desde el enlace
 * personal de su correo o, con sesión, desde «Mis pendientes».
 */
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

    /** Solicitud de firma de la persona con sesión (por id, sin token). */
    private function propia(string $id): array
    {
        $f = ctype_digit($id) ? Database::una("SELECT * FROM tit_firmas_externas WHERE id = ? AND estado <> 'cancelada'", [(int) $id]) : null;
        if ($f === null || strtolower((string) $f['email']) !== TitulacionAccess::correoSesion()) {
            $this->noEncontrado();
        }
        return $f;
    }

    private function mostrar(array $f, string $base): void
    {
        $this->privado();
        $t = TitulacionRepository::obtener((int) $f['tramite_id']);
        $doc = TitulacionRepository::documento((int) $f['documento_id']);
        if (!$t || !$doc) {
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
        [$que, $asunto] = FirmasExternas::queSeFirma((string) $doc['clave']);
        $this->vista('firma_externa', [
            'titulo' => $asunto . ' · ' . $t['folio'],
            'f' => $f, 'base' => $base, 'banner' => $banner, 'que' => $que, 'asunto' => $asunto,
            'puedeFirmar' => $propia && !AccessControl::pending() && $f['estado'] === 'pendiente',
            'alumno' => (string) ($t['datos']['datos']['nombre'] ?? ''),
            'tema' => (string) ($t['titulo'] ?: ($t['datos']['protocolo']['titulo'] ?? '')),
            'folio' => $t['folio'], 'tramiteId' => (int) $t['id'],
        ], Auth::usuario() ? 'app' : 'publico');
    }

    private function entregar(array $f): never
    {
        $d = TitulacionRepository::documento((int) $f['documento_id']);
        if (!$d || empty($d['ruta'])) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::firma_externa_descarga', 'atendido', 'Firma externa ' . (int) $f['id']);
        $this->enviarArchivo((string) $d['ruta'], $d['clave'] . '.pdf', true);
    }

    private function firmarComo(array $f, string $volver): never
    {
        $this->privado();
        $usuario = Auth::usuario();
        if (!$usuario || strtolower((string) $usuario['email']) !== strtolower((string) $f['email'])) {
            Auth::recordarDestino($volver);
            flash('error', 'Para firmar necesitas una cuenta verificada con ' . $f['email'] . '.');
            redirigir($volver);
        }
        try {
            if (AccessControl::pending()) {
                throw new RuntimeException('Verifica tu correo para poder firmar.');
            }
            FirmasExternas::firmar($f, $usuario);
            flash('exito', 'Documento firmado. ¡Gracias!');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Titulación: error en firma externa: ' . $e->getMessage());
            flash('error', 'No se pudo firmar. Intenta de nuevo.');
        }
        redirigir($volver);
    }

    // ------------------------------------------------------------------ por enlace

    public function ver(string $token): void
    {
        $this->mostrar($this->desdeToken($token), 'titulacion/firma/t/' . $token);
    }

    public function documento(string $token): void
    {
        $this->entregar($this->desdeToken($token));
    }

    public function firmar(string $token): void
    {
        $this->firmarComo($this->desdeToken($token), 'titulacion/firma/t/' . $token);
    }

    // ------------------------------------------------------------------ con sesión

    public function verPropia(string $id): void
    {
        $this->mostrar($this->propia($id), 'titulacion/firma/mia/' . (int) $id);
    }

    public function documentoPropio(string $id): void
    {
        $this->entregar($this->propia($id));
    }

    public function firmarPropia(string $id): void
    {
        $this->firmarComo($this->propia($id), 'titulacion/firma/mia/' . (int) $id);
    }
}
