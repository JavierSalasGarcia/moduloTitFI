<?php
declare(strict_types=1);

namespace App\Modules\Notificaciones;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\View;

/** Bandeja de notificaciones de la persona con sesión. */
final class NotificacionesController extends Controller
{
    private function correo(): string
    {
        return strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
    }

    public function index(): void
    {
        $this->vista('index', ['titulo' => 'Notificaciones', 'lista' => Notificaciones::de($this->correo()), 'abierta' => null]);
    }

    public function ver(string $id): void
    {
        $n = ctype_digit($id) ? Notificaciones::una((int) $id) : null;
        if ($n === null || $n['email'] !== $this->correo()) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No disponible']);
            return;
        }
        Notificaciones::marcarLeida((int) $n['id']);
        $this->vista('index', ['titulo' => 'Notificaciones', 'lista' => Notificaciones::de($this->correo()), 'abierta' => $n]);
    }

    public function leidas(): void
    {
        Notificaciones::marcarTodas($this->correo());
        redirigir('notificaciones');
    }
}
