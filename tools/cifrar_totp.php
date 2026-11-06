<?php
declare(strict_types=1);

/**
 * Cifra los secretos TOTP existentes en usuarios.totp_secret (docs/CIFRADO_DE_DATOS.md). Es idempotente.
 *
 *   php tools/cifrar_totp.php               Cifra los secretos que aún están en texto plano.
 *   php tools/cifrar_totp.php --simular     Sólo cuenta; no escribe nada.
 *   php tools/cifrar_totp.php --recifrar    Además re-cifra los ya cifrados con otra llave para pasarlos a la activa
 *                                           (tras una rotación con tools/cifrado_llave.php --rotar).
 *
 * Requisitos: migración 042 aplicada (columna de 255) y llave configurada. Haz un respaldo de la base antes de ejecutarlo.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('APP_RAIZ', dirname(__DIR__) . '/app');
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'App\\')) { $r = APP_RAIZ . '/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($r)) require $r; }
});

use App\Core\App;
use App\Core\Cifrado;
use App\Core\Database;
use App\Core\Totp;

App::cargarConfiguracion();
$simular = in_array('--simular', $argv, true);
$recifrar = in_array('--recifrar', $argv, true);

if (!Cifrado::disponible()) {
    fwrite(STDERR, "No hay llave de cifrado. Ejecuta php tools/cifrado_llave.php o define TRAZAFI_CIFRADO_LLAVE.\n");
    exit(1);
}
$ancho = (int) Database::valor(
    "SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'totp_secret'"
);
if ($ancho < 255) {
    fwrite(STDERR, "usuarios.totp_secret mide $ancho caracteres: aplica antes database/migrations/042_cifrado_totp.sql.\n");
    exit(1);
}

$filas = Database::todas("SELECT id, totp_secret FROM usuarios WHERE totp_secret IS NOT NULL AND totp_secret <> ''");
$nuevos = $recifrados = $iguales = $fallos = 0;
foreach ($filas as $f) {
    $id = (int) $f['id'];
    $actual = (string) $f['totp_secret'];
    $cifrado = Cifrado::estaCifrado($actual);
    if ($cifrado && !$recifrar) { $iguales++; continue; }
    try {
        $claro = Totp::desdeAlmacenado($actual, $id);
        $nuevo = Totp::paraGuardar($claro, $id);
        // Comprobación de ida y vuelta antes de escribir: nunca se guarda algo que no se pueda leer de vuelta.
        if (Totp::desdeAlmacenado($nuevo, $id) !== $claro) { throw new RuntimeException('El valor no se descifra de vuelta.'); }
        if ($cifrado && explode(':', $nuevo, 4)[2] === explode(':', $actual, 4)[2]) { $iguales++; continue; }
        if (!$simular) {
            Database::ejecutar('UPDATE usuarios SET totp_secret = ? WHERE id = ? AND totp_secret = ?', [$nuevo, $id, $actual]);
        }
        $cifrado ? $recifrados++ : $nuevos++;
    } catch (Throwable $e) {
        $fallos++;
        fwrite(STDERR, "Usuario $id: " . $e->getMessage() . "\n");
    }
}
echo ($simular ? '[simulación] ' : '') . "Cifrados: $nuevos · re-cifrados: $recifrados · sin cambio: $iguales · fallos: $fallos\n";
exit($fallos > 0 ? 2 : 0);
