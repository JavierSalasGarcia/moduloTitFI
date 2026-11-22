<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

use App\Core\App;
use App\Core\Database;

/**
 * Índice unificado de «Constancias emitidas». No copia datos: cada fuente tiene un proveedor que consulta sus propias
 * tablas con el alcance de la sesión y devuelve renglones normalizados:
 *   fuente, id, departamento_id, departamento, tipo, persona, fecha, folio, estado ('en_firma'|'firmada'), descargable
 *
 * Fuentes y departamento emisor:
 *  - titulacion: tit_constancias; emisor «subdirector» → Subdirección Académica, «jefe_titulacion» → Departamento de
 *    Evaluación Profesional y Grado (Constancias::TIPOS). Firmada cuando el documento tiene subdir_ruta.
 *  - posgrado: pos_generados vigentes de las claves constancias_revisores, constancias_direccion y no_adeudo; las
 *    firma la Coordinación de Estudios Avanzados (primera dependencia de `posgrado.departamentos`) en pos_firmas.
 *  - constancias: cst_solicitudes en firma o firmadas; emisor = departamento del firmante.
 *
 * Visibilidad (alcance de ConstanciasAccess::alcance): supervisión ve todo; el personal ve lo de sus departamentos;
 * cualquiera ve lo que recibió (solo firmado); en las solicitadas aquí, además, solicitante y firmante.
 */
final class Indice
{
    public const FUENTES = ['titulacion' => 'Titulación de licenciatura', 'posgrado' => 'Evaluación de grado (posgrado)', 'constancias' => 'Solicitada en Constancias'];
    public const LIMITE = 1500;

    /** @return int departamento por clave (0 si no existe) */
    private static function depto(string $clave): int
    {
        static $c = [];
        return $c[$clave] ??= (int) Database::valor('SELECT id FROM departamentos WHERE clave = ?', [$clave]);
    }

    private static function nombresDeptos(): array
    {
        static $n = null;
        return $n ??= array_column(Database::todas('SELECT id, nombre FROM departamentos'), 'nombre', 'id');
    }

    private static function marcas(array $v): string
    {
        return implode(',', array_fill(0, max(1, count($v)), '?'));
    }

    private static function like(string $q): string
    {
        return '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
    }

    /**
     * Renglones firmados visibles para el alcance, con filtros opcionales (q: nombre o folio; anio) y orden
     * (`emision`, `firma` o `depto`; `asc` o `desc`). Las que aún esperan firma se ven en «Solicitar una constancia».
     * @return array{filas:array<int,array>,recortado:bool}
     */
    public static function listar(array $alcance, string $q = '', ?int $anio = null, string $orden = 'firma', string $dir = 'desc'): array
    {
        $filas = [];
        $recortado = false;
        foreach (['titulacion', 'posgrado', 'constancias'] as $fuente) {
            try {
                $r = self::$fuente($alcance, $q, $anio, null);
            } catch (\Throwable $e) {
                // Una fuente que falle (tabla ausente en una instalación parcial) no impide ver las demás.
                error_log('Constancias: la fuente ' . $fuente . ' falló: ' . $e->getMessage());
                continue;
            }
            $recortado = $recortado || count($r) >= self::LIMITE;
            array_push($filas, ...$r);
        }
        $filas = array_values(array_filter($filas, static fn(array $f): bool => $f['estado'] === 'firmada'));
        $clave = static fn(array $f): string => match ($orden) {
            'emision' => (string) $f['fecha_emision'],
            'depto' => mb_strtolower((string) $f['departamento']),
            default => (string) $f['fecha_firma'],
        };
        $sentido = $dir === 'asc' ? 1 : -1;
        usort($filas, static fn(array $a, array $b): int => ($sentido * ($clave($a) <=> $clave($b))) ?: strcmp((string) $b['fecha_firma'], (string) $a['fecha_firma']));
        return ['filas' => $filas, 'recortado' => $recortado];
    }

