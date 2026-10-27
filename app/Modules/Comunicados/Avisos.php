<?php
declare(strict_types=1);

namespace App\Modules\Comunicados;

use App\Core\Auth;
use App\Core\Database;

/**
 * Avisos oficiales dirigidos a alumnos, profesores o ambos, de todos los programas, de un nivel o de varios programas a la vez.
 * Cada aviso es un solo registro: quien pertenece a dos de los programas destinatarios lo ve una vez, con la lista de
 * programas a los que aplica. Dos avisos independientes (por ejemplo, de dos coordinaciones) sí aparecen por separado.
 */
final class Avisos
{
    public const CLAVES_EMISORAS = [
        'coordinacion_de_estudios_avanzados',
        'departamento_de_servicios_escolares',
        'coordinacion_de_difusion_cultural',
        'subdireccion_academica',
        'titular_de_la_subdireccion_academica',
    ];

    public const DESTINATARIOS = ['todos' => 'Alumnos y profesores', 'alumno' => 'Solo alumnos', 'profesor' => 'Solo profesores'];

    private static function claveEmisora(string $clave): bool
    {
        return in_array($clave, self::CLAVES_EMISORAS, true) || str_starts_with($clave, 'coordinacion_de_docencia_de_la_licenciatura');
    }

    /** Departamentos o coordinaciones desde los que la persona puede emitir (todos, si administra). */
    public static function emisores(): array
    {
        if (Auth::tieneRol('admin')) {
            return Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre');
        }
        if (!Auth::tieneRol('titular', 'operativo')) {
            return [];
        }
        $u = Auth::usuario();
        $deptos = [['id' => (int) ($u['departamento_id'] ?? 0), 'clave' => (string) ($u['depto_clave'] ?? ''), 'nombre' => (string) ($u['departamento'] ?? '')]];
        foreach ($u['roles_extra'] ?? [] as $x) {
            if (in_array($x['rol'], ['titular', 'operativo'], true)) {
                $deptos[] = ['id' => (int) $x['departamento_id'], 'clave' => (string) ($x['depto_clave'] ?? ''), 'nombre' => (string) ($x['departamento'] ?? '')];
            }
        }
        $salida = [];
        foreach ($deptos as $d) {
            if ($d['id'] > 0 && self::claveEmisora($d['clave'])) {
                $salida[$d['id']] = ['id' => $d['id'], 'nombre' => $d['nombre']];
            }
        }
        return array_values($salida);
    }

    public static function puedeEmitir(): bool
    {
        return self::emisores() !== [];
    }

    public static function puedeGestionar(array $c): bool
    {
        return Auth::tieneRol('admin') || in_array((int) $c['depto_id'], Auth::departamentoIds(), true);
    }

