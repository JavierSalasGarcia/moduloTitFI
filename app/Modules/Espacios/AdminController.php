<?php
declare(strict_types=1);

namespace App\Modules\Espacios;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;
use PDOException;

/** Administración del módulo: espacios, horarios, personas autorizadas y administradores. */
final class AdminController extends Controller
{
    private function soloAdmin(): void
    {
        if (!EspaciosAccess::esAdmin()) {
            Audit::record('espacios::admin', 'denegado', 'Administración de auditorios y salas');
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }

    private function espacioPost(string $id): array
    {
        $this->soloAdmin();
        $e = ctype_digit($id) ? Agenda::espacio((int) $id) : null;
        if ($e === null) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No disponible']);
            exit;
        }
        return $e;
    }

    /** Busca una cuenta activa por correo. */
    private function usuarioPorCorreo(string $email): ?array
    {
        return Database::una('SELECT id, nombre, email FROM usuarios WHERE email = ? AND activo = 1', [strtolower(trim($email))]);
    }

    public function index(): void
    {
        $this->soloAdmin();
        $espacios = Agenda::espacios(false);
        foreach ($espacios as &$e) {
            $e['horario'] = Agenda::horario((int) $e['id']);
            $e['autorizados'] = Database::todas(
                'SELECT u.id, u.nombre, u.email FROM esp_autorizados a JOIN usuarios u ON u.id = a.usuario_id WHERE a.espacio_id = ? ORDER BY u.nombre',
                [(int) $e['id']]
            );
        }
        unset($e);
        $this->vista('admin', [
            'titulo' => 'Auditorios y salas · Administración',
            'espacios' => $espacios,
            'esAdminGeneral' => EspaciosAccess::esAdminGeneral(),
            'administradores' => Database::todas('SELECT u.id, u.nombre, u.email, a.creado_en FROM esp_administradores a JOIN usuarios u ON u.id = a.usuario_id ORDER BY u.nombre'),
        ]);
    }

    public function guardarEspacio(): void
    {
        $this->soloAdmin();
        $id = (int) $this->post('id');
        $nombre = mb_substr($this->post('nombre'), 0, 120);
        if (mb_strlen($nombre) < 3) {
            flash('error', 'Escribe el nombre del espacio.');
            redirigir('espacios/admin');
        }
        $datos = [
            $nombre, mb_substr($this->post('ubicacion'), 0, 160) ?: null,
            ctype_digit($this->post('capacidad')) ? min(65000, (int) $this->post('capacidad')) : null,
            mb_substr($this->post('descripcion'), 0, 500) ?: null,
            $this->post('activo', '1') === '1' ? 1 : 0, max(0, min(9999, (int) $this->post('orden', '100'))),
        ];
        try {
            if ($id > 0) {
                $this->espacioPost((string) $id);
                Database::ejecutar('UPDATE esp_espacios SET nombre = ?, ubicacion = ?, capacidad = ?, descripcion = ?, activo = ?, orden = ? WHERE id = ?', [...$datos, $id]);
            } else {
                $id = Database::ejecutar('INSERT INTO esp_espacios (nombre, ubicacion, capacidad, descripcion, activo, orden) VALUES (?,?,?,?,?,?)', $datos);
                foreach ([1, 2, 3, 4, 5] as $dia) {
                    Database::ejecutar("INSERT INTO esp_horarios (espacio_id, dia, hora_inicio, hora_fin) VALUES (?, ?, '08:00:00', '20:00:00')", [$id, $dia]);
                }
            }
        } catch (PDOException) {
            flash('error', 'Ya existe un espacio con ese nombre.');
            redirigir('espacios/admin');
        }
        Audit::record('espacios::espacio', 'correcto', 'Espacio ' . $id);
        flash('exito', 'Espacio guardado.');
        redirigir('espacios/admin#esp-' . $id);
    }

