<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * Asistencia y evidencias: QR y palabra clave del evento, constancias de los alumnos y su revisión, bitácora de
 * evidencias de Difusión Cultural, expediente de formación integral, verificación pública por folio y reporte por
 * carrera para los organismos acreditadores.
 */
final class AsistenciaController extends Controller
{
    private function evento(string $id): array
    {
        $ev = ctype_digit($id) ? Database::una('SELECT ev.*, d.nombre AS depto FROM eventos ev JOIN departamentos d ON d.id = ev.depto_id WHERE ev.id = ?', [(int) $id]) : null;
        if (!$ev) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'Evento no encontrado'], Auth::usuario() ? 'app' : 'auth');
            exit;
        }
        return $ev;
    }

    private function gestor(array $ev): void
    {
        if (!Clasificacion::puedeGestionar($ev)) {
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }

    private static function storage(): string
    {
        return rtrim((string) \App\Core\App::config('archivos')['storage'], '/\\');
    }

    // ------------------------------------------------------------------ QR y registro de asistencia

    /** Página para proyectar en el evento: QR, palabra clave e instrucciones. */
    public function qr(string $id): void
    {
        $ev = $this->evento($id);
        $this->gestor($ev);
        if (!$ev['qr_token']) {
            $ev['qr_token'] = bin2hex(random_bytes(16));
            Database::ejecutar('UPDATE eventos SET qr_token = ? WHERE id = ?', [$ev['qr_token'], (int) $ev['id']]);
        }
        $enlace = rtrim((string) \App\Core\App::config('app.url_publica', ''), '/') . url('eventos/' . (int) $ev['id'] . '/asistir/' . $ev['qr_token']);
        $this->vista('qr', ['titulo' => 'QR de asistencia', 'ev' => $ev, 'enlace' => $enlace], 'publico');
    }

    /** Quien escanea el QR confirma su asistencia (si no tiene sesión, vuelve aquí después de iniciarla). */
    public function asistir(string $id, string $token): void
    {
        $ev = $this->evento($id);
        if (!hash_equals((string) $ev['qr_token'], $token)) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'Enlace no válido'], Auth::usuario() ? 'app' : 'auth');
            exit;
        }
        if (!Auth::usuario()) {
            Auth::recordarDestino('eventos/' . (int) $ev['id'] . '/asistir/' . $token);
            flash('alerta', 'Inicia sesión para registrar tu asistencia a «' . $ev['titulo'] . '».');
            redirigir('login');
        }
        $this->vista('asistir', ['titulo' => 'Registrar asistencia', 'ev' => $ev, 'token' => $token,
            'asistencia' => Asistencias::de((int) $ev['id'], Auth::id()), 'abierta' => Asistencias::ventanaAbierta($ev)]);
    }

    public function confirmarQr(string $id, string $token): void
    {
        $ev = $this->evento($id);
        if (!Auth::usuario() || !hash_equals((string) $ev['qr_token'], $token)) {
            redirigir('eventos');
        }
        $this->registrarYVolver($ev, 'qr', []);
    }

    /** Palabra clave o constancia/capturas desde la ficha del evento. */
    public function registrar(string $id): void
    {
        $ev = $this->evento($id);
        if ($this->post('modo') === 'clave') {
            $clave = mb_strtoupper(preg_replace('/\s+/', '', $this->post('clave')) ?? '');
            if (\App\Core\AccessControl::ready() && \App\Core\AccessControl::limited('asistencia_clave|' . Auth::id(), 10, 3600)) {
                flash('error', 'Demasiados intentos. Intenta más tarde.');
                redirigir('eventos/' . (int) $ev['id']);
            }
            if ($ev['clave_asistencia'] === null || $clave === '' || !hash_equals((string) $ev['clave_asistencia'], $clave)) {
                flash('error', 'La palabra clave no coincide.');
                redirigir('eventos/' . (int) $ev['id']);
            }
            $this->registrarYVolver($ev, 'clave', []);
        }
        try {
            $archivos = guardar_archivos('archivos', 'eventos/asistencias/' . (int) $ev['id'], ['pdf', 'jpg', 'jpeg', 'png']);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('eventos/' . (int) $ev['id']);
        }
        if (!$archivos) {
            flash('error', 'Adjunta tu constancia o capturas de pantalla (PDF, JPG o PNG).');
            redirigir('eventos/' . (int) $ev['id']);
        }
        $this->registrarYVolver($ev, 'constancia', array_slice($archivos, 0, Asistencias::MAX_ARCHIVOS));
    }

    private function registrarYVolver(array $ev, string $metodo, array $archivos): never
    {
        try {
            $a = Asistencias::registrar($ev, $metodo, $archivos);
            Audit::record('eventos::asistencia', 'correcto', 'Evento #' . (int) $ev['id'] . ' · ' . $metodo);
            flash('exito', $a['estado'] === 'validada'
                ? 'Asistencia registrada (+' . (int) $a['puntos'] . ' puntos). Folio ' . $a['folio'] . '.'
                : 'Recibimos tu evidencia; Difusión Cultural la revisará. Folio ' . $a['folio'] . '.');
        } catch (\InvalidArgumentException $e) {
            foreach ($archivos as $f) {
                @unlink(self::storage() . '/' . $f['ruta']);
            }
            flash('error', $e->getMessage());
        }
        redirigir('eventos/' . (int) $ev['id']);
    }

    /** Difusión Cultural valida o rechaza una asistencia con constancia. */
    public function revisar(string $id, string $aid): void
    {
        $ev = $this->evento($id);
        $this->gestor($ev);
        Asistencias::revisar($ev, (int) $aid, $this->post('decision') === 'validar', $this->post('motivo'));
        Audit::record('eventos::revisar_asistencia', 'correcto', 'Evento #' . (int) $ev['id'] . ' · ' . (int) $aid);
        flash('exito', 'Revisión guardada.');
        redirigir('eventos/' . (int) $ev['id'] . '#asistencias');
    }

    /** Archivo de una asistencia: lo ven la persona que lo subió y quien gestiona el evento. */
    public function archivoAsistencia(string $aid, string $n): void
    {
        $a = ctype_digit($aid) ? Database::una('SELECT * FROM evento_asistencias WHERE id = ?', [(int) $aid]) : null;
        $ev = $a ? Database::una('SELECT * FROM eventos WHERE id = ?', [(int) $a['evento_id']]) : null;
        $archivos = $a ? (json_decode((string) $a['archivos'], true) ?: []) : [];
        $f = $archivos[(int) $n] ?? null;
        if (!$a || !$f || ((int) $a['usuario_id'] !== Auth::id() && !Clasificacion::puedeGestionar($ev))) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }
        descargar_archivo((string) $f['ruta']);
    }

    // ------------------------------------------------------------------ bitácora de evidencias

    public function agregarEvidencia(string $id): void
    {
        $ev = $this->evento($id);
        $this->gestor($ev);
        $tipo = array_key_exists($this->post('tipo'), Clasificacion::TIPOS_EVIDENCIA) ? $this->post('tipo') : 'otro';
        $programa = (int) $this->post('programa_id') ?: null;
        $desc = mb_substr($this->post('descripcion'), 0, 300) ?: null;
        $url = $this->post('url');
        if ($url !== '' && (!preg_match('#^https?://#i', $url) || !filter_var($url, FILTER_VALIDATE_URL) || strlen($url) > 500)) {
            flash('error', 'Revisa el enlace (debe empezar con https://).');
            redirigir('eventos/' . (int) $ev['id'] . '#evidencias');
        }
        try {
            $archivos = guardar_archivos('archivos', 'eventos/evidencias/' . (int) $ev['id'], ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'pptx', 'zip']);
        } catch (\InvalidArgumentException | \RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('eventos/' . (int) $ev['id'] . '#evidencias');
        }
        if (!$archivos && $url === '') {
            flash('error', 'Adjunta al menos un archivo o un enlace.');
            redirigir('eventos/' . (int) $ev['id'] . '#evidencias');
        }
        $sql = 'INSERT INTO evento_evidencias (evento_id, tipo, archivo, nombre, url, descripcion, programa_id, autor_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        foreach ($archivos as $f) {
            Database::ejecutar($sql, [(int) $ev['id'], $tipo, $f['ruta'], mb_substr((string) $f['nombre_original'], 0, 200), null, $desc, $programa, Auth::id()]);
        }
        if ($url !== '') {
            Database::ejecutar($sql, [(int) $ev['id'], $tipo, null, null, $url, $desc, $programa, Auth::id()]);
        }
        if ($tipo === 'grabacion' && $url !== '' && empty($ev['grabacion_url'])) {
            Database::ejecutar('UPDATE eventos SET grabacion_url = ? WHERE id = ?', [$url, (int) $ev['id']]);
        }
        Audit::record('eventos::evidencia', 'correcto', 'Evento #' . (int) $ev['id'] . ' · ' . $tipo);
        flash('exito', 'Evidencia agregada a la bitácora.');
        redirigir('eventos/' . (int) $ev['id'] . '#evidencias');
    }

    public function eliminarEvidencia(string $id, string $eid): void
    {
        $ev = $this->evento($id);
        $this->gestor($ev);
        $x = Database::una('SELECT * FROM evento_evidencias WHERE id = ? AND evento_id = ?', [(int) $eid, (int) $ev['id']]);
        if ($x) {
            Database::ejecutar('DELETE FROM evento_evidencias WHERE id = ?', [(int) $x['id']]);
            if ($x['archivo']) {
                @unlink(self::storage() . '/' . $x['archivo']);
            }
            Audit::record('eventos::evidencia_eliminada', 'correcto', 'Evento #' . (int) $ev['id'] . ' · ' . (int) $x['id']);
        }
        flash('exito', 'Evidencia eliminada.');
        redirigir('eventos/' . (int) $ev['id'] . '#evidencias');
    }

    /** Descarga de una evidencia de la bitácora: quien gestiona el evento y quien consulta el reporte de acreditación. */
    public function evidencia(string $eid): void
    {
        $x = ctype_digit($eid) ? Database::una('SELECT x.*, ev.depto_id FROM evento_evidencias x JOIN eventos ev ON ev.id = x.evento_id WHERE x.id = ?', [(int) $eid]) : null;
        if (!$x || !$x['archivo'] || (!Clasificacion::puedeVerReporte() && !Clasificacion::puedeGestionar($x))) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }
        descargar_archivo((string) $x['archivo']);
    }

    // ------------------------------------------------------------------ expediente y verificación

    public function expediente(): void
    {
        $asistencias = Asistencias::delUsuario(Auth::id());
        $this->vista('expediente', ['titulo' => 'Mi expediente de formación integral', 'asistencias' => $asistencias,
            'resumen' => Asistencias::resumen($asistencias), 'periodo' => Clasificacion::periodo(), 'temas' => Clasificacion::TEMAS]);
    }

    /** Verificación pública de una asistencia por folio (para profesores, empleadores u organismos acreditadores). */
    public function verificar(string $folio): void
    {
        header('X-Robots-Tag: noindex, nofollow');
        $this->vista('verificar', ['titulo' => 'Verificar asistencia', 'a' => Asistencias::porFolio(strtoupper($folio)), 'folio' => $folio, 'seccion' => 'Verificación'], 'publico');
    }

    // ------------------------------------------------------------------ reporte para acreditación

    /** @return array{0:array,1:array} [filtros, datos del reporte] */
    private function reporte(): array
    {
        $programa = (int) ($_GET['programa'] ?? 0);
        $periodo = Clasificacion::periodo();
        $anio = (int) substr($periodo, 0, 4);
        $desdeDef = str_ends_with($periodo, 'A') ? $anio . '-02-01' : $anio . '-08-01';
        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string) ($_GET['desde'] ?? '')) ? (string) $_GET['desde'] : $desdeDef;
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string) ($_GET['hasta'] ?? '')) ? (string) $_GET['hasta'] : date('Y-m-d');
        $eventos = Clasificacion::conProgramas(Database::todas(
            "SELECT ev.*, d.nombre AS depto FROM eventos ev JOIN departamentos d ON d.id = ev.depto_id
              WHERE ev.inicio BETWEEN ? AND ? AND (ev.formacion_integral = 1 OR EXISTS (SELECT 1 FROM evento_evidencias x WHERE x.evento_id = ev.id))
              ORDER BY ev.inicio",
            [$desde . ' 00:00:00', $hasta . ' 23:59:59']
        ));
        if ($programa) {
            $eventos = array_values(array_filter($eventos, static fn(array $e): bool => !$e['programas'] || in_array($programa, $e['programas'], true)
                || (bool) Database::valor("SELECT 1 FROM evento_asistencias WHERE evento_id = ? AND programa_id = ? AND estado = 'validada'", [(int) $e['id'], $programa])));
        }
        $ids = array_map(static fn(array $e): int => (int) $e['id'], $eventos) ?: [0];
        $en = implode(',', $ids);
        $asistentes = Database::todas(
            "SELECT a.*, u.nombre, u.email, p.siglas, ev.titulo, ev.inicio FROM evento_asistencias a JOIN usuarios u ON u.id = a.usuario_id
               JOIN eventos ev ON ev.id = a.evento_id LEFT JOIN programas p ON p.id = a.programa_id
              WHERE a.evento_id IN ({$en}) AND a.estado = 'validada'" . ($programa ? ' AND a.programa_id = ' . $programa : '') . ' ORDER BY ev.inicio, p.siglas, u.nombre'
        );
        $evidencias = Database::todas(
            "SELECT x.*, p.siglas, ev.titulo, ev.inicio FROM evento_evidencias x JOIN eventos ev ON ev.id = x.evento_id LEFT JOIN programas p ON p.id = x.programa_id
              WHERE x.evento_id IN ({$en})" . ($programa ? ' AND (x.programa_id IS NULL OR x.programa_id = ' . $programa . ')' : '') . ' ORDER BY ev.inicio, x.id'
        );
        $conteo = [];
        foreach ($asistentes as $a) {
            $conteo[(int) $a['evento_id']]['asistentes'] = ($conteo[(int) $a['evento_id']]['asistentes'] ?? 0) + 1;
        }
        foreach ($evidencias as $x) {
            $conteo[(int) $x['evento_id']]['evidencias'] = ($conteo[(int) $x['evento_id']]['evidencias'] ?? 0) + 1;
        }
        return [['programa' => $programa, 'desde' => $desde, 'hasta' => $hasta],
            ['eventos' => $eventos, 'asistentes' => $asistentes, 'evidencias' => $evidencias, 'conteo' => $conteo,
             'ranking' => Asistencias::ranking(Clasificacion::periodo($hasta), $programa)]];
    }

    private function soloReporte(): void
    {
        if (!Clasificacion::puedeVerReporte()) {
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }

    public function acreditacion(): void
    {
        $this->soloReporte();
        [$f, $r] = $this->reporte();
        $this->vista('acreditacion', ['titulo' => 'Evidencias por carrera', 'f' => $f, 'r' => $r,
            'programas' => Database::todas("SELECT id, siglas, nombre FROM programas ORDER BY nivel = 'posgrado', siglas"),
            'categorias' => EventosController::CATEGORIAS]);
    }

    public function acreditacionExcel(): void
    {
        $this->soloReporte();
        [$f, $r] = $this->reporte();
        $base = rtrim((string) \App\Core\App::config('app.url_publica', ''), '/');
        $siglas = array_column(Database::todas('SELECT id, siglas FROM programas'), 'siglas', 'id');
        $x = new \App\Modules\Formularios\XlsxEscritor();
        $titulo = 'Eventos de formación integral del ' . $f['desde'] . ' al ' . $f['hasta'] . ($f['programa'] ? ' · ' . ($siglas[$f['programa']] ?? '') : '');
        $filas = [[$titulo], ['Fecha', 'Evento', 'Formato', 'Tema', 'Modalidad', 'Organiza', 'Dirigido a', 'Horas', 'Asistentes validados', 'Evidencias', 'Post', 'Grabación', 'Ficha en Traza FI']];
        foreach ($r['eventos'] as $e) {
            $filas[] = [substr((string) $e['inicio'], 0, 16), $e['titulo'], EventosController::CATEGORIAS[$e['categoria']] ?? $e['categoria'],
                Clasificacion::TEMAS[$e['tema']] ?? $e['tema'], Clasificacion::MODALIDADES[$e['modalidad'] ?? ''] ?? '', (string) $e['organizador'],
                Clasificacion::destino($e, $siglas), (string) $e['horas'], (string) ($r['conteo'][(int) $e['id']]['asistentes'] ?? 0),
                (string) ($r['conteo'][(int) $e['id']]['evidencias'] ?? 0), (string) $e['post_url'], (string) $e['grabacion_url'], $base . url('eventos/' . (int) $e['id'])];
        }
        $x->hoja('Eventos', $filas);
        $filas = [[$titulo], ['Fecha', 'Evento', 'Nombre', 'Correo', 'Carrera', 'Semestre', 'Registro', 'Puntos', 'Folio', 'Verificación']];
        foreach ($r['asistentes'] as $a) {
            $filas[] = [substr((string) $a['inicio'], 0, 10), $a['titulo'], $a['nombre'], $a['email'], (string) $a['siglas'], (string) $a['semestre'],
                ['qr' => 'QR', 'clave' => 'Palabra clave', 'constancia' => 'Constancia'][$a['metodo']] ?? $a['metodo'], (string) $a['puntos'], $a['folio'],
                $base . url('verificar/asistencia/' . $a['folio'])];
        }
        $x->hoja('Asistentes', $filas);
        $filas = [[$titulo], ['Fecha', 'Evento', 'Tipo', 'Carrera', 'Descripción', 'Archivo o enlace']];
        foreach ($r['evidencias'] as $ev) {
            $filas[] = [substr((string) $ev['inicio'], 0, 10), $ev['titulo'], Clasificacion::TIPOS_EVIDENCIA[$ev['tipo']] ?? $ev['tipo'], (string) ($ev['siglas'] ?? 'Todas'),
                (string) $ev['descripcion'], $ev['archivo'] ? $base . url_archivo('eventos/evidencia/' . (int) $ev['id']) : (string) $ev['url']];
        }
        $x->hoja('Evidencias', $filas);
        $bytes = $x->generar();
        Audit::record('eventos::acreditacion_excel', 'atendido', $f['desde'] . ' a ' . $f['hasta']);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="evidencias-formacion-integral.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store, private');
        echo $bytes;
        exit;
    }
}
