<?php
declare(strict_types=1);

namespace App\Modules\Notificaciones;

use App\Core\AccessControl;
use App\Core\Auth;
use App\Core\Database;
use App\Core\WebPush;

/**
 * Suscripciones de notificaciones push por dispositivo y envío. Los envíos se acumulan y salen al terminar la petición
 * (ya enviada la página), para no hacer esperar a quien publica.
 */
final class Push
{
    private static array $cola = [];
    private static bool $programado = false;

    public static function guardar(int $usuarioId, string $endpoint, string $p256dh, string $auth, string $agente): void
    {
        Database::ejecutar('INSERT INTO push_suscripciones (usuario_id, endpoint, p256dh, auth, agente) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), p256dh = VALUES(p256dh), auth = VALUES(auth), agente = VALUES(agente), fallos = 0',
            [$usuarioId, $endpoint, $p256dh, $auth, mb_substr($agente, 0, 200)]);
    }

    public static function quitar(string $endpoint): void
    {
        Database::ejecutar('DELETE FROM push_suscripciones WHERE endpoint = ?', [$endpoint]);
    }

    public static function dispositivos(int $usuarioId): int
    {
        try {
            return (int) Database::valor('SELECT COUNT(*) FROM push_suscripciones WHERE usuario_id = ?', [$usuarioId]);
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Encola una notificación para las personas indicadas. $url relativa (p. ej. «notificaciones/12»). */
    public static function aUsuarios(array $ids, string $titulo, string $cuerpo, string $url): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return;
        }
        self::$cola[] = [$ids, mb_substr($titulo, 0, 120), mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($cuerpo))), 0, 180), '/' . ltrim($url, '/')];
        if (!self::$programado) {
            self::$programado = true;
            register_shutdown_function([self::class, 'despachar']);
        }
    }

    public static function aCorreo(string $email, string $titulo, string $cuerpo, string $url): void
    {
        try {
            $u = AccessControl::internal(fn() => \App\Core\Identidad::usuarioPorCorreo($email));
            if ($u) {
                self::aUsuarios([(int) $u['id']], $titulo, $cuerpo, $url);
            }
        } catch (\Throwable) {
        }
    }

    /** Personas con dispositivo suscrito que pueden ver el aviso oficial (mismo alcance que la página de avisos). */
    public static function destinatariosAviso(int $comunicadoId): array
    {
        $ids = [];
        $original = $_SESSION['usuario'] ?? null;
        try {
            foreach (Database::todas('SELECT DISTINCT usuario_id FROM push_suscripciones') as $r) {
                $u = AccessControl::user((int) $r['usuario_id']);
                if (!$u) {
                    continue;
                }
                $_SESSION['usuario'] = $u;
                if (in_array($comunicadoId, array_map('intval', array_column(\App\Modules\Comunicados\Avisos::visibles(false), 'id')), true)) {
                    $ids[] = (int) $u['id'];
                }
            }
        } catch (\Throwable) {
        } finally {
            if ($original === null) {
                unset($_SESSION['usuario']);
            } else {
                $_SESSION['usuario'] = $original;
            }
        }
        return $ids;
    }

    public static function despachar(): void
    {
        if (!self::$cola) {
            return;
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        ignore_user_abort(true);
        if (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } elseif (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        @set_time_limit(120);
        try {
            foreach (self::$cola as [$ids, $titulo, $cuerpo, $url]) {
                $subs = Database::todas('SELECT * FROM push_suscripciones WHERE usuario_id IN (' . implode(',', $ids) . ')');
                foreach ($subs as $s) {
                    try {
                        $c = WebPush::enviar($s, ['titulo' => $titulo, 'cuerpo' => $cuerpo, 'url' => $url]);
                    } catch (\Throwable) {
                        $c = 0;
                    }
                    if ($c === 404 || $c === 410) {
                        Database::ejecutar('DELETE FROM push_suscripciones WHERE id = ?', [(int) $s['id']]);
                    } elseif ($c >= 200 && $c < 300) {
                        Database::ejecutar('UPDATE push_suscripciones SET ultimo_envio = NOW(), fallos = 0 WHERE id = ?', [(int) $s['id']]);
                    } else {
                        Database::ejecutar('UPDATE push_suscripciones SET fallos = fallos + 1 WHERE id = ?', [(int) $s['id']]);
                    }
                }
            }
        } catch (\Throwable $e) {
            error_log('Push: ' . $e->getMessage());
        }
        self::$cola = [];
    }
}
