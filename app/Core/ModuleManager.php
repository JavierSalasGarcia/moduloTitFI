<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/** Descubre, valida y aísla módulos independientes. */
final class ModuleManager
{
    /** @var array<int,array<string,mixed>> */
    private static array $modulos = [];
    private static bool $descubiertos = false;
    /** @var array<string,string> */
    private static array $errores = [];

    public static function descubrir(): array
    {
        if (self::$descubiertos) return self::$modulos;
        self::$descubiertos = true;

        $directorios = glob(APP_RAIZ . '/Modules/*', GLOB_ONLYDIR) ?: [];
        sort($directorios, SORT_STRING);
        $claves = [];
        $rutas = [];

        foreach ($directorios as $dir) {
            $json = $dir . '/module.json';
            $php = $dir . '/module.php';
            if (!is_file($json) && !is_file($php)) continue;
            $origen = is_file($json) ? $json : $php;

            try {
                if (is_file($json)) {
                    $config = json_decode((string) file_get_contents($json), true, 32, JSON_THROW_ON_ERROR);
                    $formato = 'json';
                } else {
                    $config = require $php;
                    $formato = 'php-legacy';
                }
                if (!is_array($config)) throw new RuntimeException('El manifiesto debe ser un objeto/arreglo.');
                $config = self::validar($config, $dir, $formato, $claves, $rutas);
                self::$modulos[] = $config;
                $claves[$config['clave']] = true;
                foreach (array_keys($config['rutas']) as $patron) $rutas[strtoupper($patron)] = $config['clave'];
            } catch (Throwable $e) {
                $nombre = basename($dir);
                self::$errores[$nombre] = $e->getMessage();
                error_log("Módulo aislado {$nombre} ({$origen}): " . $e->getMessage());
            }
        }
        return self::$modulos;
    }

    /** @return array<string,string> */
    public static function errores(): array
    {
        self::descubrir();
        return self::$errores;
    }

    public static function dir(string $clave): ?string
    {
        foreach (self::descubrir() as $m) if ($m['clave'] === $clave) return $m['dir'];
        return null;
    }

    /** Menú agrupado por secciones y filtrado por el rol autenticado. */
    public static function menu(?string $rol): array
    {
        $secciones = [];
        foreach (self::descubrir() as $m) {
            foreach ($m['menu'] as $item) {
                if ($rol === null || !in_array($rol, $item['roles'], true)) continue;
                if (!empty($item['condicion']) && !self::cumple((string) $item['condicion'])) continue;
                $item['clave'] = $m['clave'];
                $sec = $item['seccion'];
                if (!isset($secciones[$sec])) $secciones[$sec] = ['orden' => $item['orden'], 'items' => []];
                $secciones[$sec]['orden'] = min($secciones[$sec]['orden'], $item['orden']);
                $secciones[$sec]['items'][] = $item;
            }
        }
        uasort($secciones, fn($a, $b) => $a['orden'] <=> $b['orden']);
        foreach ($secciones as &$seccion) usort($seccion['items'], fn($a, $b) => $a['orden'] <=> $b['orden']);
        unset($seccion);
        return $secciones;
    }

    /** Condición opcional de una entrada de menú: «Clase::metodo» de un módulo que devuelve bool. */
    private static function cumple(string $condicion): bool
    {
        if (!preg_match('/^App\\\\Modules\\\\[A-Za-z0-9_\\\\]+::[a-z][A-Za-z0-9_]*$/D', $condicion) || !is_callable($condicion)) return false;
        try { return (bool) $condicion(); } catch (Throwable $e) { error_log('Condición de menú fallida (' . $condicion . '): ' . $e->getMessage()); return false; }
    }

