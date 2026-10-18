<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Orquestador: carga configuración, descubre módulos y despacha la ruta.
 */
final class App
{
    private static array $config = [];

    public static function run(): void
    {
        self::cargarConfiguracion();

        self::enviarCabecerasSeguras();

        if (self::config('app.debug')) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        }

        // Fila conserva su protocolo público y la API Bearer del kiosco/reloj.
        if (\App\Modules\Fila\FilaGateway::dispatch()) return;

        $modulos = ModuleManager::descubrir();
        $router  = new Router();
        foreach ($modulos as $modulo) {
            $router->registrarModulo($modulo);
        }
        $router->despachar();
    }

    public static function cargarConfiguracion(): void
    {
        if (!self::$config) self::$config = require APP_RAIZ . '/config.php';
    }

    /** Acceso por punto: App::config('db.host') */
    public static function config(string $clave, mixed $defecto = null): mixed
    {
        $valor = self::$config;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $defecto;
            }
            $valor = $valor[$parte];
        }
        return is_string($valor) ? str_replace('UAEMéx', 'UAEMEX', $valor) : $valor;
    }

    private static function enviarCabecerasSeguras(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) return;
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=(), publickey-credentials-get=(self), publickey-credentials-create=(self)');
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'none'; form-action 'self'; img-src 'self' data: https:; font-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self' https://api.mail.hostinger.com https://integrate.api.nvidia.com https://generativelanguage.googleapis.com");
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        if ($https) header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}
