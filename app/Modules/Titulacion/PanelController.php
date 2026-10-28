<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;

/** Menús personales: «Estado» del pasante y constancias de titulación de cada profesor. */
final class PanelController extends TitulacionBase
{
    /** Los mensajes al pasante viven ahora en la bandeja única de Notificaciones. */
    public function mensajes(): void
    {
        redirigir('notificaciones');
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
                'noLeidos' => (int) Database::valor('SELECT COUNT(*) FROM notificaciones WHERE tramite_id = ? AND email = ? AND leida_en IS NULL', [(int) $t['id'], TitulacionAccess::correoSesion()]),
            ];
        }
        $this->vista('estado', ['titulo' => 'Titulación · Estado', 'tramites' => $tramites]);
    }

    // ----------------------------------------------------------------- constancias

    /** Agrega a cada constancia su año, su descripción breve y el token para verla o elegirla. */
    private function preparar(array $lista): array
    {
        foreach ($lista as &$c) {
            $c['anio'] = Services\Constancias::anio($c);
            $c['descripcion'] = Services\Constancias::descripcion($c);
            $c['ver'] = Services\Archivos::url('constancia', (int) $c['id']);
            $c['token'] = substr($c['ver'], strlen('titulacion/archivo/'));
        }
        unset($c);
        usort($lista, static fn(array $x, array $y): int => [$y['anio'], $x['descripcion']] <=> [$x['anio'], $y['descripcion']]);
        return $lista;
    }

    /** «Constancias → Titulación Lic»: las constancias de quien tiene la sesión. */
    public function constancias(): void
    {
        $this->privado();
        $email = TitulacionAccess::correoSesion();
        $this->vista('constancias', [
            'titulo' => 'Constancias · Titulación Lic',
            'constancias' => $this->preparar(Services\Constancias::dePersona($email)),
            'persona' => null, 'busqueda' => false,
        ]);
    }

    /** Su titular, o quien puede consultarla en la búsqueda según su alcance. */
    private function puedeVerConstancia(array $c): bool
    {
        $alcance = Services\Constancias::alcance();
        return \App\Core\Identidad::esDe($c, TitulacionAccess::correoSesion()) || (Services\Constancias::puedeBuscar() && ($alcance === null || $alcance === $c['emisor']));
    }

    /**
     * Archivo de la constancia que puede abrir la sesión: la firmada; la que aún espera firma solo la revisa (sin firma)
     * el personal que busca constancias, para comprobar que esté correcta antes de firmarla. El profesor solo ve las firmadas.
     */
    private function archivoConstancia(array $c): ?string
    {
        if (!$this->puedeVerConstancia($c)) {
            return null;
        }
        if (!empty($c['subdir_ruta'])) {
            return (string) $c['subdir_ruta'];
        }
        $alcance = Services\Constancias::alcance();
        return Services\Constancias::puedeBuscar() && ($alcance === null || $alcance === $c['emisor']) && !empty($c['ruta']) ? (string) $c['ruta'] : null;
    }

    /** Una constancia, para verla en el navegador (por token: titulacion/archivo/{token}). */
    public function constancia(string $id): void
    {
        $c = ctype_digit($id) ? Database::una('SELECT c.*, t.folio, d.subdir_ruta, d.ruta FROM tit_constancias c JOIN tit_tramites t ON t.id = c.tramite_id LEFT JOIN tit_documentos d ON d.id = c.documento_id WHERE c.id = ?', [(int) $id]) : null;
        $archivo = $c ? $this->archivoConstancia($c) : null;
        if ($archivo === null) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::constancia', 'atendido', 'Constancia ' . (int) $c['id'] . (empty($c['subdir_ruta']) ? ' (sin firma)' : ''));
        $this->enviarArchivo($archivo, $c['tipo'] . '_' . $c['folio'] . (empty($c['subdir_ruta']) ? '_SIN_FIRMA' : '') . '.pdf', true);
    }

    private function soloBusqueda(): void
    {
        if (!Services\Constancias::puedeBuscar()) {
            $this->prohibido('Búsqueda de constancias');
        }
        $this->privado();
    }

    /** «Constancias → Búsqueda»: personal de Titulación, Jefatura y Subdirección. */
    public function buscar(): void
    {
        $this->soloBusqueda();
        $email = strtolower(trim((string) ($_GET['email'] ?? '')));
        $persona = null;
        $lista = [];
        if ($email !== '') {
            $lista = Services\Constancias::dePersona($email, Services\Constancias::alcance());
            $persona = \App\Core\Persona::completar(['nombre' => $lista[0]['nombre'] ?? $email], $email);
        }
        $this->vista('constancias', [
            'titulo' => 'Constancias · Búsqueda',
            'constancias' => $this->preparar($lista), 'persona' => $persona, 'email' => $email, 'busqueda' => true,
            'alcance' => Services\Constancias::alcance(),
        ]);
    }

    /** Sugerencias de profesores con constancias, por nombre o correo (JSON). */
    public function personas(): void
    {
        $this->soloBusqueda();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $salida = mb_strlen($q) >= 2 ? Services\Constancias::buscarPersonas($q, Services\Constancias::alcance()) : [];
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode(array_map(static fn(array $p): array => ['email' => $p['email'], 'nombre' => \App\Core\Persona::tratamiento($p), 'total' => (int) $p['total']], $salida), JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Las constancias que se eligieron (por su token), en un ZIP; cada una se revisa con el mismo permiso que para verla (las que esperan firma van sin firma). */
    public function seleccion(): void
    {
        $this->privado();
        $volver = (string) ($_POST['volver'] ?? '') === 'busqueda' ? 'titulacion/constancias/busqueda?email=' . rawurlencode(strtolower(trim((string) ($_POST['email'] ?? '')))) : 'titulacion/constancias';
        $ids = [];
        foreach (array_slice(is_array($_POST['c'] ?? null) ? $_POST['c'] : [], 0, 500) as $token) {
            $a = is_string($token) ? Services\Archivos::abrir($token) : null;
            if ($a !== null && $a[0] === 'constancia' && ctype_digit($a[1][0] ?? '')) {
                $ids[(int) $a[1][0]] = true;
            }
        }
        $lista = [];
        if ($ids) {
            $marcas = implode(',', array_fill(0, count($ids), '?'));
            $lista = Database::todas(
                "SELECT c.*, t.folio, d.subdir_ruta, d.ruta, COALESCE(u.nombre, JSON_UNQUOTE(JSON_EXTRACT(t.datos, '$.datos.nombre'))) AS alumno_nombre
                 FROM tit_constancias c JOIN tit_tramites t ON t.id = c.tramite_id LEFT JOIN usuarios u ON u.id = t.alumno_id
                 LEFT JOIN tit_documentos d ON d.id = c.documento_id WHERE c.id IN ($marcas)",
                array_keys($ids)
            );
        }
        foreach ($lista as &$c) {
            $c['archivo'] = $this->archivoConstancia($c);
        }
        unset($c);
        $lista = array_filter($lista, static fn(array $c): bool => $c['archivo'] !== null);
        if (!$lista) {
            flash('error', 'Elige al menos una constancia.');
            redirigir($volver);
        }
        $base = rtrim((string) \App\Core\App::config('archivos.storage'), '/\\');
        $tmp = tempnam(sys_get_temp_dir(), 'cns');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            flash('error', 'No se pudo armar el archivo.');
            redirigir($volver);
        }
        $usados = [];
        foreach ($lista as $c) {
            $abs = realpath($base . '/' . $c['archivo']);
            if ($abs === false || !str_starts_with($abs, realpath($base) . DIRECTORY_SEPARATOR)) {
                continue;
            }
            $nombre = Services\Constancias::anio($c) . '_' . trim(preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', str_replace(' · ' . Services\Constancias::anio($c), '', Services\Constancias::descripcion($c))) ?: (string) $c['tipo']) ?? '', '_');
            $nombre .= empty($c['subdir_ruta']) ? '_SIN_FIRMA' : '';
            $final = $nombre;
            for ($i = 2; isset($usados[$final]); $i++) {
                $final = $nombre . '_' . $i;
            }
            $usados[$final] = true;
            $zip->addFile($abs, $final . '.pdf');
        }
        $zip->close();
        $emails = array_unique(array_column($lista, 'email'));
        Audit::record('titulacion::constancias_zip', 'atendido', implode(', ', $emails) . ' · ' . count($usados));
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        $this->enviarBytes($bytes, 'constancias_' . (count($emails) === 1 ? preg_replace('/[^a-z0-9]+/', '_', (string) reset($emails)) : 'titulacion') . '.zip', 'application/zip');
    }

    // ----------------------------------------------------------------- pendientes

    /** Todo lo que le toca hacer a quien tiene la sesión en Titulación, con acceso directo (sin token). */
    public function pendientes(): void
    {
        $this->privado();
        $this->vista('pendientes', ['titulo' => 'Titulación · Mis pendientes', 'items' => $this->itemsPendientes()]);
    }

    /** Lo mismo, para Notificaciones (que concentra los pendientes de todos los módulos). */
    public static function pendientesDeSesion(): array
    {
        return (new self('titulacion'))->itemsPendientes();
    }

    private function itemsPendientes(): array
    {
        $email = TitulacionAccess::correoSesion();
        $items = [];
        $add = static function (string $tipo, string $texto, string $enlace, ?int $tramiteId, ?string $folio, ?string $fecha = null) use (&$items): void {
            $items[] = compact('tipo', 'texto', 'enlace', 'tramiteId', 'folio', 'fecha');
        };
        foreach (Database::todas(
            "SELECT f.*, t.folio, d.clave FROM tit_firmas_externas f JOIN tit_tramites t ON t.id = f.tramite_id JOIN tit_documentos d ON d.id = f.documento_id
             WHERE f.email = ? AND f.estado = 'pendiente' AND t.estado = 'abierto' ORDER BY f.id", [$email]) as $f) {
            $add('Firma', 'Firmar ' . Services\FirmasExternas::queSeFirma((string) $f['clave'])[0], 'titulacion/firma/mia/' . (int) $f['id'], (int) $f['tramite_id'], $f['folio'], $f['creado_en']);
        }
        foreach (RevisionRepository::delEvaluador($email) as $a) {
            if (in_array($a['estado'], ['pendiente', 'borrador'], true)) {
                $add('Revisión', 'Revisar el protocolo de ' . $a['alumno_nombre'] . ' (límite ' . date('d/m/Y', strtotime((string) $a['fecha_limite'])) . ')', 'titulacion/revision/' . (int) $a['id'], (int) $a['tramite_id'], $a['folio'], $a['asignada_en']);
            }
        }
        $carreras = TitulacionAccess::carrerasEncargado();
        if ($carreras) {
            foreach (RevisionRepository::rondas(['carreras' => $carreras, 'estados' => ['en_comision', 'por_dictaminar']]) as $r) {
                $add('Comité', ($r['estado'] === 'en_comision' ? 'Enviar a revisores o resolver' : 'Revisiones completas: enviar el resultado') . ' · ' . $r['alumno_nombre'], 'titulacion/comision/' . (int) $r['id'], (int) $r['tramite_id'], $r['folio'], $r['fecha_recepcion']);
            }
        }
        foreach (Database::todas("SELECT a.*, t.folio FROM tit_asignaciones_jurado a JOIN tit_tramites t ON t.id = a.tramite_id WHERE a.coord_email = ? AND a.estado = 'solicitada' ORDER BY a.id", [$email]) as $a) {
            $add('Sínodo', 'Ratificar o modificar el sínodo del ' . (Services\Oficios::EXAMEN[$a['tipo']] ?? 'examen') . ' del ' . Services\Oficios::fecha($a['fecha']), 'titulacion/jurado/mia/' . (int) $a['id'], (int) $a['tramite_id'], $a['folio'], $a['creado_en']);
        }
        foreach (Database::todas("SELECT s.*, a.tipo, a.fecha, t.folio FROM tit_sinodales s JOIN tit_asignaciones_jurado a ON a.id = s.asignacion_id JOIN tit_tramites t ON t.id = s.tramite_id WHERE s.email = ? AND s.estado = 'pendiente' ORDER BY s.id", [$email]) as $s) {
            $add('Oficio', 'Firmar de recibido el oficio del ' . (Services\Oficios::EXAMEN[$s['tipo']] ?? 'examen') . ' del ' . Services\Oficios::fecha($s['fecha']), 'titulacion/sinodal/mio/' . (int) $s['id'], (int) $s['tramite_id'], $s['folio'], $s['enviado_en']);
        }
        if (Services\SubdireccionFirma::puedeFirmar() && ($n = count(Services\SubdireccionFirma::pendientes())) > 0) {
            $add('Firmas', $n . ' documento(s) esperan tu firma electrónica', 'titulacion/firmas', null, null);
        }
        if (TitulacionAccess::esPersonal()) {
            $porValidar = (int) Database::valor("SELECT COUNT(DISTINCT tramite_id) FROM tit_documentos WHERE origen = 'alumno' AND estado = 'en_revision' AND pendiente_firmas = 0");
            if ($porValidar > 0) {
                $add('Validación', $porValidar . ' trámite(s) con documentos por validar', 'titulacion/gestion?pendientes=1', null, null);
            }
        }
        foreach (TitulacionRepository::delAlumno(Auth::id()) as $t) {
            if ($t['estado'] !== 'abierto') {
                continue;
            }
            $t = TitulacionRepository::obtener((int) $t['id']);
            [$flujo] = $this->evaluar($t);
            foreach ($flujo['etapas'] as $e) {
                if ($e['situacion'] === 'actual' && ($e['paso']['turno'] ?? '') === 'alumno') {
                    $add('Mi trámite', $e['titulo'] . ': ' . $e['paso']['texto'], 'titulacion/licenciatura/tramite/' . (int) $t['id'] . '?etapa=' . $e['clave'], (int) $t['id'], $t['folio']);
                }
            }
        }
        return $items;
    }
}
