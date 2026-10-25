<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;

/** Documentos que la plataforma genera con los datos capturados y guarda en el expediente. */
final class DocumentosGenerados
{
    /** Guarda un PDF generado en el expediente del trámite. @return string ruta relativa */
    public static function guardar(array $tramite, string $prefijo, string $bytes): string
    {
        $relativa = 'titulacion/' . (int) $tramite['id'] . '/' . $prefijo . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $abs = FirmaElectronica::almacen() . '/' . $relativa;
        if (!is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0755, true);
        }
        file_put_contents($abs, $bytes);
        return $relativa;
    }

    /** Posiciones de firma del documento, para estampar después las firmas que faltan. */
    public static function guardarCajas(int $docId, array $cajas): void
    {
        \App\Core\Database::ejecutar('UPDATE tit_documentos SET cajas = ? WHERE id = ?', [$cajas ? json_encode($cajas) : null, $docId]);
    }

    public static function caja(array $doc, string $rol): ?array
    {
        $c = json_decode((string) ($doc['cajas'] ?? ''), true);
        return is_array($c) && isset($c[$rol]) ? $c[$rol] : null;
    }

    /**
     * Genera el formato con los datos del trámite y lo firma electrónicamente a nombre del pasante.
     * @return array{0:string,1:array} [ruta relativa del PDF firmado, cajas de firma]
     */
    public static function firmarPorPasante(array $tramite, string $formato, string $firmante): array
    {
        [$bytes, , , $cajas] = Formatos::generar($formato, $tramite);
        $dir = FirmaElectronica::almacen() . '/titulacion/' . (int) $tramite['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $origen = $dir . '/_gen_' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($origen, $bytes);
        $relativa = 'titulacion/' . (int) $tramite['id'] . '/' . $formato . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $mm = 25.4 / 72;
        $caja = $cajas['pasante'] ?? null;
        try {
            SelloPdf::estampar(
                $origen, FirmaElectronica::almacen() . '/' . $relativa, $caja ? (int) $caja['p'] : null,
                $caja ? ((float) $caja['x'] + (float) $caja['w'] / 2) * $mm : -1.0, $caja ? (float) $caja['y'] * $mm - 1.0 : -1.0,
                'FIRMA ELECTRÓNICA · PASANTE',
                [mb_strtoupper($firmante), 'Firmado el ' . date('d/m/Y H:i') . ' · ' . $tramite['folio'] . ' ' . substr(hash('sha256', $bytes), 0, 8)]
            );
        } finally {
            @unlink($origen);
        }
        return [$relativa, $cajas];
    }

    /**
     * Genera el protocolo en PDF con lo capturado y lo firma a nombre del pasante.
     * @return array{0:string,1:array} [ruta relativa, cajas de firma]
     */
    public static function firmarProtocolo(array $tramite, string $firmante): array
    {
        [$bytes, $cajas] = ProtocoloPdf::generar($tramite);
        $dir = FirmaElectronica::almacen() . '/titulacion/' . (int) $tramite['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $origen = $dir . '/_gen_' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($origen, $bytes);
        $relativa = 'titulacion/' . (int) $tramite['id'] . '/protocolo_' . bin2hex(random_bytes(8)) . '.pdf';
        $c = $cajas['pasante'];
        $mm = 25.4 / 72;
        try {
            SelloPdf::estampar(
                $origen, FirmaElectronica::almacen() . '/' . $relativa, (int) $c['p'], ((float) $c['x'] + (float) $c['w'] / 2) * $mm, (float) $c['y'] * $mm - 1.0,
                'FIRMA ELECTRÓNICA · PASANTE',
                [mb_strtoupper($firmante), 'Firmado el ' . date('d/m/Y H:i') . ' · ' . $tramite['folio'] . ' ' . substr(hash('sha256', $bytes), 0, 8)]
            );
        } finally {
            @unlink($origen);
        }
        return [$relativa, $cajas];
    }

    /** Genera y guarda un oficio del departamento. @return array{0:string,1:array} [ruta relativa, caja de firma] */
    public static function guardarOficio(array $tramite, string $clave, array $datosHito, array $extra = []): array
    {
        [$bytes, $caja] = Oficios::generar($clave, $tramite, $datosHito, $extra);
        $relativa = 'titulacion/' . (int) $tramite['id'] . '/' . $clave . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $abs = FirmaElectronica::almacen() . '/' . $relativa;
        if (!is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0755, true);
        }
        file_put_contents($abs, $bytes);
        return [$relativa, $caja];
    }

    public static function nombre(string $formato): string
    {
        return Catalogo::formatos()[$formato]['nombre'] ?? $formato;
    }
}
