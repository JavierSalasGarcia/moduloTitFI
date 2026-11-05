<?php

declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;

/**
 * Consulta de los registros que el departamento llevaba en Excel (migración 040): solicitudes sin trabajo escrito, protocolos
 * recibidos, control de oficios, «artículo 5» y actas de aplazamiento. Solo lectura.
 */
final class Registros
{
    /** @return array<string,array{titulo:string,tabla:string,fecha:string,buscar:list<string>,columnas:array<string,string>,tiene_carrera:bool,persona:string}> */
    public static function secciones(): array
    {
        return [
            'solicitudes' => ['titulo' => 'Solicitudes EGEL, aprovechamiento y créditos', 'tabla' => 'tit_solicitudes_historicas', 'fecha' => 'fecha_solicitud', 'buscar' => ['alumno', 'cuenta', 'correo'], 'tiene_carrera' => true, 'persona' => 'alumno',
                'columnas' => ['alumno' => 'Alumno', 'cuenta' => 'Cuenta', 'carrera' => 'Licenciatura', 'tipo' => 'Tipo', 'fecha_solicitud' => 'Solicitó', 'fecha_programada' => 'Presentación', 'vez' => 'Vez', 'nivel' => 'Nivel', 'correo' => 'Correo', 'telefono' => 'Teléfono', 'lote' => 'Convocatoria / lote']],
            'protocolos' => ['titulo' => 'Protocolos recibidos', 'tabla' => 'tit_protocolos_recibidos', 'fecha' => 'fecha_entrega', 'buscar' => ['alumno', 'cuenta', 'correo'], 'tiene_carrera' => true, 'persona' => 'alumno',
                'columnas' => ['alumno' => 'Alumno', 'cuenta' => 'Cuenta', 'carrera' => 'Licenciatura', 'modalidad_origen' => 'Modalidad', 'fecha_entrega' => 'Entregó', 'correo' => 'Correo', 'telefono' => 'Teléfono', 'incorporada' => 'Incorporada']],
            'oficios' => ['titulo' => 'Control de oficios', 'tabla' => 'tit_oficios_control', 'fecha' => 'fecha', 'buscar' => ['dirigido', 'asunto', 'persona'], 'tiene_carrera' => false, 'persona' => 'persona',
                'columnas' => ['serie' => 'Año de control', 'numero' => 'Núm.', 'fecha' => 'Fecha', 'tipo' => 'Tipo', 'persona' => 'Persona', 'dirigido' => 'Dirigido a', 'asunto' => 'Asunto', 'tomado_por' => 'Tomado por']],
            'articulo5' => ['titulo' => 'Artículo 5', 'tabla' => 'tit_articulo5', 'fecha' => 'fecha_articulo5', 'buscar' => ['alumno', 'cuenta', 'oficio'], 'tiene_carrera' => true, 'persona' => 'alumno',
                'columnas' => ['alumno' => 'Alumno', 'cuenta' => 'Cuenta', 'plan' => 'Plan', 'fecha_evaluacion' => 'Evaluación', 'fecha_articulo5' => 'Artículo 5', 'oficio' => 'Oficio']],
            'aplazamiento' => ['titulo' => 'Actas de aplazamiento', 'tabla' => 'tit_actas_aplazamiento', 'fecha' => 'fecha', 'buscar' => ['alumno', 'cuenta'], 'tiene_carrera' => true, 'persona' => 'alumno',
                'columnas' => ['consecutivo' => 'Consecutivo', 'alumno' => 'Alumno', 'cuenta' => 'Cuenta', 'carrera' => 'Licenciatura', 'fecha' => 'Fecha']],
        ];
    }

