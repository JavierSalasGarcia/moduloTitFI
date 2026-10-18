<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Services;

use App\Core\Database;

final class VisorReunionesService
{
    public function pendientes(int $usuarioId): array
    {
        return Database::todas("SELECT r.id, r.asunto, r.estado, r.rango_inicio, r.rango_fin,
                    u.nombre AS organizador
                FROM reuniones r JOIN reuniones_participantes p ON p.reunion_id = r.id
                LEFT JOIN usuarios u ON u.id = r.organizador_id
                WHERE p.usuario_id = ? AND p.estado_invitacion <> 'revocada'
                  AND p.tipo <> 'organizador' AND p.respondio_en IS NULL
                  AND r.estado IN ('convocada','en_respuesta')
                ORDER BY r.rango_inicio, r.id", [$usuarioId]);
    }
    public function listar(int $usuarioId, string $busqueda = '', int $categoriaId = 0): array
    {
        $like = '%' . $busqueda . '%';
        $filtroBusqueda = '';
        $filtroCategoria = '';
        $params = [$usuarioId, $usuarioId, $usuarioId, $usuarioId];

        if ($busqueda !== '') {
            $filtroBusqueda = ' AND (r.asunto LIKE ? OR r.descripcion LIKE ? OR EXISTS
                (SELECT 1 FROM reuniones_mensajes m WHERE m.reunion_id = r.id AND m.contenido LIKE ?))';
            array_push($params, $like, $like, $like);
        }
        if ($categoriaId > 0) {
            $filtroCategoria = ' AND EXISTS (SELECT 1 FROM reuniones_categoria_relacion cr
                WHERE cr.reunion_id = r.id AND cr.categoria_id = ? AND EXISTS
                (SELECT 1 FROM reuniones_categorias c WHERE c.id = cr.categoria_id AND c.usuario_id = ?))';
            array_push($params, $categoriaId, $usuarioId);
        }

        $sql = 'SELECT r.id, r.asunto, r.estado, r.rango_inicio, r.rango_fin, r.fecha_confirmada, r.hora_confirmada_inicio, r.hora_confirmada_fin, r.actualizado_en,
                    CASE WHEN r.organizador_id = ? THEN 1 ELSE 0 END AS es_organizador,
                    (SELECT COUNT(*) FROM reuniones_participantes p WHERE p.reunion_id = r.id) AS participantes,
                    (SELECT COUNT(*) FROM reuniones_mensajes m WHERE m.reunion_id = r.id) AS mensajes,
                    (SELECT COUNT(*) FROM reuniones_participantes p WHERE p.reunion_id = r.id AND (p.respondio_en IS NOT NULL OR p.tipo = \'organizador\')) AS contestaron,
                    (SELECT COUNT(*) FROM reuniones_participantes p WHERE p.reunion_id = r.id AND p.respondio_en IS NULL AND p.tipo <> \'organizador\') AS pendientes,
                    COALESCE((
                        SELECT MAX(coincidencias)
                          FROM (
                            SELECT h.reunion_id, SUM(d.estado = \'disponible\') AS coincidencias
                              FROM reuniones_horarios h
                              JOIN reuniones_disponibilidades d ON d.horario_id = h.id
                             WHERE h.activa = 1
                             GROUP BY h.id
                          ) sub
                         WHERE sub.reunion_id = r.id
                    ), 0) AS max_coincidencias
               FROM reuniones r
              WHERE (r.creador_id = ? OR r.organizador_id = ? OR EXISTS
                    (SELECT 1 FROM reuniones_participantes p WHERE p.reunion_id = r.id AND p.usuario_id = ?))
                ' . $filtroBusqueda . $filtroCategoria . '
              ORDER BY r.actualizado_en DESC, r.id DESC';

        return Database::todas($sql, $params);
    }

    public function categorias(int $usuarioId): array
    {
        return Database::todas('SELECT id, nombre, color FROM reuniones_categorias WHERE usuario_id = ? ORDER BY nombre', [$usuarioId]);
    }

    public function crearCategoria(int $usuarioId, string $nombre): bool
    {
        if ($nombre === '' || mb_strlen($nombre) > 80) return false;
        return Database::ejecutar('INSERT INTO reuniones_categorias (usuario_id, nombre) VALUES (?, ?)', [$usuarioId, $nombre]) > 0;
    }

    public function asignar(int $usuarioId, int $reunionId, int $categoriaId): bool
    {
        $permitida = Database::una('SELECT c.id FROM reuniones_categorias c WHERE c.id = ? AND c.usuario_id = ?', [$categoriaId, $usuarioId]);
        $visible = Database::una('SELECT r.id FROM reuniones r WHERE r.id = ? AND (r.creador_id = ? OR r.organizador_id = ? OR EXISTS (SELECT 1 FROM reuniones_participantes p WHERE p.reunion_id = r.id AND p.usuario_id = ?))', [$reunionId, $usuarioId, $usuarioId, $usuarioId]);
        if (!$permitida || !$visible) return false;
        $stmt = Database::pdo()->prepare('INSERT IGNORE INTO reuniones_categoria_relacion (reunion_id, categoria_id) VALUES (?, ?)');
        $stmt->execute([$reunionId, $categoriaId]);
        return $stmt->rowCount() > 0;
    }
}
