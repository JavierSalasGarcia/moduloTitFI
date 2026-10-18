<?php
declare(strict_types=1);

namespace App\Modules\Firmas;

/**
 * Estampado de sellos de firma sobre PDFs con FPDF/FPDI (vendoreados en
 * lib/, sin Composer) y generación de la hoja de firmas de respaldo.
 *
 * FPDI gratuito no puede importar PDFs con cross-reference streams
 * comprimidos (muchos PDFs modernos): esos documentos se marcan como no
 * estampables y sus firmas constan solo en la BD y en la hoja de firmas.
 */
final class PdfFirmador
{
    private const ANCHO_SELLO = 60;  // mm
    private const ALTO_SELLO  = 26;  // mm por nivel de apilado

    private static function cargarLibs(): void
    {
        static $cargado = false;
        if ($cargado) {
            return;
        }
        $lib = dirname(APP_RAIZ) . '/lib';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $lib . '/fpdf/font/');
        }
        require_once $lib . '/fpdf/fpdf.php';
        require_once $lib . '/fpdi/autoload.php';
        $cargado = true;
    }

    /** ¿FPDI puede importar este PDF? Se evalúa una vez al subirlo. */
    public static function esEstampable(string $rutaAbs): bool
    {
        self::cargarLibs();
        try {
            $pdf = new \setasign\Fpdi\Fpdi();
            if ($pdf->setSourceFile($rutaAbs) < 1) {
                return false;
            }
            $pdf->importPage(1);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Copia el PDF de origen estampando un sello en la página elegida.
     * $sello = ['pagina' => 'primera|ultima', 'posicion' => 'abajo_izquierda|
     * abajo_centro|abajo_derecha', 'nivel' => sellos previos en esa zona,
     * 'imagen' => ruta absoluta o null, 'nombre', 'fecha', 'folio'].
     * Lanza Throwable si FPDI no puede procesar el documento.
     */
    public static function estampar(string $origenAbs, string $salidaAbs, array $sello): void
    {
        self::cargarLibs();
        $pdf = new \setasign\Fpdi\Fpdi();
        // Sin salto de página automático: el sello se dibuja junto al
        // borde inferior y el auto-break de FPDF crearía páginas extra.
        $pdf->SetAutoPageBreak(false);
        $total = $pdf->setSourceFile($origenAbs);
        $objetivo = $sello['pagina'] === 'primera' ? 1 : $total;

        for ($i = 1; $i <= $total; $i++) {
            $tpl  = $pdf->importPage($i);
            $dim  = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
            $pdf->useTemplate($tpl);
            if ($i === $objetivo) {
                self::dibujarSello($pdf, (float) $dim['width'], (float) $dim['height'], $sello);
            }
        }
        $pdf->Output('F', $salidaAbs);
    }

    private static function dibujarSello(object $pdf, float $ancho, float $alto, array $s): void
    {
        $w = self::ANCHO_SELLO;
        $x = match ($s['posicion']) {
            'abajo_izquierda' => 12.0,
            'abajo_centro'    => ($ancho - $w) / 2,
            default           => $ancho - $w - 12.0,
        };
        $y = max(10.0, $alto - 32.0 - ((int) $s['nivel']) * self::ALTO_SELLO);

        if (!empty($s['imagen']) && is_file($s['imagen'])) {
            $pdf->Image($s['imagen'], $x + ($w - 36) / 2, $y, 36, 11);
        }
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->Line($x + 4, $y + 12, $x + $w - 4, $y + 12);
        $pdf->SetTextColor(25, 25, 25);
        $pdf->SetFont('Helvetica', 'B', 8);
        $pdf->SetXY($x, $y + 13);
        $pdf->Cell($w, 4, self::latin1($s['nombre']), 0, 2, 'C');
        $pdf->SetFont('Helvetica', '', 7);
        $pdf->Cell($w, 4, self::latin1('Firmado el ' . $s['fecha'] . ' · ' . $s['folio']), 0, 0, 'C');
    }

    /**
     * Hoja de firmas de la solicitud, construida SOLO con FPDF (funciona
     * para cualquier documento, estampable o no). Se regenera tras cada
     * firma o rechazo.
     * $documentos: [['nombre', 'tipo', 'sha256' => hash actual, 'estampable'], ...]
     * $firmantes:  [['nombre', 'email', 'orden', 'estado', 'respondido_en',
     *               'motivo_rechazo', 'imagen' => ruta abs o null], ...]
     */
    public static function hojaFirmas(array $solicitud, array $documentos, array $firmantes, string $salidaAbs): void
    {
        self::cargarLibs();
        $pdf = new \FPDF();
        $pdf->SetAutoPageBreak(true, 18);
        $pdf->AddPage();

        $pdf->SetTextColor(44, 82, 52); // --verde-900
        $pdf->SetFont('Helvetica', 'B', 14);
        $pdf->Cell(0, 7, self::latin1('Hoja de firmas · Plataforma FI'), 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->Cell(0, 6, self::latin1('Facultad de Ingeniería · UAEMEX'), 0, 1);
        $pdf->Ln(2);
        $pdf->SetDrawColor(156, 132, 18); // --oro
        $pdf->SetLineWidth(0.6);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->SetLineWidth(0.2);
        $pdf->Ln(4);

        $pdf->SetTextColor(25, 25, 25);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->MultiCell(0, 6, self::latin1($solicitud['folio'] . ' — ' . $solicitud['titulo']));
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, self::latin1('Modo: ' . $solicitud['modo'] . ' · Estado: ' . $solicitud['estado']
            . ' · Generada el ' . date('d/m/Y H:i')), 0, 1);
        $pdf->Ln(3);

        // ── Documentos con su hash de integridad ─────────────────────
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Documentos', 0, 1);
        $pdf->SetFont('Helvetica', '', 8);
        foreach ($documentos as $d) {
            $pdf->SetFont('Helvetica', 'B', 8);
            $etiqueta = $d['tipo'] === 'anexo' ? ' (anexo)' : '';
            $sellado  = $d['estampable'] ? '' : ' — firmas solo en esta hoja (PDF no estampable)';
            $pdf->MultiCell(0, 4.5, self::latin1('· ' . $d['nombre'] . $etiqueta . $sellado));
            $pdf->SetFont('Courier', '', 7);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->Cell(0, 4, '  SHA-256: ' . $d['sha256'], 0, 1);
            $pdf->SetTextColor(25, 25, 25);
        }
        $pdf->Ln(4);

        // ── Firmantes ────────────────────────────────────────────────
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Firmas', 0, 1);
        foreach ($firmantes as $f) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
            }
            $y = $pdf->GetY() + 2;
            if (!empty($f['imagen']) && is_file($f['imagen']) && $f['estado'] === 'firmado') {
                $pdf->Image($f['imagen'], 12, $y, 34, 11);
            }
            $pdf->SetDrawColor(70, 70, 70);
            $pdf->Line(12, $y + 12, 70, $y + 12);
            $pdf->SetXY(12, $y + 13);
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(58, 4.5, self::latin1($f['nombre']), 0, 0);

            $pdf->SetXY(75, $y + 1);
            $pdf->SetFont('Helvetica', '', 8);
            $estado = match ($f['estado']) {
                'firmado'   => 'FIRMADO el ' . ($f['respondido_en'] ? date('d/m/Y H:i', strtotime($f['respondido_en'])) : ''),
                'rechazado' => 'RECHAZADO el ' . ($f['respondido_en'] ? date('d/m/Y H:i', strtotime($f['respondido_en'])) : ''),
                default     => 'PENDIENTE',
            };
            $pdf->MultiCell(120, 4.5, self::latin1(
                $f['email'] . "\n" . 'Paso ' . $f['orden'] . ' · ' . $estado
                . ($f['estado'] === 'rechazado' && $f['motivo_rechazo'] ? "\nMotivo: " . $f['motivo_rechazo'] : '')
            ));
            $pdf->SetY(max($pdf->GetY(), $y + 18) + 3);
        }

        $pdf->Output('F', $salidaAbs);
    }

    /** FPDF usa fuentes ISO-8859-1: convierte el texto UTF-8 de la app. */
    private static function latin1(string $texto): string
    {
        return (string) iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $texto);
    }
}
