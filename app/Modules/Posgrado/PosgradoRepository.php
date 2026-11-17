<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\Cifrado;
use App\Core\Database;

/**
 * Acceso a datos del módulo; todas las consultas son preparadas.
 * Los datos personales del trámite (pos_tramites.datos y pos_hitos.datos) se cifran en reposo con App\Core\Cifrado,
 * ligados a su registro; sin llave configurada se guardan en claro y se cifrarán en cuanto exista
 * (docs/CIFRADO_DE_DATOS.md). El título del trabajo se guarda aparte, en claro, para la búsqueda de la bandeja.
 */
final class PosgradoRepository
{
    private static function contextoTramite(int $id): string
    {
        return 'pos_tramites.datos#' . $id;
    }

    private static function contextoHito(int $tramiteId, string $clave): string
    {
        return 'pos_hitos.datos#' . $tramiteId . ':' . $clave;
    }

    private static function sellar(array $datos, string $contexto): string
    {
        return (string) Cifrado::cifrar(json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $contexto);
    }

    private static function abrir(?string $valor, string $contexto): array
    {
        if ($valor === null || $valor === '') {
            return [];
        }
        try {
            $claro = Cifrado::descifrar($valor, $contexto);
        } catch (\Throwable $e) {
            error_log('Posgrado: no se pudieron descifrar los datos (' . $contexto . '): ' . $e->getMessage());
            return [];
        }
        $d = json_decode((string) $claro, true);
        return is_array($d) ? $d : [];
    }

    // ------------------------------------------------------------------ trámites

    public static function crear(int $alumnoId, string $nivel, array $datos): int
    {
        $id = Database::ejecutar("INSERT INTO pos_tramites (alumno_id, nivel, datos) VALUES (?, ?, '')", [$alumnoId, $nivel]);
        if ($id <= 0) {
            throw new \RuntimeException('No se obtuvo el identificador del trámite nuevo.');
        }
        $folio = sprintf('PG-%s-%04d', date('Y'), $id);
        Database::ejecutar('UPDATE pos_tramites SET folio = ?, datos = ? WHERE id = ?', [$folio, self::sellar($datos, self::contextoTramite($id)), $id]);
        return $id;
    }

    public static function obtener(int $id): ?array
    {
        $t = Database::una(
            'SELECT t.*, u.nombre AS alumno_nombre, u.email AS alumno_email, u.genero AS alumno_genero, r.nombre AS responsable_nombre
             FROM pos_tramites t
             JOIN usuarios u ON u.id = t.alumno_id
             LEFT JOIN usuarios r ON r.id = t.responsable_id
             WHERE t.id = ?',
            [$id]
        );
        if ($t === null) {
            return null;
        }
        $t['datos'] = self::abrir((string) $t['datos'], self::contextoTramite((int) $t['id']));
        return $t;
    }

    /** Guarda los datos y refleja en columnas lo que usa la bandeja (nivel, modalidad y título). */
    public static function guardarDatos(int $id, array $datos): void
    {
        $i = $datos['interesado'] ?? [];
        $nivel = array_key_exists((string) ($i['nivel'] ?? ''), Catalogo::niveles()) ? (string) $i['nivel'] : 'maestria';
        $modalidad = array_key_exists((string) ($i['modalidad'] ?? ''), Catalogo::modalidades()) ? (string) $i['modalidad'] : '';
        $titulo = trim((string) ($i['titulo'] ?? ''));
        Database::ejecutar(
            'UPDATE pos_tramites SET datos = ?, nivel = ?, modalidad = ?, titulo = ? WHERE id = ?',
            [self::sellar($datos, self::contextoTramite($id)), $nivel, $modalidad, $titulo !== '' ? mb_substr($titulo, 0, 500) : null, $id]
        );
    }

