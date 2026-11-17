<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

use App\Core\App;
use App\Core\LemaAnual;
use App\Modules\Titulacion\Services\Hoja;
use App\Modules\Titulacion\Services\Qr;

/**
 * PDF de la constancia sobre la hoja membretada de la Facultad (Titulacion\Services\Hoja): lugar y fecha, número de
 * oficio (folio), asunto, destinatario, cuerpo (párrafos con negritas y tablas con ajuste de texto en celdas),
 * «A T E N T A M E N T E», lema y leyenda del año (LemaAnual) y bloque de firma. El QR de verificación propio del
 * módulo (constancias/verificar/{código}) y el folio se estampan en el pie de cada hoja en una segunda pasada con
 * FPDI, porque el QR de Hoja está ligado a los trámites de Titulación.
 */
final class Pdf
{
    private const IZQ = 25.0;
    private const ANCHO = 165.9;
    private const LIMITE = 279.4 - 30.0;   // borde inferior del texto (deja libre el pie con el QR)
    private const TAM = 10.5;
    private const ALTO = 5.4;

    public static function urlVerificacion(string $codigo): string
    {
        return rtrim((string) App::config('app.url_publica', 'https://fingenieria.mx'), '/') . '/constancias/verificar/' . $codigo;
    }

    /**
     * @param array $d asunto, dirigido_a, bloques, firmante_nombre, firmante_cargo, departamento, folio, codigo, fecha (Y-m-d)
     * @return array{0:string,1:array{p:int,x:float,y:float}} bytes del PDF y caja del sello (mm; p desde 0)
     */
    public static function generar(array $d, bool $vistaPrevia): array
    {
        $pdf = Hoja::nueva('', true);
        $pdf->SetAutoPageBreak(true, 30);
        $pdf->SetY(40);
        $pdf->SetFont('Helvetica', '', self::TAM);
        $pdf->Cell(0, 5, Hoja::l1('Ciudad Universitaria, Toluca, Méx., ' . Hoja::fecha((string) ($d['fecha'] ?? date('Y-m-d')))), 0, 1, 'R');
        $pdf->SetFont('Helvetica', 'B', self::TAM);
        $pdf->Cell(0, 5, Hoja::l1('Oficio No. ' . $d['folio']), 0, 1, 'R');
        $asunto = trim((string) ($d['asunto'] ?? '')) ?: 'CONSTANCIA';
        $pdf->Cell(0, 5, Hoja::l1('ASUNTO: ' . mb_strtoupper($asunto)), 0, 1, 'R');
        $pdf->Ln(6);

        $lineas = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($d['dirigido_a'] ?? '')) ?: []), static fn(string $l): bool => $l !== ''));
        if (!$lineas) {
            $lineas = ['A QUIEN CORRESPONDA'];
        }
        $pdf->SetFont('Helvetica', 'B', self::TAM);
        if (count($lineas) === 1 && preg_match('/^a quien corresponda:?$/iu', $lineas[0])) {
            $pdf->Cell(0, 5.5, 'A QUIEN CORRESPONDA:', 0, 1);
        } else {
            foreach ($lineas as $i => $l) {
                // La primera línea (el nombre) va en mayúsculas; las siguientes (cargo, dependencia) como se escribieron.
                $pdf->SetFont('Helvetica', $i === 0 ? 'B' : '', self::TAM);
                $pdf->MultiCell(0, 5, Hoja::l1($i === 0 ? mb_strtoupper($l) : $l), 0, 'L');
            }
            $pdf->SetFont('Helvetica', 'B', self::TAM);
            $pdf->Cell(0, 5.5, 'P R E S E N T E', 0, 1);
        }
        $pdf->Ln(4);

        foreach (Cuerpo::sanear((array) ($d['bloques'] ?? [])) as $i => $b) {
            if ($i > 0) {
                $pdf->Ln(2.6);
            }
            if ($b['t'] === 'p') {
                self::parrafo($pdf, $b['runs'], $b['alinear']);
            } else {
                $pdf->Ln(0.6);
                self::tabla($pdf, $b);
                $pdf->Ln(0.6);
            }
        }
        $caja = self::firma($pdf, (string) $d['firmante_nombre'], array_values(array_filter([(string) $d['firmante_cargo'], 'Facultad de Ingeniería, UAEMéx'])));
        $bytes = (string) $pdf->Output('S');
        return [self::pie($bytes, (string) $d['folio'], (string) $d['codigo'], $vistaPrevia), $caja];
    }

    // ------------------------------------------------------------------ párrafos con negritas y justificación

    /** Asegura espacio para un renglón de $alto; si no cabe, nueva hoja. */
    private static function espacio(Hoja $pdf, float $alto): void
    {
        if ($pdf->GetY() + $alto > self::LIMITE) {
            $pdf->AddPage();
            $pdf->SetY(40);
        }
    }

    /** Ancho de un texto (UTF-8) con el estilo dado. */
    private static function ancho(Hoja $pdf, string $t, bool $negrita, float $tam): float
    {
        $pdf->SetFont('Helvetica', $negrita ? 'B' : '', $tam);
        return $pdf->GetStringWidth(Hoja::l1($t));
    }

    /**
     * Párrafo con fragmentos en negritas. FPDF solo justifica MultiCell de un solo estilo, así que aquí se arman los
     * renglones palabra por palabra y se reparte el espacio sobrante entre palabras (salvo en el último renglón).
     * @param array<int,array{0:string,1:bool}> $runs
     */
    private static function parrafo(Hoja $pdf, array $runs, string $alinear, float $tam = self::TAM, float $alto = self::ALTO): void
    {
        // Palabras: listas de segmentos [texto, negrita]; null marca un salto de línea forzado.
        $palabras = [];
        $actual = [];
        foreach ($runs as [$texto, $negrita]) {
            foreach (preg_split('/( |\n)/u', (string) $texto, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $pieza) {
                if ($pieza === ' ' || $pieza === "\n") {
                    if ($actual) {
                        $palabras[] = $actual;
                        $actual = [];
                    }
                    if ($pieza === "\n") {
                        $palabras[] = null;
                    }
                    continue;
                }
                if ($pieza !== '') {
                    $actual[] = [$pieza, (bool) $negrita];
                }
            }
        }
        if ($actual) {
            $palabras[] = $actual;
        }
        $espacio = self::ancho($pdf, ' ', false, $tam);
        $renglones = [];
        $r = [];
        $w = 0.0;
        foreach ($palabras as $p) {
            if ($p === null) {
                $renglones[] = [$r, true];
                $r = [];
                $w = 0.0;
                continue;
            }
            $pw = 0.0;
            foreach ($p as [$t, $b]) {
                $pw += self::ancho($pdf, $t, $b, $tam);
            }
            if ($pw > self::ANCHO) {
                // Palabra más ancha que el renglón (una URL larga): se corta por caracteres.
                foreach (self::cortar($pdf, $p, $tam) as $trozo) {
                    if ($r) {
                        $renglones[] = [$r, false];
                    }
                    $r = [[$trozo[0], $trozo[1]]];
                    $w = $trozo[1];
                }
                continue;
            }
            if ($r && $w + $espacio + $pw > self::ANCHO) {
                $renglones[] = [$r, false];
                $r = [];
                $w = 0.0;
            }
            $w += ($r ? $espacio : 0) + $pw;
            $r[] = [$p, $pw];
        }
        $renglones[] = [$r, true];

        foreach ($renglones as [$ren, $ultimo]) {
            self::espacio($pdf, $alto);
            $y = $pdf->GetY();
            $total = 0.0;
            foreach ($ren as [, $pw]) {
                $total += $pw;
            }
            $huecos = max(0, count($ren) - 1);
            $natural = $total + $huecos * $espacio;
            $sep = $espacio;
            $x = self::IZQ;
            if ($alinear === 'J' && !$ultimo && $huecos > 0) {
                $sep = (self::ANCHO - $total) / $huecos;
            } elseif ($alinear === 'C') {
                $x += (self::ANCHO - $natural) / 2;
            } elseif ($alinear === 'R') {
                $x += self::ANCHO - $natural;
            }
            foreach ($ren as [$p, $pw]) {
                foreach ($p as [$t, $b]) {
                    $pdf->SetFont('Helvetica', $b ? 'B' : '', $tam);
                    $tw = $pdf->GetStringWidth(Hoja::l1($t));
                    $pdf->SetXY($x, $y);
                    $pdf->Cell($tw, $alto, Hoja::l1($t), 0, 0, 'L');
                    $x += $tw;
                }
                $x += $sep;
            }
            $pdf->SetXY(self::IZQ, $y + $alto);
        }
        $pdf->SetFont('Helvetica', '', $tam);
    }

    /** Corta una palabra demasiado larga en trozos que caben en el renglón. @return array<int,array{0:array,1:float}> */
    private static function cortar(Hoja $pdf, array $palabra, float $tam): array
    {
        $trozos = [];
        $seg = [];
        $w = 0.0;
        foreach ($palabra as [$t, $b]) {
            foreach (mb_str_split($t) as $ch) {
                $cw = self::ancho($pdf, $ch, $b, $tam);
                if ($w + $cw > self::ANCHO && $seg) {
                    $trozos[] = [$seg, $w];
                    $seg = [];
                    $w = 0.0;
                }
                $n = count($seg);
                if ($n > 0 && $seg[$n - 1][1] === $b) {
                    $seg[$n - 1][0] .= $ch;
                } else {
                    $seg[] = [$ch, $b];
                }
                $w += $cw;
            }
        }
        if ($seg) {
            $trozos[] = [$seg, $w];
        }
        return $trozos;
    }

    // ------------------------------------------------------------------ tablas

    /** Tabla con bordes; anchos según el contenido, texto ajustado en cada celda y encabezado repetido al cambiar de hoja. */
    private static function tabla(Hoja $pdf, array $b): void
    {
        $filas = $b['filas'];
        $n = count($filas[0] ?? []);
        if ($n === 0) {
            return;
        }
        $tam = $n > 5 ? 8.0 : 9.0;
        $alto = $n > 5 ? 3.8 : 4.3;
        $natural = array_fill(0, $n, 8.0);
        $minimo = array_fill(0, $n, 8.0);
        foreach ($filas as $k => $f) {
            foreach ($f as $i => $c) {
                foreach (explode("\n", (string) $c['texto']) as $linea) {
                    $natural[$i] = max($natural[$i], self::ancho($pdf, $linea, $c['negrita'] || ($k === 0 && !empty($b['encabezado'])), $tam) + 5);
                    foreach (preg_split('/\s+/u', $linea) ?: [] as $palabra) {
                        $minimo[$i] = max($minimo[$i], min(45.0, self::ancho($pdf, $palabra, $c['negrita'] || ($k === 0 && !empty($b['encabezado'])), $tam) + 5));
                    }
                }
            }
        }
        $suma = array_sum($natural);
        if ($suma <= self::ANCHO) {
            $anchos = array_map(static fn(float $w): float => $w * self::ANCHO / $suma, $natural);
        } else {
            // Cada columna recibe al menos el ancho de su palabra más larga; el resto se reparte según su contenido.
            $base = array_sum($minimo);
            if ($base >= self::ANCHO) {
                $anchos = array_map(static fn(float $w): float => $w * self::ANCHO / $base, $minimo);
            } else {
                $extra = array_map(static fn(float $a, float $m): float => max(0.0, $a - $m), $natural, $minimo);
                $sumaExtra = array_sum($extra) ?: 1.0;
                $anchos = array_map(static fn(float $m, float $e): float => $m + $e * (self::ANCHO - $base) / $sumaExtra, $minimo, $extra);
            }
        }
        $encabezado = !empty($b['encabezado']);
        $pintar = static function (array $fila, bool $esEncabezado) use ($pdf, $anchos, $tam, $alto): void {
            $lineas = 1;
            foreach ($fila as $i => $c) {
                $pdf->SetFont('Helvetica', ($c['negrita'] || $esEncabezado) ? 'B' : '', $tam);
                $lineas = max($lineas, $pdf->renglones($anchos[$i] - 1, Hoja::l1((string) $c['texto'] !== '' ? (string) $c['texto'] : ' ')));
            }
            $h = $lineas * $alto + 2;
            if ($pdf->GetY() + $h > self::LIMITE) {
                $pdf->AddPage();
                $pdf->SetY(40);
            }
            $y = $pdf->GetY();
            $x = self::IZQ;
            $pdf->SetDrawColor(90, 90, 90);
            $pdf->SetLineWidth(0.2);
            $pdf->SetFillColor(230, 236, 231);
            foreach ($fila as $i => $c) {
                $pdf->Rect($x, $y, $anchos[$i], $h, $esEncabezado ? 'DF' : 'D');
                $pdf->SetFont('Helvetica', ($c['negrita'] || $esEncabezado) ? 'B' : '', $tam);
                $pdf->SetXY($x + 0.5, $y + 1);
                $pdf->MultiCell($anchos[$i] - 1, $alto, Hoja::l1((string) $c['texto']), 0, $esEncabezado ? 'C' : 'L');
                $x += $anchos[$i];
            }
            $pdf->SetXY(self::IZQ, $y + $h);
        };
        $primera = true;
        foreach ($filas as $k => $fila) {
            if ($k === 0 && $encabezado) {
                $pintar($fila, true);
                continue;
            }
            // Si la fila salta de hoja, se repite el encabezado arriba.
            if ($encabezado && !$primera) {
                $lineas = 1;
                foreach ($fila as $i => $c) {
                    $pdf->SetFont('Helvetica', $c['negrita'] ? 'B' : '', $tam);
                    $lineas = max($lineas, $pdf->renglones($anchos[$i] - 1, Hoja::l1((string) $c['texto'] !== '' ? (string) $c['texto'] : ' ')));
                }
                if ($pdf->GetY() + $lineas * $alto + 2 > self::LIMITE) {
                    $pdf->AddPage();
                    $pdf->SetY(40);
                    $pintar($filas[0], true);
                }
            }
            $primera = false;
            $pintar($fila, false);
        }
        $pdf->SetFont('Helvetica', '', self::TAM);
    }

    // ------------------------------------------------------------------ firma y pie

    /** «A T E N T A M E N T E», lema, leyenda del año y bloque de firma; devuelve dónde va el sello. */
    private static function firma(Hoja $pdf, string $nombre, array $cargo): array
    {
        $lema = count(LemaAnual::lineas());
        $necesario = 6 + 10 + $lema * 4.5 + 24 + 5 + 4.8 * count($cargo);
        if ($pdf->GetY() + $necesario > self::LIMITE) {
            $pdf->AddPage();
            $pdf->SetY(40);
        }
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', self::TAM);
        $pdf->Cell(0, 5, 'A T E N T A M E N T E', 0, 1, 'C');
        $pdf->Cell(0, 5, Hoja::l1('PATRIA, CIENCIA Y TRABAJO'), 0, 1, 'C');
        LemaAnual::imprimir($pdf);
        $pdf->Ln(24);
        $y = $pdf->GetY();
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(65, $y - 1, 150.9, $y - 1);
        $pdf->SetFont('Helvetica', 'B', self::TAM);
        $pdf->MultiCell(0, 5, Hoja::l1(mb_strtoupper($nombre)), 0, 'C');
        $pdf->SetFont('Helvetica', '', 10);
        foreach ($cargo as $c) {
            $pdf->MultiCell(0, 4.8, Hoja::l1($c), 0, 'C');
        }
        return ['p' => $pdf->PageNo() - 1, 'x' => 107.95, 'y' => $y - 1.5];
    }

    /** Segunda pasada: QR de verificación, folio y número de hoja en el pie de cada página; aviso de vista previa. */
    private static function pie(string $bytes, string $folio, string $codigo, bool $vistaPrevia): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'cst');
        file_put_contents($tmp, $bytes);
        try {
            $pdf = new \setasign\Fpdi\Fpdi();
            $pdf->SetAutoPageBreak(false);
            $total = $pdf->setSourceFile($tmp);
            $url = self::urlVerificacion($codigo);
            for ($i = 1; $i <= $total; $i++) {
                $tpl = $pdf->importPage($i);
                $dim = $pdf->getTemplateSize($tpl);
                $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
                $pdf->useTemplate($tpl);
                $w = (float) $dim['width'];
                $h = (float) $dim['height'];
                $lado = 17.0;
                Qr::dibujar($pdf, $url, $w - 8 - $lado, $h - 5 - $lado, $lado);
                $fin = $w - 8 - $lado - 1;
                $pdf->SetFont('Helvetica', '', 7);
                $pdf->SetTextColor(90, 90, 90);
                $pdf->SetXY($fin - 100, $h - 17);
                $pdf->Cell(100, 4, Hoja::l1('Verifique esta constancia con el código QR'), 0, 0, 'R');
                $pdf->SetXY($fin - 100, $h - 13);
                $pdf->Cell(100, 4, Hoja::l1('Folio: ' . $folio . ($total > 1 ? '  ·  Hoja ' . $i . ' de ' . $total : '')), 0, 0, 'R');
                if ($vistaPrevia) {
                    $pdf->SetFont('Helvetica', 'B', 8);
                    $pdf->SetTextColor(170, 30, 30);
                    $pdf->SetXY($fin - 100, $h - 9);
                    $pdf->Cell(100, 4, Hoja::l1('VISTA PREVIA · sin validez hasta que se firme'), 0, 0, 'R');
                }
                $pdf->SetTextColor(25, 25, 25);
            }
            return (string) $pdf->Output('S');
        } finally {
            @unlink($tmp);
        }
    }
}
