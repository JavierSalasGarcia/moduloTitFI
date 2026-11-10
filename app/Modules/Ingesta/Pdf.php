<?php
declare(strict_types=1);

namespace App\Modules\Ingesta;

/**
 * Lector mínimo de PDF, sin dependencias: páginas, título, autor, fecha y el texto que se pueda recuperar para la búsqueda.
 * Es un mejor esfuerzo: los PDF escaneados (imagen), cifrados o con fuentes sin tabla de caracteres no entregan texto, y
 * en ese caso el documento se encuentra por su título, palabras clave, resumen y nombre de archivo.
 * Todo está acotado en tamaño y tiempo: un PDF hostil no puede agotar la memoria.
 */
final class Pdf
{
    private const MAX_LECTURA = 12 * 1048576;     // bytes del archivo que se analizan
    private const MAX_FLUJO = 3 * 1048576;        // bytes descomprimidos por flujo
    private const MAX_TEXTO = 200000;             // caracteres de texto que se conservan
    private const MAX_SEGUNDOS = 4.0;

    /** @return array{paginas:?int,titulo:string,autor:string,fecha:?string,texto:string} */
    public static function leer(string $ruta): array
    {
        $r = ['paginas' => null, 'titulo' => '', 'autor' => '', 'fecha' => null, 'texto' => ''];
        $datos = @file_get_contents($ruta, false, null, 0, self::MAX_LECTURA);
        if (!is_string($datos) || $datos === '') {
            return $r;
        }
        $n = preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $datos);
        $r['paginas'] = $n > 0 ? min(65535, (int) $n) : null;
        if (str_contains($datos, '/Encrypt')) {
            return $r;                                  // cifrado: no se intenta leer nada más
        }
        $r['titulo'] = self::info($datos, 'Title');
        $r['autor'] = self::info($datos, 'Author');
        if (preg_match('#/CreationDate\s*\(D:(\d{4})(\d{2})(\d{2})#', $datos, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            $r['fecha'] = "$m[1]-$m[2]-$m[3]";
        }
        $r['texto'] = self::texto($datos, $r['paginas']);
        return $r;
    }

    private static function info(string $datos, string $campo): string
    {
        if (!preg_match('#/' . $campo . '\s*\(((?:[^()\\\\]|\\\\.){1,400})\)#s', $datos, $m)) {
            return '';
        }
        $v = self::cadena($m[1]);
        if (str_starts_with($v, "\xFE\xFF")) {
            $v = (string) @mb_convert_encoding(substr($v, 2), 'UTF-8', 'UTF-16BE');
        } elseif (!mb_check_encoding($v, 'UTF-8')) {
            $v = (string) @mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
        }
        $v = trim(preg_replace('/[\x00-\x1f]+/', ' ', $v) ?? '');
        // Títulos de plantilla que no describen el documento.
        return preg_match('/^(untitled|sin t[ií]tulo|microsoft word|document\d*|presentaci[oó]n\d*)\b/iu', $v) ? '' : mb_substr($v, 0, 250);
    }

    /** Texto de los operadores Tj/TJ de los flujos de contenido. Se descarta si no parece texto legible. */
    private static function texto(string $datos, ?int $paginas): string
    {
        $inicio = microtime(true);
        $texto = '';
        $desde = 0;
        while (($a = strpos($datos, 'stream', $desde)) !== false && strlen($texto) < self::MAX_TEXTO && microtime(true) - $inicio < self::MAX_SEGUNDOS) {
            $b = strpos($datos, 'endstream', $a);
            if ($b === false) {
                break;
            }
            $desde = $b + 9;
            $cabecera = substr($datos, max(0, $a - 300), min(300, $a));
            if (str_contains($cabecera, '/Image') || str_contains($cabecera, '/FontFile') || str_contains($cabecera, '/DCTDecode')) {
                continue;                               // imágenes y fuentes incrustadas: no contienen texto de página
            }
            $crudo = ltrim(substr($datos, $a + 6, $b - $a - 6), "\r\n");
            if (str_contains($cabecera, '/FlateDecode')) {
                $crudo = @gzuncompress($crudo, self::MAX_FLUJO);
                if (!is_string($crudo)) {
                    continue;
                }
            } elseif (str_contains($cabecera, '/Filter')) {
                continue;
            }
            if (!str_contains($crudo, 'BT')) {
                continue;
            }
            if (preg_match_all('#\[((?:[^\[\]\\\\]|\\\\.)*)\]\s*TJ|\(((?:[^()\\\\]|\\\\.)*)\)\s*(?:Tj|\'|")#s', $crudo, $ops, PREG_SET_ORDER)) {
                foreach ($ops as $op) {
                    if (($op[1] ?? '') !== '') {
                        if (preg_match_all('#\(((?:[^()\\\\]|\\\\.)*)\)|(-?\d+(?:\.\d+)?)#s', $op[1], $partes, PREG_SET_ORDER)) {
                            foreach ($partes as $p) {
                                // Un desplazamiento negativo grande dentro de TJ equivale a un espacio entre palabras.
                                $texto .= isset($p[2]) && $p[2] !== '' ? ((float) $p[2] < -180 ? ' ' : '') : self::cadena($p[1]);
                            }
                        }
                        $texto .= ' ';
                    } else {
                        $texto .= self::cadena($op[2] ?? '') . ' ';
                    }
                }
                $texto .= "\n";
            }
        }
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = (string) @mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }
        $texto = trim(preg_replace('/[^\P{C}\n]+/u', ' ', preg_replace('/[ \t]+/', ' ', $texto) ?? '') ?? '');
        if ($texto === '') {
            return '';
        }
        // ¿Parece texto? Al menos 70 % de letras, dígitos, espacios y puntuación común; si no, son glifos sin tabla de caracteres.
        $muestra = mb_substr($texto, 0, 4000);
        $legibles = preg_match_all('/[\p{L}\p{N}\s.,;:()\-¿?¡!"\/%]/u', $muestra);
        if (mb_strlen($muestra) < 40 || $legibles / max(1, mb_strlen($muestra)) < 0.7 || !preg_match('/\p{L}{3,}\s+\p{L}{2,}/u', $muestra)) {
            return '';
        }
        return mb_substr($texto, 0, self::MAX_TEXTO);
    }

    /** Decodifica los escapes de una cadena literal de PDF. */
    private static function cadena(string $s): string
    {
        return (string) preg_replace_callback('#\\\\(\d{1,3}|.)#s', static function (array $m): string {
            return match (true) {
                ctype_digit($m[1]) => chr(octdec($m[1]) & 0xFF),
                $m[1] === 'n' => "\n", $m[1] === 'r' => "\r", $m[1] === 't' => "\t", $m[1] === 'b' || $m[1] === 'f' => ' ',
                $m[1] === "\n" || $m[1] === "\r" => '',
                default => $m[1],
            };
        }, $s);
    }
}
