<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Enlaces de descarga sin números consecutivos para toda la plataforma: cada ruta de descarga marcada «token» en su
 * módulo solo se alcanza en /archivo/{token}, con un token aleatorio de 32 caracteres por archivo. El Router traduce
 * el token a la ruta original y la atiende como siempre (sesión, roles del módulo y permisos del controlador), así que
 * conocer el enlace de un archivo no permite deducir el de otros.
 */
final class Descargas
{
    /** @var array<string,string> caché por petición: ruta => token */
    private static array $cache = [];

    /** Ruta relativa ('archivo/{token}') para la ruta de descarga original, por ejemplo 'recursos/archivo/15'. */
    public static function url(string $ruta): string
    {
        $ruta = '/' . trim($ruta, '/');
        if (!isset(self::$cache[$ruta])) {
            $token = Database::valor('SELECT token FROM descarga_tokens WHERE ruta = ?', [$ruta]);
            if (!$token) {
                Database::ejecutar('INSERT IGNORE INTO descarga_tokens (token, ruta) VALUES (?, ?)', [bin2hex(random_bytes(16)), $ruta]);
                $token = Database::valor('SELECT token FROM descarga_tokens WHERE ruta = ?', [$ruta]);
            }
            self::$cache[$ruta] = (string) $token;
        }
        return 'archivo/' . self::$cache[$ruta];
    }

    /** Ruta original del token, o null si no existe. */
    public static function ruta(string $token): ?string
    {
        if (!preg_match('/^[0-9a-f]{32}$/D', $token)) {
            return null;
        }
        $r = Database::valor('SELECT ruta FROM descarga_tokens WHERE token = ?', [$token]);
        return $r ? (string) $r : null;
    }
}
