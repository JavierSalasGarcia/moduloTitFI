<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Evita que un mismo título se repita en pantalla (migas de pan, h1 del diseño y encabezado de la propia vista).
 * El diseño (`layouts/app.php`) pone siempre el h1 de la página; si el contenido de la vista trae un encabezado que ya dice lo
 * mismo, se retira. Ver docs/GUIA_MODULOS.md («Títulos de página»).
 */
final class Titulos
{
    /** Minúsculas, sin acentos ni signos: «Titulación · Expedientes» → «titulacion expedientes». */
    public static function normalizar(string $texto): string
    {
        $t = html_entity_decode(strip_tags($texto), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = function_exists('mb_strtolower') ? mb_strtolower($t, 'UTF-8') : strtolower($t);
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u', 'Ñ' => 'n']);
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $t) ?? '');
    }

    /** ¿Lo dicho en `$encabezado` ya está en `$titulo` (igual o contenido en él)? */
    public static function yaDicho(string $encabezado, string $titulo): bool
    {
        $h = self::normalizar($encabezado);
        $t = self::normalizar($titulo);
        return strlen($h) >= 4 && ($h === $t || str_contains($t, $h));
    }

    /**
     * Quita de la vista el encabezado que repite el título de la página (el diseño ya pone el h1):
     *  - el primer h1 de la vista, esté donde esté, si su texto ya está en el título;
     *  - si la vista no tiene h1, su primer h2 cuando es lo primero que se lee y repite el título.
     * Los encabezados que dicen algo distinto no se tocan.
     */
    public static function quitarRepetido(string $html, string $titulo): string
    {
        if (preg_match('~<h1\b[^>]*>(.*?)</h1>~is', $html, $m, PREG_OFFSET_CAPTURE)) {
            return self::yaDicho($m[1][0], $titulo) ? self::cortar($html, $m) : $html;
        }
        if (!preg_match('~<h2\b[^>]*>(.*?)</h2>~is', $html, $m, PREG_OFFSET_CAPTURE)) {
            return $html;
        }
        $antes = preg_replace('~<(style|script)\b.*?</\1>~is', '', substr($html, 0, $m[0][1])) ?? '';
        $antes = preg_replace('~<!--.*?-->~s', '', $antes) ?? '';
        if (trim(html_entity_decode(strip_tags($antes), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '') {
            return $html;
        }
        return self::yaDicho($m[1][0], $titulo) ? self::cortar($html, $m) : $html;
    }

    private static function cortar(string $html, array $m): string
    {
        return substr($html, 0, $m[0][1]) . substr($html, $m[0][1] + strlen($m[0][0]));
    }
}
