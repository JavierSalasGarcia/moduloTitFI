<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use Throwable;

/**
 * Estampa el ID del proceso de titulación en la esquina inferior derecha de cada página de un PDF que sube el
 * pasante. Si el PDF usa una compresión que FPDI no lee, se intenta normalizar con Ghostscript; si tampoco se
 * puede, el archivo se conserva tal cual (el ID queda en el expediente y en el nombre del archivo descargado).
 */
final class FolioPdf
{
    public static function estampar(string $abs, string $folio): bool
    {
        if ($folio === '' || !is_file($abs)) {
            return false;
        }
        if (self::intentar($abs, $folio)) {
            return true;
        }
        $norm = $abs . '.gs.pdf';
        if (self::ghostscript($abs, $norm) && self::intentar($norm, $folio, $abs)) {
            @unlink($norm);
            return true;
        }
        @unlink($norm);
        return false;
    }

    private static function intentar(string $origen, string $folio, ?string $destino = null): bool
    {
        try {
            $pdf = new Hoja();
            $pdf->SetAutoPageBreak(false);
            $pdf->setPrintFooter(false);
            $total = $pdf->setSourceFile($origen);
            for ($i = 1; $i <= $total; $i++) {
                $tpl = $pdf->importPage($i);
                $dim = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
                $pdf->useTemplate($tpl);
                $pdf->SetFont('Helvetica', '', 7);
                $pdf->SetTextColor(70, 70, 70);
                $pdf->SetFillColor(255, 255, 255);
                $texto = Hoja::l1('ID del proceso de titulación: ' . $folio);
                $w = $pdf->GetStringWidth($texto) + 3;
                $pdf->SetXY($dim['width'] - $w - 5, $dim['height'] - 8);
                $pdf->Cell($w, 4, $texto, 0, 0, 'R', true);
            }
            $tmp = ($destino ?? $origen) . '.tmp';
            $pdf->Output('F', $tmp);
            return rename($tmp, $destino ?? $origen);
        } catch (Throwable $e) {
            error_log('Titulación: no se pudo estampar el ID en el PDF: ' . $e->getMessage());
            return false;
        }
    }

    private static function ghostscript(string $origen, string $destino): bool
    {
        if (!function_exists('shell_exec') || in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return false;
        }
        @shell_exec('gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=' . escapeshellarg($destino) . ' ' . escapeshellarg($origen) . ' 2>/dev/null');
        return is_file($destino) && filesize($destino) > 0;
    }
}
