<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Auth;
use App\Modules\Titulacion\Services\Calendario;
use App\Modules\Titulacion\Services\FirmaElectronica;
use App\Modules\Titulacion\Services\Mensajes;
use App\Modules\Titulacion\Services\Oficios;
use App\Modules\Titulacion\Services\RevisionProtocolo;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Responsable del comité de revisión: envía cada protocolo a los revisores, vigila los plazos y resuelve cada vuelta. */
final class ComisionController extends TitulacionBase
{
    private function encargado(): array
    {
        $carreras = TitulacionAccess::carrerasEncargado();
        if (!$carreras) {
            $this->prohibido('Comisión de revisión de protocolos');
        }
        $this->privado();
        return $carreras;
    }

    /** Carga la ronda solo si pertenece a una comisión que el usuario encabeza. */
    private function ronda(string $id): array
    {
        $carreras = $this->encargado();
        $r = ctype_digit($id) ? RevisionRepository::ronda((int) $id) : null;
        if ($r === null || !in_array($r['carrera'], $carreras, true)) {
            $this->noEncontrado();
        }
        return $r;
    }

    public function index(): void
    {
        $carreras = $this->encargado();
        $rondas = RevisionRepository::rondas(['carreras' => $carreras, 'estados' => ['en_comision', 'en_evaluacion', 'por_dictaminar', 'dictaminada']]);
        foreach ($rondas as &$r) {
            $r['asignaciones'] = $this->decorarAsignaciones(RevisionRepository::asignaciones((int) $r['id']));
        }
        unset($r);
        // Tablero tipo OJS: revisiones pendientes por color del semáforo.
        $estado = ['verde' => 0, 'rojo' => 0, 'listo' => 0];
        foreach ($rondas as $r) {
            if (in_array($r['estado'], ['en_evaluacion', 'por_dictaminar'], true)) {
                foreach ($r['asignaciones'] as $a) {
                    if (isset($estado[$a['semaforo']])) {
                        $estado[$a['semaforo']]++;
                    }
                }
            }
        }
        $this->vista('comision', [
            'titulo' => 'Titulación · Mi comité de revisión', 'rondas' => $rondas, 'carreras' => Catalogo::carreras(), 'misCarreras' => $carreras,
            'resumen' => $estado + [
                'por_enviar' => count(array_filter($rondas, static fn(array $r): bool => $r['estado'] === 'en_comision')),
                'por_dictaminar' => count(array_filter($rondas, static fn(array $r): bool => $r['estado'] === 'por_dictaminar')),
            ],
        ]);
    }

    public function ver(string $id): void
    {
        $r = $this->ronda($id);
        $asigs = $this->decorarAsignaciones(RevisionRepository::asignaciones((int) $r['id']));
        $u = Auth::usuario();
        $plantilla = Mensajes::plantilla(TitulacionAccess::correoSesion());
        $tramite = TitulacionRepository::obtener((int) $r['tramite_id']);
        $this->vista('comision_ronda', [
            'puedeDictaminar' => RevisionProtocolo::puedeDictaminar($r),
            'revisiones' => RevisionProtocolo::revisiones((int) $r['id']),
            'plantilla' => $plantilla,
            'marcas' => Mensajes::valores($r, $u ?? []),
            'obsOficio' => $tramite ? Oficios::observacionesAprobacion($tramite) : 'Ninguna.',
            'anteriores' => (int) $r['numero'] > 1 ? RevisionRepository::revisoresAnteriores((int) $r['tramite_id'], (int) $r['numero']) : [],
            'maxRevisores' => RevisionProtocolo::revisoresPorProtocolo(),
            'mensajes' => \App\Core\Database::todas('SELECT * FROM tit_mensajes WHERE revision_id IN (SELECT id FROM tit_revisiones WHERE tramite_id = ?) ORDER BY id DESC', [(int) $r['tramite_id']]),
            'titulo' => 'Protocolo ' . $r['folio'],
            'r' => $r,
            'asignaciones' => $asigs,
            'miembros' => RevisionRepository::miembros((string) $r['carrera']),
            'asignados' => array_column(array_filter($asigs, static fn(array $a): bool => $a['estado'] !== 'cancelada'), 'miembro_id'),
            'dias' => Calendario::diasPlazo(),
            'limite' => Calendario::agregarHabiles(new \DateTimeImmutable('today'), Calendario::diasPlazo())->format('d/m/Y'),
            'nivelFirma' => FirmaElectronica::nivelRevisores(),
            'carreras' => Catalogo::carreras(),
        ]);
    }

