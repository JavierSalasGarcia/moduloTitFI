<?php
declare(strict_types=1);

namespace App\Modules\Tickets;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * Tickets de atención canalizados por categoría al departamento que
 * atiende el asunto, con estados y bitácora de seguimiento.
 * Bandejas separadas (los que genero / los que atiendo) con sub-vistas
 * de activos y cerrados, clasificación PERSONAL con la matriz de
 * Eisenhower, rechazo con motivo y redirección entre departamentos.
 */
final class TicketsController extends Controller
{
    private const ESTADOS = ['abierto', 'en_atencion', 'resuelto', 'cerrado', 'rechazado'];
    /** Estados asignables desde el select de atención (el rechazo va por su propio formulario). */
    private const ESTADOS_CAMBIABLES = ['abierto', 'en_atencion', 'resuelto', 'cerrado'];
    private const ACTIVOS    = ['abierto', 'en_atencion'];
    private const CUADRANTES = ['hacer', 'planificar', 'delegar', 'eliminar'];

    public function index(): void
    {
        $u = Auth::usuario();
        $esStaff = Auth::tieneRol('admin', 'titular', 'operativo');
        $bandeja = ($_GET['bandeja'] ?? '') === 'recibidos' && $esStaff ? 'recibidos' : 'mios';
        $vista   = in_array($_GET['vista'] ?? '', ['cerrados', 'matriz'], true) ? $_GET['vista'] : 'activos';

        if ($vista === 'matriz') {
            $this->matriz($esStaff);
            return;
        }

        $filtroEstado = $vista === 'activos'
            ? "t.estado IN ('abierto','en_atencion')"
            : "t.estado IN ('resuelto','cerrado','rechazado')";

        if ($bandeja === 'recibidos') {
            $tickets = Database::todas(
                "SELECT t.*, COALESCE(c.nombre, 'Canal directo') AS categoria, u.nombre AS solicitante,
                        tc.cuadrante AS mi_cuadrante
                   FROM tickets t
                   LEFT JOIN ticket_categorias c ON c.id = t.categoria_id
                   JOIN usuarios u ON u.id = t.usuario_id
                   LEFT JOIN ticket_clasificaciones tc ON tc.ticket_id = t.id AND tc.usuario_id = ?
                  WHERE t.depto_destino_id = ? AND {$filtroEstado}
                  ORDER BY FIELD(t.estado,'abierto','en_atencion','resuelto','cerrado','rechazado'), t.actualizado_en DESC",
                [Auth::id(), Auth::departamentoId()]
            );
        } else {
            $tickets = Database::todas(
                "SELECT t.*, COALESCE(c.nombre, 'Canal directo') AS categoria, d.nombre AS destino,
                        tc.cuadrante AS mi_cuadrante
                   FROM tickets t
                   LEFT JOIN ticket_categorias c ON c.id = t.categoria_id
                   JOIN departamentos d ON d.id = t.depto_destino_id
                   LEFT JOIN ticket_clasificaciones tc ON tc.ticket_id = t.id AND tc.usuario_id = ?
                  WHERE t.usuario_id = ? AND {$filtroEstado}
                  ORDER BY t.actualizado_en DESC",
                [Auth::id(), $u['id']]
            );
        }

        $this->vista('index', [
            'titulo'  => 'Tickets',
            'bandeja' => $bandeja,
            'vistaFiltro' => $vista,
            'esStaff' => $esStaff,
            'tickets' => $tickets,
        ]);
    }

    /** Matriz de Eisenhower PERSONAL: mis tickets activos (creados + recibidos). */
    private function matriz(bool $esStaff): void
    {
        $condicion = $esStaff ? '(t.usuario_id = ? OR t.depto_destino_id = ?)' : 't.usuario_id = ?';

        $tickets = Database::todas(
            "SELECT t.*, COALESCE(c.nombre, 'Canal directo') AS categoria, d.nombre AS destino,
                    u.nombre AS solicitante, tc.cuadrante AS mi_cuadrante,
                    (t.usuario_id = ?) AS es_mio
               FROM tickets t
               LEFT JOIN ticket_categorias c ON c.id = t.categoria_id
               JOIN departamentos d ON d.id = t.depto_destino_id
               JOIN usuarios u ON u.id = t.usuario_id
               LEFT JOIN ticket_clasificaciones tc ON tc.ticket_id = t.id AND tc.usuario_id = ?
              WHERE {$condicion} AND t.estado IN ('abierto','en_atencion')
              ORDER BY t.actualizado_en DESC",
            array_merge([Auth::id(), Auth::id()], $esStaff
                ? [Auth::id(), Auth::departamentoId()] : [Auth::id()])
        );

        $porCuadrante = ['' => [], 'hacer' => [], 'planificar' => [], 'delegar' => [], 'eliminar' => []];
        foreach ($tickets as $t) {
            $porCuadrante[$t['mi_cuadrante'] ?? ''][] = $t;
        }

        $this->vista('matriz', [
            'titulo'       => 'Tickets · Matriz de Eisenhower',
            'porCuadrante' => $porCuadrante,
            'cuadrantes'   => self::CUADRANTES,
            'esStaff'      => $esStaff,
        ]);
    }

