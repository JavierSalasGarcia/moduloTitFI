<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Services\Mensajes;

/** Menús personales: «Titulación → Mensajes» y «Estado» del pasante, y «Constancias → Protocolos» de quien revisa. */
final class PanelController extends TitulacionBase
{
    public function mensajes(): void
    {
        $this->privado();
        $this->vista('mensajes', [
            'titulo' => 'Titulación · Mensajes',
            'mensajes' => Mensajes::delAlumno(Auth::id()),
            'abierto' => null,
        ]);
    }

    public function mensaje(string $id): void
    {
        $this->privado();
        $m = ctype_digit($id) ? Mensajes::mensaje((int) $id) : null;
        if ($m === null || ((int) $m['alumno_id'] !== Auth::id() && !TitulacionAccess::esPersonal())) {
            $this->noEncontrado();
        }
        if ((int) $m['alumno_id'] === Auth::id()) {
            Mensajes::marcarLeido((int) $m['id']);
        }
        $this->vista('mensajes', [
            'titulo' => 'Titulación · Mensajes',
            'mensajes' => Mensajes::delAlumno((int) $m['alumno_id']),
            'abierto' => $m,
            'ronda' => $m['revision_id'] ? RevisionRepository::ronda((int) $m['revision_id']) : null,
        ]);
    }

    public function estado(): void
    {
        $this->privado();
        $tramites = [];
        foreach (TitulacionRepository::delAlumno(Auth::id()) as $t) {
            $t = TitulacionRepository::obtener((int) $t['id']);
            [$flujo, $docs] = $this->evaluar($t);
            $actual = null;
            foreach ($flujo['etapas'] as $e) {
                if ($e['situacion'] === 'actual') {
                    $actual = $e;
                }
            }
            $ronda = RevisionRepository::rondasDeTramite((int) $t['id'])[0] ?? null;
            $oficio = $docs['protocolo|oficio_aprobacion_tema'] ?? null;
            $firmas = $oficio ? Database::todas("SELECT rol_firma, estado, firmado_en FROM tit_firmas_subdireccion WHERE documento_id = ? AND estado <> 'cancelado' ORDER BY id", [(int) $oficio['id']]) : [];
            $tramites[] = [
                't' => $t, 'flujo' => $flujo, 'actual' => $actual, 'ronda' => $ronda, 'oficio' => $oficio, 'firmas' => $firmas,
                'noLeidos' => (int) Database::valor('SELECT COUNT(*) FROM tit_mensajes WHERE tramite_id = ? AND leido_en IS NULL', [(int) $t['id']]),
            ];
        }
        $this->vista('estado', ['titulo' => 'Titulación · Estado', 'tramites' => $tramites]);
    }

    // ----------------------------------------------------------------- constancias

    private function misConstancias(): array
    {
        return Database::todas(
            'SELECT c.*, t.folio, t.titulo, t.modalidad, u.nombre AS alumno_nombre, d.subdir_en, d.subdir_ruta
             FROM tit_constancias c JOIN tit_tramites t ON t.id = c.tramite_id JOIN usuarios u ON u.id = t.alumno_id
             LEFT JOIN tit_documentos d ON d.id = c.documento_id
             WHERE c.email = ? ORDER BY c.id DESC',
            [TitulacionAccess::correoSesion()]
        );
    }

    public function constancias(): void
    {
        $this->privado();
        $email = TitulacionAccess::correoSesion();
        $this->vista('constancias', [
            'titulo' => 'Constancias · Protocolos',
            'constancias' => $this->misConstancias(),
            // Revisiones firmadas cuyo protocolo aún no se aprueba: su constancia se emitirá al aprobarse.
            'enCurso' => $email === '' ? [] : Database::todas(
                "SELECT DISTINCT t.folio, t.titulo, u.nombre AS alumno_nombre FROM tit_revision_asignaciones a
                 JOIN tit_comision_miembros m ON m.id = a.miembro_id JOIN tit_revisiones r ON r.id = a.revision_id
                 JOIN tit_tramites t ON t.id = r.tramite_id JOIN usuarios u ON u.id = t.alumno_id
                 WHERE m.email = ? AND a.estado = 'firmada' AND t.estado = 'abierto'
                   AND NOT EXISTS (SELECT 1 FROM tit_constancias c WHERE c.tramite_id = t.id AND c.email = m.email)",
                [$email]
            ),
        ]);
    }

    public function constancia(string $id): void
    {
        foreach ($this->misConstancias() as $c) {
            if ((string) $c['id'] === $id && !empty($c['subdir_ruta'])) {
                Audit::record('titulacion::constancia', 'atendido', 'Constancia ' . (int) $c['id']);
                $this->enviarArchivo((string) $c['subdir_ruta'], 'constancia_revision_' . $c['folio'] . '.pdf', true);
            }
        }
        $this->noEncontrado();
    }
}
