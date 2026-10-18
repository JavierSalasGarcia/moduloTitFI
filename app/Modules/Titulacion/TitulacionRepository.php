<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Database;

/** Acceso a datos del módulo; todas las consultas son preparadas. */
final class TitulacionRepository
{
    public static function crear(int $alumnoId, string $tipo, string $modalidad, ?string $variante, array $datosIniciales): int
    {
        $id = Database::ejecutar(
            "INSERT INTO tit_tramites (alumno_id, tipo, modalidad, variante, datos) VALUES (?, ?, ?, ?, ?)",
            [$alumnoId, $tipo, $modalidad, $variante, json_encode($datosIniciales, JSON_UNESCAPED_UNICODE)]
        );
        $folio = sprintf('TIT-%s-%05d', date('Y'), $id);
        Database::ejecutar('UPDATE tit_tramites SET folio = ? WHERE id = ?', [$folio, $id]);
        return $id;
    }

    public static function obtener(int $id): ?array
    {
        $t = Database::una(
            'SELECT t.*, u.nombre AS alumno_nombre, u.email AS alumno_email, r.nombre AS responsable_nombre
             FROM tit_tramites t
             JOIN usuarios u ON u.id = t.alumno_id
             LEFT JOIN usuarios r ON r.id = t.responsable_id
             WHERE t.id = ?',
            [$id]
        );
        if ($t === null) {
            return null;
        }
        $datos = json_decode((string) $t['datos'], true);
        $t['datos'] = is_array($datos) ? $datos : [];
        return $t;
    }

    public static function guardarDatos(int $id, array $datos, ?string $titulo = null): void
    {
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
        if ($titulo !== null) {
            Database::ejecutar('UPDATE tit_tramites SET datos = ?, titulo = ? WHERE id = ?', [$json, mb_substr($titulo, 0, 500), $id]);
        } else {
            Database::ejecutar('UPDATE tit_tramites SET datos = ? WHERE id = ?', [$json, $id]);
        }
    }

    public static function actualizarAvance(int $id, string $etapa, string $estado, ?string $resultado = null): void
    {
        Database::ejecutar(
            'UPDATE tit_tramites SET etapa_actual = ?, estado = ?, resultado = COALESCE(?, resultado) WHERE id = ?',
            [$etapa, $estado, $resultado, $id]
        );
    }

    public static function delAlumno(int $alumnoId): array
    {
        return Database::todas(
            'SELECT * FROM tit_tramites WHERE alumno_id = ? ORDER BY creado_en DESC',
            [$alumnoId]
        );
    }

