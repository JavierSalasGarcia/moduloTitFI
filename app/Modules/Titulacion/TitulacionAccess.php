<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\App;
use App\Core\Auth;

/** Autorización del módulo: personal de Titulación frente a pasantes. */
final class TitulacionAccess
{
    public static function esPersonal(): bool
    {
        $u = Auth::usuario();
        if (!$u) {
            return false;
        }
        if (($u['rol'] ?? '') === 'admin') {
            return true;
        }
        $email = strtolower(trim((string) ($u['email'] ?? '')));
        $gestores = array_map(static fn($v): string => strtolower(trim((string) $v)), (array) App::config('titulacion.gestores', []));
        if ($email !== '' && in_array($email, $gestores, true)) {
            return true;
        }
        return in_array((string) ($u['rol'] ?? ''), ['titular', 'operativo', 'coordinador'], true)
            && in_array((string) ($u['depto_clave'] ?? ''), (array) App::config('titulacion.departamentos', []), true);
    }

    public static function esDueno(array $tramite): bool
    {
        return Auth::id() > 0 && (int) $tramite['alumno_id'] === Auth::id();
    }

    public static function puedeVer(array $tramite): bool
    {
        return self::esDueno($tramite) || self::esPersonal();
    }

    /** El pasante solo captura y sube mientras el trámite siga abierto. */
    public static function puedeEditar(array $tramite): bool
    {
        return self::esDueno($tramite) && $tramite['estado'] === 'abierto';
    }

    public static function puedeIniciar(): bool
    {
        return in_array(Auth::rol(), ['alumno', 'admin'], true);
    }
}
