<?php
declare(strict_types=1);
namespace App\Modules\Fila;

use App\Core\{Auth, AccessControl};

final class FilaGateway
{
    public static function load(): void
    {
        require_once dirname(APP_RAIZ) . '/fila/publico/lib/datos.php';
        require_once dirname(APP_RAIZ) . '/fila/publico/lib/plantilla.php';
        if (($GLOBALS['_config']['integrado'] ?? false) === true) return;
        $values = [];
        $credentialsPath = dirname(APP_RAIZ) . '/fila/credenciales.txt';
        if (!is_file($credentialsPath)) {
            throw new \RuntimeException('Falta la configuración privada de Fila.');
        }
        foreach (file($credentialsPath, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/^(base de datos|usuario|password)\s*:\s*(.+)$/iu', trim($line), $m)) $values[mb_strtolower($m[1])] = trim($m[2]);
        }
        foreach (['base de datos','usuario','password'] as $key) {
            if (empty($values[$key])) throw new \RuntimeException('Falta la configuración privada de Fila.');
        }
        $cfg = require dirname(APP_RAIZ).'/fila/publico/config.example.php';
        $cfg['db'] = ['dsn'=>'mysql:host=localhost;dbname='.$values['base de datos'].';charset=utf8mb4',
            'usuario'=>$values['usuario'], 'clave'=>$values['password']];
        $cfg['base_url'] = rtrim(url('fila/publico'), '/');
        $cfg['sitio'] = rtrim((string) \App\Core\App::config('app.url_publica', 'https://fingenieria.mx'), '/')
            . '/fila/publico';
        $cfg['carpeta_subidas'] = dirname(APP_RAIZ).'/storage/fila/subidas';
        $cfg['correo']['modo'] = 'traza';
        $cfg['integrado'] = true;
        config_usar($cfg);
    }

    public static function dispatch(): bool
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
        $prefix = rtrim(url('fila/publico'), '/');
        if ($path !== $prefix && !str_starts_with($path, $prefix.'/')) return false;
        $route = trim(substr($path, strlen($prefix)), '/');
        header('Cache-Control: no-store');
        if (in_array($route, ['estatico/estilo.css','estatico/kiosco.css','estatico/identidad.js'], true)) {
            header('Content-Type: '.(str_ends_with($route,'.js')?'application/javascript':'text/css').'; charset=utf-8');
            readfile(dirname(APP_RAIZ).'/fila/publico/'.$route);
            return true;
        }
        try {
            self::load();
        } catch (\RuntimeException $error) {
            http_response_code(503);
            echo 'El módulo Fila no está configurado en este entorno.';
            return true;
        }
        // No se admite el antiguo login de Fila ni privilegios guardados en sesión.
        if (explode('/', $route)[0] === 'admin') {
            AccessControl::refresh();
            if (!Auth::usuario()) redirigir('login');
            if (!Auth::tieneRol('admin') || isset($_SESSION['impersonador']) || AccessControl::pending()) {
                http_response_code(403); echo 'Acceso restringido.'; return true;
            }
        }
        $_GET['r'] = $route; // Nunca confiar en ?r= para saltarse el control anterior.
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && explode('/', $route)[0] !== 'api' && !csrf_valido()) {
            http_response_code(419); echo 'Formulario vencido. Vuelve a abrir la página.'; return true;
        }
        require dirname(APP_RAIZ).'/fila/publico/index.php';
        return true;
    }
}