    public function nuevo(): void
    {
        $deptoDirecto = null;
        if (!empty($_GET['depto'])) {
            $deptoDirecto = Database::una(
                'SELECT id, nombre FROM departamentos WHERE id = ?', [(int) $_GET['depto']]);
        }
        
        $carpetaVinculada = null;
        if (!empty($_GET['teams_carpeta_id'])) {
            $carpetaVinculada = Database::una(
                'SELECT id, ruta_relativa FROM teams_carpetas WHERE id = ?', [(int) $_GET['teams_carpeta_id']]);
        }

        $this->vista('nuevo', [
            'titulo'        => 'Nuevo ticket',
            'departamentos' => Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre'),
            'deptoDirecto'  => $deptoDirecto,
            'carpetaVinculada' => $carpetaVinculada,
            'usuarios'      => Database::todas("SELECT id, nombre, email, rol FROM usuarios WHERE activo = 1 ORDER BY nombre"),
            'ref'           => trim((string) ($_GET['ref'] ?? '')),
        ]);
    }

    public function crear(): void
    {
        $u = Auth::usuario();
        $asunto = $this->post('asunto');
        $desc   = $this->post('descripcion');
        $prio   = in_array($this->post('prioridad'), ['baja', 'media', 'alta'], true) ? $this->post('prioridad') : 'media';
        $teamsCarpetaId = !empty($_POST['teams_carpeta_id']) ? (int)$_POST['teams_carpeta_id'] : null;
        
        $paraType = $_POST['para_type'] ?? [];
        $paraId = $_POST['para_id'] ?? [];
        $ccType = $_POST['cc_type'] ?? [];
        $ccId = $_POST['cc_id'] ?? [];
        try {
            foreach (\App\Core\RecipientGroups::recipients('grupos_para') as $person) { $paraType[]='usuario'; $paraId[]=$person; }
            foreach (\App\Core\RecipientGroups::recipients('grupos_cc') as $person) { $ccType[]='usuario'; $ccId[]=$person; }
            $seen=[]; $clean=static function(array $types,array $ids) use (&$seen): array { $t=[];$values=[];foreach($ids as $i=>$id){$type=$types[$i]??'';if(!in_array($type,['usuario','departamento'],true)||!is_scalar($id)||!ctype_digit((string)$id)||(int)$id<1)throw new \InvalidArgumentException('Revisa los destinatarios seleccionados.');$key=$type.':'.(int)$id;if(isset($seen[$key]))continue;$seen[$key]=true;$t[]=$type;$values[]=(int)$id;}return [$t,$values];};
            [$paraType,$paraId]=$clean((array)$paraType,(array)$paraId);[$ccType,$ccId]=$clean((array)$ccType,(array)$ccId);
        } catch (\InvalidArgumentException $ex) { flash('error',$ex->getMessage()); redirigir('tickets/nuevo'); }

        if (empty($paraId)) {
            flash('error', 'Debes elegir al menos un destinatario en el campo "Para".');
            redirigir('tickets/nuevo');
        }

        // Resolver depto_destino_id a partir del primer destinatario "Para"
        $destino = 0;
        foreach($paraId as $idx=>$person) {
            $destino=$paraType[$idx]==='departamento'?(int)Database::valor('SELECT id FROM departamentos WHERE id=?',[$person]):(int)Database::valor('SELECT departamento_id FROM usuarios WHERE id=? AND activo=1',[$person]);
            if($destino)break;
        }

        if (!$destino || $asunto === '' || $desc === '') {
            flash('error', 'No se pudo resolver el departamento de destino. Verifica los destinatarios.');
            redirigir('tickets/nuevo');
        }

        $id = Database::ejecutar(
            "INSERT INTO tickets (folio, categoria_id, usuario_id, depto_destino_id, asunto, descripcion, prioridad, teams_carpeta_id)
             VALUES ('', ?, ?, ?, ?, ?, ?, ?)",
            [null, $u['id'], $destino, $asunto, $desc, $prio, $teamsCarpetaId]
        );
        $folio = sprintf('TK-%s-%04d', date('Y'), $id);
        Database::ejecutar('UPDATE tickets SET folio = ? WHERE id = ?', [$folio, $id]);

        // Insertar destinatarios "Para"
        foreach ($paraId as $idx => $pId) {
            Database::ejecutar(
                "INSERT INTO ticket_destinatarios (ticket_id, tipo, destinatario_type, destinatario_id) VALUES (?, 'para', ?, ?)",
                [$id, $paraType[$idx], (int)$pId]
            );
        }

        // Insertar destinatarios "CC"
        if (!empty($ccId)) {
            foreach ($ccId as $idx => $cId) {
                Database::ejecutar(
                    "INSERT INTO ticket_destinatarios (ticket_id, tipo, destinatario_type, destinatario_id) VALUES (?, 'cc', ?, ?)",
                    [$id, $ccType[$idx], (int)$cId]
                );
            }
        }

        // Subir y asociar múltiples archivos adjuntos
        if (isset($_FILES['archivos']) && is_array($_FILES['archivos']['name'])) {
            try {
                $guardados = guardar_archivos('archivos', 'tickets_adjuntos');
                foreach ($guardados as $g) {
                    if (!empty($g['ruta'])) {
                        Database::ejecutar(
                            "INSERT INTO ticket_archivos (ticket_id, usuario_id, nombre_original, ruta) VALUES (?, ?, ?, ?)",
                            [$id, $u['id'], $g['nombre_original'], $g['ruta']]
                        );
                    }
                }
            } catch (\Throwable $e) {
                flash('error', 'Error al subir archivos adjuntos: ' . $e->getMessage());
            }
        }

        flash('exito', "Solicitud {$folio} enviada. En esta pantalla puedes ver el área responsable y seguir su atención.");
        redirigir('tickets/' . $id);
    }

