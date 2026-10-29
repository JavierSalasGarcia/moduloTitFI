<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Titulacion\Services\Captura;
use App\Modules\Titulacion\Services\Constancias;
use App\Modules\Titulacion\Services\SubdireccionFirma;

/** «Captura transitoria»: fichas de los trámites en papel hasta que se inaugure la plataforma, y su vínculo con el trámite en línea. */
final class CapturaController extends TitulacionBase
{
    private function soloPersonal(): void
    {
        if (!Constancias::puedeBuscar()) {
            $this->prohibido('Captura transitoria');
        }
        $this->privado();
    }

    public static function puedeCerrar(): bool
    {
        return Auth::tieneRol('admin') || SubdireccionFirma::esSubdirector() || SubdireccionFirma::esJefeTitulacion();
    }

    public function index(): void
    {
        $this->soloPersonal();
        $f = [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80), 'carrera' => (string) ($_GET['carrera'] ?? ''),
            'estado' => (string) ($_GET['estado'] ?? ''), 'faltantes' => (string) ($_GET['faltantes'] ?? ''),
        ];
        $r = Captura::listar($f);
        $editar = ctype_digit((string) ($_GET['editar'] ?? '')) ? Captura::obtener((int) $_GET['editar']) : null;
        $this->vista('captura', [
            'titulo' => 'Titulación · Captura transitoria', 'f' => $f, 'filas' => $r['filas'], 'total' => $r['total'],
            'resumen' => Captura::resumen(), 'editar' => $editar, 'cerrada' => Captura::cerrada(), 'puedeCerrar' => self::puedeCerrar(),
        ]);
    }

    public function guardar(): void
    {
        $this->soloPersonal();
        $id = ctype_digit($this->post('id')) ? (int) $this->post('id') : null;
        try {
            $id = Captura::guardar($id, $_POST, Auth::id());
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('titulacion/captura' . ($id ? '?editar=' . $id : ''));
        }
        Audit::record('titulacion::captura', 'correcto', 'Ficha de captura transitoria guardada ' . $id);
        $faltan = Captura::faltantes(Captura::obtener($id) ?? []);
        flash($faltan ? 'alerta' : 'exito', $faltan
            ? 'Ficha guardada; aún le falta: ' . implode(', ', array_map(static fn(string $k): string => Captura::CAMPOS[$k][0], $faltan)) . '.'
            : 'Ficha guardada y completa.');
        redirigir('titulacion/captura?q=' . rawurlencode($this->post('alumno')));
    }

    public function eliminar(string $id): void
    {
        $this->soloPersonal();
        try {
            $c = Captura::eliminar((int) $id);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('titulacion/captura');
        }
        Audit::record('titulacion::captura', 'correcto', 'Ficha de captura transitoria eliminada ' . (int) $id . ': ' . $c['alumno']);
        flash('exito', 'Ficha eliminada.');
        redirigir('titulacion/captura');
    }

    public function historial(string $id): void
    {
        $this->soloPersonal();
        try {
            $hid = Captura::pasarAlHistorial((int) $id, Auth::id());
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('titulacion/captura?editar=' . (int) $id);
        }
        Audit::record('titulacion::captura', 'correcto', 'Ficha ' . (int) $id . ' pasada al libro de actas ' . $hid);
        $c = Captura::obtener((int) $id);
        flash('exito', 'La ficha quedó en el historial del libro de actas; desde ahí se generan las constancias del director, codirector y sinodales.');
        redirigir('titulacion/constancias/historial?q=' . rawurlencode((string) ($c['cuenta'] ?: $c['alumno'])));
    }

    public function cerrar(): void
    {
        $this->soloPersonal();
        if (!self::puedeCerrar()) {
            $this->prohibido('Cerrar la captura transitoria');
        }
        $cerrar = $this->post('cerrar') === '1';
        Captura::cerrar($cerrar);
        Audit::record('titulacion::captura', 'correcto', $cerrar ? 'Captura transitoria cerrada' : 'Captura transitoria reabierta');
        flash('exito', $cerrar ? 'Captura cerrada: queda en solo lectura y cada ficha se vincula desde el expediente del alumno.' : 'Captura reabierta.');
        redirigir('titulacion/captura');
    }

    public function exportar(): void
    {
        $this->soloPersonal();
        Audit::record('titulacion::captura', 'correcto', 'Captura transitoria exportada');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="captura_titulacion_' . date('Ymd') . '.csv"');
        echo Captura::csv();
        exit;
    }

    /** Desde el expediente: vincula la ficha del alumno con su trámite en línea y precarga lo que falte. */
    public function vincular(string $id): void
    {
        $t = $this->tramite($id, true);
        try {
            $hechos = Captura::vincular($t, (int) $this->post('captura'), Auth::id());
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('titulacion/gestion/' . $t['id']);
        }
        Audit::record('titulacion::captura', 'correcto', 'Ficha ' . (int) $this->post('captura') . ' vinculada con el trámite ' . $t['id']);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Ficha vinculada' . ($hechos ? '; se precargaron ' . count($hechos) . ' datos del protocolo' : '') . '. Registra cada etapa ya cumplida con sus fechas.');
        redirigir('titulacion/gestion/' . $t['id']);
    }
}
