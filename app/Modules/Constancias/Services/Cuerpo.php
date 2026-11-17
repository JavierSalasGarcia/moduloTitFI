<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

/**
 * Cuerpo de una constancia como lista de bloques, venga del campo de texto o de un archivo de Word:
 *   ['t' => 'p', 'alinear' => 'J'|'L'|'C'|'R', 'runs' => [[texto, negrita], …]]   párrafo (admite «\n» como salto de línea)
 *   ['t' => 'tabla', 'encabezado' => bool, 'filas' => [[['texto' => …, 'negrita' => bool], …], …]]
 *
 * Formato sencillo del campo de texto: párrafos separados por una línea en blanco; **negritas** entre dobles
 * asteriscos; y tablas pegadas como renglones con celdas separadas por tabuladores (copiar de Excel o Word) o por «|».
 * Un renglón «|---|---|» (Markdown) o una primera fila toda en negritas la marca como encabezado.
 */
final class Cuerpo
{
    public const MAX_TEXTO = 20000;
    public const MAX_FILAS = 200;
    public const MAX_COLUMNAS = 8;

    /** @return array<int,array> */
    public static function desdeTexto(string $texto): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $texto);
        $lineas = explode("\n", $texto);
        $bloques = [];
        $parrafo = [];
        $tabla = [];
        $cerrarParrafo = static function () use (&$parrafo, &$bloques): void {
            $t = trim(implode("\n", $parrafo));
            if ($t !== '') {
                $bloques[] = ['t' => 'p', 'alinear' => 'J', 'runs' => self::negritas($t)];
            }
            $parrafo = [];
        };
        $cerrarTabla = static function () use (&$tabla, &$bloques): void {
            if ($tabla) {
                $bloques[] = self::tablaDeLineas($tabla);
            }
            $tabla = [];
        };
        foreach ($lineas as $l) {
            if (self::esRenglonTabla($l)) {
                $cerrarParrafo();
                $tabla[] = $l;
                continue;
            }
            $cerrarTabla();
            if (trim($l) === '') {
                $cerrarParrafo();
            } else {
                $parrafo[] = trim($l);
            }
        }
        $cerrarParrafo();
        $cerrarTabla();
        return $bloques;
    }

    private static function esRenglonTabla(string $l): bool
    {
        if (str_contains($l, "\t") && trim($l) !== '') {
            return true;
        }
        $t = trim($l);
        return substr_count($t, '|') >= 2 || (str_starts_with($t, '|') && substr_count($t, '|') >= 1 && strlen($t) > 1);
    }

    private static function tablaDeLineas(array $lineas): array
    {
        $filas = [];
        $encabezado = false;
        foreach ($lineas as $l) {
            $t = trim($l);
            if (preg_match('/^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/', $t)) {
                $encabezado = $encabezado || count($filas) === 1;
                continue;
            }
            if (str_contains($l, "\t")) {
                $celdas = explode("\t", rtrim($l, "\n"));
            } else {
                $t = preg_replace('/^\|/', '', $t) ?? $t;
                $t = preg_replace('/\|$/', '', $t) ?? $t;
                $celdas = explode('|', $t);
            }
            $fila = [];
            foreach (array_slice($celdas, 0, self::MAX_COLUMNAS) as $c) {
                $c = trim($c);
                $negrita = (bool) preg_match('/^\*\*(.+)\*\*$/su', $c, $m);
                $fila[] = ['texto' => $negrita ? trim($m[1]) : str_replace('**', '', $c), 'negrita' => $negrita];
            }
            $filas[] = $fila;
            if (count($filas) >= self::MAX_FILAS) {
                break;
            }
        }
        return self::normalizarTabla($filas, $encabezado);
    }

    /** Iguala el número de celdas y decide el encabezado (marcado, o primera fila toda en negritas). */
    public static function normalizarTabla(array $filas, bool $encabezado): array
    {
        $n = 0;
        foreach ($filas as $f) {
            $n = max($n, count($f));
        }
        foreach ($filas as &$f) {
            while (count($f) < $n) {
                $f[] = ['texto' => '', 'negrita' => false];
            }
        }
        unset($f);
        if (!$encabezado && count($filas) > 1) {
            $llenas = array_filter($filas[0], static fn(array $c): bool => $c['texto'] !== '');
            $encabezado = $llenas && count(array_filter($llenas, static fn(array $c): bool => $c['negrita'])) === count($llenas);
        }
        return ['t' => 'tabla', 'encabezado' => $encabezado, 'filas' => $filas];
    }

    /** «Texto con **negritas**» → [[texto, false], [negritas, true]]. @return array<int,array{0:string,1:bool}> */
    public static function negritas(string $t): array
    {
        $runs = [];
        foreach (preg_split('/(\*\*.+?\*\*)/su', $t, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $parte) {
            if (preg_match('/^\*\*(.+)\*\*$/su', $parte, $m)) {
                $runs[] = [$m[1], true];
            } else {
                $runs[] = [$parte, false];
            }
        }
        return $runs;
    }

    /** Texto plano equivalente (para validar que no esté vacío y para el contenido de la verificación). */
    public static function plano(array $bloques): string
    {
        $r = [];
        foreach ($bloques as $b) {
            if (($b['t'] ?? '') === 'p') {
                $r[] = implode('', array_map(static fn(array $x): string => (string) $x[0], $b['runs']));
            } elseif (($b['t'] ?? '') === 'tabla') {
                foreach ($b['filas'] as $f) {
                    $r[] = implode(' | ', array_map(static fn(array $c): string => (string) $c['texto'], $f));
                }
            }
        }
        return trim(implode("\n", $r));
    }

    /** Valida la estructura (al leerla de la base o del formulario) y recorta lo excesivo. */
    public static function sanear(array $bloques): array
    {
        $r = [];
        foreach (array_slice($bloques, 0, 400) as $b) {
            if (($b['t'] ?? '') === 'p' && is_array($b['runs'] ?? null)) {
                $runs = [];
                foreach ($b['runs'] as $x) {
                    if (is_array($x) && isset($x[0]) && is_string($x[0]) && $x[0] !== '') {
                        $runs[] = [$x[0], !empty($x[1])];
                    }
                }
                if ($runs) {
                    $r[] = ['t' => 'p', 'alinear' => in_array($b['alinear'] ?? 'J', ['J', 'L', 'C', 'R'], true) ? $b['alinear'] : 'J', 'runs' => $runs];
                }
            } elseif (($b['t'] ?? '') === 'tabla' && is_array($b['filas'] ?? null) && $b['filas']) {
                $filas = [];
                foreach (array_slice($b['filas'], 0, self::MAX_FILAS) as $f) {
                    $fila = [];
                    foreach (array_slice((array) $f, 0, self::MAX_COLUMNAS) as $c) {
                        $fila[] = ['texto' => mb_substr((string) ($c['texto'] ?? ''), 0, 2000), 'negrita' => !empty($c['negrita'])];
                    }
                    $filas[] = $fila;
                }
                $r[] = self::normalizarTabla($filas, !empty($b['encabezado']));
            }
        }
        return $r;
    }
}
