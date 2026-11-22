<?php
declare(strict_types=1);

namespace App\Modules\Constancias;

use App\Core\Audit;
use App\Modules\Constancias\Services\Indice;

/** «Constancias firmadas»: lista única de las constancias ya firmadas, ordenable por emisión, firma o departamento, y descarga con permiso. */
final class IndiceController extends ConstanciasBase
{
    /** Cada persona ve solo las constancias que se le extendieron, no las que firmó, solicitó o emitió su departamento. */
    private static function alcancePropio(): array
    {
        return ['todas' => false, 'deptos' => [], 'solo_recibidas' => true] + ConstanciasAccess::alcance();
    }

    public function index(): void
    {
        $this->privado();
        $alcance = self::alcancePropio();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $anio = ctype_digit((string) ($_GET['anio'] ?? '')) ? (int) $_GET['anio'] : null;
        if ($anio !== null && ($anio < 2000 || $anio > 2100)) {
            $anio = null;
        }
        $orden = in_array($_GET['orden'] ?? '', ['emision', 'firma', 'depto'], true) ? (string) $_GET['orden'] : 'firma';
        $dir = ($_GET['dir'] ?? '') === 'asc' ? 'asc' : 'desc';
        $r = Indice::listar($alcance, $q, $anio, $orden, $dir);
        $this->vista('index', [
            'titulo' => 'Constancias firmadas',
            'filas' => $r['filas'],
            'orden' => $orden, 'dir' => $dir,
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
        $fila = $num !== null ? Indice::uno(self::alcancePropio(), $fuente, $num) : null;
        $archivo = $fila ? Indice::archivo($fila) : null;
        if ($archivo === null) {
            $this->noEncontrado();
        }
        Audit::record('constancias::descargar', 'atendido', $fuente . ' ' . $num);
        $this->enviarArchivo($archivo, 'constancia_' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $fila['folio']) . '.pdf');
    }
}
