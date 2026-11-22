<?php

declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Consejos\Services\Adjuntos;

/** Documentos adjuntos con descripción de las solicitudes y de los casos (docs/CONSEJOS.md). */
final class AdjuntoController extends ConsejosBase
{
    private function adjunto(string $id): array
    {
        $this->privado();
        $n = self::id($id);
        $a = $n ? Database::una('SELECT * FROM consejos_adjuntos WHERE id = ?', [$n]) : null;
        if (!$a || !$this->puedeVer($a)) {
            $this->noEncontrado();
        }
        return $a;
    }

    /** Gestores siempre; quien solicita y la coordinación a la que se dirige, solo lo de su solicitud. */
    private function puedeVer(array $a): bool
    {
        if (ConsejosAccess::esGestor()) {
            return true;
        }
        $uid = Auth::id();
        if ($uid === null || empty($a['solicitud_id'])) {
            return false;
        }
        $s = Database::una('SELECT solicitante_id, coordinador_id FROM consejos_solicitudes WHERE id = ?', [(int) $a['solicitud_id']]);
        return $s && ($uid === (int) $s['solicitante_id'] || $uid === (int) $s['coordinador_id']);
    }

    public function ver(string $id): void
    {
        $a = $this->adjunto($id);
        $abs = self::rutaAbsoluta((string) $a['ruta']);
        if (!$abs) {
            $this->noEncontrado();
        }
        Audit::record('consejos::descarga', 'correcto', 'Adjunto #' . $a['id']);
        $visible = Adjuntos::esVisible((string) $a['extension']);
        $this->privado();
        if (($_GET['marco'] ?? '') === '1' && $visible) {
            header('X-Frame-Options: SAMEORIGIN');
            header("Content-Security-Policy: frame-ancestors 'self'");
        }
        header('Content-Type: ' . Adjuntos::mime((string) $a['extension']));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($visible && ($_GET['descargar'] ?? '') !== '1' ? 'inline' : 'attachment') . '; filename="' . (preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $a['nombre']) ?: 'adjunto') . '"');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }

    public function subirSolicitud(string $id): void
    {
        $this->privado();
        $n = self::id($id);
        $s = $n ? Database::una('SELECT * FROM consejos_solicitudes WHERE id = ?', [$n]) : null;
        if (!$s || (int) $s['solicitante_id'] !== (int) Auth::id()) {
            $this->noEncontrado();
        }
        if (!in_array($s['estado'], ['borrador', 'devuelta'], true)) {
            flash('error', 'Los anexos se agregan mientras la solicitud es borrador o está devuelta.');
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        $r = Adjuntos::guardarDelFormulario(['solicitud_id' => (int) $s['id']]);
        $this->avisarAdjuntos($r);
        if ($r['guardados'] === 0 && !$r['errores']) {
            flash('error', 'Elige un archivo y escribe de qué es.');
        }
        Audit::record('consejos::solicitud', 'correcto', 'Agrega ' . $r['guardados'] . ' anexo(s) a ' . $s['folio']);
        redirigir('consejos/solicitudes/' . $s['id']);
    }

    public function quitar(string $id): void
    {
        $adj = $this->adjunto($id);
        if (!empty($adj['solicitud_id']) && empty($adj['asunto_id'])) {
            $s = Database::una('SELECT * FROM consejos_solicitudes WHERE id = ?', [(int) $adj['solicitud_id']]);
            if (!$s || (int) $s['solicitante_id'] !== (int) Auth::id() || !in_array($s['estado'], ['borrador', 'devuelta'], true)) {
                flash('error', 'Este anexo ya no se puede quitar.');
                redirigir('consejos/solicitudes/' . (int) $adj['solicitud_id']);
            }
            Adjuntos::quitar($adj);
            flash('exito', 'Anexo retirado.');
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        $this->gestor();
        $a = $this->asunto((string) $adj['asunto_id']);
        $this->noCerrada((int) $a['reunion_id']);
        Adjuntos::quitar($adj);
        Audit::record('consejos::asunto', 'correcto', 'Quita un adjunto del caso #' . $a['id']);
        flash('exito', 'Documento retirado.');
        redirigir('consejos/asunto/' . $a['id'] . '/editar');
    }
}
