<?php
/**
 * Autoloader PSR-4 mínimo para FPDI (setasign\Fpdi\ -> src/).
 * Sustituye al autoload.php del paquete "FPDI without Composer";
 * el zip de GitHub solo trae src/. Requiere fpdf.php cargado antes.
 */
spl_autoload_register(static function (string $clase): void {
    $prefijo = 'setasign\\Fpdi\\';
    if (!str_starts_with($clase, $prefijo)) {
        return;
    }
    $ruta = __DIR__ . '/src/' . str_replace('\\', '/', substr($clase, strlen($prefijo))) . '.php';
    if (is_file($ruta)) {
        require $ruta;
    }
});
