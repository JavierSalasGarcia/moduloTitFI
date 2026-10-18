<?php
declare(strict_types=1);

namespace App\Modules\Ejemplo;

use App\Core\Controller;

final class EjemploController extends Controller
{
    public function index(): void
    {
        $this->vista('index', ['titulo' => 'Ejemplo']);
    }

    public function guardar(): void
    {
        // El Router ya exigió sesión, rol del módulo y token CSRF.
        flash('exito', 'Guardado.');
        redirigir('ejemplo');
    }
}
