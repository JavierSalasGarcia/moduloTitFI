<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

use App\Core\App;
use Throwable;

/**
 * Avisos del módulo: deja la notificación en la bandeja (módulo Notificaciones) y manda un correo que alerta con un
 * enlace a la plataforma (que pide sesión). El correo no lleva adjuntos: el envío de la plataforma (HostingerMailer)
 * no los admite, así que el PDF se descarga desde el enlace. En modo debug sin servicio de correo, el mensaje queda
 * en storage/logs/constancias-correos.log. Un fallo del aviso nunca interrumpe la acción que lo origina.
 */
final class Aviso
{
    public static function enlace(string $ruta): string
    {
        // No se usa url_publica . url($ruta): url() antepone el directorio del script en ejecución y, fuera del front
        // controller, el enlace saldría con una carpeta ajena.
        $publica = rtrim((string) App::config('app.url_publica', ''), '/');
        return $publica !== '' ? $publica . '/' . ltrim($ruta, '/') : url($ruta);
    }

    public static function enviar(string $para, string $nombre, string $asunto, string $texto, string $ruta, string $boton): void
    {
        $para = strtolower(trim($para));
        if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $enlace = self::enlace($ruta);
        try {
            if (class_exists(\App\Modules\Notificaciones\Notificaciones::class)) {
                \App\Modules\Notificaciones\Notificaciones::crear($para, $asunto, $texto, $enlace, 'constancias', null);
            }
        } catch (Throwable $e) {
            error_log('Constancias: no se dejó la notificación: ' . $e->getMessage());
        }
        try {
            $esc = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
            $saludo = $nombre !== '' ? 'Hola, ' . $nombre : 'Hola';
            $html = "<div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:8px'>"
                . "<h2 style='color:#2C5234;margin-top:0'>Constancias · Facultad de Ingeniería</h2>"
                . "<p style='color:#334155;font-size:14px'><strong>" . $esc($saludo) . '</strong>:</p>'
                . "<div style='color:#334155;font-size:14px;line-height:1.5'>" . nl2br($esc($texto)) . '</div>'
                . "<p style='margin:22px 0'><a href='" . $esc($enlace) . "' style='background:#2C5234;color:#fff;padding:11px 18px;border-radius:6px;text-decoration:none;font-weight:bold'>" . $esc($boton) . '</a></p>'
                . "<p style='color:#64748b;font-size:12px'>Si el botón no funciona, copia este enlace en tu navegador:<br>" . $esc($enlace) . '</p>'
                . "<hr style='border:0;border-top:1px solid #e2e8f0;margin:20px 0'><p style='color:#94a3b8;font-size:11px;text-align:center'>Traza FI · Facultad de Ingeniería · UAEMéx</p></div>";
            $plano = "$saludo:\n\n$texto\n\n$boton: $enlace\n\nTraza FI · Facultad de Ingeniería UAEMéx";
            if ((string) App::config('correo.api_token', '') !== '' && class_exists(\App\Modules\Reuniones\Services\HostingerMailer::class)) {
                (new \App\Modules\Reuniones\Services\HostingerMailer())->enviar($para, $asunto, $plano, $html);
            } elseif (App::config('app.debug')) {
                $dir = rtrim((string) App::config('archivos.storage'), '/\\') . '/logs';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0750, true);
                }
                @file_put_contents($dir . '/constancias-correos.log', '=== ' . date('c') . " a $para ===\n$asunto\n$plano\n\n", FILE_APPEND);
            }
        } catch (Throwable $e) {
            error_log('Constancias: no se envió el correo: ' . $e->getMessage());
        }
    }
}