    /** Sustituye el horario del espacio con lo capturado (h[dia][] = «HH:MM-HH:MM»). */
    public function horario(string $id): void
    {
        $e = $this->espacioPost($id);
        $entrada = is_array($_POST['h'] ?? null) ? $_POST['h'] : [];
        $filas = [];
        foreach (Agenda::DIAS as $dia => $nombreDia) {
            $texto = trim((string) ($entrada[$dia] ?? ''));
            foreach (preg_split('/[\s,;]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $rango) {
                if (!preg_match('/^([01]?\d|2[0-3]):([0-5]\d)-([01]?\d|2[0-3]):([0-5]\d)$/D', $rango, $m)) {
                    flash('error', 'Revisa el horario del ' . mb_strtolower($nombreDia) . ': usa rangos como 08:00-14:00.');
                    redirigir('espacios/admin#esp-' . (int) $e['id']);
                }
                $ini = sprintf('%02d:%02d', $m[1], $m[2]);
                $fin = sprintf('%02d:%02d', $m[3], $m[4]);
                if ($ini >= $fin) {
                    flash('error', 'En el ' . mb_strtolower($nombreDia) . ' la hora de inicio debe ser anterior a la de término.');
                    redirigir('espacios/admin#esp-' . (int) $e['id']);
                }
                $filas[$dia . $ini] = [$dia, $ini, $fin];
            }
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        Database::ejecutar('DELETE FROM esp_horarios WHERE espacio_id = ?', [(int) $e['id']]);
        foreach ($filas as [$dia, $ini, $fin]) {
            Database::ejecutar('INSERT INTO esp_horarios (espacio_id, dia, hora_inicio, hora_fin) VALUES (?, ?, ?, ?)', [(int) $e['id'], $dia, $ini . ':00', $fin . ':00']);
        }
        $pdo->commit();
        Audit::record('espacios::horario', 'correcto', 'Espacio ' . (int) $e['id']);
        flash('exito', 'Horario de ' . $e['nombre'] . ' guardado. Las ocupaciones ya registradas no cambian.');
        redirigir('espacios/admin#esp-' . (int) $e['id']);
    }

    public function autorizar(string $id): void
    {
        $e = $this->espacioPost($id);
        $u = $this->usuarioPorCorreo($this->post('email'));
        if ($u === null) {
            flash('error', 'No hay una cuenta activa con ese correo.');
        } else {
            Database::ejecutar('INSERT IGNORE INTO esp_autorizados (espacio_id, usuario_id, creado_por) VALUES (?, ?, ?)', [(int) $e['id'], (int) $u['id'], Auth::id()]);
            Audit::record('espacios::autorizar', 'correcto', 'Espacio ' . (int) $e['id'] . ' · usuario ' . (int) $u['id']);
            flash('exito', $u['nombre'] . ' ya puede registrar la ocupación de ' . $e['nombre'] . '.');
        }
        redirigir('espacios/admin#esp-' . (int) $e['id']);
    }

    public function quitarAutorizado(string $id, string $usuario): void
    {
        $e = $this->espacioPost($id);
        Database::ejecutar('DELETE FROM esp_autorizados WHERE espacio_id = ? AND usuario_id = ?', [(int) $e['id'], (int) $usuario]);
        Audit::record('espacios::autorizar', 'retirado', 'Espacio ' . (int) $e['id'] . ' · usuario ' . (int) $usuario);
        flash('exito', 'Autorización retirada.');
        redirigir('espacios/admin#esp-' . (int) $e['id']);
    }

    public function agregarAdministrador(): void
    {
        $this->soloAdminGeneral();
        $u = $this->usuarioPorCorreo($this->post('email'));
        if ($u === null) {
            flash('error', 'No hay una cuenta activa con ese correo.');
        } else {
            Database::ejecutar('INSERT IGNORE INTO esp_administradores (usuario_id, creado_por) VALUES (?, ?)', [(int) $u['id'], Auth::id()]);
            Audit::record('espacios::administrador', 'correcto', 'Usuario ' . (int) $u['id']);
            flash('exito', $u['nombre'] . ' ahora administra Auditorios y salas.');
        }
        redirigir('espacios/admin#administradores');
    }

    public function quitarAdministrador(string $usuario): void
    {
        $this->soloAdminGeneral();
        Database::ejecutar('DELETE FROM esp_administradores WHERE usuario_id = ?', [(int) $usuario]);
        Audit::record('espacios::administrador', 'retirado', 'Usuario ' . (int) $usuario);
        flash('exito', 'Administrador retirado.');
        redirigir('espacios/admin#administradores');
    }

    private function soloAdminGeneral(): void
    {
        if (!EspaciosAccess::esAdminGeneral()) {
            Audit::record('espacios::administrador', 'denegado', 'Reservado al administrador general');
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }
}