    /** Publica el aviso (un solo registro) y sus programas destinatarios. @throws \InvalidArgumentException */
    public static function publicar(array $datos): int
    {
        $titulo = trim((string) ($datos['titulo'] ?? ''));
        $contenido = trim((string) ($datos['contenido'] ?? ''));
        $alcance = (string) ($datos['alcance'] ?? '');
        $destinatarios = (string) ($datos['destinatarios'] ?? 'todos');
        $programas = array_values(array_unique(array_filter(array_map('intval', (array) ($datos['programas'] ?? [])))));
        $vence = (string) ($datos['vence_en'] ?? '');
        $deptoId = (int) ($datos['depto_id'] ?? 0);
        if ($titulo === '' || $contenido === '' || !in_array($alcance, ['todos', 'licenciatura', 'posgrado', 'programa'], true) || !isset(self::DESTINATARIOS[$destinatarios])) {
            throw new \InvalidArgumentException('Completa título, contenido, destinatarios y alcance.');
        }
        if ($alcance === 'programa') {
            if (!$programas) {
                throw new \InvalidArgumentException('Elige al menos un programa.');
            }
            $validos = Database::todas('SELECT id FROM programas WHERE id IN (' . implode(',', array_fill(0, count($programas), '?')) . ')', $programas);
            if (count($validos) !== count($programas)) {
                throw new \InvalidArgumentException('Alguno de los programas elegidos no existe.');
            }
        }
        if ($vence !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $vence) || date('Y-m-d', strtotime($vence)) !== $vence)) {
            throw new \InvalidArgumentException('La fecha de vigencia no es válida.');
        }
        $permitidos = array_map('intval', array_column(self::emisores(), 'id'));
        if ($deptoId === 0 || !in_array($deptoId, $permitidos, true)) {
            $deptoId = $permitidos[0] ?? 0;
        }
        if ($deptoId === 0) {
            throw new \InvalidArgumentException('Tu cuenta no está autorizada para emitir comunicados.');
        }
        $id = Database::ejecutar(
            'INSERT INTO comunicados (autor_id, depto_id, titulo, contenido, alcance, destinatarios, programa_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [Auth::id(), $deptoId, $titulo, $contenido, $alcance, $destinatarios, $alcance === 'programa' ? $programas[0] : null]
        );
        if ($alcance === 'programa') {
            foreach ($programas as $pid) {
                Database::ejecutar('INSERT IGNORE INTO comunicado_programas (comunicado_id, programa_id) VALUES (?, ?)', [$id, $pid]);
            }
        }
        if ($vence !== '') {
            Database::ejecutar('INSERT INTO ajustes (clave,valor,descripcion) VALUES (?,?,?)', ['comunicado_vence_' . $id, $vence, 'Vigencia del aviso']);
        }
        return $id;
    }

    /** Condición SQL (alias c) con los avisos que le corresponden a la persona, sin repetir ninguno. */
    public static function condicionVisible(): array
    {
        if (Auth::tieneRol('admin', 'titular', 'operativo', 'coordinador')) {
            return ['', []];
        }
        $roles = array_values(array_intersect(Auth::roles(), ['alumno', 'profesor']));
        $programas = Auth::programaIds();
        // Un profesor sin programa asignado conserva la vista general que siempre tuvo.
        if (!$roles || (!$programas && !in_array('alumno', $roles, true))) {
            return ['', []];
        }
        $niveles = [];
        if ($programas) {
            foreach (Database::todas('SELECT nivel FROM programas WHERE id IN (' . implode(',', array_fill(0, count($programas), '?')) . ')', $programas) as $r) {
                $niveles[$r['nivel'] === 'posgrado' ? 'posgrado' : 'licenciatura'] = true;
            }
        }
        $alcance = ["c.alcance = 'todos'"];
        $params = $roles;
        foreach (array_keys($niveles) as $n) {
            $alcance[] = 'c.alcance = ?';
            $params[] = $n;
        }
        if ($programas) {
            $alcance[] = "(c.alcance = 'programa' AND EXISTS (SELECT 1 FROM comunicado_programas cp WHERE cp.comunicado_id = c.id AND cp.programa_id IN (" . implode(',', array_fill(0, count($programas), '?')) . ')))';
            $params = array_merge($params, $programas);
        }
        $sql = "(c.destinatarios = 'todos' OR c.destinatarios IN (" . implode(',', array_fill(0, count($roles), '?')) . ')) AND (' . implode(' OR ', $alcance) . ')';
        return [$sql, $params];
    }

    /** Avisos visibles para la persona; cada fila trae «programas_lista» con las siglas a las que aplica. */
    public static function visibles(bool $puedeEmitir): array
    {
        $sql = 'SELECT c.*, d.nombre AS depto, u2.nombre AS autor, p.siglas,
                   (SELECT a.valor FROM ajustes a WHERE a.clave = CONCAT(\'comunicado_vence_\', c.id)) AS vence_en
                   FROM comunicados c
                   JOIN departamentos d ON d.id = c.depto_id
                   JOIN usuarios u2 ON u2.id = c.autor_id
                   LEFT JOIN programas p ON p.id = c.programa_id';
        [$cond, $params] = self::condicionVisible();
        $conds = $cond === '' ? [] : [$cond];
        if (!$puedeEmitir) {
            $conds[] = "c.categoria != 'Ocultar'";
        }
        if ($conds) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        $filas = Database::todas($sql . ' ORDER BY CASE WHEN c.categoria = \'Importantes\' THEN 1 ELSE 2 END, c.creado_en DESC', $params);
        if ($filas) {
            $ids = array_column($filas, 'id');
            $mapa = [];
            foreach (Database::todas('SELECT cp.comunicado_id, p.siglas FROM comunicado_programas cp JOIN programas p ON p.id = cp.programa_id WHERE cp.comunicado_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY p.siglas', $ids) as $r) {
                $mapa[(int) $r['comunicado_id']][] = $r['siglas'];
            }
            foreach ($filas as &$f) {
                $f['programas_lista'] = $mapa[(int) $f['id']] ?? [];
            }
            unset($f);
        }
        return $filas;
    }

    /** Texto «Dirigido a …» de un aviso. */
    public static function destino(array $c): string
    {
        $quien = ['todos' => 'Alumnos y profesores', 'alumno' => 'Alumnos', 'profesor' => 'Profesores'][$c['destinatarios'] ?? 'todos'] ?? 'Alumnos y profesores';
        $donde = match ($c['alcance']) {
            'programa' => 'de ' . implode(', ', $c['programas_lista'] ?: array_filter([$c['siglas'] ?? ''])),
            'licenciatura' => 'de Licenciatura',
            'posgrado' => 'de Posgrado',
            default => 'de todos los programas',
        };
        return $quien . ' ' . $donde;
    }
}
