<?php
declare(strict_types=1);

namespace App\Modules\Concesiones;

use App\Core\Acceso;
use App\Core\Auth;

/** Avisos de vencimiento: a la persona con el acceso y, a quien concede, de las de su dependencia. */
final class PendientesConcesiones
{
    private const DIAS = 15;

    public static function de(): array
    {
        $id = Auth::id();
        if ($id <= 0) {
            return [];
        }
        $r = [];
        foreach (Acceso::vigentes($id) as $c) {
            if ($c['vigente_hasta'] <= date('Y-m-d', strtotime('+' . self::DIAS . ' days'))) {
                $r[] = ['tipo' => 'Acceso', 'texto' => 'Tu acceso «' . Acceso::etiqueta((string) $c['funcion']) . '» vence el ' . $c['vigente_hasta'], 'enlace' => 'perfil', 'fecha' => null];
            }
        }
        if (Auth::tieneRol('admin', 'titular')) {
            $deptos = Acceso::dependenciasQueConcede($id);
            $n = count(Acceso::porVencer(self::DIAS, $deptos));
            if ($n > 0 && ($deptos === null || $deptos !== [])) {
                $r[] = ['tipo' => 'Acceso', 'texto' => $n . ' acceso(s) especial(es) vencen en ' . self::DIAS . ' días o menos', 'enlace' => 'concesiones', 'fecha' => null];
            }
        }
        return $r;
    }
}
