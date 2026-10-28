<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * Formulario de Difusión Cultural para publicar y editar avisos de eventos con todo lo necesario para filtrarlos
 * (público, carreras, semestres, tema, formato, modalidad), el post de Facebook (imagen y enlace), el enlace del
 * evento y su configuración de asistencia (puntos, horas, palabra clave y QR). También guarda las preferencias de
 * avisos del perfil y sugiere la clasificación con la guía para IA.
 */
final class AvisosController extends Controller
{
    private function evento(string $id): array
    {
        $ev = ctype_digit($id) ? Database::una('SELECT * FROM eventos WHERE id = ?', [(int) $id]) : null;
        if (!$ev) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'Evento no encontrado']);
            exit;
        }
        return $ev;
    }

    private function prohibido(): never
    {
        http_response_code(403);
        View::render('errores/403', ['titulo' => 'Acceso restringido']);
        exit;
    }

    private function formulario(array $ev, array $programas): void
    {
        $emisores = Auth::tieneRol('admin') || Clasificacion::esDifusion()
            ? Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre')
            : Database::todas('SELECT id, nombre FROM departamentos WHERE id IN (' . implode(',', Auth::departamentoIds() ?: [0]) . ') ORDER BY nombre');
        $this->vista('formulario', [
            'titulo' => $ev ? 'Editar aviso' : 'Publicar aviso de evento',
            'ev' => $ev, 'programasEv' => $programas,
            'categorias' => EventosController::CATEGORIAS,
            'programas' => Database::todas("SELECT id, siglas, nombre, nivel FROM programas ORDER BY nivel = 'posgrado', siglas"),
            'emisores' => $emisores,
            'previo' => $_SESSION['aviso_previo'] ?? null,
        ]);
        unset($_SESSION['aviso_previo']);
    }

    public function nuevo(): void
    {
        if (!Clasificacion::puedePublicar()) {
            $this->prohibido();
        }
        $this->formulario([], []);
    }

    public function editar(string $id): void
    {
        $ev = $this->evento($id);
        if (!Clasificacion::puedeGestionar($ev)) {
            $this->prohibido();
        }
        $this->formulario($ev, array_map('intval', array_column(Database::todas('SELECT programa_id FROM evento_programas WHERE evento_id = ?', [(int) $ev['id']]), 'programa_id')));
    }

    private static function fecha(string $v): ?string
    {
        $t = $v !== '' ? strtotime(str_replace('T', ' ', $v)) : false;
        return $t ? date('Y-m-d H:i:s', $t) : null;
    }

    private static function enlace(string $v): ?string
    {
        $v = trim($v);
        return $v !== '' && preg_match('#^https?://#i', $v) && filter_var($v, FILTER_VALIDATE_URL) && strlen($v) <= 500 ? $v : null;
    }

    /** Publica (sin id) o actualiza un aviso. */
    public function guardar(string $id = ''): void
    {
        $ev = $id !== '' ? $this->evento($id) : [];
        if ($ev ? !Clasificacion::puedeGestionar($ev) : !Clasificacion::puedePublicar()) {
            $this->prohibido();
        }
        $volver = $ev ? 'eventos/' . (int) $ev['id'] . '/editar' : 'eventos/nuevo';
        $errores = [];
        $categorias = EventosController::CATEGORIAS;
        $d = [
            'titulo' => mb_substr($this->post('titulo'), 0, 180),
            'descripcion' => mb_substr($this->post('descripcion'), 0, 5000),
            'categoria' => array_key_exists($this->post('categoria'), $categorias) ? $this->post('categoria') : 'otro',
            'tema' => array_key_exists($this->post('tema'), Clasificacion::TEMAS) ? $this->post('tema') : 'otro',
            'publico' => implode(',', array_values(array_intersect(array_keys(Clasificacion::PUBLICOS), (array) ($_POST['publico'] ?? [])))),
            'semestre_min' => (int) $this->post('semestre_min') ?: null,
            'semestre_max' => (int) $this->post('semestre_max') ?: null,
            'inicio' => self::fecha($this->post('inicio')),
            'fin' => self::fecha($this->post('fin')),
            'lugar' => mb_substr($this->post('lugar'), 0, 160) ?: null,
            'modalidad' => array_key_exists($this->post('modalidad'), Clasificacion::MODALIDADES) ? $this->post('modalidad') : null,
            'organizador' => mb_substr($this->post('organizador'), 0, 200) ?: null,
            'enlace_evento' => self::enlace($this->post('enlace_evento')),
            'requiere_registro' => $this->post('requiere_registro') !== '' ? 1 : 0,
            'fecha_limite' => self::fecha($this->post('fecha_limite')),
            'post_url' => self::enlace($this->post('post_url')),
            'grabacion_url' => self::enlace($this->post('grabacion_url')),
            'evidencia_post_url' => self::enlace($this->post('evidencia_post_url')),
            'formacion_integral' => $this->post('formacion_integral') !== '' ? 1 : 0,
            'horas' => $this->post('horas') !== '' ? max(0, min(99, round((float) $this->post('horas'), 1))) : null,
            'puntos' => max(0, min(50, (int) $this->post('puntos'))),
            'clave_asistencia' => mb_strtoupper(mb_substr(preg_replace('/\s+/', '', $this->post('clave_asistencia')) ?? '', 0, 30)) ?: null,
        ];
        foreach (['semestre_min', 'semestre_max'] as $k) {
            if ($d[$k] !== null && ($d[$k] < 1 || $d[$k] > Clasificacion::SEMESTRE_MAX)) {
                $d[$k] = null;
            }
        }
        if ($d['semestre_min'] !== null && $d['semestre_max'] !== null && $d['semestre_min'] > $d['semestre_max']) {
            [$d['semestre_min'], $d['semestre_max']] = [$d['semestre_max'], $d['semestre_min']];
        }
        $programas = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['programas'] ?? [])))));
        $deptoId = (int) $this->post('depto_id');
        $permitidos = Clasificacion::esDifusion() ? array_map('intval', array_column(Database::todas('SELECT id FROM departamentos'), 'id')) : Auth::departamentoIds();
        $deptoId = in_array($deptoId, $permitidos, true) ? $deptoId : (int) ($ev['depto_id'] ?? Auth::departamentoId());

        if ($d['titulo'] === '' || $d['descripcion'] === '' || $d['inicio'] === null) {
            $errores[] = 'Completa título, descripción e inicio.';
        }
        if ($d['fin'] !== null && $d['inicio'] !== null && $d['fin'] < $d['inicio']) {
            $errores[] = 'El fin no puede ser antes del inicio.';
        }
        foreach (['enlace_evento' => 'del evento', 'post_url' => 'del post', 'grabacion_url' => 'de la grabación', 'evidencia_post_url' => 'de la publicación de la evidencia'] as $k => $n) {
            if ($this->post($k) !== '' && $d[$k] === null) {
                $errores[] = 'Revisa el enlace ' . $n . ' (debe empezar con https://).';
            }
        }
        if ($d['post_url'] === null) {
            $errores[] = 'Agrega el enlace del post de Facebook.';
        }
        if (in_array($d['modalidad'], ['en_linea', 'hibrida'], true) && $d['enlace_evento'] === null) {
            $errores[] = 'Un evento en línea o híbrido necesita el enlace para conectarse.';
        }
        $imagen = $_FILES['post_imagen'] ?? null;
        $hayImagen = is_array($imagen) && ($imagen['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        if (!$hayImagen && empty($ev['post_imagen'])) {
            $errores[] = 'Adjunta la imagen del post de Facebook.';
        }
        if ($d['formacion_integral'] && $d['clave_asistencia'] === null) {
            $d['clave_asistencia'] = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        }
        if (!$errores && $hayImagen) {
            try {
                $d['post_imagen'] = guardar_archivo_datos($imagen, 'eventos/posts', ['jpg', 'jpeg', 'png'], 5 * 1048576);
            } catch (\InvalidArgumentException | \RuntimeException $e) {
                $errores[] = 'Imagen del post: ' . $e->getMessage();
            }
        }
        if ($errores) {
            $_SESSION['aviso_previo'] = $d + ['programas' => $programas, 'depto_id' => $deptoId];
            flash('error', implode(' ', array_slice($errores, 0, 4)));
            redirigir($volver);
        }

        $d['depto_id'] = $deptoId;
        if ($ev) {
            $sets = implode(', ', array_map(static fn(string $k): string => $k . ' = ?', array_keys($d)));
            Database::ejecutar("UPDATE eventos SET {$sets}, actualizado_en = NOW() WHERE id = ?", [...array_values($d), (int) $ev['id']]);
            $eid = (int) $ev['id'];
            if (isset($d['post_imagen']) && !empty($ev['post_imagen'])) {
                @unlink(rtrim((string) \App\Core\App::config('archivos')['storage'], '/') . '/' . $ev['post_imagen']);
            }
        } else {
            $d += ['autor_id' => Auth::id(), 'qr_token' => bin2hex(random_bytes(16))];
            $eid = Database::ejecutar('INSERT INTO eventos (' . implode(', ', array_keys($d)) . ') VALUES (' . implode(', ', array_fill(0, count($d), '?')) . ')', array_values($d));
        }
        Database::ejecutar('DELETE FROM evento_programas WHERE evento_id = ?', [$eid]);
        foreach ($programas as $pid) {
            Database::ejecutar('INSERT IGNORE INTO evento_programas (evento_id, programa_id) SELECT ?, id FROM programas WHERE id = ?', [$eid, $pid]);
        }
        $avisados = 0;
        if (!$ev && $this->post('notificar') !== '') {
            $avisados = Clasificacion::notificar(Database::una('SELECT * FROM eventos WHERE id = ?', [$eid]));
        }
        Audit::record('eventos::' . ($ev ? 'editar' : 'publicar'), 'correcto', 'Evento #' . $eid);
        flash('exito', $ev ? 'Aviso actualizado.' : 'Aviso publicado' . ($avisados ? '; se avisó a ' . $avisados . ' persona(s) que siguen el tema.' : '.'));
        redirigir('eventos/' . $eid);
    }

    /** Imagen del post (se muestra en la ficha del evento). */
    public function imagen(string $id): void
    {
        $ev = $this->evento($id);
        $base = realpath((string) \App\Core\App::config('archivos')['storage']);
        $ruta = $ev['post_imagen'] ? realpath($base . '/' . $ev['post_imagen']) : false;
        if (!$base || !$ruta || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR) || !is_file($ruta)) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: ' . ((new \finfo(FILEINFO_MIME_TYPE))->file($ruta) === 'image/png' ? 'image/png' : 'image/jpeg'));
        header('Content-Length: ' . filesize($ruta));
        header('Cache-Control: private, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($ruta);
        exit;
    }

    /** Sugerencia de clasificación (JSON) para el formulario. */
    public function sugerir(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        if (!Clasificacion::puedePublicar()) {
            echo json_encode(['ok' => false]);
            return;
        }
        $s = Clasificacion::sugerir($this->post('titulo'), $this->post('descripcion'), EventosController::CATEGORIAS);
        echo json_encode(['ok' => true] + $s, JSON_UNESCAPED_UNICODE);
    }

    /** Preferencias de avisos del perfil: semestre (alumnos) y temas de interés. */
    public function preferencias(): void
    {
        $semestre = (int) $this->post('semestre');
        Clasificacion::guardarPreferencias(Auth::id(), $semestre ?: null, (array) ($_POST['temas'] ?? []), $this->post('notificar') !== '');
        flash('exito', 'Tus preferencias de avisos se guardaron.');
        $volver = $this->post('volver');
        redirigir(in_array($volver, ['perfil', 'eventos'], true) ? $volver : 'perfil');
    }
}
