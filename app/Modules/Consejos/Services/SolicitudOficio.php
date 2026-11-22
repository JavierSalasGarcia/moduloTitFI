<?php

declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use App\Core\App;
use App\Core\Database;
use App\Modules\Titulacion\Services\FirmaElectronica;
use App\Modules\Titulacion\Services\Hoja;

/**
 * Oficio de la solicitud sobre la hoja membretada. Lleva la solicitud, los antecedentes, el fundamento del acuerdo AG28 y los
 * bloques de firma de quien solicita y del Vo. Bo. de la Coordinación. Con firma autógrafa se imprime y se sube escaneado; con la
 * firma interna se estampa el sello de conformidad (huella del documento) de cada quien en cuanto actúa.
 */
final class SolicitudOficio
{
    private static function almacen(): string
    {
        return rtrim((string) App::config('archivos.storage'), '/\\');
    }

    /** Arma el PDF vigente de la solicitud (con los sellos internos que correspondan) y guarda su ruta. @return string ruta relativa */
    public static function regenerar(array $s): string
    {
        $dir = 'consejos/solicitudes/' . (int) $s['id'];
        $abs = self::almacen() . '/' . $dir;
        if (!is_dir($abs)) {
            mkdir($abs, 0750, true);
        }
        $base = $abs . '/base.pdf';
        file_put_contents($base, self::pdf($s));
        $actual = $base;
        if (!empty($s['enviado_en']) && ($s['modo_solicitante'] ?? '') === 'interna') {
            $sal = $abs . '/solicitante.pdf';
            FirmaElectronica::sellar($actual, $sal, ['etiqueta' => 'Solicita', 'nombre' => Solicitudes::nombreCompleto((int) $s['solicitante_id']), 'folio' => (string) $s['folio'], 'posicion' => 'abajo_izquierda', 'pagina' => 'ultima']);
            $actual = $sal;
        }
        if (!empty($s['vobo_en']) && ($s['modo_vobo'] ?? '') === 'interna') {
            $sal = $abs . '/vobo.pdf';
            FirmaElectronica::sellar($actual, $sal, ['etiqueta' => $s['vobo_tipo'] === 'enterado' ? 'Enterado' : 'Vo. Bo.', 'nombre' => Solicitudes::nombreCompleto((int) $s['vobo_por']), 'folio' => (string) $s['folio'], 'posicion' => 'abajo_derecha', 'pagina' => 'ultima']);
            $actual = $sal;
        }
        $final = $abs . '/oficio-' . bin2hex(random_bytes(4)) . '.pdf';
        copy($actual, $final);
        foreach (glob($abs . '/oficio-*.pdf') ?: [] as $viejo) {
            if ($viejo !== $final) {
                @unlink($viejo);
            }
        }
        $rel = $dir . '/' . basename($final);
        Database::ejecutar('UPDATE consejos_solicitudes SET oficio_ruta = ? WHERE id = ?', [$rel, (int) $s['id']]);
        return $rel;
    }

    private static function pdf(array $s): string
    {
        $pdf = Hoja::nueva('', true);
        $pdf->lugarFecha(date('Y-m-d'));
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->Cell(0, 5, Hoja::l1('Oficio: ' . $s['folio']), 0, 1, 'R');
        $pdf->Cell(0, 5, Hoja::l1('Asunto: Solicitud a los HH. Consejos Académico y de Gobierno'), 0, 1, 'R');
        $pdf->Ln(3);
        $pdf->destinatario(['H.H. Consejos Académico y de Gobierno', 'Facultad de Ingeniería, UAEMéx']);

        self::bloque($pdf, (string) $s['solicitud_html']);
        $ant = trim(Html::texto((string) $s['antecedentes_html']));
        if ($ant !== '') {
            $pdf->Ln(2);
            $pdf->texto('Documentos y/o antecedentes', 'B', 10);
            self::bloque($pdf, (string) $s['antecedentes_html']);
        }
        $anexos = Adjuntos::de('solicitud_id', (int) $s['id']);
        if ($anexos) {
            $pdf->Ln(1);
            $pdf->texto('Anexos: ' . Adjuntos::lista($anexos) . '.', '', 9.5);
        }
        $pdf->Ln(2);
        $pdf->texto(Solicitudes::fundamento(), 'I', 8.5);
        $pdf->Ln(3);
        $pdf->texto('Sin otro particular, quedo de ustedes.', '', 10);

        $uc = Database::una('SELECT nombre, grado FROM usuarios WHERE id = ?', [(int) $s['coordinador_id']]);
        $us = Database::una('SELECT nombre, grado FROM usuarios WHERE id = ?', [(int) $s['solicitante_id']]);
        $nombre = static fn(?array $u): string => $u ? Solicitudes::conGrado($u) : '';
        $pdf->firmas([
            ['rol' => 'solicitante', 'arriba' => 'ATENTAMENTE', 'nombre' => $nombre($us), 'cargo' => ['Solicitante']],
            ['rol' => 'vobo', 'arriba' => 'Vo. Bo. / De enterado', 'nombre' => $nombre($uc), 'cargo' => [(string) $s['coordinacion']]],
        ], 20.0);
        return $pdf->Output('S');
    }

    /** Párrafos y tablas del HTML del caso. */
    private static function bloque(Hoja $pdf, string $html): void
    {
        $tablas = [];
        $sinTablas = preg_replace_callback('~<table\b.*?</table>~is', static function (array $m) use (&$tablas): string {
            $tablas[] = $m[0];
            return '<p>@@T' . (count($tablas) - 1) . '@@</p>';
        }, $html) ?? $html;
        foreach (preg_split('/\n+/', Html::texto($sinTablas)) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            if (preg_match('/^@@T(\d+)@@$/', $linea, $m)) {
                self::tabla($pdf, self::filas($tablas[(int) $m[1]]));
                $pdf->Ln(2);
                continue;
            }
            $pdf->texto($linea, '', 10);
            $pdf->Ln(1);
        }
    }

    /** @return list<list<string>> */
    private static function filas(string $html): array
    {
        $r = [];
        if (preg_match_all('~<tr\b.*?</tr>~is', $html, $trs)) {
            foreach ($trs[0] as $tr) {
                preg_match_all('~<t[dh]\b.*?</t[dh]>~is', $tr, $celdas);
                $fila = array_map(static fn(string $c): string => trim(preg_replace('/\s+/u', ' ', Html::texto($c)) ?? ''), $celdas[0]);
                if ($fila) {
                    $r[] = $fila;
                }
            }
        }
        return $r;
    }

    /** @param list<list<string>> $filas */
    private static function tabla(Hoja $pdf, array $filas): void
    {
        if (!$filas) {
            return;
        }
        $n = max(array_map('count', $filas));
        $w = 166.0 / $n;
        foreach ($filas as $i => $f) {
            $pdf->SetFont('Helvetica', $i === 0 ? 'B' : '', 8.5);
            $lineas = 1;
            foreach ($f as $c) {
                $lineas = max($lineas, $pdf->renglones($w - 2, Hoja::l1($c)));
            }
            $alto = $lineas * 4.0 + 2.0;
            if ($pdf->GetY() + $alto > 279.4 - 30) {
                $pdf->AddPage();
            }
            $y = $pdf->GetY();
            foreach ($f as $j => $c) {
                $pdf->Rect(25 + $j * $w, $y, $w, $alto);
                $pdf->SetXY(25 + $j * $w + 1, $y + 1);
                $pdf->MultiCell($w - 2, 4.0, Hoja::l1($c), 0, 'L');
            }
            $pdf->SetXY(25, $y + $alto);
        }
    }
}
