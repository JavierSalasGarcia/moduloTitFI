<?php
declare(strict_types=1);

namespace App\Modules\Firmas;

use App\Core\Auth;
use App\Core\Database;

/** Documentos que esperan la firma de la persona con sesión (para la bandeja de Notificaciones). */
final class PendientesFirmas
{
    public static function de(): array
    {
        $r = [];
        foreach (Database::todas(
            "SELECT s.id, s.titulo, s.actualizado_en, u.nombre AS creador
               FROM firma_firmantes f
               JOIN firma_solicitudes s ON s.id = f.solicitud_id
               JOIN usuarios u ON u.id = s.creador_id
              WHERE f.usuario_id = ? AND s.estado = 'en_ruta' AND f.estado = 'pendiente' AND (s.modo = 'paralela' OR f.orden = s.paso_actual)
              ORDER BY s.actualizado_en DESC LIMIT 50",
            [Auth::id()]
        ) as $s) {
            $r[] = ['tipo' => 'Firma', 'texto' => 'Firmar «' . $s['titulo'] . '» (envía ' . $s['creador'] . ')', 'enlace' => 'firmas/' . (int) $s['id'], 'fecha' => $s['actualizado_en']];
        }
        return $r;
    }
}
