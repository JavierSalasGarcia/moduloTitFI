<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Policies;

use App\Core\Acceso;
use App\Core\Auth;

final class ReunionPolicy
{
    public static function puedeAccederModulo(): bool
    {
        return Auth::tieneRol('admin', 'titular', 'operativo', 'profesor', 'alumno', 'coordinador');
    }

    public static function puedePrecargar(): bool
    {
        return Acceso::puede('reuniones.precargar', ['admin', 'coordinador'], null, null, 'usar', false);
    }

    public static function puedeAdministrarCoordinadores(): bool
    {
        return Auth::tieneRol('admin');
    }
}
