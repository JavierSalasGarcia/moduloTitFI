<?php
declare(strict_types=1);

namespace App\Modules\Constancias;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Constancias\Services\Adjuntos;
use App\Modules\Constancias\Services\Solicitudes;

/** Evidencia adjunta a una constancia: la ven quien la solicita, el firmante designado y la supervisión; solo quien solicita la quita. */
final class AdjuntoController extends ConstanciasBase
{
    /** @return array{0:array,1:array} adjunto y su constancia */
    private function cargar(string $id, bool $soloSolicitante): array
    {
        $this->privado();
        $n = self::id($id);
        $a = $n !== null ? Adjuntos::uno($n) : null;
        $s = $a ? Solicitudes::obtener((int) $a['solicitud_id']) : null;
        if (!$a || !$s) {
            $this->noEncontrado();
        }
        $yo = Auth::id();
        $esSolicitante = (int) $s['solicitante_id'] === $yo;
        $permitido = $soloSolicitante
            ? $esSolicitante
            : ($esSolicitante || ((int) $s['firmante_id'] === $yo && $s['estado'] !== 'borrador') || ConstanciasAccess::esSupervision());
        if (!$permitido) {
            $this->noEncontrado();
        }
        return [$a, $s];
    }

    public function ver(string $id): void
    {
        [$a] = $this->cargar($id, false);
        $base = realpath((string) App::config('archivos.storage'));
        $abs = $base !== false ? realpath($base . '/' . $a['ruta']) : false;
        if ($abs === false || !is_file($abs) || !str_starts_with($abs, rtrim($base, '/\\') . DIRECTORY_SEPARATOR)) {
            $this->noEncontrado();
        }
        Audit::record('constancias::evidencia', 'atendido', 'Adjunto #' . (int) $a['id'] . ' de la constancia ' . (int) $a['solicitud_id']);
        $ext = (string) $a['extension'];
        header('Content-Type: ' . Adjuntos::mime($ext));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true) ? 'inline' : 'attachment') . '; filename="' . (preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $a['nombre']) ?: 'evidencia') . '"');
        header('Content-Length: ' . filesize($abs));
        readfile($abs);
        exit;
    }

    public function quitar(string $id): void
    {
        [$a, $s] = $this->cargar($id, true);
        if (!in_array($s['estado'], ['borrador', 'devuelta'], true)) {
            flash('error', 'La evidencia solo se quita mientras la constancia es borrador o está devuelta.');
            redirigir('constancias/solicitud/' . (int) $s['id']);
        }
        Adjuntos::quitar($a);
        Audit::record('constancias::evidencia', 'correcto', 'Quita adjunto #' . (int) $a['id'] . ' de la constancia ' . (int) $s['id']);
        flash('exito', 'Archivo quitado.');
        redirigir('constancias/solicitud/' . (int) $s['id'] . '/editar');
    }
}
