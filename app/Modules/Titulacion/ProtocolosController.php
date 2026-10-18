<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Auth;
use App\Modules\Titulacion\Services\Calendario;
use App\Modules\Titulacion\Services\RevisionProtocolo;
use InvalidArgumentException;
use RuntimeException;

/** Personal operativo de Titulación: recibe protocolos, los turna a la comisión y vigila los plazos. */
final class ProtocolosController extends TitulacionBase
{
    private function personal(): void
    {
        if (!TitulacionAccess::esPersonal()) {
            $this->prohibido('Protocolos recibidos');
        }
        $this->privado();
    }

    public function index(): void
    {
        $this->personal();
        $estado = (string) ($_GET['estado'] ?? 'activos');
        $filtros = match ($estado) {
            'recibida' => ['estados' => ['recibida']],
            'todos' => [],
            default => ['estados' => ['recibida', 'en_comision', 'en_evaluacion', 'por_dictaminar']],
        };
        $rondas = RevisionRepository::rondas($filtros);
        foreach ($rondas as &$r) {
            $r['asignaciones'] = $r['estado'] === 'en_evaluacion' || $r['estado'] === 'por_dictaminar'
                ? $this->decorarAsignaciones(RevisionRepository::asignaciones((int) $r['id'])) : [];
        }
        unset($r);
        $this->vista('protocolos', [
            'titulo' => 'Titulación · Protocolos recibidos',
            'rondas' => $rondas, 'estado' => $estado,
            'carreras' => Catalogo::carreras(), 'modalidades' => Catalogo::modalidades(),
            'comisiones' => array_map(static fn(string $c): bool => RevisionRepository::comisionConfigurada($c), array_keys(Catalogo::carreras())),
        ]);
    }

    public function enviar(string $id): void
    {
        $this->personal();
        $ronda = ctype_digit($id) ? RevisionRepository::ronda((int) $id) : null;
        if ($ronda === null) {
            $this->noEncontrado();
        }
        try {
            RevisionProtocolo::enviarAComision($ronda, Auth::id(), $this->post('carrera', (string) $ronda['carrera']));
            flash('exito', 'Protocolo turnado a la comisión; sus encargados recibieron el aviso.');
        } catch (InvalidArgumentException | RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('titulacion/protocolos');
    }

    public function recordatorio(string $id, string $asig): void
    {
        $this->personal();
        $a = ctype_digit($asig) ? RevisionRepository::asignacion((int) $asig) : null;
        $ronda = ctype_digit($id) ? RevisionRepository::ronda((int) $id) : null;
        if ($a === null || $ronda === null || (int) $a['revision_id'] !== (int) $ronda['id']) {
            $this->noEncontrado();
        }
        try {
            $ok = RevisionProtocolo::recordatorio($a, $ronda);
            flash($ok ? 'exito' : 'error', $ok ? 'Recordatorio enviado a ' . $a['miembro_nombre'] . '.' : 'No se pudo enviar el correo (servicio de correo no disponible).');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('titulacion/protocolos');
    }

    public function documento(string $id): void
    {
        $ronda = ctype_digit($id) ? RevisionRepository::ronda((int) $id) : null;
        if ($ronda === null || empty($ronda['doc_ruta']) || !(TitulacionAccess::esPersonal() || in_array($ronda['carrera'], TitulacionAccess::carrerasEncargado(), true))) {
            $this->noEncontrado();
        }
        $this->enviarArchivo((string) $ronda['doc_ruta'], $ronda['folio'] . '_protocolo.pdf', true);
    }
}
