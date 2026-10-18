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

    // --- condiciones de menú: cada persona solo ve lo que le toca hacer ---

    public static function nivelAlumno(): ?string
    {
        $u = Auth::usuario();
        return $u && ($u['rol'] ?? '') === 'alumno' ? ((string) ($u['programa_nivel'] ?? '') ?: null) : null;
    }

    /** El pasante de posgrado no ve licenciatura (ni a la inversa). */
    public static function veLicenciatura(): bool
    {
        return self::nivelAlumno() !== 'posgrado';
    }

    public static function vePosgrado(): bool
    {
        return self::nivelAlumno() !== 'licenciatura';
    }

    /** Correo de la sesión, en minúsculas. */
    public static function correoSesion(): string
    {
        return strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
    }

    private static function pertenencias(): array
    {
        static $cache = [];
        $email = self::correoSesion();
        if ($email === '') {
            return [];
        }
        try {
            return $cache[$email] ??= RevisionRepository::pertenencias($email);
        } catch (\Throwable) {
            return [];
        }
    }

    public static function esEvaluador(): bool
    {
        return self::pertenencias() !== [];
    }

    public static function esEncargado(): bool
    {
        return self::carrerasEncargado() !== [];
    }

    /** @return string[] */
    public static function carrerasEncargado(): array
    {
        return array_values(array_map(static fn(array $m): string => $m['carrera'], array_filter(self::pertenencias(), static fn(array $m): bool => $m['rol'] === 'encargado')));
    }
}
