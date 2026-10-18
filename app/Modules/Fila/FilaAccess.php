<?php
declare(strict_types=1);
namespace App\Modules\Fila;
use App\Core\Auth;

final class FilaAccess
{
    public static function oficinas(): array
    {
        if (isset($_SESSION['impersonador'])) return [];
        if (Auth::tieneRol('admin')) return dependencias();
        if (!Auth::tieneRol('titular')) return [];
        return filas('SELECT d.*, r.cargo FROM dependencias d JOIN fila_responsables r '
            .'ON r.dependencia_id=d.id WHERE r.usuario_id=? AND d.activa=1 ORDER BY d.clave', [Auth::id()]);
    }
    public static function oficina(string $clave): ?array
    {
        foreach (self::oficinas() as $dep) if ($dep['clave']===$clave) return $dep;
        return null;
    }
}
