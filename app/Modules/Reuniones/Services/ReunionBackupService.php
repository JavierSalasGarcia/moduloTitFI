<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Services;

use RuntimeException;
use ZipArchive;

final class ReunionBackupService
{
    public function generar(string $storage, int $id, array $reunion): array
    {
        $dir = rtrim($storage, '/\\') . '/reuniones/' . $id;
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('No se pudo crear el almacenamiento.');
        $txt = $dir . '/' . $id . '.txt';
        $lineas = ["Reunión #{$id}", 'Asunto: ' . $reunion['asunto'], '', 'CHAT'];
        foreach ($reunion['mensajes'] as $m) $lineas[] = sprintf('[%s] %s: %s', $m['creado_en'], $m['nombre'] ?: $m['correo'], $m['contenido']);
        if (file_put_contents($txt, implode("\n", $lineas) . "\n", LOCK_EX) === false) throw new RuntimeException('No se pudo escribir el chat.');
        $zip = $dir . '/' . $id . '.zip'; $a = new ZipArchive();
        if ($a->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) throw new RuntimeException('No se pudo abrir el ZIP.');
        $a->addFile($txt, $id . '.txt');
        foreach ($reunion['archivos'] as $f) {
            $ruta = rtrim($storage, '/\\') . '/' . ltrim($f['ruta_storage'], '/\\');
            if (!is_file($ruta) || !$a->addFile($ruta, 'archivos/' . basename($ruta))) { $a->close(); throw new RuntimeException('Falta un archivo del expediente.'); }
        }
        if (!$a->close() || !is_file($zip) || filesize($zip) === 0) throw new RuntimeException('El ZIP está incompleto.');
        return ['ruta' => 'reuniones/' . $id . '/' . $id . '.zip', 'hash' => (string) hash_file('sha256', $zip)];
    }
}
