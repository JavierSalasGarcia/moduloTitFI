<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Database;

/** Consultas de comisiones, rondas de revisión y evaluadores. */
final class RevisionRepository
{
    // ---------------------------------------------------------------- comisiones

    public static function miembros(string $carrera, bool $soloActivos = true): array
    {
        return Database::todas(
            'SELECT * FROM tit_comision_miembros WHERE carrera = ?' . ($soloActivos ? ' AND activo = 1' : '') . " ORDER BY rol = 'encargado' DESC, nombre",
            [$carrera]
        );
    }

    public static function todosLosMiembros(): array
    {
        return Database::todas("SELECT * FROM tit_comision_miembros ORDER BY carrera, rol = 'encargado' DESC, nombre");
    }

    public static function miembro(int $id): ?array
    {
        return Database::una('SELECT * FROM tit_comision_miembros WHERE id = ?', [$id]);
    }

    /** Pertenencias activas de un correo a comisiones. */
    public static function pertenencias(string $email): array
    {
        return Database::todas('SELECT * FROM tit_comision_miembros WHERE activo = 1 AND email = ?', [strtolower(trim($email))]);
    }

    public static function encargados(string $carrera): array
    {
        return Database::todas("SELECT * FROM tit_comision_miembros WHERE carrera = ? AND rol = 'encargado' AND activo = 1", [$carrera]);
    }

    public static function comisionConfigurada(string $carrera): bool
    {
        return $carrera !== '' && self::encargados($carrera) !== [];
    }

    // -------------------------------------------------------------------- rondas

    public static function ronda(int $id): ?array
    {
        return Database::una(
            'SELECT r.*, t.folio, t.titulo, t.modalidad, t.alumno_id, t.datos AS tramite_datos, t.estado AS tramite_estado,
                    u.nombre AS alumno_nombre, u.email AS alumno_email, d.ruta AS doc_ruta, d.nombre_original AS doc_nombre
             FROM tit_revisiones r
             JOIN tit_tramites t ON t.id = r.tramite_id
             JOIN usuarios u ON u.id = t.alumno_id
             LEFT JOIN tit_documentos d ON d.id = r.documento_id
             WHERE r.id = ?',
            [$id]
        );
    }

    public static function rondasDeTramite(int $tramiteId): array
    {
        return Database::todas('SELECT * FROM tit_revisiones WHERE tramite_id = ? ORDER BY numero DESC', [$tramiteId]);
    }

    /** Integrantes que firmaron la vuelta anterior del mismo trámite (para proponerlos en una nueva vuelta). @return int[] */
    public static function revisoresAnteriores(int $tramiteId, int $numero): array
    {
        return array_map('intval', array_column(Database::todas(
            "SELECT a.miembro_id FROM tit_revision_asignaciones a JOIN tit_revisiones r ON r.id = a.revision_id
             WHERE r.tramite_id = ? AND r.numero = ? AND a.estado = 'firmada'",
            [$tramiteId, $numero - 1]
        ), 'miembro_id'));
    }

    public static function rondaAbierta(int $tramiteId): ?array
    {
        return Database::una(
            "SELECT * FROM tit_revisiones WHERE tramite_id = ? AND estado NOT IN ('dictaminada','cancelada') ORDER BY numero DESC LIMIT 1",
            [$tramiteId]
        );
    }

    public static function crearRonda(int $tramiteId, string $carrera, int $docId): int
    {
        $n = (int) Database::valor('SELECT COALESCE(MAX(numero), 0) FROM tit_revisiones WHERE tramite_id = ?', [$tramiteId]) + 1;
        return Database::ejecutar(
            'INSERT INTO tit_revisiones (tramite_id, numero, documento_id, carrera) VALUES (?, ?, ?, ?)',
            [$tramiteId, $n, $docId, $carrera]
        );
    }

    /**
     * Rondas con su avance de evaluadores. $filtros: estados (array), carreras (array).
     */
    public static function rondas(array $filtros = []): array
    {
        $donde = ['1 = 1'];
        $p = [];
        foreach (['estados' => 'r.estado', 'carreras' => 'r.carrera'] as $k => $col) {
            if (!empty($filtros[$k])) {
                $donde[] = $col . ' IN (' . implode(',', array_fill(0, count($filtros[$k]), '?')) . ')';
                array_push($p, ...array_values($filtros[$k]));
            }
        }
        return Database::todas(
            "SELECT r.*, t.folio, t.titulo, t.modalidad, u.nombre AS alumno_nombre,
                    (SELECT COUNT(*) FROM tit_revision_asignaciones a WHERE a.revision_id = r.id AND a.estado <> 'cancelada') AS total,
                    (SELECT COUNT(*) FROM tit_revision_asignaciones a WHERE a.revision_id = r.id AND a.estado = 'firmada') AS firmadas,
                    (SELECT MIN(a.fecha_limite) FROM tit_revision_asignaciones a WHERE a.revision_id = r.id AND a.estado IN ('pendiente','borrador')) AS limite_pendiente
             FROM tit_revisiones r
             JOIN tit_tramites t ON t.id = r.tramite_id
             JOIN usuarios u ON u.id = t.alumno_id
             WHERE " . implode(' AND ', $donde) . "
             ORDER BY FIELD(r.estado, 'recibida','en_comision','por_dictaminar','en_evaluacion','dictaminada','cancelada'), r.fecha_recepcion DESC
             LIMIT 300",
            $p
        );
    }

