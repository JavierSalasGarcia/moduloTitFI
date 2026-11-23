<?php
/**
 * FPDF de Traza FI con DM Sans.
 *
 * Todos los formatos y oficios PDF de la plataforma usan la fuente DM Sans (obligatoria). Esta clase `FPDF` es una capa de
 * compatibilidad sobre tFPDF (FPDF con fuentes TrueType) para que el código existente siga funcionando sin cambios:
 *  - `SetFont('Helvetica'|'Arial'|'Courier'|'Times', …)` usa DM Sans (regular, negrita, cursiva y negrita cursiva).
 *  - El código heredado entrega las cadenas en Windows-1252 (por `iconv(...)`/`utf8_decode`); aquí se convierten a UTF-8
 *    una sola vez, en la llamada de más afuera (las llamadas internas de tFPDF ya llevan UTF-8).
 * Las fuentes están en `lib/fpdf/font/unifont/` (licencia SIL OFL, ver OFL-DMSans.txt). FPDI (`lib/fpdi`) extiende esta clase.
 */
if (!defined('FPDF_FONTPATH')) {
    define('FPDF_FONTPATH', __DIR__ . '/font/');
}
require_once __DIR__ . '/tfpdf.php';

class FPDF extends tFPDF
{
    public const FUENTE = 'DMSans';
    private const SUSTITUIDAS = ['helvetica', 'arial', 'courier', 'times', 'symbol', 'zapfdingbats', ''];

    /** true mientras se ejecuta una llamada interna (las cadenas ya están en UTF-8). */
    private bool $interno = false;

    private const ARCHIVOS = ['' => 'DMSans-Regular.ttf', 'B' => 'DMSans-Bold.ttf', 'I' => 'DMSans-Italic.ttf', 'BI' => 'DMSans-BoldItalic.ttf'];

    /** Estilos de DM Sans ya registrados en este documento (solo se incrustan los que se usan). */
    private array $registrados = [];

    private function registrar(string $estilo): void
    {
        $e = strtoupper(str_replace('U', '', $estilo));
        $e = ($e === 'IB') ? 'BI' : $e;
        if (!isset(self::ARCHIVOS[$e])) {
            $e = '';
        }
        if (!isset($this->registrados[$e])) {
            $this->AddFont(self::FUENTE, $e, self::ARCHIVOS[$e], true);
            $this->registrados[$e] = true;
        }
    }

    /** Windows-1252 → UTF-8 (solo en la llamada de más afuera). */
    private function aUtf8(string $s): string
    {
        if ($s === '' || $this->interno || !preg_match('/[\x80-\xFF]/', $s)) {
            return $s;
        }
        // Si ya es UTF-8 válido y trae caracteres multibyte, no se vuelve a convertir.
        if (preg_match('//u', $s) && preg_match('/[\xC2-\xF4][\x80-\xBF]/', $s)) {
            return $s;
        }
        $r = @iconv('Windows-1252', 'UTF-8//IGNORE', $s);
        return $r === false ? $s : $r;
    }

    /** Ejecuta $f con la bandera de «llamada interna» activa si es la llamada de más afuera. */
    private function envolver(callable $f)
    {
        if ($this->interno) {
            return $f();
        }
        $this->interno = true;
        try {
            return $f();
        } finally {
            $this->interno = false;
        }
    }

    public function SetFont($family, $style = '', $size = 0)
    {
        if (in_array(strtolower((string) $family), self::SUSTITUIDAS, true)) {
            $family = self::FUENTE;
        }
        if ($family === self::FUENTE) {
            $this->registrar((string) $style);
        }
        parent::SetFont($family, $style, $size);
    }

    public function GetStringWidth($s)
    {
        $s = $this->aUtf8((string) $s);
        return $this->envolver(fn() => parent::GetStringWidth($s));
    }

    public function Text($x, $y, $txt)
    {
        $txt = $this->aUtf8((string) $txt);
        $this->envolver(fn() => parent::Text($x, $y, $txt));
    }

    public function Cell($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $txt = $this->aUtf8((string) $txt);
        $this->envolver(fn() => parent::Cell($w, $h, $txt, $border, $ln, $align, $fill, $link));
    }

    public function MultiCell($w, $h, $txt, $border = 0, $align = 'J', $fill = false)
    {
        $txt = $this->aUtf8((string) $txt);
        $this->envolver(fn() => parent::MultiCell($w, $h, $txt, $border, $align, $fill));
    }

    public function Write($h, $txt, $link = '')
    {
        $txt = $this->aUtf8((string) $txt);
        $this->envolver(fn() => parent::Write($h, $txt, $link));
    }
}
