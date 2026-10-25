<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;

// FPDF y FPDI deben estar cargados antes de declarar la clase que los extiende.
if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', dirname(APP_RAIZ) . '/lib/fpdf/font/');
}
require_once dirname(APP_RAIZ) . '/lib/fpdf/fpdf.php';
require_once dirname(APP_RAIZ) . '/lib/fpdi/autoload.php';

/**
 * Hoja de los formatos y oficios que genera la plataforma: hoja membretada de fondo, y en la esquina inferior
 * derecha de cada página el ID del proceso de titulación (control, seguimiento y auditoría) y el QR que lleva a la
 * página pública de verificación del documento, que muestra el mismo contenido que se imprime (ver Verificacion).
 * Ofrece los bloques que se repiten en los formatos oficiales: título, destinatario, casillas, datos y firmas.
 */
final class Hoja extends \setasign\Fpdi\Fpdi
{
    public const VERDE = [44, 82, 52];

    private ?string $fondo = null;

    /** @param string $folio ID del proceso que se imprime en cada página; $membretada usa la hoja de la Facultad como fondo */
    public static function nueva(string $folio, bool $membretada = true, string $version = ''): self
    {
        $pdf = new self('P', 'mm', 'Letter');
        $pdf->folio = $folio;
        $pdf->version = $version;
        $pdf->codigo = $folio !== '' ? Verificacion::nuevoCodigo() : null;
        if ($membretada) {
            $pdf->setSourceFile(dirname(__DIR__) . '/plantillas/pdf/Membretada.pdf');
            $pdf->fondo = $pdf->importPage(1);
        }
        $pdf->SetMargins(25, 36, 25);
        $pdf->SetAutoPageBreak(true, 26);
        $pdf->AddPage();
        $pdf->SetTextColor(25, 25, 25);
        return $pdf;
    }

    public string $folio = '';
    public string $version = '';
    /** Código de verificación del documento (QR); null en los PDF importados. */
    public ?string $codigo = null;
    private bool $pie = true;
    private bool $enPie = false;
    /** Contenido impreso, para la página de verificación: ['t'|'p'|'l', texto] o ['d', etiqueta, valor]. */
    private array $registro = [];
    private string $renglon = '';
    private int $anidado = 0;

    /** Sin pie: para PDF importados, donde el ID se estampa aparte. */
    public function setPrintFooter(bool $pie): void
    {
        $this->pie = $pie;
    }

    public function Header(): void
    {
        if ($this->fondo !== null) {
            $this->useTemplate($this->fondo);
        }
    }

    public function Footer(): void
    {
        if (!$this->pie) {
            return;
        }
        $this->enPie = true;
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(90, 90, 90);
        $lado = 17.0;
        $finTexto = $this->codigo !== null ? $this->w - 8 - $lado - 1 : $this->w - 7;
        if ($this->codigo !== null) {
            Qr::dibujar($this, Verificacion::url($this->codigo), $this->w - 8 - $lado, $this->h - 5 - $lado, $lado);
            $this->SetXY($finTexto - 90, -13);
            $this->Cell(90, 4, self::l1('Verifique este documento con el código QR'), 0, 0, 'R');
        }
        if ($this->folio !== '') {
            $this->SetXY($finTexto - 90, -9);
            $this->Cell(90, 4, self::l1('ID del proceso de titulación: ' . $this->folio), 0, 0, 'R');
        }
        if ($this->version !== '') {
            $this->SetXY($finTexto - 90, -17);
            $this->Cell(90, 4, $this->version, 0, 0, 'R');
        }
        $this->SetTextColor(25, 25, 25);
        $this->enPie = false;
    }

    // --------------------------------------------- registro del contenido impreso (verificación)

    private static function utf8(string $s): string
    {
        $r = iconv('Windows-1252', 'UTF-8', $s);
        return trim(preg_replace('/\s+/u', ' ', $r === false ? $s : $r) ?? '');
    }

