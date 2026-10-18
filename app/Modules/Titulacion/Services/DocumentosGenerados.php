<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;

/** Documentos que la plataforma genera con los datos capturados y guarda en el expediente. */
final class DocumentosGenerados
{
    /**
     * Genera el formato, lo firma electrónicamente a nombre del pasante y lo guarda en el almacén.
     * @return string ruta relativa del PDF firmado
     */
    public static function firmarPorPasante(array $tramite, string $formato, string $firmante): string
    {
        [$bytes] = Formatos::generar($formato, $tramite);
        $dir = FirmaElectronica::almacen() . '/titulacion/' . (int) $tramite['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $origen = $dir . '/_gen_' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($origen, $bytes);
        $relativa = 'titulacion/' . (int) $tramite['id'] . '/' . $formato . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $mm = 25.4 / 72;
        $caja = SubdireccionFirma::cajaDeFormato($formato);
        try {
            SelloPdf::estampar(
                $origen, FirmaElectronica::almacen() . '/' . $relativa, $caja ? (int) ($caja['p'] ?? 0) : null,
                $caja ? 67.0 : -1.0, $caja ? (float) $caja['y'] * $mm - 1.5 : -1.0,
                'FIRMA ELECTRÓNICA · PASANTE',
                [mb_strtoupper($firmante), 'Firmado el ' . date('d/m/Y H:i') . ' · ' . $tramite['folio'] . ' ' . substr(hash('sha256', $bytes), 0, 8)]
            );
        } finally {
            @unlink($origen);
        }
        return $relativa;
    }

    /** Genera y guarda un oficio del departamento. @return array{0:string,1:array} [ruta relativa, caja de firma] */
    public static function guardarOficio(array $tramite, string $clave, array $datosHito): array
    {
        [$bytes, $caja] = Oficios::generar($clave, $tramite, $datosHito);
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
