<?php
declare(strict_types=1);

namespace App\Modules\Oficios\Services;

use App\Core\LemaAnual;
use App\Modules\Titulacion\Services\Qr;
use DOMDocument;
use DOMElement;
use DOMNode;

if (!class_exists('FPDF', false)) {
    if (!defined('FPDF_FONTPATH')) {
        define('FPDF_FONTPATH', dirname(APP_RAIZ) . '/lib/fpdf/font/');
    }
    require_once dirname(APP_RAIZ) . '/lib/fpdf/fpdf.php';
}
require_once dirname(APP_RAIZ) . '/lib/fpdi/autoload.php';

/**
 * Oficio de la Subdirección Académica en hoja membretada, con DM Sans: fecha y número, asunto, destinatarios, cuerpo (párrafos con negritas
 * y cursivas y tablas pegadas), «Atentamente», lema y leyenda del año, espacio para el sello de la e.firma, nombre y cargo del titular
 * (en mayúsculas, «DE LA FACULTAD DE INGENIERÍA») y el QR de verificación en el pie. Interlineado sencillo y 0.8 pt después de cada párrafo.
 */
final class OficioPdf extends \setasign\Fpdi\Fpdi
{
    public const PT = 0.352778;            // mm por punto
    public const INTERLINEADO = 1.302;     // «sencillo» de DM Sans: (ascender 992 + descender 310) / 1000
    public const DESPUES_PT = 0.8;         // espacio después de cada párrafo, en puntos
    public const CUERPO_PT = 11.0;

    private $fondo;
    private string $url = '';
    private string $pie = '';
    private bool $borrador = false;

    /**
     * @param array{numero_texto:?string,fecha:string,asunto:string,destinatarios:array,cuerpo_html:string,codigo:string,url:string,borrador:bool,
     *              firmante:array{nombre:string,cargo:string[]},sello:?string[]} $o
     */
    public static function generar(array $o): string
    {
        $pdf = new self('P', 'mm', 'Letter');
        $pdf->setSourceFile(dirname(__DIR__, 2) . '/Titulacion/plantillas/pdf/Membretada.pdf');
        $pdf->fondo = $pdf->importPage(1);
        $pdf->url = (string) $o['url'];
        $pdf->borrador = (bool) $o['borrador'];
        $pdf->pie = 'Oficio ' . ($o['numero_texto'] ?: 'sin número');
        $pdf->SetMargins(25, 36, 25);
        $pdf->SetAutoPageBreak(true, 26);
        $pdf->AddPage();
        $pdf->SetTextColor(25, 25, 25);
        $pdf->SetTitle('Oficio ' . ($o['numero_texto'] ?: ''), true);
        $pdf->SetAuthor((string) $o['firmante']['nombre'], true);
        $pdf->SetCreator('Traza FI · Facultad de Ingeniería', true);

        $pdf->SetY(40);
        $pdf->linea('Toluca, Estado de México, a ' . self::fechaLarga((string) $o['fecha']), '', 11, 'R');
        $pdf->linea('Oficio No. ' . ($o['numero_texto'] ?: 'ACAD/FI/___/' . substr((string) $o['fecha'], 0, 4)), 'B', 11, 'R');
        $pdf->parrafo([['Asunto: ', 'B'], [(string) $o['asunto'], 'B']], 11, 'L', self::DESPUES_PT, 85.0);
        $pdf->Ln(4);

        foreach ($o['destinatarios'] as $d) {
            $pdf->linea(mb_strtoupper((string) $d['nombre']), 'B');
            if (trim((string) ($d['cargo'] ?? '')) !== '') {
                $pdf->linea((string) $d['cargo']);
            }
            if (trim((string) ($d['dependencia'] ?? '')) !== '') {
                $pdf->linea((string) $d['dependencia']);
            }
            $pdf->Ln(2);
        }
        $pdf->linea(count($o['destinatarios']) > 1 ? 'P R E S E N T E S' : 'P R E S E N T E', 'B');
        $pdf->Ln(3);

        $pdf->cuerpo((string) $o['cuerpo_html']);

        // Cierre: no se parte entre páginas.
        $pdf->Ln(5);
        if ($pdf->GetY() > 175) {
            $pdf->AddPage();
            $pdf->SetY(40);
        }
        $pdf->linea('A T E N T A M E N T E', 'B', 11, 'C');
        $pdf->linea('PATRIA, CIENCIA Y TRABAJO', 'B', 11, 'C');
        $lema = LemaAnual::lineas((int) substr((string) $o['fecha'], 0, 4) ?: null);
        if ($lema) {
            $lema[0] = '“' . ltrim($lema[0], '"“«');
            $lema[count($lema) - 1] = rtrim($lema[count($lema) - 1], '"”»') . '”';
            foreach ($lema as $l) {
                $pdf->linea($l, 'I', 8.5, 'C');
            }
        }
        $pdf->Ln(8);
        $y = $pdf->GetY();
        if (!empty($o['sello'])) {
            $pdf->SetFont('DMSans', '', 7);
            $pdf->SetTextColor(70, 70, 70);
            foreach ($o['sello'] as $k => $linea) {
                $pdf->SetXY(25, $y + 1 + $k * 3.6);
                $pdf->Cell(165.9, 3.4, $linea, 0, 0, 'C');
            }
            $pdf->SetTextColor(25, 25, 25);
        }
        $pdf->SetY($y + 17);
        $pdf->linea((string) $o['firmante']['nombre'], 'B', 11, 'C');
        foreach ($o['firmante']['cargo'] as $c) {
            $pdf->linea((string) $c, 'B', 10, 'C');
        }
        return $pdf->Output('S');
    }

