<?php
declare(strict_types=1);

namespace App\Modules\Notificaciones;

use App\Core\Database;
use Throwable;

/**
 * Bandeja única de notificaciones de la plataforma: todo aviso dirigido a una persona (por su correo) llega aquí;
 * el correo electrónico solo la alerta y la trae de vuelta a esta bandeja.
 */
final class Notificaciones
{
    public static function crear(string $email, string $titulo, string $cuerpo, ?string $enlace = null, string $origen = 'plataforma', ?int $tramiteId = null): int
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 0;
        }
        try {
            return Database::ejecutar(
                'INSERT INTO notificaciones (email, titulo, cuerpo, enlace, origen, tramite_id) VALUES (?, ?, ?, ?, ?, ?)',
                [$email, mb_substr($titulo, 0, 200), mb_substr($cuerpo, 0, 20000), $enlace !== null ? mb_substr($enlace, 0, 500) : null, mb_substr($origen, 0, 40), $tramiteId]
            );
        } catch (Throwable $e) {
            error_log('Notificaciones: no se registró: ' . $e->getMessage());
            return 0;
        }
    }

    /** Correos (uno o varios: la persona puede haber cambiado de dominio) normalizados y sin repetir. @return string[] */
    private static function correos(string|array $email): array
    {
        return array_values(array_unique(array_filter(array_map(static fn($e): string => strtolower(trim((string) $e)), (array) $email))));
    }

    public static function de(string|array $email, int $limite = 300): array
    {
        $c = self::correos($email);
        return $c ? Database::todas('SELECT * FROM notificaciones WHERE email IN (' . implode(',', array_fill(0, count($c), '?')) . ') ORDER BY id DESC LIMIT ' . max(1, $limite), $c) : [];
    }

    public static function una(int $id): ?array
    {
        return Database::una('SELECT * FROM notificaciones WHERE id = ?', [$id]);
    }

    public static function noLeidas(string|array $email): int
    {
        try {
            $c = self::correos($email);
            return $c ? (int) Database::valor('SELECT COUNT(*) FROM notificaciones WHERE email IN (' . implode(',', array_fill(0, count($c), '?')) . ') AND leida_en IS NULL', $c) : 0;
        } catch (Throwable) {
            return 0;
        }
    }

    public static function marcarLeida(int $id): void
    {
        Database::ejecutar('UPDATE notificaciones SET leida_en = NOW() WHERE id = ? AND leida_en IS NULL', [$id]);
    }

    public static function marcarTodas(string|array $email): void
    {
        $c = self::correos($email);
        if ($c) {
            Database::ejecutar('UPDATE notificaciones SET leida_en = NOW() WHERE email IN (' . implode(',', array_fill(0, count($c), '?')) . ') AND leida_en IS NULL', $c);
        }
    }
}
