<?php
declare(strict_types=1);

namespace App\Modules\Notificaciones;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\View;

/** Bandeja de notificaciones de la persona con sesión. */
final class NotificacionesController extends Controller
{
    /** Todos los correos de la persona (el vigente y los anteriores): sus avisos siguen llegando aquí aunque cambie el dominio. @return string[] */
    private function correos(): array
    {
        $u = Auth::usuario();
        return array_values(array_unique(array_filter(array_map(
            static fn($e): string => strtolower(trim((string) $e)),
            [...\App\Core\Identidad::correosDeUsuario((int) ($u['id'] ?? 0)), (string) ($u['email'] ?? '')]
        ))));
    }

    public function index(): void
    {
        $this->vista('index', ['titulo' => 'Notificaciones', 'lista' => Notificaciones::de($this->correos()), 'abierta' => null, 'pendientes' => \App\Core\Pendientes::agrupados()]);
    }

    public function ver(string $id): void
    {
        $n = ctype_digit($id) ? Notificaciones::una((int) $id) : null;
        if ($n === null || !in_array($n['email'], $this->correos(), true)) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No disponible']);
            return;
        }
        Notificaciones::marcarLeida((int) $n['id']);
        $this->vista('index', ['titulo' => 'Notificaciones', 'lista' => Notificaciones::de($this->correos()), 'abierta' => $n, 'pendientes' => []]);
    }

    public function leidas(): void
    {
        Notificaciones::marcarTodas($this->correos());
        redirigir('notificaciones');
    }

    /** Registra el dispositivo (navegador o app instalada) para recibir notificaciones push. */
    public function suscribir(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $endpoint = (string) ($_POST['endpoint'] ?? '');
        $p256dh = (string) ($_POST['p256dh'] ?? '');
        $auth = (string) ($_POST['auth'] ?? '');
        if (!preg_match('#^https://[^\s]{10,690}$#', $endpoint) || !preg_match('/^[A-Za-z0-9_-]{60,120}$/', $p256dh) || !preg_match('/^[A-Za-z0-9_-]{10,60}$/', $auth)) {
            http_response_code(422);
            echo json_encode(['exito' => false, 'error' => 'Suscripción no válida.']);
            exit;
        }
        Push::guardar((int) Auth::id(), $endpoint, $p256dh, $auth, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        if (($_POST['probar'] ?? '') === '1') {
            Push::aUsuarios([(int) Auth::id()], 'Traza FI', 'Listo: en este dispositivo recibirás avisos, eventos y notificaciones de la plataforma.', 'notificaciones');
        }
        echo json_encode(['exito' => true]);
        exit;
    }

    public function cancelar(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        Push::quitar((string) ($_POST['endpoint'] ?? ''));
        echo json_encode(['exito' => true]);
        exit;
    }

    /** Guía para instalar Traza FI como app en Android e iOS y activar las notificaciones. */
    public function guia(): void
    {
        $this->vista('guia_app', ['titulo' => 'Instalar Traza FI y recibir notificaciones', 'clavePush' => \App\Core\WebPush::claves()['publica'],
            'dispositivos' => Push::dispositivos((int) Auth::id())]);
    }
}