    private function cerrarRenglon(): void
    {
        if ($this->renglon !== '') {
            $this->registro[] = ['l', $this->renglon];
            $this->renglon = '';
        }
    }

    private function anotar(string $txt, bool $salto): void
    {
        if ($this->enPie || $this->anidado > 0) {
            return;
        }
        $t = self::utf8($txt);
        if ($t !== '') {
            $this->renglon .= ($this->renglon !== '' ? ' ' : '') . $t;
        }
        if ($salto) {
            $this->cerrarRenglon();
        }
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $this->anotar((string) $txt, $ln > 0);
        parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        if (!$this->enPie && $this->anidado === 0) {
            $this->cerrarRenglon();
            $t = self::utf8((string) $txt);
            if ($t !== '') {
                $this->registro[] = [$fill ? 't' : 'p', $t];
            }
        }
        $this->anidado++;
        parent::MultiCell($w, $h, $txt, $border, $align, $fill);
        $this->anidado--;
    }

    public function Write($h, $txt, $link = '')
    {
        $this->anotar((string) $txt, false);
        $this->anidado++;
        parent::Write($h, $txt, $link);
        $this->anidado--;
    }

    public function Ln($h = null)
    {
        if ($this->anidado === 0 && !$this->enPie) {
            $this->cerrarRenglon();
        }
        parent::Ln($h);
    }

    public function Output($dest = '', $name = '', $isUTF8 = false)
    {
        $r = parent::Output($dest, $name, $isUTF8);
        if ($this->codigo !== null && $dest === 'S') {
            $this->cerrarRenglon();
            Verificacion::generado((string) $r, $this->codigo, $this->folio, $this->registro);
        }
        return $r;
    }

    /** UTF-8 a la codificación de las fuentes de FPDF; la viñeta «•» se conserva. */
    public static function l1(string $s): string
    {
        $r = iconv('UTF-8', 'ISO-8859-1//TRANSLIT', str_replace('•', "\x01", $s));
        return str_replace("\x01", chr(149), $r === false ? $s : $r);
    }

