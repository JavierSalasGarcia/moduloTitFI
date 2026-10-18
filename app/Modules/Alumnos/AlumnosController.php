<?php
declare(strict_types=1);

namespace App\Modules\Alumnos;

use App\Core\Auth;
use App\Core\AccessControl;
use App\Core\Controller;
use App\Core\Database;

/**
 * Padrón de alumnos con vista granular: todos, por nivel o por
 * programa (pestañas en orden alfabético por siglas).
 */
final class AlumnosController extends Controller
{
    /** Hash de la contraseña inicial "fi2026" para altas de demo. */
    private const HASH_INICIAL = '$2y$10$RoWEsK0CZFDyyMZqOBCBIOX2h9RCHePcTaFifTWP/Y3Q3W2iD7.4y';

    public function index(): void
    {
        $programas = Database::todas(
            'SELECT p.*, (SELECT COUNT(*) FROM usuarios u WHERE u.programa_id = p.id AND u.rol = \'alumno\' AND u.activo = 1) AS alumnos
               FROM programas p ORDER BY p.nivel = \'posgrado\', p.siglas');

        $filtro = $_GET['programa'] ?? 'todos';
        $q = trim((string) ($_GET['q'] ?? ''));

        $sql = "SELECT u.id, u.nombre, u.email, u.creado_en, p.siglas, p.nombre AS programa, p.nivel
                  FROM usuarios u JOIN programas p ON p.id = u.programa_id
                 WHERE u.rol = 'alumno' AND u.activo = 1";
        $params = [];
        if (in_array($filtro, ['licenciatura', 'posgrado'], true)) {
            $sql .= ' AND p.nivel = ?';
            $params[] = $filtro;
        } elseif ($filtro !== 'todos') {
            $sql .= ' AND p.siglas = ?';
            $params[] = $filtro;
        }
        if ($q !== '') {
            $sql .= ' AND (u.nombre LIKE ? OR u.email LIKE ?)';
            $params[] = "%{$q}%";
            $params[] = "%{$q}%";
        }
        $alumnos = Database::todas($sql . ' ORDER BY p.siglas, u.nombre', $params);

        $this->vista('index', [
            'titulo'    => 'Alumnos',
            'programas' => $programas,
            'alumnos'   => $alumnos,
            'filtro'    => $filtro,
            'q'         => $q,
            'puedeAlta' => Auth::tieneRol('admin') || Auth::usuario()['depto_clave'] === 'departamento_de_servicios_escolares',
        ]);
    }

    public function crear(): void
    {
        $puede = Auth::tieneRol('admin') || Auth::usuario()['depto_clave'] === 'departamento_de_servicios_escolares';
        if (!$puede) {
            flash('error', 'Solo Servicios Escolares o la administración pueden dar de alta alumnos.');
            redirigir('alumnos');
        }

        $nombre = $this->post('nombre');
        $email  = strtolower($this->post('email'));
        $programa = Database::una('SELECT * FROM programas WHERE id = ?', [(int) $this->post('programa')]);

        if ($nombre === '' || !AccessControl::isUaemexEmail($email) || !$programa) {
            flash('error', 'Verifica nombre, programa y correo; el correo debe contener uaemex.mx.');
            redirigir('alumnos');
        }
        if (Database::una('SELECT 1 FROM usuarios WHERE email = ?', [$email])) {
            flash('error', 'Ya existe un usuario con ese correo.');
            redirigir('alumnos');
        }

        Database::ejecutar(
            "INSERT INTO usuarios (nombre, email, password_hash, departamento_id, programa_id, rol)
             VALUES (?, ?, ?, ?, ?, 'alumno')",
            [$nombre, $email, self::HASH_INICIAL, $programa['depto_id'], $programa['id']]
        );
        flash('exito', "Alumno registrado en {$programa['siglas']} (contraseña inicial: fi2026).");
        redirigir('alumnos?programa=' . $programa['siglas']);
    }
}
