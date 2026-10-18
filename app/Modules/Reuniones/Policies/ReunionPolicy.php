<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Policies;

use App\Core\Auth;

final class ReunionPolicy
{
    public static function puedeAccederModulo(): bool
    {
        return Auth::tieneRol('admin', 'titular', 'operativo', 'profesor', 'alumno', 'coordinador');
    }

    public static function puedePrecargar(): bool
    {
        return Auth::tieneRol('admin', 'coordinador');
    }

    public static function puedeAdministrarCoordinadores(): bool
    {
        return Auth::tieneRol('admin');
    }
}
