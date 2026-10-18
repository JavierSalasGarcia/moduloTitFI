<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

/** Sello de firma electrónica visible, dibujado con FPDF/FPDI sobre una página del PDF. */
final class SelloPdf
{
    private static function libs(): void
    {
        $lib = dirname(APP_RAIZ) . '/lib';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $lib . '/fpdf/font/');
        }
        require_once $lib . '/fpdf/fpdf.php';
        require_once $lib . '/fpdi/autoload.php';
    }

    /**
     * @param int|null $pagina índice de página (0 = primera); null = última
     * @param float $centroMm abscisa del centro del sello (negativa = centro de la página)
     * @param float $yMm borde inferior del sello, en mm desde arriba (negativa = pie de la página)
     * @param string[] $lineas texto bajo la cabecera (la última puede ser una leyenda en cursiva)
     */
    public static function estampar(string $origenAbs, string $salidaAbs, ?int $pagina, float $centroMm, float $yMm, string $cabecera, array $lineas, ?string $leyenda = null): void
    {
        self::libs();
        if (!is_dir(dirname($salidaAbs))) {
            mkdir(dirname($salidaAbs), 0755, true);
        }
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->SetAutoPageBreak(false);
        $total = $pdf->setSourceFile($origenAbs);
        $objetivo = $pagina ?? $total - 1;
        for ($i = 0; $i < $total; $i++) {
            $tpl = $pdf->importPage($i + 1);
            $dim = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
            $pdf->useTemplate($tpl);
            if ($i !== $objetivo) {
                continue;
            }
            $w = 62.0;
            $alto = 5.0 + 3.0 * (count($lineas) + 1 + ($leyenda !== null && $leyenda !== '' ? 1 : 0));
            $centro = $centroMm < 0 ? $dim['width'] / 2 : $centroMm;
            $x = max(8.0, min($dim['width'] - $w - 8.0, $centro - $w / 2));
            $y = max(8.0, min($dim['height'] - $alto - 8.0, ($yMm < 0 ? $dim['height'] - 12.0 : $yMm) - $alto));
            $pdf->SetDrawColor(44, 82, 52);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($x, $y, $w, $alto);
            $pdf->SetTextColor(44, 82, 52);
            $pdf->SetFont('Helvetica', 'B', 6.5);
            $pdf->SetXY($x, $y + 1.2);
            $pdf->Cell($w, 3, self::l1($cabecera), 0, 2, 'C');
            $pdf->SetFont('Helvetica', '', 6.5);
            $pdf->SetTextColor(25, 25, 25);
            foreach ($lineas as $l) {
                $pdf->Cell($w, 3, self::l1(mb_strimwidth($l, 0, 60, '…')), 0, 2, 'C');
            }
            if ($leyenda !== null && $leyenda !== '') {
                $pdf->SetFont('Helvetica', 'I', 6);
                $pdf->Cell($w, 3, self::l1($leyenda), 0, 2, 'C');
            }
        }
        $pdf->Output('F', $salidaAbs);
    }

    private static function l1(string $s): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?: $s;
    }
}
