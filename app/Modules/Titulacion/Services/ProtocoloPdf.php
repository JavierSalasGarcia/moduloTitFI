<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;

/** Protocolo del pasante en PDF (hoja membretada), generado con lo que escribió en la plataforma. */
final class ProtocoloPdf
{
    private static function l1(string $s): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?: $s;
    }

    /**
     * @return array{0:string,1:array} [PDF, cajas de firma por rol: {p,x,y,w} en puntos]
     */
    public static function generar(array $t): array
    {
        $lib = dirname(APP_RAIZ) . '/lib';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $lib . '/fpdf/font/');
        }
        require_once $lib . '/fpdf/fpdf.php';

        $modalidad = Catalogo::modalidad((string) $t['modalidad']);
        $proto = Catalogo::protocolo((string) $t['modalidad']);
        if ($modalidad === null || $proto === null) {
            throw new \RuntimeException('Esta modalidad no tiene protocolo.');
        }
        $p = $t['datos']['datos'] ?? [];
        $r = $t['datos']['protocolo'] ?? [];
        $nombre = (string) ($p['nombre'] ?? '');
        $carrera = Catalogo::carreras()[$p['carrera'] ?? '']['nombre'] ?? '';
        $hoy = Formatos::fechaHoy();
        $tema = '';
        foreach ($proto['campos'] as $c) {
            if (!empty($c['tema'])) {
                $tema = (string) ($r[$c['k']] ?? '');
            }
        }

        $pdf = new \FPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(22, 18, 22);
        $pdf->SetAutoPageBreak(true, 22);
        $pdf->AddPage();
        $img = dirname(__DIR__) . '/plantillas/img';
        if (is_file($img . '/uaem.jpg')) {
            $pdf->Image($img . '/uaem.jpg', 22, 12, 24);
        }
        if (is_file($img . '/facultad.jpg')) {
            $pdf->Image($img . '/facultad.jpg', 172, 12, 22);
        }
        $pdf->SetY(15);
        $pdf->SetTextColor(44, 82, 52);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 5, self::l1('Universidad Autónoma del Estado de México'), 0, 1, 'C');
        $pdf->Cell(0, 5, self::l1('Facultad de Ingeniería'), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, self::l1('Departamento de Evaluación Profesional'), 0, 1, 'C');
        $pdf->SetDrawColor(156, 132, 18);
        $pdf->SetLineWidth(0.6);
        $pdf->Line(22, 37, 194, 37);
        $pdf->SetTextColor(25, 25, 25);
        $pdf->SetY(42);

        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->MultiCell(0, 5.5, self::l1('SOLICITUD DE APROBACIÓN DE TEMA DE TITULACIÓN MEDIANTE ' . mb_strtoupper($modalidad['nombre'])), 0, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, self::l1('Toluca, México; ' . $hoy['dia'] . ' de ' . $hoy['mes'] . ' de ' . $hoy['anio']), 0, 1, 'R');
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 10);
        foreach ([mb_strtoupper(Formatos::subdirector()), 'SUBDIRECTOR ACADÉMICO', 'DE LA FACULTAD DE INGENIERÍA', 'DE LA UNIVERSIDAD AUTÓNOMA DEL ESTADO DE MÉXICO', 'PRESENTE'] as $l) {
            $pdf->Cell(0, 5, self::l1($l), 0, 1);
        }
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 5, self::l1("Quien suscribe, $nombre, con número de cuenta " . ($p['cuenta'] ?? '') . ", pasante de $carrera, somete a su consideración el tema:"), 0, 'J');
        $pdf->Ln(1);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->MultiCell(0, 5, self::l1($tema !== '' ? $tema : '—'), 0, 'C');
        $pdf->Ln(1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 5, self::l1('El cual será desarrollado para la evaluación profesional en la modalidad de ' . $modalidad['nombre'] . '. Adjunto el protocolo con la propuesta del tema para que sea revisado y, en su caso, aprobado. Sin otro particular, quedo a sus órdenes.'), 0, 'J');
        $pdf->Ln(4);

        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 6, self::l1('INFORMACIÓN DEL PROTOCOLO'), 0, 1, 'C');
        $pdf->Ln(1);
        $cron = null;
        foreach ($proto['campos'] as $c) {
            $v = $r[$c['k']] ?? '';
            if (($c['tipo'] ?? '') === 'cronograma') {
                $cron = [$c, is_array($v) ? $v : []];
                continue;
            }
            if (is_string($v) && trim($v) === '' && !empty($c['vacio'])) {
                $v = $c['vacio'];
            }
            if (!isset($c['h']) && !in_array($c['tipo'] ?? '', ['fecha'], true)) {
                continue;
            }
            $etiqueta = $c['h'] ?? $c['label'];
            if (($c['tipo'] ?? '') === 'fecha') {
                $pdf->SetFont('Helvetica', 'B', 10);
                $pdf->Cell(0, 5.5, self::l1($etiqueta . ': ' . ($v !== '' ? ProtocoloMembretado::fechaLarga((string) $v) : '—')), 0, 1);
                continue;
            }
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->MultiCell(0, 5, self::l1($etiqueta), 0, 'L');
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->MultiCell(0, 5, self::l1(trim((string) $v) !== '' ? (string) $v : '—'), 0, 'J');
            $pdf->Ln(2);
        }
        if ($cron !== null) {
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(0, 6, self::l1($cron[0]['h'] ?? 'Cronograma de actividades'), 0, 1);
            $pdf->SetFont('Helvetica', 'B', 7);
            $pdf->Cell(66, 5, 'Actividad', 1, 0, 'C');
            for ($b = 1; $b <= 12; $b++) {
                $pdf->Cell(8.8, 5, (string) $b, 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Helvetica', '', 7.5);
            foreach ($cron[1] as $f) {
                if (trim((string) ($f['a'] ?? '')) === '') {
                    continue;
                }
                $pdf->Cell(66, 5, self::l1(mb_strimwidth((string) $f['a'], 0, 48, '…')), 1);
                for ($b = 1; $b <= 12; $b++) {
                    $pdf->Cell(8.8, 5, in_array($b, (array) ($f['b'] ?? []), true) ? 'X' : '', 1, 0, 'C');
                }
                $pdf->Ln();
            }
            $pdf->Ln(3);
        }
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->MultiCell(0, 4.5, self::l1('NOTA: El tema tendrá una vigencia de dos años, a partir de la fecha de aceptación (Art. 86, Fracc. VIII, del Reglamento de Evaluación Profesional).'), 0, 'J');
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 5, self::l1('DATOS DEL PASANTE'), 0, 1);
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->Cell(0, 5, self::l1('Correo: ' . ($p['correo_pers'] ?? '—') . '   Teléfono: ' . ($p['telefono'] ?? '—')), 0, 1);

        // Bloque de firmas (se mantiene junto en una sola página).
        if ($pdf->GetY() > 215) {
            $pdf->AddPage();
        }
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Vo. Bo.', 0, 1, 'C');
        $y = $pdf->GetY() + 26;
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(24, $y, 98, $y);
        $pdf->Line(118, $y, 192, $y);
        $pdf->SetXY(24, $y + 1);
        $pdf->Cell(74, 5, 'Nombre y firma del pasante', 0, 0, 'C');
        $pdf->SetXY(118, $y + 1);
        $pdf->Cell(74, 5, 'Nombre y firma del asesor', 0, 0, 'C');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->SetXY(24, $y + 6);
        $pdf->Cell(74, 5, self::l1(mb_strimwidth($nombre, 0, 44, '…')), 0, 0, 'C');
        $pdf->SetXY(118, $y + 6);
        $pdf->Cell(74, 5, self::l1(mb_strimwidth((string) ($r['asesor'] ?? ''), 0, 44, '…')), 0, 0, 'C');
        $pagina = $pdf->PageNo() - 1;
        $mm = 72 / 25.4;
        $cajas = [
            'pasante' => ['p' => $pagina, 'x' => 24 * $mm, 'y' => $y * $mm, 'w' => 74 * $mm],
            'asesor' => ['p' => $pagina, 'x' => 118 * $mm, 'y' => $y * $mm, 'w' => 74 * $mm],
        ];
        return [$pdf->Output('S'), $cajas];
    }
}
