<?php
declare(strict_types=1);

namespace App\Modules\Solicitudes;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Intercambio de información interdepartamental:
 * solicitudes con folio, prioridad, estado y bitácora, restringidas
 * a los canales autorizados en la tabla `relaciones`.
 */
final class SolicitudesController extends Controller
{
    private const ESTADOS = ['pendiente', 'en_proceso', 'resuelta', 'rechazada'];

    public function index(): void
    {
        $depto   = Auth::departamentoId();
        $bandeja = ($_GET['bandeja'] ?? 'recibidas') === 'enviadas' ? 'enviadas' : 'recibidas';
        $campo   = $bandeja === 'recibidas' ? 'depto_destino_id' : 'depto_origen_id';

        $solicitudes = Database::todas(
            "SELECT s.*, dorig.nombre AS origen, ddest.nombre AS destino, u.nombre AS solicitante
               FROM solicitudes s
               JOIN departamentos dorig ON dorig.id = s.depto_origen_id
               JOIN departamentos ddest ON ddest.id = s.depto_destino_id
               JOIN usuarios u ON u.id = s.usuario_id
              WHERE s.{$campo} = ?
              ORDER BY FIELD(s.estado,'pendiente','en_proceso','rechazada','resuelta'), s.actualizado_en DESC",
            [$depto]
        );

        $this->vista('index', [
            'titulo'      => 'Solicitudes',
            'bandeja'     => $bandeja,
            'solicitudes' => $solicitudes,
        ]);
    }

    public function nueva(): void
    {
        $canales = Database::todas(
            'SELECT d.id, d.nombre, r.descripcion
               FROM relaciones r JOIN departamentos d ON d.id = r.depto_destino_id
              WHERE r.depto_origen_id = ? AND r.activa = 1 ORDER BY d.nombre',
            [Auth::departamentoId()]
        );
        $this->vista('nueva', ['titulo' => 'Nueva solicitud', 'canales' => $canales]);
    }

    public function crear(): void
    {
        $origen  = Auth::departamentoId();
        $destino = (int) $this->post('destino');
        $asunto  = $this->post('asunto');
        $desc    = $this->post('descripcion');
        $prio    = in_array($this->post('prioridad'), ['baja', 'media', 'alta'], true) ? $this->post('prioridad') : 'media';

        // El destino debe ser un canal autorizado para el departamento del usuario
        $canal = Database::una(
            'SELECT 1 FROM relaciones WHERE depto_origen_id = ? AND depto_destino_id = ? AND activa = 1',
            [$origen, $destino]
        );
        if (!$canal || $asunto === '' || $desc === '') {
            flash('error', 'Verifica el destino (debe ser un canal autorizado), el asunto y la descripción.');
            redirigir('solicitudes/nueva');
        }

        $id = Database::ejecutar(
            "INSERT INTO solicitudes (folio, depto_origen_id, depto_destino_id, usuario_id, asunto, descripcion, prioridad)
             VALUES ('', ?, ?, ?, ?, ?, ?)",
            [$origen, $destino, Auth::usuario()['id'], $asunto, $desc, $prio]
        );
        $folio = sprintf('FI-%s-%04d', date('Y'), $id);
        Database::ejecutar('UPDATE solicitudes SET folio = ? WHERE id = ?', [$folio, $id]);

        flash('exito', "Solicitud {$folio} enviada correctamente.");
        redirigir('solicitudes/' . $id);
    }

    public function detalle(string $id): void
    {
        $s = $this->obtenerPropia((int) $id);
        $eventos = Database::todas(
            'SELECT ev.*, u.nombre AS autor, d.nombre AS depto_autor
               FROM solicitud_eventos ev
               JOIN usuarios u ON u.id = ev.usuario_id
               JOIN departamentos d ON d.id = u.departamento_id
              WHERE ev.solicitud_id = ? ORDER BY ev.creado_en, ev.id',
            [$s['id']]
        );

        $this->vista('detalle', [
            'titulo'      => $s['folio'],
            'solicitud'   => $s,
            'eventos'     => $eventos,
            'esDestino'   => (int) $s['depto_destino_id'] === Auth::departamentoId(),
            'estados'     => self::ESTADOS,
        ]);
    }

    public function comentar(string $id): void
    {
        $s = $this->obtenerPropia((int) $id);
        $texto = $this->post('contenido');
        if ($texto !== '') {
            Database::ejecutar(
                "INSERT INTO solicitud_eventos (solicitud_id, usuario_id, tipo, contenido) VALUES (?, ?, 'comentario', ?)",
                [$s['id'], Auth::usuario()['id'], $texto]
            );
            Database::ejecutar('UPDATE solicitudes SET actualizado_en = NOW() WHERE id = ?', [$s['id']]);
        }
        redirigir('solicitudes/' . $s['id']);
    }

    public function cambiarEstado(string $id): void
    {
        $s = $this->obtenerPropia((int) $id);
        $estado = $this->post('estado');
        $nota   = $this->post('nota');

        // Solo el departamento destino resuelve la solicitud
        if ((int) $s['depto_destino_id'] !== Auth::departamentoId() || !in_array($estado, self::ESTADOS, true)) {
            flash('error', 'No tienes permiso para cambiar el estado de esta solicitud.');
            redirigir('solicitudes/' . $s['id']);
        }

        Database::ejecutar('UPDATE solicitudes SET estado = ? WHERE id = ?', [$estado, $s['id']]);
        Database::ejecutar(
            "INSERT INTO solicitud_eventos (solicitud_id, usuario_id, tipo, contenido, estado_nuevo) VALUES (?, ?, 'cambio_estado', ?, ?)",
            [$s['id'], Auth::usuario()['id'], $nota !== '' ? $nota : 'Cambio de estado.', $estado]
        );
        flash('exito', 'Estado actualizado a «' . str_replace('_', ' ', $estado) . '».');
        redirigir('solicitudes/' . $s['id']);
    }

    /** Carga la solicitud validando que el depto del usuario participe en ella. */
    private function obtenerPropia(int $id): array
    {
        $depto = Auth::departamentoId();
        $s = Database::una(
            'SELECT s.*, dorig.nombre AS origen, ddest.nombre AS destino, u.nombre AS solicitante
               FROM solicitudes s
               JOIN departamentos dorig ON dorig.id = s.depto_origen_id
               JOIN departamentos ddest ON ddest.id = s.depto_destino_id
               JOIN usuarios u ON u.id = s.usuario_id
              WHERE s.id = ? AND (s.depto_origen_id = ? OR s.depto_destino_id = ?)',
            [$id, $depto, $depto]
        );
        if (!$s) {
            http_response_code(404);
            \App\Core\View::render('errores/404', ['titulo' => 'No encontrada']);
            exit;
        }
        return $s;
    }
}
