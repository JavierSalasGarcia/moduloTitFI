<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Identidad estable de las personas ante el cambio de dominio del correo institucional (@profesor.uaemex.mx y @alumno.uaemex.mx
 * pasan a @uaemex.mx). La persona es la cuenta (usuarios.id) o, si aún no se registra, el profesor del directorio (profesores.id,
 * que se identifica por un dato interno que nunca se muestra); el correo es un atributo que puede cambiar. Migración 027.
 *
 * Todo degrada con gracia si las tablas aún no existen: se comporta como antes, con el correo como llave.
 */
final class Identidad
{
    private const CODIGO_MINUTOS = 10;

    public static function normalizar(string $email): string
    {
        return strtolower(trim($email));
    }

    /** Cuenta activa por su correo vigente o por uno anterior que verificó (para iniciar sesión y recuperar el acceso). */
    public static function usuarioPorCorreo(string $email): ?array
    {
        $email = self::normalizar($email);
        if ($email === '') {
            return null;
        }
        $u = Database::una('SELECT * FROM usuarios WHERE email = ? AND activo = 1', [$email]);
        if ($u) {
            return $u;
        }
        try {
            return Database::una('SELECT u.* FROM usuario_correos c JOIN usuarios u ON u.id = c.usuario_id WHERE c.email = ? AND u.activo = 1', [$email]);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Correos de la cuenta: el vigente primero, luego los anteriores verificados. @return string[] */
    public static function correosDeUsuario(int $usuarioId): array
    {
        $vigente = (string) Database::valor('SELECT email FROM usuarios WHERE id = ?', [$usuarioId]);
        $otros = [];
        try {
            $otros = array_column(Database::todas('SELECT email FROM usuario_correos WHERE usuario_id = ? ORDER BY principal DESC, id', [$usuarioId]), 'email');
        } catch (\Throwable) {
        }
        return array_values(array_unique(array_filter([$vigente, ...$otros])));
    }

    /** Todos los correos que identifican a la persona del correo dado: sus correos de cuenta y los de su registro del directorio. @return string[] */
    public static function correosDe(string $email): array
    {
        $email = self::normalizar($email);
        $correos = [$email];
        try {
            ['usuario_id' => $uid, 'profesor_id' => $pid] = self::resolver($email);
            if ($uid) {
                $correos = [...$correos, ...self::correosDeUsuario($uid)];
            }
            if ($pid) {
                $correos = [...$correos, ...array_column(Database::todas('SELECT email FROM profesor_correos WHERE profesor_id = ?', [$pid]), 'email')];
            }
        } catch (\Throwable) {
        }
        return array_values(array_unique(array_filter($correos)));
    }

    /** La persona detrás de un correo: su cuenta y/o su registro del directorio de profesores (cualquiera puede faltar). */
    public static function resolver(string $email): array
    {
        static $cache = [];
        $email = self::normalizar($email);
        if (isset($cache[$email])) {
            return $cache[$email];
        }
        $uid = null;
        $pid = null;
        try {
            $uid = ($u = self::usuarioPorCorreo($email)) ? (int) $u['id'] : null;
            $pid = Database::valor('SELECT profesor_id FROM profesor_correos WHERE email = ?', [$email]);
            if (!$pid && $uid) {
                $pid = Database::valor('SELECT id FROM profesores WHERE usuario_id = ?', [$uid]);
            }
            if (!$uid && $pid) {
                $uid = Database::valor('SELECT usuario_id FROM profesores WHERE id = ?', [$pid]);
            }
        } catch (\Throwable) {
        }
        return $cache[$email] = ['usuario_id' => $uid ? (int) $uid : null, 'profesor_id' => $pid ? (int) $pid : null];
    }

    /** ¿La constancia (o cualquier fila con email/usuario_id/profesor_id) es de la persona de esta sesión? */
    public static function esDe(array $fila, string $correoSesion): bool
    {
        $yo = self::resolver($correoSesion);
        return ($yo['usuario_id'] && (int) ($fila['usuario_id'] ?? 0) === $yo['usuario_id'])
            || ($yo['profesor_id'] && (int) ($fila['profesor_id'] ?? 0) === $yo['profesor_id'])
            || in_array(self::normalizar((string) ($fila['email'] ?? '')), self::correosDe($correoSesion), true);
    }

    /** Condición SQL (con alias de tabla) y parámetros para las filas de la persona: por cuenta, por profesor o por cualquiera de sus correos. */
    public static function condicion(string $alias, string $email): array
    {
        $p = self::resolver($email);
        $correos = self::correosDe($email);
        $sql = [];
        $par = [];
        if ($p['usuario_id']) {
            $sql[] = "$alias.usuario_id = ?";
            $par[] = $p['usuario_id'];
        }
        if ($p['profesor_id']) {
            $sql[] = "$alias.profesor_id = ?";
            $par[] = $p['profesor_id'];
        }
        $sql[] = "$alias.email IN (" . implode(',', array_fill(0, count($correos), '?')) . ')';
        return ['(' . implode(' OR ', $sql) . ')', [...$par, ...$correos]];
    }

    // ------------------------------------------------------------------ cambio de correo verificado

    /** ¿El correo puede pasar a ser de esta cuenta? No debe ser (ni haber sido) de otra cuenta ni de otro profesor con cuenta distinta. */
    public static function disponible(string $email, int $usuarioId): bool
    {
        $email = self::normalizar($email);
        if (Database::valor('SELECT id FROM usuarios WHERE email = ? AND id <> ?', [$email, $usuarioId])) {
            return false;
        }
        try {
            if (Database::valor('SELECT usuario_id FROM usuario_correos WHERE email = ? AND usuario_id <> ?', [$email, $usuarioId])) {
                return false;
            }
            $dueño = Database::una('SELECT p.usuario_id FROM profesor_correos c JOIN profesores p ON p.id = c.profesor_id WHERE c.email = ?', [$email]);
            if ($dueño && $dueño['usuario_id'] !== null && (int) $dueño['usuario_id'] !== $usuarioId) {
                return false;
            }
        } catch (\Throwable) {
        }
        return true;
    }

    /** Guarda la solicitud y devuelve el código de 6 dígitos que debe enviarse al correo nuevo. */
    public static function iniciarCambio(int $usuarioId, string $nuevo): string
    {
        $codigo = (string) random_int(100000, 999999);
        Database::ejecutar(
            'REPLACE INTO cambio_correo (usuario_id, email_nuevo, codigo_hash, expira_en, intentos) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . self::CODIGO_MINUTOS . ' MINUTE), 0)',
            [$usuarioId, self::normalizar($nuevo), password_hash($codigo, PASSWORD_DEFAULT)]
        );
        return $codigo;
    }

