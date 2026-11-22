<?php
declare(strict_types=1);

namespace App\Modules\Constancias;

use App\Core\Auth;
use App\Core\Database;
use App\Modules\Constancias\Services\Solicitudes;
use RuntimeException;

/**
 * Bandeja «Por firmar»: el firmante designado revisa, firma con su sesión o devuelve con una observación.
 * Cualquier otra cuenta recibe 404. El enlace del correo (constancias/firmar/{id}) pide iniciar sesión.
 */
final class FirmaController extends ConstanciasBase
{
    public function bandeja(): void
    {
        $this->privado();
        $this->vista('firmar', ['titulo' => 'Constancias por firmar', 'lista' => Solicitudes::delFirmante(Auth::id())]);
    }

    public function ver(string $id): void
    {
        $s = $this->mia($id);
        $this->privado();
        $this->vista('firma', ['titulo' => 'Firmar constancia ' . $s['folio'], 's' => $s, 'historial' => Solicitudes::historial((int) $s['id']), 'evidencia' => \App\Modules\Constancias\Services\Adjuntos::de((int) $s['id'])]);
    }

    public function firmar(string $id): void
    {
        $s = $this->mia($id);
        if (($_POST['revisada'] ?? '') !== '1') {
            flash('error', 'Confirma que revisaste la constancia antes de firmarla.');
            redirigir('constancias/firmar/' . (int) $s['id']);
        }
        try {
            $s = Solicitudes::firmar((int) $s['id'], Auth::id());
            flash('exito', 'Constancia ' . $s['folio'] . ' firmada. Avisamos a quien la solicitó.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('constancias/firmar/' . (int) $s['id']);
        }
        redirigir('constancias/firmar');
    }

    public function devolver(string $id): void
    {
        $s = $this->mia($id);
        $obs = mb_substr(trim(str_replace("\r", '', (string) ($_POST['observacion'] ?? ''))), 0, 1000);
        if (mb_strlen($obs) < 5) {
            flash('error', 'Escribe qué hay que corregir para devolverla.');
            redirigir('constancias/firmar/' . (int) $s['id']);
        }
        try {
            Solicitudes::devolver((int) $s['id'], Auth::id(), $obs);
            flash('exito', 'Constancia devuelta a ' . $s['solicitante_nombre'] . ' con tu observación.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('constancias/firmar/' . (int) $s['id']);
        }
        redirigir('constancias/firmar');
    }

    /** Solo el firmante designado; a cualquier otra cuenta, 404 (no revela que exista). */
    private function mia(string $id): array
    {
        $n = self::id($id);
        $s = $n !== null ? Solicitudes::obtener($n) : null;
        if (!$s || (int) $s['firmante_id'] !== Auth::id() || !in_array($s['estado'], ['en_firma', 'firmada', 'devuelta'], true)) {
            $this->noEncontrado();
        }
        return $s;
    }

    /**
     * Proveedor `por_firmar` de la bandeja única (docs/FIRMAS_UNICA.md): las constancias que esperan MI firma, sin importar
     * de qué departamento lleguen ni quién las solicitó; basta ser el firmante designado.
     */
    public static function porFirmar(): array
    {
        $yo = Auth::id();
        if (!$yo) {
            return [];
        }
        $r = [];
        foreach (Solicitudes::delFirmante($yo) as $f) {
            if ($f['estado'] !== 'en_firma') {
                continue;
            }
            $r[] = ['tipo' => 'Constancia', 'texto' => 'Constancia ' . $f['folio'] . ' · ' . ($f['persona_nombre'] !== '' ? $f['persona_nombre'] : $f['asunto']) . ' (solicita ' . $f['solicitante_nombre'] . ', ' . $f['departamento'] . ')',
                'enlace' => 'constancias/firmar/' . (int) $f['id'], 'fecha' => $f['enviada_en']];
        }
        return $r;
    }

    /** Proveedor de pendientes (Notificaciones): constancias por firmar y constancias propias devueltas para corregir. */
    public static function pendientesDeSesion(): array
    {
        $yo = Auth::id();
        if (!$yo) {
            return [];
        }
        $r = [];
        foreach (Database::todas("SELECT id, folio, enviada_en FROM cst_solicitudes WHERE firmante_id = ? AND estado = 'en_firma' ORDER BY enviada_en", [$yo]) as $f) {
            $r[] = ['tipo' => 'Firma', 'texto' => 'Constancia ' . $f['folio'] . ' espera tu firma', 'enlace' => 'constancias/firmar/' . (int) $f['id'], 'fecha' => $f['enviada_en']];
        }
        foreach (Database::todas("SELECT id, folio, devuelta_en FROM cst_solicitudes WHERE solicitante_id = ? AND estado = 'devuelta' ORDER BY devuelta_en", [$yo]) as $f) {
            $r[] = ['tipo' => 'Corrección', 'texto' => 'Constancia ' . $f['folio'] . ' devuelta: corrígela y reenvíala', 'enlace' => 'constancias/solicitud/' . (int) $f['id'], 'fecha' => $f['devuelta_en']];
        }
        return $r;
    }
}