    private static function fechaLarga(string $iso): string
    {
        $meses = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso) ?: new \DateTimeImmutable('today');
        return $f->format('j') . ' de ' . $meses[(int) $f->format('n')] . ' de ' . $f->format('Y');
    }

    // ------------------------------------------------------------------ hoja

    public function Header(): void
    {
        if ($this->fondo !== null) {
            $this->useTemplate($this->fondo);
        }
    }

    public function Footer(): void
    {
        $lado = 17.0;
        Qr::dibujar($this, $this->url, $this->w - 8 - $lado, $this->h - 5 - $lado, $lado);
        $this->SetFont('DMSans', '', 7);
        $this->SetTextColor(90, 90, 90);
        $x = $this->w - 8 - $lado - 1 - 90;
        $this->SetXY($x, -13);
        $this->Cell(90, 4, 'Verifique este documento con el código QR', 0, 0, 'R');
        $this->SetXY($x, -9);
        $this->Cell(90, 4, $this->pie . ' · Página ' . $this->PageNo(), 0, 0, 'R');
        if ($this->borrador) {
            $this->SetTextColor(180, 30, 30);
            $this->SetFont('DMSans', 'B', 7);
            $this->SetXY($x, -17);
            $this->Cell(90, 4, 'VISTA PREVIA · sin validez hasta que se firme', 0, 0, 'R');
        }
        $this->SetTextColor(25, 25, 25);
    }

    // ------------------------------------------------------------------ texto

    private function alto(float $pt): float
    {
        return $pt * self::INTERLINEADO * self::PT;
    }

    /** Una línea (sin ajuste) con 0.8 pt después. */
    public function linea(string $texto, string $est = '', float $pt = 11, string $alinea = 'L'): void
    {
        $this->SetFont('DMSans', $est, $pt);
        $lh = $this->alto($pt);
        if ($this->GetY() + $lh > $this->PageBreakTrigger) {
            $this->AddPage();
        }
        $this->Cell(0, $lh, $texto, 0, 1, $alinea);
        $this->SetY($this->GetY() + self::DESPUES_PT * self::PT);
    }

    /**
     * Párrafo con negritas y cursivas, justificado, interlineado sencillo y 0.8 pt después.
     * @param array<int,array{0:string,1:string}> $runs [texto, estilo '', 'B', 'I', 'BI']
     */
    public function parrafo(array $runs, float $pt = self::CUERPO_PT, string $alinea = 'J', float $despuesPt = self::DESPUES_PT, float $sangria = 0.0): void
    {
        $lh = $this->alto($pt);
        $x0 = $this->lMargin + $sangria;
        $ancho = $this->w - $x0 - $this->rMargin;
        // Palabras: cada una es una lista de piezas [texto, estilo]; los runs pegados forman una sola palabra.
        $palabras = [];
        $abierta = false;
        foreach ($runs as [$texto, $est]) {
            foreach (preg_split('/(\s+)/u', $texto, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $z) {
                if (trim($z) === '') {
                    $abierta = false;
                    continue;
                }
                if ($abierta) {
                    $palabras[count($palabras) - 1][] = [$z, $est];
                } else {
                    $palabras[] = [[$z, $est]];
                    $abierta = true;
                }
            }
        }
        $ancha = function (array $pzs) use ($pt): float {
            $w = 0.0;
            foreach ($pzs as [$z, $e]) {
                $this->SetFont('DMSans', $e, $pt);
                $w += $this->GetStringWidth($z);
            }
            return $w;
        };
        $this->SetFont('DMSans', '', $pt);
        $espN = $this->GetStringWidth(' ');
        $lineas = [];
        $actual = [];
        $usado = 0.0;
        foreach ($palabras as $pzs) {
            $w = $ancha($pzs);
            $necesita = $usado + ($actual ? $espN : 0) + $w;
            if ($actual && $necesita > $ancho) {
                $lineas[] = $actual;
                $actual = [];
                $necesita = $w;
            }
            $actual[] = [$pzs, $w];
            $usado = $necesita;
        }
        if ($actual) {
            $lineas[] = $actual;
        }
        foreach ($lineas as $i => $linea) {
            if ($this->GetY() + $lh > $this->PageBreakTrigger) {
                $this->AddPage();
            }
            $suma = array_sum(array_column($linea, 1));
            $huecos = max(1, count($linea) - 1);
            $ultima = $i === count($lineas) - 1;
            $esp = $espN;
            if ($alinea === 'J' && !$ultima && count($linea) > 1) {
                $esp = ($ancho - $suma) / $huecos;
                $x = $x0;
            } else {
                $largo = $suma + $espN * (count($linea) - 1);
                $x = $alinea === 'C' ? $x0 + ($ancho - $largo) / 2 : ($alinea === 'R' ? $x0 + $ancho - $largo : $x0);
            }
            $y = $this->GetY();
            foreach ($linea as [$pzs, $w]) {
                $xx = $x;
                foreach ($pzs as [$z, $e]) {
                    $this->SetFont('DMSans', $e, $pt);
                    $zw = $this->GetStringWidth($z);
                    $this->SetXY($xx, $y);
                    $this->Cell($zw, $lh, $z, 0, 0, 'L');
                    $xx += $zw;
                }
                $x += $w + $esp;
            }
            $this->SetY($y + $lh);
        }
        $this->SetY($this->GetY() + $despuesPt * self::PT);
    }

    private function partir(string $texto, float $ancho, string $est, float $pt): array
    {
        $this->SetFont('DMSans', $est, $pt);
        $r = [];
        foreach (preg_split('/\R/u', $texto) ?: [''] as $par) {
            $l = '';
            foreach (preg_split('/\s+/u', trim($par)) ?: [] as $p) {
                $prueba = $l === '' ? $p : $l . ' ' . $p;
                if ($l !== '' && $this->GetStringWidth($prueba) > $ancho) {
                    $r[] = $l;
                    $l = $p;
                } else {
                    $l = $prueba;
                }
            }
            $r[] = $l;
        }
        return $r;
    }

    /** Tabla pegada: la primera fila va en negritas y con fondo suave. @param array<int,array<int,string>> $filas */
    public function tabla(array $filas, float $pt = 10.0): void
    {
        $filas = array_values(array_filter($filas, static fn($f) => $f !== []));
        if (!$filas) {
            return;
        }
        $lh = $this->alto($pt);
        $ancho = $this->w - $this->lMargin - $this->rMargin;
        $cols = max(array_map('count', $filas));
        $pesos = array_fill(0, $cols, 1.0);
        foreach ($filas as $f) {
            foreach (array_values($f) as $c => $t) {
                $pesos[$c] = max($pesos[$c], min(3.0, mb_strlen((string) $t) / 12));
            }
        }
        $suma = array_sum($pesos);
        $anchos = array_map(static fn($p) => $ancho * $p / $suma, $pesos);
        $pad = 1.6;
        $this->SetDrawColor(120, 120, 120);
        $this->SetLineWidth(0.2);
        foreach ($filas as $n => $fila) {
            $fila = array_pad(array_values($fila), $cols, '');
            $est = $n === 0 ? 'B' : '';
            $partidas = [];
            $maxl = 1;
            foreach ($fila as $c => $t) {
                $partidas[$c] = $this->partir((string) $t, $anchos[$c] - 2 * $pad, $est, $pt);
                $maxl = max($maxl, count($partidas[$c]));
            }
            $alto = $maxl * $lh + 2 * $pad * 0.6;
            if ($this->GetY() + $alto > $this->PageBreakTrigger) {
                $this->AddPage();
            }
            $x = $this->lMargin;
            $y = $this->GetY();
            foreach ($fila as $c => $t) {
                if ($n === 0) {
                    $this->SetFillColor(238, 243, 238);
                    $this->Rect($x, $y, $anchos[$c], $alto, 'DF');
                } else {
                    $this->Rect($x, $y, $anchos[$c], $alto);
                }
                $this->SetFont('DMSans', $est, $pt);
                foreach ($partidas[$c] as $k => $l) {
                    $this->SetXY($x + $pad, $y + $pad * 0.6 + $k * $lh);
                    $this->Cell($anchos[$c] - 2 * $pad, $lh, $l, 0, 0, 'L');
                }
                $x += $anchos[$c];
            }
            $this->SetY($y + $alto);
        }
        $this->SetY($this->GetY() + self::DESPUES_PT * self::PT);
    }

    // ------------------------------------------------------------------ HTML del lienzo → PDF

    /** Convierte el HTML limpio del lienzo (p, b, i, br, tablas; las listas pasan a párrafos) en contenido del PDF. */
    public function cuerpo(string $html): void
    {
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return;
        }
        foreach ($body->childNodes as $n) {
            if (!$n instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($n->tagName);
            if ($tag === 'table') {
                $this->Ln(1.5);
                $this->tabla(self::filas($n));
                $this->Ln(1.5);
            } elseif ($tag === 'ul' || $tag === 'ol') {
                foreach ($n->childNodes as $li) {
                    if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                        $this->parrafoHtml($li, '•  ');
                    }
                }
            } else {
                $this->parrafoHtml($n);
            }
        }
    }

    private function parrafoHtml(DOMNode $n, string $prefijo = ''): void
    {
        // Un <br> parte el párrafo en renglones sueltos (sin el espacio posterior entre ellos).
        $tramos = [[]];
        $this->corridas($n, false, false, $tramos);
        $ultimo = count($tramos) - 1;
        foreach ($tramos as $i => $runs) {
            if ($i === 0 && $prefijo !== '') {
                array_unshift($runs, [$prefijo, '']);
            }
            if (trim(implode('', array_column($runs, 0))) === '') {
                continue;
            }
            $this->parrafo($runs, self::CUERPO_PT, 'J', $i === $ultimo ? self::DESPUES_PT : 0.0);
        }
    }

    /** @param array<int,array<int,array{0:string,1:string}>> $tramos */
    private function corridas(DOMNode $n, bool $b, bool $i, array &$tramos): void
    {
        foreach ($n->childNodes as $h) {
            if ($h instanceof \DOMText) {
                $t = (string) $h->nodeValue;
                if ($t !== '') {
                    $tramos[count($tramos) - 1][] = [$t, ($b ? 'B' : '') . ($i ? 'I' : '')];
                }
            } elseif ($h instanceof DOMElement) {
                $tag = strtolower($h->tagName);
                if ($tag === 'br') {
                    $tramos[] = [];
                } elseif ($tag === 'table' || $tag === 'ul' || $tag === 'ol') {
                    $tramos[] = [[mb_substr(self::texto($h), 0, 4000), '']];
                    $tramos[] = [];
                } else {
                    $this->corridas($h, $b || in_array($tag, ['b', 'strong'], true), $i || in_array($tag, ['i', 'em'], true), $tramos);
                }
            }
        }
    }

    /** @return array<int,array<int,string>> */
    private static function filas(DOMElement $t): array
    {
        $r = [];
        foreach ($t->getElementsByTagName('tr') as $tr) {
            $fila = [];
            foreach ($tr->childNodes as $c) {
                if ($c instanceof DOMElement && in_array(strtolower($c->tagName), ['td', 'th'], true)) {
                    $fila[] = trim(preg_replace('/[ \t]+/u', ' ', self::texto($c)) ?? '');
                }
            }
            if ($fila) {
                $r[] = $fila;
            }
        }
        return $r;
    }

    private static function texto(DOMNode $n): string
    {
        $s = '';
        foreach ($n->childNodes as $h) {
            if ($h instanceof \DOMText) {
                $s .= $h->nodeValue;
            } elseif ($h instanceof DOMElement) {
                $tag = strtolower($h->tagName);
                $s .= $tag === 'br' ? "\n" : self::texto($h) . (in_array($tag, ['p', 'li', 'div'], true) ? "\n" : '');
            }
        }
        return trim($s);
    }
}
