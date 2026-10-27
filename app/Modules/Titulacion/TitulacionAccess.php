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
        return Auth::tieneRol('alumno', 'admin');
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

    /** Pasante con al menos un trámite: ve «Estado». */
    public static function tieneTramites(): bool
    {
        if (Auth::id() <= 0 || !Auth::tieneRol('alumno')) {
            return false;
        }
        try {
            return (bool) \App\Core\Database::valor('SELECT 1 FROM tit_tramites WHERE alumno_id = ? LIMIT 1', [Auth::id()]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Quien revisa protocolos o ya tiene constancias ve «Constancias → Protocolos». */
    public static function tieneConstancias(): bool
    {
        if (self::esEvaluador()) {
            return true;
        }
        $email = self::correoSesion();
        try {
            return $email !== '' && (bool) \App\Core\Database::valor('SELECT 1 FROM tit_constancias WHERE email = ? LIMIT 1', [$email]);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * ¿La persona con sesión participa en el trámite? Pasante, personal de Titulación, asesoría, comité de su
     * carrera, Coordinador de Docencia, sinodales, autoridades que firman y quien tenga una firma o constancia en él.
     */
    public static function participa(array $tramite): bool
    {
        if (self::puedeVer($tramite)) {
            return true;
        }
        $email = self::correoSesion();
        if ($email === '') {
            return false;
        }
        if (Services\SubdireccionFirma::puedeFirmar()) {
            return true;
        }
        $p = $tramite['datos']['protocolo'] ?? [];
        if (in_array($email, [strtolower((string) ($p['asesor_correo'] ?? '')), strtolower((string) ($p['coasesor_correo'] ?? ''))], true)) {
            return true;
        }
        $carrera = (string) ($tramite['datos']['datos']['carrera'] ?? '');
        if (in_array($carrera, self::carrerasEncargado(), true) || (Services\Autoridades::coordinadorDocencia($carrera)['email'] ?? '') === $email) {
            return true;
        }
        $tid = (int) $tramite['id'];
        try {
            return (bool) \App\Core\Database::valor(
                "SELECT 1 FROM tit_firmas_externas WHERE tramite_id = ? AND email = ?
                 UNION SELECT 1 FROM tit_sinodales WHERE tramite_id = ? AND email = ?
                 UNION SELECT 1 FROM tit_asignaciones_jurado WHERE tramite_id = ? AND coord_email = ?
                 UNION SELECT 1 FROM tit_revision_asignaciones a JOIN tit_revisiones r ON r.id = a.revision_id JOIN tit_comision_miembros m ON m.id = a.miembro_id WHERE r.tramite_id = ? AND m.email = ?
                 LIMIT 1",
                [$tid, $email, $tid, $email, $tid, $email, $tid, $email]
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