    public function asignar(string $id): void
    {
        $r = $this->ronda($id);
        $ids = array_filter((array) ($_POST['miembros'] ?? []), 'ctype_digit');
        if (!$ids) {
            flash('error', 'Elige al menos a un integrante de la comisión.');
            redirigir('titulacion/comision/' . $r['id']);
        }
        try {
            $res = RevisionProtocolo::asignar($r, array_map('intval', $ids), Auth::id());
            $msg = $res['asignados'] . ' evaluador(es) notificado(s) por correo.';
            if ($res['sin_correo'] > 0) {
                $msg .= ' ' . $res['sin_correo'] . ' correo(s) no pudieron enviarse (servicio de correo no disponible); usa «Recordatorio» más tarde.';
            }
            if ($res['omitidos'] > 0) {
                $msg .= ' ' . $res['omitidos'] . ' ya estaban asignados o no pertenecen a la comisión.';
            }
            flash($res['asignados'] > 0 ? 'exito' : 'error', $res['asignados'] > 0 ? $msg : 'No se asignó a nadie: ' . $msg);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('titulacion/comision/' . $r['id']);
    }

    public function recordatorio(string $id, string $asig): void
    {
        $r = $this->ronda($id);
        $a = ctype_digit($asig) ? RevisionRepository::asignacion((int) $asig) : null;
        if ($a === null || (int) $a['revision_id'] !== (int) $r['id']) {
            $this->noEncontrado();
        }
        try {
            $ok = RevisionProtocolo::recordatorio($a, $r);
            flash($ok ? 'exito' : 'error', $ok ? 'Recordatorio enviado a ' . $a['miembro_nombre'] . '.' : 'No se pudo enviar el correo (servicio de correo no disponible).');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('titulacion/comision/' . $r['id']);
    }

    public function cancelar(string $id, string $asig): void
    {
        $r = $this->ronda($id);
        $a = ctype_digit($asig) ? RevisionRepository::asignacion((int) $asig) : null;
        if ($a === null || (int) $a['revision_id'] !== (int) $r['id'] || $a['estado'] === 'firmada') {
            $this->noEncontrado();
        }
        RevisionRepository::cancelarAsignacion((int) $a['id']);
        $pendientes = array_filter(RevisionRepository::asignaciones((int) $r['id']), static fn(array $x): bool => in_array($x['estado'], ['pendiente', 'borrador'], true));
        $firmadas = array_filter(RevisionRepository::asignaciones((int) $r['id']), static fn(array $x): bool => $x['estado'] === 'firmada');
        if (!$pendientes && $firmadas && $r['estado'] === 'en_evaluacion') {
            RevisionRepository::actualizarRonda((int) $r['id'], ['estado' => 'por_dictaminar']);
        }
        flash('exito', 'Se retiró la asignación de ' . $a['miembro_nombre'] . '; su enlace dejó de funcionar.');
        redirigir('titulacion/comision/' . $r['id']);
    }

    public function dictaminar(string $id): void
    {
        $r = $this->ronda($id);
        $u = Auth::usuario();
        try {
            if (\App\Core\AccessControl::pending()) {
                throw new RuntimeException('Verifica tu correo para poder firmar.');
            }
            RevisionProtocolo::dictaminar($r, [
                'dictamen' => $this->post('dictamen'), 'requiere_coord' => $this->post('requiere_coord', 'no'),
                'revisores' => is_array($_POST['revisor'] ?? null) ? array_map('strval', $_POST['revisor']) : [],
                'propias' => $this->post('propias'), 'asunto' => $this->post('asunto'), 'mensaje' => (string) ($_POST['mensaje'] ?? ''),
                'obs_oficio' => (string) ($_POST['obs_oficio'] ?? ''),
            ], $u, $this->post('codigo_2fa'));
            $this->sincronizar((int) $r['tramite_id']);
            flash('exito', $this->post('dictamen') === 'aprobado'
                ? 'Protocolo aprobado. Se avisó al pasante y el oficio de aprobación pasó a firmas (Vo. Bo. de la Jefatura y Subdirección).'
                : 'Dictamen enviado al pasante; lo verá en Titulación → Mensajes y podrá subir su versión corregida.');
        } catch (InvalidArgumentException | RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Titulación: error al dictaminar: ' . $e->getMessage());
            flash('error', 'No se pudo emitir el dictamen. Intenta de nuevo.');
        }
        redirigir('titulacion/comision/' . $r['id']);
    }

    public function revisionPdf(string $id, string $asig): void
    {
        $r = $this->ronda($id);
        $a = ctype_digit($asig) ? RevisionRepository::asignacion((int) $asig) : null;
        if ($a === null || (int) $a['revision_id'] !== (int) $r['id'] || empty($a['pdf_ruta'])) {
            $this->noEncontrado();
        }
        $this->enviarArchivo((string) $a['pdf_ruta'], $r['folio'] . '_revision.pdf', true);
    }

    /** Guarda o restablece la plantilla del mensaje de cada vuelta de quien tiene la sesión. */
    public function plantilla(): void
    {
        $this->encargado();
        $volver = (string) ($_POST['volver'] ?? '');
        $volver = preg_match('#^titulacion/comision/\d+$#D', $volver) ? $volver : 'titulacion/comision';
        if ($this->post('accion') === 'restablecer') {
            Mensajes::restablecerPlantilla(TitulacionAccess::correoSesion());
            flash('exito', 'Se restableció la plantilla original.');
        } else {
            $asunto = $this->post('asunto');
            $cuerpo = trim(str_replace("\r", '', (string) ($_POST['cuerpo'] ?? '')));
            if ($asunto === '' || mb_strlen($cuerpo) < 20 || !str_contains($cuerpo, '{observaciones}')) {
                flash('error', 'La plantilla necesita asunto y cuerpo, y debe incluir {observaciones} donde van las observaciones de los revisores.');
            } else {
                Mensajes::guardarPlantilla(TitulacionAccess::correoSesion(), $asunto, $cuerpo);
                flash('exito', 'Plantilla guardada; se usará en tus próximos mensajes.');
            }
        }
        redirigir($volver . '#mensaje');
    }
}
