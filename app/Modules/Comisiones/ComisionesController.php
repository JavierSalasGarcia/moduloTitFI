<?php
declare(strict_types=1);

namespace App\Modules\Comisiones;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * Comisiones de seguimiento (etapa de trazabilidad).
 *
 * Detonadores: a) una reunión convocada (origen 'reunion'),
 * b) una petición del foro que alcanzó el umbral de likes.
 * Las crean Dirección, Subdirecciones o el titular competente.
 * Los profesores participan como voluntarios (se postulan) o por
 * invitación que deben CONFIRMAR. La bitácora admite archivos
 * (guardados fuera del webroot) para auditar las acciones.
 */
final class ComisionesController extends Controller
{
    public function index(): void
    {
        $comisiones = Database::todas(
            "SELECT c.*, d.nombre AS depto, u.nombre AS creador,
                    (SELECT COUNT(*) FROM comision_miembros m WHERE m.comision_id = c.id AND m.estado = 'aceptado') AS miembros,
                    (SELECT m2.estado FROM comision_miembros m2 WHERE m2.comision_id = c.id AND m2.usuario_id = ?) AS mi_estado
               FROM comisiones c
               JOIN departamentos d ON d.id = c.depto_responsable_id
               JOIN usuarios u ON u.id = c.creador_id
              ORDER BY FIELD(c.estado,'formacion','en_seguimiento','concluida'), c.creado_en DESC",
            [Auth::id()]
        );

        $this->vista('index', [
            'titulo'     => 'Comisiones de seguimiento',
            'comisiones' => $comisiones,
            'puedeCrear' => Auth::tieneRol('admin', 'titular'),
        ]);
    }

    public function nueva(): void
    {
        $this->exigirAutoridad();
        $publicacion = null;
        if (!empty($_GET['publicacion'])) {
            $publicacion = Database::una(
                "SELECT * FROM foro_publicaciones WHERE id = ? AND estado = 'en_trazabilidad'",
                [(int) $_GET['publicacion']]);
        }
        $this->vista('nueva', [
            'titulo'      => 'Formar comisión',
            'publicacion' => $publicacion,
            'profesores'  => $this->profesores(),
            'deptos'      => Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre'),
        ]);
    }

    public function crear(): void
    {
        $this->exigirAutoridad();
        $titulo = $this->post('titulo');
        $desc   = $this->post('descripcion');
        $depto  = (int) $this->post('depto');
        $publicacionId = (int) $this->post('publicacion') ?: null;

        if ($titulo === '' || $desc === '' || !$depto) {
            flash('error', 'Completa título, descripción y departamento responsable.');
            redirigir('comisiones/nueva');
        }

        $id = Database::ejecutar(
            'INSERT INTO comisiones (publicacion_id, origen, titulo, descripcion, creador_id, depto_responsable_id)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$publicacionId, $publicacionId ? 'peticion' : 'reunion', $titulo, $desc, Auth::id(), $depto]
        );

        foreach ((array) ($_POST['profesores'] ?? []) as $pid) {
            Database::ejecutar(
                "INSERT IGNORE INTO comision_miembros (comision_id, usuario_id, estado) VALUES (?, ?, 'invitado')",
                [$id, (int) $pid]);
        }
        if ($publicacionId) {
            Database::ejecutar(
                "UPDATE foro_publicaciones SET estado = 'atendida' WHERE id = ?", [$publicacionId]);
        }
        flash('exito', 'Comisión creada. Los profesores invitados deberán confirmar su participación.');
        redirigir('comisiones/' . $id);
    }

