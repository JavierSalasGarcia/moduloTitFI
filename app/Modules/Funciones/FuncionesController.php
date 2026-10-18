<?php
declare(strict_types=1);

namespace App\Modules\Funciones;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

final class FuncionesController extends Controller
{
    public function index(): void
    {
        $depto = Database::una(
            'SELECT d.*, a.nombre AS area FROM departamentos d JOIN areas a ON a.id = d.area_id WHERE d.id = ?',
            [Auth::departamentoId()]
        );

        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT numero, descripcion FROM funciones WHERE departamento_id = ?';
        $params = [$depto['id']];
        if ($q !== '') {
            $sql .= ' AND descripcion LIKE ?';
            $params[] = "%{$q}%";
        }
        $funciones = Database::todas($sql . ' ORDER BY numero', $params);

        $this->vista('index', [
            'titulo'    => 'Mis Funciones',
            'depto'     => $depto,
            'funciones' => $funciones,
            'q'         => $q,
        ]);
    }
}