    private static function validar(array $config, string $dir, string $formato, array $claves, array $rutas): array
    {
        $clave = (string) ($config['clave'] ?? '');
        if (!preg_match('/^[a-z][a-z0-9_-]{1,39}$/D', $clave)) throw new RuntimeException('La clave del módulo no es válida.');
        if (isset($claves[$clave])) throw new RuntimeException("La clave '{$clave}' está duplicada.");
        if (isset($config['version']) && !preg_match('/^\d+\.\d+\.\d+$/D', (string) $config['version'])) {
            throw new RuntimeException('La versión debe usar formato semántico, por ejemplo 1.0.0.');
        }
        self::validarRoles($config['roles'] ?? [], 'roles del módulo');
        if (!isset($config['rutas']) || !is_array($config['rutas']) || !$config['rutas']) throw new RuntimeException('El módulo debe declarar al menos una ruta.');

        foreach ($config['rutas'] as $patron => $destino) {
            if (!is_string($patron) || !preg_match('#^(GET|POST|PUT|PATCH|DELETE) (?:/|(?:/[A-Za-z0-9._~-]+|/\{[a-z][A-Za-z0-9_]*\})+)$#D', $patron)) {
                throw new RuntimeException("Ruta inválida: {$patron}");
            }
            $identificador = strtoupper($patron);
            if (isset($rutas[$identificador])) throw new RuntimeException("La ruta {$patron} ya pertenece a {$rutas[$identificador]}.");
            if (!is_array($destino) || count($destino) < 2 || !is_string($destino[0]) || !is_string($destino[1])) throw new RuntimeException("Destino inválido para {$patron}.");
            if (!preg_match('/^App\\\\[A-Za-z0-9_\\\\]+Controller$/D', $destino[0]) || !preg_match('/^[a-z][A-Za-z0-9_]*$/D', $destino[1])) {
                throw new RuntimeException("Controlador o acción inválidos para {$patron}.");
            }
            if (isset($destino[2]) && $destino[2] !== 'publico') throw new RuntimeException("Marca de acceso inválida para {$patron}.");
            $archivoClase = APP_RAIZ . '/' . str_replace('\\', '/', substr($destino[0], 4)) . '.php';
            if (!is_file($archivoClase)) throw new RuntimeException("No existe el controlador de {$patron}.");
        }

        $menu = $config['menu'] ?? [];
        if (!is_array($menu)) throw new RuntimeException('menu debe ser un arreglo.');
        foreach ($menu as $i => &$item) {
            if (!is_array($item)) throw new RuntimeException("La entrada de menú {$i} no es válida.");
            foreach (['seccion', 'titulo', 'ruta'] as $campo) {
                if (!isset($item[$campo]) || !is_string($item[$campo]) || ($campo !== 'ruta' && trim($item[$campo]) === '')) {
                    throw new RuntimeException("Falta {$campo} en la entrada de menú {$i}.");
                }
            }
            if (str_starts_with($item['ruta'], '/') || str_contains($item['ruta'], '..')) throw new RuntimeException("Ruta de menú insegura en {$i}.");
            if (isset($item['condicion']) && (!is_string($item['condicion']) || !preg_match('/^App\\\\Modules\\\\[A-Za-z0-9_\\\\]+::[a-z][A-Za-z0-9_]*$/D', $item['condicion']))) {
                throw new RuntimeException("Condición de menú inválida en {$i}.");
            }
            self::validarRoles($item['roles'] ?? [], "roles del menú {$i}");
            $item['roles'] = array_values($item['roles'] ?? []);
            $item['orden'] = isset($item['orden']) ? (int) $item['orden'] : 999;
        }
        unset($item);

        $config['dir'] = $dir;
        $config['formato_manifiesto'] = $formato;
        $config['menu'] = $menu;
        $config['roles'] = array_values($config['roles'] ?? []);
        $config['publico'] = (bool) ($config['publico'] ?? false);
        return $config;
    }

    private static function validarRoles(mixed $roles, string $contexto): void
    {
        if (!is_array($roles)) throw new RuntimeException("{$contexto} debe ser un arreglo.");
        foreach ($roles as $rol) {
            if (!is_string($rol) || !Roles::valido($rol)) throw new RuntimeException("Rol desconocido '{$rol}' en {$contexto}.");
        }
    }
}
