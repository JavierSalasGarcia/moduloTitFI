<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Cifrado de campos sensibles en reposo (docs/CIFRADO_DE_DATOS.md).
 *
 * Protege el contenido de columnas concretas aunque alguien obtenga un volcado de la base de datos: la llave vive fuera
 * de MySQL (variable de entorno o archivo en storage/secrets/, bloqueado por HTTP y no versionado).
 *
 *  - Algoritmo: AES-256-GCM (ext-openssl, presente en la web y en la consola). Autenticado: un valor alterado no se
 *    descifra. Los valores «enc:v1:» (XChaCha20-Poly1305, ext-sodium) se siguen leyendo donde sodium esté instalado;
 *    no se escriben más porque el PHP de la web del servidor no tiene esa extensión.
 *  - El «contexto» (p. ej. «usuarios.totp_secret») se liga como dato asociado: un texto cifrado copiado a otra columna
 *    o a otro registro de contexto distinto no se descifra.
 *  - Formato almacenado: «enc:v2:{id_llave}:{base64url(nonce ‖ cifrado ‖ etiqueta)}». El id de llave permite rotarlas sin cifrar
 *    todo de golpe: se cifra con la activa y se descifra con la que indique el valor.
 *  - Los valores sin prefijo «enc:» se tratan como texto plano heredado; así la migración puede ser gradual.
 */
final class Cifrado
{
    /** Formato anterior (XChaCha20-Poly1305 con ext-sodium): solo lectura. */
    public const PREFIJO = 'enc:v1:';
    /** Formato vigente (AES-256-GCM con ext-openssl). */
    public const PREFIJO_V2 = 'enc:v2:';
    private const LLAVE_BYTES = 32;
    private const GCM_NONCE = 12;
    private const GCM_ETIQUETA = 16;

    /** @var array{activa:string,llaves:array<string,string>}|null */
    private static ?array $llaves = null;

    /** ¿Hay una llave configurada? */
    public static function disponible(): bool
    {
        return self::cargar()['activa'] !== '';
    }

    /** ¿El valor ya está cifrado por esta clase? */
    public static function estaCifrado(?string $valor): bool
    {
        return $valor !== null && (str_starts_with($valor, self::PREFIJO_V2) || str_starts_with($valor, self::PREFIJO));
    }

    /**
     * Cifra $valor para el $contexto dado. NULL y cadena vacía se conservan tal cual.
     * Sin llave configurada devuelve el texto plano, salvo que `cifrado.obligatorio` sea true (entonces falla).
     */
    public static function cifrar(?string $valor, string $contexto): ?string
    {
        if ($valor === null || $valor === '' || self::estaCifrado($valor)) {
            return $valor;
        }
        $cfg = self::cargar();
        if ($cfg['activa'] === '') {
            if (App::config('cifrado.obligatorio', false)) {
                throw new RuntimeException('Falta la llave de cifrado (TRAZAFI_CIFRADO_LLAVE o storage/secrets/cifrado.json).');
            }
            return $valor;
        }
        $nonce = random_bytes(self::GCM_NONCE);
        $etiqueta = '';
        $cifrado = openssl_encrypt(
            $valor, 'aes-256-gcm', self::subllaveGcm(self::llave($cfg['activa'], $cfg)), OPENSSL_RAW_DATA, $nonce, $etiqueta, $contexto, self::GCM_ETIQUETA
        );
        if ($cifrado === false || strlen($etiqueta) !== self::GCM_ETIQUETA) {
            throw new RuntimeException('No se pudo cifrar el valor.');
        }
        return self::PREFIJO_V2 . $cfg['activa'] . ':' . self::b64($nonce . $cifrado . $etiqueta);
    }

