<?php
declare(strict_types=1);

namespace App\Modules\Ajustes;

use App\Core\AccessControl;
use App\Core\Controller;
use App\Core\Database;

/** Parámetros configurables de la plataforma (solo administración). */
final class AjustesController extends Controller
{
    public function index(): void
    {
        $this->asegurarAjustesNvidia();
        $this->vista('index', [
            'titulo'  => 'Ajustes de la plataforma',
            'ajustes' => Database::todas('SELECT * FROM ajustes ORDER BY clave'),
            // Los antiguos departamentos «Titular de…» se reemplazan por el cargo Titular/Asistente; solo se listan los que aún tienen cuentas.
            'departamentos' => Database::todas("SELECT id, nombre FROM departamentos WHERE clave NOT LIKE 'titular\_de\_%' OR id IN (SELECT departamento_id FROM usuarios WHERE departamento_id IS NOT NULL) OR id IN (SELECT departamento_id FROM usuario_roles) ORDER BY nombre"),
        ]);
    }

    private function asegurarAjustesNvidia(): void
    {
        $ajustes = [
            ['ia_nvidia_api_key', '', 'API key de NVIDIA NIM (opcional; vacío = motor local)'],
            ['ia_nvidia_modelo', 'meta/llama-3.1-8b-instruct', 'Modelo de chat de NVIDIA NIM'],
            ['ia_nvidia_url', 'https://integrate.api.nvidia.com/v1/chat/completions', 'Endpoint compatible con OpenAI de NVIDIA NIM'],
        ];
        foreach ($ajustes as $ajuste) {
            Database::ejecutar(
                'INSERT IGNORE INTO ajustes (clave, valor, descripcion) VALUES (?, ?, ?)',
                $ajuste
            );
        }
    }

    public function guardar(): void
    {
        foreach ((array) ($_POST['valor'] ?? []) as $clave => $valor) {
            // Dedicated assistant settings have their own validated, admin-only endpoint.
            if (str_starts_with((string)$clave,'ia_') && !in_array($clave,['ia_nvidia_api_key','ia_nvidia_modelo','ia_nvidia_url','ia_api_key','ia_modelo_embeddings','ia_url_embeddings'],true)) continue;
            if (str_contains((string) $clave, 'api_key') && trim((string) $valor) === '') {
                continue;
            }
            Database::ejecutar(
                'UPDATE ajustes SET valor = ? WHERE clave = ?',
                [trim((string) $valor), (string) $clave]
            );
        }
        flash('exito', 'Ajustes guardados.');
        redirigir('ajustes');
    }

