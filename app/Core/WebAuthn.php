<?php
declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class WebAuthn
{
    /**
     * Genera un reto aleatorio criptográficamente seguro, lo guarda en sesión
     * en formato hexadecimal y base64url, y lo devuelve.
     */
    public static function generarChallenge(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $bytes = random_bytes(32);
        $challengeHex = bin2hex($bytes);
        // Base64URL encoding (removes =, + to -, / to _)
        $challengeBase64Url = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
        
        $_SESSION['webauthn_challenge_hex'] = $challengeHex;
        $_SESSION['webauthn_challenge_b64'] = $challengeBase64Url;
        $_SESSION['webauthn_challenge_creado_en'] = time();
        
        return [
            'hex' => $challengeHex,
            'b64' => $challengeBase64Url
        ];
    }

    /**
     * Valida una firma de WebAuthn usando OpenSSL y la clave pública PEM.
     */
    public static function verificar(
        string $clientDataJSONB64,
        string $authenticatorDataB64,
        string $signatureB64,
        string $publicKeyPem,
        string $expectedOrigin,
        string $rpId
    ): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Decodificar Base64URL o Base64 estándar
        $clientDataJSON = self::base64urlDecode($clientDataJSONB64);
        $authenticatorData = self::base64urlDecode($authenticatorDataB64);
        $signature = self::base64urlDecode($signatureB64);

        // 1. Validar el Desafío (Challenge) en clientDataJSON
        $clientData = json_decode($clientDataJSON, true);
        if (!is_array($clientData)
            || ($clientData['type'] ?? '') !== 'webauthn.get'
            || !isset($clientData['challenge'], $clientData['origin'])) {
            return false;
        }

        $sessionChallengeB64 = $_SESSION['webauthn_challenge_b64'] ?? '';
        $challengeCreado = (int) ($_SESSION['webauthn_challenge_creado_en'] ?? 0);
        if ($sessionChallengeB64 === ''
            || $challengeCreado < time() - 300
            || !hash_equals($sessionChallengeB64, (string) $clientData['challenge'])) {
            // Desafío no coincide o ya expiró
            return false;
        }

        // Limpiar el challenge usado para evitar ataques de repetición
        unset($_SESSION['webauthn_challenge_hex'], $_SESSION['webauthn_challenge_b64'], $_SESSION['webauthn_challenge_creado_en']);

        // 2. Validar origen, RP ID y presencia/verificación del usuario.
        if (!hash_equals($expectedOrigin, (string) $clientData['origin'])
            || !empty($clientData['crossOrigin'])
            || strlen($authenticatorData) < 37) {
            return false;
        }
        $rpIdHash = substr($authenticatorData, 0, 32);
        if (!hash_equals(hash('sha256', $rpId, true), $rpIdHash)) {
            return false;
        }
        $flags = ord($authenticatorData[32]);
        if (($flags & 0x01) === 0 || ($flags & 0x04) === 0) {
            return false;
        }

        // 3. Reconstruir la base de la firma
        // signatureBase = authenticatorData + SHA-256(clientDataJSON)
        $clientDataHash = hash('sha256', $clientDataJSON, true);
        $signatureBase = $authenticatorData . $clientDataHash;

        // 4. Verificar firma usando OpenSSL
        $ok = openssl_verify($signatureBase, $signature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        return $ok === 1;
    }

    private static function base64urlDecode(string $data): string
    {
        // Convertir base64url a base64 estándar
        $b64 = strtr($data, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($b64, true);
        if ($decoded === false) {
            throw new RuntimeException("Error al decodificar base64url");
        }
        return $decoded;
    }
}
