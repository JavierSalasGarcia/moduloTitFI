<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use RuntimeException;

/**
 * Llena los formatos PDF oficiales sobreimprimiendo los datos del pasante.
 *
 * Las coordenadas viven en formatos/pdf_specs.json y las produce
 * tools/titulacion_preparar.py a partir de los PDF vigentes; así, cuando el
 * departamento publique una versión nueva basta con regenerar ese archivo.
 */
final class PdfFormato
{
    private static ?array $specs = null;

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

    private static function dirModulo(): string
    {
        return dirname(__DIR__);
    }

    /** @return array<string,array<string,mixed>> */
    public static function specs(): array
    {
        if (self::$specs === null) {
            $ruta = self::dirModulo() . '/formatos/pdf_specs.json';
            $json = is_file($ruta) ? json_decode((string) file_get_contents($ruta), true) : null;
            self::$specs = is_array($json) ? $json : [];
        }
        return self::$specs;
    }

    /** Páginas y tamaño de un PDF, o null si FPDI no puede importarlo. @return array{paginas:int,ancho:float,alto:float}|null */
    public static function inspeccionar(string $ruta): ?array
    {
        self::cargarLibs();
        try {
            $pdf = new \setasign\Fpdi\Fpdi('P', 'pt');
            $n = $pdf->setSourceFile($ruta);
            $tam = $pdf->getTemplateSize($pdf->importPage(1));
            return ['paginas' => $n, 'ancho' => round((float) $tam['width'], 1), 'alto' => round((float) $tam['height'], 1)];
        } catch (\Throwable) {
            return null;
        }
    }

    public static function existe(string $clave): bool
    {
        return isset(self::specs()[$clave]);
    }

    /** Devuelve el PDF llenado como cadena binaria con la especificación que acompaña al módulo. */
    public static function generar(string $clave, array $valores): string
    {
        $spec = self::specs()[$clave] ?? null;
        if ($spec === null) {
            throw new RuntimeException('Formato PDF desconocido: ' . $clave);
        }
        return self::generarCon($spec, self::dirModulo() . '/plantillas/' . $spec['plantilla'], $valores);
    }

    /** Llena cualquier plantilla PDF con una especificación dada (la guardada o un borrador del visor). */
    public static function generarCon(array $spec, string $plantilla, array $valores): string
    {
        self::cargarLibs();
        if (!is_file($plantilla)) {
            throw new RuntimeException('Falta la plantilla del formato.');
        }

        $pdf = new \setasign\Fpdi\Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetCreator('Traza FI · Titulación');
        $total = $pdf->setSourceFile($plantilla);
        $porPagina = [];
        foreach ($spec['campos'] as $campo) {
            $porPagina[(int) $campo['p']][] = $campo;
        }
        for ($i = 1; $i <= $total; $i++) {
            $tpl = $pdf->importPage($i);
            $tam = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($tam['orientation'], [$tam['width'], $tam['height']]);
            $pdf->useTemplate($tpl);
            foreach ($porPagina[$i - 1] ?? [] as $campo) {
                self::pintar($pdf, $campo, $valores);
            }
            self::sustituirSubdirector($pdf, $spec, $i - 1, (string) ($valores['_subdirector'] ?? ''));
        }
        return $pdf->Output('S');
    }

    /**
     * El nombre del Subdirector Académico viene impreso en los formatos. Se captura una sola vez
     * en Ajustes («titulacion_subdirector») y, si difiere del impreso, se cubre y se reescribe.
     */
    private static function sustituirSubdirector(\setasign\Fpdi\Fpdi $pdf, array $spec, int $pagina, string $nombre): void
    {
        $nombre = trim($nombre);
        if ($nombre === '' || $nombre === ($spec['subdirector_impreso'] ?? '')) {
            return;
        }
        $texto = self::latin(mb_strtoupper($nombre));
        foreach ($spec['subdirector'] ?? [] as $caja) {
            if ((int) $caja['p'] !== $pagina) {
                continue;
            }
            $pdf->SetFont('Helvetica', !empty($caja['negrita']) ? 'B' : '', (float) $caja['s']);
            $ancho = max((float) $caja['w'], $pdf->GetStringWidth($texto)) + 6;
            $centrado = (float) $caja['x'] > 200;
            $x0 = $centrado ? (float) $caja['x'] + (float) $caja['w'] / 2 - $ancho / 2 : (float) $caja['x'];
            [$r, $g, $b] = $caja['fondo'] ?? [255, 255, 255];
            $pdf->SetFillColor((int) $r, (int) $g, (int) $b);
            $pdf->Rect($x0 - 1, (float) $caja['y'] - 0.5, $ancho + 2, (float) $caja['h'] + 1, 'F');
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetXY($x0, (float) $caja['y']);
            $pdf->Cell($ancho, (float) $caja['h'], $texto, 0, 0, $centrado ? 'C' : 'L');
        }
    }

    private static function latin(string $texto): string
    {
        $texto = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $texto);
        $convertido = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);
        return $convertido === false ? preg_replace('/[^\x20-\x7E]/', '?', $texto) : $convertido;
    }

    private static function pintar(\setasign\Fpdi\Fpdi $pdf, array $campo, array $valores): void
    {
        $k = (string) $campo['k'];
        $valor = $valores[$k] ?? null;
        if ($valor === null || $valor === '') {
            return;
        }
        $pdf->SetTextColor(10, 10, 60);
        switch ($campo['tipo']) {
            case 'marca':
                $coord = $campo['m'][(string) $valor] ?? null;
                if ($coord === null) {
                    return;
                }
                $pdf->SetFont('Helvetica', 'B', 11);
                $pdf->SetXY((float) $coord[0] - 6, (float) $coord[1] - 6.5);
                $pdf->Cell(12, 13, 'X', 0, 0, 'C');
                return;
            case 'parrafo':
                self::parrafo($pdf, $campo, (string) $valor);
                return;
            default:
                $texto = self::latin((string) $valor);
                $tam = (float) $campo['s'];
                $pdf->SetFont('Helvetica', '', $tam);
                while ($tam > 6 && $pdf->GetStringWidth($texto) > (float) $campo['w']) {
                    $tam -= 0.5;
                    $pdf->SetFont('Helvetica', '', $tam);
                }
                $pdf->SetXY((float) $campo['x'], (float) $campo['y']);
                $pdf->Cell((float) $campo['w'], (float) $campo['h'], $texto, 0, 0, (string) $campo['a']);
        }
    }

    /** Reparte un texto largo entre los renglones subrayados del formato. */
    private static function parrafo(\setasign\Fpdi\Fpdi $pdf, array $campo, string $valor): void
    {
        $palabras = preg_split('/\s+/', self::latin($valor), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $pdf->SetFont('Helvetica', '', (float) $campo['s']);
        $i = 0;
        $ultima = count($campo['cajas']) - 1;
        foreach ($campo['cajas'] as $n => [$x, $y, $w, $h]) {
            $linea = '';
            while ($i < count($palabras)) {
                $prueba = $linea === '' ? $palabras[$i] : $linea . ' ' . $palabras[$i];
                if ($pdf->GetStringWidth($prueba) > $w - 2) {
                    break;
                }
                $linea = $prueba;
                $i++;
            }
            if ($n === $ultima && $i < count($palabras)) {
                $linea = rtrim($linea) . '...';
            }
            $pdf->SetXY((float) $x, (float) $y);
            $pdf->Cell((float) $w, (float) $h, $linea, 0, 0, 'L');
        }
    }
}
