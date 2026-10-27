<?php
declare(strict_types=1);

namespace App\Modules\Profesores;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Identidad;
use App\Core\View;
use App\Modules\Profesores\Services\CargaRrhh;

/**
 * Directorio de profesores para Recursos Humanos: carga del RFC (interno) y del número de empleado como desambiguadores, correos nuevos,
 * y enlace de cuentas con su registro. El RFC nunca se muestra aquí ni al profesor.
 */
final class ProfesoresController extends Controller
{
    private function esAdmin(): bool
    {
        return Auth::tieneRol('admin');
    }

    private function autorizar(): void
    {
        $rrhh = (bool) Database::valor('SELECT 1 FROM rrhh_administradores WHERE usuario_id = ?', [Auth::id()]);
        if (!$this->esAdmin() && !$rrhh) {
            Audit::record('profesores::rrhh', 'denegado');
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }

    public function index(): void
    {
        $this->autorizar();
        $resultado = $_SESSION['rrhh_resultado'] ?? null;
        unset($_SESSION['rrhh_resultado']);
        $this->vista('index', [
            'titulo' => 'Directorio de profesores',
            'total' => [
                'profesores' => (int) Database::valor('SELECT COUNT(*) FROM profesores'),
                'con_cuenta' => (int) Database::valor('SELECT COUNT(*) FROM profesores WHERE usuario_id IS NOT NULL'),
                'con_empleado' => (int) Database::valor('SELECT COUNT(*) FROM profesores WHERE num_empleado IS NOT NULL'),
                'sin_correo' => (int) Database::valor('SELECT COUNT(*) FROM profesores p WHERE NOT EXISTS (SELECT 1 FROM profesor_correos c WHERE c.profesor_id = p.id)'),
            ],
            'resultado' => $resultado,
            'porEnlazar' => CargaRrhh::cuentasPorEnlazar(),
            'porPasar' => CargaRrhh::correosPorPasar(),
            'esAdmin' => $this->esAdmin(),
            'responsables' => $this->esAdmin() ? Database::todas('SELECT u.id, u.nombre, u.email FROM rrhh_administradores r JOIN usuarios u ON u.id = r.usuario_id ORDER BY u.nombre') : [],
        ]);
    }

    public function subir(): void
    {
        $this->autorizar();
        $a = $_FILES['archivo'] ?? null;
        try {
            if (!$a || ($a['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $a['tmp_name'])) {
                throw new \InvalidArgumentException('Elige un archivo CSV o Excel (.xlsx).');
            }
            if ((int) $a['size'] > 3 * 1024 * 1024 || !in_array(strtolower(pathinfo((string) $a['name'], PATHINFO_EXTENSION)), ['csv', 'xlsx'], true)) {
                throw new \InvalidArgumentException('El archivo debe ser .csv o .xlsx y pesar menos de 3 MB.');
            }
            $r = CargaRrhh::aplicar(CargaRrhh::leer((string) $a['tmp_name'], (string) $a['name']));
            $_SESSION['rrhh_resultado'] = $r;
            Audit::record('profesores::rrhh_carga', 'correcto', json_encode($r['resumen']));
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('profesores/rrhh');
    }

    public function enlazar(): void
    {
        $this->autorizar();
        try {
            CargaRrhh::enlazar((int) ($_POST['usuario_id'] ?? 0), (int) ($_POST['profesor_id'] ?? 0));
            Audit::record('profesores::enlazar', 'correcto', 'Cuenta ' . (int) $_POST['usuario_id'] . ' · profesor ' . (int) $_POST['profesor_id']);
            flash('exito', 'La cuenta quedó enlazada al profesor del directorio.');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('profesores/rrhh');
    }

    public function usarCorreo(): void
    {
        $this->autorizar();
        try {
            CargaRrhh::usarCorreoDelDirectorio((int) ($_POST['usuario_id'] ?? 0), (string) ($_POST['email'] ?? ''));
            Audit::record('profesores::correo_de_cuenta', 'correcto', 'Cuenta ' . (int) $_POST['usuario_id']);
            flash('exito', 'La cuenta usará el correo nuevo. El anterior sigue reconocido para iniciar sesión.');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('profesores/rrhh');
    }

    public function agregarResponsable(): void
    {
        if (!$this->esAdmin()) {
            $this->autorizar();
            redirigir('profesores/rrhh');
        }
        $u = Identidad::usuarioPorCorreo((string) \App\Core\Auth::normalizarCorreoInstitucional((string) ($_POST['email'] ?? '')));
        if (!$u) {
            flash('error', 'Esa persona aún no tiene cuenta en la plataforma.');
        } else {
            Database::ejecutar('INSERT IGNORE INTO rrhh_administradores (usuario_id) VALUES (?)', [$u['id']]);
            Audit::record('profesores::rrhh_responsable', 'correcto', 'Alta de cuenta ' . (int) $u['id']);
            flash('exito', 'Quedó como responsable de Recursos Humanos.');
        }
        redirigir('profesores/rrhh');
    }

    public function quitarResponsable(): void
    {
        $this->autorizar();
        if ($this->esAdmin()) {
            Database::ejecutar('DELETE FROM rrhh_administradores WHERE usuario_id = ?', [(int) ($_POST['usuario_id'] ?? 0)]);
            Audit::record('profesores::rrhh_responsable', 'correcto', 'Baja de cuenta ' . (int) ($_POST['usuario_id'] ?? 0));
        }
        redirigir('profesores/rrhh');
    }
}
