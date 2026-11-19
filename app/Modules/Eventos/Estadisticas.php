<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\Auth;
use App\Core\Database;

/**
 * Impacto de Avisos y eventos: cuántas personas abren cada uno (con sesión, una vez por persona), cuántos visitantes
 * sin cuenta (identificador anónimo diario, sin guardar IP ni nombre), aperturas totales, descargas por archivo y
 * desglose por perfil y carrera. Las consultan admin, la Subdirección Académica, coordinadores y la lista de «Estadísticas».
 */
final class Estadisticas
{
    public const PERFILES = ['alumno' => 'Alumnos', 'profesor' => 'Profesores', 'personal' => 'Personal y directivos', 'visitante' => 'Visitantes sin cuenta'];

    public static function esGestor(): bool
    {
        return Auth::tieneRol('admin') || (bool) array_intersect(Auth::deptoClaves(), ['subdireccion_academica', 'titular_de_la_subdireccion_academica']);
    }

    public static function puedeVer(): bool
    {
        if (!Auth::id()) {
            return false;
        }
        if (self::esGestor() || Auth::tieneRol('coordinador')) {
            return true;
        }
        try {
            return (bool) Database::valor('SELECT 1 FROM estadisticas_usuarios WHERE usuario_id = ?', [Auth::id()]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Identificador anónimo de un visitante sin sesión, válido solo por un día (no permite seguirlo entre días). */
    private static function visitante(): string
    {
        return substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . date('Y-m-d') . '|' . __FILE__), 0, 16);
    }

    /** @return array{0:string,1:?string} perfil y siglas del programa de quien hace la petición */
    private static function perfil(): array
    {
        $u = Auth::usuario();
        if (!$u) {
            return ['visitante', null];
        }
        $rol = (string) ($u['rol'] ?? '');
        $perfil = $rol === 'alumno' ? 'alumno' : ($rol === 'profesor' ? 'profesor' : 'personal');
        $prog = $u['programa_siglas'] ?? null;
        if (!$prog) {
            try {
                $prog = Database::valor('SELECT p.siglas FROM programa_profesores pp JOIN programas p ON p.id = pp.programa_id WHERE pp.usuario_id = ? AND pp.activo = 1 ORDER BY p.nivel, p.siglas LIMIT 1', [(int) $u['id']]) ?: null;
            } catch (\Throwable) {
            }
        }
        return [$perfil, $prog ? (string) $prog : null];
    }

    public static function visita(string $tipo, int $id): void
    {
        try {
            [$perfil, $prog] = self::perfil();
            Database::ejecutar('INSERT INTO aviso_visitas (tipo, item_id, usuario_id, visitante, perfil, programa) VALUES (?, ?, ?, ?, ?, ?)',
                [$tipo, $id, Auth::id() ?: null, Auth::id() ? null : self::visitante(), $perfil, $prog]);
        } catch (\Throwable) {
        }
    }

    public static function descarga(int $comunicadoId, int $adjuntoId): void
    {
        try {
            Database::ejecutar('INSERT INTO aviso_descargas (comunicado_id, adjunto_id, usuario_id, visitante) VALUES (?, ?, ?, ?)',
                [$comunicadoId, $adjuntoId, Auth::id() ?: null, Auth::id() ? null : self::visitante()]);
        } catch (\Throwable) {
        }
    }

    /** Resumen de un aviso o evento. */
    public static function de(string $tipo, int $id): array
    {
        $t = Database::una('SELECT COUNT(*) aperturas, COUNT(DISTINCT usuario_id) personas, COUNT(DISTINCT visitante, DATE(creado_en)) visitantes, MIN(creado_en) primera, MAX(creado_en) ultima
                              FROM aviso_visitas WHERE tipo = ? AND item_id = ?', [$tipo, $id]) ?: [];
        // Perfil y carrera: una vez por persona (o por visitante y día).
        $base = "SELECT perfil, programa FROM aviso_visitas WHERE tipo = ? AND item_id = ? GROUP BY COALESCE(usuario_id, CONCAT(visitante, DATE(creado_en))), perfil, programa";
        $porPerfil = [];
        $porPrograma = [];
        foreach (Database::todas($base, [$tipo, $id]) as $r) {
            $porPerfil[$r['perfil']] = ($porPerfil[$r['perfil']] ?? 0) + 1;
            if ($r['programa']) {
                $porPrograma[$r['programa']] = ($porPrograma[$r['programa']] ?? 0) + 1;
            }
        }
        arsort($porPrograma);
        $descargas = $tipo === 'comunicado' ? Database::todas(
            'SELECT a.etiqueta, a.grupo, COUNT(d.id) total, COUNT(DISTINCT d.usuario_id) personas
               FROM comunicado_adjuntos a LEFT JOIN aviso_descargas d ON d.adjunto_id = a.id
              WHERE a.comunicado_id = ? GROUP BY a.id ORDER BY total DESC, a.orden', [$id]) : [];
        return ['aperturas' => (int) ($t['aperturas'] ?? 0), 'personas' => (int) ($t['personas'] ?? 0), 'visitantes' => (int) ($t['visitantes'] ?? 0),
            'primera' => $t['primera'] ?? null, 'ultima' => $t['ultima'] ?? null, 'por_perfil' => $porPerfil, 'por_programa' => $porPrograma,
            'descargas' => $descargas, 'total_descargas' => array_sum(array_map(static fn($d) => (int) $d['total'], $descargas))];
    }

    /** Tabla de todos los avisos y eventos de los últimos meses con sus cifras. */
    public static function resumen(int $meses = 6): array
    {
        $desde = date('Y-m-d', strtotime('-' . $meses . ' months'));
        $filas = [];
        $cifras = [];
        foreach (Database::todas("SELECT tipo, item_id, COUNT(*) aperturas, COUNT(DISTINCT usuario_id) personas, COUNT(DISTINCT visitante, DATE(creado_en)) visitantes,
                    SUM(perfil = 'alumno') a_alumno, SUM(perfil = 'profesor') a_profesor FROM aviso_visitas GROUP BY tipo, item_id") as $r) {
            $cifras[$r['tipo'] . ':' . $r['item_id']] = $r;
        }
        $desc = [];
        foreach (Database::todas('SELECT comunicado_id, COUNT(*) n FROM aviso_descargas GROUP BY comunicado_id') as $r) {
            $desc[(int) $r['comunicado_id']] = (int) $r['n'];
        }
        foreach (Database::todas("SELECT c.id, c.token, c.titulo, c.clase, c.categoria, c.creado_en, d.nombre depto FROM comunicados c JOIN departamentos d ON d.id = c.depto_id WHERE c.creado_en >= ?", [$desde]) as $c) {
            $x = $cifras['comunicado:' . $c['id']] ?? [];
            $filas[] = ['tipo' => ['examenes' => 'Exámenes', 'convocatoria' => 'Convocatoria'][$c['clase']] ?? 'Aviso oficial', 'titulo' => $c['titulo'], 'fecha' => $c['creado_en'],
                'depto' => $c['depto'], 'borrador' => $c['categoria'] === 'Ocultar', 'url' => AvisoController::ruta($c),
                'personas' => (int) ($x['personas'] ?? 0), 'visitantes' => (int) ($x['visitantes'] ?? 0), 'aperturas' => (int) ($x['aperturas'] ?? 0), 'descargas' => $desc[(int) $c['id']] ?? 0];
        }
        foreach (Database::todas('SELECT ev.id, ev.titulo, ev.categoria, ev.inicio, d.nombre depto FROM eventos ev JOIN departamentos d ON d.id = ev.depto_id WHERE ev.inicio >= ?', [$desde]) as $e) {
            $x = $cifras['evento:' . $e['id']] ?? [];
            $filas[] = ['tipo' => $e['categoria'] === 'convocatoria' ? 'Convocatoria' : 'Evento', 'titulo' => $e['titulo'], 'fecha' => $e['inicio'], 'depto' => $e['depto'], 'borrador' => false,
                'url' => 'eventos/' . (int) $e['id'], 'personas' => (int) ($x['personas'] ?? 0), 'visitantes' => (int) ($x['visitantes'] ?? 0), 'aperturas' => (int) ($x['aperturas'] ?? 0), 'descargas' => 0];
        }
        usort($filas, static fn($a, $b) => [$b['personas'] + $b['visitantes'], $b['fecha']] <=> [$a['personas'] + $a['visitantes'], $a['fecha']]);
        return $filas;
    }
}
