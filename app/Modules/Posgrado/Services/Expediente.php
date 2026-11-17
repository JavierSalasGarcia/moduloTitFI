<?php
declare(strict_types=1);

namespace App\Modules\Posgrado\Services;

use App\Core\App;
use App\Modules\Posgrado\PosgradoRepository;
use RuntimeException;

/** Guarda en storage/posgrado/<id>/ los oficios y constancias que emite la plataforma y los registra con su huella. */
final class Expediente
{
    public static function carpeta(int $tramiteId): string
    {
        return 'posgrado/' . $tramiteId;
    }

    public static function base(): string
    {
        return rtrim((string) App::config('archivos.storage'), '/\\');
    }

    /** Ruta absoluta de un archivo de storage/ (relativa como se guarda en la base). */
    public static function absoluta(string $relativa): string
    {
        return self::base() . '/' . ltrim($relativa, '/');
    }

    /** Nombre nuevo, dentro de la carpeta del trámite, para la versión sellada de un documento. */
    public static function nuevaRuta(int $tramiteId, string $clave): string
    {
        return self::carpeta($tramiteId) . '/' . preg_replace('/[^a-z0-9_]+/', '_', $clave) . '-firmado-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
    }

    /** @return int id en pos_generados */
    public static function guardar(array $t, string $clave, string $nombre, string $bytes, int $usuarioId, ?string $huellaDatos = null): int
    {
        if (!str_starts_with($bytes, '%PDF')) {
            throw new RuntimeException('El documento generado no es un PDF.');
        }
        $base = self::base();
        $relativa = self::carpeta((int) $t['id']);
        $dir = $base . '/' . $relativa;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear la carpeta del expediente.');
        }
        $archivo = preg_replace('/[^a-z0-9_]+/', '_', $clave) . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
        if (file_put_contents($dir . '/' . $archivo, $bytes, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el documento generado.');
        }
        return PosgradoRepository::registrarGenerado((int) $t['id'], $clave, $nombre, $relativa . '/' . $archivo, hash('sha256', $bytes), $usuarioId, $huellaDatos);
    }
}
