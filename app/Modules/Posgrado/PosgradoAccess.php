<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\Acceso;
use App\Core\App;
use App\Core\Auth;

/** Autorización del módulo: personal de Apoyo a Grado frente a egresados de posgrado. */
final class PosgradoAccess
{
    /**
     * El módulo solo se muestra cuando `posgrado.publicado` está activo en la configuración; mientras tanto únicamente
     * la administración puede entrar para probarlo.
     */
    public static function publicado(): bool
    {
        return (bool) App::config('posgrado.publicado', false) || ((Auth::usuario()['rol'] ?? '') === 'admin');
    }

    /**
     * Publicación real del módulo, sin la excepción de la administración: las rutas públicas de firma por enlace solo
     * operan cuando `posgrado.publicado` es true.
     */
    public static function publicadoGlobal(): bool
    {
        return (bool) App::config('posgrado.publicado', false);
    }

    /** Correo de la sesión, en minúsculas. */
    public static function correoSesion(): string
    {
        return strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
    }

    /** Correos con los que se reconoce a la persona con sesión (el vigente y sus alias anteriores). @return string[] */
    public static function correosSesion(): array
    {
        $u = Auth::usuario();
        if (!$u) {
            return [];
        }
        $c = [self::correoSesion()];
        try {
            $c = array_merge($c, \App\Core\Identidad::correosDeUsuario((int) $u['id']));
        } catch (\Throwable) {
            // Sin la tabla de alias, solo el correo principal.
        }
        return array_values(array_unique(array_filter(array_map(static fn($e): string => strtolower(trim((string) $e)), $c))));
    }

    /**
     * Firma por la Coordinación de Estudios Avanzados: la cuenta cuyo correo es el ajuste `posgrado_coordinador_correo`
     * o una cuenta con rol titular en una dependencia de `posgrado.departamentos`. La administración no firma por la
     * Coordinación salvo que cumpla lo mismo.
     */
    public static function puedeFirmarCoordinacion(): bool
    {
        $u = Auth::usuario();
        if (!$u) {
            return false;
        }
        $correo = strtolower(trim(Configuracion::valor('posgrado_coordinador_correo')));
        if ($correo !== '' && in_array($correo, self::correosSesion(), true)) {
            return true;
        }
        $deptos = (array) App::config('posgrado.departamentos', []);
        if (($u['rol'] ?? '') === 'titular' && in_array((string) ($u['depto_clave'] ?? ''), $deptos, true)) {
            return true;
        }
        foreach ($u['roles_extra'] ?? [] as $r) {
            if (($r['rol'] ?? '') === 'titular' && in_array((string) ($r['depto_clave'] ?? ''), $deptos, true)) {
                return true;
            }
        }
        return false;
    }

    /** Condición de menú «Posgrado: firmas»: firma por la Coordinación o tiene firmas pedidas a su correo. */
    public static function tieneFirmas(): bool
    {
        if (!Auth::usuario() || !self::publicado()) {
            return false;
        }
        return self::esSupervision() || self::puedeFirmarCoordinacion() || Services\Firmas::hayDeCorreo(self::correosSesion());
    }

    /** Condición de menú de la bandeja del personal. */
    public static function menuGestion(): bool
    {
        return self::publicado() && self::esPersonal();
    }

    /**
     * Supervisión: la administración y el Subdirector Académico ven Posgrado completo (trámites, bandeja, configuración y
     * firmas) aunque no participen. El Subdirector solo consulta: no opera la bandeja ni firma por la Coordinación salvo
     * que cumpla puedeFirmarCoordinacion().
     */
    public static function esSupervision(): bool
    {
        $u = Auth::usuario();
        if (!$u) {
            return false;
        }
        if (($u['rol'] ?? '') === 'admin') {
            return true;
        }
        try {
            return \App\Modules\Titulacion\Services\SubdireccionFirma::esSubdirector();
        } catch (\Throwable) {
            return false;
        }
    }

    /** Personal de Apoyo a Grado que opera trámites (valida, coteja, emite); incluye a la administración. */
    public static function esPersonalOperativo(): bool
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
        $deptos = (array) App::config('posgrado.departamentos', []);
        if (in_array((string) ($u['rol'] ?? ''), ['titular', 'operativo', 'coordinador'], true) && in_array((string) ($u['depto_clave'] ?? ''), $deptos, true)) {
            return true;
        }
        foreach ($u['roles_extra'] ?? [] as $r) {
            if (in_array((string) ($r['rol'] ?? ''), ['titular', 'operativo', 'coordinador'], true) && in_array((string) ($r['depto_clave'] ?? ''), $deptos, true)) {
                return true;
            }
        }
        return false;
    }

    /** Ve la bandeja y los expedientes: personal de Apoyo a Grado o supervisión. */
    public static function esPersonal(): bool
    {
        return self::esPersonalOperativo() || self::esSupervision();
    }

    /** Alumno o egresado de un programa de posgrado. */
    public static function esAlumnoPosgrado(): bool
    {
        $u = Auth::usuario();
        return $u !== null && ($u['rol'] ?? '') === 'alumno' && (string) ($u['programa_nivel'] ?? '') === 'posgrado';
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

    /** Administración o alumno de un programa de posgrado. */
    public static function puedeIniciar(): bool
    {
        $u = Auth::usuario();
        if (!$u) {
            return false;
        }
        return ($u['rol'] ?? '') === 'admin' || self::esAlumnoPosgrado();
    }

    /**
     * Entrada «Posgrado: obtener mi grado»: solo sus actores (alumnos de posgrado y personal de Apoyo a Grado) y la
     * supervisión. Un profesor o personal de otra área sin participación no la ve; un alumno de licenciatura tampoco.
     */
    public static function veTramite(): bool
    {
        if (!Auth::usuario() || !self::publicado()) {
            return false;
        }
        return self::esSupervision() || self::esAlumnoPosgrado() || self::esPersonal();
    }
}
