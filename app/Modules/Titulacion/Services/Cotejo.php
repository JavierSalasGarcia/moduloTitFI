<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use RuntimeException;
use Throwable;

/**
 * Cotejo del original contra el PDF que subió el pasante. Deja constancia de quién coteja, cuándo y
 * la huella SHA-256 del archivo cotejado, con un sello visible o con firma PAdES (PKI institucional).
 */
final class Cotejo
{
    /** @return array{0:string,1:?string} [huella SHA-256, ruta relativa del PDF sellado o null si no se pudo estampar] */
    public static function sello(array $tramite, array $doc, array $usuario): array
    {
        $origen = FirmaElectronica::almacen() . '/' . $doc['ruta'];
        $hash = is_file($origen) ? (hash_file('sha256', $origen) ?: '') : '';
        if ($hash === '') {
            throw new RuntimeException('No se encontró el PDF que se va a cotejar.');
        }
        $rel = 'titulacion/' . (int) $tramite['id'] . '/cotejo-' . (int) $doc['id'] . '-' . bin2hex(random_bytes(4)) . '.pdf';
        try {
            FirmaElectronica::sellar($origen, FirmaElectronica::almacen() . '/' . $rel, ['etiqueta' => 'Cotejo', 'nombre' => (string) $usuario['nombre'], 'folio' => (string) $tramite['folio'], 'posicion' => 'abajo_derecha']);
            return [$hash, $rel];
        } catch (Throwable) {
            // Algunos PDF no se pueden estampar; el cotejo igual queda registrado con su huella.
            @unlink(FirmaElectronica::almacen() . '/' . $rel);
            return [$hash, null];
        }
    }

    /** @return array{0:string,1:string} */
    public static function pades(array $tramite, array $doc, int $usuarioId, string $codigo): array
    {
        $rel = 'titulacion/' . (int) $tramite['id'] . '/cotejo-' . (int) $doc['id'] . '-' . bin2hex(random_bytes(4)) . '.pdf';
        $hash = FirmaElectronica::pades($usuarioId, $codigo, FirmaElectronica::almacen() . '/' . $doc['ruta'], FirmaElectronica::almacen() . '/' . $rel, (string) $tramite['folio'], 'Cotejo del original contra el PDF entregado');
        return [$hash, $rel];
    }
}