    public static function actualizarRonda(int $id, array $campos): void
    {
        $sets = [];
        $p = [];
        foreach ($campos as $col => $v) {
            if (!in_array($col, ['estado', 'enviada_por', 'enviada_en', 'evaluacion_en', 'dictamen', 'requiere_coord', 'observaciones', 'mensaje', 'obs_oficio', 'dictaminado_por', 'dictaminado_en', 'firma_tipo', 'dictamen_ruta', 'dictamen_hash', 'documento_id', 'carrera'], true)) {
                continue;
            }
            $sets[] = $col . ' = ?';
            $p[] = $v;
        }
        if ($sets) {
            $p[] = $id;
            Database::ejecutar('UPDATE tit_revisiones SET ' . implode(', ', $sets) . ' WHERE id = ?', $p);
        }
    }

    // ------------------------------------------------------------- asignaciones

    public static function asignaciones(int $rondaId): array
    {
        return Database::todas(
            'SELECT a.*, m.nombre AS miembro_nombre, m.email AS miembro_email, m.rol AS miembro_rol
             FROM tit_revision_asignaciones a JOIN tit_comision_miembros m ON m.id = a.miembro_id
             WHERE a.revision_id = ? ORDER BY a.id',
            [$rondaId]
        );
    }

    public static function asignacion(int $id): ?array
    {
        return Database::una(
            'SELECT a.*, m.nombre AS miembro_nombre, m.email AS miembro_email, m.carrera AS miembro_carrera
             FROM tit_revision_asignaciones a JOIN tit_comision_miembros m ON m.id = a.miembro_id WHERE a.id = ?',
            [$id]
        );
    }

    public static function asignacionPorToken(string $token): ?array
    {
        return Database::una(
            'SELECT a.*, m.nombre AS miembro_nombre, m.email AS miembro_email, m.carrera AS miembro_carrera
             FROM tit_revision_asignaciones a JOIN tit_comision_miembros m ON m.id = a.miembro_id WHERE a.token_hash = ?',
            [hash('sha256', $token)]
        );
    }

    public static function crearAsignacion(int $rondaId, int $miembroId, string $tokenHash, string $limite, string $vence, int $porId): int
    {
        return Database::ejecutar(
            'INSERT INTO tit_revision_asignaciones (revision_id, miembro_id, token_hash, token_vence, fecha_limite, asignada_por) VALUES (?, ?, ?, ?, ?, ?)',
            [$rondaId, $miembroId, $tokenHash, $vence, $limite, $porId]
        );
    }

    public static function rotarToken(int $asigId, string $tokenHash, string $vence): void
    {
        Database::ejecutar(
            'UPDATE tit_revision_asignaciones SET token_hash = ?, token_vence = ?, recordatorios = recordatorios + 1, ultimo_recordatorio = NOW() WHERE id = ?',
            [$tokenHash, $vence, $asigId]
        );
    }

    public static function guardarBorrador(int $id, ?string $propuesta, ?int $requiere, string $forma, string $fondo): void
    {
        Database::ejecutar(
            "UPDATE tit_revision_asignaciones SET propuesta = ?, requiere_coord = ?, forma = ?, fondo = ?, estado = IF(estado = 'pendiente', 'borrador', estado) WHERE id = ? AND estado IN ('pendiente','borrador')",
            [$propuesta, $requiere, $forma, $fondo, $id]
        );
    }

    public static function marcarFirmada(int $id, string $tipo, ?int $firmanteId, string $ruta, string $hash): void
    {
        Database::ejecutar(
            "UPDATE tit_revision_asignaciones SET estado = 'firmada', firmada_en = NOW(), firma_tipo = ?, firmante_id = ?, pdf_ruta = ?, pdf_hash = ? WHERE id = ?",
            [$tipo, $firmanteId, $ruta, $hash, $id]
        );
    }

    public static function cancelarAsignacion(int $id): void
    {
        Database::ejecutar("UPDATE tit_revision_asignaciones SET estado = 'cancelada' WHERE id = ? AND estado <> 'firmada'", [$id]);
    }

    /** Asignaciones de una persona (por correo) con datos de la ronda. */
    public static function delEvaluador(string $email): array
    {
        return Database::todas(
            "SELECT a.*, m.carrera, r.numero, r.estado AS ronda_estado, t.folio, t.titulo, t.modalidad, u.nombre AS alumno_nombre
             FROM tit_revision_asignaciones a
             JOIN tit_comision_miembros m ON m.id = a.miembro_id
             JOIN tit_revisiones r ON r.id = a.revision_id
             JOIN tit_tramites t ON t.id = r.tramite_id
             JOIN usuarios u ON u.id = t.alumno_id
             WHERE m.email = ? AND a.estado <> 'cancelada'
             ORDER BY a.estado = 'firmada', a.fecha_limite
             LIMIT 200",
            [strtolower(trim($email))]
        );
    }

    public static function correosPersonal(array $departamentos, array $gestores): array
    {
        $out = [];
        if ($departamentos) {
            foreach (Database::todas(
                "SELECT u.nombre, u.email FROM usuarios u JOIN departamentos d ON d.id = u.departamento_id
                 WHERE u.activo = 1 AND u.rol IN ('titular','operativo','coordinador') AND d.clave IN (" . implode(',', array_fill(0, count($departamentos), '?')) . ')',
                array_values($departamentos)
            ) as $u) {
                $out[strtolower($u['email'])] = $u['nombre'];
            }
        }
        foreach ($gestores as $g) {
            $out[strtolower((string) $g)] ??= 'Titulación';
        }
        return $out;
    }
}
