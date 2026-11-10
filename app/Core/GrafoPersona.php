<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Grafo de relaciones de una persona: todo su perfil funcional y documental en la plataforma (docs/GRAFO_DE_PERSONA.md).
 *
 * El núcleo aporta la identidad (dependencia, roles, cargos y accesos especiales). Lo demás lo aporta cada módulo con la
 * clave `grafo` de su manifiesto: "App\Modules\X\Clase::metodo", con firma
 *     fn(int $usuarioId, string[] $correos, ?int $profesorId): array
 * que devuelve una lista de grupos:
 *     [['titulo' => 'Programas en los que imparte clases', 'items' => [['etiqueta' => '…', 'detalle' => '…', 'enlace' => 'ruta'|null], …], 'total' => 12], …]
 * Un módulo nuevo aparece en el grafo con solo declarar esa clave; no se modifica esta clase ni la pantalla.
 */
final class GrafoPersona
{
    public const MAX_ITEMS = 25;          // por grupo, en la lista
    private const MAX_NODOS = 7;          // por grupo, en el dibujo
    /** @var array<int,int[]|null> personas visibles por sesión (caché de la petición) */
    private static array $visibles = [];

    /** Olvida la caché de visibilidad (pruebas, o tras conceder o revocar en la misma petición). */
    public static function reiniciar(): void
    {
        self::$visibles = [];
    }

    /**
     * ¿La sesión puede ver el grafo de esa persona? El propio, siempre. El de otra persona solo por pertenencia o concesión
     * (ver idsVisibles). Alumnos y profesores sin cargo solo ven el suyo.
     */
    public static function puedeVer(int $objetivoId): bool
    {
        $yo = Auth::id();
        if ($yo <= 0 || $objetivoId <= 0) {
            return false;
        }
        $ids = self::idsVisibles();
        return $objetivoId === $yo || $ids === null || in_array($objetivoId, $ids, true);
    }

    /** ¿Tiene a alguien más que consultar? (muestra el buscador de personas) */
    public static function puedeBuscar(): bool
    {
        $ids = self::idsVisibles();
        return Auth::id() > 0 && ($ids === null || count($ids) > 1);
    }

