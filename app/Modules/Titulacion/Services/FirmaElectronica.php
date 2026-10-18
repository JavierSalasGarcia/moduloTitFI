<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\App;
use App\Core\Database;
use App\Core\Totp;
use App\Modules\Firmas\PadesSigner;
use App\Modules\Firmas\PdfFirmador;
use RuntimeException;
use Throwable;

/**
 * Firma electrónica de PDF generados por el módulo: sello visible con huella (sin infraestructura
 * adicional) o firma PAdES con el certificado individual y segundo factor (PKI del módulo Firmas).
 */
final class FirmaElectronica
{
    public static function almacen(): string
    {
        return rtrim((string) App::config('archivos.storage'), '/\\');
    }

    public static function pkiDisponible(): bool
    {
        return PadesSigner::habilitado();
    }

    /** Nivel configurado para las revisiones de protocolo. */
    public static function nivelRevisores(): string
    {
        return \ajuste('titulacion_firma_revisores', 'sello') === 'pades' && self::pkiDisponible() ? 'pades' : 'sello';
    }

    /**
     * Estampa un sello de firma visible. @return string huella SHA-256 del PDF resultante
     *
     * @param array{etiqueta:string,nombre:string,folio:string,posicion?:string,pagina?:string} $datos
     */
    public static function sellar(string $origenAbs, string $salidaAbs, array $datos): string
    {
        $hashOrigen = hash_file('sha256', $origenAbs) ?: '';
        if (!is_dir(dirname($salidaAbs))) {
            mkdir(dirname($salidaAbs), 0755, true);
        }
        PdfFirmador::estampar($origenAbs, $salidaAbs, [
            'pagina' => $datos['pagina'] ?? 'ultima', 'posicion' => $datos['posicion'] ?? 'abajo_centro', 'nivel' => 0, 'imagen' => null,
            'nombre' => $datos['etiqueta'] . ': ' . mb_strimwidth($datos['nombre'], 0, 26, '…'),
            'fecha' => date('d/m/Y H:i'), 'folio' => $datos['folio'] . ' ' . substr($hashOrigen, 0, 8),
        ]);
        return hash_file('sha256', $salidaAbs) ?: '';
    }

    /** Firma PAdES con TOTP. @return string huella SHA-256 previa a la firma */
    public static function pades(int $usuarioId, string $codigo, string $origenAbs, string $salidaAbs, string $folio, string $motivo): string
    {
        if (!PadesSigner::habilitado()) {
            throw new RuntimeException('La firma PAdES no está habilitada por la administración.');
        }
        $u = Database::una('SELECT id, nombre, email, totp_secret FROM usuarios WHERE id = ?', [$usuarioId]);
        $codigo = preg_replace('/\D+/', '', $codigo) ?? '';
        if (!$u || empty($u['totp_secret'])) {
            throw new RuntimeException('Activa la contraseña de un solo uso (TOTP) en tu perfil antes de firmar.');
        }
        if (!Totp::verificar($u['totp_secret'], $codigo)) {
            throw new RuntimeException('El código de segundo factor no es válido o ya expiró.');
        }
        self::registrarUsoCodigo($usuarioId, $codigo);
        $cert = Database::una(
            "SELECT * FROM firma_certificados WHERE usuario_id = ? AND estado = 'activo' AND valido_hasta > NOW() ORDER BY id DESC LIMIT 1",
            [$usuarioId]
        );
        if (!$cert || !is_file($cert['pfx_path'])) {
            throw new RuntimeException('Aún no tienes certificado de firma. Genera el tuyo desde el módulo Firmas de documentos.');
        }
        if (!is_dir(dirname($salidaAbs))) {
            mkdir(dirname($salidaAbs), 0755, true);
        }
        $r = PadesSigner::firmar([
            'input_pdf' => $origenAbs, 'output_pdf' => $salidaAbs,
            'pfx_path' => $cert['pfx_path'], 'wrapped_secret' => $cert['secreto_envuelto'],
            'field_name' => 'TrazaFI_Tit_' . bin2hex(random_bytes(4)),
            'signer_name' => $u['nombre'], 'folio' => $folio, 'reason' => $motivo,
            'page' => 'ultima', 'position' => 'abajo_centro', 'level' => 0,
        ]);
        return (string) $r['sha256_before'];
    }

    private static function registrarUsoCodigo(int $usuarioId, string $codigo): void
    {
        $ruta = (string) App::config('firma_pki.audit_hmac_key', '');
        $clave = $ruta !== '' && is_file($ruta) ? trim((string) file_get_contents($ruta)) : '';
        if (strlen($clave) < 32) {
            throw new RuntimeException('Falta configurar la llave de auditoría del segundo factor.');
        }
        $huella = hash_hmac('sha256', $usuarioId . '|' . $codigo . '|' . intdiv(time(), 30), $clave);
        try {
            Database::ejecutar('INSERT INTO firma_segundo_factor_usos (usuario_id, codigo_hash) VALUES (?, ?)', [$usuarioId, $huella]);
        } catch (Throwable) {
            throw new RuntimeException('Ese código ya se usó para firmar. Espera el siguiente.');
        }
    }
}
