<?php
declare(strict_types=1);

namespace App\Modules\Posgrado\Services;

use App\Core\App;
use Throwable;

/**
 * Avisos al egresado: deja la notificación en su bandeja (módulo Notificaciones) y le manda un correo que lo alerta.
 * Un fallo de la bandeja o del correo nunca interrumpe la acción que lo origina.
 */
final class Aviso
{
    public static function enlace(string $ruta): string
    {
        return rtrim((string) App::config('app.url_publica', ''), '/') . url($ruta);
    }

    /** @param bool $correo false: solo la bandeja de notificaciones (p. ej. cada documento validado, para no saturar el correo) */
    public static function egresado(array $t, string $asunto, string $texto, bool $correo = true): void
    {
        $para = strtolower(trim((string) ($t['alumno_email'] ?? '')));
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $enlace = self::enlace('posgrado/tramite/' . (int) $t['id']);
        try {
            if (class_exists(\App\Modules\Notificaciones\Notificaciones::class)) {
                // tramite_id de notificaciones se refiere a Titulación: aquí va nulo y el enlace lleva al trámite.
                \App\Modules\Notificaciones\Notificaciones::crear($para, $asunto, $texto, $enlace, 'posgrado', null);
            }
        } catch (Throwable $e) {
            error_log('Posgrado: no se dejó la notificación: ' . $e->getMessage());
        }
        if (!$correo) {
            return;
        }
        try {
            self::correo($para, (string) ($t['alumno_nombre'] ?? ''), $asunto, $texto, $enlace);
        } catch (Throwable $e) {
            error_log('Posgrado: no se envió el correo: ' . $e->getMessage());
        }
    }

    private static function correo(string $para, string $nombre, string $asunto, string $texto, string $enlace): void
    {
        $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
        $saludo = $nombre !== '' ? 'Hola, ' . $nombre : 'Hola';
        $html = "<div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:8px'>"
            . "<h2 style='color:#2C5234;margin-top:0'>Evaluación de grado · Facultad de Ingeniería</h2>"
            . "<p style='color:#334155;font-size:14px'><strong>" . $esc($saludo) . '</strong>:</p>'
            . "<div style='color:#334155;font-size:14px;line-height:1.5'>" . nl2br($esc($texto)) . '</div>'
            . "<p style='margin:22px 0'><a href='" . $esc($enlace) . "' style='background:#2C5234;color:#fff;padding:11px 18px;border-radius:6px;text-decoration:none;font-weight:bold'>Ver mi trámite</a></p>"
            . "<hr style='border:0;border-top:1px solid #e2e8f0;margin:20px 0'><p style='color:#94a3b8;font-size:11px;text-align:center'>Traza FI · Coordinación de Estudios Avanzados · UAEMéx</p></div>";
        $plano = "$saludo:\n\n$texto\n\nVer mi trámite: $enlace\n\nTraza FI · Facultad de Ingeniería UAEMéx";
        if ((string) App::config('correo.api_token', '') !== '' && class_exists(\App\Modules\Reuniones\Services\HostingerMailer::class)) {
            (new \App\Modules\Reuniones\Services\HostingerMailer())->enviar($para, $asunto, $plano, $html);
        } elseif (App::config('app.debug')) {
            // Solo en pruebas: deja el mensaje en storage/logs para revisarlo.
            $dir = rtrim((string) App::config('archivos.storage'), '/\\') . '/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            @file_put_contents($dir . '/posgrado-correos.log', '=== ' . date('c') . " a $para ===\n$asunto\n$plano\n\n", FILE_APPEND);
        }
    }
}
