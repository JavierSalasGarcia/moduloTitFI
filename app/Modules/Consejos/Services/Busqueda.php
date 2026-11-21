<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use App\Core\Database;

/**
 * Buscador de las solicitudes, antecedentes y acuerdos de todas las actas. Admite comodines: «*» (cualquier cantidad de
 * caracteres) y «?» (un carácter). No distingue mayúsculas ni acentos (intercalación spanish_ci). Una frase se busca tal
 * cual; en el modo «todas las palabras» cada palabra puede estar en cualquier lugar del asunto.
 */
final class Busqueda
{
    public const CAMPOS = [
        'todo' => ['texto_busqueda', 'acuerdo_academico', 'acuerdo_gobierno', 'nota_gobierno'],
        'solicitud' => ['texto_busqueda'],
        'acuerdos' => ['acuerdo_academico', 'acuerdo_gobierno', 'nota_gobierno'],
    ];
    public const MAX = 200;

    /** «*» → %, «?» → _ y escapa lo demás para LIKE. */
    public static function aLike(string $termino): string
    {
        $t = strtr($termino, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']);
        return '%' . strtr($t, ['*' => '%', '?' => '_']) . '%';
    }

    /** Expresión regular equivalente, para resaltar y recortar el fragmento que coincide. */
    public static function aRegex(string $termino): string
    {
        $r = preg_quote($termino, '/');
        $r = strtr($r, ['\\*' => '\\S*', '\\?' => '\\S']);
        return '/' . $r . '/iu';
    }

    /** @return string[] términos (sin vacíos) */
    public static function terminos(string $q, string $modo): array
    {
        $q = trim(preg_replace('/\s+/u', ' ', $q) ?? $q);
        if ($q === '') {
            return [];
        }
        return $modo === 'palabras' ? array_values(array_filter(explode(' ', $q), static fn($p) => trim($p, '*?') !== '')) : [$q];
    }

    /**
     * @return array{filas:array,total:int,recortado:bool}
     */
    public static function buscar(string $q, string $modo, string $campo, ?int $reunionId, string $grupo): array
    {
        $terminos = self::terminos($q, $modo);
        if (!$terminos || trim(implode('', $terminos), '*? ') === '') {
            return ['filas' => [], 'total' => 0, 'recortado' => false];
        }
        $cols = self::CAMPOS[$campo] ?? self::CAMPOS['todo'];
        $where = [];
        $params = [];
        foreach ($terminos as $t) {
            $partes = [];
            foreach ($cols as $c) {
                $partes[] = "a.{$c} LIKE ?";
                $params[] = self::aLike($t);
            }
            $where[] = '(' . implode(' OR ', $partes) . ')';
        }
        if ($reunionId) {
            $where[] = 'a.reunion_id = ?';
            $params[] = $reunionId;
        }
        if (in_array($grupo, ['profesor', 'alumno', 'general'], true)) {
            $where[] = 'a.grupo = ?';
            $params[] = $grupo;
        }
        $filas = Database::todas(
            'SELECT a.*, r.etiqueta, r.academico_fecha, r.gobierno_fecha FROM consejos_asuntos a JOIN consejos_reuniones r ON r.id = a.reunion_id
              WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(r.gobierno_fecha, r.academico_fecha, DATE(r.creado_en)) DESC, FIELD(a.grupo, \'profesor\', \'alumno\', \'general\'), a.numero LIMIT ' . (self::MAX + 1),
            $params
        );
        $recortado = count($filas) > self::MAX;
        $filas = array_slice($filas, 0, self::MAX);
        foreach ($filas as &$f) {
            $f['clave'] = Texto::clave($f['grupo'], (int) $f['numero']);
            $f['fragmentos'] = self::fragmentos($f, $terminos, $cols);
        }
        unset($f);
        return ['filas' => $filas, 'total' => count($filas), 'recortado' => $recortado];
    }

    /** Fragmento con la coincidencia resaltada de cada campo donde aparece. @return array<int,array{campo:string,html:string}> */
    private static function fragmentos(array $f, array $terminos, array $cols): array
    {
        $nombres = ['texto_busqueda' => 'Solicitud y antecedentes', 'acuerdo_academico' => 'Acuerdo del Consejo Académico', 'acuerdo_gobierno' => 'Acuerdo del Consejo de Gobierno', 'nota_gobierno' => 'Nota del Consejo de Gobierno'];
        $salida = [];
        foreach ($cols as $c) {
            $texto = Texto::limpio((string) ($f[$c] ?? ''));
            if ($texto === '') {
                continue;
            }
            foreach ($terminos as $t) {
                if (preg_match(self::aRegex($t), $texto, $m, PREG_OFFSET_CAPTURE)) {
                    $pos = (int) $m[0][1];
                    $ini = max(0, $pos - 90);
                    $frag = mb_strcut($texto, $ini, 280);
                    $html = htmlspecialchars($frag, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                    foreach ($terminos as $t2) {
                        $html = preg_replace(self::aRegex(htmlspecialchars($t2, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')), '<mark>$0</mark>', $html) ?? $html;
                    }
                    $salida[] = ['campo' => $nombres[$c] ?? $c, 'html' => ($ini > 0 ? '… ' : '') . $html . (strlen($texto) > $ini + 280 ? ' …' : '')];
                    break;
                }
            }
        }
        return $salida;
    }
}
