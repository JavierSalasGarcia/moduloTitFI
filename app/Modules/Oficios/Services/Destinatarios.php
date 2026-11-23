<?php
declare(strict_types=1);

namespace App\Modules\Oficios\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Profesores;
use RuntimeException;

/**
 * Destinatarios reutilizables de los oficios de la Subdirección (tabla ofs_destinatarios): personas y grupos de profesores.
 * Además decide quién ve cada oficio firmado en «Avisos y eventos → Oficios» (tabla ofs_oficio_destinos).
 */
final class Destinatarios
{
    private static function limpio(string $s, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $s) ?? ''), 0, $max);
    }

    /**
     * Catálogo (personas y grupos) y, si se escriben al menos 3 letras, profesores del directorio que aún no están en el catálogo
     * (llegan con id 0 y su profesor_id: al elegirlos se agregan al catálogo ya enlazados a su registro).
     * @return array<int,array<string,mixed>>
     */
    public static function buscar(string $q, int $limite = 12): array
    {
        $q = self::limpio($q, 80);
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        // (Sin «? = ''» en SQL: mezcla de intercalaciones en producción.)
        $r = Database::todas(
            'SELECT id, nombre, cargo, dependencia, tipo FROM ofs_destinatarios WHERE activo = 1' . ($q === '' ? '' : ' AND (nombre LIKE ? OR cargo LIKE ? OR dependencia LIKE ?)')
              . ' ORDER BY (tipo = \'grupo\') DESC, nombre LIMIT ' . (int) $limite,
            $q === '' ? [] : [$like, $like, $like]
        );
        if (mb_strlen($q) >= 3) {
            try {
                $filas = Database::todas(
                    "SELECT p.id, p.grado, p.nombre, p.apellido1, p.apellido2, p.genero FROM profesores p
                      WHERE CONCAT_WS(' ', p.nombre, p.apellido1, p.apellido2) LIKE ?
                        AND NOT EXISTS (SELECT 1 FROM ofs_destinatarios d WHERE d.profesor_id = p.id)
                      ORDER BY p.apellido1, p.nombre LIMIT 8",
                    [$like]
                );
            } catch (\Throwable) {
                $filas = [];
            }
            foreach ($filas as $f) {
                $r[] = ['id' => 0, 'profesor_id' => (int) $f['id'], 'tipo' => 'persona',
                    'nombre' => trim(($f['grado'] ? $f['grado'] . ' ' : '') . Profesores::nombreCompleto($f)),
                    'cargo' => ($f['genero'] === 'femenino' ? 'Profesora' : 'Profesor') . ' de la Facultad de Ingeniería', 'dependencia' => ''];
            }
        }
        return $r;
    }

    /** Crea (o reutiliza si ya existe) un destinatario. @return array{id:int,nombre:string,cargo:string,dependencia:string} */
    public static function crear(string $nombre, string $cargo, string $dependencia, ?int $profesorId = null, ?int $usuarioId = null): array
    {
        $nombre = self::limpio($nombre, 160);
        $cargo = self::limpio($cargo, 255);
        $dependencia = self::limpio($dependencia, 255);
        if (mb_strlen($nombre) < 3) {
            throw new RuntimeException('Escribe el nombre del destinatario (con su grado, por ejemplo «Dra. Ana López»).');
        }
        if ($profesorId !== null && !Database::valor('SELECT id FROM profesores WHERE id = ?', [$profesorId])) {
            $profesorId = null;
        }
        $id = Database::valor('SELECT id FROM ofs_destinatarios WHERE nombre = ? AND cargo = ? AND dependencia = ?', [$nombre, $cargo, $dependencia]);
        if ($id) {
            Database::ejecutar('UPDATE ofs_destinatarios SET activo = 1, profesor_id = COALESCE(profesor_id, ?), usuario_id = COALESCE(usuario_id, ?) WHERE id = ?', [$profesorId, $usuarioId, (int) $id]);
        } else {
            $id = Database::ejecutar('INSERT INTO ofs_destinatarios (nombre, cargo, dependencia, profesor_id, usuario_id, creado_por) VALUES (?, ?, ?, ?, ?, ?)', [$nombre, $cargo, $dependencia, $profesorId, $usuarioId, Auth::id()]);
        }
        return ['id' => (int) $id, 'nombre' => $nombre, 'cargo' => $cargo, 'dependencia' => $dependencia];
    }

    /**
     * Copia de los destinatarios elegidos, en ese orden: el oficio conserva lo que decía al momento de guardarse aunque luego se edite el catálogo.
     * @param array<int,int|string> $ids
     * @return array<int,array<string,mixed>>
     */
    public static function instantaneas(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $filas = Database::todas('SELECT id, nombre, cargo, dependencia, tipo, grupo, usuario_id, profesor_id FROM ofs_destinatarios WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        $porId = array_column($filas, null, 'id');
        $r = [];
        foreach ($ids as $id) {
            if (isset($porId[$id])) {
                $x = $porId[$id];
                $r[] = ['nombre' => $x['nombre'], 'cargo' => $x['cargo'], 'dependencia' => $x['dependencia'], 'tipo' => $x['tipo'], 'grupo' => $x['grupo'],
                    'usuario_id' => $x['usuario_id'] !== null ? (int) $x['usuario_id'] : null, 'profesor_id' => $x['profesor_id'] !== null ? (int) $x['profesor_id'] : null];
            }
        }
        return $r;
    }

    /** Ids de los destinatarios de una copia guardada (para volver a mostrar los chips al editar). */
    public static function idsDe(array $copia): array
    {
        $r = [];
        foreach ($copia as $d) {
            $id = Database::valor('SELECT id FROM ofs_destinatarios WHERE nombre = ? AND cargo = ? AND dependencia = ?', [$d['nombre'], $d['cargo'] ?? '', $d['dependencia'] ?? '']);
            $r[] = ['id' => $id ? (int) $id : 0, 'nombre' => $d['nombre'], 'cargo' => $d['cargo'] ?? '', 'dependencia' => $d['dependencia'] ?? ''];
        }
        return $r;
    }

    // ------------------------------------------------------------------ quién ve cada oficio firmado (Avisos y eventos → Oficios)

    /** Registra a quién le corresponde ver el oficio firmado: grupos, cuentas y registros del directorio (también por nombre, si coincide con una sola persona). */
    public static function indexar(int $oficioId, array $copia): void
    {
        Database::ejecutar('DELETE FROM ofs_oficio_destinos WHERE oficio_id = ?', [$oficioId]);
        $poner = static function (string $tipo, $ref) use ($oficioId): void {
            Database::ejecutar('INSERT IGNORE INTO ofs_oficio_destinos (oficio_id, tipo, ref) VALUES (?, ?, ?)', [$oficioId, $tipo, (string) $ref]);
        };
        foreach ($copia as $d) {
            if (($d['tipo'] ?? 'persona') === 'grupo' && !empty($d['grupo'])) {
                $poner('grupo', $d['grupo']);
                continue;
            }
            $usuario = (int) ($d['usuario_id'] ?? 0);
            $prof = (int) ($d['profesor_id'] ?? 0);
            if (!$usuario && !$prof) {
                $m = Profesores::porNombre((string) ($d['nombre'] ?? ''));
                if ($m && !empty($m['profesor_id'])) {
                    $prof = (int) $m['profesor_id'];
                } elseif ($m && !empty($m['email'])) {
                    $usuario = (int) Database::valor('SELECT id FROM usuarios WHERE email = ?', [$m['email']]);
                }
            }
            if ($usuario) {
                $poner('usuario', $usuario);
            }
            if ($prof) {
                $poner('profesor', $prof);
            }
        }
    }

    /** Grupos a los que pertenece la persona con sesión. */
    public static function gruposDe(): array
    {
        $uid = (int) Auth::id();
        $g = [];
        try {
            $p = Database::una('SELECT id, categoria FROM profesores WHERE usuario_id = ?', [$uid]);
            if (Auth::tieneRol('profesor') || $p) {
                $g[] = 'profesores';
            }
            if ($p && !empty($p['categoria'])) {
                $g[] = (string) $p['categoria'];
            }
            foreach (Database::todas('SELECT DISTINCT programa_id FROM programa_profesores WHERE usuario_id = ? AND activo = 1 AND (fecha_fin IS NULL OR fecha_fin >= CURDATE())', [$uid]) as $r) {
                $g[] = 'programa:' . (int) $r['programa_id'];
            }
        } catch (\Throwable) {
        }
        return $g;
    }

    /** Condición SQL y parámetros: oficios firmados dirigidos a la persona con sesión. */
    private static function condicionRecibidos(): array
    {
        $uid = (int) Auth::id();
        $pid = 0;
        try {
            $pid = (int) Database::valor('SELECT id FROM profesores WHERE usuario_id = ?', [$uid]);
        } catch (\Throwable) {
        }
        $grupos = self::gruposDe();
        $sql = "(d.tipo = 'usuario' AND d.ref = ?) OR (d.tipo = 'profesor' AND d.ref = ?)";
        $p = [(string) $uid, (string) $pid];
        if ($grupos) {
            $sql .= " OR (d.tipo = 'grupo' AND d.ref IN (" . implode(',', array_fill(0, count($grupos), '?')) . '))';
            array_push($p, ...$grupos);
        }
        return ['(' . $sql . ')', $p];
    }

    /** Oficios firmados dirigidos a la persona con sesión (más recientes primero). */
    public static function recibidos(): array
    {
        if (!Auth::id()) {
            return [];
        }
        [$c, $p] = self::condicionRecibidos();
        return Database::todas(
            "SELECT DISTINCT o.id, o.fecha, o.numero, o.numero_texto, o.asunto FROM ofs_oficios o JOIN ofs_oficio_destinos d ON d.oficio_id = o.id
              WHERE o.estado = 'firmado' AND o.pdf_firmado IS NOT NULL AND {$c} ORDER BY o.fecha DESC, o.numero DESC",
            $p
        );
    }

    public static function leDirige(int $oficioId): bool
    {
        if (!Auth::id()) {
            return false;
        }
        [$c, $p] = self::condicionRecibidos();
        return (bool) Database::valor(
            "SELECT o.id FROM ofs_oficios o JOIN ofs_oficio_destinos d ON d.oficio_id = o.id WHERE o.id = ? AND o.estado = 'firmado' AND {$c} LIMIT 1",
            [$oficioId, ...$p]
        );
    }
}
