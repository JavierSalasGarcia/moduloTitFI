<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Trato a las personas según su grado y género (campos de la cuenta): evita fórmulas como «Estimado(a)».
 * Una «persona» es un arreglo con nombre y, si se conocen, grado y genero ('masculino'|'femenino').
 */
final class Persona
{
    public const GRADOS = ['Dr.', 'Dra.', 'M. en C.', 'M. en I.', 'M. en C. C.', 'Mtro.', 'Mtra.', 'Ing.', 'Lic.', 'Arq.', 'C.P.', 'L.A.E.'];

    /** Persona con cuenta, por correo; null si no existe. */
    public static function porEmail(string $email): ?array
    {
        static $cache = [];
        $email = strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        if (!array_key_exists($email, $cache)) {
            try {
                $cache[$email] = Database::una('SELECT nombre, grado, genero FROM usuarios WHERE email = ? AND activo = 1', [$email]) ?: null;
            } catch (\Throwable) {
                $cache[$email] = null;   // aún sin la migración
            }
            // Sin cuenta: el directorio de profesores da su género («Profesor» / «Profesora») y su nombre.
            $cache[$email] ??= Profesores::porEmail($email);
        }
        return $cache[$email];
    }

    public static function porId(int $id): ?array
    {
        try {
            return Database::una('SELECT nombre, grado, genero FROM usuarios WHERE id = ?', [$id]) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function genero(array $p): ?string
    {
        $g = $p['genero'] ?? null;
        return in_array($g, ['masculino', 'femenino'], true) ? $g : null;
    }

    /** «Dr. Javier Salas García» (grado y nombre; no repite el grado si el nombre ya lo trae). */
    public static function tratamiento(array $p, bool $mayusculas = false): string
    {
        $nombre = trim((string) ($p['nombre'] ?? ''));
        $grado = trim((string) ($p['grado'] ?? ''));
        if ($grado !== '' && !preg_match('/^' . preg_quote(rtrim($grado, '.'), '/') . '\b/iu', $nombre)) {
            $nombre = $grado . ' ' . $nombre;
        }
        return $mayusculas ? mb_strtoupper($nombre) : $nombre;
    }

    /** Elige la forma masculina o femenina; sin género conocido usa la masculina genérica. */
    public static function f(array $p, string $masculino, string $femenino): string
    {
        return self::genero($p) === 'femenino' ? $femenino : $masculino;
    }

    /** «Estimado Dr. Javier Salas García» / «Estimada Dra. …»; si no se conoce el género, un saludo neutro. */
    public static function saludo(array $p): string
    {
        $t = self::tratamiento($p);
        return self::genero($p) === null ? 'Hola ' . $t : self::f($p, 'Estimado ', 'Estimada ') . $t;
    }

    /** Completa grado y género de una persona con los de su cuenta, si tiene. */
    public static function completar(array $p, string $email): array
    {
        $c = self::porEmail($email);
        if ($c) {
            $p['nombre'] = $p['nombre'] ?? $c['nombre'];
            $p['grado'] = $p['grado'] ?? $c['grado'];
            $p['genero'] = $p['genero'] ?? $c['genero'];
        }
        return $p;
    }
}
