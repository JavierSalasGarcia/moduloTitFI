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
     * $propietarioId, quien creó el recurso (si lo hay). Con $auditar = false no registra la denegación (condiciones de menú).
     */
    public static function puede(string $funcion, array $roles = [], ?int $recursoId = null, ?int $propietarioId = null, string $permiso = 'usar', bool $auditar = true): bool
    {
        $id = Auth::id();
        if ($id <= 0) {
            return false;
        }
        $ok = ($propietarioId !== null && $propietarioId === $id)
            || ($roles && Auth::tieneRol(...$roles))
            || self::tiene($id, $funcion, $recursoId, $permiso);
        if (!$ok && $auditar) {
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

    /**
     * Funciones que el administrador puede conceder a una persona (las de módulo completo se generan en la pantalla).
     * titular = lo puede conceder el titular de su dependencia. recurso = la concesión se da sobre un programa, órgano o persona concretos.
     */
    public const CATALOGO = [
        'titulacion.personal' => ['nombre' => 'Personal de Titulación (gestión de trámites)', 'titular' => true],
        'asuntos.gestor' => ['nombre' => 'Gestión de Asuntos (oficinas)', 'titular' => true],
        'reuniones.precargar' => ['nombre' => 'Reuniones: precargar y administrar', 'titular' => true],
        'documentos.subir' => ['nombre' => 'Archivo documental: subir documentos', 'titular' => true],
        'ingesta.subir' => ['nombre' => 'Documentos indizados: ingresar PDFs, carpetas y enlaces de Teams', 'titular' => true],
        'ingesta.carpeta' => ['nombre' => 'Documentos indizados: consultar carpetas de otras dependencias', 'titular' => true],
        'grafo.personas' => ['nombre' => 'Grafo de relaciones: ver el de todas las personas', 'titular' => false],
        'grafo.programa' => ['nombre' => 'Grafo de relaciones: profesores de un programa', 'titular' => false, 'recurso' => 'programa'],
        'grafo.organo' => ['nombre' => 'Grafo de relaciones: miembros de un cuerpo u órgano académico', 'titular' => false, 'recurso' => 'organo'],
        'grafo.persona' => ['nombre' => 'Grafo de relaciones: una persona en particular', 'titular' => false, 'recurso' => 'persona'],
    ];

    /** Claves de función con concesión vigente para la persona (caché por petición). @return string[] */
    public static function funcionesVigentes(int $usuarioId): array
    {
        static $cache = [];
        if ($usuarioId <= 0) {
            return [];
        }
        return $cache[$usuarioId] ??= array_values(array_unique(array_column(self::vigentes($usuarioId), 'funcion')));
    }

    /** ¿Entra al módulo por concesión (función «modulo.<clave>»)? Complementa los roles del manifiesto. */
    public static function entraAlModulo(string $clave): bool
    {
        return in_array('modulo.' . $clave, self::funcionesVigentes(Auth::id()), true);
    }

    /** Concesiones vigentes que vencen en $dias días o menos; con $departamentoId solo las de esa dependencia (titular). */
    public static function porVencer(int $dias = 15, ?array $departamentos = null): array
    {
        try {
            return Database::todas(
                'SELECT c.id, c.usuario_id, u.nombre, u.email, c.funcion, c.vigente_hasta FROM acceso_concesiones c JOIN usuarios u ON u.id = c.usuario_id
                 WHERE c.revocado_en IS NULL AND c.vigente_hasta >= CURDATE() AND c.vigente_hasta <= CURDATE() + INTERVAL ? DAY'
                . ($departamentos !== null ? ' AND c.departamento_id IN (' . self::marcas($departamentos) . ')' : '') . ' ORDER BY c.vigente_hasta',
                [$dias, ...($departamentos ?? [])]
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** Listado para la pantalla: vigentes y recientemente vencidas o revocadas. $departamentos limita a esas dependencias. */
    public static function listar(?array $departamentos = null): array
    {
        try {
            return Database::todas(
                'SELECT c.*, u.nombre, u.email, o.nombre AS otorgante, d.nombre AS departamento,
                        (SELECT pr.nombre FROM programas pr WHERE c.funcion = "grafo.programa" AND pr.id = c.recurso_id) AS recurso_programa,
                        (SELECT CONCAT(og.tipo, " · ", pg.nombre) FROM programa_organos og JOIN programas pg ON pg.id = og.programa_id WHERE c.funcion = "grafo.organo" AND og.id = c.recurso_id) AS recurso_organo,
                        (SELECT ur.nombre FROM usuarios ur WHERE c.funcion = "grafo.persona" AND ur.id = c.recurso_id) AS recurso_persona,
                        CASE WHEN c.revocado_en IS NOT NULL THEN "revocada" WHEN c.vigente_hasta < CURDATE() THEN "vencida" ELSE "vigente" END AS estado
                 FROM acceso_concesiones c JOIN usuarios u ON u.id = c.usuario_id JOIN usuarios o ON o.id = c.otorgado_por
                 LEFT JOIN departamentos d ON d.id = c.departamento_id
                 WHERE (c.vigente_hasta >= CURDATE() - INTERVAL 90 DAY)' . ($departamentos !== null ? ' AND c.departamento_id IN (' . self::marcas($departamentos) . ')' : '')
                . ' ORDER BY (c.revocado_en IS NOT NULL OR c.vigente_hasta < CURDATE()), c.vigente_hasta',
                $departamentos ?? []
            );
        } catch (\Throwable) {
            return [];
        }
    }

    /** Marcadores «?,?,?» para un IN; con lista vacía, «NULL» (no coincide con nada). */
    private static function marcas(array $lista): string
    {
        return $lista ? implode(',', array_fill(0, count($lista), '?')) : 'NULL';
    }

    /** Dependencias sobre las que la persona puede conceder: todas (null) si es administradora; las suyas si es titular. @return int[]|null */
    public static function dependenciasQueConcede(int $usuarioId): ?array
    {
        $u = Database::una('SELECT rol, departamento_id FROM usuarios WHERE id = ? AND activo = 1', [$usuarioId]);
        if (!$u) {
            return [];
        }
        if ($u['rol'] === Roles::ADMIN) {
            return null;
        }
        $ids = [];
        if ($u['rol'] === Roles::TITULAR && (int) $u['departamento_id'] > 0) {
            $ids[] = (int) $u['departamento_id'];
        }
        try {
            foreach (Database::todas('SELECT departamento_id FROM usuario_roles WHERE usuario_id = ? AND rol = ? AND departamento_id IS NOT NULL', [$usuarioId, Roles::TITULAR]) as $r) {
                $ids[] = (int) $r['departamento_id'];
            }
        } catch (\Throwable) {
        }
        return array_values(array_unique($ids));
    }

    /** Nombre legible de una función concedida. */
    public static function etiqueta(string $funcion): string
    {
        if (isset(self::CATALOGO[$funcion])) {
            return self::CATALOGO[$funcion]['nombre'];
        }
        return str_starts_with($funcion, 'modulo.') ? 'Módulo «' . substr($funcion, 7) . '» completo' : $funcion;
    }
}
