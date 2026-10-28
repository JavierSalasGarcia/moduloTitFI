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
}
