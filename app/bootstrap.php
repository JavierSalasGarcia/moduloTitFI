<?php
/**
 * Bootstrap: autoloader PSR-4 (App\ → app/), sesión y helpers.
 */
declare(strict_types=1);

define('APP_RAIZ', __DIR__);

// Hora local de la Facultad (los sellos de firma y fechas de la BD
// deben coincidir; sin esto PHP usa UTC cuando php.ini no fija zona).
date_default_timezone_set('America/Mexico_City');

spl_autoload_register(function (string $clase): void {
    if (str_starts_with($clase, 'App\\')) {
        $ruta = APP_RAIZ . '/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
        if (is_file($ruta)) {
            require $ruta;
        }
    }
});

require APP_RAIZ . '/Core/helpers.php';

$sessionBase = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/\\');
if ($sessionBase !== '') {
    // Evita que un clon en /pruebas comparta la sesion de produccion.
    session_name('TRAZAFI_' . strtoupper(substr(hash('sha256', $sessionBase), 0, 12)));
}
$https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => $https,
    'cookie_samesite' => 'Lax',
    'cookie_path' => $sessionBase !== '' ? $sessionBase . '/' : '/',
]);