    public function detalle(string $id): void
    {
        $t = $this->obtenerPropio((int) $id);
        $eventos = Database::todas(
            'SELECT ev.*, u.nombre AS autor, d.nombre AS depto_autor
               FROM ticket_eventos ev
               JOIN usuarios u ON u.id = ev.usuario_id
               JOIN departamentos d ON d.id = u.departamento_id
              WHERE ev.ticket_id = ? ORDER BY ev.creado_en, ev.id',
            [$t['id']]
        );

        $carpeta = null;
        if (!empty($t['teams_carpeta_id'])) {
            $carpeta = Database::una(
                "SELECT c.*, s.url_base 
                   FROM teams_carpetas c
                   JOIN teams_sitios s ON s.id = c.sitio_id
                  WHERE c.id = ?",
                [$t['teams_carpeta_id']]
            );
        }

        $destinatarios = Database::todas(
            "SELECT td.*, 
                    CASE 
                        WHEN td.destinatario_type = 'usuario' THEN u.nombre
                        WHEN td.destinatario_type = 'departamento' THEN d.nombre
                    END AS destinatario_nombre 
               FROM ticket_destinatarios td
               LEFT JOIN usuarios u ON u.id = td.destinatario_id AND td.destinatario_type = 'usuario'
               LEFT JOIN departamentos d ON d.id = td.destinatario_id AND td.destinatario_type = 'departamento'
              WHERE td.ticket_id = ? ORDER BY td.tipo, td.id",
            [$t['id']]
        );

        $adjuntos = Database::todas(
            "SELECT * FROM ticket_archivos WHERE ticket_id = ?",
            [$t['id']]
        );

        $this->vista('detalle', [
            'titulo'    => $t['folio'],
            'ticket'    => $t,
            'eventos'   => $eventos,
            'carpeta'   => $carpeta,
            'destinatarios' => $destinatarios,
            'adjuntos'  => $adjuntos,
            'esDestino' => (int) $t['depto_destino_id'] === Auth::departamentoId()
                           && Auth::tieneRol('admin', 'titular', 'operativo'),
            'estados'   => self::ESTADOS_CAMBIABLES,
            'miCuadrante' => (string) (Database::valor(
                'SELECT cuadrante FROM ticket_clasificaciones WHERE ticket_id = ? AND usuario_id = ?',
                [$t['id'], Auth::id()]) ?: ''),
            'cuadrantes'  => self::CUADRANTES,
            'departamentos' => Database::todas(
                'SELECT id, nombre FROM departamentos WHERE id <> ? ORDER BY nombre',
                [$t['depto_destino_id']]),
        ]);
    }

