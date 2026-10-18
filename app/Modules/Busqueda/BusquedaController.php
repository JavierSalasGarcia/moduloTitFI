<?php
declare(strict_types=1);

namespace App\Modules\Busqueda;

use App\Core\Controller;

/**
 * Búsqueda semántica: describe tu necesidad en lenguaje natural y el
 * sistema encuentra las funciones oficiales más parecidas; con un clic
 * el ticket queda precanalizado al departamento correspondiente.
 */
final class BusquedaController extends Controller
{
    public function index(): void
    {
        redirigir('' . (isset($_GET['q']) ? '?q=' . urlencode((string) $_GET['q']) : ''));
        $q = trim((string) ($_GET['q'] ?? ''));
        $buscador = new Buscador();

        $this->vista('index', [
            'titulo'     => '¿A dónde dirijo mi asunto?',
            'q'          => $q,
            'resultados' => $q !== '' ? $buscador->buscar($q) : [],
            'metodo'     => $buscador->metodoActivo(),
        ]);
    }
}
