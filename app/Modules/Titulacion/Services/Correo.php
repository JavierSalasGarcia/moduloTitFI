<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\App;
use App\Core\Database;
use App\Modules\Reuniones\Services\HostingerMailer;
use Throwable;

/** Envío de avisos por correo con bitácora (sin guardar el cuerpo, que lleva enlaces personales). */
final class Correo
{
    /** URL absoluta de una ruta interna, para los enlaces de los correos. */
    public static function enlace(string $ruta): string
    {
        return rtrim((string) App::config('app.url_publica', ''), '/') . url($ruta);
    }

    /** @param string|array $nombre nombre, o persona con 'nombre', 'grado' y 'genero' (se completa con los datos de su cuenta si la tiene) */
    public static function enviar(string $para, string|array $nombre, string $asunto, string $parrafos, ?string $boton, ?string $urlBoton, string $tipo, ?int $tramiteId = null): bool
    {
        $persona = \App\Core\Persona::completar(is_array($nombre) ? $nombre : ['nombre' => $nombre], $para);
        $saludo = \App\Core\Persona::saludo($persona);
        $esc = static fn(string $t): string => htmlspecialchars($t, ENT_QUOTES, 'UTF-8');
        $html = "<div style='font-family:Arial,sans-serif;max-width:560px;margin:0 auto;padding:20px;border:1px solid #e2e8f0;border-radius:8px'>"
            . "<h2 style='color:#2C5234;margin-top:0'>Titulación · Facultad de Ingeniería</h2>"
            . "<p style='color:#334155;font-size:14px'><strong>" . $esc($saludo) . "</strong>,</p>"
            . "<div style='color:#334155;font-size:14px;line-height:1.5'>" . nl2br($esc($parrafos)) . "</div>";
        if ($boton !== null && $urlBoton !== null) {
            $html .= "<p style='margin:22px 0'><a href='" . $esc($urlBoton) . "' style='background:#2C5234;color:#fff;padding:11px 18px;border-radius:6px;text-decoration:none;font-weight:bold'>" . $esc($boton) . "</a></p>"
                . "<p style='color:#64748b;font-size:12px'>Si el botón no funciona, copia este enlace en tu navegador:<br>" . $esc($urlBoton) . "</p>";
        }
        $html .= "<hr style='border:0;border-top:1px solid #e2e8f0;margin:20px 0'><p style='color:#94a3b8;font-size:11px;text-align:center'>Traza FI · Departamento de Evaluación Profesional · UAEMéx</p></div>";
        $texto = "$saludo,\n\n$parrafos\n" . ($urlBoton !== null ? "\n$boton: $urlBoton\n" : '') . "\nTraza FI · Facultad de Ingeniería UAEMéx";

        $configurado = (string) App::config('correo.api_token', '') !== '';
        $ok = false;
        if ($configurado) {
            try {
                $ok = (new HostingerMailer())->enviar($para, $asunto, $texto, $html);
            } catch (Throwable $e) {
                error_log('Titulación: error al enviar correo: ' . $e->getMessage());
            }
        } elseif (App::config('app.debug')) {
            // Solo en pruebas: deja el mensaje en storage/logs para poder revisarlo.
            $dir = rtrim((string) App::config('archivos.storage'), '/\\') . '/logs';
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
            @file_put_contents($dir . '/titulacion-correos.log', "=== " . date('c') . " a $para ===\n$asunto\n$texto\n\n", FILE_APPEND);
        }
        try {
            Database::ejecutar(
                'INSERT INTO tit_correos (destinatario, asunto, tipo, tramite_id, estado) VALUES (?, ?, ?, ?, ?)',
                [mb_substr($para, 0, 120), mb_substr($asunto, 0, 200), $tipo, $tramiteId, $ok ? 'enviado' : ($configurado ? 'fallido' : 'no_configurado')]
            );
        } catch (Throwable $e) {
            error_log('Titulación: no se registró el correo: ' . $e->getMessage());
        }
        return $ok;
    }
}