    public function probarNvidia(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $saved = array_column(Database::todas("SELECT clave, valor FROM ajustes WHERE clave LIKE 'ia_nvidia_%'"), 'valor', 'clave');
        $key = trim((string) ($_POST['api_key'] ?? '')) ?: ($saved['ia_nvidia_api_key'] ?? '');
        $model = trim((string) ($_POST['modelo'] ?? '')) ?: ($saved['ia_nvidia_modelo'] ?? 'meta/llama-3.1-8b-instruct');
        if ($key === '' || !function_exists('curl_init')) {
            echo json_encode(['ok'=>false, 'mensaje'=>$key === '' ? 'Introduce una clave de NVIDIA para probar la conexión.' : 'El servidor no tiene disponible la conexión con NVIDIA.']);
            return;
        }
        $curl = curl_init('https://integrate.api.nvidia.com/v1/chat/completions');
        curl_setopt_array($curl, [CURLOPT_POST=>true, CURLOPT_RETURNTRANSFER=>true, CURLOPT_CONNECTTIMEOUT=>8,
            CURLOPT_TIMEOUT=>25, CURLOPT_HTTPHEADER=>['Content-Type: application/json','Authorization: Bearer '.$key],
            CURLOPT_POSTFIELDS=>json_encode(['model'=>$model,'messages'=>[['role'=>'user','content'=>'Responde solamente OK.']], 'max_tokens'=>16, 'stream'=>false])]);
        $response = curl_exec($curl); $code = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE); curl_close($curl);
        $data = is_string($response) ? json_decode($response, true) : null;
        $ok = $code >= 200 && $code < 300 && isset($data['choices'][0]);
        echo json_encode(['ok'=>$ok, 'mensaje'=>$ok ? 'Conexión comprobada. El modelo respondió correctamente. Guarda los ajustes para utilizar estos datos.' : match($code) {
            401,403=>'La clave no permite acceder. Revisa tu cuenta y los permisos de NVIDIA.',
            404=>'El modelo no está disponible. Revisa su nombre.', 429=>'NVIDIA alcanzó el límite de solicitudes. Intenta más tarde.',
            default=>'No fue posible obtener una respuesta. Revisa la clave y el modelo o intenta más tarde.'
        }]);
    }

    public function usuarios(): void
    {
        $orden = (string) ($_GET['orden'] ?? 'recientes');
        $ordenes = [
            'recientes' => 'u.creado_en DESC, u.id DESC',
            'antiguos' => 'u.creado_en ASC, u.id ASC',
            'nombre_asc' => 'u.nombre ASC, u.id ASC',
            'nombre_desc' => 'u.nombre DESC, u.id DESC',
        ];
        if (!isset($ordenes[$orden])) {
            $orden = 'recientes';
        }

        $usuarios = Database::todas("SELECT u.*, d.nombre AS depto_nombre, p.siglas AS programa_siglas, (SELECT GROUP_CONCAT(rp.codigo SEPARATOR ', ') FROM reuniones_coordinadores rc JOIN reuniones_posgrados rp ON rp.id = rc.posgrado_id WHERE rc.usuario_id = u.id AND rc.activo = 1) AS posgrado_codigo, (SELECT GROUP_CONCAT(CAST(rp.id AS CHAR) SEPARATOR ', ') FROM reuniones_coordinadores rc JOIN reuniones_posgrados rp ON rp.id = rc.posgrado_id WHERE rc.usuario_id = u.id AND rc.activo = 1) AS posgrado_ids FROM usuarios u LEFT JOIN departamentos d ON d.id = u.departamento_id LEFT JOIN programas p ON p.id = u.programa_id ORDER BY {$ordenes[$orden]}");
        $extras = [];
        foreach (Database::todas('SELECT r.usuario_id, r.rol, r.departamento_id, r.programa_id, r.cargo, d.nombre AS depto_nombre, p.siglas AS programa_siglas FROM usuario_roles r LEFT JOIN departamentos d ON d.id = r.departamento_id LEFT JOIN programas p ON p.id = r.programa_id ORDER BY r.id') as $r) {
            $extras[(int) $r['usuario_id']][] = $r;
        }
        foreach ($usuarios as &$usr) {
            $usr['roles_extra'] = $extras[(int) $usr['id']] ?? [];
        }
        unset($usr);

        $this->vista('usuarios', [
            'titulo' => 'Gestión de usuarios y coordinadores',
            // El panel administrativo permite localizar cualquier cuenta y ajustar su rol y adscripción.
            'usuarios' => Database::todas("SELECT u.*, d.nombre AS depto_nombre, p.siglas AS programa_siglas, (SELECT GROUP_CONCAT(rp.codigo SEPARATOR ', ') FROM reuniones_coordinadores rc JOIN reuniones_posgrados rp ON rp.id = rc.posgrado_id WHERE rc.usuario_id = u.id AND rc.activo = 1) AS posgrado_codigo, (SELECT GROUP_CONCAT(CAST(rp.id AS CHAR) SEPARATOR ', ') FROM reuniones_coordinadores rc JOIN reuniones_posgrados rp ON rp.id = rc.posgrado_id WHERE rc.usuario_id = u.id AND rc.activo = 1) AS posgrado_ids FROM usuarios u LEFT JOIN departamentos d ON d.id = u.departamento_id LEFT JOIN programas p ON p.id = u.programa_id ORDER BY {$ordenes[$orden]}"),
            'orden' => $orden,
            'departamentos' => Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre'),
            'programas' => Database::todas('SELECT id, siglas, nombre, nivel FROM programas ORDER BY nivel, siglas'),
            'posgrados' => Database::todas('SELECT id, codigo, nombre FROM reuniones_posgrados WHERE activo = 1 ORDER BY codigo')
        ]);
    }

    /** @return array{0:?string,1:?string} grado y género capturados en el formulario */
    private function tratamiento(): array
    {
        $grado = mb_substr(trim($this->post('grado')), 0, 30);
        $genero = $this->post('genero');
        return [$grado !== '' ? $grado : null, in_array($genero, ['masculino', 'femenino', 'no_binario'], true) ? $genero : null];
    }

    public function crearUsuario(): void
    {
        $nombre = $this->post('nombre');
        $email = $this->post('email');
        $password = $this->post('password');
        $deptoId = (isset($_POST['departamento_id']) && $_POST['departamento_id'] !== '') ? (int)$_POST['departamento_id'] : null;
        $rol = $this->post('rol');
        [$grado, $genero] = $this->tratamiento();
        $permiteContrasena = isset($_POST['permite_contrasena']) ? 1 : 0;
        
        $tipoContratacion = ($rol === 'profesor') ? $this->post('tipo_contratacion') : null;
        $programaId = ($rol === 'alumno') ? ((isset($_POST['programa_id']) && $_POST['programa_id'] !== '') ? (int)$_POST['programa_id'] : null) : null;
        $posgradoId = ($rol === 'coordinador') ? ((isset($_POST['posgrado_id']) && $_POST['posgrado_id'] !== '') ? (int)$_POST['posgrado_id'] : null) : null;

        if ($nombre === '' || $password === '' || !$rol || !AccessControl::isUaemexEmail($email)) {
            flash('error', 'Completa los campos obligatorios. El correo debe ser válido y contener uaemex.mx.');
            redirigir('ajustes/usuarios');
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        try {
            Database::ejecutar(
                'INSERT INTO usuarios (nombre, grado, genero, email, password_hash, departamento_id, rol, activo, tipo_contratacion, programa_id, permite_contrasena) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?)',
                [$nombre, $grado, $genero, $email, $hash, $deptoId, $rol, $tipoContratacion, $programaId, $permiteContrasena]
            );
            $nuevoId = (int) Database::pdo()->lastInsertId();
            if ($rol === 'coordinador' && $posgradoId) {
                Database::ejecutar('UPDATE reuniones_coordinadores SET activo = 0 WHERE posgrado_id = ? AND activo = 1', [$posgradoId]);
                Database::ejecutar('INSERT INTO reuniones_coordinadores (posgrado_id, usuario_id, activo) VALUES (?, ?, 1)', [$posgradoId, $nuevoId]);
            }
            flash('exito', 'Usuario creado exitosamente.');
        } catch (\Exception $e) {
            flash('error', 'Error al crear usuario (posible correo duplicado): ' . $e->getMessage());
        }

        redirigir('ajustes/usuarios');
    }

    /** Cargo principal (Titular / Asistente) y roles adicionales de la cuenta. */
    private function guardarCargoYRoles(int $usuarioId, string $rolPrincipal): void
    {
        $cargo = $this->post('cargo');
        $cargo = in_array($cargo, ['titular', 'asistente'], true) && in_array($rolPrincipal, ['titular', 'operativo'], true) ? $cargo : null;
        Database::ejecutar('UPDATE usuarios SET cargo = ? WHERE id = ?', [$cargo, $usuarioId]);
        if (!isset($_POST['extra']) && !isset($_POST['extra_enviado'])) {
            return;
        }
        Database::ejecutar('DELETE FROM usuario_roles WHERE usuario_id = ?', [$usuarioId]);
        $vistos = [];
        foreach ((array) ($_POST['extra'] ?? []) as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $rol = (string) ($fila['rol'] ?? '');
            if (!\App\Core\Roles::valido($rol)) {
                continue;
            }
            $depto = (int) ($fila['departamento_id'] ?? 0);
            $programa = (int) ($fila['programa_id'] ?? 0);
            $cargoExtra = in_array($fila['cargo'] ?? '', ['titular', 'asistente'], true) && in_array($rol, ['titular', 'operativo'], true) ? $fila['cargo'] : null;
            $clave = "$rol|$depto|$programa";
            if (isset($vistos[$clave])) {
                continue;
            }
            $vistos[$clave] = true;
            Database::ejecutar('INSERT INTO usuario_roles (usuario_id, rol, departamento_id, programa_id, cargo) VALUES (?, ?, ?, ?, ?)', [$usuarioId, $rol, $depto, $programa, $cargoExtra]);
        }
    }

    public function actualizarUsuario(string $id): void
    {
        $nombre = $this->post('nombre');
        $email = $this->post('email');
        $password = $this->post('password');
        $deptoId = (isset($_POST['departamento_id']) && $_POST['departamento_id'] !== '') ? (int)$_POST['departamento_id'] : null;
        $rol = $this->post('rol');
        [$grado, $genero] = $this->tratamiento();
        $activo = isset($_POST['activo']) ? 1 : 0;
        $permiteContrasena = isset($_POST['permite_contrasena']) ? 1 : 0;
        
        $tipoContratacion = ($rol === 'profesor') ? $this->post('tipo_contratacion') : null;
        $programaId = ($rol === 'alumno') ? ((isset($_POST['programa_id']) && $_POST['programa_id'] !== '') ? (int)$_POST['programa_id'] : null) : null;
        $posgradoId = ($rol === 'coordinador') ? ((isset($_POST['posgrado_id']) && $_POST['posgrado_id'] !== '') ? (int)$_POST['posgrado_id'] : null) : null;

        if ($nombre === '' || !$rol || !AccessControl::isUaemexEmail($email)) {
            flash('error', 'Nombre y rol son obligatorios; el correo debe ser válido y contener uaemex.mx.');
            redirigir('ajustes/usuarios');
        }

        try {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                Database::ejecutar(
                    'UPDATE usuarios SET nombre = ?, grado = ?, genero = ?, email = ?, password_hash = ?, departamento_id = ?, rol = ?, activo = ?, tipo_contratacion = ?, programa_id = ?, logins_contrasena_restantes = CASE WHEN permite_contrasena = 0 AND ? = 1 THEN 20 ELSE logins_contrasena_restantes END, permite_contrasena = ? WHERE id = ?',
                    [$nombre, $grado, $genero, $email, $hash, $deptoId, $rol, $activo, $tipoContratacion, $programaId, $permiteContrasena, $permiteContrasena, (int)$id]
                );
            } else {
                Database::ejecutar(
                    'UPDATE usuarios SET nombre = ?, grado = ?, genero = ?, email = ?, departamento_id = ?, rol = ?, activo = ?, tipo_contratacion = ?, programa_id = ?, logins_contrasena_restantes = CASE WHEN permite_contrasena = 0 AND ? = 1 THEN 20 ELSE logins_contrasena_restantes END, permite_contrasena = ? WHERE id = ?',
                    [$nombre, $grado, $genero, $email, $deptoId, $rol, $activo, $tipoContratacion, $programaId, $permiteContrasena, $permiteContrasena, (int)$id]
                );
            }

            if ($rol === 'coordinador' && $posgradoId) {
                Database::ejecutar('UPDATE reuniones_coordinadores SET activo = 0 WHERE posgrado_id = ? AND activo = 1', [$posgradoId]);
                Database::ejecutar('UPDATE reuniones_coordinadores SET activo = 0 WHERE usuario_id = ? AND activo = 1 AND posgrado_id <> ?', [(int)$id, $posgradoId]);
                $existe = (int) Database::valor('SELECT COUNT(*) FROM reuniones_coordinadores WHERE usuario_id = ? AND posgrado_id = ?', [(int)$id, $posgradoId]);
                if ($existe > 0) {
                    Database::ejecutar('UPDATE reuniones_coordinadores SET activo = 1 WHERE usuario_id = ? AND posgrado_id = ?', [(int)$id, $posgradoId]);
                } else {
                    Database::ejecutar('INSERT INTO reuniones_coordinadores (posgrado_id, usuario_id, activo) VALUES (?, ?, 1)', [$posgradoId, (int)$id]);
                }
            } else {
                Database::ejecutar('UPDATE reuniones_coordinadores SET activo = 0 WHERE usuario_id = ? AND activo = 1', [(int)$id]);
            }

            $this->guardarCargoYRoles((int)$id, $rol);
            flash('exito', 'Usuario actualizado con éxito.');
        } catch (\Exception $e) {
            flash('error', 'Error al actualizar usuario: ' . $e->getMessage());
        }

        redirigir('ajustes/usuarios');
    }

    public function eliminarUsuario(string $id): void
    {
        Database::ejecutar('UPDATE usuarios SET activo = 0 WHERE id = ?', [(int)$id]);
        flash('exito', 'Usuario marcado como inactivo.');
        redirigir('ajustes/usuarios');
    }

    public function exportar(): void
    {
        $rol = $_GET['export_rol'] ?? 'todos';
        $contrato = $_GET['export_contrato'] ?? 'todos';
        $programa = $_GET['export_programa'] ?? 'todos';

        $sql = "SELECT u.nombre, u.email, u.rol, d.nombre AS depto, p.siglas AS programa, u.tipo_contratacion, u.activo 
                  FROM usuarios u 
                  LEFT JOIN departamentos d ON d.id = u.departamento_id
                  LEFT JOIN programas p ON p.id = u.programa_id";
        
        $conds = [];
        $params = [];
        
        if ($rol === 'coordinadores') {
            $conds[] = "u.rol IN ('admin', 'titular', 'operativo')";
        } elseif ($rol === 'profesores') {
            $conds[] = "u.rol = 'profesor'";
        } elseif ($rol === 'alumnos') {
            $conds[] = "u.rol = 'alumno'";
        }
        
        if ($contrato !== 'todos') {
            $conds[] = "u.tipo_contratacion = ?";
            $params[] = $contrato;
        }
        
        if ($programa !== 'todos') {
            $conds[] = "u.programa_id = ?";
            $params[] = (int)$programa;
        }
        
        if (!empty($conds)) {
            $sql .= " WHERE " . implode(" AND ", $conds);
        }
        
        $sql .= " ORDER BY u.nombre";
        $data = Database::todas($sql, $params);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=usuarios_plataforma_' . date('Ymd_His') . '.csv');
        
        // Output BOM para compatibilidad con UTF-8 en Excel
        echo "\xEF\xBB\xBF";
        
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Nombre', 'Correo', 'Rol', 'Departamento/Coordinación', 'Programa/Carrera', 'Contratación', 'Estado']);
        
        foreach ($data as $r) {
            fputcsv($out, [
                $r['nombre'],
                $r['email'],
                strtoupper($r['rol']),
                $r['depto'] ?: '—',
                $r['programa'] ?: '—',
                $r['tipo_contratacion'] ?: '—',
                $r['activo'] ? 'Activo' : 'Inactivo'
            ]);
        }
        fclose($out);
    }
}
