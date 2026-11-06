<?php
declare(strict_types=1);

/**
 * Crea o rota la llave de cifrado de datos en reposo (docs/CIFRADO_DE_DATOS.md).
 *
 *   php tools/cifrado_llave.php            Crea storage/secrets/cifrado.json con la llave «k1» (no sobrescribe).
 *   php tools/cifrado_llave.php --rotar    Agrega una llave nueva y la marca activa; conserva las anteriores para descifrar.
 *   php tools/cifrado_llave.php --generar  Sólo imprime una llave nueva (para TRAZAFI_CIFRADO_LLAVE).
 *
 * La llave se muestra sólo con --generar. Respalda el archivo creado en un gestor de contraseñas o en un
 * lugar distinto al respaldo de la base de datos: sin la llave, los datos cifrados no se pueden recuperar.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('APP_RAIZ', dirname(__DIR__) . '/app');
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'App\\')) { $r = APP_RAIZ . '/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($r)) require $r; }
});

use App\Core\App;
use App\Core\Cifrado;

App::cargarConfiguracion();
$opcion = $argv[1] ?? '';

if ($opcion === '--generar') {
    echo Cifrado::generarLlave() . "\n";
    exit(0);
}

$archivo = (string) App::config('cifrado.archivo');
$dir = dirname($archivo);
$datos = ['activa' => '', 'llaves' => []];
if (is_file($archivo)) {
    $actual = json_decode((string) file_get_contents($archivo), true);
    if (!is_array($actual)) { fwrite(STDERR, "El archivo $archivo no es JSON válido.\n"); exit(1); }
    $datos = $actual + $datos;
}

if ($opcion === '' && $datos['llaves']) {
    fwrite(STDERR, "Ya existe $archivo. Usa --rotar para agregar una llave nueva; no se sobrescribe la actual.\n");
    exit(1);
}
if ($opcion === '--rotar' && !$datos['llaves']) {
    fwrite(STDERR, "No hay llaves que rotar. Ejecuta primero el comando sin opciones.\n");
    exit(1);
}
if (!in_array($opcion, ['', '--rotar'], true)) { fwrite(STDERR, "Opción desconocida: $opcion\n"); exit(1); }

$id = 'k' . (count($datos['llaves']) + 1);
$datos['llaves'][$id] = Cifrado::generarLlave();
$datos['activa'] = $id;

if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) { fwrite(STDERR, "No se pudo crear $dir\n"); exit(1); }
$tmp = $archivo . '.tmp';
if (file_put_contents($tmp, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n", LOCK_EX) === false) { fwrite(STDERR, "No se pudo escribir la llave.\n"); exit(1); }
chmod($tmp, 0600);
rename($tmp, $archivo);

echo "Llave «{$id}» activa en $archivo (permisos 0600).\n";
echo $opcion === '--rotar'
    ? "Vuelve a cifrar los datos con la llave nueva: php tools/cifrar_totp.php --recifrar\n"
    : "Respalda este archivo fuera del servidor y fuera del respaldo de la base. Después: php tools/cifrar_totp.php\n";
