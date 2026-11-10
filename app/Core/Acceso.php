<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Punto único de decisión para el acceso a funciones y recursos (docs/CONCESIONES_DE_ACCESO.md).
 *
 * Orden de decisión, siempre negando por defecto:
 *   1. el propietario del recurso;
 *   2. un rol que el módulo declara para esa acción;
 *   3. una concesión por persona vigente (acceso_concesiones), que caduca sola en vigente_hasta.
 * Las denegaciones y las concesiones/revocaciones se auditan. Sin la migración 043 todo se niega (falla cerrado).
 */
final class Acceso
{
    /** ¿La persona tiene una concesión vigente para la función (y el recurso, si se indica)? Una concesión sin recurso cubre toda la función. */
    public static function tiene(int $usuarioId, string $funcion, ?int $recursoId = null, string $permiso = 'usar'): bool
    {
        if ($usuarioId <= 0 || $funcion === '') {
            return false;
        }
        try {
            return (bool) Database::valor(
                'SELECT 1 FROM acceso_concesiones WHERE usuario_id = ? AND funcion = ? AND permiso = ?
                   AND (recurso_id IS NULL OR recurso_id = ?)
                   AND revocado_en IS NULL AND vigente_desde <= CURDATE() AND vigente_hasta >= CURDATE() LIMIT 1',
                [$usuarioId, $funcion, $permiso, $recursoId ?? 0]
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Decisión para la persona en sesión. $roles son los que el módulo admite para la acción;
     * $propietarioId, quien creó el recurso (si lo hay).
     */
    public static function puede(string $funcion, array $roles = [], ?int $recursoId = null, ?int $propietarioId = null, string $permiso = 'usar'): bool
    {
        $id = Auth::id();
        if ($id <= 0) {
            return false;
        }
        $ok = ($propietarioId !== null && $propietarioId === $id)
            || ($roles && Auth::tieneRol(...$roles))
            || self::tiene($id, $funcion, $recursoId, $permiso);
        if (!$ok) {
            Audit::record('acceso_denegado', 'denegado', $funcion . ($recursoId !== null ? ' #' . $recursoId : ''));
        }
        return $ok;
    }

    /** Detiene la petición con 404 (sin revelar que el recurso existe) si la persona no puede. */
    public static function exigir(string $funcion, array $roles = [], ?int $recursoId = null, ?int $propietarioId = null, string $permiso = 'usar'): void
    {
        if (!self::puede($funcion, $roles, $recursoId, $propietarioId, $permiso)) {
            http_response_code(404);
            exit('No disponible.');
        }
    }

    /** ¿Quién puede conceder? Admin en toda la plataforma; titular solo sobre su propia dependencia. */
    public static function puedeConceder(int $otorgadoPor, ?int $departamentoId): bool
    {
        try {
            $u = Database::una('SELECT rol, departamento_id FROM usuarios WHERE id = ? AND activo = 1', [$otorgadoPor]);
            if (!$u) {
                return false;
            }
            if ($u['rol'] === Roles::ADMIN) {
                return true;
            }
            if ($departamentoId === null) {
                return false;   // las concesiones de toda la plataforma son del administrador
            }
            if ($u['rol'] === Roles::TITULAR && (int) $u['departamento_id'] === $departamentoId) {
                return true;
            }
            return (bool) Database::valor(
                'SELECT 1 FROM usuario_roles WHERE usuario_id = ? AND rol = ? AND departamento_id = ? LIMIT 1',
                [$otorgadoPor, Roles::TITULAR, $departamentoId]
            );
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Concede acceso a una persona. La vigencia es obligatoria y debe ser futura; nadie se concede a sí mismo.
     * Devuelve el id de la concesión.
     */
    public static function conceder(int $otorgadoPor, int $usuarioId, string $funcion, string $vigenteHasta, ?int $recursoId = null, ?int $departamentoId = null, string $permiso = 'usar', string $motivo = ''): int
    {
        $hoy = date('Y-m-d');
        $fecha = \DateTimeImmutable::createFromFormat('!Y-m-d', $vigenteHasta);
        if (!$fecha || $fecha->format('Y-m-d') !== $vigenteHasta || $vigenteHasta < $hoy) {
            throw new \InvalidArgumentException('La vigencia debe ser una fecha de hoy en adelante (AAAA-MM-DD).');
        }
        if ($funcion === '' || !preg_match('/^[a-z0-9_.-]{1,80}$/', $funcion) || !preg_match('/^[a-z_]{1,30}$/', $permiso)) {
            throw new \InvalidArgumentException('Función o permiso no válidos.');
        }
        if ($usuarioId === $otorgadoPor) {
            Audit::record('concesion_acceso', 'denegado', 'Autoconcesión de ' . $funcion);
            throw new \DomainException('Nadie puede concederse acceso a sí mismo.');
        }
        if (!self::puedeConceder($otorgadoPor, $departamentoId)) {
            Audit::record('concesion_acceso', 'denegado', $funcion . ' para usuario #' . $usuarioId);
            throw new \DomainException('No tienes facultad para conceder este acceso.');
        }
        if (!Database::valor('SELECT 1 FROM usuarios WHERE id = ? AND activo = 1', [$usuarioId])) {
            throw new \InvalidArgumentException('La persona no tiene cuenta activa.');
        }
        Database::ejecutar(
            'INSERT INTO acceso_concesiones (usuario_id, funcion, recurso_id, departamento_id, permiso, motivo, otorgado_por, vigente_desde, vigente_hasta)
             VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)',
            [$usuarioId, $funcion, $recursoId, $departamentoId, $permiso, mb_substr($motivo, 0, 255), $otorgadoPor, $vigenteHasta]
        );
        // LAST_INSERT_ID() y no el valor de PDO: la auditoría de la sentencia consulta la sesión y PDO devolvería 0.
        $id = (int) Database::valor('SELECT LAST_INSERT_ID()');
        Audit::record('concesion_acceso', 'correcto', "#$id $funcion a usuario #$usuarioId hasta $vigenteHasta");
        return $id;
    }

    /** Revoca antes del vencimiento. Solo quien pueda conceder sobre esa dependencia. */
    public static function revocar(int $concesionId, int $revocadoPor): bool
    {
        $c = Database::una('SELECT departamento_id FROM acceso_concesiones WHERE id = ? AND revocado_en IS NULL', [$concesionId]);
        if (!$c || !self::puedeConceder($revocadoPor, $c['departamento_id'] !== null ? (int) $c['departamento_id'] : null)) {
            return false;
        }
        Database::ejecutar('UPDATE acceso_concesiones SET revocado_en = NOW(), revocado_por = ? WHERE id = ?', [$revocadoPor, $concesionId]);
        Audit::record('concesion_revocada', 'correcto', "#$concesionId");
        return true;
    }

    /** Concesiones vigentes de una persona (para mostrarlas y para el aviso de próximo vencimiento). */
    public static function vigentes(int $usuarioId): array
    {
        try {
            return Database::todas(
                'SELECT id, funcion, recurso_id, permiso, motivo, vigente_hasta FROM acceso_concesiones
                 WHERE usuario_id = ? AND revocado_en IS NULL AND vigente_desde <= CURDATE() AND vigente_hasta >= CURDATE() ORDER BY vigente_hasta',
                [$usuarioId]
            );
        } catch (\Throwable) {
            return [];
        }
    }
}
