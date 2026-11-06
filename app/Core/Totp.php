<?php
declare(strict_types=1);

namespace App\Core;

final class Totp
{
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** Marca y cuenta coherentes para las aplicaciones Authenticator. */
    public static function uri(string $email, string $secreto): string
    {
        $issuer = 'Traza FI';
        return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email)
            . '?' . http_build_query(['secret'=>$secreto, 'issuer'=>$issuer], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Genera un secreto aleatorio Base32 de 16 caracteres.
     */
    public static function generarSecreto(): string
    {
        $secreto = '';
        for ($i = 0; $i < 16; $i++) {
            $secreto .= self::$base32Chars[random_int(0, 31)];
        }
        return $secreto;
    }

    /** Contexto de cifrado del secreto: ligado a la columna y al usuario para que no se pueda mover entre cuentas. */
    private static function contexto(int $usuarioId): string
    {
        return 'usuarios.totp_secret#' . $usuarioId;
    }

    /** Valor que se guarda en usuarios.totp_secret (cifrado si hay llave configurada; ver docs/CIFRADO_DE_DATOS.md). */
    public static function paraGuardar(string $secreto, int $usuarioId): string
    {
        return (string) Cifrado::cifrar($secreto, self::contexto($usuarioId));
    }

    /** Secreto Base32 en claro a partir de lo almacenado (acepta texto plano heredado). */
    public static function desdeAlmacenado(string $almacenado, int $usuarioId): string
    {
        return (string) Cifrado::descifrar($almacenado, self::contexto($usuarioId));
    }

    /**
     * Verifica un código contra el valor de usuarios.totp_secret tal como está en la base (cifrado o heredado).
     * Si no se puede descifrar (llave ausente o dato alterado) devuelve false: nunca deja pasar.
     */
    public static function verificarAlmacenado(string $almacenado, int $usuarioId, string $codigo): bool
    {
        try {
            return self::verificar(self::desdeAlmacenado($almacenado, $usuarioId), $codigo);
        } catch (\Throwable $e) {
            error_log('TOTP: no se pudo descifrar el secreto del usuario ' . $usuarioId . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Verifica si un código de 6 dígitos es válido para un secreto Base32.
     * Soporta un margen de tolerancia de 1 ventana hacia atrás/adelante (total 3 ventanas, 90 seg).
     */
    public static function verificar(string $secreto, string $codigo): bool
    {
        $codigo = trim($codigo);
        if (strlen($codigo) !== 6 || !is_numeric($codigo)) {
            return false;
        }

        try {
            $secretoBinario = self::base32Decode($secreto);
        } catch (\Throwable $e) {
            return false;
        }
        
        $timeWindow = (int) floor(time() / 30);

        // Validar ventana actual, anterior y siguiente
        for ($i = -1; $i <= 1; $i++) {
            if (self::calcularCodigo($secretoBinario, $timeWindow + $i) === $codigo) {
                return true;
            }
        }

        return false;
    }

    private static function calcularCodigo(string $secretoBinario, int $timeIndex): string
    {
        // Empacar el índice de tiempo en 8 bytes big-endian
        $timeBinary = pack('N*', 0, $timeIndex);
        
        // Calcular HMAC-SHA1
        $hash = hash_hmac('sha1', $timeBinary, $secretoBinario, true);
        
        // Obtener desplazamiento (offset) dinámico
        $offset = ord($hash[19]) & 0xf;
        
        // Extraer número de 4 bytes
        $truncatedHash = (
            (ord($hash[$offset]) & 0x7f) << 24 |
            (ord($hash[$offset + 1]) & 0xff) << 16 |
            (ord($hash[$offset + 2]) & 0xff) << 8 |
            (ord($hash[$offset + 3]) & 0xff)
        );
        
        // Obtener código de 6 dígitos
        $otp = $truncatedHash % 1000000;
        
        return str_pad((string) $otp, 6, '0', STR_PAD_LEFT);
    }

    private static function base32Decode(string $base32): string
    {
        $base32 = strtoupper(trim($base32));
        $base32 = str_replace('=', '', $base32);
        
        $binario = '';
        $longitud = strlen($base32);
        
        for ($i = 0; $i < $longitud; $i++) {
            $val = strpos(self::$base32Chars, $base32[$i]);
            if ($val === false) {
                continue;
            }
            $binario .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
        }
        
        $decodificado = '';
        $bloques = str_split($binario, 8);
        foreach ($bloques as $bloque) {
            if (strlen($bloque) < 8) {
                break;
            }
            $decodificado .= chr(bindec($bloque));
        }
        
        return $decodificado;
    }
}
