<?php
declare(strict_types=1);

namespace App\Modules\Reuniones;

use App\Core\Auth;
use App\Modules\Reuniones\Services\VisorReunionesService;

/** Invitaciones a reunión que la persona aún no responde (para la bandeja de Notificaciones). */
final class PendientesReuniones
{
    public static function de(): array
    {
        $r = [];
        foreach ((new VisorReunionesService())->pendientes((int) Auth::id()) as $x) {
            $r[] = ['tipo' => 'Reunión', 'texto' => 'Indicar tu disponibilidad: «' . $x['asunto'] . '»' . ($x['organizador'] ? ' (convoca ' . $x['organizador'] . ')' : ''),
                'enlace' => 'reuniones/' . (int) $x['id'], 'fecha' => null];
        }
        return $r;
    }
}