    /** Un renglón, solo si el alcance lo ve. */
    public static function uno(array $alcance, string $fuente, int $id): ?array
    {
        if (!isset(self::FUENTES[$fuente])) {
            return null;
        }
        try {
            return self::$fuente($alcance, '', null, $id)[0] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Ruta absoluta del PDF firmado de un renglón visible. */
    public static function archivo(array $fila): ?string
    {
        if (($fila['estado'] ?? '') !== 'firmada' || empty($fila['_ruta'])) {
            return null;
        }
        $base = rtrim((string) App::config('archivos.storage'), '/\\');
        $abs = $base . '/' . ltrim((string) $fila['_ruta'], '/');
        return is_file($abs) ? $abs : null;
    }

    /** Condición de «las que recibí» por cuenta, ficha de profesor o correos. @return array{0:string,1:array} */
    private static function recibidas(array $a, string $alias, bool $profesor = true): array
    {
        $sql = [];
        $p = [];
        if (!empty($a['usuario_id'])) {
            $sql[] = "$alias.usuario_id = ?";
            $p[] = (int) $a['usuario_id'];
        }
        if ($profesor && !empty($a['profesor_id'])) {
            $sql[] = "$alias.profesor_id = ?";
            $p[] = (int) $a['profesor_id'];
        }
        if (!empty($a['correos'])) {
            $sql[] = "$alias.email IN (" . self::marcas($a['correos']) . ')';
            array_push($p, ...$a['correos']);
        }
        return [$sql ? '(' . implode(' OR ', $sql) . ')' : '0 = 1', $p];
    }

    // ------------------------------------------------------------------ Titulación

    private static function titulacion(array $a, string $q, ?int $anio, ?int $id): array
    {
        $porEmisor = ['subdirector' => self::depto('subdireccion_academica'), 'jefe_titulacion' => self::depto('departamento_de_evaluacion_profesional_y_grado')];
        $donde = [];
        $p = [];
        if (empty($a['todas'])) {
            $vis = [];
            $emisores = array_keys(array_filter($porEmisor, static fn(int $d): bool => $d > 0 && in_array($d, $a['deptos'], true)));
            if ($emisores) {
                $vis[] = 'c.emisor IN (' . self::marcas($emisores) . ')';
                array_push($p, ...$emisores);
            }
            [$rec, $pr] = self::recibidas($a, 'c');
            $vis[] = "($rec AND d.subdir_ruta IS NOT NULL)";
            array_push($p, ...$pr);
            $donde[] = '(' . implode(' OR ', $vis) . ')';
        }
        if ($id !== null) {
            $donde[] = 'c.id = ?';
            $p[] = $id;
        }
        if ($anio) {
            $donde[] = 'YEAR(COALESCE(d.subdir_en, c.creado_en)) = ?';
            $p[] = $anio;
        }
        if ($q !== '') {
            $donde[] = '(c.nombre LIKE ? OR t.folio LIKE ? OR EXISTS (SELECT 1 FROM tit_verificaciones v2 JOIN tit_oficios o2 ON o2.verificacion = v2.codigo WHERE v2.documento_id = c.documento_id AND o2.texto LIKE ?))';
            $l = self::like($q);
            array_push($p, $l, $l, $l);
        }
        $filas = Database::todas(
            'SELECT c.id, c.tipo, c.nombre, c.emisor, c.detalle, c.creado_en, d.subdir_en, d.subdir_ruta, t.folio AS tramite_folio,
                    (SELECT o.texto FROM tit_verificaciones v JOIN tit_oficios o ON o.verificacion = v.codigo WHERE v.documento_id = c.documento_id ORDER BY o.id DESC LIMIT 1) AS oficio
               FROM tit_constancias c JOIN tit_tramites t ON t.id = c.tramite_id LEFT JOIN tit_documentos d ON d.id = c.documento_id
              WHERE ' . ($donde ? implode(' AND ', $donde) : '1 = 1') . ' ORDER BY COALESCE(d.subdir_en, c.creado_en) DESC LIMIT ' . self::LIMITE,
            $p
        );
        $nombres = self::nombresDeptos();
        $tipos = \App\Modules\Titulacion\Services\Constancias::TIPOS;
        $r = [];
        foreach ($filas as $f) {
            $dep = $porEmisor[$f['emisor']] ?? 0;
            $tipo = $tipos[$f['tipo']][0] ?? 'Constancia';
            $det = json_decode((string) $f['detalle'], true);
            if ($f['tipo'] === 'sinodal' && is_array($det) && !empty($det['funcion'])) {
                $tipo .= ' (' . $det['funcion'] . ')';
            }
            $r[] = [
                'fuente' => 'titulacion', 'id' => (int) $f['id'], 'departamento_id' => $dep,
                'departamento' => $nombres[$dep] ?? \App\Modules\Titulacion\Services\Constancias::EMISORES[$f['emisor']] ?? 'Titulación',
                'tipo' => $tipo, 'persona' => (string) $f['nombre'], 'fecha' => (string) ($f['subdir_en'] ?: $f['creado_en']),
                'fecha_emision' => (string) $f['creado_en'], 'fecha_firma' => (string) ($f['subdir_ruta'] ? ($f['subdir_en'] ?: $f['creado_en']) : ''),
                'folio' => (string) ($f['oficio'] ?: $f['tramite_folio']), 'detalle' => 'Trámite ' . $f['tramite_folio'],
                'estado' => $f['subdir_ruta'] ? 'firmada' : 'en_firma', '_ruta' => (string) ($f['subdir_ruta'] ?? ''),
            ];
        }
        return $r;
    }

    // ------------------------------------------------------------------ Posgrado

    private const POSGRADO = [
        'constancias_revisores' => ['Constancias de revisores', 'sinodal'],
        'constancias_direccion' => ['Constancias de director, codirector y tutor', 'director'],
        'no_adeudo' => ['Constancia de no adeudo de equipo y mobiliario', null],
    ];

    private static function posgrado(array $a, string $q, ?int $anio, ?int $id): array
    {
        $claveDepto = (string) (((array) App::config('posgrado.departamentos', ['coordinacion_de_estudios_avanzados']))[0] ?? 'coordinacion_de_estudios_avanzados');
        $dep = self::depto($claveDepto);
        $claves = array_keys(self::POSGRADO);
        $donde = ['g.clave IN (' . self::marcas($claves) . ')', 'g.vigente = 1', "t.estado <> 'cancelado'"];
        $p = $claves;
        if (empty($a['todas']) && !in_array($dep, $a['deptos'], true)) {
            $correos = $a['correos'] ?: [''];
            $m = self::marcas($correos);
            $donde[] = "f.estado = 'firmada' AND (
                (g.clave = 'no_adeudo' AND (t.alumno_id = ? OR u.email IN ($m)))
                OR (g.clave = 'constancias_revisores' AND EXISTS (SELECT 1 FROM pos_firmas s WHERE s.tramite_id = t.id AND s.rol = 'sinodal' AND s.estado <> 'cancelada' AND s.email IN ($m)))
                OR (g.clave = 'constancias_direccion' AND EXISTS (SELECT 1 FROM pos_firmas s WHERE s.tramite_id = t.id AND s.rol = 'director' AND s.estado <> 'cancelada' AND s.email IN ($m))))";
            array_push($p, (int) ($a['usuario_id'] ?? 0), ...$correos, ...$correos, ...$correos);
        }
        if ($id !== null) {
            $donde[] = 'g.id = ?';
            $p[] = $id;
        }
        if ($anio) {
            $donde[] = 'YEAR(COALESCE(f.firmada_en, g.generado_en)) = ?';
            $p[] = $anio;
        }
        if ($q !== '') {
            $donde[] = '(u.nombre LIKE ? OR t.folio LIKE ?)';
            $l = self::like($q);
            array_push($p, $l, $l);
        }
        $filas = Database::todas(
            "SELECT g.id, g.clave, g.ruta, g.generado_en, t.id AS tramite_id, t.folio, u.nombre AS alumno_nombre, f.estado AS festado, f.firmada_en
               FROM pos_generados g JOIN pos_tramites t ON t.id = g.tramite_id JOIN usuarios u ON u.id = t.alumno_id
               LEFT JOIN pos_firmas f ON f.id = (SELECT MAX(x.id) FROM pos_firmas x WHERE x.generado_id = g.id AND x.rol = 'coordinacion' AND x.estado <> 'cancelada')
              WHERE " . implode(' AND ', $donde) . ' ORDER BY COALESCE(f.firmada_en, g.generado_en) DESC LIMIT ' . self::LIMITE,
            $p
        );
        $nombre = self::nombresDeptos()[$dep] ?? 'Coordinación de Estudios Avanzados';
        $r = [];
        foreach ($filas as $f) {
            $r[] = [
                'fuente' => 'posgrado', 'id' => (int) $f['id'], 'departamento_id' => $dep, 'departamento' => $nombre,
                'tipo' => self::POSGRADO[$f['clave']][0], 'persona' => self::personasPosgrado($f), 'fecha' => (string) ($f['firmada_en'] ?: $f['generado_en']),
                'fecha_emision' => (string) $f['generado_en'], 'fecha_firma' => $f['festado'] === 'firmada' ? (string) $f['firmada_en'] : '',
                'folio' => (string) $f['folio'], 'detalle' => 'Trámite de grado ' . $f['folio'],
                'estado' => $f['festado'] === 'firmada' ? 'firmada' : 'en_firma', '_ruta' => $f['festado'] === 'firmada' ? (string) $f['ruta'] : '',
            ];
        }
        return $r;
    }

    /** A quién se extienden las constancias de posgrado (los nombres están en los datos cifrados del trámite). */
    private static function personasPosgrado(array $f): string
    {
        if ($f['clave'] === 'no_adeudo') {
            return (string) $f['alumno_nombre'];
        }
        try {
            $t = \App\Modules\Posgrado\PosgradoRepository::obtener((int) $f['tramite_id']);
            if ($t) {
                $x = \App\Modules\Posgrado\Services\Formatos::datos($t, \App\Modules\Posgrado\PosgradoRepository::hitos((int) $t['id']));
                $nombres = $f['clave'] === 'constancias_revisores'
                    ? array_column($x['definitivo'], 'nombre')
                    : array_values(array_filter([$x['director'], $x['codirector'], $x['tutor']]));
                if ($nombres) {
                    return implode('; ', $nombres) . ' (egresado(a): ' . $f['alumno_nombre'] . ')';
                }
            }
        } catch (\Throwable) {
        }
        return 'Trámite de ' . $f['alumno_nombre'];
    }

    // ------------------------------------------------------------------ solicitadas en este módulo

    private static function constancias(array $a, string $q, ?int $anio, ?int $id): array
    {
        $donde = ["s.estado IN ('en_firma','firmada')"];
        $p = [];
        if (empty($a['todas'])) {
            // Con «solo_recibidas» no cuentan quien la solicitó ni quien la firmó: solo a quien se le extendió.
            $vis = [];
            if (empty($a['solo_recibidas'])) {
                $vis = ['s.solicitante_id = ?', 's.firmante_id = ?'];
                array_push($p, (int) $a['yo'], (int) $a['yo']);
            }
            if ($a['deptos']) {
                $vis[] = 's.departamento_id IN (' . self::marcas($a['deptos']) . ')';
                array_push($p, ...$a['deptos']);
            }
            $rec = [];
            if (!empty($a['usuario_id'])) {
                $rec[] = 's.persona_usuario_id = ?';
                $p[] = (int) $a['usuario_id'];
            }
            if (!empty($a['profesor_id'])) {
                $rec[] = 's.persona_profesor_id = ?';
                $p[] = (int) $a['profesor_id'];
            }
            $huellas = Solicitudes::huellas($a['correos']);
            if ($huellas) {
                $rec[] = 's.persona_huella IN (' . self::marcas($huellas) . ')';
                array_push($p, ...$huellas);
            }
            if ($rec) {
                $vis[] = "(s.estado = 'firmada' AND (" . implode(' OR ', $rec) . '))';
            }
            $donde[] = $vis ? '(' . implode(' OR ', $vis) . ')' : '0 = 1';
        }
        if ($id !== null) {
            $donde[] = 's.id = ?';
            $p[] = $id;
        }
        if ($anio) {
            $donde[] = 'YEAR(COALESCE(s.firmada_en, s.enviada_en)) = ?';
            $p[] = $anio;
        }
        $filas = Database::todas(
            'SELECT s.id, s.estado, s.asunto, s.folio, s.persona_nombre, s.creado_en, s.enviada_en, s.firmada_en, s.ruta_firmado, s.departamento_id, d.nombre AS departamento, s.firmante_nombre
               FROM cst_solicitudes s JOIN departamentos d ON d.id = s.departamento_id
              WHERE ' . implode(' AND ', $donde) . ' ORDER BY COALESCE(s.firmada_en, s.enviada_en) DESC LIMIT ' . self::LIMITE,
            $p
        );
        $r = [];
        $qq = mb_strtolower($q);
        foreach ($filas as $f) {
            $f = Solicitudes::descifrarFila($f);
            // El nombre está cifrado: la búsqueda por nombre se hace aquí, ya descifrado.
            if ($qq !== '' && !str_contains(mb_strtolower((string) $f['persona_nombre']), $qq) && !str_contains(mb_strtolower((string) $f['folio']), $qq)) {
                continue;
            }
            $asunto = (string) $f['asunto'];
            $r[] = [
                'fuente' => 'constancias', 'id' => (int) $f['id'], 'departamento_id' => (int) $f['departamento_id'], 'departamento' => (string) $f['departamento'],
                'tipo' => self::tipoDeAsunto($asunto),
                'persona' => (string) $f['persona_nombre'], 'fecha' => (string) ($f['firmada_en'] ?: $f['enviada_en']), 'folio' => (string) $f['folio'],
                'fecha_emision' => (string) ($f['enviada_en'] ?: $f['creado_en']), 'fecha_firma' => $f['estado'] === 'firmada' ? (string) $f['firmada_en'] : '',
                'detalle' => 'Firma: ' . $f['firmante_nombre'], 'estado' => $f['estado'] === 'firmada' ? 'firmada' : 'en_firma',
                '_ruta' => $f['estado'] === 'firmada' ? (string) $f['ruta_firmado'] : '',
            ];
        }
        return $r;
    }

    /** «CONSTANCIA» → «Constancia»; «CONSTANCIA DE CURSOS IMPARTIDOS» → «Constancia de cursos impartidos»; otro asunto → «Constancia · …». */
    private static function tipoDeAsunto(string $asunto): string
    {
        $a = mb_strtolower(trim($asunto));
        if ($a === '' || $a === 'constancia') {
            return 'Constancia';
        }
        $a = mb_strtoupper(mb_substr($a, 0, 1)) . mb_substr($a, 1);
        return str_starts_with($a, 'Constancia') ? $a : 'Constancia · ' . $a;
    }

    /** Agrupa por departamento emisor. @return array<string,array<int,array>> */
    public static function porDepartamento(array $filas): array
    {
        $g = [];
        foreach ($filas as $f) {
            $g[$f['departamento']][] = $f;
        }
        ksort($g, SORT_LOCALE_STRING);
        return $g;
    }

    /** Años para el filtro: los últimos ocho. @return int[] */
    public static function anios(): array
    {
        return range((int) date('Y'), (int) date('Y') - 7);
    }
}
