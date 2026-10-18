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

    public static function valido(string $rol): bool
    {
        return in_array($rol, self::TODOS, true);
    }
}