    public function comentar(string $id): void
    {
        $t = $this->obtenerPropio((int) $id);
        $texto = $this->post('contenido');
        if ($texto !== '') {
            Database::ejecutar(
                "INSERT INTO ticket_eventos (ticket_id, usuario_id, tipo, contenido) VALUES (?, ?, 'comentario', ?)",
                [$t['id'], Auth::id(), $texto]
            );
            Database::ejecutar('UPDATE tickets SET actualizado_en = NOW() WHERE id = ?', [$t['id']]);
        }
        redirigir('tickets/' . $t['id']);
    }

    public function cambiarEstado(string $id): void
    {
        $t = $this->obtenerPropio((int) $id);
        $estado = $this->post('estado');
        $nota   = $this->post('nota');

        if (!$this->atiendeTicket($t) || !in_array($estado, self::ESTADOS_CAMBIABLES, true)) {
            flash('error', 'Solo el departamento que atiende el ticket puede cambiar su estado.');
            redirigir('tickets/' . $t['id']);
        }

        Database::ejecutar('UPDATE tickets SET estado = ? WHERE id = ?', [$estado, $t['id']]);
        Database::ejecutar(
            "INSERT INTO ticket_eventos (ticket_id, usuario_id, tipo, contenido, estado_nuevo) VALUES (?, ?, 'cambio_estado', ?, ?)",
            [$t['id'], Auth::id(), $nota !== '' ? $nota : 'Cambio de estado.', $estado]
        );
        flash('exito', 'Estado del ticket actualizado.');
        redirigir('tickets/' . $t['id']);
    }

    /** Clasificación PERSONAL en la matriz de Eisenhower (no afecta a otros usuarios). */
    public function clasificar(string $id): void
    {
        $t = $this->obtenerPropio((int) $id);
        $cuadrante = $this->post('cuadrante');

        if ($cuadrante === '') {
            Database::ejecutar(
                'DELETE FROM ticket_clasificaciones WHERE ticket_id = ? AND usuario_id = ?',
                [$t['id'], Auth::id()]);
        } elseif (in_array($cuadrante, self::CUADRANTES, true)) {
            Database::ejecutar(
                'INSERT INTO ticket_clasificaciones (ticket_id, usuario_id, cuadrante) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE cuadrante = VALUES(cuadrante)',
                [$t['id'], Auth::id(), $cuadrante]);
        }
        redirigir($this->post('volver') === 'detalle' ? 'tickets/' . $t['id'] : 'tickets?vista=matriz');
    }

    /** Rechazo con motivo obligatorio: el asunto no procede en este departamento. */
    public function rechazar(string $id): void
    {
        $t = $this->obtenerPropio((int) $id);
        $motivo = $this->post('motivo');

        if (!$this->atiendeTicket($t)) {
            flash('error', 'Solo el departamento que atiende el ticket puede rechazarlo.');
            redirigir('tickets/' . $t['id']);
        }
        if ($motivo === '') {
            flash('error', 'Indica el motivo del rechazo.');
            redirigir('tickets/' . $t['id']);
        }

        Database::ejecutar("UPDATE tickets SET estado = 'rechazado' WHERE id = ?", [$t['id']]);
        Database::ejecutar(
            "INSERT INTO ticket_eventos (ticket_id, usuario_id, tipo, contenido, estado_nuevo) VALUES (?, ?, 'rechazo', ?, 'rechazado')",
            [$t['id'], Auth::id(), $motivo]
        );
        flash('exito', 'Ticket rechazado; el solicitante puede ver el motivo.');
        redirigir('tickets/' . $t['id']);
    }

