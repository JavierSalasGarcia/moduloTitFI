<?php
declare(strict_types=1);

namespace App\Modules\Oficios\Services;

use RuntimeException;

/**
 * Lectura y verificación de una firma CMS/PKCS#7 desprendida (la que genera el navegador con la e.firma): integridad del documento,
 * validez de la firma RSA-SHA256 y datos del certificado. Verificación propia con `openssl_verify` sobre los atributos firmados, que
 * no depende de las variantes de `openssl_cms_verify`. Nunca imprime ni guarda el RFC del certificado.
 */
final class Cms
{
    private const OID_MESSAGE_DIGEST = "\x2A\x86\x48\x86\xF7\x0D\x01\x09\x04";
    private const OID_SHA256 = "\x60\x86\x48\x01\x65\x03\x04\x02\x01";
    private const OID_SIGNING_TIME = "\x2A\x86\x48\x86\xF7\x0D\x01\x09\x05";

    /** @return array{0:int,1:int,2:int,3:int} etiqueta, largo, inicio del contenido, fin del contenido */
    private static function tlv(string $d, int $p): array
    {
        if ($p + 2 > strlen($d)) {
            throw new RuntimeException('La firma está incompleta.');
        }
        $tag = ord($d[$p]);
        $l = ord($d[$p + 1]);
        $q = $p + 2;
        if ($l & 0x80) {
            $n = $l & 0x7F;
            if ($n === 0 || $n > 4) {
                throw new RuntimeException('La firma usa una codificación no admitida.');
            }
            $l = 0;
            for ($i = 0; $i < $n; $i++) {
                $l = ($l << 8) | ord($d[$q++]);
            }
        }
        if ($q + $l > strlen($d)) {
            throw new RuntimeException('La firma está truncada.');
        }
        return [$tag, $l, $q, $q + $l];
    }

    /** Hijos de un nodo constructivo: lista de [etiqueta, inicio del nodo, inicio del contenido, fin]. */
    private static function hijos(string $d, int $ini, int $fin): array
    {
        $r = [];
        $p = $ini;
        while ($p < $fin) {
            [$tag, , $c, $f] = self::tlv($d, $p);
            $r[] = [$tag, $p, $c, $f];
            $p = $f;
        }
        return $r;
    }

    /**
     * @return array{certificados:string[],atributos:string,firma:string,digest_contenido:string,algoritmo_ok:bool,hora:?string}
     */
    public static function analizar(string $der): array
    {
        [, , $c0, $f0] = self::tlv($der, 0);                    // ContentInfo
        $h = self::hijos($der, $c0, $f0);                        // OID, [0] SignedData
        if (count($h) < 2 || $h[1][0] !== 0xA0) {
            throw new RuntimeException('La firma no tiene el formato esperado.');
        }
        [, , $cs] = self::tlv($der, $h[1][1]);                   // contenido del [0]
        [, , $csd, $fsd] = self::tlv($der, $cs);                 // SignedData SEQUENCE
        $sd = self::hijos($der, $csd, $fsd);
        $certs = [];
        $signerInfos = null;
        foreach ($sd as $n) {
            if ($n[0] === 0xA0) {                                // [0] certificados
                foreach (self::hijos($der, $n[2], $n[3]) as $c) {
                    $certs[] = substr($der, $c[1], $c[3] - $c[1]);
                }
            } elseif ($n[0] === 0x31) {
                $signerInfos = $n;                               // el último SET es signerInfos
            }
        }
        if (!$certs || $signerInfos === null) {
            throw new RuntimeException('La firma no incluye certificado o firmante.');
        }
        $si = self::hijos($der, $signerInfos[2], $signerInfos[3]);
        [, , $csi, $fsi] = self::tlv($der, $si[0][1]);           // primer SignerInfo
        $s = self::hijos($der, $csi, $fsi);
        // version, sid, digestAlgorithm, [0] atributos firmados, signatureAlgorithm, signature
        $algoritmoOk = false;
        $atributos = null;
        $firma = null;
        foreach ($s as $i => $n) {
            if ($n[0] === 0x30 && $i === 2) {
                $algoritmoOk = str_contains(substr($der, $n[2], $n[3] - $n[2]), self::OID_SHA256);
            } elseif ($n[0] === 0xA0) {
                $atributos = "\x31" . substr($der, $n[1] + 1, $n[3] - $n[1] - 1);   // la etiqueta [0] se firma como SET
            } elseif ($n[0] === 0x04) {
                $firma = substr($der, $n[2], $n[3] - $n[2]);
            }
        }
        if ($atributos === null || $firma === null) {
            throw new RuntimeException('La firma no trae atributos firmados.');
        }
        $digest = self::valorAtributo($atributos, self::OID_MESSAGE_DIGEST);
        if ($digest === null) {
            throw new RuntimeException('La firma no trae la huella del documento.');
        }
        return ['certificados' => $certs, 'atributos' => $atributos, 'firma' => $firma, 'digest_contenido' => $digest, 'algoritmo_ok' => $algoritmoOk,
            'hora' => self::hora($atributos)];
    }

