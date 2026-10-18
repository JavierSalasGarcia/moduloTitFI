<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Services;

use App\Core\Database;
use App\Core\App;
use Throwable;

final class RetencionService
{
    public function ejecutarRetencion(): array
    {
        $vencidas = Database::todas(
            'SELECT r.id, r.asunto, r.zip_archivo, b.ruta_zip, b.descargas
               FROM reuniones r
               LEFT JOIN reuniones_respaldo b ON b.reunion_id = r.id
              WHERE r.retener_hasta IS NOT NULL AND r.retener_hasta < CURDATE()'
        );

        $resultado = [
            'procesadas' => 0,
            'omitidas' => 0,
            'errores' => []
        ];

        $storage = rtrim((string) App::config('archivos.storage'), '/\\');

        foreach ($vencidas as $r) {
            $reunionId = (int) $r['id'];

            // 1. Protection against accidental deletion:
            // Do not automatically delete if it's pending administrative download (descargas == 0)
            if ($r['zip_archivo'] !== null && isset($r['descargas']) && (int) $r['descargas'] === 0) {
                Database::ejecutar(
                    "INSERT INTO reuniones_auditoria (reunion_id, accion, datos_json) 
                     VALUES (?, 'retencion_omitida_pendiente_descarga', ?)",
                    [$reunionId, json_encode(['asunto' => $r['asunto']])]
                );
                $resultado['omitidas']++;
                continue;
            }

            $pdo = Database::pdo();
            $pdo->beginTransaction();
            try {
                // Get all files of this meeting
                $archivos = Database::todas('SELECT id, ruta_storage FROM reuniones_archivos WHERE reunion_id = ?', [$reunionId]);
                
                // Delete physical files
                foreach ($archivos as $arc) {
                    $rutaFisica = $storage . '/' . ltrim($arc['ruta_storage'], '/\\');
                    if (is_file($rutaFisica)) {
                        @unlink($rutaFisica);
                    }
                }

                // Delete physical ZIP
                if ($r['ruta_zip']) {
                    $rutaZipFisica = $storage . '/' . ltrim($r['ruta_zip'], '/\\');
                    if (is_file($rutaZipFisica)) {
                        @unlink($rutaZipFisica);
                    }
                    // Also clean up directory if empty
                    $dir = dirname($rutaZipFisica);
                    if (is_dir($dir)) {
                        @rmdir($dir);
                    }
                }

                // Clean data but preserve the reunion row and auditoria for minimal traceability
                Database::ejecutar('DELETE FROM reuniones_mensajes WHERE reunion_id = ?', [$reunionId]);
                Database::ejecutar('DELETE FROM reuniones_archivos WHERE reunion_id = ?', [$reunionId]);
                Database::ejecutar('DELETE FROM reuniones_respaldo WHERE reunion_id = ?', [$reunionId]);
                Database::ejecutar('DELETE FROM reuniones_disponibilidades WHERE horario_id IN (SELECT id FROM reuniones_horarios WHERE reunion_id = ?)', [$reunionId]);
                Database::ejecutar('DELETE FROM reuniones_horarios WHERE reunion_id = ?', [$reunionId]);
                Database::ejecutar('DELETE FROM reuniones_participantes WHERE reunion_id = ? AND tipo != \'organizador\'', [$reunionId]);

                // Update meeting record
                Database::ejecutar(
                    "UPDATE reuniones SET descripcion = '[DATOS ELIMINADOS POR RETENCION]', zip_archivo = NULL, retener_hasta = NULL WHERE id = ?",
                    [$reunionId]
                );

                // Log audit
                Database::ejecutar(
                    "INSERT INTO reuniones_auditoria (reunion_id, accion, datos_json) 
                     VALUES (?, 'retencion_ejecutada', ?)",
                    [$reunionId, json_encode(['archivos_borrados' => count($archivos), 'zip_borrado' => !empty($r['ruta_zip'])])]
                );

                $pdo->commit();
                $resultado['procesadas']++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $resultado['errores'][] = "Error en Reunión #{$reunionId}: " . $e->getMessage();
            }
        }

        return $resultado;
    }
}