    /** @return array{total:int,filas:list<array<string,mixed>>} */
    public static function listar(string $seccion, array $f, int $limite, int $desde): array
    {
        $s = self::secciones()[$seccion] ?? null;
        if ($s === null) {
            return ['total' => 0, 'filas' => []];
        }
        $donde = ['1=1'];
        $p = [];
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $donde[] = '(' . implode(' OR ', array_map(static fn(string $c): string => "$c LIKE ?", $s['buscar'])) . ')';
            array_push($p, ...array_fill(0, count($s['buscar']), $like));
        }
        if ($s['tiene_carrera'] && ($f['carrera'] ?? '') !== '') {
            $donde[] = 'carrera = ?';
            $p[] = $f['carrera'];
        }
        if (($f['tipo'] ?? '') !== '' && isset($s['columnas']['tipo'])) {
            $donde[] = 'tipo = ?';
            $p[] = $f['tipo'];
        }
        if (preg_match('/^\d{4}$/', (string) ($f['anio'] ?? ''))) {
            $donde[] = "YEAR({$s['fecha']}) = ?";
            $p[] = (int) $f['anio'];
        }
        if (!empty($f['revisar'])) {
            $donde[] = "(fecha_revisar = 1 OR {$s['fecha']} IS NULL)";
        }
        $w = implode(' AND ', $donde);
        $total = (int) Database::valor("SELECT COUNT(*) FROM {$s['tabla']} WHERE $w", $p);
        $orden = $seccion === 'oficios' ? 'serie DESC, numero DESC' : ($seccion === 'aplazamiento' ? 'consecutivo DESC' : "({$s['fecha']} IS NULL), {$s['fecha']} DESC, id DESC");
        $filas = Database::todas("SELECT * FROM {$s['tabla']} WHERE $w ORDER BY $orden LIMIT " . (int) $limite . ' OFFSET ' . (int) $desde, $p);
        return ['total' => $total, 'filas' => $filas];
    }

    /** Conteos por sección para las pestañas. @return array<string,int> */
    public static function conteos(): array
    {
        $c = [];
        foreach (self::secciones() as $k => $s) {
            $c[$k] = (int) Database::valor("SELECT COUNT(*) FROM {$s['tabla']}");
        }
        return $c;
    }

    /** Años presentes en una sección (para el filtro). @return list<int> */
    public static function anios(string $seccion): array
    {
        $s = self::secciones()[$seccion] ?? null;
        if ($s === null) {
            return [];
        }
        return array_map('intval', array_column(Database::todas("SELECT DISTINCT YEAR({$s['fecha']}) AS a FROM {$s['tabla']} WHERE {$s['fecha']} IS NOT NULL ORDER BY a DESC"), 'a'));
    }

    /** Tipos de oficio y de solicitud, para el filtro. @return list<string> */
    public static function tipos(string $seccion): array
    {
        $s = self::secciones()[$seccion] ?? null;
        if ($s === null || !isset($s['columnas']['tipo'])) {
            return [];
        }
        return array_column(Database::todas("SELECT DISTINCT tipo FROM {$s['tabla']} WHERE tipo <> '' ORDER BY tipo"), 'tipo');
    }

    /**
     * Antecedentes de una persona en estos registros: por cuenta o, en los oficios (que solo traen nombre), por las palabras del nombre.
     * @return array{solicitudes:list<array>,protocolos:list<array>,articulo5:list<array>,aplazamiento:list<array>,oficios:list<array>}
     */
    public static function antecedentes(string $cuenta, string $alumno): array
    {
        $r = ['solicitudes' => [], 'protocolos' => [], 'articulo5' => [], 'aplazamiento' => [], 'oficios' => []];
        $cuenta = trim($cuenta);
        if ($cuenta !== '') {
            $r['solicitudes'] = Database::todas('SELECT tipo, fecha_solicitud, fecha_programada, lote FROM tit_solicitudes_historicas WHERE cuenta = ? ORDER BY fecha_solicitud', [$cuenta]);
            $r['protocolos'] = Database::todas('SELECT modalidad_origen, fecha_entrega, incorporada FROM tit_protocolos_recibidos WHERE cuenta = ? ORDER BY fecha_entrega', [$cuenta]);
            $r['articulo5'] = Database::todas('SELECT fecha_evaluacion, fecha_articulo5, oficio FROM tit_articulo5 WHERE cuenta = ? ORDER BY fecha_articulo5', [$cuenta]);
            $r['aplazamiento'] = Database::todas('SELECT consecutivo, fecha FROM tit_actas_aplazamiento WHERE cuenta = ? ORDER BY consecutivo', [$cuenta]);
        }
        $palabras = self::palabras($alumno);
        if (count($palabras) >= 2) {
            usort($palabras, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
            foreach (Database::todas('SELECT serie, numero, fecha, tipo, persona FROM tit_oficios_control WHERE persona LIKE ? ORDER BY fecha', ['%' . addcslashes($palabras[0], '%_\\') . '%']) as $o) {
                $op = self::palabras((string) $o['persona']);
                if (count($op) >= 2 && !array_diff($op, self::palabras($alumno))) {
                    $r['oficios'][] = $o;
                }
            }
        }
        return $r;
    }

    /** Palabras del nombre sin acentos, en mayúsculas y sin partículas. @return list<string> */
    private static function palabras(string $s): array
    {
        $s = strtoupper(strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'À' => 'A', 'È' => 'E', 'Ì' => 'I', 'Ò' => 'O', 'Ù' => 'U']));
        $p = preg_split('/[^A-Z]+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter($p, static fn(string $w): bool => strlen($w) > 2 && !in_array($w, ['DEL', 'LAS', 'LOS'], true))));
    }
}
