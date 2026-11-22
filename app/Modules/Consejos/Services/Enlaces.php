<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Identidad;

/**
 * Enlaces con token para ver y descargar un dictamen escaneado (correo de «Notificar dictamen»).
 * El token viaja solo en el correo; en la base queda su SHA-256. No da acceso por sí mismo: además hay que iniciar sesión
 * con una cuenta que tenga ese mismo correo (la cuenta se crea con correo verificado), y el enlace vence.
 */
final class Enlaces
{
    public const VIGENCIA_DIAS = 60;

    /** Crea un enlace para el correo y devuelve el token en claro (solo se puede mostrar ahora). */
    public static function crear(int $oficioId, string $email, ?int $por): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Database::ejecutar(
            'INSERT INTO consejos_enlaces (oficio_id, email, token_hash, expira_en, creado_por) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ' . self::VIGENCIA_DIAS . ' DAY), ?)',
            [$oficioId, strtolower(trim($email)), hash('sha256', $token), $por]
        );
        return $token;
    }

    public static function formatoValido(string $token): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_-]{40,50}$/', $token);
    }

    /** @return array<string,mixed>|null fila vigente del token */
    public static function buscar(string $token): ?array
    {
        if (!self::formatoValido($token)) {
            return null;
        }
        return Database::una('SELECT * FROM consejos_enlaces WHERE token_hash = ? AND expira_en > NOW()', [hash('sha256', $token)]) ?: null;
    }

    /** ¿La sesión actual tiene un enlace vigente de este oficio emitido para alguno de sus correos? */
    public static function permiteAlUsuario(int $oficioId): bool
    {
        $id = Auth::id();
        if (!$id) {
            return false;
        }
        $correos = array_map('strtolower', Identidad::correosDeUsuario((int) $id));
        if (!$correos) {
            return false;
        }
        return (bool) Database::valor(
            'SELECT 1 FROM consejos_enlaces WHERE oficio_id = ? AND expira_en > NOW() AND email IN (' . implode(',', array_fill(0, count($correos), '?')) . ') LIMIT 1',
            [$oficioId, ...$correos]
        );
    }

    /** ¿Ya existe una cuenta (o un correo adicional) con ese correo? Decide si se le pide iniciar sesión o crear su cuenta. */
    public static function tieneCuenta(string $email): bool
    {
        $email = strtolower(trim($email));
        if (Database::valor('SELECT 1 FROM usuarios WHERE LOWER(email) = ? LIMIT 1', [$email])) {
            return true;
        }
        try {
            return (bool) Database::valor('SELECT 1 FROM usuario_correos WHERE LOWER(email) = ? LIMIT 1', [$email]);
        } catch (\Throwable) {
            return false;
        }
    }
}
