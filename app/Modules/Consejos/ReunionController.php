<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Consejos\Services\Texto;
use App\Modules\Consejos\Services\Word;

/** Reuniones de los HH. Consejos (el par Consejo Académico + Consejo de Gobierno de un mes), sus actas y los archivos firmados. */
final class ReunionController extends ConsejosBase
{
    private const CAMPOS_TEXTO = ['presidente', 'secretario', 'academico_lugar', 'gobierno_lugar'];
    private const CAMPOS_LARGOS = ['consejeros_academico', 'consejeros_gobierno', 'actas_anteriores_academico', 'actas_anteriores_gobierno'];

    public function index(): void
    {
        $this->gestor();
        $reuniones = Database::todas(
            "SELECT r.*, (SELECT COUNT(*) FROM consejos_asuntos a WHERE a.reunion_id = r.id) AS asuntos,
                    (SELECT COUNT(*) FROM consejos_oficios o JOIN consejos_asuntos a ON a.id = o.asunto_id WHERE a.reunion_id = r.id) AS oficios
               FROM consejos_reuniones r ORDER BY COALESCE(r.gobierno_fecha, r.academico_fecha, DATE(r.creado_en)) DESC, r.id DESC"
        );
        $this->vista('index', ['titulo' => 'Consejos', 'reuniones' => $reuniones]);
    }

    /** Manual de llenado para los departamentos: se arma con el mismo catálogo de plantillas, así nunca queda desactualizado. */
    public function manual(): void
    {
        $this->privado();
        $this->vista('manual', ['titulo' => 'Manual de captura de casos', 'plantillas' => \App\Modules\Consejos\Services\Plantillas::todas()]);
    }

    public function nueva(): void
    {
        $this->gestor();
        // Se parte de la reunión anterior: misma mesa y mismos consejeros.
        $ant = Database::una('SELECT * FROM consejos_reuniones ORDER BY id DESC LIMIT 1') ?: [];
        $r = array_fill_keys(array_merge(self::CAMPOS_TEXTO, self::CAMPOS_LARGOS), '');
        foreach (['presidente', 'secretario', 'consejeros_academico', 'consejeros_gobierno', 'academico_lugar', 'gobierno_lugar'] as $c) {
            $r[$c] = $ant[$c] ?? '';
        }
        $r += ['id' => 0, 'etiqueta' => '', 'tipo_sesion' => 'ordinaria', 'academico_fecha' => '', 'academico_hora' => '', 'academico_convocatoria' => 'segunda',
            'gobierno_fecha' => '', 'gobierno_hora' => '', 'gobierno_convocatoria' => 'segunda', 'estado' => 'preparacion'];
        $this->vista('reunion_form', ['titulo' => 'Nueva reunión de Consejos', 'r' => $r, 'nueva' => true]);
    }

