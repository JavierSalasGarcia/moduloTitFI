<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Core\Database;
use App\Modules\Consejos\Services\Texto;

/**
 * Conducción de las sesiones. Primero el Consejo Académico (se presenta cada caso y se redacta su acuerdo y si se avala o no);
 * después, en otra reunión, el Consejo de Gobierno (se proyecta el caso con el acuerdo del Académico y se redacta el suyo, si se
 * aprueba o no el acuerdo del Académico y una nota adicional). Cada cambio se guarda al momento.
 */
final class SesionController extends ConsejosBase
{
    public function pantalla(string $id, string $consejo): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $consejo = self::consejo($consejo);
        if ($consejo === 'gobierno' && empty($r['academico_fin'])) {
            flash('alerta', 'Primero debe terminar la sesión del Consejo Académico.');
            redirigir('consejos/reunion/' . $r['id']);
        }
        // Los casos son los mismos para los dos Consejos; en el Académico no salen los que nacieron en el de Gobierno.
        $filas = Database::todas("SELECT * FROM consejos_asuntos WHERE reunion_id = ?" . ($consejo === 'academico' ? " AND origen = 'academico'" : '') . " ORDER BY FIELD(grupo, 'profesor', 'alumno', 'general'), numero", [(int) $r['id']]);
        $asuntos = array_map(static fn(array $a) => [
            'id' => (int) $a['id'], 'clave' => Texto::clave($a['grupo'], (int) $a['numero']), 'grupo' => $a['grupo'], 'origen' => (string) $a['origen'],
            'observacion' => (string) $a['observacion'], 'solicitud' => (string) $a['solicitud_html'], 'antecedentes' => (string) $a['antecedentes_html'],
            'aval' => (string) $a['aval_academico'], 'acuerdoAcademico' => (string) $a['acuerdo_academico'],
            'aprob' => (string) $a['aprob_gobierno'], 'acuerdoGobierno' => (string) $a['acuerdo_gobierno'], 'nota' => (string) $a['nota_gobierno'],
        ], $filas);
        $this->vista('sesion', ['titulo' => 'Consejo ' . ($consejo === 'gobierno' ? 'de Gobierno' : 'Académico'), 'r' => $r, 'consejo' => $consejo, 'asuntos' => $asuntos], 'app');
    }

    public function iniciar(string $id, string $consejo): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $consejo = self::consejo($consejo);
        if ($consejo === 'gobierno' && empty($r['academico_fin'])) {
            flash('error', 'Primero debe terminar la sesión del Consejo Académico.');
            redirigir('consejos/reunion/' . $r['id']);
        }
        $estado = $consejo === 'gobierno' ? 'gobierno' : ($r['estado'] === 'preparacion' ? 'academico' : $r['estado']);
        Database::ejecutar('UPDATE consejos_reuniones SET estado = ? WHERE id = ?', [$estado, (int) $r['id']]);
        Audit::record('consejos::sesion', 'correcto', 'Inicia Consejo ' . $consejo . ' reunión #' . $r['id']);
        redirigir('consejos/reunion/' . $r['id'] . '/sesion/' . $consejo . ($this->post('proyectar') === '1' ? '?proyectar=1' : ''));
    }

    /** Guarda el acuerdo de un caso (JSON). El Consejo cuya sesión ya terminó no se modifica sin reabrirla. */
    public function guardar(string $id): void
    {
        $this->gestor();
        $a = $this->asunto($id);
        $r = $this->reunion((string) $a['reunion_id']);
        $consejo = self::consejo($this->post('consejo'));
        if (!empty($r[$consejo . '_fin'])) {
            $this->json(['ok' => false, 'error' => 'La sesión de este Consejo ya terminó. Reábrela para corregir.'], 409);
        }
        $acuerdo = mb_substr(str_replace("\r", '', $this->post('acuerdo')), 0, 6000);
        if ($consejo === 'academico') {
            $aval = in_array($this->post('decision'), ['pendiente', 'avala', 'no_avala'], true) ? $this->post('decision') : 'pendiente';
            $cambio = $acuerdo !== (string) $a['acuerdo_academico'] || $aval !== $a['aval_academico'];
            Database::ejecutar('UPDATE consejos_asuntos SET aval_academico = ?, acuerdo_academico = ?, version_acuerdo = version_acuerdo + ? WHERE id = ?', [$aval, $acuerdo, $cambio ? 1 : 0, (int) $a['id']]);
        } else {
            $aprob = in_array($this->post('decision'), ['pendiente', 'aprueba', 'no_aprueba'], true) ? $this->post('decision') : 'pendiente';
            $nota = mb_substr(str_replace("\r", '', $this->post('nota')), 0, 3000);
            $cambio = $acuerdo !== (string) $a['acuerdo_gobierno'] || $aprob !== $a['aprob_gobierno'] || $nota !== (string) $a['nota_gobierno'];
            Database::ejecutar('UPDATE consejos_asuntos SET aprob_gobierno = ?, acuerdo_gobierno = ?, nota_gobierno = ?, version_acuerdo = version_acuerdo + ? WHERE id = ?', [$aprob, $acuerdo, $nota, $cambio ? 1 : 0, (int) $a['id']]);
        }
        if ($cambio) {
            Audit::record('consejos::acuerdo', 'correcto', Texto::clave($a['grupo'], (int) $a['numero']) . ' Consejo ' . $consejo . ' reunión #' . $r['id']);
        }
        $this->json(['ok' => true, 'hora' => date('H:i:s')]);
    }

    /** Asienta la terminación de la sesión (fecha y hora). */
    public function terminar(string $id, string $consejo): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $consejo = self::consejo($consejo);
        $hora = $this->post('hora');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $hora)) {
            flash('error', 'Escribe la hora de terminación (HH:MM).');
            redirigir('consejos/reunion/' . $r['id'] . '/sesion/' . $consejo);
        }
        $fecha = $this->post('fecha');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || date('Y-m-d', strtotime($fecha)) !== $fecha) {
            $fecha = (string) ($r[$consejo . '_fecha'] ?: date('Y-m-d'));
        }
        $estado = $consejo === 'gobierno' ? 'cerrada' : $r['estado'];
        Database::ejecutar("UPDATE consejos_reuniones SET {$consejo}_fin = ?, estado = ? WHERE id = ?", [$fecha . ' ' . $hora . ':00', $estado, (int) $r['id']]);
        Audit::record('consejos::sesion', 'correcto', 'Termina Consejo ' . $consejo . ' reunión #' . $r['id']);
        flash('exito', 'Sesión del Consejo ' . ($consejo === 'gobierno' ? 'de Gobierno' : 'Académico') . ' terminada a las ' . $hora . ' horas.');
        redirigir('consejos/reunion/' . $r['id']);
    }

    public function reabrir(string $id, string $consejo): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $consejo = self::consejo($consejo);
        if ($consejo === 'academico' && !empty($r['gobierno_fin'])) {
            flash('error', 'No se puede reabrir el Consejo Académico: el de Gobierno ya terminó. Reabre primero ese.');
            redirigir('consejos/reunion/' . $r['id']);
        }
        $estado = $consejo === 'gobierno' ? 'gobierno' : $r['estado'];
        Database::ejecutar("UPDATE consejos_reuniones SET {$consejo}_fin = NULL, estado = ? WHERE id = ?", [$estado, (int) $r['id']]);
        Audit::record('consejos::sesion', 'correcto', 'Reabre Consejo ' . $consejo . ' reunión #' . $r['id']);
        flash('exito', 'Sesión reabierta: ya se pueden corregir los acuerdos.');
        redirigir('consejos/reunion/' . $r['id'] . '/sesion/' . $consejo);
    }

    private static function consejo(string $c): string
    {
        return $c === 'gobierno' ? 'gobierno' : 'academico';
    }
}
