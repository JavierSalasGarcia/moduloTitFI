<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Modules\Titulacion\Services\FirmaElectronica;
use App\Modules\Titulacion\Services\RevisionProtocolo;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Evaluador de la comisión. Funciona con sesión o con el enlace personal del correo (token): con el
 * enlace puede leer el protocolo y redactar sus recomendaciones; para firmar necesita cuenta verificada.
 */
final class RevisionController extends TitulacionBase
{
    // --------------------------------------------------------------- con sesión

    public function lista(): void
    {
        $this->privado();
        if (!TitulacionAccess::esEvaluador()) {
            $this->prohibido('Protocolos por evaluar');
        }
        $this->vista('revisor_lista', [
            'titulo' => 'Titulación · Protocolos por evaluar',
            'asignaciones' => $this->decorarAsignaciones(RevisionRepository::delEvaluador(TitulacionAccess::correoSesion())),
            'carreras' => Catalogo::carreras(),
        ]);
    }

    public function ver(string $id): void
    {
        $this->privado();
        $this->mostrar($this->porSesion($id), 'sesion', '');
    }

    public function guardar(string $id): void
    {
        $this->privado();
        $this->accionGuardar($this->porSesion($id), 'titulacion/revision/' . (int) $id);
    }

    public function firmar(string $id): void
    {
        $this->privado();
        $this->accionFirmar($this->porSesion($id), 'titulacion/revision/' . (int) $id, '');
    }

    public function documento(string $id): void
    {
        $this->entregarProtocolo($this->porSesion($id));
    }

    private function porSesion(string $id): array
    {
        $a = ctype_digit($id) ? RevisionRepository::asignacion((int) $id) : null;
        if (!Auth::usuario() || $a === null || strtolower((string) $a['miembro_email']) !== TitulacionAccess::correoSesion() || $a['estado'] === 'cancelada') {
            $this->noEncontrado();
        }
        return $a;
    }

    // ---------------------------------------------------------------- con token

    public function porToken(string $token): void
    {
        $this->privado();
        $a = $this->desdeToken($token);
        if (Auth::usuario() && strtolower((string) $a['miembro_email']) === TitulacionAccess::correoSesion()) {
            redirigir('titulacion/revision/' . (int) $a['id']);   // ya inició sesión: ve lo mismo, de forma transparente
        }
        $this->mostrar($a, 'token', $token);
    }

    public function guardarToken(string $token): void
    {
        $this->privado();
        $this->accionGuardar($this->desdeToken($token), 'titulacion/revision/t/' . $token);
    }

    public function firmarToken(string $token): void
    {
        $this->privado();
        $this->accionFirmar($this->desdeToken($token), 'titulacion/revision/t/' . $token, $token);
    }

    public function documentoToken(string $token): void
    {
        $this->entregarProtocolo($this->desdeToken($token));
    }

