<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

/** Utilidades de texto y fechas en español para las actas y los dictámenes. */
final class Texto
{
    public const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    public const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

    /** Minúsculas, sin acentos y con espacios colapsados: para comparar. */
    public static function norm(string $s): string
    {
        $s = strtr(mb_strtolower($s), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    /** Quita saltos de línea y espacios repetidos. */
    public static function limpio(string $s): string
    {
        return trim(preg_replace('/[ \t\x{00A0}]+/u', ' ', str_replace(["\r", "\n"], ' ', $s)) ?? $s);
    }

    public static function mayus(string $s): string
    {
        return mb_strtoupper(self::limpio($s));
    }

    /** «19 de noviembre de 2026» a partir de AAAA-MM-DD. */
    public static function fechaLarga(?string $ymd, bool $dosDigitos = true): string
    {
        $t = $ymd ? strtotime($ymd) : false;
        if ($t === false) {
            return '';
        }
        return ($dosDigitos ? date('d', $t) : (string) (int) date('j', $t)) . ' de ' . self::MESES[(int) date('n', $t) - 1] . ' de ' . date('Y', $t);
    }

    public static function diaSemana(?string $ymd): string
    {
        $t = $ymd ? strtotime($ymd) : false;
        return $t === false ? '' : self::DIAS[(int) date('w', $t)];
    }

    /** Líneas no vacías de un texto, ya recortadas. @return string[] */
    public static function lineas(?string $t): array
    {
        $r = [];
        foreach (preg_split('/\R/u', (string) $t) ?: [] as $l) {
            $l = trim($l);
            if ($l !== '') {
                $r[] = $l;
            }
        }
        return $r;
    }

    public static function clave(string $grupo, int $numero): string
    {
        return ['profesor' => 'P', 'alumno' => 'A', 'general' => 'AG'][$grupo] . $numero;
    }
}