    public function crear(): void
    {
        $this->gestor();
        [$datos, $error] = $this->datos();
        if ($error !== '') {
            flash('error', $error);
            redirigir('consejos/nueva');
        }
        $cols = array_keys($datos);
        $id = Database::ejecutar(
            'INSERT INTO consejos_reuniones (' . implode(',', $cols) . ', creado_por) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ', ?)',
            [...array_values($datos), Auth::id()]
        );
        Audit::record('consejos::reunion', 'correcto', 'Crea reunión #' . $id);
        $cartera = (int) Database::valor("SELECT COUNT(*) FROM consejos_solicitudes WHERE estado = 'visto_bueno'");
        flash('exito', 'Reunión creada.' . ($cartera ? ' Hay ' . $cartera . ' solicitud(es) con Vo. Bo. en la cartera para incorporar.' : '') . ' Agrega los casos de profesores, de alumnos y los asuntos generales.');
        redirigir('consejos/reunion/' . $id);
    }

    public function ver(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $asuntos = Database::todas(
            "SELECT a.*, (SELECT COUNT(*) FROM consejos_asunto_profesores p WHERE p.asunto_id = a.id) AS profesores,
                    (SELECT s.folio FROM consejos_solicitudes s WHERE s.id = a.solicitud_id) AS sol_folio,
                    (SELECT s.escaneado_ruta FROM consejos_solicitudes s WHERE s.id = a.solicitud_id) AS sol_escaneado
               FROM consejos_asuntos a WHERE a.reunion_id = ? ORDER BY FIELD(a.grupo, 'profesor', 'alumno', 'general'), a.numero",
            [(int) $r['id']]
        );
        $archivos = Database::todas("SELECT * FROM consejos_archivos WHERE reunion_id = ? AND tipo IN ('acta_academico','acta_gobierno') ORDER BY id DESC", [(int) $r['id']]);
        $oficios = (int) Database::valor('SELECT COUNT(*) FROM consejos_oficios o JOIN consejos_asuntos a ON a.id = o.asunto_id WHERE a.reunion_id = ?', [(int) $r['id']]);
        $this->vista('reunion', ['titulo' => 'Reunión ' . $r['etiqueta'], 'r' => $r, 'asuntos' => $asuntos,
            'actaFirmada' => ['academico' => self::primero($archivos, 'acta_academico'), 'gobierno' => self::primero($archivos, 'acta_gobierno')], 'oficios' => $oficios, 'cerrada' => self::cerrada((int) $r['id']),
            'solicitudes' => Database::todas("SELECT s.id, s.folio, s.vobo_tipo, s.coordinacion, u.nombre AS solicitante FROM consejos_solicitudes s JOIN usuarios u ON u.id = s.solicitante_id WHERE s.estado = 'visto_bueno' ORDER BY s.vobo_en, s.id")]);
    }

    public function editar(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $this->vista('reunion_form', ['titulo' => 'Datos de la reunión', 'r' => $r, 'nueva' => false]);
    }

    public function actualizar(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        [$datos, $error] = $this->datos();
        if ($error !== '') {
            flash('error', $error);
            redirigir('consejos/reunion/' . $r['id'] . '/editar');
        }
        $set = implode(', ', array_map(static fn($c) => $c . ' = ?', array_keys($datos)));
        Database::ejecutar("UPDATE consejos_reuniones SET {$set} WHERE id = ?", [...array_values($datos), (int) $r['id']]);
        Audit::record('consejos::reunion', 'correcto', 'Actualiza reunión #' . $r['id']);
        flash('exito', 'Datos guardados.');
        redirigir('consejos/reunion/' . $r['id']);
    }

    public function eliminar(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        if ($r['estado'] !== 'preparacion') {
            flash('error', 'Solo se puede eliminar una reunión que aún no se inicia.');
            redirigir('consejos/reunion/' . $r['id']);
        }
        foreach (Database::todas('SELECT ruta FROM consejos_archivos WHERE reunion_id = ?', [(int) $r['id']]) as $f) {
            $abs = self::rutaAbsoluta((string) $f['ruta']);
            if ($abs) {
                @unlink($abs);
            }
        }
        Database::ejecutar('DELETE FROM consejos_reuniones WHERE id = ?', [(int) $r['id']]);
        Audit::record('consejos::reunion', 'correcto', 'Elimina reunión #' . $r['id']);
        flash('exito', 'Reunión eliminada.');
        redirigir('consejos');
    }

    /** Avisa por correo y en la bandeja a los profesores mencionados en la reunión (cuando ya terminó el Consejo de Gobierno). */
    public function avisar(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        if (empty($r['gobierno_fin'])) {
            flash('error', 'Avisa a los profesores cuando termine la sesión del Consejo de Gobierno: hasta entonces no ven sus comisiones.');
            redirigir('consejos/reunion/' . $r['id']);
        }
        $n = \App\Modules\Consejos\Services\Avisar::reunion($r);
        Audit::record('consejos::avisar', 'correcto', 'Reunión #' . $r['id'] . ': ' . $n . ' profesores');
        flash($n ? 'exito' : 'alerta', $n ? 'Se avisó a ' . $n . ' profesor' . ($n > 1 ? 'es' : '') . ' (correo y bandeja de Notificaciones).' : 'Ningún caso de esta reunión menciona a profesores con cuenta.');
        redirigir('consejos/reunion/' . $r['id']);
    }

    /** Acta del Consejo en Word, con lo capturado hasta el momento. */
    public function acta(string $id, string $consejo): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $consejo = self::consejo($consejo);
        $asuntos = Database::todas("SELECT * FROM consejos_asuntos WHERE reunion_id = ?" . ($consejo === 'academico' ? " AND origen = 'academico'" : '') . " ORDER BY FIELD(grupo, 'profesor', 'alumno', 'general'), numero", [(int) $r['id']]);
        Audit::record('consejos::acta', 'correcto', 'Descarga acta ' . $consejo . ' reunión #' . $r['id']);
        $fecha = $r[$consejo . '_fecha'] ?: date('Y-m-d');
        $this->enviarDocx(Word::acta($r, $consejo, $asuntos), 'Acta_Consejo_' . ($consejo === 'gobierno' ? 'Gobierno' : 'Academico') . '_' . $fecha . '.docx');
    }

    /** Escaneado del acta firmada y sellada (reemplaza al anterior). */
    public function subirFirmada(string $id, string $consejo): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $consejo = self::consejo($consejo);
        [$rel, $bytes, $error] = $this->guardarPdf($_FILES['archivo'] ?? [], (int) $r['id']);
        if ($rel === null) {
            flash('error', $error);
            redirigir('consejos/reunion/' . $r['id']);
        }
        $tipo = 'acta_' . $consejo;
        foreach (Database::todas('SELECT id, ruta FROM consejos_archivos WHERE reunion_id = ? AND tipo = ?', [(int) $r['id'], $tipo]) as $viejo) {
            if ($abs = self::rutaAbsoluta((string) $viejo['ruta'])) {
                @unlink($abs);
            }
            Database::ejecutar('DELETE FROM consejos_archivos WHERE id = ?', [(int) $viejo['id']]);
        }
        $nombre = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', (string) ($_FILES['archivo']['name'] ?? 'acta.pdf')) ?: 'acta.pdf', 0, 150);
        Database::ejecutar('INSERT INTO consejos_archivos (reunion_id, tipo, nombre, ruta, bytes, subido_por) VALUES (?, ?, ?, ?, ?, ?)', [(int) $r['id'], $tipo, $nombre, $rel, $bytes, Auth::id()]);
        Audit::record('consejos::archivo', 'correcto', 'Sube ' . $tipo . ' reunión #' . $r['id']);
        flash('exito', 'Acta firmada guardada.');
        redirigir('consejos/reunion/' . $r['id']);
    }

    /** Descarga de un PDF: gestores siempre; profesores, solo lo que les corresponde. */
    public function archivo(string $id): void
    {
        $n = self::id($id);
        $f = $n ? Database::una('SELECT * FROM consejos_archivos WHERE id = ?', [$n]) : null;
        if (!$f || !self::puedeVer($f)) {
            $this->noEncontrado();
        }
        $abs = self::rutaAbsoluta((string) $f['ruta']);
        if (!$abs) {
            $this->noEncontrado();
        }
        Audit::record('consejos::descarga', 'correcto', 'Archivo #' . $f['id'] . ' ' . $f['tipo']);
        $this->enviarPdf($abs, ($f['tipo'] === 'dictamen' ? 'Dictamen' : ($f['tipo'] === 'acta_gobierno' ? 'Acta_Consejo_de_Gobierno' : 'Acta_Consejo_Academico')) . '_' . $f['id'] . '.pdf', ($_GET['descargar'] ?? '') !== '1');
    }

    public function quitarArchivo(string $id): void
    {
        $this->gestor();
        $n = self::id($id);
        $f = $n ? Database::una('SELECT * FROM consejos_archivos WHERE id = ?', [$n]) : null;
        if (!$f) {
            $this->noEncontrado();
        }
        if ($abs = self::rutaAbsoluta((string) $f['ruta'])) {
            @unlink($abs);
        }
        Database::ejecutar('DELETE FROM consejos_archivos WHERE id = ?', [(int) $f['id']]);
        Audit::record('consejos::archivo', 'correcto', 'Quita archivo #' . $f['id']);
        flash('exito', 'Archivo retirado.');
        redirigir($f['tipo'] === 'dictamen' ? 'consejos/reunion/' . $f['reunion_id'] . '/dictamenes' : 'consejos/reunion/' . $f['reunion_id']);
    }

    public static function puedeVer(array $f): bool
    {
        if (ConsejosAccess::esGestor()) {
            return true;
        }
        $cerrada = (bool) Database::valor('SELECT gobierno_fin IS NOT NULL FROM consejos_reuniones WHERE id = ?', [(int) $f['reunion_id']]);
        if (!$cerrada) {
            return false;
        }
        if ($f['tipo'] === 'dictamen') {
            // Quien abrió el enlace de «Notificar dictamen» con la cuenta del correo al que se envió.
            if (!empty($f['oficio_id']) && \App\Modules\Consejos\Services\Enlaces::permiteAlUsuario((int) $f['oficio_id'])) {
                return true;
            }
            return $f['asunto_id'] && Database::valor('SELECT 1 FROM consejos_asunto_profesores WHERE asunto_id = ? AND usuario_id = ?', [(int) $f['asunto_id'], Auth::id()]);
        }
        return ConsejosAccess::mencionadoEn((int) $f['reunion_id']);
    }

    private static function consejo(string $c): string
    {
        return $c === 'gobierno' ? 'gobierno' : 'academico';
    }

    private static function primero(array $filas, string $tipo): ?array
    {
        foreach ($filas as $f) {
            if ($f['tipo'] === $tipo) {
                return $f;
            }
        }
        return null;
    }

    /** @return array{0:array<string,mixed>,1:string} datos validados y mensaje de error */
    private function datos(): array
    {
        $d = ['etiqueta' => mb_substr($this->post('etiqueta'), 0, 80), 'tipo_sesion' => $this->post('tipo_sesion') === 'extraordinaria' ? 'extraordinaria' : 'ordinaria'];
        if ($d['etiqueta'] === '') {
            return [[], 'Escribe cómo se llamará la reunión (por ejemplo, «Noviembre 2026»).'];
        }
        foreach (self::CAMPOS_TEXTO as $c) {
            $d[$c] = mb_substr($this->post($c), 0, 160);
        }
        foreach (self::CAMPOS_LARGOS as $c) {
            // Los consejeros llegan del selector de personas como una lista (uno por elemento).
            $crudo = $_POST[$c] ?? '';
            $texto = is_array($crudo) ? implode("\n", array_map(static fn($n) => trim((string) $n), $crudo)) : $this->post($c);
            $d[$c] = mb_substr(trim(str_replace("\r", '', $texto)), 0, 6000);
        }
        foreach (['academico', 'gobierno'] as $c) {
            $f = $this->post($c . '_fecha');
            if ($f !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) || date('Y-m-d', strtotime($f)) !== $f)) {
                return [[], 'La fecha del Consejo ' . ($c === 'academico' ? 'Académico' : 'de Gobierno') . ' no es válida.'];
            }
            $h = $this->post($c . '_hora');
            if ($h !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $h)) {
                return [[], 'La hora del Consejo ' . ($c === 'academico' ? 'Académico' : 'de Gobierno') . ' no es válida (HH:MM).'];
            }
            $d[$c . '_fecha'] = $f !== '' ? $f : null;
            $d[$c . '_hora'] = $h !== '' ? $h . ':00' : null;
            // La hora capturada es la de la segunda convocatoria (la primera es media hora antes); la extraordinaria solo tiene una.
            $d[$c . '_convocatoria'] = $d['tipo_sesion'] === 'extraordinaria' ? 'unica' : 'segunda';
        }
        return [$d, ''];
    }
}
