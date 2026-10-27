<?php
declare(strict_types=1);

namespace App\Core;

/** Catálogo único de niveles de acceso reconocidos por Traza FI. */
final class Roles
{
    public const ADMIN = 'admin';
    public const TITULAR = 'titular';
    public const OPERATIVO = 'operativo';
    public const COORDINADOR = 'coordinador';
    public const PROFESOR = 'profesor';
    public const ALUMNO = 'alumno';

    public const TODOS = [
        self::ADMIN,
        self::TITULAR,
        self::OPERATIVO,
        self::COORDINADOR,
        self::PROFESOR,
        self::ALUMNO,
    ];

    /** «Titular de …» / «Asistente de …» según el cargo y la dependencia; sin cargo, solo el nombre de la dependencia. */
    public static function puesto(?string $cargo, string $dependencia): string
    {
        if ($dependencia === '') {
            return '';
        }
        $prefijo = ['titular' => 'Titular de ', 'asistente' => 'Asistente de '][$cargo ?? ''] ?? '';
        if ($prefijo === '') {
            return $dependencia;
        }
        // «de la Subdirección…» y «del Departamento…»: se contrae según el género del nombre.
        if (preg_match('/^(Coordinación|Subdirección|Dirección|Unidad)\b/u', $dependencia)) {
            return $prefijo . 'la ' . $dependencia;
        }
        if (preg_match('/^(Departamento|Centro|Consejo)\b/u', $dependencia)) {
            return substr($prefijo, 0, -1) . 'l ' . $dependencia;
        }
        return $prefijo . $dependencia;
    }

    public static function valido(string $rol): bool
    {
        return in_array($rol, self::TODOS, true);
    }
}
