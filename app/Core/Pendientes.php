<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Todo lo que le toca hacer a la persona con sesión, de todos los módulos, en un solo lugar (Notificaciones).
 * Cada módulo declara en su manifiesto `pendientes: "App\Modules\X\Clase::metodo"`; esa función devuelve una lista de
 * ['tipo' => 'Firma', 'texto' => '…', 'enlace' => 'ruta', 'fecha' => 'Y-m-d H:i:s'|null]. Un módulo que falle no afecta a los demás.
 */
final class Pendientes
{
    /** @return array<string,array{nombre:string,items:array<int,array<string,mixed>>}> pendientes por módulo (solo los que tienen algo). */
    public static function agrupados(): array
    {
        $nombres = [];
        foreach (ModuleManager::descubrir() as $m) {
            $nombres[(string) $m['clave']] = (string) ($m['nombre'] ?? InterfaceUi::NAMES[$m['clave']] ?? ucfirst((string) $m['clave']));
        }
        $r = [];
        foreach (ModuleManager::proveedoresPendientes() as $clave => $funcion) {
            if (!is_callable($funcion)) {
                continue;
            }
            try {
                $items = array_values(array_filter((array) $funcion(), static fn($i): bool => is_array($i) && !empty($i['texto']) && isset($i['enlace'])));
            } catch (Throwable $e) {
                error_log('Pendientes de ' . $clave . ': ' . $e->getMessage());
                continue;
            }
            if ($items) {
                $r[$clave] = ['nombre' => $nombres[$clave] ?? $clave, 'items' => $items];
            }
        }
        return $r;
    }

    /** Total para la insignia del menú; se recalcula como máximo cada 90 segundos por sesión. */
    public static function total(): int
    {
        $c = $_SESSION['_pendientes'] ?? null;
        if (is_array($c) && ($c['t'] ?? 0) > time() - 90 && ($c['u'] ?? 0) === (int) Auth::id()) {
            return (int) $c['n'];
        }
        $n = 0;
        foreach (self::agrupados() as $g) {
            $n += count($g['items']);
        }
        $_SESSION['_pendientes'] = ['t' => time(), 'u' => (int) Auth::id(), 'n' => $n];
        return $n;
    }
}
