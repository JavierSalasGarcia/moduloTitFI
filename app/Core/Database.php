<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/**
 * Conexión PDO única (singleton) con utilidades de consulta cortas.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = App::config('db');
            $dsn = "mysql:host={$c['host']};dbname={$c['nombre']};charset={$c['charset']}";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STATEMENT_CLASS   => [GuardStatement::class],
            ];
            if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
                $options[(int) constant('PDO::MYSQL_ATTR_INIT_COMMAND')] = "SET time_zone = '" . date('P') . "'";
            }
            self::$pdo = new GuardPDO($dsn, $c['usuario'], $c['clave'], $options);
        }
        return self::$pdo;
    }

    /** Todas las filas. */
    public static function todas(string $sql, array $params = []): array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** Una fila o null. */
    public static function una(string $sql, array $params = []): ?array
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        $fila = $stmt->fetch();
        return $fila === false ? null : $fila;
    }

    /** Un valor escalar. */
    public static function valor(string $sql, array $params = []): mixed
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    /** INSERT/UPDATE/DELETE; devuelve el último id insertado. */
    public static function ejecutar(string $sql, array $params = []): int
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return (int) self::pdo()->lastInsertId();
    }
}
