<?php
declare(strict_types=1);

define('APP_RAIZ', dirname(__DIR__) . '/app');
spl_autoload_register(static function (string $clase): void {
    if (!str_starts_with($clase, 'App\\')) return;
    $ruta = APP_RAIZ . '/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
    if (is_file($ruta)) require $ruta;
});

\App\Core\App::cargarConfiguracion();
$modulos = \App\Core\ModuleManager::descubrir();
$errores = \App\Core\ModuleManager::errores();

foreach ($modulos as $modulo) {
    echo "OK  {$modulo['clave']} ({$modulo['formato_manifiesto']})\n";
}
foreach ($errores as $modulo => $error) {
    fwrite(STDERR, "ERROR  {$modulo}: {$error}\n");
}
echo count($modulos) . " módulo(s) válidos; " . count($errores) . " aislado(s).\n";
exit($errores ? 1 : 0);
