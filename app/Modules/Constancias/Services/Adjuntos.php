<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;

/**
 * Evidencia opcional de una constancia solicitada (PDF, imagen, Word o Excel; la descripción es opcional). La sube quien solicita al
 * pedirla o al corregirla cuando el firmante la devolvió pidiendo evidencias o más datos. Se guarda en storage/constancias/adjuntos/ y se
 * sirve con permiso (AdjuntoController): solo el solicitante, el firmante designado y la supervisión.
 */
final class Adjuntos
{
    public const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'];
    public const MAX = 15 * 1024 * 1024;
    public const MAX_POR_VEZ = 10;

    public static function mime(string $ext): string
    {
        return ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'][$ext] ?? 'application/octet-stream';
    }

    public static function de(int $solicitudId): array
    {
        try {
            return Database::todas('SELECT * FROM cst_adjuntos WHERE solicitud_id = ? ORDER BY id', [$solicitudId]);
        } catch (\Throwable $e) {
            error_log('Constancias: adjuntos no disponibles: ' . $e->getMessage());
            return [];
        }
    }

    public static function uno(int $id): ?array
    {
        return Database::una('SELECT * FROM cst_adjuntos WHERE id = ?', [$id]);
    }

    /** Guarda los archivos del campo `adjuntos[]` con su descripción opcional (`adjuntos_desc[]`). @return array{guardados:int,errores:string[]} */
    public static function guardarDelFormulario(int $solicitudId): array
    {
        $r = ['guardados' => 0, 'errores' => []];
        $f = $_FILES['adjuntos'] ?? null;
        if (!is_array($f) || !is_array($f['name'] ?? null)) {
            return $r;
        }
        $desc = (array) ($_POST['adjuntos_desc'] ?? []);
        $n = 0;
        foreach ($f['name'] as $i => $nombre) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || $nombre === '') {
                continue;
            }
            if (++$n > self::MAX_POR_VEZ) {
                $r['errores'][] = 'Se guardan hasta ' . self::MAX_POR_VEZ . ' archivos por vez.';
                break;
            }
            $descripcion = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($desc[$i] ?? '')) ?? ''), 0, 200);
            [$ok, $error] = self::guardar(['name' => (string) $nombre, 'tmp_name' => (string) $f['tmp_name'][$i], 'error' => (int) $f['error'][$i], 'size' => (int) $f['size'][$i]], $descripcion, $solicitudId);
            if ($ok) {
                $r['guardados']++;
            } else {
                $r['errores'][] = '«' . mb_substr((string) $nombre, 0, 60) . '» no se guardó: ' . $error;
            }
        }
        return $r;
    }

    /** @return array{0:bool,1:string} */
    private static function guardar(array $f, string $descripcion, int $solicitudId): array
    {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            return [false, 'no llegó completo (revisa que pese menos de ' . (int) (self::MAX / 1048576) . ' MB).'];
        }
        if ($f['size'] > self::MAX || $f['size'] <= 0) {
            return [false, 'pesa más de ' . (int) (self::MAX / 1048576) . ' MB o está vacío.'];
        }
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::EXTENSIONES, true)) {
            return [false, 'solo se aceptan ' . implode(', ', self::EXTENSIONES) . '.'];
        }
        $cabecera = (string) file_get_contents($f['tmp_name'], false, null, 0, 8);
        $valido = match ($ext) {
            'pdf' => str_starts_with($cabecera, '%PDF-'),
            'jpg', 'jpeg' => str_starts_with($cabecera, "\xFF\xD8\xFF"),
            'png' => str_starts_with($cabecera, "\x89PNG"),
            default => str_starts_with($cabecera, 'PK'),
        };
        if (!$valido) {
            return [false, 'el contenido no corresponde a un archivo .' . $ext . '.'];
        }
        $rel = 'constancias/adjuntos/' . $solicitudId . '-' . bin2hex(random_bytes(10)) . '.' . $ext;
        $dest = rtrim((string) App::config('archivos.storage'), '/\\') . '/' . $rel;
        if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0750, true) && !is_dir(dirname($dest))) {
            return [false, 'no fue posible crear la carpeta.'];
        }
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            return [false, 'no fue posible guardarlo.'];
        }
        $nombre = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $f['name']) ?: ('archivo.' . $ext), 0, 160);
        Database::ejecutar(
            'INSERT INTO cst_adjuntos (solicitud_id, descripcion, nombre, ruta, extension, bytes, subido_por) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$solicitudId, $descripcion, $nombre, $rel, $ext, (int) $f['size'], Auth::id()]
        );
        return [true, ''];
    }

    public static function quitar(array $adj): void
    {
        $base = realpath((string) App::config('archivos.storage'));
        $abs = $base !== false ? realpath($base . '/' . $adj['ruta']) : false;
        if ($abs !== false && $base !== false && str_starts_with($abs, rtrim($base, '/\\') . DIRECTORY_SEPARATOR) && is_file($abs)) {
            @unlink($abs);
        }
        Database::ejecutar('DELETE FROM cst_adjuntos WHERE id = ?', [(int) $adj['id']]);
    }
}
