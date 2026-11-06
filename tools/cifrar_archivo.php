<?php
declare(strict_types=1);

/**
 * Cifra y descifra archivos de respaldo (volcados SQL, ZIP) con una frase de contraseña (docs/CIFRADO_DE_DATOS.md).
 *
 *   php tools/cifrar_archivo.php cifrar    respaldo.zip            → respaldo.zip.tzfi  (conserva el original)
 *   php tools/cifrar_archivo.php cifrar    respaldo.zip --borrar   → cifra, verifica leyendo de vuelta y borra el original
 *   php tools/cifrar_archivo.php descifrar respaldo.zip.tzfi       → respaldo.zip       (no sobrescribe)
 *   php tools/cifrar_archivo.php verificar respaldo.zip.tzfi       → comprueba frase e integridad sin escribir nada
 *
 * La frase se toma de la variable TRAZAFI_BACKUP_FRASE o, si hay terminal, se pide sin mostrarla. Es distinta de la
 * llave de la aplicación a propósito: quien administra los respaldos no necesita (ni debe tener) la llave del servidor.
 *
 * Formato: «TZFI-ENC1» ‖ sal(16) ‖ ops(4) ‖ mem(4) ‖ cabecera secretstream(24) ‖ bloques [longitud(4) ‖ cifrado].
 * Llave derivada con Argon2id (libsodium); bloques de 64 KiB con XChaCha20-Poly1305 en modo secretstream, que detecta
 * archivos truncados, reordenados o alterados. Funciona en streaming: no carga el archivo completo en memoria.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const MAGIA = 'TZFI-ENC1';
const BLOQUE = 65536;
const EXT = '.tzfi';

function salir(string $m, int $c = 1): never { fwrite(STDERR, $m . "\n"); exit($c); }

function frase(bool $confirmar): string
{
    $f = getenv('TRAZAFI_BACKUP_FRASE');
    if (is_string($f) && $f !== '') return $f;
    if (!function_exists('posix_isatty') || !posix_isatty(STDIN)) salir('Define TRAZAFI_BACKUP_FRASE (no hay terminal para pedirla).');
    $pedir = function (string $txt): string {
        fwrite(STDERR, $txt);
        shell_exec('stty -echo 2>/dev/null');
        $l = rtrim((string) fgets(STDIN), "\r\n");
        shell_exec('stty echo 2>/dev/null');
        fwrite(STDERR, "\n");
        return $l;
    };
    $a = $pedir('Frase de contraseña: ');
    if ($confirmar && $a !== $pedir('Repite la frase: ')) salir('Las frases no coinciden.');
    if ($a === '') salir('La frase no puede estar vacía.');
    return $a;
}

function derivar(string $frase, string $sal, int $ops, int $mem): string
{
    return sodium_crypto_pwhash(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES, $frase, $sal, $ops, $mem, SODIUM_CRYPTO_PWHASH_ALG_ARGON2ID13);
}

function cifrar(string $origen, string $destino, string $frase): void
{
    if (file_exists($destino)) salir("Ya existe $destino; no se sobrescribe.");
    $in = fopen($origen, 'rb') ?: salir("No se pudo abrir $origen");
    $tmp = $destino . '.parcial';
    $out = fopen($tmp, 'wb') ?: salir("No se pudo crear $tmp");
    chmod($tmp, 0600);
    $sal = random_bytes(SODIUM_CRYPTO_PWHASH_SALTBYTES);
    $ops = SODIUM_CRYPTO_PWHASH_OPSLIMIT_MODERATE;
    $mem = SODIUM_CRYPTO_PWHASH_MEMLIMIT_MODERATE;
    $llave = derivar($frase, $sal, $ops, $mem);
    [$estado, $cabecera] = sodium_crypto_secretstream_xchacha20poly1305_init_push($llave);
    $enc = MAGIA . "\0";
    fwrite($out, $enc . $sal . pack('NN', $ops, intdiv($mem, 1024)) . $cabecera);
    // El primer bloque se adelanta para saber cuál es el último (etiqueta FINAL).
    $actual = fread($in, BLOQUE);
    while (true) {
        $sig = $actual === '' || strlen($actual) < BLOQUE ? '' : fread($in, BLOQUE);
        $ultimo = $sig === '' || $sig === false;
        $c = sodium_crypto_secretstream_xchacha20poly1305_push(
            $estado, (string) $actual, '', $ultimo ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
        );
        fwrite($out, pack('N', strlen($c)) . $c);
        if ($ultimo) break;
        $actual = $sig;
    }
    fclose($in);
    fclose($out);
    sodium_memzero($llave);
    rename($tmp, $destino);
}

/** Descifra a $destino (o sólo verifica si es null). Devuelve el número de bytes en claro. */
function descifrar(string $origen, ?string $destino, string $frase): int
{
    if ($destino !== null && file_exists($destino)) salir("Ya existe $destino; no se sobrescribe.");
    $in = fopen($origen, 'rb') ?: salir("No se pudo abrir $origen");
    $enc = MAGIA . "\0";
    $cab = fread($in, strlen($enc) + SODIUM_CRYPTO_PWHASH_SALTBYTES + 8 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
    if ($cab === false || !str_starts_with($cab, $enc)) salir('El archivo no tiene el formato de cifrado de Traza FI.');
    $p = strlen($enc);
    $sal = substr($cab, $p, SODIUM_CRYPTO_PWHASH_SALTBYTES); $p += SODIUM_CRYPTO_PWHASH_SALTBYTES;
    $n = unpack('Nops/Nmem', substr($cab, $p, 8)); $p += 8;
    if ($n['ops'] < 1 || $n['ops'] > 20 || $n['mem'] < 8192 || $n['mem'] > 4194304) salir('Parámetros de derivación fuera de rango.');
    $cabecera = substr($cab, $p);
    $llave = derivar($frase, $sal, $n['ops'], $n['mem'] * 1024);
    try { $estado = sodium_crypto_secretstream_xchacha20poly1305_init_pull($cabecera, $llave); }
    catch (SodiumException) { salir('Cabecera inválida.'); }
    sodium_memzero($llave);
    $tmp = $destino === null ? null : $destino . '.parcial';
    $out = $tmp === null ? null : (fopen($tmp, 'wb') ?: salir("No se pudo crear $tmp"));
    if ($tmp !== null) chmod($tmp, 0600);
    $total = 0; $final = false;
    while (!feof($in)) {
        $l = fread($in, 4);
        if ($l === '' || $l === false) break;
        if (strlen($l) !== 4) { $final = false; break; }
        $largo = unpack('N', $l)[1];
        if ($largo < SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $largo > BLOQUE + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) { $final = false; break; }
        $c = fread($in, $largo);
        $r = $c === false || strlen($c) !== $largo ? false : sodium_crypto_secretstream_xchacha20poly1305_pull($estado, $c);
        if ($r === false) {
            if ($tmp !== null) { fclose($out); unlink($tmp); }
            salir('Frase incorrecta o archivo alterado.', 3);
        }
        [$claro, $etiqueta] = $r;
        $total += strlen($claro);
        if ($out) fwrite($out, $claro);
        if ($etiqueta === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) { $final = true; break; }
    }
    fclose($in);
    if (!$final) {
        if ($tmp !== null) { fclose($out); unlink($tmp); }
        salir('El archivo está incompleto o truncado.', 3);
    }
    if ($out) { fclose($out); rename($tmp, $destino); }
    return $total;
}

$modo = $argv[1] ?? '';
$ruta = $argv[2] ?? '';
if (!in_array($modo, ['cifrar', 'descifrar', 'verificar'], true) || $ruta === '' || !is_file($ruta)) {
    salir("Uso: php tools/cifrar_archivo.php cifrar|descifrar|verificar <archivo> [--borrar]");
}

if ($modo === 'cifrar') {
    if (str_ends_with($ruta, EXT)) salir('El archivo ya tiene la extensión ' . EXT . '.');
    $f = frase(true);
    $destino = $ruta . EXT;
    cifrar($ruta, $destino, $f);
    $bytes = descifrar($destino, null, $f); // lectura de verificación
    if ($bytes !== filesize($ruta)) { unlink($destino); salir('La verificación de lectura falló; se descartó el cifrado.', 3); }
    if (in_array('--borrar', $argv, true)) { unlink($ruta); echo "Original eliminado.\n"; }
    echo "Cifrado y verificado: $destino\n";
} elseif ($modo === 'descifrar') {
    $destino = str_ends_with($ruta, EXT) ? substr($ruta, 0, -strlen(EXT)) : $ruta . '.descifrado';
    $bytes = descifrar($ruta, $destino, frase(false));
    echo "Descifrado ($bytes bytes): $destino\n";
} else {
    $bytes = descifrar($ruta, null, frase(false));
    echo "Correcto: frase válida e integridad verificada ($bytes bytes).\n";
}
