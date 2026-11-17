<?php
declare(strict_types=1);

namespace App\Modules\Constancias;

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Identidad;

/**
 * Quién ve y quién hace qué en Constancias (decisiones del Subdirector Académico):
 *  - Cada persona ve las constancias que recibió (firmadas), agrupadas por el departamento que las emitió.
 *  - El personal (titular, coordinador, operativo) ve además todas las que emitió su departamento.
 *  - Administración y Subdirección Académica ven todas.
 *  - Solicitan constancias las cuentas de personal (titular, coordinador, operativo y administración).
 */
final class ConstanciasAccess
{
    public const ROLES_PERSONAL = ['titular', 'coordinador', 'operativo'];
    public const ROLES_SOLICITAN = ['admin', 'titular', 'coordinador', 'operativo'];
    /** Dependencias cuyo personal supervisa todas las constancias. */
    public const SUPERVISION = ['subdireccion_academica', 'titular_de_la_subdireccion_academica'];

    public static function puedeSolicitar(): bool
    {
        return Auth::tieneRol(...self::ROLES_SOLICITAN);
    }

    public static function esPersonal(): bool
    {
        return Auth::tieneRol(...self::ROLES_PERSONAL);
    }

    /** Administración, personal de la Subdirección Académica o el Subdirector (por su nombramiento). */
    public static function esSupervision(): bool
    {
        if (Auth::tieneRol('admin')) {
            return true;
        }
        if (self::esPersonal() && array_intersect(Auth::deptoClaves(), self::SUPERVISION)) {
            return true;
        }
        try {
            return \App\Modules\Titulacion\Services\SubdireccionFirma::esSubdirector();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Departamentos cuyas constancias ve la persona como personal. Incluye la dependencia «Titular de la X» como X y las
     * dependencias que Posgrado agrupa como Estudios Avanzados (`posgrado.departamentos`).
     * @return int[]
     */
    public static function departamentosPropios(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        if (!self::esPersonal()) {
            return $cache = [];
        }
        $claves = Auth::deptoClaves();
        foreach (Auth::deptoClaves() as $c) {
            if (str_starts_with($c, 'titular_de_la_')) {
                $claves[] = substr($c, strlen('titular_de_la_'));
            } elseif (str_starts_with($c, 'titular_del_')) {
                $claves[] = substr($c, strlen('titular_del_'));
            }
        }
        $posgrado = (array) App::config('posgrado.departamentos', []);
        if (array_intersect($claves, $posgrado)) {
            $claves = array_merge($claves, $posgrado);
        }
        $ids = Auth::departamentoIds();
        $claves = array_values(array_unique(array_filter($claves)));
        if ($claves) {
            $ids = array_merge($ids, array_map('intval', array_column(Database::todas(
                'SELECT id FROM departamentos WHERE clave IN (' . implode(',', array_fill(0, count($claves), '?')) . ')', $claves
            ), 'id')));
        }
        return $cache = array_values(array_unique(array_filter(array_map('intval', $ids))));
    }

    /** Correos con los que se reconoce a la persona con sesión (el vigente, sus alias y los de su ficha de profesor). @return string[] */
    public static function correosSesion(): array
    {
        $email = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        if ($email === '') {
            return [];
        }
        try {
            return Identidad::correosDe($email);
        } catch (\Throwable) {
            return [$email];
        }
    }

    /**
     * Alcance de la sesión para el índice: todas (supervisión), departamentos propios y la identidad para «las que recibí».
     * @return array{todas:bool,deptos:int[],yo:int,usuario_id:?int,profesor_id:?int,correos:string[]}
     */
    public static function alcance(): array
    {
        $email = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        $persona = $email !== '' ? Identidad::resolver($email) : ['usuario_id' => null, 'profesor_id' => null];
        return [
            'todas' => self::esSupervision(),
            'deptos' => self::departamentosPropios(),
            'yo' => Auth::id(),
            'usuario_id' => $persona['usuario_id'] ?? (Auth::id() ?: null),
            'profesor_id' => $persona['profesor_id'] ?? null,
            'correos' => self::correosSesion(),
        ];
    }

    /** Condición de menú: tiene constancias por firmar. */
    public static function tieneFirmas(): bool
    {
        if (!Auth::id()) {
            return false;
        }
        try {
            return (bool) Database::valor("SELECT 1 FROM cst_solicitudes WHERE firmante_id = ? AND estado IN ('en_firma','firmada') LIMIT 1", [Auth::id()]);
        } catch (\Throwable) {
            return false;
        }
    }
}