    public static function cambioPendiente(int $usuarioId): ?string
    {
        try {
            return Database::valor('SELECT email_nuevo FROM cambio_correo WHERE usuario_id = ? AND expira_en > NOW()', [$usuarioId]) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Confirma el cambio con el código recibido en el correo nuevo. El anterior queda como alias verificado. */
    public static function confirmarCambio(int $usuarioId, string $codigo): bool
    {
        $fila = Database::una('SELECT * FROM cambio_correo WHERE usuario_id = ? AND expira_en > NOW() AND intentos < 5', [$usuarioId]);
        if (!$fila) {
            return false;
        }
        Database::ejecutar('UPDATE cambio_correo SET intentos = intentos + 1 WHERE usuario_id = ?', [$usuarioId]);
        if (!preg_match('/^\d{6}$/D', $codigo) || !password_verify($codigo, (string) $fila['codigo_hash'])) {
            return false;
        }
        $nuevo = (string) $fila['email_nuevo'];
        if (!self::disponible($nuevo, $usuarioId)) {
            return false;
        }
        self::asignarCorreo($usuarioId, $nuevo, 'cambio');
        Database::ejecutar('DELETE FROM cambio_correo WHERE usuario_id = ?', [$usuarioId]);
        return true;
    }

    /** Pone `$nuevo` como correo vigente de la cuenta, conserva el anterior como alias y suma el nuevo al directorio de profesores. */
    public static function asignarCorreo(int $usuarioId, string $nuevo, string $origen): void
    {
        $nuevo = self::normalizar($nuevo);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $anterior = (string) Database::valor('SELECT email FROM usuarios WHERE id = ? FOR UPDATE', [$usuarioId]);
            if ($anterior !== $nuevo) {
                Database::ejecutar('INSERT IGNORE INTO usuario_correos (usuario_id, email, principal, origen) VALUES (?, ?, 0, ?)', [$usuarioId, $anterior, 'cuenta']);
                Database::ejecutar('UPDATE usuarios SET email = ? WHERE id = ?', [$nuevo, $usuarioId]);
            }
            Database::ejecutar('UPDATE usuario_correos SET principal = 0 WHERE usuario_id = ?', [$usuarioId]);
            Database::ejecutar(
                'INSERT INTO usuario_correos (usuario_id, email, principal, origen) VALUES (?, ?, 1, ?) ON DUPLICATE KEY UPDATE principal = 1',
                [$usuarioId, $nuevo, $origen]
            );
            $pid = Database::valor('SELECT id FROM profesores WHERE usuario_id = ?', [$usuarioId]);
            if ($pid && !Database::valor('SELECT 1 FROM profesor_correos WHERE email = ?', [$nuevo])) {
                Database::ejecutar('UPDATE profesor_correos SET principal = 0 WHERE profesor_id = ?', [$pid]);
                Database::ejecutar('INSERT INTO profesor_correos (profesor_id, email, principal) VALUES (?, ?, 1)', [$pid, $nuevo]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /** «j•••@profesor.uaemex.mx»: para decirle a alguien con qué correo ya tiene cuenta sin mostrarlo completo. */
    public static function enmascarar(string $email): string
    {
        [$u, $d] = array_pad(explode('@', $email, 2), 2, '');
        return mb_substr($u, 0, 1) . str_repeat('•', max(2, min(6, mb_strlen($u) - 1))) . '@' . $d;
    }
}
