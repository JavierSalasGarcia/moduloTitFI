<?php
declare(strict_types=1);

namespace App\Modules\Oficios\Services;

use App\Core\Auth;
use App\Core\Database;
use RuntimeException;

/** Destinatarios reutilizables de los oficios de la Subdirección (tabla ofs_destinatarios). */
final class Destinatarios
{
    private static function limpio(string $s, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $s) ?? ''), 0, $max);
    }

    /** @return array<int,array{id:int,nombre:string,cargo:string,dependencia:string}> */
    public static function buscar(string $q, int $limite = 12): array
    {
        $q = self::limpio($q, 80);
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        return Database::todas(
            'SELECT id, nombre, cargo, dependencia FROM ofs_destinatarios WHERE activo = 1 AND (? = \'\' OR nombre LIKE ? OR cargo LIKE ? OR dependencia LIKE ?)
              ORDER BY nombre LIMIT ' . (int) $limite,
            [$q, $like, $like, $like]
        );
    }

    /** Crea (o reutiliza si ya existe) un destinatario. @return array{id:int,nombre:string,cargo:string,dependencia:string} */
    public static function crear(string $nombre, string $cargo, string $dependencia): array
    {
        $nombre = self::limpio($nombre, 160);
        $cargo = self::limpio($cargo, 255);
        $dependencia = self::limpio($dependencia, 255);
        if (mb_strlen($nombre) < 3) {
            throw new RuntimeException('Escribe el nombre del destinatario (con su grado, por ejemplo «Dra. Ana López»).');
        }
        $id = Database::valor('SELECT id FROM ofs_destinatarios WHERE nombre = ? AND cargo = ? AND dependencia = ?', [$nombre, $cargo, $dependencia]);
        if ($id) {
            Database::ejecutar('UPDATE ofs_destinatarios SET activo = 1 WHERE id = ?', [(int) $id]);
        } else {
            $id = Database::ejecutar('INSERT INTO ofs_destinatarios (nombre, cargo, dependencia, creado_por) VALUES (?, ?, ?, ?)', [$nombre, $cargo, $dependencia, Auth::id()]);
        }
        return ['id' => (int) $id, 'nombre' => $nombre, 'cargo' => $cargo, 'dependencia' => $dependencia];
    }

    /**
     * Copia de los destinatarios elegidos, en ese orden: el oficio conserva lo que decía al momento de guardarse aunque luego se edite el catálogo.
     * @param array<int,int|string> $ids
     * @return array<int,array{nombre:string,cargo:string,dependencia:string}>
     */
    public static function instantaneas(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }
        $filas = Database::todas('SELECT id, nombre, cargo, dependencia FROM ofs_destinatarios WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        $porId = array_column($filas, null, 'id');
        $r = [];
        foreach ($ids as $id) {
            if (isset($porId[$id])) {
                $r[] = ['nombre' => $porId[$id]['nombre'], 'cargo' => $porId[$id]['cargo'], 'dependencia' => $porId[$id]['dependencia']];
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
}