    /** Valor (OCTET STRING) del atributo con ese OID. */
    private static function valorAtributo(string $set, string $oid): ?string
    {
        [, , $c, $f] = self::tlv($set, 0);
        foreach (self::hijos($set, $c, $f) as $a) {
            $x = self::hijos($set, $a[2], $a[3]);               // OID, SET{valor}
            if (count($x) >= 2 && substr($set, $x[0][2], $x[0][3] - $x[0][2]) === $oid) {
                $v = self::hijos($set, $x[1][2], $x[1][3]);
                return $v ? substr($set, $v[0][2], $v[0][3] - $v[0][2]) : null;
            }
        }
        return null;
    }

    /** Hora de firma declarada en la firma (UTCTime «YYMMDDHHMMSSZ»), solo informativa. */
    private static function hora(string $set): ?string
    {
        $v = self::valorAtributo($set, self::OID_SIGNING_TIME);
        if ($v === null || !preg_match('/^(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})Z$/', $v, $m)) {
            return null;
        }
        $anio = (int) $m[1] + ((int) $m[1] >= 70 ? 1900 : 2000);
        return sprintf('%04d-%02d-%02d %02d:%02d:%02d', $anio, $m[2], $m[3], $m[4], $m[5], $m[6]);
    }

    public static function pem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END CERTIFICATE-----\n";
    }

    /**
     * Verifica la firma sobre $contenido (los bytes del PDF fuera de la firma).
     * @return array{ok:bool,error:?string,certificado:?string,hora:?string}
     */
    public static function verificar(string $der, string $contenido): array
    {
        try {
            $f = self::analizar($der);
        } catch (RuntimeException $e) {
            return ['ok' => false, 'error' => $e->getMessage(), 'certificado' => null, 'hora' => null];
        }
        if (!$f['algoritmo_ok']) {
            return ['ok' => false, 'error' => 'La firma no usa SHA-256.', 'certificado' => null, 'hora' => null];
        }
        if (!hash_equals($f['digest_contenido'], hash('sha256', $contenido, true))) {
            return ['ok' => false, 'error' => 'La firma no corresponde al contenido del documento.', 'certificado' => null, 'hora' => null];
        }
        foreach ($f['certificados'] as $cer) {
            $clave = openssl_pkey_get_public(self::pem($cer));
            if ($clave !== false && openssl_verify($f['atributos'], $f['firma'], $clave, OPENSSL_ALGO_SHA256) === 1) {
                return ['ok' => true, 'error' => null, 'certificado' => $cer, 'hora' => $f['hora']];
            }
        }
        return ['ok' => false, 'error' => 'La firma criptográfica no es válida.', 'certificado' => null, 'hora' => $f['hora']];
    }

    /**
     * Datos públicos del certificado. El RFC se devuelve solo para compararlo en el servidor; no debe mostrarse ni guardarse.
     * @return array{nombre:string,serie:string,emisor:string,desde:int,hasta:int,rfc:string,firma_digital:bool,sat:bool}
     */
    public static function certificado(string $der): array
    {
        $x = openssl_x509_parse(self::pem($der), true);
        if (!is_array($x)) {
            throw new RuntimeException('El certificado no se puede leer.');
        }
        $sujeto = array_change_key_case(is_array($x['subject'] ?? null) ? $x['subject'] : [], CASE_LOWER);
        $emisorArr = is_array($x['issuer'] ?? null) ? $x['issuer'] : [];
        $emisor = trim((string) (($emisorArr['O'] ?? '') ?: ($emisorArr['CN'] ?? '')));
        // El «número de serie» del SAT es el serial escrito como texto (cada byte es un dígito ASCII).
        $hex = strtoupper((string) ($x['serialNumberHex'] ?? ''));
        $serie = $hex;
        if ($hex !== '' && strlen($hex) % 2 === 0) {
            $txt = (string) hex2bin($hex);
            if (preg_match('/^\d+$/', $txt)) {
                $serie = $txt;
            }
        }
        $rfc = '';
        foreach ($sujeto as $valor) {
            foreach ((array) $valor as $v) {
                if (preg_match('/\b([A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3})\b/u', mb_strtoupper((string) $v), $m)) {
                    $rfc = $m[1];
                    break 2;
                }
            }
        }
        $uso = (string) ($x['extensions']['keyUsage'] ?? '');
        return [
            'nombre' => (string) (is_array($sujeto['cn'] ?? null) ? $sujeto['cn'][0] : ($sujeto['cn'] ?? '')),
            'serie' => $serie, 'emisor' => $emisor, 'desde' => (int) ($x['validFrom_time_t'] ?? 0), 'hasta' => (int) ($x['validTo_time_t'] ?? 0),
            'rfc' => $rfc, 'firma_digital' => $uso === '' || str_contains($uso, 'Digital Signature') || str_contains($uso, 'Non Repudiation'),
            'sat' => (bool) preg_match('/(servicio de administraci.n tributaria|\bSAT\b)/iu', $emisor . ' ' . implode(' ', array_map('strval', array_values($emisorArr)))),
        ];
    }

    /**
     * ¿La cadena del certificado llega a un certificado raíz cargado? $cadena: PEM de los certificados del SAT cargados.
     * @param string[] $pems
     * @return array{estado:string,detalle:string}
     */
    public static function cadena(string $der, array $pems): array
    {
        if (!$pems) {
            return ['estado' => 'sin_certificados', 'detalle' => 'No hay certificados del SAT cargados para verificar la cadena.'];
        }
        $raices = '';
        $intermedios = '';
        foreach ($pems as $p) {
            $x = @openssl_x509_parse($p, false);
            if (!is_array($x)) {
                continue;
            }
            if (($x['subject'] ?? null) == ($x['issuer'] ?? null)) {
                $raices .= $p . "\n";
            } else {
                $intermedios .= $p . "\n";
            }
        }
        $fr = tempnam(sys_get_temp_dir(), 'raiz');
        $fi = tempnam(sys_get_temp_dir(), 'int');
        try {
            file_put_contents($fr, $raices);
            file_put_contents($fi, $intermedios);
            $r = openssl_x509_checkpurpose(self::pem($der), X509_PURPOSE_ANY, [$fr], $fi);
        } finally {
            @unlink($fr);
            @unlink($fi);
        }
        return $r === true
            ? ['estado' => 'verificada', 'detalle' => 'Cadena verificada contra los certificados del SAT cargados.']
            : ['estado' => 'no_verificada', 'detalle' => 'La cadena del certificado no llegó a los certificados del SAT cargados.'];
    }
}