    /** Bandeja del personal con conteo de documentos por validar. */
    public static function bandeja(array $f): array
    {
        $donde = ['1 = 1'];
        $p = [];
        if (($f['estado'] ?? '') !== '') {
            $donde[] = 't.estado = ?';
            $p[] = $f['estado'];
        }
        if (($f['modalidad'] ?? '') !== '') {
            $donde[] = 't.modalidad = ?';
            $p[] = $f['modalidad'];
        }
        if (($f['nivel'] ?? '') !== '') {
            $donde[] = 't.nivel = ?';
            $p[] = $f['nivel'];
        }
        if (!empty($f['pendientes'])) {
            $donde[] = 'EXISTS (SELECT 1 FROM tit_documentos d WHERE d.tramite_id = t.id AND d.estado = \'en_revision\' AND d.origen = \'alumno\')';
        }
        if (($f['q'] ?? '') !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $f['q']) . '%';
            $donde[] = '(t.folio LIKE ? OR u.nombre LIKE ? OR u.email LIKE ? OR t.titulo LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        return Database::todas(
            "SELECT t.id, t.folio, t.modalidad, t.tipo, t.estado, t.etapa_actual, t.titulo, t.resultado, t.actualizado_en,
                    u.nombre AS alumno_nombre, u.email AS alumno_email, r.nombre AS responsable_nombre,
                    (SELECT COUNT(*) FROM tit_documentos d WHERE d.tramite_id = t.id AND d.estado = 'en_revision' AND d.origen = 'alumno') AS por_validar
             FROM tit_tramites t
             JOIN usuarios u ON u.id = t.alumno_id
             LEFT JOIN usuarios r ON r.id = t.responsable_id
             WHERE " . implode(' AND ', $donde) . "
             ORDER BY por_validar DESC, t.actualizado_en DESC
             LIMIT 300",
            $p
        );
    }

    public static function resumenBandeja(): array
    {
        return [
            'abiertos' => (int) Database::valor("SELECT COUNT(*) FROM tit_tramites WHERE estado = 'abierto'"),
            'por_validar' => (int) Database::valor("SELECT COUNT(*) FROM tit_documentos WHERE estado = 'en_revision' AND origen = 'alumno'"),
            'concluidos' => (int) Database::valor("SELECT COUNT(*) FROM tit_tramites WHERE estado = 'concluido'"),
        ];
    }

    // ----------------------------------------------------------------- documentos

    /** Documentos vigentes (sin versiones reemplazadas), el más reciente por etapa y clave. */
    public static function documentosVigentes(int $tramiteId): array
    {
        $filas = Database::todas(
            "SELECT d.*, u.nombre AS subido_nombre, r.nombre AS revisor_nombre, c.nombre AS cotejo_nombre
             FROM tit_documentos d
             JOIN usuarios u ON u.id = d.subido_por
             LEFT JOIN usuarios r ON r.id = d.revisado_por
             LEFT JOIN usuarios c ON c.id = d.cotejo_por
             WHERE d.tramite_id = ? AND d.estado <> 'reemplazado'
             ORDER BY d.id",
            [$tramiteId]
        );
        $mapa = [];
        foreach ($filas as $f) {
            $mapa[$f['etapa'] . '|' . $f['clave']] = $f; // el último gana
        }
        return $mapa;
    }

    public static function historialDocumento(int $tramiteId, string $etapa, string $clave): array
    {
        return Database::todas(
            'SELECT d.*, u.nombre AS subido_nombre FROM tit_documentos d JOIN usuarios u ON u.id = d.subido_por
             WHERE d.tramite_id = ? AND d.etapa = ? AND d.clave = ? ORDER BY d.id DESC',
            [$tramiteId, $etapa, $clave]
        );
    }

    public static function documento(int $id): ?array
    {
        return Database::una('SELECT * FROM tit_documentos WHERE id = ?', [$id]);
    }

    public static function registrarDocumento(int $tramiteId, string $etapa, string $clave, string $origen, string $estado, ?string $ruta, ?string $nombreOriginal, int $usuarioId): int
    {
        Database::ejecutar(
            "UPDATE tit_documentos SET estado = 'reemplazado' WHERE tramite_id = ? AND etapa = ? AND clave = ? AND estado <> 'reemplazado'",
            [$tramiteId, $etapa, $clave]
        );
        return Database::ejecutar(
            'INSERT INTO tit_documentos (tramite_id, etapa, clave, origen, estado, ruta, nombre_original, subido_por, revisado_por, revisado_en)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$tramiteId, $etapa, $clave, $origen, $estado, $ruta, $nombreOriginal, $usuarioId,
                $origen === 'personal' ? $usuarioId : null, $origen === 'personal' ? date('Y-m-d H:i:s') : null]
        );
    }

    public static function revisarDocumento(int $id, string $estado, ?string $comentario, int $revisorId): void
    {
        Database::ejecutar(
            'UPDATE tit_documentos SET estado = ?, comentario = ?, revisado_por = ?, revisado_en = NOW() WHERE id = ?',
            [$estado, $comentario, $revisorId, $id]
        );
    }

    public static function registrarCotejo(int $docId, string $tipo, int $usuarioId, string $hash, ?string $ruta): void
    {
        Database::ejecutar(
            'UPDATE tit_documentos SET cotejo_tipo = ?, cotejo_por = ?, cotejo_en = NOW(), cotejo_hash = ?, cotejo_ruta = ? WHERE id = ?',
            [$tipo, $usuarioId, $hash, $ruta, $docId]
        );
    }

    public static function quitarCotejo(int $docId): void
    {
        Database::ejecutar(
            'UPDATE tit_documentos SET cotejo_tipo = NULL, cotejo_por = NULL, cotejo_en = NULL, cotejo_hash = NULL, cotejo_ruta = NULL WHERE id = ?',
            [$docId]
        );
    }

    // --------------------------------------------------------------------- hitos

    public static function hitos(int $tramiteId): array
    {
        $mapa = [];
        foreach (Database::todas(
            'SELECT h.*, u.nombre AS completado_nombre FROM tit_hitos h JOIN usuarios u ON u.id = h.completado_por WHERE h.tramite_id = ?',
            [$tramiteId]
        ) as $h) {
            $datos = json_decode((string) $h['datos'], true);
            $h['datos'] = is_array($datos) ? $datos : [];
            $mapa[$h['etapa']] = $h;
        }
        return $mapa;
    }

    public static function guardarHito(int $tramiteId, string $etapa, array $datos, int $usuarioId): void
    {
        Database::ejecutar(
            'INSERT INTO tit_hitos (tramite_id, etapa, datos, completado_por) VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE datos = VALUES(datos), completado_por = VALUES(completado_por), completado_en = NOW()',
            [$tramiteId, $etapa, json_encode($datos, JSON_UNESCAPED_UNICODE), $usuarioId]
        );
    }

    public static function borrarHito(int $tramiteId, string $etapa): void
    {
        Database::ejecutar('DELETE FROM tit_hitos WHERE tramite_id = ? AND etapa = ?', [$tramiteId, $etapa]);
    }

    // ------------------------------------------------------------------- eventos

    public static function evento(int $tramiteId, int $usuarioId, string $tipo, string $detalle, bool $visibleAlumno = true): void
    {
        Database::ejecutar(
            'INSERT INTO tit_eventos (tramite_id, usuario_id, tipo, detalle, visible_alumno) VALUES (?, ?, ?, ?, ?)',
            [$tramiteId, $usuarioId, $tipo, mb_substr($detalle, 0, 500), $visibleAlumno ? 1 : 0]
        );
    }

    public static function eventos(int $tramiteId, bool $soloAlumno): array
    {
        return Database::todas(
            'SELECT e.*, u.nombre AS autor FROM tit_eventos e JOIN usuarios u ON u.id = e.usuario_id
             WHERE e.tramite_id = ? ' . ($soloAlumno ? 'AND e.visible_alumno = 1 ' : '') . 'ORDER BY e.id DESC LIMIT 100',
            [$tramiteId]
        );
    }
}
