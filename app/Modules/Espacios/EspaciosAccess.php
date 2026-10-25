<?php
declare(strict_types=1);

namespace App\Modules\Espacios;

use App\Core\Auth;
use App\Core\Database;
use Throwable;

/** Quién administra el módulo y quién puede registrar la ocupación de cada espacio. */
final class EspaciosAccess
{
    /** Administrador general de la plataforma: nombra a los administradores del módulo. */
    public static function esAdminGeneral(): bool
    {
        return Auth::rol() === 'admin';
    }

    public static function esAdmin(): bool
    {
        if (self::esAdminGeneral()) {
            return true;
        }
        try {
            return Auth::id() > 0 && (bool) Database::valor('SELECT 1 FROM esp_administradores WHERE usuario_id = ?', [Auth::id()]);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return int[] espacios en los que la persona puede registrar ocupación */
    public static function espaciosAutorizados(): array
    {
        if (self::esAdmin()) {
            return array_map(static fn(array $e): int => (int) $e['id'], Agenda::espacios(false));
        }
        try {
            return array_map('intval', array_column(Database::todas('SELECT espacio_id FROM esp_autorizados WHERE usuario_id = ?', [Auth::id()]), 'espacio_id'));
        } catch (Throwable) {
            return [];
        }
    }

    public static function puedeRegistrar(int $espacioId): bool
    {
        return in_array($espacioId, self::espaciosAutorizados(), true);
    }

    /** Puede cancelar quien registró la ocupación o un administrador del módulo. */
    public static function puedeCancelar(array $reserva): bool
    {
        return self::esAdmin() || (int) $reserva['registrado_por'] === Auth::id();
    }
}
