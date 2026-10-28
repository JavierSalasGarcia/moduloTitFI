<?php
declare(strict_types=1);

namespace App\Modules\Encuestas;

use App\Core\Auth;
use App\Core\Database;

/** Encuestas abiertas que la persona aún no responde (para la bandeja de Notificaciones). */
final class PendientesEncuestas
{
    public static function de(): array
    {
        $n = (int) Database::valor("SELECT COUNT(*) FROM encuestas e WHERE e.estado = 'abierta' AND NOT EXISTS (SELECT 1 FROM encuesta_participantes p WHERE p.encuesta_id = e.id AND p.usuario_id = ?)", [Auth::id()]);
        return $n > 0 ? [['tipo' => 'Encuesta', 'texto' => $n . ' encuesta(s) sin responder', 'enlace' => 'encuestas?vista=pendientes', 'fecha' => null]] : [];
    }
}
