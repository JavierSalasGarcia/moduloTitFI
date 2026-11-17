<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\Acceso;
use App\Core\App;
use App\Core\Auth;

/** Autorización del módulo: personal de Apoyo a Grado frente a egresados de posgrado. */
final class PosgradoAccess
{
    public static function esPersonal(): bool
    {
        $u = Auth::usuario();
        if (!$u) {
            return false;
        }
        if (($u['rol'] ?? '') === 'admin' || Acceso::puede('posgrado.personal', [], null, null, 'usar', false)) {
            return true;
        }
        $email = strtolower(trim((string) ($u['email'] ?? '')));
        $gestores = array_map(static fn($v): string => strtolower(trim((string) $v)), (array) App::config('posgrado.gestores', []));
        if ($email !== '' && in_array($email, $gestores, true)) {
            return true;
        }
        return in_array((string) ($u['rol'] ?? ''), ['titular', 'operativo', 'coordinador'], true)
            && in_array((string) ($u['depto_clave'] ?? ''), (array) App::config('posgrado.departamentos', []), true);
    }

    public static function esDueno(array $tramite): bool
    {
        return Auth::id() > 0 && (int) $tramite['alumno_id'] === Auth::id();
    }

    public static function puedeVer(array $tramite): bool
    {
        return self::esDueno($tramite) || self::esPersonal();
    }

    /** El egresado solo captura y sube mientras el trámite siga abierto. */
    public static function puedeEditar(array $tramite): bool
    {
        return self::esDueno($tramite) && $tramite['estado'] === 'abierto';
    }

    /** Administración o alumno de un programa de posgrado (cualquier nivel salvo licenciatura). */
    public static function puedeIniciar(): bool
    {
        $u = Auth::usuario();
        if (!$u) {
            return false;
        }
        if (($u['rol'] ?? '') === 'admin') {
            return true;
        }
        return ($u['rol'] ?? '') === 'alumno' && (string) ($u['programa_nivel'] ?? '') !== 'licenciatura';
    }

    /** Condición de menú «Posgrado: obtener mi grado»: todos menos los alumnos de licenciatura. */
    public static function veTramite(): bool
    {
        $u = Auth::usuario();
        if (!$u) {
            return false;
        }
        return !(($u['rol'] ?? '') === 'alumno' && (string) ($u['programa_nivel'] ?? '') === 'licenciatura');
    }
}
