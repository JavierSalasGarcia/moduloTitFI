<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Modules\Titulacion\Services\Ruta;

/** Ruta del trámite para todos los que participan en él, con la descarga de cada documento. */
final class RutaController extends TitulacionBase
{
    private function cargar(string $id): array
    {
        $t = ctype_digit($id) ? TitulacionRepository::obtener((int) $id) : null;
        if ($t === null || !TitulacionAccess::participa($t)) {
            $this->noEncontrado();
        }
        return $t;
    }

    public function ver(string $id): void
    {
        $this->privado();
        $t = $this->cargar($id);
        [$flujo, $docs] = $this->evaluar($t);
        $this->vista('ruta', [
            'titulo' => 'Ruta del trámite ' . $t['folio'],
            't' => $t,
            'modalidad' => Catalogo::modalidad((string) $t['modalidad']),
            'pasos' => Ruta::pasos($t, $flujo, $docs),
            'esPersonal' => TitulacionAccess::esPersonal(),
            'esDueno' => TitulacionAccess::esDueno($t),
        ]);
    }

    public function documento(string $id, string $doc): void
    {
        $t = $this->cargar($id);
        $d = ctype_digit($doc) ? TitulacionRepository::documento((int) $doc) : null;
        if ($d === null || (int) $d['tramite_id'] !== (int) $t['id'] || empty($d['ruta'])) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::ruta_documento', 'atendido', 'Documento ' . (int) $d['id']);
        $this->enviarArchivo((string) ($d['subdir_ruta'] ?: $d['ruta']), $t['folio'] . '_' . $d['clave'] . '.pdf', true);
    }

    public function dictamen(string $id, string $ronda): void
    {
        $t = $this->cargar($id);
        $r = ctype_digit($ronda) ? RevisionRepository::ronda((int) $ronda) : null;
        if ($r === null || (int) $r['tramite_id'] !== (int) $t['id'] || empty($r['dictamen_ruta'])) {
            $this->noEncontrado();
        }
        $this->enviarArchivo((string) $r['dictamen_ruta'], $t['folio'] . '_dictamen_' . $r['numero'] . '.pdf', true);
    }
}
