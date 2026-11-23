<?php
declare(strict_types=1);

namespace App\Modules\Oficios;

use App\Core\Auth;
use App\Modules\Consejos\ConsejosAccess;
use App\Modules\Titulacion\Services\SubdireccionFirma;

/**
 * Quién hace qué en los oficios de la Subdirección Académica:
 *  - Redactan y envían a firma: el Subdirector Académico, su asistente y quien tenga la concesión `consejos.gestor` (la misma regla de Consejos).
 *  - Firma: solo el titular de la Subdirección Académica, con su e.firma del SAT.
 *  - Configuración (RFC del titular y certificados del SAT): el titular y la administración.
 */
final class OficiosAccess
{
    public static function puedeRedactar(): bool
    {
        return Auth::id() !== null && (ConsejosAccess::esGestor() || self::puedeFirmar());
    }

    public static function puedeFirmar(): bool
    {
        try {
            return Auth::id() !== null && SubdireccionFirma::esSubdirector();
        } catch (\Throwable) {
            return false;
        }
    }

    public static function puedeConfigurar(): bool
    {
        return Auth::id() !== null && (Auth::tieneRol('admin') || self::puedeFirmar());
    }
}