    /**
     * Descifra un valor producido por cifrar(). Si no tiene el prefijo se devuelve igual (texto plano heredado).
     * Lanza excepción si la llave falta o si el valor fue alterado o pertenece a otro contexto.
     */
    public static function descifrar(?string $valor, string $contexto): ?string
    {
        if ($valor === null || $valor === '' || !self::estaCifrado($valor)) {
            return $valor;
        }
        $v2 = str_starts_with($valor, self::PREFIJO_V2);
        $partes = explode(':', substr($valor, strlen($v2 ? self::PREFIJO_V2 : self::PREFIJO)), 2);
        if (count($partes) !== 2) {
            throw new RuntimeException('Valor cifrado con formato inválido.');
        }
        [$id, $cuerpo] = $partes;
        $crudo = self::deB64($cuerpo);
        if ($v2) {
            if ($crudo === null || strlen($crudo) <= self::GCM_NONCE + self::GCM_ETIQUETA) {
                throw new RuntimeException('Valor cifrado con formato inválido.');
            }
            $claro = openssl_decrypt(
                substr($crudo, self::GCM_NONCE, -self::GCM_ETIQUETA), 'aes-256-gcm', self::subllaveGcm(self::llave($id, self::cargar())),
                OPENSSL_RAW_DATA, substr($crudo, 0, self::GCM_NONCE), substr($crudo, -self::GCM_ETIQUETA), $contexto
            );
        } else {
            if (!function_exists('sodium_crypto_aead_xchacha20poly1305_ietf_decrypt')) {
                throw new RuntimeException('Este valor usa el formato anterior y este PHP no tiene ext-sodium: ejecuta php tools/cifrar_totp.php --recifrar desde la consola.');
            }
            $nlen = SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
            if ($crudo === null || strlen($crudo) <= $nlen + SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_ABYTES) {
                throw new RuntimeException('Valor cifrado con formato inválido.');
            }
            $claro = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                substr($crudo, $nlen), $contexto, substr($crudo, 0, $nlen), self::llave($id, self::cargar())
            );
        }
        if ($claro === false) {
            throw new RuntimeException('No se pudo descifrar el valor (llave incorrecta, contexto distinto o dato alterado).');
        }
        return $claro;
    }

    /**
     * Índice ciego: huella HMAC determinista para buscar por igualdad sin descifrar (correo, matrícula).
     * Usa una subllave derivada distinta de la de cifrado. Normaliza a minúsculas y sin espacios en los extremos.
     */
    public static function indiceCiego(string $valor, string $contexto): string
    {
        $cfg = self::cargar();
        if ($cfg['activa'] === '') {
            throw new RuntimeException('Falta la llave de cifrado para calcular el índice ciego.');
        }
        $sub = hash_hmac('sha256', 'indice-ciego|' . $contexto, self::llave($cfg['activa'], $cfg), true);
        return hash_hmac('sha256', mb_strtolower(trim($valor), 'UTF-8'), $sub);
    }

    /** Genera una llave nueva lista para configurar (32 bytes en Base64). */
    public static function generarLlave(): string
    {
        return base64_encode(random_bytes(self::LLAVE_BYTES));
    }

    /** Sólo para pruebas: fuerza la recarga de la configuración o inyecta llaves. */
    public static function reiniciar(?array $llaves = null): void
    {
        self::$llaves = $llaves;
    }

    // ---------------------------------------------------------------------------------------------------------------

    /** @return array{activa:string,llaves:array<string,string>} */
    private static function cargar(): array
    {
        if (self::$llaves !== null) {
            return self::$llaves;
        }
        $cfg = ['activa' => '', 'llaves' => []];

        $archivo = (string) App::config('cifrado.archivo', '');
        if ($archivo !== '' && is_file($archivo)) {
            $json = json_decode((string) file_get_contents($archivo), true);
            if (is_array($json) && is_array($json['llaves'] ?? null)) {
                $cfg['llaves'] = array_map('strval', $json['llaves']);
                $cfg['activa'] = (string) ($json['activa'] ?? '');
            }
        }
        // La variable de entorno tiene prioridad: es la llave «k1» y la activa si no se indica otra.
        $env = getenv('TRAZAFI_CIFRADO_LLAVE');
        if (is_string($env) && $env !== '') {
            $cfg['llaves']['env'] = $env;
            $cfg['activa'] = 'env';
        }
        if ($cfg['activa'] !== '' && !isset($cfg['llaves'][$cfg['activa']])) {
            throw new RuntimeException('La llave de cifrado activa no existe en la configuración.');
        }
        return self::$llaves = $cfg;
    }

    /** @param array{activa:string,llaves:array<string,string>} $cfg */
    private static function llave(string $id, array $cfg): string
    {
        $b64 = $cfg['llaves'][$id] ?? null;
        if ($b64 === null) {
            throw new RuntimeException("No hay llave de cifrado «{$id}» para descifrar este valor.");
        }
        $llave = base64_decode($b64, true);
        if ($llave === false || strlen($llave) !== self::LLAVE_BYTES) {
            throw new RuntimeException("La llave de cifrado «{$id}» debe ser de 32 bytes en Base64.");
        }
        return $llave;
    }

    /** Subllave propia de AES-GCM: la llave maestra no se usa directamente con dos algoritmos. */
    private static function subllaveGcm(string $llave): string
    {
        return hash_hmac('sha256', 'trazafi|cifrado|aes-256-gcm', $llave, true);
    }

    private static function b64(string $crudo): string
    {
        return rtrim(strtr(base64_encode($crudo), '+/', '-_'), '=');
    }

    private static function deB64(string $texto): ?string
    {
        $r = base64_decode(strtr($texto, '-_', '+/'), true);
        return $r === false ? null : $r;
    }
}