    private function desdeToken(string $token): array
    {
        $a = preg_match('/^[A-Za-z0-9_-]{40,50}$/D', $token) ? RevisionRepository::asignacionPorToken($token) : null;
        if ($a === null || $a['estado'] === 'cancelada' || $a['token_vence'] < date('Y-m-d')) {
            Audit::record('titulacion::token', 'denegado', 'Enlace no válido o vencido');
            if (AccessControl::ready() && AccessControl::limited('tit_token_fallo:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 30, 900)) {
                http_response_code(429);
                header('Retry-After: 900');
                exit('Demasiados intentos. Intenta de nuevo en unos minutos.');
            }
            $this->enlaceNoValido();
        }
        return $a;
    }

    private function enlaceNoValido(): never
    {
        http_response_code(404);
        View::render('titulacion:revisor_enlace_invalido', ['titulo' => 'Enlace no disponible'], 'publico');
        exit;
    }

    // ------------------------------------------------------------------ común

    private function cargarRonda(array $a): array
    {
        $r = RevisionRepository::ronda((int) $a['revision_id']);
        if ($r === null || $r['estado'] === 'cancelada') {
            $this->noEncontrado();
        }
        return $r;
    }

    private function mostrar(array $a, string $modo, string $token): void
    {
        $r = $this->cargarRonda($a);
        $correo = TitulacionAccess::correoSesion();
        $sesionPropia = Auth::usuario() !== null && $correo === strtolower((string) $a['miembro_email']);
        $pendienteVerificar = $sesionPropia && AccessControl::pending();
        $banner = null;
        if (!$sesionPropia) {
            $existe = (int) Database::valor('SELECT COUNT(*) FROM usuarios WHERE email = ? AND activo = 1', [strtolower((string) $a['miembro_email'])]) > 0;
            $banner = ['tipo' => Auth::usuario() ? 'otra' : ($existe ? 'login' : 'registro'), 'email' => $a['miembro_email']];
            $_SESSION['_registro_email'] = (string) $a['miembro_email'];
        } elseif ($pendienteVerificar) {
            $banner = ['tipo' => 'verificar', 'email' => $a['miembro_email']];
        }
        $datos = json_decode((string) $r['tramite_datos'], true) ?: [];
        $base = $modo === 'token' ? 'titulacion/revision/t/' . $token : 'titulacion/revision/' . (int) $a['id'];
        $this->vista('revisor_ver', [
            'titulo' => 'Revisión de protocolo ' . $r['folio'],
            'a' => $this->decorarAsignaciones([$a])[0], 'r' => $r, 'modo' => $modo, 'base' => $base, 'banner' => $banner,
            'puedeFirmar' => $sesionPropia && !$pendienteVerificar && in_array($a['estado'], ['pendiente', 'borrador'], true),
            'nivelFirma' => FirmaElectronica::nivelRevisores(),
            'carrera' => Catalogo::carreras()[$r['carrera']]['nombre'] ?? '',
            'modalidad' => Catalogo::modalidad((string) $r['modalidad'])['nombre'] ?? '',
            'editable' => in_array($a['estado'], ['pendiente', 'borrador'], true) && $r['estado'] !== 'dictaminada',
            'alumno' => $datos['datos']['nombre'] ?? $r['alumno_nombre'],
        ], $modo === 'token' && !Auth::usuario() ? 'publico' : 'app');
    }

    private function accionGuardar(array $a, string $volver): void
    {
        $this->cargarRonda($a);
        if (!in_array($a['estado'], ['pendiente', 'borrador'], true)) {
            flash('error', 'Esta revisión ya no se puede modificar.');
            redirigir($volver);
        }
        RevisionProtocolo::guardar($a, $_POST);
        flash('exito', 'Borrador guardado. Puedes volver con el mismo enlace.');
        redirigir($volver);
    }

    private function accionFirmar(array $a, string $volver, string $token): void
    {
        $r = $this->cargarRonda($a);
        // Primero se guarda lo capturado, para no perder el trabajo si falta iniciar sesión.
        if (in_array($a['estado'], ['pendiente', 'borrador'], true)) {
            RevisionProtocolo::guardar($a, $_POST);
            $a = RevisionRepository::asignacion((int) $a['id']) ?? $a;
        }
        $usuario = Auth::usuario();
        if (!$usuario || strtolower((string) $usuario['email']) !== strtolower((string) $a['miembro_email'])) {
            if ($token !== '') {
                Auth::recordarDestino('titulacion/revision/t/' . $token);
            }
            flash('error', 'Guardamos tu borrador. Para firmar necesitas una cuenta verificada con ' . $a['miembro_email'] . '.');
            redirigir($token !== '' ? $volver : 'login');
        }
        try {
            if (AccessControl::pending()) {
                throw new RuntimeException('Verifica tu correo para poder firmar.');
            }
            RevisionProtocolo::firmar($a, $r, $usuario, $this->post('codigo_2fa'));
            flash('exito', 'Revisión firmada y enviada al encargado de la comisión. ¡Gracias!');
        } catch (InvalidArgumentException | RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Titulación: error al firmar revisión: ' . $e->getMessage());
            flash('error', 'No se pudo firmar. Intenta de nuevo.');
        }
        redirigir('titulacion/revision/' . (int) $a['id']);
    }

    private function entregarProtocolo(array $a): never
    {
        $r = $this->cargarRonda($a);
        if (empty($r['doc_ruta'])) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::protocolo_descarga', 'atendido', 'Asignación ' . (int) $a['id']);
        $this->enviarArchivo((string) $r['doc_ruta'], $r['folio'] . '_protocolo.pdf', true);
    }
}