    /** Redirige el ticket al departamento que sí atiende el asunto. */
    public function redirigir(string $id): void
    {
        $t = $this->obtenerPropio((int) $id);
        $nuevoDepto = (int) $this->post('depto_id');
        $motivo = $this->post('motivo');

        if (!$this->atiendeTicket($t)) {
            flash('error', 'Solo el departamento que atiende el ticket puede redirigirlo.');
            redirigir('tickets/' . $t['id']);
        }
        $destino = Database::una('SELECT id, nombre FROM departamentos WHERE id = ?', [$nuevoDepto]);
        if (!$destino || $nuevoDepto === (int) $t['depto_destino_id']) {
            flash('error', 'Elige el departamento al que se canaliza el ticket.');
            redirigir('tickets/' . $t['id']);
        }

        Database::ejecutar(
            "UPDATE tickets SET depto_destino_id = ?, estado = 'abierto' WHERE id = ?",
            [$nuevoDepto, $t['id']]);
        Database::ejecutar(
            "INSERT INTO ticket_eventos (ticket_id, usuario_id, tipo, contenido, estado_nuevo) VALUES (?, ?, 'redireccion', ?, 'abierto')",
            [$t['id'], Auth::id(), 'Redirigido de ' . $t['destino'] . ' a ' . $destino['nombre'] . '.'
                . ($motivo !== '' ? ' Motivo: ' . $motivo : '')]
        );
        flash('exito', 'Ticket redirigido a ' . $destino['nombre'] . '.');
        redirigir('tickets/' . $t['id']);
    }

    /** ¿El usuario es staff del departamento que atiende este ticket? */
    private function atiendeTicket(array $t): bool
    {
        return (int) $t['depto_destino_id'] === Auth::departamentoId()
               && Auth::tieneRol('admin', 'titular', 'operativo');
    }

    /** Departamento destino: fijo por categoría o coordinación de la carrera. */
    private function resolverDestino(?array $categoria, array $u): int
    {
        if (!$categoria) {
            return 0;
        }
        if ($categoria['depto_destino_id'] !== null) {
            return (int) $categoria['depto_destino_id'];
        }
        if ($u['programa_id']) {
            return (int) Database::valor('SELECT depto_id FROM programas WHERE id = ?', [$u['programa_id']]);
        }
        return 0;
    }

    /** Solo el solicitante o el departamento destino ven el ticket. */
    private function obtenerPropio(int $id): array
    {
        $t = Database::una(
            "SELECT t.*, COALESCE(c.nombre, 'Canal directo') AS categoria, u.nombre AS solicitante, d.nombre AS destino
               FROM tickets t
               LEFT JOIN ticket_categorias c ON c.id = t.categoria_id
               JOIN usuarios u ON u.id = t.usuario_id
               JOIN departamentos d ON d.id = t.depto_destino_id
              WHERE t.id = ?",
            [$id]
        );
        $esDestino = $t && (int) $t['depto_destino_id'] === Auth::departamentoId()
                     && Auth::tieneRol('admin', 'titular', 'operativo');
        if (!$t || ((int) $t['usuario_id'] !== Auth::id() && !$esDestino && Auth::rol() !== 'admin')) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No encontrado']);
            exit;
        }
        return $t;
    }

    public function descargarArchivo(string $id): never
    {
        $u = Auth::usuario();
        $archivoId = (int) $id;

        $archivo = Database::una(
            "SELECT ta.*, t.usuario_id AS creador_ticket_id, t.depto_destino_id 
               FROM ticket_archivos ta
               JOIN tickets t ON t.id = ta.ticket_id
              WHERE ta.id = ?",
            [$archivoId]
        );

        if (!$archivo) {
            http_response_code(404);
            echo "Archivo no encontrado.";
            exit;
        }

        // Validar acceso: creador del ticket, destinatarios específicos, o miembros del departamento destino
        $esDestinatario = Database::valor(
            "SELECT COUNT(*) FROM ticket_destinatarios WHERE ticket_id = ? AND usuario_id = ?",
            [$archivo['ticket_id'], $u['id']]
        ) > 0;

        $esStaffDepto = (int)$archivo['depto_destino_id'] === Auth::departamentoId()
                        && Auth::tieneRol('admin', 'titular', 'operativo');

        if ((int)$archivo['creador_ticket_id'] !== (int)$u['id'] && !$esDestinatario && !$esStaffDepto && Auth::rol() !== 'admin') {
            http_response_code(403);
            echo "Acceso denegado.";
            exit;
        }

        descargar_archivo($archivo['ruta']);
    }
}
