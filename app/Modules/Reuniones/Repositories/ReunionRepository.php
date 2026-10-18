<?php
declare(strict_types=1);
namespace App\Modules\Reuniones\Repositories;
use App\Core\Auth;
use App\Core\Database;
final class ReunionRepository
{
    public function reunionesDelUsuario(int $usuarioId): array
    {
        return Database::todas(
            'SELECT r.id, r.asunto, r.estado, r.rango_inicio, r.rango_fin,
                    r.fecha_confirmada, r.actualizado_en,
                    CASE WHEN r.organizador_id = ? THEN 1 ELSE 0 END AS es_organizador,
                    (SELECT COUNT(*) FROM reuniones_participantes p WHERE p.reunion_id = r.id) AS participantes,
                    (SELECT COUNT(*) FROM reuniones_mensajes m WHERE m.reunion_id = r.id) AS mensajes
               FROM reuniones r
              WHERE r.creador_id = ? OR r.organizador_id = ?
                 OR EXISTS (
                    SELECT 1 FROM reuniones_participantes p
                     WHERE p.reunion_id = r.id AND p.usuario_id = ?
                 )
              ORDER BY r.actualizado_en DESC, r.id DESC',
            [$usuarioId, $usuarioId, $usuarioId, $usuarioId]
        );
    }
    public function posgrados(): array
    {
        return Database::todas(
            'SELECT id, codigo, nombre FROM reuniones_posgrados WHERE activo = 1 ORDER BY codigo'
        );
    }
    public function usuarioActual(): array
    {
        return Auth::usuario();
    }
    public function crear(array $datos): int
    {
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $id = Database::ejecutar('INSERT INTO reuniones (asunto, descripcion, creador_id, organizador_id, posgrado_id, estado, rango_inicio, rango_fin, hora_default_inicio, hora_default_fin, retener_hasta) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 5 YEAR))', [$datos['asunto'], $datos['descripcion'], $datos['creador_id'], $datos['organizador_id'], $datos['posgrado_id'], $datos['estado'], $datos['rango_inicio'], $datos['rango_fin'], $datos['hora_inicio'], $datos['hora_fin']]);
            Database::ejecutar('INSERT INTO reuniones_participantes (reunion_id, usuario_id, correo, nombre, tipo, estado_invitacion) VALUES (?, ?, ?, ?, \'organizador\', \'pendiente\')', [$id, $datos['organizador_id'], $datos['organizador_correo'], $datos['organizador_nombre']]);
            foreach ($datos['invitados'] as $invitado) {
                Database::ejecutar('INSERT INTO reuniones_participantes (reunion_id, usuario_id, correo, nombre, tipo) VALUES (?, ?, ?, ?, ?)', [$id, $invitado['usuario_id'], $invitado['correo'], $invitado['nombre'], $invitado['usuario_id'] === null ? 'externo' : 'invitado']);
            }
            foreach ($datos['horarios'] as $horario) {
                Database::ejecutar('INSERT INTO reuniones_horarios (reunion_id, inicio, fin, creado_por) VALUES (?, ?, ?, ?)', [$id, $horario['inicio'], $horario['fin'], $datos['creador_id']]);
            }
            Database::ejecutar('INSERT INTO reuniones_auditoria (reunion_id, usuario_id, accion, datos_json) VALUES (?, ?, \'creacion\', ?)', [$id, $datos['creador_id'], json_encode(['invitados' => count($datos['invitados']), 'horarios' => count($datos['horarios'])])]);
            $pdo->commit();
            return (int) $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
    public function usuariosActivos(): array
    {
        return Database::todas('SELECT id, nombre, email, rol FROM usuarios WHERE activo = 1 ORDER BY nombre');
    }

    public function registrarArchivo(int $reunionId, int $usuarioId, array $archivo, string $ruta): void
    {
        Database::ejecutar(
            'INSERT INTO reuniones_archivos (reunion_id, subido_por, nombre_original, ruta_storage, extension, mime, tamano_bytes, tipo)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'inicial\')',
            [$reunionId, $usuarioId, $archivo['name'], $ruta, strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)), $archivo['type'], $archivo['size']]
        );
    }

    public function detalle(int $reunionId, int $usuarioId): ?array
    {
        $reunion = Database::una(
            'SELECT r.*, p.nombre AS posgrado, rp.id AS participante_id,
                    (SELECT COUNT(*) FROM reuniones_participantes WHERE reunion_id = r.id) AS participantes
               FROM reuniones r
               LEFT JOIN reuniones_posgrados p ON p.id = r.posgrado_id
               JOIN reuniones_participantes rp ON rp.reunion_id = r.id AND rp.usuario_id = ?
              WHERE r.id = ?', [$usuarioId, $reunionId]
        );
        if (!$reunion) return null;
        $reunion['horarios'] = Database::todas(
            'SELECT h.id, h.inicio, h.fin,
                    SUM(d.estado = \'disponible\') AS disponibles,
                    SUM(d.estado = \'no_disponible\') AS no_disponibles,
                    MAX(CASE WHEN d.participante_id = ? THEN d.estado ELSE NULL END) AS mi_estado
               FROM reuniones_horarios h
               LEFT JOIN reuniones_disponibilidades d ON d.horario_id = h.id
              WHERE h.reunion_id = ? AND h.activa = 1
              GROUP BY h.id ORDER BY h.inicio', [(int) $reunion['participante_id'], $reunionId]
        );
        $reunion['mensajes'] = $this->mensajes($reunionId);
        $reunion['archivos'] = $this->archivos($reunionId);
        return $reunion;
    }

    public function guardarDisponibilidad(int $horarioId, int $participanteId, string $estado, int $reunionId = 0): void
    {
        Database::ejecutar(
            'INSERT INTO reuniones_disponibilidades (horario_id, participante_id, estado)
             SELECT ?, ?, ? FROM reuniones_horarios h JOIN reuniones_participantes p ON p.reunion_id = h.reunion_id
              WHERE h.id = ? AND h.activa = 1 AND p.id = ? AND (? = 0 OR h.reunion_id = p.reunion_id)
             ON DUPLICATE KEY UPDATE estado = VALUES(estado), version = version + 1',
            [$horarioId, $participanteId, $estado, $horarioId, $participanteId, $reunionId]
        );
    }

    public function datosNotificacionDisponibilidad(int $reunionId, int $participanteId): ?array
    {
        return Database::una(
            'SELECT r.id, r.asunto, u.email AS organizador_correo, p.nombre, p.correo
               FROM reuniones r JOIN usuarios u ON u.id = r.organizador_id
               JOIN reuniones_participantes p ON p.id = ? AND p.reunion_id = r.id
              WHERE r.id = ?', [$participanteId, $reunionId]
        );
    }

    public function confirmarHorario(int $reunionId, int $usuarioId, array|int $horarioIds): bool
    {
        $ids = is_array($horarioIds) ? $horarioIds : [$horarioIds];
        if (empty($ids)) return false;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$reunionId, $usuarioId]);
        $datos = Database::una(
            "SELECT MIN(h.inicio) AS inicio, MAX(h.fin) AS fin
               FROM reuniones_horarios h
               JOIN reuniones r ON r.id = h.reunion_id
              WHERE h.id IN ($placeholders) AND h.reunion_id = ? AND r.organizador_id = ? AND h.activa = 1",
            $params
        );
        if (!$datos || !$datos['inicio'] || !$datos['fin']) return false;
        if (date('Y-m-d', strtotime($datos['inicio'])) !== date('Y-m-d', strtotime($datos['fin']))) return false;
        Database::ejecutar(
            'UPDATE reuniones SET estado = \'fecha_confirmada\', fecha_confirmada = DATE(?),
             hora_confirmada_inicio = TIME(?), hora_confirmada_fin = TIME(?) WHERE id = ?',
            [$datos['inicio'], $datos['inicio'], $datos['fin'], $reunionId]
        );
        Database::ejecutar(
            'INSERT INTO reuniones_auditoria (reunion_id, usuario_id, accion, datos_json) VALUES (?, ?, \'confirmacion_horario\', ?)',
            [$reunionId, $usuarioId, json_encode(['horarios' => $ids, 'inicio' => $datos['inicio'], 'fin' => $datos['fin']])]
        );
        return true;
    }

    public function participantesParaConvocatoria(int $reunionId, int $usuarioId): array
    {
        return Database::todas(
            'SELECT p.id, p.correo, p.nombre, p.tipo FROM reuniones_participantes p
              JOIN reuniones r ON r.id = p.reunion_id
             WHERE p.reunion_id = ? AND r.organizador_id = ? AND p.tipo <> \'organizador\'',
            [$reunionId, $usuarioId]
        );
    }

    public function correosParticipantes(int $reunionId): array { return array_values(array_filter(array_map(static fn (array $p): string => (string) $p['correo'], Database::todas('SELECT correo FROM reuniones_participantes WHERE reunion_id = ? AND estado_invitacion <> \'revocada\'', [$reunionId])))); }
    public function correosParticipantesExcepto(int $reunionId, int $usuarioId): array { return array_values(array_filter(array_map(static fn (array $p): string => (string) $p['correo'], Database::todas('SELECT p.correo FROM reuniones_participantes p WHERE p.reunion_id = ? AND p.usuario_id <> ? AND p.estado_invitacion <> \'revocada\'', [$reunionId, $usuarioId])))); }

    public function registrarNotificacion(int $reunionId, string $destinatario, string $estado, ?string $error = null, string $evento = 'invitacion', string $tipo = 'normal'): void
    {
        Database::ejecutar(
            'INSERT INTO reuniones_notificaciones (reunion_id, destinatario, tipo_mensaje, evento, estado_entrega, intentos, ultimo_error, enviado_en)
             VALUES (?, ?, ?, ?, ?, 1, ?, CASE WHEN ? = \'enviado\' THEN NOW() ELSE NULL END)',
            [$reunionId, $destinatario, in_array($tipo, ['normal', 'urgente'], true) ? $tipo : 'normal', in_array($evento, ['invitacion', 'disponibilidad', 'confirmacion', 'chat', 'finalizacion'], true) ? $evento : 'invitacion', $estado, $error, $estado]
        );
    }

    public function marcarInvitacionEnviada(int $participanteId, bool $enviada): void
    {
        Database::ejecutar('UPDATE reuniones_participantes SET estado_invitacion = ? WHERE id = ?', [$enviada ? 'enviada' : 'pendiente', $participanteId]);
    }

    public function marcarConvocada(int $reunionId, int $usuarioId): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE reuniones SET estado = \'convocada\' WHERE id = ? AND organizador_id = ? AND estado IN (\'borrador\', \'precargada\')');
        $stmt->execute([$reunionId, $usuarioId]);
        return $stmt->rowCount() > 0;
    }

    public function invitacionExterna(string $token): ?array
    {
        return Database::una(
            'SELECT r.id, r.asunto, r.descripcion, r.estado, r.rango_inicio, r.rango_fin,
                    r.organizador_id, u.email AS organizador_correo,
                    p.id AS participante_id, p.correo, p.nombre,
                    (SELECT COUNT(*) FROM reuniones_participantes WHERE reunion_id = r.id) AS participantes
               FROM reuniones_participantes p JOIN reuniones r ON r.id = p.reunion_id
               JOIN usuarios u ON u.id = r.organizador_id
              WHERE p.token_hash = SHA2(?, 256)
                AND (p.token_expira_en IS NULL OR p.token_expira_en > NOW())
                AND r.estado NOT IN (\'cancelada\')', [$token]
        );
    }

    public function generarTokenExterno(int $participanteId): string
    {
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        Database::ejecutar(
            'UPDATE reuniones_participantes SET token_hash = SHA2(?, 256), token_expira_en = DATE_ADD(NOW(), INTERVAL 30 DAY), estado_invitacion = \'enviada\' WHERE id = ?',
            [$token, $participanteId]
        );
        return $token;
    }
    public function revocarTokenExterno(int $reunionId, int $participanteId, int $usuarioId): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE reuniones_participantes p JOIN reuniones r ON r.id = p.reunion_id SET p.token_hash = NULL, p.token_expira_en = NOW(), p.estado_invitacion = \'revocada\' WHERE p.id = ? AND p.reunion_id = ? AND r.organizador_id = ?');
        $stmt->execute([$participanteId, $reunionId, $usuarioId]);
        return $stmt->rowCount() > 0;
    }

    public function mensajes(int $reunionId): array
    {
        return Database::todas('SELECT m.*, p.nombre, p.correo FROM reuniones_mensajes m JOIN reuniones_participantes p ON p.id = m.participante_id WHERE m.reunion_id = ? ORDER BY m.creado_en', [$reunionId]);
    }

    public function guardarMensaje(int $reunionId, int $participanteId, string $contenido, string $tipo): int
    {
        return (int) Database::ejecutar('INSERT INTO reuniones_mensajes (reunion_id, participante_id, contenido, tipo) VALUES (?, ?, ?, ?)', [$reunionId, $participanteId, $contenido, $tipo]);
    }

    public function archivos(int $reunionId): array
    {
        return Database::todas('SELECT a.*, u.nombre FROM reuniones_archivos a LEFT JOIN usuarios u ON u.id = a.subido_por WHERE a.reunion_id = ? ORDER BY a.creado_en DESC', [$reunionId]);
    }

    public function archivoDescargable(int $reunionId, int $archivoId, int $usuarioId): ?string { return Database::valor('SELECT a.ruta_storage FROM reuniones_archivos a JOIN reuniones_participantes p ON p.reunion_id = a.reunion_id AND p.usuario_id = ? WHERE a.id = ? AND a.reunion_id = ?', [$usuarioId, $archivoId, $reunionId]) ?: null; }

    public function registrarArchivoComplementario(int $reunionId, int $usuarioId, array $archivo, string $ruta): void
    {
        Database::ejecutar('INSERT INTO reuniones_archivos (reunion_id, subido_por, nombre_original, ruta_storage, extension, mime, tamano_bytes, tipo) VALUES (?, ?, ?, ?, ?, ?, ?, \'complementario\')', [$reunionId, $usuarioId, $archivo['name'], $ruta, strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)), $archivo['type'], $archivo['size']]);
    }

    public function finalizar(int $reunionId, int $usuarioId, string $rutaZip): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE reuniones SET estado = \'finalizada\', finalizada_en = NOW(), zip_archivo = ? WHERE id = ? AND organizador_id = ? AND estado NOT IN (\'finalizada\', \'cancelada\')');
        $stmt->execute([$rutaZip, $reunionId, $usuarioId]);
        $ok = $stmt->rowCount() > 0;
        if ($ok) Database::ejecutar('INSERT INTO reuniones_auditoria (reunion_id, usuario_id, accion) VALUES (?, ?, \'finalizacion\')', [$reunionId, $usuarioId]);
        return $ok;
    }

    public function puedeDescargarRespaldo(int $reunionId, int $usuarioId): ?string
    {
        $ruta = Database::valor('SELECT b.ruta_zip FROM reuniones_respaldo b JOIN reuniones_participantes p ON p.reunion_id = b.reunion_id AND p.usuario_id = ? JOIN reuniones r ON r.id = b.reunion_id WHERE b.reunion_id = ? AND r.estado = \'finalizada\'', [$usuarioId, $reunionId]);
        if (!$ruta) return null;
        Database::ejecutar('UPDATE reuniones_respaldo SET descargas = descargas + 1, ultimo_acceso_en = NOW() WHERE reunion_id = ?', [$reunionId]);
        $this->auditar($reunionId, $usuarioId, 'descarga_zip');
        return (string) $ruta;
    }
    public function hashRespaldo(int $reunionId): ?string { return Database::valor('SELECT hash_sha256 FROM reuniones_respaldo WHERE reunion_id = ?', [$reunionId]) ?: null; }

    public function registrarRespaldo(int $reunionId, int $usuarioId, string $rutaZip, string $hash): void
    {
        Database::ejecutar('INSERT INTO reuniones_respaldo (reunion_id, ruta_zip, hash_sha256, generado_en, generado_por) VALUES (?, ?, ?, NOW(), ?) ON DUPLICATE KEY UPDATE ruta_zip = VALUES(ruta_zip), hash_sha256 = VALUES(hash_sha256), generado_en = NOW(), generado_por = VALUES(generado_por)', [$reunionId, $rutaZip, $hash, $usuarioId]);
        $this->auditar($reunionId, $usuarioId, 'generacion_zip', ['ruta' => $rutaZip, 'hash_sha256' => $hash]);
    }
    public function finalizarConRespaldo(int $reunionId, int $usuarioId, string $rutaZip, string $hash): bool { $pdo = Database::pdo(); $pdo->beginTransaction(); try { if (!$this->finalizar($reunionId, $usuarioId, $rutaZip)) throw new \RuntimeException('El expediente ya fue cerrado.'); $this->registrarRespaldo($reunionId, $usuarioId, $rutaZip, $hash); $pdo->commit(); return true; } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; } }

    public function auditar(int $reunionId, ?int $usuarioId, string $accion, array $datos = []): void
    {
        Database::ejecutar('INSERT INTO reuniones_auditoria (reunion_id, usuario_id, accion, datos_json) VALUES (?, ?, ?, ?)', [$reunionId, $usuarioId, $accion, json_encode($datos, JSON_UNESCAPED_UNICODE)]);
    }

    public function horariosExternos(string $token): ?array
    {
        $base = $this->invitacionExterna($token);
        if (!$base) return null;
        $base['horarios'] = Database::todas(
            'SELECT h.id, h.inicio, h.fin,
                    (SELECT COUNT(*) FROM reuniones_disponibilidades WHERE horario_id = h.id AND estado = \'disponible\') AS disponibles,
                    d.estado AS mi_estado
               FROM reuniones_horarios h
               LEFT JOIN reuniones_disponibilidades d ON d.horario_id = h.id AND d.participante_id = ?
              WHERE h.reunion_id = ? AND h.activa = 1 ORDER BY h.inicio',
            [(int) $base['participante_id'], (int) $base['id']]
        );
        return $base;
    }
    public function guardarDisponibilidadExterna(string $token, int $horarioId, string $estado): bool
    {
        $base = $this->invitacionExterna($token);
        if (!$base || in_array($base['estado'], ['finalizada', 'cancelada'], true) || !in_array($estado, ['disponible', 'no_disponible'], true)) return false;
        $this->guardarDisponibilidad($horarioId, (int) $base['participante_id'], $estado, (int) $base['id']);
        Database::ejecutar('UPDATE reuniones_participantes SET respondio_en = NOW() WHERE id = ?', [(int) $base['participante_id']]);
        return true;
    }
    public function mensajesExternos(string $token): array { $base = $this->invitacionExterna($token); return $base ? $this->mensajes((int) $base['id']) : []; }
    public function guardarMensajeExterno(string $token, string $contenido, string $tipo = 'normal'): bool { $base = $this->invitacionExterna($token); if (!$base || trim($contenido) === '' || !in_array($tipo, ['normal', 'urgente'], true) || in_array($base['estado'], ['finalizada', 'cancelada'], true)) return false; $this->guardarMensaje((int) $base['id'], (int) $base['participante_id'], trim($contenido), $tipo); return true; }
}
