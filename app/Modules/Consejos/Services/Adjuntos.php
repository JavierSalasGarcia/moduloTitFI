<?php

declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;

/**
 * Documentos adjuntos de una solicitud o de un caso, cada uno con una breve descripción («Constancias de recursos humanos», «Anexo 1…»).
 * Los de una solicitud pasan al caso cuando ésta se incorpora; el oficio original escaneado de un caso capturado a mano es un adjunto
 * de tipo «oficio». Se guardan en storage/consejos/adjuntos/ (nunca en public/) y se sirven con permisos (AdjuntoController).
 */
final class Adjuntos
{
    public const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx'];
    public const MAX = 15 * 1024 * 1024;
    public const MAX_POR_VEZ = 10;

    /** Se pueden ver en el visor de la sesión; los demás se descargan. */
    public static function esVisible(string $ext): bool
    {
        return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
    }

    public static function mime(string $ext): string
    {
        return ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'][$ext] ?? 'application/octet-stream';
    }

    /**
     * Guarda los archivos del campo `adjuntos[]` con sus descripciones (`adjuntos_desc[]`) y tipos (`adjuntos_tipo[]`, solo en casos).
     * Cada archivo exige su descripción. @param array{solicitud_id?:int,asunto_id?:int} $destino
     * @return array{guardados:int,errores:string[]}
     */
    public static function guardarDelFormulario(array $destino, bool $admiteOficio = false): array
    {
        $r = ['guardados' => 0, 'errores' => []];
        $f = $_FILES['adjuntos'] ?? null;
        if (!is_array($f) || !is_array($f['name'] ?? null)) {
            return $r;
        }
        $desc = (array) ($_POST['adjuntos_desc'] ?? []);
        $tipos = (array) ($_POST['adjuntos_tipo'] ?? []);
        $n = 0;
        foreach ($f['name'] as $i => $nombre) {
            if (($f['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE || $nombre === '') {
                continue;   // fila vacía
            }
            if (++$n > self::MAX_POR_VEZ) {
                $r['errores'][] = 'Se guardan hasta ' . self::MAX_POR_VEZ . ' archivos por vez.';
                break;
            }
            $descripcion = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) ($desc[$i] ?? '')) ?? ''), 0, 200);
            if ($descripcion === '') {
                $r['errores'][] = '«' . mb_substr((string) $nombre, 0, 60) . '» no se guardó: escribe de qué es el archivo (por ejemplo «Constancias de recursos humanos»).';
                continue;
            }
            $tipo = $admiteOficio && ($tipos[$i] ?? '') === 'oficio' ? 'oficio' : 'anexo';
            [$ok, $error] = self::guardar(['name' => (string) $nombre, 'tmp_name' => (string) $f['tmp_name'][$i], 'error' => (int) $f['error'][$i], 'size' => (int) $f['size'][$i]], $descripcion, $tipo, $destino);
            if ($ok) {
                $r['guardados']++;
            } else {
                $r['errores'][] = '«' . mb_substr((string) $nombre, 0, 60) . '» no se guardó: ' . $error;
            }
        }
        return $r;
    }

    /** @return array{0:bool,1:string} */
    private static function guardar(array $f, string $descripcion, string $tipo, array $destino): array
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
        $rel = 'consejos/adjuntos/' . bin2hex(random_bytes(12)) . '.' . $ext;
        $dest = rtrim((string) App::config('archivos.storage'), '/\\') . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0750, true);
        }
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            return [false, 'no fue posible guardarlo.'];
        }
        $nombre = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', $f['name']) ?: ('archivo.' . $ext), 0, 160);
        Database::ejecutar(
            'INSERT INTO consejos_adjuntos (solicitud_id, asunto_id, tipo, descripcion, nombre, ruta, extension, bytes, subido_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$destino['solicitud_id'] ?? null, $destino['asunto_id'] ?? null, $tipo, $descripcion, $nombre, $rel, $ext, (int) $f['size'], Auth::id()]
        );
        return [true, ''];
    }

    public static function de(string $columna, int $id): array
    {
        return Database::todas('SELECT * FROM consejos_adjuntos WHERE ' . ($columna === 'asunto_id' ? 'asunto_id' : 'solicitud_id') . " = ? ORDER BY FIELD(tipo, 'oficio', 'anexo'), id", [$id]);
    }

    public static function quitar(array $adj): void
    {
        $base = realpath((string) App::config('archivos.storage'));
        $abs = $base !== false ? realpath($base . '/' . $adj['ruta']) : false;
        if ($abs !== false && $base !== false && str_starts_with($abs, rtrim($base, '/\\') . DIRECTORY_SEPARATOR) && is_file($abs)) {
            @unlink($abs);
        }
        Database::ejecutar('DELETE FROM consejos_adjuntos WHERE id = ?', [(int) $adj['id']]);
    }

    /** Anexos que se citan en el texto del oficio y de los antecedentes del caso. */
    public static function lista(array $adjuntos): string
    {
        return implode('; ', array_map(static fn(array $a): string => (string) $a['descripcion'], $adjuntos));
    }
}
