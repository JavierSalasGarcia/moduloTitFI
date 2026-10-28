<?php
declare(strict_types=1);

namespace App\Modules\Solicitudes;

use App\Core\Auth;
use App\Core\Database;

/** Solicitudes entre áreas dirigidas a tu departamento que siguen sin resolverse (para la bandeja de Notificaciones). */
final class PendientesSolicitudes
{
    public static function de(): array
    {
        if (!in_array(Auth::usuario()['rol'] ?? '', ['admin', 'titular', 'operativo'], true) || !Auth::departamentoId()) {
            return [];
        }
        $n = (int) Database::valor("SELECT COUNT(*) FROM solicitudes WHERE depto_destino_id = ? AND estado IN ('pendiente','en_proceso')", [Auth::departamentoId()]);
        return $n > 0 ? [['tipo' => 'Solicitud', 'texto' => $n . ' solicitud(es) entre áreas por atender', 'enlace' => 'solicitudes', 'fecha' => null]] : [];
    }
}
