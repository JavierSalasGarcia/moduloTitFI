<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Titulacion\Services\ExpedienteFinal;

/** Verificación final del expediente y su envío a Servicios Escolares (solo personal de Titulación). */
final class ExpedienteFinalController extends TitulacionBase
{
    private function soloPersonal(): void
    {
        $this->privado();
        if (!TitulacionAccess::esPersonal()) {
            $this->prohibido('Expediente final de titulación');
        }
    }

    public function bandeja(): void
    {
        $this->soloPersonal();
        $this->vista('expedientes_bandeja', [
            'titulo' => 'Titulación · Expedientes para Servicios Escolares',
            'filas' => ExpedienteFinal::bandeja(),
            'lotes' => ExpedienteFinal::lotes(),
            'modalidades' => Catalogo::modalidades(),
        ]);
    }

    public function ver(string $id): void
    {
        $this->soloPersonal();
        $t = $this->tramite($id, true);
        $guardado = ExpedienteFinal::obtener((int) $t['id']);
        $items = ExpedienteFinal::evaluar($t, TitulacionRepository::documentosVigentes((int) $t['id']), $guardado);
        $this->vista('expediente_final', [
            'titulo' => 'Verificación final · ' . $t['folio'],
            't' => $t,
            'modalidad' => Catalogo::modalidad((string) $t['modalidad']),
            'exp' => $guardado,
            'items' => $items,
            'elegible' => ExpedienteFinal::elegible($t),
            'completo' => ExpedienteFinal::completo($items),
        ]);
    }

    private function cargar(string $id): array
    {
        $this->soloPersonal();
        $t = $this->tramite($id, true);
        if (!ExpedienteFinal::elegible($t)) {
            flash('error', 'El expediente se integra cuando la evaluación profesional concluyó con resultado favorable.');
            redirigir('titulacion/expedientes');
        }
        return $t;
    }

    public function guardar(string $id): void
    {
        $t = $this->cargar($id);
        $guardado = ExpedienteFinal::obtener((int) $t['id']);
        if ($guardado && $guardado['estado'] !== 'en_integracion') {
            flash('error', 'El expediente ya está verificado; reábrelo para cambiarlo.');
            redirigir('titulacion/expedientes/' . $t['id']);
        }
        $items = ExpedienteFinal::evaluar($t, TitulacionRepository::documentosVigentes((int) $t['id']), $guardado);
        ExpedienteFinal::guardar((int) $t['id'], $items, $_POST, $this->post('observaciones'));
        if ($this->post('accion') === 'verificar') {
            $items = ExpedienteFinal::evaluar($t, TitulacionRepository::documentosVigentes((int) $t['id']), ExpedienteFinal::obtener((int) $t['id']));
            if (!ExpedienteFinal::completo($items)) {
                flash('error', 'Aún faltan renglones por cubrir: ' . implode('; ', array_map(static fn(array $i): string => mb_substr($i['texto'], 0, 60), ExpedienteFinal::faltantes($items))));
                redirigir('titulacion/expedientes/' . $t['id']);
            }
            ExpedienteFinal::verificar((int) $t['id'], Auth::id());
            TitulacionRepository::evento((int) $t['id'], Auth::id(), 'expediente_verificado', 'El Departamento de Titulación verificó el expediente final con la lista de integración.', true);
            Audit::record('titulacion::expediente_final', 'verificado', 'Trámite ' . $t['id']);
            flash('exito', 'Expediente verificado. Ya puede incluirse en el envío a Servicios Escolares.');
        } else {
            flash('exito', 'Se guardó la lista de verificación.');
        }
        redirigir('titulacion/expedientes/' . $t['id']);
    }

    public function reabrir(string $id): void
    {
        $t = $this->cargar($id);
        $g = ExpedienteFinal::obtener((int) $t['id']);
        if (!$g || $g['estado'] !== 'verificado') {
            flash('error', 'Solo se puede reabrir un expediente verificado que aún no se envía.');
        } else {
            ExpedienteFinal::reabrir((int) $t['id']);
            TitulacionRepository::evento((int) $t['id'], Auth::id(), 'expediente_reabierto', 'Se reabrió la verificación final del expediente.', false);
            Audit::record('titulacion::expediente_final', 'reabierto', 'Trámite ' . $t['id']);
            flash('exito', 'Verificación reabierta.');
        }
        redirigir('titulacion/expedientes/' . $t['id']);
    }

    /** Formato de integración llenado (vista previa en PDF). */
    public function formato(string $id): void
    {
        $t = $this->cargar($id);
        $g = ExpedienteFinal::obtener((int) $t['id']);
        $items = ExpedienteFinal::evaluar($t, TitulacionRepository::documentosVigentes((int) $t['id']), $g);
        $bytes = ExpedienteFinal::pdfFormato($t, $items, (string) ($g['verificador'] ?? Auth::usuario()['nombre'] ?? ''));
        $this->enviarBytes($bytes, 'Integracion_expediente_' . $t['folio'] . '.pdf', 'application/pdf');
    }

    /** Exporta como carpeta comprimida los expedientes verificados (uno o varios) y los marca como enviados. */
    public function exportar(): void
    {
        $this->soloPersonal();
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])))));
        if (!$ids) {
            flash('error', 'Elige al menos un expediente verificado.');
            redirigir('titulacion/expedientes');
        }
        $validos = [];
        foreach ($ids as $id) {
            $g = ExpedienteFinal::obtener($id);
            if ($g && in_array($g['estado'], ['verificado', 'enviado'], true)) {
                $validos[] = $id;
            }
        }
        if (!$validos) {
            flash('error', 'Ninguno de los expedientes elegidos está verificado.');
            redirigir('titulacion/expedientes');
        }
        [$bytes, $folios] = ExpedienteFinal::zip($validos, (string) (Auth::usuario()['nombre'] ?? ''));
        $lote = ExpedienteFinal::registrarEnvio($validos, $folios, hash('sha256', $bytes), Auth::id());
        foreach ($validos as $id) {
            TitulacionRepository::evento($id, Auth::id(), 'expediente_enviado', 'El expediente se exportó para su envío a Servicios Escolares (Administración Central), lote ' . $lote . '.', true);
        }
        Audit::record('titulacion::expediente_final', 'exportado', 'Lote ' . $lote . ': ' . implode(', ', $folios));
        $this->enviarBytes($bytes, 'Expedientes_Servicios_Escolares_' . date('Ymd') . '_lote' . $lote . '.zip', 'application/zip');
    }
}
