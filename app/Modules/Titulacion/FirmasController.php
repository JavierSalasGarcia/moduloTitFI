<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Services\SubdireccionFirma;
use Throwable;

/** Firmas de la Subdirección: bandeja del Subdirector y su asistente, asistentes y piloto automático. */
final class FirmasController extends TitulacionBase
{
    private function soloFirmantes(): void
    {
        if (!SubdireccionFirma::puedeFirmar()) {
            $this->prohibido('Firmas de la Subdirección');
        }
    }

    private function soloSubdirector(): void
    {
        if (!SubdireccionFirma::esSubdirector()) {
            $this->prohibido('Administración de firmas reservada al Subdirector');
        }
    }

    public function index(): void
    {
        $this->privado();
        $this->soloFirmantes();
        SubdireccionFirma::procesarPiloto();
        $esSub = SubdireccionFirma::esSubdirector();
        $this->vista('firmas', [
            'titulo' => 'Titulación · Firmas de la Subdirección',
            'esSubdirector' => $esSub,
            'pendientes' => SubdireccionFirma::pendientes(),
            'bitacora' => SubdireccionFirma::bitacora(),
            'asistentes' => $esSub ? Database::todas('SELECT * FROM tit_asistentes ORDER BY activo DESC, nombre') : [],
            'piloto' => SubdireccionFirma::pilotoUltimo(),
            'pilotoVigente' => SubdireccionFirma::pilotoVigente(),
            'formatos' => SubdireccionFirma::formatosFirmables(),
        ]);
    }

    public function firmar(): void
    {
        $this->soloFirmantes();
        $ids = array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])));
        if (!empty($_POST['todas'])) {
            $ids = array_map(static fn(array $f): int => (int) $f['id'], SubdireccionFirma::pendientes());
        }
        if (!$ids) {
            flash('error', 'Selecciona al menos un documento.');
            redirigir('titulacion/firmas');
        }
        $capacidad = SubdireccionFirma::esSubdirector() ? 'personal' : 'delegada';
        $ok = 0;
        $fallos = 0;
        foreach ($ids as $id) {
            try {
                SubdireccionFirma::firmar($id, $capacidad, Auth::id());
                $ok++;
            } catch (Throwable $e) {
                $fallos++;
                error_log('Titulación: firma #' . $id . ': ' . $e->getMessage());
            }
        }
        flash($fallos ? 'error' : 'exito', "Documentos firmados: $ok." . ($fallos ? " No se pudieron firmar: $fallos." : ''));
        redirigir('titulacion/firmas');
    }

    public function descargar(string $id): void
    {
        $this->privado();
        $this->soloFirmantes();
        $f = ctype_digit($id) ? Database::una("SELECT * FROM tit_firmas_subdireccion WHERE id = ? AND estado = 'firmado'", [(int) $id]) : null;
        if (!$f) {
            $this->noEncontrado();
        }
        $this->enviarArchivo((string) $f['ruta_firmado'], $f['formato'] . '_firmado.pdf', true);
    }

    public function vistaPrevia(string $id): void
    {
        $this->privado();
        $this->soloFirmantes();
        $f = ctype_digit($id) ? Database::una("SELECT * FROM tit_firmas_subdireccion WHERE id = ? AND estado = 'pendiente'", [(int) $id]) : null;
        $t = $f ? TitulacionRepository::obtener((int) $f['tramite_id']) : null;
        if (!$f || !$t) {
            $this->noEncontrado();
        }
        try {
            [$bytes, $nombre, $mime] = Services\Formatos::generar((string) $f['formato'], $t);
        } catch (Throwable) {
            $this->noEncontrado();
        }
        $this->enviarBytes($bytes, $nombre, $mime);
    }

    // ----------------------------------------------------------------- asistentes (solo Subdirector)

    public function agregarAsistente(): void
    {
        $this->soloSubdirector();
        $email = strtolower(trim($this->post('email')));
        $nombre = mb_substr(trim($this->post('nombre')), 0, 160);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($nombre) < 3) {
            flash('error', 'Indica nombre y un correo válido.');
            redirigir('titulacion/firmas#asistentes');
        }
        Database::ejecutar(
            'INSERT INTO tit_asistentes (email, nombre, activo, creado_por) VALUES (?, ?, 1, ?)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), activo = 1',
            [$email, $nombre, Auth::id()]
        );
        Audit::record('titulacion::subdireccion', 'asistente_alta', $email);
        flash('exito', 'Asistente registrado. Para firmar deberá entrar con una cuenta verificada con ese correo.');
        redirigir('titulacion/firmas#asistentes');
    }

    public function quitarAsistente(string $id): void
    {
        $this->soloSubdirector();
        $a = ctype_digit($id) ? Database::una('SELECT * FROM tit_asistentes WHERE id = ?', [(int) $id]) : null;
        if (!$a) {
            $this->noEncontrado();
        }
        Database::ejecutar('UPDATE tit_asistentes SET activo = 1 - activo WHERE id = ?', [(int) $a['id']]);
        Audit::record('titulacion::subdireccion', $a['activo'] ? 'asistente_baja' : 'asistente_alta', $a['email']);
        flash('exito', $a['activo'] ? 'Asistente desactivado.' : 'Asistente reactivado.');
        redirigir('titulacion/firmas#asistentes');
    }

    // ----------------------------------------------------------------- piloto automático (solo Subdirector)

    public function guardarPiloto(): void
    {
        $this->soloSubdirector();
        $inicio = strtotime(str_replace('T', ' ', $this->post('inicio')));
        $fin = strtotime(str_replace('T', ' ', $this->post('fin')));
        $retraso = (int) $this->post('retraso');
        $validos = array_keys(SubdireccionFirma::formatosFirmables());
        $formatos = array_values(array_intersect($validos, array_map('strval', (array) ($_POST['formatos'] ?? []))));
        if (!$inicio || !$fin || $fin <= $inicio || $fin - $inicio > 90 * 86400) {
            flash('error', 'Indica un periodo válido de máximo 90 días.');
            redirigir('titulacion/firmas#piloto');
        }
        if ($retraso < 5 || $retraso > 1440 || !$formatos) {
            flash('error', 'El retraso va de 5 a 1440 minutos y debes elegir al menos un formato.');
            redirigir('titulacion/firmas#piloto');
        }
        Database::ejecutar('UPDATE tit_piloto SET activo = 0, apagado_en = ? WHERE activo = 1', [date('Y-m-d H:i:s')]);
        Database::ejecutar(
            'INSERT INTO tit_piloto (inicio, fin, retraso_min, formatos, activo, creado_por) VALUES (?, ?, ?, ?, 1, ?)',
            [date('Y-m-d H:i:s', $inicio), date('Y-m-d H:i:s', $fin), $retraso, json_encode($formatos), Auth::id()]
        );
        Audit::record('titulacion::subdireccion', 'piloto_programado', date('c', $inicio) . ' → ' . date('c', $fin) . ' · ' . $retraso . ' min · ' . implode(',', $formatos));
        flash('exito', 'Piloto automático programado. Cada firma saldrá con la leyenda «Firma automática programada».');
        redirigir('titulacion/firmas#piloto');
    }

    public function apagarPiloto(): void
    {
        $this->soloSubdirector();
        Database::ejecutar('UPDATE tit_piloto SET activo = 0, apagado_en = ? WHERE activo = 1', [date('Y-m-d H:i:s')]);
        Audit::record('titulacion::subdireccion', 'piloto_apagado', '');
        flash('exito', 'Piloto automático apagado.');
        redirigir('titulacion/firmas#piloto');
    }
}
