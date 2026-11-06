<?php
declare(strict_types=1);

/** Pruebas del cifrado en reposo (sin base de datos): php tools/test_cifrado.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('APP_RAIZ', dirname(__DIR__) . '/app');
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'App\\')) { $r = APP_RAIZ . '/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($r)) require $r; }
});

use App\Core\Cifrado;
use App\Core\Totp;

$fallos = 0;
function prueba(string $nombre, bool $ok): void
{
    global $fallos;
    echo ($ok ? 'ok    ' : 'FALLA ') . $nombre . "\n";
    $fallos += $ok ? 0 : 1;
}
function lanza(callable $f): bool { try { $f(); return false; } catch (Throwable) { return true; } }

$k1 = Cifrado::generarLlave();
$k2 = Cifrado::generarLlave();

// Sin llave: texto plano (modo transitorio).
Cifrado::reiniciar(['activa' => '', 'llaves' => []]);
prueba('sin llave: cifrar devuelve el texto tal cual', Cifrado::cifrar('ABC', 'ctx') === 'ABC');
prueba('sin llave: disponible() es false', !Cifrado::disponible());

// Con llave.
Cifrado::reiniciar(['activa' => 'k1', 'llaves' => ['k1' => $k1]]);
$c = Cifrado::cifrar('JBSWY3DPEHPK3PXP', 'usuarios.totp_secret#7');
prueba('el valor cifrado lleva prefijo y no contiene el secreto', str_starts_with($c, 'enc:v1:k1:') && !str_contains($c, 'JBSWY3DPEHPK3PXP'));
prueba('cabe en VARCHAR(255)', strlen($c) < 255);
prueba('ida y vuelta', Cifrado::descifrar($c, 'usuarios.totp_secret#7') === 'JBSWY3DPEHPK3PXP');
prueba('dos cifrados del mismo valor difieren (nonce aleatorio)', $c !== Cifrado::cifrar('JBSWY3DPEHPK3PXP', 'usuarios.totp_secret#7'));
prueba('no se re-cifra un valor ya cifrado', Cifrado::cifrar($c, 'usuarios.totp_secret#7') === $c);
prueba('otro contexto (otro usuario) no descifra', lanza(fn() => Cifrado::descifrar($c, 'usuarios.totp_secret#8')));
$alterado = substr($c, 0, -3) . (substr($c, -3) === 'AAA' ? 'BBB' : 'AAA');
prueba('un valor alterado no descifra', lanza(fn() => Cifrado::descifrar($alterado, 'usuarios.totp_secret#7')));
prueba('texto plano heredado se acepta', Cifrado::descifrar('ABC', 'x') === 'ABC');
prueba('NULL y vacío se conservan', Cifrado::cifrar(null, 'x') === null && Cifrado::cifrar('', 'x') === '');

// Rotación: se cifra con la activa y se descifra con la que indique el valor.
Cifrado::reiniciar(['activa' => 'k2', 'llaves' => ['k1' => $k1, 'k2' => $k2]]);
prueba('tras rotar, lo cifrado con k1 sigue legible', Cifrado::descifrar($c, 'usuarios.totp_secret#7') === 'JBSWY3DPEHPK3PXP');
$c2 = Cifrado::cifrar('JBSWY3DPEHPK3PXP', 'usuarios.totp_secret#7');
prueba('tras rotar, lo nuevo usa k2', str_starts_with($c2, 'enc:v1:k2:'));
Cifrado::reiniciar(['activa' => 'k2', 'llaves' => ['k2' => $k2]]);
prueba('sin la llave original no se descifra', lanza(fn() => Cifrado::descifrar($c, 'usuarios.totp_secret#7')));

// Índice ciego.
Cifrado::reiniciar(['activa' => 'k1', 'llaves' => ['k1' => $k1]]);
$i1 = Cifrado::indiceCiego(' Ana@UAEMEX.mx ', 'usuarios.email');
prueba('índice ciego determinista y normalizado', $i1 === Cifrado::indiceCiego('ana@uaemex.mx', 'usuarios.email') && strlen($i1) === 64);
prueba('índice ciego depende del contexto', $i1 !== Cifrado::indiceCiego('ana@uaemex.mx', 'otra.columna'));

// TOTP almacenado: verifica el código real contra el secreto cifrado.
$secreto = Totp::generarSecreto();
$guardado = Totp::paraGuardar($secreto, 42);
$ref = new ReflectionMethod(Totp::class, 'calcularCodigo');
$ref->setAccessible(true);
$bin = (new ReflectionMethod(Totp::class, 'base32Decode'))->invoke(null, $secreto);
$codigo = $ref->invoke(null, $bin, (int) floor(time() / 30));
prueba('TOTP: el secreto guardado está cifrado', Cifrado::estaCifrado($guardado));
prueba('TOTP: código válido con secreto cifrado', Totp::verificarAlmacenado($guardado, 42, $codigo));
prueba('TOTP: código válido con secreto heredado en claro', Totp::verificarAlmacenado($secreto, 42, $codigo));
prueba('TOTP: el secreto de otro usuario no sirve', !Totp::verificarAlmacenado($guardado, 43, $codigo));
prueba('TOTP: sin llave no deja pasar', (function () use ($guardado, $codigo) {
    Cifrado::reiniciar(['activa' => '', 'llaves' => []]);
    return !Totp::verificarAlmacenado($guardado, 42, $codigo);
})());

// Archivos de respaldo.
$dir = sys_get_temp_dir() . '/tzfi-' . bin2hex(random_bytes(4));
mkdir($dir);
$herr = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/cifrar_archivo.php');
$ejecutar = function (string $args, string $frase) use ($herr): array {
    $cmd = 'TRAZAFI_BACKUP_FRASE=' . escapeshellarg($frase) . ' ' . $herr . ' ' . $args . ' 2>&1';
    exec($cmd, $salida, $codigo);
    return [$codigo, implode("\n", $salida)];
};
foreach ([0, 1, 65535, 65536, 65537, 200000] as $tam) {
    $f = "$dir/r$tam.bin";
    file_put_contents($f, $tam > 0 ? random_bytes($tam) : "");
    [$cod] = $ejecutar('cifrar ' . escapeshellarg($f), 'frase-de-prueba');
    $ok = $cod === 0 && is_file("$f.tzfi") && !str_contains((string) file_get_contents("$f.tzfi"), substr((string) file_get_contents($f), 0, 32) ?: "\x00impossible");
    rename($f, "$f.orig");
    [$cod2] = $ejecutar('descifrar ' . escapeshellarg("$f.tzfi"), 'frase-de-prueba');
    prueba("archivo de $tam bytes: cifra y descifra idéntico", $ok && $cod2 === 0 && file_get_contents($f) === file_get_contents("$f.orig"));
}
[$cod] = $ejecutar('verificar ' . escapeshellarg("$dir/r200000.bin.tzfi"), 'otra-frase');
prueba('frase incorrecta se rechaza', $cod === 3);
$datos = file_get_contents("$dir/r200000.bin.tzfi");
file_put_contents("$dir/trunc.tzfi", substr($datos, 0, strlen($datos) - 40000));
[$cod] = $ejecutar('verificar ' . escapeshellarg("$dir/trunc.tzfi"), 'frase-de-prueba');
prueba('archivo truncado se detecta', $cod === 3);
$mal = $datos; $mal[strlen($mal) - 100] = $mal[strlen($mal) - 100] ^ "\x01";
file_put_contents("$dir/mal.tzfi", $mal);
[$cod] = $ejecutar('verificar ' . escapeshellarg("$dir/mal.tzfi"), 'frase-de-prueba');
prueba('archivo alterado se detecta', $cod === 3);
[$cod] = $ejecutar('cifrar ' . escapeshellarg("$dir/r0.bin.orig") . ' --borrar', 'x');
prueba('--borrar elimina el original sólo tras verificar', $cod === 0 && !is_file("$dir/r0.bin.orig") && is_file("$dir/r0.bin.orig.tzfi"));

exec('rm -rf ' . escapeshellarg($dir));
echo $fallos === 0 ? "\nTodas las pruebas pasaron.\n" : "\n$fallos prueba(s) fallaron.\n";
exit($fallos === 0 ? 0 : 1);
