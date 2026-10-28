<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\AccessControl;
use App\Core\Auth;
use App\Core\Database;

/**
 * Recordatorios en la bandeja de Traza FI de los eventos que la persona agregó a su calendario: la víspera (24 h antes)
 * y una hora antes del inicio, y la víspera de la fecha límite (en las convocatorias, solo esta). No requiere tareas
 * programadas: se generan cuando la persona usa la plataforma (como mucho una revisión cada cinco minutos por sesión),
 * que es cuando puede verlos en su bandeja. evento_recordatorios evita repetirlos.
 */
final class Recordatorios
{
    private const CADA = 300;

    public static function generar(): void
    {
        $uid = Auth::id();
        if ($uid <= 0 || (int) ($_SESSION['_recordatorios_en'] ?? 0) > time() - self::CADA) {
            return;
        }
        $_SESSION['_recordatorios_en'] = time();
        try {
            AccessControl::internal(static fn() => self::paraUsuario($uid, (string) (Auth::usuario()['email'] ?? '')));
        } catch (\Throwable $e) {
            error_log('Recordatorios: ' . $e->getMessage());
        }
    }

    /** @return int recordatorios creados */
    public static function paraUsuario(int $uid, string $email, ?int $ahora = null): int
    {
        $ahora ??= time();
        $eventos = Database::todas(
            'SELECT ev.id, ev.titulo, ev.categoria, ev.inicio, ev.lugar, ev.enlace_evento, ev.fecha_limite, ev.modalidad
               FROM evento_suscripciones s JOIN eventos ev ON ev.id = s.evento_id
              WHERE s.usuario_id = ? AND (ev.inicio BETWEEN ? AND ? OR ev.fecha_limite BETWEEN ? AND ?)',
            [$uid, date('Y-m-d H:i:s', $ahora), date('Y-m-d H:i:s', $ahora + 86400), date('Y-m-d H:i:s', $ahora), date('Y-m-d H:i:s', $ahora + 86400)]
        );
        $n = 0;
        foreach ($eventos as $ev) {
            $faltan = (int) strtotime((string) $ev['inicio']) - $ahora;
            $momentos = [];
            if ($ev['categoria'] === 'convocatoria') {
                // Una convocatoria no tiene sesión: solo importa su fecha límite.
            } elseif ($faltan > 0 && $faltan <= 3600) {
                $momentos['hora'] = 'Empieza en ' . max(1, (int) round($faltan / 60)) . ' min';
            } elseif ($faltan > 3600 && $faltan <= 86400) {
                $momentos['dia'] = (date('Y-m-d', (int) strtotime((string) $ev['inicio'])) === date('Y-m-d', $ahora) ? 'Es hoy: ' : 'Es mañana: ') . fecha_corta((string) $ev['inicio']);
            }
            $limite = $ev['fecha_limite'] ? (int) strtotime((string) $ev['fecha_limite']) - $ahora : -1;
            if ($limite > 0 && $limite <= 86400) {
                $momentos['limite'] = 'La fecha límite es ' . fecha_corta((string) $ev['fecha_limite']);
            }
            foreach ($momentos as $momento => $cuando) {
                $st = Database::pdo()->prepare('INSERT IGNORE INTO evento_recordatorios (evento_id, usuario_id, momento) VALUES (?, ?, ?)');
                $st->execute([(int) $ev['id'], $uid, $momento]);
                if ($st->rowCount() === 0) {
                    continue;
                }
                $donde = $ev['modalidad'] === 'en_linea' ? ' · en línea' : ($ev['lugar'] ? ' · ' . $ev['lugar'] : '');
                $cuerpo = $cuando . $donde . '.' . ($momento === 'limite' ? ' No olvides completar tu registro.' : ($ev['enlace_evento'] ? ' El enlace está en la ficha del evento.' : ''));
                \App\Modules\Notificaciones\Notificaciones::crear($email, 'Recordatorio: ' . $ev['titulo'], $cuerpo, 'eventos/' . (int) $ev['id'], 'recordatorio');
                $n++;
            }
        }
        return $n;
    }
}