    public function detalle(string $id): void
    {
        $c = $this->obtener((int) $id);
        $miembros = Database::todas(
            'SELECT m.*, u.nombre, d.nombre AS depto
               FROM comision_miembros m
               JOIN usuarios u ON u.id = m.usuario_id
               JOIN departamentos d ON d.id = u.departamento_id
              WHERE m.comision_id = ? ORDER BY m.estado, u.nombre', [$c['id']]);
        $bitacora = Database::todas(
            'SELECT b.*, u.nombre AS autor FROM comision_bitacora b
               JOIN usuarios u ON u.id = b.usuario_id
              WHERE b.comision_id = ? ORDER BY b.creado_en DESC, b.id DESC', [$c['id']]);

        $miEstado = Database::valor(
            'SELECT estado FROM comision_miembros WHERE comision_id = ? AND usuario_id = ?',
            [$c['id'], Auth::id()]);

        $this->vista('detalle', [
            'titulo'       => 'Comisión',
            'c'            => $c,
            'miembros'     => $miembros,
            'bitacora'     => $bitacora,
            'miEstado'     => $miEstado ?: null,
            'esGestor'     => $this->esGestor($c),
            'compromiso'   => json_decode((string) Database::valor('SELECT valor FROM ajustes WHERE clave = ?', ['comision_plan_' . $c['id']]), true) ?: [],
            'puedeBitacora'=> $this->esGestor($c) || $miEstado === 'aceptado',
            'profesores'   => $this->esGestor($c) ? $this->profesores($c['id']) : [],
        ]);
    }

    /** El profesor invitado confirma (acepta) o declina la asignación. */
    public function responder(string $id): void
    {
        $c = $this->obtener((int) $id);
        $respuesta = $this->post('respuesta') === 'aceptar' ? 'aceptado' : 'rechazado';
        $filas = Database::pdo()->prepare(
            "UPDATE comision_miembros SET estado = ?, respondido_en = NOW()
              WHERE comision_id = ? AND usuario_id = ? AND estado = 'invitado'");
        $filas->execute([$respuesta, $c['id'], Auth::id()]);
        flash($respuesta === 'aceptado' ? 'exito' : 'alerta',
            $respuesta === 'aceptado'
                ? 'Confirmaste tu participación: ya puedes registrar avances en la bitácora.'
                : 'Declinaste la asignación.');
        redirigir('comisiones/' . $c['id']);
    }

    /** Un profesor se postula como voluntario. */
    public function postular(string $id): void
    {
        $c = $this->obtener((int) $id);
        if (!Auth::tieneRol('profesor') || $c['estado'] === 'concluida') {
            flash('error', 'Solo profesores pueden postularse a comisiones activas.');
            redirigir('comisiones/' . $c['id']);
        }
        Database::ejecutar(
            "INSERT IGNORE INTO comision_miembros (comision_id, usuario_id, estado, es_voluntario, respondido_en)
             VALUES (?, ?, 'aceptado', 1, NOW())",
            [$c['id'], Auth::id()]);
        flash('exito', 'Te registraste como voluntario de la comisión.');
        redirigir('comisiones/' . $c['id']);
    }

    public function invitar(string $id): void
    {
        $c = $this->obtener((int) $id);
        if (!$this->esGestor($c)) {
            flash('error', 'Solo quien gestiona la comisión puede invitar profesores.');
            redirigir('comisiones/' . $c['id']);
        }
        $profesor = Database::una(
            "SELECT id FROM usuarios WHERE id = ? AND rol = 'profesor' AND activo = 1",
            [(int) $this->post('profesor')]);
        if ($profesor) {
            Database::ejecutar(
                "INSERT IGNORE INTO comision_miembros (comision_id, usuario_id, estado) VALUES (?, ?, 'invitado')",
                [$c['id'], $profesor['id']]);
            flash('exito', 'Invitación enviada; el profesor deberá confirmarla.');
        }
        redirigir('comisiones/' . $c['id']);
    }

    /** Entrada de bitácora con archivo opcional (auditoría). */
    public function agregarBitacora(string $id): void
    {
        $c = $this->obtener((int) $id);
        $miEstado = Database::valor(
            'SELECT estado FROM comision_miembros WHERE comision_id = ? AND usuario_id = ?',
            [$c['id'], Auth::id()]);
        if (!($this->esGestor($c) || $miEstado === 'aceptado')) {
            flash('error', 'Solo los miembros confirmados registran avances.');
            redirigir('comisiones/' . $c['id']);
        }
        $contenido = $this->post('contenido');
        if ($contenido === '') {
            flash('error', 'Describe la acción realizada.');
            redirigir('comisiones/' . $c['id']);
        }
        try {
            $archivo = guardar_archivo('archivo', 'comisiones/' . $c['id']);
        } catch (\InvalidArgumentException $ex) {
            flash('error', $ex->getMessage());
            redirigir('comisiones/' . $c['id']);
        }
        Database::ejecutar(
            'INSERT INTO comision_bitacora (comision_id, usuario_id, contenido, archivo) VALUES (?, ?, ?, ?)',
            [$c['id'], Auth::id(), $contenido, $archivo]);
        flash('exito', 'Avance registrado en la bitácora.');
        redirigir('comisiones/' . $c['id']);
    }

    /** Descarga controlada del archivo de una entrada (usuarios con sesión). */
    public function archivo(string $id): void
    {
        $b = Database::una('SELECT archivo FROM comision_bitacora WHERE id = ?', [(int) $id]);
        if (!$b || !$b['archivo']) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }
        descargar_archivo($b['archivo']);
    }

    public function cambiarEstado(string $id): void
    {
        $c = $this->obtener((int) $id);
        $estado = $this->post('estado');
        if (!$this->esGestor($c) || !in_array($estado, ['formacion', 'en_seguimiento', 'concluida'], true)) {
            flash('error', 'Solo quien gestiona la comisión cambia su estado.');
        } else {
            Database::ejecutar('UPDATE comisiones SET estado = ? WHERE id = ?', [$estado, $c['id']]);
            flash('exito', 'Estado de la comisión actualizado.');
        }
        redirigir('comisiones/' . $c['id']);
    }

    public function compromiso(string $id): void
    {
        $c = $this->obtener((int) $id);
        if (!$this->esGestor($c)) {
            http_response_code(403);
            exit('Solo quien gestiona la comisión puede actualizar el compromiso.');
        }
        $texto = $this->post('compromiso');
        $fecha = $this->post('fecha');
        $valida = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($texto === '' || mb_strlen($texto) > 500 || !$valida || $valida->format('Y-m-d') !== $fecha) {
            flash('error', 'Escribe el próximo compromiso (hasta 500 caracteres) y una fecha válida.');
            redirigir('comisiones/' . $c['id']);
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::ejecutar('INSERT INTO ajustes (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
                ['comision_plan_' . $c['id'], json_encode(['texto'=>$texto, 'fecha'=>$fecha], JSON_UNESCAPED_UNICODE), 'Próximo compromiso de la comisión']);
            Database::ejecutar('INSERT INTO comision_bitacora (comision_id, usuario_id, contenido) VALUES (?, ?, ?)',
                [$c['id'], Auth::id(), 'Próximo compromiso (' . $fecha . '): ' . $texto]);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
        flash('exito', 'Próximo compromiso actualizado y registrado en la bitácora.');
        redirigir('comisiones/' . $c['id']);
    }

    // ── Auxiliares ──────────────────────────────────────────────────

    private function exigirAutoridad(): void
    {
        if (!Auth::tieneRol('admin', 'titular')) {
            flash('error', 'Solo la Dirección, Subdirecciones o titulares competentes forman comisiones.');
            redirigir('comisiones');
        }
    }

    private function esGestor(array $c): bool
    {
        return Auth::tieneRol('admin')
            || (int) $c['creador_id'] === Auth::id()
            || (Auth::tieneRol('titular') && (int) $c['depto_responsable_id'] === Auth::departamentoId());
    }

    /** Profesores disponibles (excluye a los ya miembros de la comisión). */
    private function profesores(int $comisionId = 0): array
    {
        return Database::todas(
            "SELECT u.id, u.nombre, d.nombre AS depto
               FROM usuarios u JOIN departamentos d ON d.id = u.departamento_id
              WHERE u.rol = 'profesor' AND u.activo = 1
                AND u.id NOT IN (SELECT usuario_id FROM comision_miembros WHERE comision_id = ?)
              ORDER BY u.nombre",
            [$comisionId]);
    }

    private function obtener(int $id): array
    {
        $c = Database::una(
            'SELECT c.*, d.nombre AS depto, u.nombre AS creador, p.titulo AS peticion_titulo
               FROM comisiones c
               JOIN departamentos d ON d.id = c.depto_responsable_id
               JOIN usuarios u ON u.id = c.creador_id
               LEFT JOIN foro_publicaciones p ON p.id = c.publicacion_id
              WHERE c.id = ?', [$id]);
        if (!$c) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No encontrada']);
            exit;
        }
        return $c;
    }
}