    /** Un trámite en pausa (abandonado) o cancelado conserva su estado: solo el egresado lo retoma (Services\Abandono). */
    public static function actualizarAvance(int $id, int $momento, string $estado, ?string $resultado): void
    {
        Database::ejecutar("UPDATE pos_tramites SET momento = ?, estado = IF(estado IN ('abandonado','cancelado'), estado, ?), resultado = ? WHERE id = ?", [$momento, $estado, $resultado, $id]);
    }

    public static function asignarResponsable(int $id, int $usuarioId): void
    {
        Database::ejecutar('UPDATE pos_tramites SET responsable_id = COALESCE(responsable_id, ?) WHERE id = ?', [$usuarioId, $id]);
    }

    public static function delAlumno(int $alumnoId): array
    {
        return Database::todas(
            'SELECT id, folio, nivel, modalidad, titulo, momento, estado, resultado, creado_en, actualizado_en, abandonado_en
             FROM pos_tramites WHERE alumno_id = ? ORDER BY creado_en DESC',
            [$alumnoId]
        );
    }

    public static function abiertoDe(int $alumnoId): ?array
    {
        return Database::una("SELECT id, folio FROM pos_tramites WHERE alumno_id = ? AND estado = 'abierto' LIMIT 1", [$alumnoId]);
    }

