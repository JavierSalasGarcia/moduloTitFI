<?php
declare(strict_types=1);

namespace App\Modules\Concursos;

use App\Core\Auth;

/** Concursos con la votación abierta en los que la persona aún no vota (para la bandeja de Notificaciones). */
final class PendientesConcursos
{
    public static function de(): array
    {
        $r = [];
        foreach (Concursos::listar()['actuales'] as $c) {
            if ($c['fase'] === 'votacion' && !Concursos::yaVoto((int) $c['id'], (int) Auth::id())) {
                $r[] = ['tipo' => 'Votación', 'texto' => 'Vota en «' . $c['titulo'] . '» (hasta ' . Concursos::fechaHora($c['votacion_fin']) . ')', 'enlace' => 'concursos/' . $c['slug'], 'fecha' => null];
            }
        }
        return $r;
    }
}
