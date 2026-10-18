<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Vistas PHP planas con layout. Busca primero en app/Views/ y después
 * en las carpetas views/ de los módulos.
 */
final class View
{
    public static function render(string $vista, array $datos = [], string $layout = 'app'): void
    {
        $datos['contenido'] = self::capturar($vista, $datos);
        extract($datos, EXTR_SKIP);
        require APP_RAIZ . "/Views/layouts/{$layout}.php";
    }

    public static function capturar(string $vista, array $datos = []): string
    {
        $archivo = self::resolver($vista);
        extract($datos, EXTR_SKIP);
        ob_start();
        require $archivo;
        return ob_get_clean();
    }

    /**
     * "clave:vista" busca dentro del módulo con esa clave;
     * "carpeta/vista" busca en app/Views/.
     */
    private static function resolver(string $vista): string
    {
        if (str_contains($vista, ':')) {
            [$clave, $nombre] = explode(':', $vista, 2);
            $dir = ModuleManager::dir($clave);
            $archivo = $dir ? "{$dir}/views/{$nombre}.php" : '';
        } else {
            $archivo = APP_RAIZ . "/Views/{$vista}.php";
        }
        if ($archivo && is_file($archivo)) {
            return $archivo;
        }
        throw new RuntimeException("Vista no encontrada: {$vista}");
    }
}
