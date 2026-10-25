<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Modules\Titulacion\Services\Jurado;
use App\Modules\Titulacion\Services\Oficios;
use RuntimeException;
use Throwable;

/** Enlaces personales por correo: el Coordinador de Docencia asigna sinodales y cada sinodal firma de recibido. */
final class JuradoController extends TitulacionBase
{
    private function invalido(): never
    {
        Audit::record('titulacion::token', 'denegado', 'Enlace de jurado no válido o vencido');
        if (AccessControl::ready() && AccessControl::limited('tit_token_fallo:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 30, 900)) {
            http_response_code(429);
            header('Retry-After: 900');
            exit('Demasiados intentos. Intenta de nuevo en unos minutos.');
        }
        http_response_code(404);
        View::render('titulacion:revisor_enlace_invalido', ['titulo' => 'Enlace no disponible'], 'publico');
        exit;
    }

    /** Banner según la sesión frente al correo al que se envió el enlace. */
    private function banner(string $email): array
    {
        $propia = Auth::usuario() !== null && TitulacionAccess::correoSesion() === strtolower($email);
        if ($propia) {
            return [true, AccessControl::pending() ? ['tipo' => 'verificar', 'email' => $email] : null];
        }
        $existe = (int) Database::valor('SELECT COUNT(*) FROM usuarios WHERE email = ? AND activo = 1', [strtolower($email)]) > 0;
        $_SESSION['_registro_email'] = $email;
        return [false, ['tipo' => Auth::usuario() ? 'otra' : ($existe ? 'login' : 'registro'), 'email' => $email]];
    }

    // ----------------------------------------------------------------- Coordinador

    /** Asignación del Coordinador con sesión (sin token), desde «Mis pendientes». */
    private function asignacionPropia(string $id): array
    {
        $a = ctype_digit($id) ? Database::una("SELECT * FROM tit_asignaciones_jurado WHERE id = ? AND estado <> 'cancelada'", [(int) $id]) : null;
        if ($a === null || strtolower((string) $a['coord_email']) !== TitulacionAccess::correoSesion()) {
            $this->noEncontrado();
        }
        return $a;
    }

    private function sinodalPropio(string $id): array
    {
        $s = ctype_digit($id) ? Database::una("SELECT * FROM tit_sinodales WHERE id = ? AND estado <> 'cancelado'", [(int) $id]) : null;
        if ($s === null || strtolower((string) $s['email']) !== TitulacionAccess::correoSesion()) {
            $this->noEncontrado();
        }
        return $s;
    }

    public function coordinadorMio(string $id): void
    {
        $this->verCoordinador($this->asignacionPropia($id), '', 'titulacion/jurado/mia/' . (int) $id);
    }

    public function firmarCoordinadorMio(string $id): void
    {
        $this->firmarAsignacion($this->asignacionPropia($id), 'titulacion/jurado/mia/' . (int) $id);
    }

    public function trabajoCoordinadorMio(string $id): void
    {
        $this->entregarTrabajo($this->asignacionPropia($id));
    }

    public function sinodalMio(string $id): void
    {
        $this->verSinodal($this->sinodalPropio($id), '', 'titulacion/sinodal/mio/' . (int) $id);
    }

    public function oficioSinodalMio(string $id): void
    {
        $this->entregarOficio($this->sinodalPropio($id));
    }

    public function trabajoSinodalMio(string $id): void
    {
        $s = $this->sinodalPropio($id);
        $this->entregarTrabajo(Database::una('SELECT * FROM tit_asignaciones_jurado WHERE id = ?', [(int) $s['asignacion_id']]) ?? []);
    }

    public function firmarSinodalMio(string $id): void
    {
        $this->acuse($this->sinodalPropio($id), 'titulacion/sinodal/mio/' . (int) $id);
    }

    public function coordinador(string $token): void
    {
        $a = Jurado::porToken($token) ?? $this->invalido();
        $this->verCoordinador($a, $token, 'titulacion/jurado/t/' . $token);
    }

    private function verCoordinador(array $a, string $token, string $base): void
    {
        $this->privado();
        $t = TitulacionRepository::obtener((int) $a['tramite_id']);
        if (!$t) {
            $this->noEncontrado();
        }
        [$propia, $banner] = $this->banner((string) $a['coord_email']);
        $this->vista('jurado_coordinador', [
            'titulo' => 'Asignar sinodales · ' . $t['folio'],
            'a' => $a, 't' => $t, 'token' => $token, 'base' => $base, 'banner' => $banner,
            'puedeFirmar' => $propia && $banner === null && $a['estado'] === 'solicitada',
            'sinodales' => $a['estado'] === 'firmada' ? Jurado::sinodales((int) $a['id']) : [],
            'propuesta' => Jurado::propuesta($a),
            'integrantes' => Jurado::integrantes($a),
            'examen' => Oficios::EXAMEN[$a['tipo']] ?? 'examen',
            'trabajo' => Jurado::trabajo($a) !== null,
        ], Auth::usuario() ? 'app' : 'publico');
    }

    public function firmarCoordinador(string $token): void
    {
        $this->firmarAsignacion(Jurado::porToken($token) ?? $this->invalido(), 'titulacion/jurado/t/' . $token);
    }

    private function firmarAsignacion(array $a, string $volver): never
    {
        $this->privado();
        $elegidos = [];
        [$prop, $sup] = Jurado::integrantes($a);
        for ($i = 0; $i < $prop + $sup; $i++) {
            $elegidos[$i] = ['nombre' => (string) ($_POST['s'][$i]['nombre'] ?? ''), 'email' => (string) ($_POST['s'][$i]['email'] ?? ''), 'grado' => (string) ($_POST['s'][$i]['grado'] ?? ''), 'genero' => (string) ($_POST['s'][$i]['genero'] ?? '')];
        }
        $_SESSION['_jurado_borrador'][$a['id']] = $elegidos;
        $usuario = Auth::usuario();
        if (!$usuario || strtolower((string) $usuario['email']) !== strtolower((string) $a['coord_email'])) {
            Auth::recordarDestino($volver);
            flash('error', 'Para firmar necesitas una cuenta verificada con ' . $a['coord_email'] . '.');
            redirigir($volver);
        }
        try {
            if (AccessControl::pending()) {
                throw new RuntimeException('Verifica tu correo para poder firmar.');
            }
            Jurado::firmarAsignacion($a, $usuario, $elegidos);
            unset($_SESSION['_jurado_borrador'][$a['id']]);
            flash('exito', 'Asignación firmada. Se generaron los oficios para cada profesor. ¡Gracias!');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Titulación: error al firmar asignación de jurado: ' . $e->getMessage());
            flash('error', 'No se pudo firmar. Intenta de nuevo.');
        }
        redirigir($volver);
    }

    public function trabajoCoordinador(string $token): void
    {
        $this->entregarTrabajo(Jurado::porToken($token) ?? $this->invalido());
    }

    private function entregarTrabajo(array $a): never
    {
        $d = $a ? Jurado::trabajo($a) : null;
        if ($d === null) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::trabajo_descarga', 'atendido', 'Asignación ' . (int) $a['id']);
        $this->enviarArchivo((string) $d['ruta'], 'trabajo.pdf', true);
    }

    // ----------------------------------------------------------------- Sinodal

    public function sinodal(string $token): void
    {
        $this->verSinodal(Jurado::sinodalPorToken($token) ?? $this->invalido(), $token, 'titulacion/sinodal/t/' . $token);
    }

    private function verSinodal(array $s, string $token, string $base): void
    {
        $this->privado();
        $a = Database::una('SELECT * FROM tit_asignaciones_jurado WHERE id = ?', [(int) $s['asignacion_id']]);
        $t = $a ? TitulacionRepository::obtener((int) $a['tramite_id']) : null;
        if (!$t) {
            $this->noEncontrado();
        }
        [$propia, $banner] = $this->banner((string) $s['email']);
        $this->vista('jurado_sinodal', [
            'titulo' => 'Oficio de citación · ' . $t['folio'],
            's' => $s, 'a' => $a, 'token' => $token, 'base' => $base, 'banner' => $banner,
            'puedeFirmar' => $propia && $banner === null && $s['estado'] === 'pendiente',
            'alumno' => (string) ($t['datos']['datos']['nombre'] ?? ''),
            'examen' => Oficios::EXAMEN[$a['tipo']] ?? 'examen',
            'trabajo' => Jurado::trabajo($a) !== null,
        ], Auth::usuario() ? 'app' : 'publico');
    }

    public function oficioSinodal(string $token): void
    {
        $this->entregarOficio(Jurado::sinodalPorToken($token) ?? $this->invalido());
    }

    private function entregarOficio(array $s): never
    {
        $d = TitulacionRepository::documento((int) $s['oficio_doc_id']);
        if (!$d) {
            $this->noEncontrado();
        }
        $this->enviarArchivo((string) ($d['subdir_ruta'] ?: $d['ruta']), 'oficio.pdf', true);
    }

    public function trabajoSinodal(string $token): void
    {
        $s = Jurado::sinodalPorToken($token) ?? $this->invalido();
        $a = Database::una('SELECT * FROM tit_asignaciones_jurado WHERE id = ?', [(int) $s['asignacion_id']]);
        $d = $a ? Jurado::trabajo($a) : null;
        if ($d === null) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::trabajo_descarga', 'atendido', 'Sinodal ' . (int) $s['id']);
        $this->enviarArchivo((string) $d['ruta'], 'trabajo.pdf', true);
    }

    public function firmarSinodal(string $token): void
    {
        $this->acuse(Jurado::sinodalPorToken($token) ?? $this->invalido(), 'titulacion/sinodal/t/' . $token);
    }

    private function acuse(array $s, string $volver): never
    {
        $this->privado();
        $usuario = Auth::usuario();
        if (!$usuario || strtolower((string) $usuario['email']) !== strtolower((string) $s['email'])) {
            Auth::recordarDestino($volver);
            flash('error', 'Para firmar necesitas una cuenta verificada con ' . $s['email'] . '.');
            redirigir($volver);
        }
        try {
            if (AccessControl::pending()) {
                throw new RuntimeException('Verifica tu correo para poder firmar.');
            }
            Jurado::acuse($s, $usuario);
            flash('exito', 'Acuse firmado. La fecha del examen ya aparece en tu calendario.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Titulación: error en acuse de sinodal: ' . $e->getMessage());
            flash('error', 'No se pudo firmar. Intenta de nuevo.');
        }
        redirigir($volver);
    }
}