    /** Bandeja del personal con el conteo de documentos por validar. */
    public static function bandeja(array $f): array
    {
        $donde = ['1 = 1'];
        $p = [];
        if (($f['estado'] ?? '') !== '') {
            $donde[] = 't.estado = ?';
            $p[] = $f['estado'];
        }
        if ((int) ($f['momento'] ?? 0) > 0) {
            $donde[] = "t.momento = ? AND t.estado = 'abierto'";
            $p[] = (int) $f['momento'];
        }
        if (($f['nivel'] ?? '') !== '') {
            $donde[] = 't.nivel = ?';
            $p[] = $f['nivel'];
        }
        if (!empty($f['pendientes'])) {
            // Un trámite en pausa no es pendiente del personal.
            $donde[] = "t.estado = 'abierto' AND EXISTS (SELECT 1 FROM pos_documentos d WHERE d.tramite_id = t.id AND d.estado = 'subido')";
        }
        if (($f['q'] ?? '') !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $f['q']) . '%';
            $donde[] = '(t.folio LIKE ? OR u.nombre LIKE ? OR u.email LIKE ? OR t.titulo LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        return Database::todas(
            "SELECT t.id, t.folio, t.nivel, t.modalidad, t.titulo, t.momento, t.estado, t.resultado, t.actualizado_en, t.abandonado_en,
                    u.nombre AS alumno_nombre, u.email AS alumno_email, r.nombre AS responsable_nombre,
                    IF(t.estado = 'abierto', (SELECT COUNT(*) FROM pos_documentos d WHERE d.tramite_id = t.id AND d.estado = 'subido'), 0) AS por_validar,
                    (SELECT COUNT(*) FROM pos_firmas f JOIN pos_generados g ON g.id = f.generado_id AND g.vigente = 1
                      WHERE f.tramite_id = t.id AND f.documento = 'visto_bueno' AND f.estado = 'firmada'
                        AND 'no' IN (JSON_UNQUOTE(JSON_EXTRACT(f.respuestas, '$.concluida')), JSON_UNQUOTE(JSON_EXTRACT(f.respuestas, '$.entregada')), JSON_UNQUOTE(JSON_EXTRACT(f.respuestas, '$.periodo')))) AS respuestas_no,
                    (SELECT COUNT(*) FROM pos_firmas f JOIN pos_generados g ON g.id = f.generado_id AND g.vigente = 1
                      WHERE f.tramite_id = t.id AND f.rol = 'coordinacion' AND f.estado = 'pendiente') AS en_firma_coord
             FROM pos_tramites t
             JOIN usuarios u ON u.id = t.alumno_id
             LEFT JOIN usuarios r ON r.id = t.responsable_id
             WHERE " . implode(' AND ', $donde) . '
             ORDER BY por_validar DESC, t.actualizado_en DESC
             LIMIT 300',
            $p
        );
    }

    /** Contadores de la bandeja: por momento (abiertos), por validar y concluidos. */
    public static function resumen(): array
    {
        $r = ['por_validar' => (int) Database::valor("SELECT COUNT(*) FROM pos_documentos d JOIN pos_tramites t ON t.id = d.tramite_id WHERE d.estado = 'subido' AND t.estado = 'abierto'"),
            'concluidos' => (int) Database::valor("SELECT COUNT(*) FROM pos_tramites WHERE estado = 'concluido'"),
            'abandonados' => (int) Database::valor("SELECT COUNT(*) FROM pos_tramites WHERE estado = 'abandonado'"),
            'abiertos' => 0, 'momentos' => [1 => 0, 2 => 0, 3 => 0, 4 => 0]];
        foreach (Database::todas("SELECT momento, COUNT(*) AS n FROM pos_tramites WHERE estado = 'abierto' GROUP BY momento") as $f) {
            $r['momentos'][(int) $f['momento']] = (int) $f['n'];
            $r['abiertos'] += (int) $f['n'];
        }
        return $r;
    }

    // ---------------------------------------------------------------- documentos

    /** Versión vigente (no reemplazada) de cada documento, por clave. */
    public static function documentosVigentes(int $tramiteId): array
    {
        $mapa = [];
        foreach (Database::todas(
            "SELECT d.*, u.nombre AS subido_nombre, r.nombre AS revisor_nombre
             FROM pos_documentos d
             JOIN usuarios u ON u.id = d.subido_por
             LEFT JOIN usuarios r ON r.id = d.revisado_por
             WHERE d.tramite_id = ? AND d.estado <> 'reemplazado'
             ORDER BY d.id",
            [$tramiteId]
        ) as $f) {
            $mapa[$f['clave']] = $f;
        }
        return $mapa;
    }

    public static function documento(int $id): ?array
    {
        return Database::una('SELECT * FROM pos_documentos WHERE id = ?', [$id]);
    }

    public static function registrarDocumento(int $tramiteId, string $clave, string $tipo, string $estado, ?string $ruta, ?string $nombre, ?string $huella, int $usuarioId): int
    {
        self::retirarDocumento($tramiteId, $clave);
        return Database::ejecutar(
            'INSERT INTO pos_documentos (tramite_id, clave, tipo, estado, ruta, nombre_original, huella, subido_por, revisado_por, revisado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$tramiteId, $clave, $tipo, $estado, $ruta, $nombre, $huella, $usuarioId,
                $tipo === 'fisico' ? $usuarioId : null, $tipo === 'fisico' ? date('Y-m-d H:i:s') : null]
        );
    }

    /** Deja la versión vigente como reemplazada (nueva versión, físico desmarcado o reprogramación). */
    public static function retirarDocumento(int $tramiteId, string $clave): void
    {
        Database::ejecutar(
            "UPDATE pos_documentos SET estado = 'reemplazado' WHERE tramite_id = ? AND clave = ? AND estado <> 'reemplazado'",
            [$tramiteId, $clave]
        );
    }

    public static function revisarDocumento(int $id, string $estado, ?string $comentario, int $revisorId): void
    {
        Database::ejecutar(
            'UPDATE pos_documentos SET estado = ?, comentario = ?, revisado_por = ?, revisado_en = NOW() WHERE id = ?',
            [$estado, $comentario, $revisorId, $id]
        );
    }

    // --------------------------------------------------------------------- hitos

    public static function hitos(int $tramiteId): array
    {
        $mapa = [];
        foreach (Database::todas(
            'SELECT h.*, u.nombre AS completado_nombre FROM pos_hitos h JOIN usuarios u ON u.id = h.completado_por WHERE h.tramite_id = ?',
            [$tramiteId]
        ) as $h) {
            $h['datos'] = self::abrir((string) $h['datos'], self::contextoHito($tramiteId, (string) $h['clave']));
            $mapa[$h['clave']] = $h;
        }
        return $mapa;
    }

    public static function guardarHito(int $tramiteId, string $clave, array $datos, int $usuarioId): void
    {
        Database::ejecutar(
            'INSERT INTO pos_hitos (tramite_id, clave, datos, completado_por) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE datos = VALUES(datos), completado_por = VALUES(completado_por), completado_en = NOW()',
            [$tramiteId, $clave, self::sellar($datos, self::contextoHito($tramiteId, $clave)), $usuarioId]
        );
    }

    public static function borrarHito(int $tramiteId, string $clave): void
    {
        Database::ejecutar('DELETE FROM pos_hitos WHERE tramite_id = ? AND clave = ?', [$tramiteId, $clave]);
    }

    // ------------------------------------------------------------------- eventos

    public static function evento(int $tramiteId, int $usuarioId, string $tipo, string $detalle, bool $visibleAlumno = true): void
    {
        Database::ejecutar(
            'INSERT INTO pos_eventos (tramite_id, usuario_id, tipo, detalle, visible_alumno) VALUES (?, ?, ?, ?, ?)',
            [$tramiteId, $usuarioId, mb_substr($tipo, 0, 30), mb_substr($detalle, 0, 500), $visibleAlumno ? 1 : 0]
        );
    }

    public static function eventos(int $tramiteId, bool $soloAlumno): array
    {
        return Database::todas(
            'SELECT e.*, u.nombre AS autor FROM pos_eventos e JOIN usuarios u ON u.id = e.usuario_id
             WHERE e.tramite_id = ? ' . ($soloAlumno ? 'AND e.visible_alumno = 1 ' : '') . 'ORDER BY e.id DESC LIMIT 100',
            [$tramiteId]
        );
    }

    // ----------------------------------------------------------------- generados

    /** Registra un PDF emitido; la emisión anterior de la misma clave deja de estar vigente. */
    public static function registrarGenerado(int $tramiteId, string $clave, string $nombre, string $ruta, string $huella, int $usuarioId, ?string $huellaDatos = null): int
    {
        self::retirarGenerado($tramiteId, $clave);
        return Database::ejecutar(
            'INSERT INTO pos_generados (tramite_id, clave, nombre, ruta, huella, vigente, huella_datos, generado_por) VALUES (?, ?, ?, ?, ?, 1, ?, ?)',
            [$tramiteId, $clave, mb_substr($nombre, 0, 160), $ruta, $huella, $huellaDatos, $usuarioId]
        );
    }

    /** Deja sin vigencia la emisión de una clave (se regeneró, se devolvió la propuesta o se aplazó el examen). */
    public static function retirarGenerado(int $tramiteId, string $clave): void
    {
        Database::ejecutar('UPDATE pos_generados SET vigente = 0 WHERE tramite_id = ? AND clave = ? AND vigente = 1', [$tramiteId, $clave]);
    }

    /** Tras estampar un sello: el archivo vigente es el nuevo, con su huella. */
    public static function actualizarArchivoGenerado(int $id, string $ruta, string $huella): void
    {
        Database::ejecutar('UPDATE pos_generados SET ruta = ?, huella = ? WHERE id = ?', [$ruta, $huella, $id]);
    }

    /** Emisión vigente de cada clave. */
    public static function generadosVigentes(int $tramiteId): array
    {
        $mapa = [];
        foreach (Database::todas(
            'SELECT g.*, u.nombre AS generado_nombre FROM pos_generados g JOIN usuarios u ON u.id = g.generado_por WHERE g.tramite_id = ? AND g.vigente = 1 ORDER BY g.id',
            [$tramiteId]
        ) as $g) {
            $mapa[$g['clave']] = $g;
        }
        return $mapa;
    }

    public static function generado(int $id): ?array
    {
        return Database::una('SELECT * FROM pos_generados WHERE id = ?', [$id]);
    }
}
