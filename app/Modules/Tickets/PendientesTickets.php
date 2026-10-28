<?php
declare(strict_types=1);

namespace App\Modules\Tickets;

use App\Core\Auth;
use App\Core\Database;

/** Tickets que atender (personal) o que siguen abiertos (alumnado y profesorado), para la bandeja de Notificaciones. */
final class PendientesTickets
{
    public static function de(): array
    {
        $u = Auth::usuario();
        $r = [];
        if (in_array($u['rol'], ['alumno', 'profesor'], true)) {
            $n = (int) Database::valor("SELECT COUNT(*) FROM tickets WHERE usuario_id = ? AND estado IN ('abierto','en_atencion')", [$u['id']]);
            if ($n > 0) {
                $r[] = ['tipo' => 'Ticket', 'texto' => $n . ' solicitud(es) tuya(s) siguen en seguimiento', 'enlace' => 'tickets', 'fecha' => null];
            }
            return $r;
        }
        $n = (int) Database::valor("SELECT COUNT(*) FROM tickets WHERE depto_destino_id = ? AND estado IN ('abierto','en_atencion')", [Auth::departamentoId()]);
        if ($n > 0) {
            $r[] = ['tipo' => 'Ticket', 'texto' => $n . ' ticket(s) de tu área por atender', 'enlace' => 'tickets?bandeja=recibidos', 'fecha' => null];
        }
        return $r;
    }
}