    public static function fecha(?string $iso = null): string
    {
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso ?? date('Y-m-d'));
        return $f ? $f->format('j') . ' de ' . Catalogo::MESES[(int) $f->format('n')] . ' de ' . $f->format('Y') : '';
    }

    // ------------------------------------------------------------------ bloques

    /** Barra verde con el nombre del formato, como en los formatos del sitio de Titulación. */
    public function titulo(string $texto, ?string $subtitulo = null): void
    {
        $this->SetFillColor(...self::VERDE);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 10.5);
        $this->MultiCell(0, 6, self::l1(mb_strtoupper($texto)), 0, 'C', true);
        if ($subtitulo !== null) {
            $this->MultiCell(0, 5, self::l1(mb_strtoupper($subtitulo)), 0, 'C', true);
        }
        $this->SetTextColor(25, 25, 25);
        $this->Ln(2);
    }

    public function lugarFecha(?string $iso = null, string $lugar = 'Ciudad Universitaria, Toluca, Méx.'): void
    {
        $this->SetFont('Helvetica', 'I', 10);
        $this->Cell(0, 5, self::l1($lugar . ' ' . self::fecha($iso) . '.'), 0, 1, 'R');
        $this->Ln(1);
    }

    /** @param string[] $lineas destinatario en negritas; termina con «PRESENTE.» */
    public function destinatario(array $lineas): void
    {
        $this->SetFont('Helvetica', 'B', 10);
        foreach ($lineas as $l) {
            $this->Cell(0, 4.8, self::l1(mb_strtoupper($l)), 0, 1);
        }
        $this->Cell(0, 4.8, 'PRESENTE.', 0, 1);
        $this->Ln(2);
    }

    public function texto(string $texto, string $estilo = '', float $tam = 10, string $alin = 'J', float $alto = 4.6): void
    {
        $this->SetFont('Helvetica', $estilo, $tam);
        $this->MultiCell(0, $alto, self::l1($texto), 0, $alin);
    }

    /** Tabla de dos columnas «etiqueta | valor» con bordes; ambas columnas pueden ocupar varios renglones. @param array<int,array{0:string,1:string}> $filas */
    public function datos(array $filas, float $ancho = 45.0): void
    {
        $this->cerrarRenglon();
        foreach ($filas as [$etiqueta, $valor]) {
            if ($this->anidado === 0) {
                $this->registro[] = ['d', self::utf8(self::l1($etiqueta)), self::utf8(self::l1($valor))];
            }
            $this->anidado++;
            if ($this->GetY() > 279.4 - 26 - 12) {
                $this->AddPage();
            }
            $x = 25.0;
            $y = $this->GetY();
            $this->SetFont('Helvetica', '', 9);
            $this->SetXY($x, $y + 0.4);
            $this->MultiCell($ancho, 4.4, self::l1($etiqueta), 0, 'L');
            $fin = $this->GetY();
            $this->SetFont('Helvetica', 'B', 9.5);
            $this->SetXY($x + $ancho, $y + 0.4);
            $this->MultiCell(166 - $ancho, 4.4, self::l1($valor !== '' ? $valor : ' '), 0, 'L');
            $alto = max($fin, $this->GetY()) - $y + 0.6;
            $this->Rect($x, $y, $ancho, $alto);
            $this->Rect($x + $ancho, $y, 166 - $ancho, $alto);
            $this->SetXY($x, $y + $alto);
            $this->anidado--;
        }
        $this->Ln(2);
    }

    /** Lista de casillas «( X ) opción»; $columnas > 1 las reparte. @param array<string,string> $opciones */
    public function casillas(array $opciones, ?string $marcada, float $sangria = 6.0, int $columnas = 1, float $tam = 9.5): void
    {
        $this->SetFont('Helvetica', '', $tam);
        $ancho = (190.0 - 25.0 - $sangria - 25.0 + 25.0) / $columnas;
        $i = 0;
        foreach ($opciones as $clave => $texto) {
            if ($i % $columnas === 0) {
                $this->SetX(25 + $sangria);
            }
            $this->Cell(9, 4.1, (string) $clave === (string) $marcada ? '( X )' : '(    )', 0, 0);
            $t = $tam;
            while ($t > 6.5 && $this->GetStringWidth(self::l1($texto)) > $ancho - 10) {
                $t -= 0.5;
                $this->SetFontSize($t);
            }
            $this->Cell($ancho - 9, 4.1, self::l1($texto), 0, ($i % $columnas === $columnas - 1) ? 1 : 0);
            $this->SetFontSize($tam);
            $i++;
        }
        if ($i % $columnas !== 0) {
            $this->Ln();
        }
        $this->Ln(1);
    }

    /** Licenciatura y, para Computación, el sistema (FI-UAEMéx, UI-CUI, CESU, CUTV, UIF), como en los formatos oficiales. */
    public function licenciatura(string $carrera, string $sistema, string $encabezado = 'Pasante de la Licenciatura de Ingeniería:'): void
    {
        $this->texto($encabezado, '', 10);
        $this->SetFont('Helvetica', '', 9.5);
        foreach (Catalogo::carreras() as $clave => $c) {
            $this->SetX(31);
            $this->Cell(9, 4.1, $clave === $carrera ? '( X )' : '(    )', 0, 0);
            $nombre = preg_replace('/^Ingeniería /u', '', $c['nombre']);
            if ($clave === 'computacion') {
                $this->Cell(30, 4.1, self::l1($nombre), 0, 0);
                foreach (Catalogo::sistemas() as $s => $et) {
                    $this->Cell(23, 4.1, self::l1(($clave === $carrera && $s === $sistema ? '(X) ' : '(  ) ') . $et), 0, 0);
                }
                $this->Ln();
            } else {
                $this->Cell(0, 4.1, self::l1($nombre), 0, 1);
            }
        }
        $this->Ln(1);
    }

    /** Opciones con trabajo escrito, en el orden de los formatos oficiales. */
    public static function opcionesTrabajo(): array
    {
        return [
            'articulo' => 'Artículo especializado para publicar en revista indizada', 'ensayo' => 'Ensayo',
            'memoria' => 'Memoria de experiencia laboral', 'aplicacion' => 'Reporte de aplicación de conocimientos',
            'autoempleo' => 'Reporte de autoempleo profesional', 'residencia' => 'Reporte de residencia de investigación',
            'tesina' => 'Tesina', 'tesis' => 'Tesis',
        ];
    }

    /** Valor subrayado dentro de un renglón: «cuyo título es: ____». */
    public function campo(string $etiqueta, string $valor, float $tam = 10): void
    {
        $this->SetFont('Helvetica', '', $tam);
        $this->Write(5.2, self::l1($etiqueta . ' '));
        $this->SetFont('Helvetica', 'BU', $tam);
        $this->Write(5.2, self::l1($valor !== '' ? $valor : str_repeat(' ', 30)));
        $this->SetFont('Helvetica', '', $tam);
    }

    /**
     * Bloques de firma en columnas. Cada bloque: ['rol' => clave, 'arriba' => «ATENTAMENTE»/«Vo.Bo.», 'nombre' => …, 'cargo' => [líneas]].
     * Devuelve las cajas (puntos, como las espera SelloPdf) por rol, sobre la línea de firma.
     * @param array<int,array{rol:string,arriba?:string,nombre:string,cargo?:array<int,string>}> $bloques
     * @return array<string,array{p:int,x:float,y:float,w:float,h:float}>
     */
    public function firmas(array $bloques, float $espacio = 16.0): array
    {
        $n = max(1, count($bloques));
        if ($this->GetY() + 4 + 5 + $espacio + 10 > 279.4 - 24) {
            $this->AddPage();
        }
        $ancho = 166.0 / $n;
        $y0 = $this->GetY() + 4;
        $this->SetFont('Helvetica', 'B', 9.5);
        foreach ($bloques as $i => $b) {
            if (!empty($b['arriba'])) {
                $this->SetXY(25 + $i * $ancho, $y0);
                $this->Cell($ancho, 5, self::l1($b['arriba']), 0, 0, 'C');
            }
        }
        $y = $y0 + 5 + $espacio;
        $cajas = [];
        $mm = 72 / 25.4;
        $fin = $y;
        foreach ($bloques as $i => $b) {
            $x = 25 + $i * $ancho;
            $this->SetDrawColor(70, 70, 70);
            $this->SetLineWidth(0.2);
            $this->Line($x + 4, $y, $x + $ancho - 4, $y);
            $this->SetXY($x, $y + 0.8);
            $this->SetFont('Helvetica', 'B', 8.5);
            $this->MultiCell($ancho, 4, self::l1(mb_strtoupper($b['nombre'])), 0, 'C');
            $this->SetFont('Helvetica', '', 8);
            foreach ($b['cargo'] ?? [] as $c) {
                $this->SetX($x);
                $this->MultiCell($ancho, 3.8, self::l1($c), 0, 'C');
            }
            $fin = max($fin, $this->GetY());
            $cajas[$b['rol']] = ['p' => $this->PageNo() - 1, 'x' => ($x + 2) * $mm, 'y' => $y * $mm, 'w' => ($ancho - 4) * $mm, 'h' => 11.0];
        }
        $this->SetXY(25, $fin + 3);
        return $cajas;
    }

    /** Recuadro en blanco con título (observaciones, motivos). */
    public function recuadro(string $etiqueta, string $contenido, float $minimo = 20.0): void
    {
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->Cell(0, 5, self::l1($etiqueta), 0, 1);
        $y = $this->GetY();
        $this->SetFont('Helvetica', '', 9.5);
        $this->MultiCell(0, 4.8, self::l1($contenido !== '' ? $contenido : ' '), 0, 'J');
        $alto = max($minimo, $this->GetY() - $y + 2);
        $this->Rect(25, $y, 166, $alto);
        $this->SetY($y + $alto + 2);
    }
}
