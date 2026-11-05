<?php
declare(strict_types=1);

namespace App\Modules\Auth;

use App\Core\{AccessControl, Audit, Auth, Controller, Database};
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\{AccesoEgresados, Correo};

/**
 * Solicitud pública de acceso para egresados sin correo institucional. No crea cuentas: crea una solicitud que el personal
 * de Titulación valida (docs/ACCESO_EGRESADOS.md). Cada solicitud se atiende con un enlace personal (token) enviado al correo.
 */
final class EgresadoController extends Controller
{
    private function listo(): void
    {
        if (!AccesoEgresados::tablasListas() || !AccessControl::ready()) {
            http_response_code(503);
            exit('La solicitud de acceso para egresados aún no está disponible. Intenta más tarde.');
        }
    }

    private function enlace(string $token): string
    {
        return Correo::enlace('registro/egresado/s/' . $token);
    }

    public function formulario(): void
    {
        $this->listo();
        if (Auth::id()) {
            redirigir('');
        }
        $_SESSION['_egr_t'] = time();
        $this->vista('egresado_form', ['titulo' => 'Acceso para egresados', 'carreras' => Catalogo::carreras()], 'auth');
    }

    public function crear(): void
    {
        $this->listo();
        if (Auth::id()) {
            redirigir('');
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (AccessControl::limited('egresado:' . $ip, 5, 3600)) {
            flash('error', 'Se alcanzó el límite de solicitudes desde esta conexión. Intenta más tarde.');
            redirigir('registro/egresado');
        }
        // Campo trampa y pausa mínima: las personas no llenan el campo oculto ni envían en menos de 4 segundos.
        if ($this->post('sitio_web') !== '' || time() - (int) ($_SESSION['_egr_t'] ?? 0) < 4) {
            flash('error', 'No se pudo enviar el formulario. Revísalo e inténtalo de nuevo.');
            redirigir('registro/egresado');
        }
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $d = [
            'nombre' => preg_replace('/\s+/', ' ', $this->post('nombre')) ?? '',
            'genero' => in_array($_POST['genero'] ?? '', ['masculino', 'femenino', 'no_binario'], true) ? (string) $_POST['genero'] : '',
            'carrera' => array_key_exists((string) ($_POST['carrera'] ?? ''), Catalogo::carreras()) ? (string) $_POST['carrera'] : '',
            'cuenta' => AccesoEgresados::cuentaNorm($this->post('cuenta')),
            'email' => $email,
            'telefono' => preg_replace('/[^0-9+ ()-]/', '', $this->post('telefono')) ?? '',
        ];
        $_SESSION['_egr_form'] = array_diff_key($d, ['cuenta' => 1]);
        if (mb_strlen($d['nombre']) < 8 || mb_strlen($d['nombre']) > 120 || $d['genero'] === '' || $d['carrera'] === ''
            || !preg_match('/^\d{6,8}$/D', preg_replace('/\D/', '', $this->post('cuenta')) ?? '')
            || strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($d['telefono']) > 20 || empty($_POST['aviso'])) {
            flash('error', 'Revisa tus datos: nombre completo, género, licenciatura, número de cuenta (6 a 8 dígitos), correo válido y la aceptación del aviso.');
            redirigir('registro/egresado');
        }
        if (Auth::normalizarCorreoInstitucional($email) !== null) {
            flash('alerta', 'Ese es un correo institucional: crea tu cuenta con el registro normal.');
            redirigir('registro');
        }
        if (AccessControl::limited('egresado-correo:' . hash('sha256', $email), 3, 3600)) {
            flash('error', 'Ya se hicieron varias solicitudes con ese correo. Intenta más tarde.');
            redirigir('registro/egresado');
        }
        if (AccesoEgresados::correoConCuenta($email)) {
            flash('error', 'Ese correo ya tiene una cuenta. Inicia sesión o recupera tu acceso desde el inicio de sesión.');
            redirigir('login');
        }
        if (AccesoEgresados::hayAbierta($d['cuenta'], $email)) {
            flash('error', 'Ya hay una solicitud abierta con ese número de cuenta o correo. Revisa tu correo: ahí está el enlace para continuarla.');
            redirigir('registro/egresado');
        }
        $r = AccesoEgresados::crear($d, $ip);
        $s = AccesoEgresados::porToken($r['token']);
        $ok = $s && AccesoEgresados::enviarCodigo($s, $this->enlace($r['token']));
        Audit::record('egresado_solicitud', $ok ? 'correcto' : 'error', 'solicitud ' . $r['id']);
        unset($_SESSION['_egr_form'], $_SESSION['_egr_t']);
        flash($ok ? 'exito' : 'alerta', $ok ? 'Te enviamos un código de 6 dígitos a tu correo. Escríbelo para continuar.' : 'No pudimos enviar el código. Pide otro en un minuto.');
        redirigir('registro/egresado/s/' . $r['token']);
    }

    private function solicitud(string $token): array
    {
        $this->listo();
        $s = AccesoEgresados::porToken($token);
        if (!$s) {
            http_response_code(404);
            exit('Enlace no válido o vencido. Si lo necesitas, inicia una solicitud nueva.');
        }
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: no-referrer');
        return $s;
    }

    public function estado(string $token): void
    {
        $s = $this->solicitud($token);
        $this->vista('egresado_estado', ['titulo' => 'Solicitud de acceso', 's' => $s, 'token' => $token,
            'docs' => AccesoEgresados::documentos((int) $s['id'])], 'auth');
    }

    public function codigo(string $token): void
    {
        $s = $this->solicitud($token);
        if (AccessControl::limited('egresado-cod:' . $s['id'], 12, 3600)) {
            flash('error', 'Demasiados intentos. Intenta más tarde.');
            redirigir('registro/egresado/s/' . $token);
        }
        $ok = AccesoEgresados::verificarCodigo($s, $this->post('codigo'));
        Audit::record('egresado_verificacion', $ok ? 'correcto' : 'denegado', 'solicitud ' . $s['id']);
        flash($ok ? 'exito' : 'error', $ok ? 'Correo verificado.' : 'Código incorrecto, vencido o sin intentos. Pide otro código.');
        redirigir('registro/egresado/s/' . $token);
    }

    public function reenviar(string $token): void
    {
        $s = $this->solicitud($token);
        if ($s['estado'] === 'sin_verificar' && !AccessControl::limited('egresado-reenvio:' . $s['id'], 5, 3600)) {
            $ok = AccesoEgresados::enviarCodigo($s, $this->enlace($token));
            flash($ok ? 'exito' : 'alerta', $ok ? 'Enviamos otro código.' : 'Espera un minuto antes de pedir otro código.');
        }
        redirigir('registro/egresado/s/' . $token);
    }

    public function documentos(string $token): void
    {
        $s = $this->solicitud($token);
        if ($s['estado'] !== 'requiere_docs' || AccessControl::limited('egresado-docs:' . $s['id'], 20, 3600)) {
            redirigir('registro/egresado/s/' . $token);
        }
        $subidos = 0;
        foreach (['identificacion', 'comprobante'] as $tipo) {
            $f = $_FILES[$tipo] ?? null;
            if (!$f || (int) $f['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                AccesoEgresados::guardarDocumento((int) $s['id'], $tipo, $f);
                $subidos++;
            } catch (\Throwable $e) {
                flash('error', ($tipo === 'identificacion' ? 'Identificación: ' : 'Comprobante: ') . $e->getMessage());
                redirigir('registro/egresado/s/' . $token);
            }
        }
        $s = AccesoEgresados::porToken($token) ?? $s;
        $enviada = AccesoEgresados::enviarAValidar($s);
        Audit::record('egresado_documentos', 'correcto', 'solicitud ' . $s['id']);
        flash('exito', $enviada ? 'Recibimos tus documentos. El personal de Titulación revisará tu solicitud.' : ($subidos ? 'Documento guardado. Falta el otro para enviar tu solicitud.' : 'Elige al menos un archivo.'));
        redirigir('registro/egresado/s/' . $token);
    }

    public function clave(string $token): void
    {
        $s = $this->solicitud($token);
        $clave = (string) ($_POST['clave'] ?? '');
        if (strlen($clave) < 10 || strlen($clave) > 72 || $clave !== ($_POST['confirmacion'] ?? '')) {
            flash('error', 'Las contraseñas deben coincidir y tener entre 10 y 72 caracteres.');
            redirigir('registro/egresado/s/' . $token);
        }
        if (!AccesoEgresados::fijarClave($s, $clave)) {
            flash('error', 'El enlace ya se usó o venció. Pide al Departamento de Evaluación Profesional que te lo envíe de nuevo.');
            redirigir('registro/egresado/s/' . $token);
        }
        flash('exito', 'Contraseña guardada. Inicia sesión con tu correo personal; te pediremos un código que llegará a ese correo.');
        redirigir('login');
    }
}
