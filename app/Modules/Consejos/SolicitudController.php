<?php

declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\View;
use App\Modules\Consejos\Services\FirmaConfig;
use App\Modules\Consejos\Services\Html;
use App\Modules\Consejos\Services\Plantillas;
use App\Modules\Consejos\Services\SolicitudOficio;
use App\Modules\Consejos\Services\Solicitudes;

/**
 * Solicitudes a los HH. Consejos de profesores y coordinadores, con el Vo. Bo. de la coordinación (docs/CONSEJOS.md). Cada persona ve
 * solo las suyas; la coordinación a la que se dirigen y quienes gestionan los Consejos, también.
 */
final class SolicitudController extends ConsejosBase
{
    private function solicitud(string $id): array
    {
        $this->privado();
        $n = self::id($id);
        $s = $n ? Database::una('SELECT * FROM consejos_solicitudes WHERE id = ?', [$n]) : null;
        if (!$s || !self::puedeVer($s)) {
            $this->noEncontrado();
        }
        return $s;
    }

    private static function puedeVer(array $s): bool
    {
        $uid = Auth::id();
        return $uid !== null && ($uid === (int) $s['solicitante_id'] || $uid === (int) $s['coordinador_id'] || ConsejosAccess::esGestor());
    }

    // ------------------------------------------------------------------ listado

    public function index(): void
    {
        $this->privado();
        $uid = (int) Auth::id();
        $mias = Database::todas("SELECT * FROM consejos_solicitudes WHERE solicitante_id = ? AND estado <> 'cancelada' ORDER BY id DESC LIMIT 100", [$uid]);
        $revisar = Database::todas("SELECT s.*, u.nombre AS solicitante FROM consejos_solicitudes s JOIN usuarios u ON u.id = s.solicitante_id WHERE s.coordinador_id = ? AND s.estado = 'enviada' ORDER BY s.enviado_en, s.id", [$uid]);
        $revisadas = Database::todas("SELECT s.*, u.nombre AS solicitante FROM consejos_solicitudes s JOIN usuarios u ON u.id = s.solicitante_id WHERE s.coordinador_id = ? AND s.estado IN ('visto_bueno','incorporada','devuelta') ORDER BY s.actualizado_en DESC LIMIT 30", [$uid]);
        $cartera = ConsejosAccess::esGestor() ? Database::todas("SELECT s.*, u.nombre AS solicitante FROM consejos_solicitudes s JOIN usuarios u ON u.id = s.solicitante_id WHERE s.estado = 'visto_bueno' ORDER BY s.vobo_en, s.id") : [];
        $reuniones = $cartera ? Database::todas("SELECT id, etiqueta FROM consejos_reuniones WHERE estado = 'preparacion' ORDER BY id DESC") : [];
        $this->vista('solicitudes', ['titulo' => 'Solicitudes a los Consejos', 'mias' => $mias, 'revisar' => $revisar, 'revisadas' => $revisadas, 'cartera' => $cartera, 'reuniones' => $reuniones]);
    }

    // ------------------------------------------------------------------ redactar

    public function nueva(): void
    {
        $this->privado();
        $this->formulario(['id' => 0, 'grupo' => 'profesor', 'plantilla' => '', 'solicitud_html' => '', 'antecedentes_html' => '', 'coordinador_id' => 0, 'coordinacion' => '', 'profesores' => '']);
    }

