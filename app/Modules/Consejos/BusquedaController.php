<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Core\Database;
use App\Modules\Consejos\Services\Busqueda;

/** Buscador del Subdirector Académico (y quien gestiona Consejos) en las solicitudes, antecedentes y acuerdos de todas las actas. */
final class BusquedaController extends ConsejosBase
{
    public function index(): void
    {
        $this->gestor();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 200);
        $modo = ($_GET['modo'] ?? '') === 'palabras' ? 'palabras' : 'frase';
        $campo = array_key_exists($_GET['campo'] ?? '', Busqueda::CAMPOS) ? (string) $_GET['campo'] : 'todo';
        $reunion = ctype_digit((string) ($_GET['reunion'] ?? '')) ? (int) $_GET['reunion'] : null;
        $grupo = (string) ($_GET['grupo'] ?? '');
        $res = ['filas' => [], 'total' => 0, 'recortado' => false];
        if ($q !== '') {
            $res = Busqueda::buscar($q, $modo, $campo, $reunion, $grupo);
            Audit::record('consejos::busqueda', 'correcto', mb_substr($q, 0, 80) . ' → ' . $res['total']);
        }
        $this->vista('buscar', [
            'titulo' => 'Buscar en las actas', 'q' => $q, 'modo' => $modo, 'campo' => $campo, 'reunionSel' => $reunion, 'grupo' => $grupo,
            'reuniones' => Database::todas('SELECT id, etiqueta FROM consejos_reuniones ORDER BY id DESC'), 'res' => $res,
        ]);
    }
}
