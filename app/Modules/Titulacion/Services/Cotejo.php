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
 * Cotejo del original contra el PDF que subió el pasante. Deja constancia de quién coteja, cuándo y
 * la huella SHA-256 del archivo cotejado, con un sello visible o con firma PAdES (PKI institucional).
 */
final class Cotejo
{
    private static function storage(): string
    {
        return rtrim((string) App::config('archivos.storage'), '/\\');
    }

    /** @return array{0:string,1:?string} [huella SHA-256, ruta relativa del PDF sellado o null si no se pudo estampar] */
    public static function sello(array $tramite, array $doc, array $usuario): array
    {
        $origen = self::storage() . '/' . $doc['ruta'];
        $hash = is_file($origen) ? (hash_file('sha256', $origen) ?: '') : '';
        if ($hash === '') {
            throw new RuntimeException('No se encontró el PDF que se va a cotejar.');
        }
        $rel = 'titulacion/' . (int) $tramite['id'] . '/cotejo-' . (int) $doc['id'] . '-' . bin2hex(random_bytes(4)) . '.pdf';
        try {
            if (!is_dir(dirname(self::storage() . '/' . $rel))) {
                mkdir(dirname(self::storage() . '/' . $rel), 0755, true);
            }
            PdfFirmador::estampar($origen, self::storage() . '/' . $rel, [
                'pagina' => 'ultima', 'posicion' => 'abajo_derecha', 'nivel' => 0, 'imagen' => null,
                'nombre' => 'Cotejo: ' . mb_strimwidth((string) $usuario['nombre'], 0, 26, '…'),
                'fecha' => date('d/m/Y H:i'), 'folio' => $tramite['folio'] . ' ' . substr($hash, 0, 8),
            ]);
            return [$hash, $rel];
        } catch (Throwable $e) {
            // Algunos PDF no se pueden estampar; el cotejo igual queda registrado con su huella.
            @unlink(self::storage() . '/' . $rel);
            return [$hash, null];
        }
    }

    /** Firma PAdES con el certificado individual de quien coteja y segundo factor TOTP. @return array{0:string,1:string} */
    public static function pades(array $tramite, array $doc, int $usuarioId, string $codigo): array
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
        $origen = self::storage() . '/' . $doc['ruta'];
        $rel = 'titulacion/' . (int) $tramite['id'] . '/cotejo-' . (int) $doc['id'] . '-' . bin2hex(random_bytes(4)) . '.pdf';
        $r = PadesSigner::firmar([
            'input_pdf' => $origen, 'output_pdf' => self::storage() . '/' . $rel,
            'pfx_path' => $cert['pfx_path'], 'wrapped_secret' => $cert['secreto_envuelto'],
            'field_name' => 'TrazaFI_Cotejo_D' . (int) $doc['id'] . '_' . bin2hex(random_bytes(3)),
            'signer_name' => $u['nombre'], 'folio' => $tramite['folio'],
            'reason' => 'Cotejo del original contra el PDF entregado', 'page' => 'ultima', 'position' => 'abajo_derecha', 'level' => 0,
        ]);
        return [(string) $r['sha256_before'], $rel];
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
