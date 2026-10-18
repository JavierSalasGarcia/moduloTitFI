<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Services;

use App\Core\Database;

final class CoordinadorService
{
    public function asignaciones(): array
    {
        return Database::todas('SELECT p.id AS posgrado_id, p.codigo, p.nombre AS posgrado,
                    c.id AS asignacion_id, u.id AS usuario_id, u.nombre, u.email
               FROM reuniones_posgrados p
               LEFT JOIN reuniones_coordinadores c ON c.posgrado_id = p.id AND c.activo = 1
               LEFT JOIN usuarios u ON u.id = c.usuario_id WHERE p.activo = 1 ORDER BY p.codigo, u.nombre');
    }

    public function usuarios(): array
    {
        return Database::todas("SELECT id, nombre, email FROM usuarios WHERE activo = 1 AND rol = 'coordinador' ORDER BY nombre");
    }

    public function asignar(int $posgradoId, int $usuarioId): bool
    {
        $u = Database::una("SELECT id FROM usuarios WHERE id = ? AND activo = 1 AND rol = 'coordinador'", [$usuarioId]);
        $p = Database::una('SELECT id FROM reuniones_posgrados WHERE id = ? AND activo = 1', [$posgradoId]);
        if (!$u || !$p) return false;
        Database::ejecutar('UPDATE reuniones_coordinadores SET activo = 0 WHERE posgrado_id = ? AND activo = 1', [$posgradoId]);
        Database::ejecutar('INSERT INTO reuniones_coordinadores (posgrado_id, usuario_id, activo) VALUES (?, ?, 1)', [$posgradoId, $usuarioId]);
        return true;
    }

    public function revocar(int $id): bool
    {
        $stmt = Database::pdo()->prepare('UPDATE reuniones_coordinadores SET activo = 0 WHERE id = ? AND activo = 1');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
