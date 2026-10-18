<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Controlador base: render con layout y validación CSRF para POST.
 */
abstract class Controller
{
    public function __construct(protected string $modulo)
    {
    }

    /** Renderiza una vista de la carpeta views/ del propio módulo. */
    protected function vista(string $nombre, array $datos = [], string $layout = 'app'): void
    {
        $datos['moduloActivo'] = $this->modulo;
        View::render("{$this->modulo}:{$nombre}", $datos, $layout);
    }

    /** Campo POST saneado. (El token CSRF lo valida el Router en todo POST.) */
    protected function post(string $campo, string $defecto = ''): string
    {
        return trim((string) ($_POST[$campo] ?? $defecto));
    }
}