    public function editar(string $id): void
    {
        $s = $this->solicitud($id);
        if ((int) $s['solicitante_id'] !== (int) Auth::id() || !in_array($s['estado'], ['borrador', 'devuelta'], true)) {
            flash('error', 'Solo puedes editar tus solicitudes en borrador o devueltas.');
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        $this->formulario($s);
    }

    private function formulario(array $s): void
    {
        $ids = array_filter(array_map('intval', explode(',', (string) ($s['profesores'] ?? ''))));
        $profes = $ids ? Database::todas('SELECT id, nombre, email FROM usuarios WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY nombre', array_values($ids)) : [];
        $this->vista('solicitud_form', ['titulo' => $s['id'] ? 'Editar solicitud' : 'Nueva solicitud', 's' => $s, 'profesores' => $profes,
            'coordinaciones' => Solicitudes::coordinaciones(), 'plantillas' => Plantillas::paraGrupo('')]);
    }

    public function crear(): void
    {
        $this->guardar(null);
    }

    public function actualizar(string $id): void
    {
        $s = $this->solicitud($id);
        if ((int) $s['solicitante_id'] !== (int) Auth::id() || !in_array($s['estado'], ['borrador', 'devuelta'], true)) {
            flash('error', 'Solo puedes editar tus solicitudes en borrador o devueltas.');
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        $this->guardar($s);
    }

    private function guardar(?array $previa): void
    {
        $this->privado();
        $grupo = in_array($this->post('grupo'), ['profesor', 'alumno', 'general'], true) ? $this->post('grupo') : 'general';
        $sol = Html::limpiar((string) ($_POST['solicitud_html'] ?? ''));
        $ant = Html::limpiar((string) ($_POST['antecedentes_html'] ?? ''));
        $volver = $previa ? 'consejos/solicitudes/' . $previa['id'] . '/editar' : 'consejos/solicitudes/nueva';
        if (trim(Html::texto($sol)) === '') {
            flash('error', 'Escribe la solicitud.');
            redirigir($volver);
        }
        $coord = Solicitudes::coordinaciones()[$this->post('coordinacion')] ?? null;
        if (!$coord) {
            flash('error', 'Elige la coordinación que dará el Vo. Bo. (Docencia de tu licenciatura o Estudios Avanzados).');
            redirigir($volver);
        }
        if ($coord['usuario_id'] === (int) Auth::id()) {
            flash('error', 'El Vo. Bo. debe darlo otra persona: elige una coordinación distinta.');
            redirigir($volver);
        }
        $pl = preg_replace('/[^a-z_]/', '', $this->post('plantilla'));
        $profes = implode(',', array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['profesores'] ?? []))))));
        if ($previa) {
            Database::ejecutar("UPDATE consejos_solicitudes SET grupo = ?, plantilla = ?, solicitud_html = ?, antecedentes_html = ?, profesores = ?, coordinador_id = ?, coordinacion = ? WHERE id = ?",
                [$grupo, $pl ?: null, $sol, $ant, $profes ?: null, $coord['usuario_id'], $coord['coordinacion'], (int) $previa['id']]);
            $id = (int) $previa['id'];
        } else {
            $id = Database::ejecutar("INSERT INTO consejos_solicitudes (solicitante_id, grupo, plantilla, solicitud_html, antecedentes_html, profesores, coordinador_id, coordinacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [(int) Auth::id(), $grupo, $pl ?: null, $sol, $ant, $profes ?: null, $coord['usuario_id'], $coord['coordinacion']]);
            Database::ejecutar('UPDATE consejos_solicitudes SET folio = ? WHERE id = ?', [Solicitudes::folio($id), $id]);
        }
        Audit::record('consejos::solicitud', 'correcto', ($previa ? 'Edita ' : 'Crea ') . Solicitudes::folio($id));
        if ($this->post('accion') === 'enviar') {
            $this->enviarYa($id);
        }
        flash('exito', 'Solicitud ' . Solicitudes::folio($id) . ' guardada como borrador.');
        redirigir('consejos/solicitudes/' . $id);
    }

    // ------------------------------------------------------------------ flujo

    public function enviar(string $id): void
    {
        $s = $this->solicitud($id);
        $this->enviarYa((int) $s['id']);
    }

    private function enviarYa(int $id): never
    {
        $s = Database::una('SELECT * FROM consejos_solicitudes WHERE id = ?', [$id]);
        if (!$s || (int) $s['solicitante_id'] !== (int) Auth::id() || !in_array($s['estado'], ['borrador', 'devuelta'], true)) {
            flash('error', 'Esta solicitud no se puede enviar.');
            redirigir('consejos/solicitudes');
        }
        // Al reenviar una solicitud devuelta, lo que se corrigió queda en el historial de la ronda.
        $respuesta = mb_substr(trim($this->post('respuesta')), 0, 1000);
        if ($s['estado'] === 'devuelta' && $respuesta !== '') {
            Database::ejecutar("INSERT INTO consejos_solicitud_obs (solicitud_id, ronda, tipo, autor_id, texto) VALUES (?, ?, 'respuesta', ?, ?)", [$id, max(1, Solicitudes::ronda($id)), (int) Auth::id(), $respuesta]);
        }
        Database::ejecutar("UPDATE consejos_solicitudes SET estado = 'enviada', enviado_en = NOW(), modo_solicitante = ?, comentario = NULL WHERE id = ?", [FirmaConfig::efectivo('solicitud_solicitante'), $id]);
        $s = Database::una('SELECT * FROM consejos_solicitudes WHERE id = ?', [$id]);
        try {
            SolicitudOficio::regenerar($s);
        } catch (\Throwable $e) {
            flash('error', 'La solicitud se envió, pero no se pudo generar el oficio en PDF: ' . $e->getMessage());
        }
        Audit::record('consejos::solicitud', 'correcto', 'Envía ' . $s['folio'] . ' a ' . $s['coordinacion']);
        $ronda = Solicitudes::ronda($id);
        Solicitudes::avisar((int) $s['coordinador_id'], 'Solicitud ' . $s['folio'] . ($ronda > 0 ? ' corregida' : ' para tu Vo. Bo.'), Solicitudes::nombreCompleto((int) $s['solicitante_id']) . ($ronda > 0 ? ' corrigió y reenvió' : ' envió') . ' la solicitud ' . $s['folio'] . ' a los HH. Consejos. Requiere tu Vo. Bo. o de enterado (' . $s['coordinacion'] . ').' . ($respuesta !== '' ? ' Respuesta: ' . $respuesta : ''), 'consejos/solicitudes/' . $id, 'Revisar la solicitud');
        flash('exito', 'Solicitud ' . $s['folio'] . ' enviada a ' . $s['coordinacion'] . '.');
        redirigir('consejos/solicitudes/' . $id);
    }

    public function vobo(string $id): void
    {
        $s = $this->solicitud($id);
        if (!Solicitudes::esCoordinadorDe($s, Auth::id()) || $s['estado'] !== 'enviada') {
            flash('error', 'Solo la coordinación a la que se dirige puede dar el Vo. Bo., y solo a solicitudes enviadas.');
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        $tipo = $this->post('tipo') === 'enterado' ? 'enterado' : 'vobo';
        Database::ejecutar("UPDATE consejos_solicitudes SET estado = 'visto_bueno', vobo_tipo = ?, vobo_por = ?, vobo_en = NOW(), modo_vobo = ? WHERE id = ?", [$tipo, (int) Auth::id(), FirmaConfig::efectivo('solicitud_vobo'), (int) $s['id']]);
        $s = Database::una('SELECT * FROM consejos_solicitudes WHERE id = ?', [(int) $s['id']]);
        try {
            SolicitudOficio::regenerar($s);
        } catch (\Throwable $e) {
            flash('error', 'No se pudo actualizar el oficio en PDF: ' . $e->getMessage());
        }
        Audit::record('consejos::solicitud', 'correcto', ($tipo === 'enterado' ? 'Enterado de ' : 'Vo. Bo. a ') . $s['folio']);
        // Con el Vo. Bo. la solicitud pasa a la cartera del Consejo y se avisa al secretario académico y a su asistente, que la llevan a una reunión.
        $quien = Solicitudes::nombreCompleto((int) $s['solicitante_id']);
        foreach (Solicitudes::destinatariosCartera() as $d) {
            Solicitudes::avisar($d['id'], 'Nueva solicitud en cartera: ' . $s['folio'], $quien . ' presentó la solicitud ' . $s['folio'] . ' con el ' . ($tipo === 'enterado' ? 'de enterado' : 'Vo. Bo.') . ' de ' . $s['coordinacion'] . '. Ya está en la cartera de casos para el Consejo.', 'consejos/solicitudes', 'Ver la cartera');
        }
        Solicitudes::avisar((int) $s['solicitante_id'], 'Tu solicitud ' . $s['folio'] . ' tiene Vo. Bo.', $s['coordinacion'] . ' dio el ' . ($tipo === 'enterado' ? 'enterado' : 'Vo. Bo.') . ' a tu solicitud ' . $s['folio'] . '. Pasó a la cartera de casos para el Consejo; entrará al acta de la reunión que se programe.', 'consejos/solicitudes/' . (int) $s['id'], 'Ver mi solicitud');
        flash('exito', 'Vo. Bo. registrado. La solicitud pasó a la cartera de casos para el Consejo y se avisó al secretario académico y a su asistente.');
        redirigir('consejos/solicitudes/' . $s['id']);
    }

    public function devolver(string $id): void
    {
        $s = $this->solicitud($id);
        $motivo = mb_substr(trim($this->post('comentario')), 0, 1000);
        if (!Solicitudes::esCoordinadorDe($s, Auth::id()) || $s['estado'] !== 'enviada') {
            flash('error', 'Solo la coordinación a la que se dirige puede devolver una solicitud enviada.');
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        if ($motivo === '') {
            flash('error', 'Escribe qué debe corregirse.');
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        Database::ejecutar("UPDATE consejos_solicitudes SET estado = 'devuelta', comentario = ?, enviado_en = NULL, modo_solicitante = NULL WHERE id = ?", [$motivo, (int) $s['id']]);
        $ronda = Solicitudes::ronda((int) $s['id']) + 1;
        Database::ejecutar("INSERT INTO consejos_solicitud_obs (solicitud_id, ronda, tipo, autor_id, texto) VALUES (?, ?, 'observacion', ?, ?)", [(int) $s['id'], $ronda, (int) Auth::id(), $motivo]);
        Audit::record('consejos::solicitud', 'correcto', 'Devuelve ' . $s['folio'] . ' (ronda ' . $ronda . ')');
        Solicitudes::avisar((int) $s['solicitante_id'], 'Tu solicitud ' . $s['folio'] . ' fue devuelta', $s['coordinacion'] . ' devolvió tu solicitud: ' . $motivo, 'consejos/solicitudes/' . (int) $s['id'], 'Corregir la solicitud');
        flash('exito', 'Observaciones enviadas: la solicitud regresó a quien la redactó para que la corrija. Cuando la reenvíe te llegará de nuevo.');
        redirigir('consejos/solicitudes');
    }

    public function cancelar(string $id): void
    {
        $s = $this->solicitud($id);
        if ((int) $s['solicitante_id'] !== (int) Auth::id() || !in_array($s['estado'], ['borrador', 'enviada', 'devuelta'], true)) {
            flash('error', 'Esta solicitud ya no se puede cancelar.');
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        Database::ejecutar("UPDATE consejos_solicitudes SET estado = 'cancelada' WHERE id = ?", [(int) $s['id']]);
        Audit::record('consejos::solicitud', 'correcto', 'Cancela ' . $s['folio']);
        flash('exito', 'Solicitud cancelada.');
        redirigir('consejos/solicitudes');
    }

    /** Quienes gestionan los Consejos llevan a una reunión las solicitudes con Vo. Bo. que esperaban una. */
    public function incorporar(string $id): void
    {
        $this->gestor();
        $s = $this->solicitud($id);
        $r = $this->reunion($this->post('reunion_id'));
        $this->noCerrada((int) $r['id']);
        if ($s['estado'] !== 'visto_bueno') {
            flash('error', 'Solo se incorporan solicitudes con Vo. Bo. que esperan reunión.');
            redirigir('consejos/reunion/' . $r['id']);
        }
        Solicitudes::incorporar($s, (int) $r['id']);
        Audit::record('consejos::solicitud', 'correcto', 'Incorpora ' . $s['folio'] . ' a la reunión #' . $r['id']);
        flash('exito', 'Solicitud ' . $s['folio'] . ' incorporada como caso.');
        redirigir('consejos/reunion/' . $r['id']);
    }

    /** Lleva a una reunión toda la cartera (las solicitudes con Vo. Bo. que esperan). */
    public function incorporarTodas(): void
    {
        $this->gestor();
        $r = $this->reunion($this->post('reunion_id'));
        $this->noCerrada((int) $r['id']);
        $n = Solicitudes::incorporarPendientes((int) $r['id']);
        Audit::record('consejos::solicitud', 'correcto', 'Incorpora ' . $n . ' solicitud(es) de la cartera a la reunión #' . $r['id']);
        flash('exito', $n ? $n . ' solicitud(es) de la cartera incorporada(s) como casos.' : 'La cartera está vacía.');
        redirigir('consejos/reunion/' . $r['id']);
    }

    // ------------------------------------------------------------------ detalle y archivos

    public function ver(string $id): void
    {
        $s = $this->solicitud($id);
        $solicitante = Database::una('SELECT nombre, grado, email FROM usuarios WHERE id = ?', [(int) $s['solicitante_id']]);
        $this->vista('solicitud_ver', ['titulo' => 'Solicitud ' . $s['folio'], 's' => $s, 'solicitante' => $solicitante, 'historial' => Solicitudes::historial((int) $s['id']),
            'caso' => $s['asunto_id'] ? Database::una('SELECT a.grupo, a.numero, r.etiqueta, r.id AS reunion FROM consejos_asuntos a JOIN consejos_reuniones r ON r.id = a.reunion_id WHERE a.id = ?', [(int) $s['asunto_id']]) : null,
            'reuniones' => ConsejosAccess::esGestor() ? Database::todas("SELECT id, etiqueta FROM consejos_reuniones WHERE estado = 'preparacion' ORDER BY id DESC") : []]);
    }

    public function oficio(string $id): void
    {
        $s = $this->solicitud($id);
        $abs = $s['oficio_ruta'] ? self::rutaAbsoluta((string) $s['oficio_ruta']) : null;
        if (!$abs) {
            $this->noEncontrado();
        }
        $this->enviarPdf($abs, 'Solicitud_' . $s['folio'] . '.pdf', ($_GET['descargar'] ?? '') !== '1', ($_GET['marco'] ?? '') === '1');
    }

    public function escaneado(string $id): void
    {
        $s = $this->solicitud($id);
        $abs = $s['escaneado_ruta'] ? self::rutaAbsoluta((string) $s['escaneado_ruta']) : null;
        if (!$abs) {
            $this->noEncontrado();
        }
        $this->enviarPdf($abs, 'Solicitud_firmada_' . $s['folio'] . '.pdf', true, ($_GET['marco'] ?? '') === '1');
    }

    /** Sube el oficio impreso con el Vo. Bo. y la firma autógrafa, escaneado. */
    public function subirEscaneado(string $id): void
    {
        $s = $this->solicitud($id);
        if (!in_array($s['estado'], ['visto_bueno', 'incorporada'], true)) {
            flash('error', 'El escaneado firmado se sube después del Vo. Bo. (o del de enterado) de la coordinación.');
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        [$rel, , $error] = $this->guardarPdf($_FILES['archivo'] ?? [], 'solicitudes/' . (int) $s['id']);
        if ($rel === null) {
            flash('error', $error);
            redirigir('consejos/solicitudes/' . $s['id']);
        }
        if ($s['escaneado_ruta'] && ($viejo = self::rutaAbsoluta((string) $s['escaneado_ruta']))) {
            @unlink($viejo);
        }
        Database::ejecutar('UPDATE consejos_solicitudes SET escaneado_ruta = ?, escaneado_en = NOW(), escaneado_por = ? WHERE id = ?', [$rel, (int) Auth::id(), (int) $s['id']]);
        Audit::record('consejos::solicitud', 'correcto', 'Sube el escaneado de ' . $s['folio']);
        flash('exito', 'Escaneado guardado.');
        redirigir('consejos/solicitudes/' . $s['id']);
    }

    /** Buscador de personas para mencionarlas en la solicitud (JSON). */
    public function personas(): void
    {
        $this->privado();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        if (mb_strlen($q) < 2) {
            $this->json(['personas' => []]);
        }
        $donde = [];
        $params = [];
        foreach (array_slice(preg_split('/\s+/u', $q) ?: [], 0, 5) as $palabra) {
            $like = '%' . strtr($palabra, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
            $donde[] = '(nombre LIKE ? OR email LIKE ?)';
            array_push($params, $like, $like);
        }
        $filas = Database::todas("SELECT id, nombre, email FROM usuarios WHERE activo = 1 AND rol <> 'alumno' AND " . implode(' AND ', $donde) . ' ORDER BY nombre LIMIT 15', $params);
        $this->json(['personas' => array_map(static fn($f) => ['id' => (int) $f['id'], 'nombre' => $f['nombre'], 'email' => $f['email']], $filas)]);
    }

    // ------------------------------------------------------------------ configuración de firmas

    public function firmas(): void
    {
        $this->gestor();
        $this->vista('firmas_config', ['titulo' => 'Firmas de los Consejos', 'autorizada' => FirmaConfig::electronicaAutorizada(), 'marcada' => \ajuste('consejos_firma_electronica', '0') === '1']);
    }

    public function guardarFirmas(): void
    {
        $this->gestor();
        foreach (array_keys(FirmaConfig::DOCUMENTOS) as $doc) {
            if (FirmaConfig::DOCUMENTOS[$doc][1]) {
                FirmaConfig::guardar($doc, $this->post('modo_' . $doc));
            }
        }
        FirmaConfig::autorizarElectronica($this->post('electronica') === '1');
        Audit::record('consejos::firmas', 'correcto', 'Actualiza la configuración de firmas');
        flash('exito', 'Configuración de firmas guardada.');
        redirigir('consejos/firmas');
    }
}
