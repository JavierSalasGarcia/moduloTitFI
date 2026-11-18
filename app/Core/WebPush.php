<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Notificaciones push web (estándar Web Push: RFC 8291 cifrado aes128gcm y RFC 8292 VAPID) sin dependencias externas.
 * Las claves VAPID se generan una sola vez y se guardan en storage/push_vapid.json (fuera del alcance web).
 */
final class WebPush
{
    private static ?array $claves = null;

    public static function b64u(string $s): string
    {
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function deB64u(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    private static function archivo(): string
    {
        return rtrim((string) App::config('archivos.storage'), '/\\') . '/push_vapid.json';
    }

    /** @return array{publica:string,privada:string} publica en base64url (65 bytes sin comprimir), privada en PEM. */
    public static function claves(): array
    {
        if (self::$claves !== null) {
            return self::$claves;
        }
        $f = self::archivo();
        if (is_file($f)) {
            $c = json_decode((string) file_get_contents($f), true);
            if (!empty($c['publica']) && !empty($c['privada'])) {
                return self::$claves = $c;
            }
        }
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($k, $pem);
        $d = openssl_pkey_get_details($k)['ec'];
        $c = ['publica' => self::b64u("\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT)), 'privada' => $pem];
        file_put_contents($f, json_encode($c), LOCK_EX);
        @chmod($f, 0600);
        return self::$claves = $c;
    }

    private static function hmac(string $clave, string $dato): string
    {
        return hash_hmac('sha256', $dato, $clave, true);
    }

    /** Llave pública P-256 en PEM a partir del punto sin comprimir (65 bytes). */
    private static function pemPublica(string $punto): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $punto;
        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /** Firma DER (ECDSA) a formato JOSE r||s de 64 bytes. */
    private static function derARaw(string $der): string
    {
        $p = 2 + (ord($der[1]) & 0x80 ? (ord($der[1]) & 0x7f) : 0);
        $lr = ord($der[$p + 1]);
        $r = substr($der, $p + 2, $lr);
        $p += 2 + $lr;
        $ls = ord($der[$p + 1]);
        $s = substr($der, $p + 2, $ls);
        $norm = static fn(string $x): string => str_pad(ltrim($x, "\0"), 32, "\0", STR_PAD_LEFT);
        return $norm($r) . $norm($s);
    }

    private static function vapid(string $endpoint): string
    {
        $u = parse_url($endpoint);
        $aud = $u['scheme'] . '://' . $u['host'];
        $cab = self::b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
        $car = self::b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:academica_fi@uaemex.mx']));
        openssl_sign($cab . '.' . $car, $firma, self::claves()['privada'], OPENSSL_ALGO_SHA256);
        return $cab . '.' . $car . '.' . self::b64u(self::derARaw($firma));
    }

    /** Cifra el mensaje para una suscripción (RFC 8291, aes128gcm). */
    private static function cifrar(string $mensaje, string $p256dh, string $auth): string
    {
        $uaPublica = self::deB64u($p256dh);
        $secreto = self::deB64u($auth);
        $efimera = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $d = openssl_pkey_get_details($efimera)['ec'];
        $asPublica = "\x04" . str_pad($d['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['y'], 32, "\0", STR_PAD_LEFT);
        $compartido = openssl_pkey_derive(openssl_pkey_get_public(self::pemPublica($uaPublica)), $efimera, 32);
        $prkClave = self::hmac($secreto, $compartido);
        $ikm = self::hmac($prkClave, "WebPush: info\0" . $uaPublica . $asPublica . "\x01");
        $sal = random_bytes(16);
        $prk = self::hmac($sal, $ikm);
        $cek = substr(self::hmac($prk, "Content-Encoding: aes128gcm\0\x01"), 0, 16);
        $nonce = substr(self::hmac($prk, "Content-Encoding: nonce\0\x01"), 0, 12);
        $cifrado = openssl_encrypt($mensaje . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $etiqueta);
        return $sal . pack('N', 4096) . chr(65) . $asPublica . $cifrado . $etiqueta;
    }

    /** Envía y devuelve el código HTTP (201 = entregado al servicio; 404/410 = suscripción caducada). */
    public static function enviar(array $sub, array $datos): int
    {
        $cuerpo = self::cifrar(json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) $sub['p256dh'], (string) $sub['auth']);
        $ch = curl_init((string) $sub['endpoint']);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => $cuerpo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: vapid t=' . self::vapid((string) $sub['endpoint']) . ', k=' . self::claves()['publica'],
                'Content-Encoding: aes128gcm', 'Content-Type: application/octet-stream', 'TTL: 172800', 'Urgency: normal',
            ],
        ]);
        curl_exec($ch);
        $codigo = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return $codigo;
    }
}
