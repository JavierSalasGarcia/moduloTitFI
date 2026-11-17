<?php
declare(strict_types=1);

namespace App\Modules\Constancias;

use App\Core\Audit;
use App\Modules\Constancias\Services\Indice;

/** «Constancias emitidas»: índice unificado agrupado por departamento emisor y descarga con permiso. */
final class IndiceController extends ConstanciasBase
{
    public function index(): void
    {
        $this->privado();
        $alcance = ConstanciasAccess::alcance();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $anio = ctype_digit((string) ($_GET['anio'] ?? '')) ? (int) $_GET['anio'] : null;
        if ($anio !== null && ($anio < 2000 || $anio > 2100)) {
            $anio = null;
        }
        $r = Indice::listar($alcance, $q, $anio);
        $this->vista('index', [
            'titulo' => 'Constancias emitidas',
            'grupos' => Indice::porDepartamento($r['filas']),
            'total' => count($r['filas']),
            'recortado' => $r['recortado'],
            'q' => $q, 'anio' => $anio, 'anios' => Indice::anios(),
            'alcance' => $alcance,
            'puedeSolicitar' => ConstanciasAccess::puedeSolicitar(),
        ]);
    }

    /** PDF firmado de un renglón del índice; 404 si la sesión no lo ve o aún no está firmado. */
    public function descargar(string $fuente, string $id): void
    {
        $num = self::id($id);
        $fila = $num !== null ? Indice::uno(ConstanciasAccess::alcance(), $fuente, $num) : null;
        $archivo = $fila ? Indice::archivo($fila) : null;
        if ($archivo === null) {
            $this->noEncontrado();
        }
        Audit::record('constancias::descargar', 'atendido', $fuente . ' ' . $num);
        $this->enviarArchivo($archivo, 'constancia_' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $fila['folio']) . '.pdf');
    }
}