    /**
     * Personas cuyo grafo puede ver la sesión: null = todas; si no, la lista de cuentas (incluida la propia).
     *
     *  · Administración, o concesión «grafo.personas»: todas.
     *  · Líder de un cuerpo u órgano académico (presidente o coordinador): sus miembros.
     *  · Coordinador o responsable de un programa (carrera o posgrado): los profesores y órganos de ese programa.
     *  · Titular o responsable de una dependencia: el personal adscrito a ella (no los alumnos).
     *  · Concesión temporal por persona (docs/CONCESIONES_DE_ACCESO.md): «grafo.programa», «grafo.organo» o «grafo.persona»
     *    sobre un programa, un órgano o una persona concretos, con vigencia (por ejemplo, para una comisión).
     * @return int[]|null
     */
    public static function idsVisibles(): ?array
    {
        $yo = Auth::id();
        if ($yo <= 0) {
            return [];
        }
        if (array_key_exists($yo, self::$visibles)) {
            return self::$visibles[$yo];
        }
        if (Auth::tieneRol(Roles::ADMIN) || Acceso::tiene($yo, 'grafo.personas')) {
            return self::$visibles[$yo] = null;
        }
        $ids = [$yo];
        $vig = static fn(string $a): string => "($a.activo = 1 AND ($a.fecha_fin IS NULL OR $a.fecha_fin >= CURDATE()))";
        $sumar = static function (string $sql, array $par) use (&$ids): void {
            try {
                foreach (Database::todas($sql, $par) as $f) {
                    $ids[] = (int) reset($f);
                }
            } catch (\Throwable) {       // tabla aún sin migrar: esa regla simplemente no aporta
            }
        };
        $marcas = static fn(array $l): string => implode(',', array_fill(0, count($l), '?'));
        $concedidos = static function (string $funcion) use ($yo): array {
            try {
                return array_map('intval', array_column(Database::todas(
                    "SELECT recurso_id FROM acceso_concesiones WHERE usuario_id = ? AND funcion = ? AND permiso = 'usar' AND recurso_id IS NOT NULL
                        AND revocado_en IS NULL AND vigente_desde <= CURDATE() AND vigente_hasta >= CURDATE()",
                    [$yo, $funcion]
                ), 'recurso_id'));
            } catch (\Throwable) {
                return [];
            }
        };

        // Órganos: los que lidera y los concedidos.
        $organos = $concedidos('grafo.organo');
        try {
            $organos = [...$organos, ...array_map('intval', array_column(Database::todas(
                "SELECT m.organo_id FROM programa_organo_miembros m WHERE m.usuario_id = ? AND m.cargo IN ('presidente','coordinador') AND " . $vig('m'), [$yo]), 'organo_id'))];
        } catch (\Throwable) {
        }
        // Programas: los que coordina (estructura académica o coordinaciones de posgrado) y los concedidos.
        $programas = $concedidos('grafo.programa');
        try {
            $programas = [...$programas, ...array_map('intval', array_column(Database::todas(
                "SELECT r.programa_id FROM programa_responsables r WHERE r.usuario_id = ? AND r.cargo IN ('coordinador','responsable') AND " . $vig('r'), [$yo]), 'programa_id'))];
        } catch (\Throwable) {
        }
        try {
            $programas = [...$programas, ...array_map('intval', array_column(Database::todas(
                'SELECT p.id FROM reuniones_coordinadores rc JOIN reuniones_posgrados rp ON rp.id = rc.posgrado_id AND rp.activo = 1 JOIN programas p ON p.siglas = rp.codigo
                  WHERE rc.usuario_id = ? AND rc.activo = 1', [$yo]), 'id'))];
        } catch (\Throwable) {
        }
        if ($programas = array_values(array_unique($programas))) {
            $sumar('SELECT x.usuario_id FROM programa_profesores x WHERE x.programa_id IN (' . $marcas($programas) . ') AND ' . $vig('x'), $programas);
            try {
                $organos = [...$organos, ...array_map('intval', array_column(Database::todas('SELECT id FROM programa_organos WHERE activo = 1 AND programa_id IN (' . $marcas($programas) . ')', $programas), 'id'))];
            } catch (\Throwable) {
            }
        }
        if ($organos = array_values(array_unique($organos))) {
            $sumar('SELECT x.usuario_id FROM programa_organo_miembros x WHERE x.organo_id IN (' . $marcas($organos) . ') AND ' . $vig('x'), $organos);
        }
        // Dependencias a su cargo: personal adscrito, nunca alumnos.
        $deptos = Acceso::dependenciasQueConcede($yo) ?? [];
        try {
            $deptos = [...$deptos, ...array_map('intval', array_column(Database::todas(
                "SELECT r.departamento_id FROM departamento_responsables r WHERE r.usuario_id = ? AND r.cargo IN ('titular','jefe','coordinador') AND " . $vig('r'), [$yo]), 'departamento_id'))];
        } catch (\Throwable) {
        }
        if ($deptos = array_values(array_unique(array_filter($deptos)))) {
            $sumar("SELECT u.id FROM usuarios u WHERE u.activo = 1 AND u.rol <> 'alumno' AND u.departamento_id IN (" . $marcas($deptos) . ')', $deptos);
        }
        // Personas concedidas una por una.
        $ids = [...$ids, ...$concedidos('grafo.persona')];
        return self::$visibles[$yo] = array_values(array_unique(array_filter($ids)));
    }

    /** Personas que la sesión puede consultar y que coinciden con el texto (nombre o correo). */
    public static function buscarPersonas(string $texto, int $limite = 20): array
    {
        $ids = self::idsVisibles();
        $texto = mb_substr(trim($texto), 0, 80);
        $donde = 'u.activo = 1';
        $par = [];
        if ($ids !== null) {
            if (count($ids) <= 1) {
                return [];
            }
            $donde .= ' AND u.id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $par = $ids;
        } elseif ($texto === '') {
            return [];          // quien ve a todas las personas debe escribir a quién busca
        }
        if ($texto !== '') {
            $como = '%' . addcslashes($texto, '%_\\') . '%';
            $donde .= ' AND (u.nombre LIKE ? OR u.email LIKE ?)';
            $par = [...$par, $como, $como];
        }
        return Database::todas("SELECT u.id, u.nombre, u.email, u.rol FROM usuarios u WHERE $donde ORDER BY u.nombre LIMIT " . max(1, min(50, $limite)), $par);
    }

    /** @return array{persona:array,grupos:array<int,array{titulo:string,modulo:string,items:array,total:int}>}|null */
    public static function de(int $usuarioId): ?array
    {
        $u = AccessControl::internal(fn() => Database::una(
            'SELECT u.id, u.nombre, u.email, u.rol, u.grado, d.nombre AS departamento, p.nombre AS programa
               FROM usuarios u LEFT JOIN departamentos d ON d.id = u.departamento_id LEFT JOIN programas p ON p.id = u.programa_id
              WHERE u.id = ? AND u.activo = 1',
            [$usuarioId]
        ));
        if (!$u) {
            return null;
        }
        $correos = Identidad::correosDeUsuario($usuarioId);
        $profesorId = null;
        try {
            $profesorId = ((int) Database::valor('SELECT id FROM profesores WHERE usuario_id = ?', [$usuarioId])) ?: null;
        } catch (\Throwable) {
        }
        $grupos = self::normalizar(self::identidad($usuarioId, $u), 'Cuenta');
        $nombres = [];
        foreach (ModuleManager::descubrir() as $m) {
            $nombres[(string) $m['clave']] = (string) ($m['nombre'] ?? InterfaceUi::NAMES[$m['clave']] ?? ucfirst((string) $m['clave']));
        }
        foreach (ModuleManager::proveedores('grafo') as $modulo => $funcion) {
            if (!is_callable($funcion)) {
                continue;
            }
            try {
                $grupos = [...$grupos, ...self::normalizar((array) $funcion($usuarioId, $correos, $profesorId), $nombres[$modulo] ?? $modulo)];
            } catch (\Throwable $e) {
                error_log('Grafo de ' . $modulo . ': ' . $e->getMessage());   // un módulo que falla no rompe el grafo
            }
        }
        return ['persona' => ['id' => (int) $u['id'], 'nombre' => Persona::tratamiento($u), 'detalle' => trim(($u['departamento'] ?? '') . ' ' . ($u['programa'] ? '· ' . $u['programa'] : ''))], 'grupos' => $grupos];
    }

    /** Lo que el núcleo sabe de la persona. */
    private static function identidad(int $id, array $u): array
    {
        $items = [['etiqueta' => 'Rol: ' . $u['rol'], 'detalle' => (string) ($u['departamento'] ?? ''), 'enlace' => null]];
        try {
            foreach (Database::todas('SELECT r.rol, r.cargo, d.nombre AS departamento, p.nombre AS programa FROM usuario_roles r LEFT JOIN departamentos d ON d.id = r.departamento_id LEFT JOIN programas p ON p.id = r.programa_id WHERE r.usuario_id = ?', [$id]) as $r) {
                $items[] = ['etiqueta' => 'Rol adicional: ' . $r['rol'], 'detalle' => trim(($r['cargo'] ? $r['cargo'] . ' · ' : '') . ($r['departamento'] ?? $r['programa'] ?? '')), 'enlace' => null];
            }
            foreach (Database::todas('SELECT r.cargo, d.nombre FROM departamento_responsables r JOIN departamentos d ON d.id = r.departamento_id WHERE r.usuario_id = ? AND r.activo = 1 AND (r.fecha_fin IS NULL OR r.fecha_fin >= CURDATE())', [$id]) as $r) {
                $items[] = ['etiqueta' => ucfirst((string) $r['cargo']) . ' de dependencia', 'detalle' => (string) $r['nombre'], 'enlace' => null];
            }
        } catch (\Throwable) {
        }
        $grupos = [['titulo' => 'Adscripción y cargos', 'items' => $items]];
        $accesos = [];
        foreach (Acceso::vigentes($id) as $c) {
            $accesos[] = ['etiqueta' => Acceso::etiqueta((string) $c['funcion']), 'detalle' => 'Vigente hasta ' . $c['vigente_hasta'], 'enlace' => null];
        }
        if ($accesos) {
            $grupos[] = ['titulo' => 'Accesos especiales', 'items' => $accesos];
        }
        return $grupos;
    }

    /** Valida y acota lo que devuelve un proveedor: solo texto, rutas internas relativas y un máximo de elementos. */
    private static function normalizar(array $grupos, string $modulo): array
    {
        $r = [];
        foreach ($grupos as $g) {
            if (!is_array($g) || !is_string($g['titulo'] ?? null) || !is_array($g['items'] ?? null) || !$g['items']) {
                continue;
            }
            $items = [];
            foreach (array_slice($g['items'], 0, self::MAX_ITEMS) as $i) {
                if (!is_array($i) || trim((string) ($i['etiqueta'] ?? '')) === '') {
                    continue;
                }
                $enlace = $i['enlace'] ?? null;
                $enlace = is_string($enlace) && $enlace !== '' && !preg_match('#^(/|[a-z][a-z0-9+.-]*:)|\.\.#i', $enlace) ? $enlace : null;
                $items[] = ['etiqueta' => mb_substr(trim((string) $i['etiqueta']), 0, 160), 'detalle' => mb_substr(trim((string) ($i['detalle'] ?? '')), 0, 200), 'enlace' => $enlace];
            }
            if ($items) {
                $r[] = ['titulo' => mb_substr($g['titulo'], 0, 80), 'modulo' => $modulo, 'items' => $items, 'total' => max(count($items), (int) ($g['total'] ?? count($g['items'])))];
            }
        }
        return $r;
    }

    /**
     * Dibujo SVG generado en el servidor: la persona al centro, un nodo por grupo y, alrededor de cada grupo, sus elementos.
     * Sin bibliotecas ni scripts. Los textos se escapan; la lista accesible equivalente va debajo, en la vista.
     */
    public static function svg(array $grafo): string
    {
        $grupos = $grafo['grupos'];
        $n = max(1, count($grupos));
        $cx = 480.0;
        $cy = 480.0;
        $r1 = 150.0;          // anillo de grupos
        $r2 = 300.0;          // anillo de elementos
        $e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $corto = static fn(string $s, int $m): string => mb_strlen($s) > $m ? mb_substr($s, 0, $m - 1) . '…' : $s;
        // Árbol radial: cada elemento ocupa una ranura igual sobre el anillo exterior (más una de separación por grupo), su
        // etiqueta va girada sobre su rayo y el nodo del grupo queda al centro de sus elementos. Así nada se encima.
        $listas = [];
        $ranuras = 0;
        foreach ($grupos as $g) {
            $visibles = array_slice($g['items'], 0, self::MAX_NODOS);
            $resto = $g['total'] - count($visibles);
            if ($resto > 0) {
                $visibles[] = ['etiqueta' => '+' . $resto . ' más', 'detalle' => '', 'enlace' => null];
            }
            $listas[] = $visibles;
            $ranuras += count($visibles) + 1;
        }
        $paso = 2 * M_PI / max(8, $ranuras);
        $cursor = -M_PI / 2;
        $lineas = '';
        $nodos = '';
        foreach ($grupos as $k => $g) {
            $m = count($listas[$k]);
            $a = $cursor + $paso * $m / 2;
            $gx = $cx + $r1 * cos($a);
            $gy = $cy + $r1 * sin($a);
            $lineas .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" class="grafo-arista-grupo"/>', $cx, $cy, $gx, $gy);
            foreach ($listas[$k] as $j => $item) {
                $b = $cursor + $paso * ($j + 0.5);
                $ix = $cx + $r2 * cos($b);
                $iy = $cy + $r2 * sin($b);
                $lineas .= sprintf('<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" class="grafo-arista"/>', $gx, $gy, $ix, $iy);
                $izquierda = cos($b) < 0;                       // en la mitad izquierda el texto se voltea para leerse de corrido
                $nodos .= sprintf('<g><title>%s</title><circle cx="%.1f" cy="%.1f" r="5" class="grafo-nodo"/><text x="%.1f" y="%.1f" text-anchor="%s" transform="rotate(%.1f %.1f %.1f)" class="grafo-texto">%s</text></g>',
                    $e($item['etiqueta'] . ($item['detalle'] !== '' ? ' — ' . $item['detalle'] : '')), $ix, $iy, $ix + ($izquierda ? -10 : 10), $iy + 3.6,
                    $izquierda ? 'end' : 'start', rad2deg($b) + ($izquierda ? 180 : 0), $ix, $iy, $e($corto($item['etiqueta'], 24)));
            }
            // El rótulo del grupo también va girado sobre su rayo, entre el nodo del grupo y el anillo de elementos.
            $izq = cos($a) < 0;
            $nodos .= sprintf('<g><title>%s</title><circle cx="%.1f" cy="%.1f" r="11" class="grafo-grupo"/><text x="%.1f" y="%.1f" text-anchor="%s" transform="rotate(%.1f %.1f %.1f)" class="grafo-texto-grupo">%s</text></g>',
                $e($g['titulo'] . ' (' . $g['modulo'] . ')'), $gx, $gy, $gx + ($izq ? -17 : 17), $gy + 4, $izq ? 'end' : 'start', rad2deg($a) + ($izq ? 180 : 0), $gx, $gy,
                $e($corto($g['titulo'], 19) . ' · ' . $g['total']));
            $cursor += $paso * ($m + 1);
        }
        $centro = sprintf('<g><circle cx="%.1f" cy="%.1f" r="30" class="grafo-centro"/><text x="%.1f" y="%.1f" text-anchor="middle" class="grafo-texto-centro">%s</text></g>',
            $cx, $cy, $cx, $cy + 50, $e($corto($grafo['persona']['nombre'], 36)));
        return '<svg class="grafo-persona" viewBox="0 0 960 960" role="img" aria-label="' . $e('Grafo de relaciones de ' . $grafo['persona']['nombre'])
            . '" xmlns="http://www.w3.org/2000/svg">' . $lineas . $nodos . $centro . '</svg>';
    }
}
