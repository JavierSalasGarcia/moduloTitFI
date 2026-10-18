<?php
declare(strict_types=1);
namespace App\Modules\Plan;

use App\Core\Auth;

/** Historial del plan asociado a la cuenta autenticada, disponible entre dispositivos. */
final class PlanDraftStore
{
    private static function empty(): array
    {
        return ['version' => 0, 'revisiones' => []];
    }

    private static function root(): string
    {
        return dirname(APP_RAIZ) . '/storage/plan/revisiones';
    }

    private static function accountFile(int $number, bool $create = true): ?string
    {
        $userId = Auth::id();
        if ($userId < 1) return null;
        $folder = self::root() . '/cuenta-' . hash('sha256', 'usuario:' . $userId);
        if ($create && !is_dir($folder) && !mkdir($folder, 0770, true) && !is_dir($folder)) {
            throw new \RuntimeException('No se pudo crear el espacio de revisiones de la cuenta.');
        }
        return $folder . '/proyecto-' . $number . '.json';
    }

    private static function legacyFiles(int $number): array
    {
        return array_values(array_filter(glob(self::root() . '/*/proyecto-' . $number . '.json') ?: [],
            static fn($file) => preg_match('/^[a-f0-9]{64}$/', basename(dirname((string)$file))) === 1));
    }

    private static function decode(string $raw): array
    {
        $data = $raw !== '' ? json_decode($raw, true) : null;
        return is_array($data) && is_array($data['revisiones'] ?? null) ? $data : self::empty();
    }

    private static function belongsToCurrentAccount(array $revision): bool
    {
        $user = Auth::usuario();
        if (!$user) return false;
        $author = $revision['autor'] ?? [];
        if ((int)($author['usuario_id'] ?? 0) === (int)$user['id']) return true;
        $accountEmail = strtolower(trim((string)($user['email'] ?? '')));
        $authorEmail = strtolower(trim((string)($author['correo'] ?? '')));
        return $accountEmail !== '' && hash_equals($accountEmail, $authorEmail);
    }

    private static function signature(array $revision): string
    {
        return hash('sha256', json_encode([
            $revision['fecha'] ?? '', $revision['autor'] ?? [], $revision['contenido'] ?? [],
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private static function normalize(array $revisions): array
    {
        $unique = [];
        foreach ($revisions as $revision) {
            if (is_array($revision)) $unique[self::signature($revision)] = $revision;
        }
        $items = array_values($unique);
        usort($items, static fn($a, $b) => strcmp((string)($a['fecha'] ?? ''), (string)($b['fecha'] ?? '')));
        foreach ($items as $index => &$revision) $revision['version'] = $index + 1;
        unset($revision);
        return ['version' => count($items), 'revisiones' => $items];
    }

    /** Localiza y migra una sola vez todas las revisiones históricas que pertenecen a la cuenta. */
    private static function migrateLegacy(int $number, string $accountFile): void
    {
        foreach (self::legacyFiles($number) as $legacyFile) {
            if (!is_file($legacyFile) || $legacyFile === $accountFile) continue;
            $legacyHandle = fopen($legacyFile, 'c+');
            if (!$legacyHandle) continue;
            try {
                if (!flock($legacyHandle, LOCK_EX)) continue;
                $legacy = self::decode((string)stream_get_contents($legacyHandle));
                $mine = array_values(array_filter($legacy['revisiones'], [self::class, 'belongsToCurrentAccount']));
                if (!$mine) { flock($legacyHandle, LOCK_UN); continue; }

                $accountHandle = fopen($accountFile, 'c+');
                if (!$accountHandle) throw new \RuntimeException('No se pudo abrir el historial de la cuenta.');
                try {
                    if (!flock($accountHandle, LOCK_EX)) throw new \RuntimeException('No se pudo bloquear el historial de la cuenta.');
                    $account = self::decode((string)stream_get_contents($accountHandle));
                    $account = self::normalize(array_merge($account['revisiones'], $mine));
                    rewind($accountHandle);ftruncate($accountHandle, 0);
                    fwrite($accountHandle, json_encode($account, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                    fflush($accountHandle);flock($accountHandle, LOCK_UN);
                } finally { fclose($accountHandle); }

                $remaining = array_values(array_filter($legacy['revisiones'],
                    static fn($revision) => !is_array($revision) || !self::belongsToCurrentAccount($revision)));
                $backup = $legacyFile . '.migrado-cuenta-' . Auth::id() . '-' . date('YmdHis') . '.bak';
                @copy($legacyFile, $backup);
                $legacy = self::normalize($remaining);
                rewind($legacyHandle);ftruncate($legacyHandle, 0);
                fwrite($legacyHandle, json_encode($legacy, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
                fflush($legacyHandle);flock($legacyHandle, LOCK_UN);
            } finally { fclose($legacyHandle); }
        }
    }

    public static function read(int $number): array
    {
        $file = self::accountFile($number);
        if (!$file) return self::empty();
        self::migrateLegacy($number, $file);
        if (!is_file($file)) return self::empty();
        return self::decode((string)file_get_contents($file));
    }

    /** Lee las revisiones de la cuenta sin crear archivos para visitantes no autenticados. */
    public static function peek(int $number): array
    {
        if (Auth::id() < 1) return self::empty();
        return self::read($number);
    }

    public static function save(int $number, int $expectedVersion, array $content, array $author): array
    {
        $file = self::accountFile($number);
        if (!$file) throw new \DomainException('Inicia sesión para guardar revisiones del plan.');
        self::migrateLegacy($number, $file);
        $handle = fopen($file, 'c+');
        if (!$handle) throw new \RuntimeException('No se pudo abrir el borrador.');
        try {
            if (!flock($handle, LOCK_EX)) throw new \RuntimeException('No se pudo bloquear el borrador.');
            $data = self::decode((string)stream_get_contents($handle));
            if ((int)$data['version'] !== $expectedVersion) {
                throw new \DomainException('El borrador cambió en otra sesión o dispositivo. Recarga para revisar la versión nueva.');
            }
            $data['version']++;
            $data['revisiones'][] = ['version' => $data['version'],
                'fecha' => date(DATE_ATOM), 'autor' => $author, 'contenido' => $content];
            rewind($handle);ftruncate($handle, 0);
            fwrite($handle, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            fflush($handle);flock($handle, LOCK_UN);
            return $data;
        } finally { fclose($handle); }
    }
}
