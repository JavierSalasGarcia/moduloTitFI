<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * Avisos de eventos (reuniones, conferencias…) emitidos por los
 * departamentos. Los usuarios los agregan con un clic a su calendario
 * personal (menú RECURSOS → Mi Calendario). Incluye buscador y una
 * biblioteca de eventos concluidos en carruseles por categoría.
 */
final class EventosController extends Controller
{
    public const CATEGORIAS = [
        'conferencia' => 'Conferencias',
        'taller'      => 'Talleres',
        'curso'       => 'Cursos',
        'academico'   => 'Académicos',
        'cultural'    => 'Culturales',
        'deportivo'   => 'Deportivos',
        'otro'        => 'Otros',
    ];

    public function index(): void
    {
        $u = Auth::usuario();
        $puedeEmitirEventos = Auth::tieneRol('admin', 'titular', 'operativo');
        $puedeEmitirComunicados = $this->puedeEmitirComunicados();

        // 1. Obtener Eventos
        [$condicion, $params] = $this->filtros();
        $eventos = Database::todas(
            "SELECT ev.*, d.nombre AS depto,
                    EXISTS(SELECT 1 FROM evento_suscripciones s WHERE s.evento_id = ev.id AND s.usuario_id = ?) AS suscrito,
                    (SELECT COUNT(*) FROM evento_suscripciones s2 WHERE s2.evento_id = ev.id) AS interesados
               FROM eventos ev JOIN departamentos d ON d.id = ev.depto_id
              WHERE COALESCE(ev.fin, ev.inicio) >= NOW() {$condicion}
              ORDER BY ev.inicio",
            [Auth::id(), ...$params]
        );

        // 2. Obtener Comunicados
        $sqlC = 'SELECT c.*, d.nombre AS depto, u2.nombre AS autor, p.siglas,
                   (SELECT a.valor FROM ajustes a WHERE a.clave = CONCAT(\'comunicado_vence_\', c.id)) AS vence_en
                   FROM comunicados c
                   JOIN departamentos d ON d.id = c.depto_id
                   JOIN usuarios u2 ON u2.id = c.autor_id
                   LEFT JOIN programas p ON p.id = c.programa_id';
        $condsC = [];
        $paramsC = [];
        if ($u['rol'] === 'alumno' && $u['programa_id']) {
            $nivel = $u['programa_nivel'] === 'posgrado' ? 'posgrado' : 'licenciatura';
            $condsC[] = "(c.alcance = 'todos' OR c.alcance = ? OR (c.alcance = 'programa' AND c.programa_id = ?))";
            $paramsC[] = $nivel;
            $paramsC[] = $u['programa_id'];
        }
        if (!$puedeEmitirComunicados) {
            $condsC[] = "c.categoria != 'Ocultar'";
        }
        if (!empty($condsC)) {
            $sqlC .= ' WHERE ' . implode(' AND ', $condsC);
        }
        $comunicados = Database::todas($sqlC . ' ORDER BY CASE WHEN c.categoria = \'Importantes\' THEN 1 ELSE 2 END, c.creado_en DESC', $paramsC);
        $archivo = ($_GET['avisos'] ?? '') === 'archivo';
        $comunicados = array_values(array_filter($comunicados, static function($c) use ($archivo) {
            $vencido = !empty($c['vence_en']) && $c['vence_en'] < date('Y-m-d');
            if ($archivo !== $vencido) return false;
            return empty($_GET['recientes']) || $c['creado_en'] >= date('Y-m-d H:i:s', strtotime('-14 days'));
        }));

        $this->vista('index', [
            'titulo'     => 'Avisos y eventos',
            'eventos'    => $eventos,
            'comunicados' => $comunicados,
            'avisosArchivo' => $archivo,
            'q'          => trim((string) ($_GET['q'] ?? '')),
            'categoria'  => $_GET['categoria'] ?? '',
            'categorias' => self::CATEGORIAS,
            'puedeEmitir'=> $puedeEmitirEventos,
            'puedeEmitirComunicados' => $puedeEmitirComunicados,
            'departamentos' => Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre'),
            'usuarios'      => Database::todas("SELECT id, nombre, email, rol FROM usuarios WHERE activo = 1 ORDER BY nombre"),
            'programas'     => Database::todas("SELECT * FROM programas ORDER BY nivel = 'posgrado', siglas"),
        ]);
    }

    private function puedeEmitirComunicados(): bool
    {
        if (Auth::rol() === 'admin') {
            return true;
        }
        if (!Auth::tieneRol('titular', 'operativo')) {
            return false;
        }
        $clave = Auth::usuario()['depto_clave'];
        return in_array($clave, [
            'coordinacion_de_estudios_avanzados',
            'departamento_de_servicios_escolares',
            'coordinacion_de_difusion_cultural',
        ], true) || str_starts_with($clave, 'coordinacion_de_docencia_de_la_licenciatura');
    }

    /** Biblioteca de eventos concluidos: carruseles por categoría. */
    public function biblioteca(): void
    {
        [$condicion, $params] = $this->filtros();
        $pasados = Database::todas(
            "SELECT ev.*, d.nombre AS depto
               FROM eventos ev JOIN departamentos d ON d.id = ev.depto_id
              WHERE COALESCE(ev.fin, ev.inicio) < NOW() {$condicion}
              ORDER BY ev.inicio DESC",
            $params
        );

        $porCategoria = [];
        foreach ($pasados as $ev) {
            $porCategoria[$ev['categoria']][] = $ev;
        }
        // Presentar en el orden del catálogo de categorías
        $porCategoria = array_replace(
            array_intersect_key(array_fill_keys(array_keys(self::CATEGORIAS), []), $porCategoria),
            $porCategoria
        );

        $this->vista('biblioteca', [
            'titulo'       => 'Biblioteca de eventos',
            'porCategoria' => array_filter($porCategoria),
            'q'            => trim((string) ($_GET['q'] ?? '')),
            'categorias'   => self::CATEGORIAS,
        ]);
    }

    public function detalle(string $id): void
    {
        $ev = Database::una(
            'SELECT ev.*, d.nombre AS depto, u.nombre AS autor,
                    EXISTS(SELECT 1 FROM evento_suscripciones s WHERE s.evento_id = ev.id AND s.usuario_id = ?) AS suscrito
               FROM eventos ev
               JOIN departamentos d ON d.id = ev.depto_id
               JOIN usuarios u ON u.id = ev.autor_id
              WHERE ev.id = ?',
            [Auth::id(), (int) $id]);
        if (!$ev) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'Evento no encontrado']);
            return;
        }

        $destinatarios = Database::todas(
            "SELECT ed.*, 
                    CASE 
                        WHEN ed.destinatario_type = 'usuario' THEN u.nombre
                        WHEN ed.destinatario_type = 'departamento' THEN d.nombre
                    END AS destinatario_nombre 
               FROM evento_destinatarios ed
               LEFT JOIN usuarios u ON u.id = ed.destinatario_id AND ed.destinatario_type = 'usuario'
               LEFT JOIN departamentos d ON d.id = ed.destinatario_id AND ed.destinatario_type = 'departamento'
              WHERE ed.evento_id = ? ORDER BY ed.tipo, ed.id",
            [$ev['id']]
        );

        $this->vista('detalle', [
            'titulo'     => 'Evento',
            'ev'         => $ev,
            'destinatarios' => $destinatarios,
            'concluido'  => strtotime($ev['fin'] ?: $ev['inicio']) < time(),
            'categorias' => self::CATEGORIAS,
        ]);
    }

    /** Los departamentos emiten avisos (personal de la plataforma). */
    public function crear(): void
    {
        if (!Auth::tieneRol('admin', 'titular', 'operativo')) {
            flash('error', 'Solo los departamentos emiten avisos de eventos.');
            redirigir('eventos');
        }
        $titulo = $this->post('titulo');
        $desc   = $this->post('descripcion');
        $inicio = $this->post('inicio');
        $cat    = array_key_exists($this->post('categoria'), self::CATEGORIAS) ? $this->post('categoria') : 'otro';

        if ($titulo === '' || $desc === '' || !strtotime($inicio)) {
            flash('error', 'Completa título, descripción y fecha de inicio.');
            redirigir('eventos');
        }

        $id = Database::ejecutar(
            'INSERT INTO eventos (titulo, descripcion, categoria, depto_id, autor_id, inicio, fin, lugar)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$titulo, $desc, $cat, Auth::departamentoId(), Auth::id(),
             date('Y-m-d H:i:s', strtotime($inicio)),
             strtotime((string) $this->post('fin')) ? date('Y-m-d H:i:s', strtotime($this->post('fin'))) : null,
             $this->post('lugar') ?: null]
         );

        // Guardar destinatarios Para
        $paraType = $_POST['para_type'] ?? [];
        $paraId = $_POST['para_id'] ?? [];
        foreach ($paraId as $idx => $pId) {
            Database::ejecutar(
                "INSERT INTO evento_destinatarios (evento_id, tipo, destinatario_type, destinatario_id) VALUES (?, 'para', ?, ?)",
                [$id, $paraType[$idx], (int)$pId]
            );
        }

        // Guardar destinatarios CC
        $ccType = $_POST['cc_type'] ?? [];
        $ccId = $_POST['cc_id'] ?? [];
        foreach ($ccId as $idx => $cId) {
            Database::ejecutar(
                "INSERT INTO evento_destinatarios (evento_id, tipo, destinatario_type, destinatario_id) VALUES (?, 'cc', ?, ?)",
                [$id, $ccType[$idx], (int)$cId]
            );
        }

        flash('exito', 'Aviso publicado; la comunidad ya puede agregarlo a su calendario.');
        redirigir('eventos/' . $id);
    }

    /** Alterna la suscripción: agrega o quita el evento del calendario personal. */
    public function suscribir(string $id): void
    {
        $ev = Database::una('SELECT id FROM eventos WHERE id = ?', [(int) $id]);
        if ($ev) {
            $habia = Database::una(
                'SELECT 1 FROM evento_suscripciones WHERE evento_id = ? AND usuario_id = ?', [$ev['id'], Auth::id()]);
            if ($habia) {
                Database::ejecutar('DELETE FROM evento_suscripciones WHERE evento_id = ? AND usuario_id = ?', [$ev['id'], Auth::id()]);
                flash('alerta', 'Evento retirado de tu calendario.');
            } else {
                Database::ejecutar('INSERT INTO evento_suscripciones (evento_id, usuario_id) VALUES (?, ?)', [$ev['id'], Auth::id()]);
                flash('exito', 'Evento agregado a tu calendario (menú MI ESPACIO → Mi Calendario).');
            }
        }
        redirigir($_POST['volver'] ?? 'eventos');
    }

    /** Buscador: texto libre (?q=) y categoría (?categoria=). */
    private function filtros(): array
    {
        $condicion = '';
        $params = [];
        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $condicion .= ' AND (ev.titulo LIKE ? OR ev.descripcion LIKE ? OR ev.lugar LIKE ? OR d.nombre LIKE ?)';
            $params = array_merge($params, array_fill(0, 4, "%{$q}%"));
        }
        $cat = $_GET['categoria'] ?? '';
        if (array_key_exists($cat, self::CATEGORIAS)) {
            $condicion .= ' AND ev.categoria = ?';
            $params[] = $cat;
        }
        return [$condicion, $params];
    }

    public function exportarIcs(string $id): void
    {
        $ev = Database::una(
            'SELECT ev.*, d.nombre AS depto 
               FROM eventos ev 
               JOIN departamentos d ON d.id = ev.depto_id 
              WHERE ev.id = ?', 
            [(int) $id]
        );

        if (!$ev) {
            echo "Evento no encontrado.";
            exit;
        }

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="evento_' . $ev['id'] . '.ics"');

        $dtstart = date('Ymd\THis', strtotime($ev['inicio']));
        $dtend = date('Ymd\THis', strtotime($ev['fin'] ?? $ev['inicio'] . ' +1 hour'));
        $summary = $ev['titulo'];
        $description = strip_tags($ev['descripcion'] ?? $ev['contenido'] ?? '');
        $location = $ev['lugar'] ?: 'Facultad de Ingeniería, UAEMEX';
        $uid = "evento_{$ev['id']}@trazafi.uaemex.mx";

        echo "BEGIN:VCALENDAR\r\n";
        echo "VERSION:2.0\r\n";
        echo "PRODID:-//Traza FI//Calendario Eventos//ES\r\n";
        echo "BEGIN:VEVENT\r\n";
        echo "UID:$uid\r\n";
        echo "DTSTAMP:" . date('Ymd\THis\Z') . "\r\n";
        echo "DTSTART:$dtstart\r\n";
        echo "DTEND:$dtend\r\n";
        echo "SUMMARY:" . $this->sanitizeIcsText($summary) . "\r\n";
        echo "DESCRIPTION:" . $this->sanitizeIcsText($description) . "\r\n";
        echo "LOCATION:" . $this->sanitizeIcsText($location) . "\r\n";
        echo "END:VEVENT\r\n";
        echo "END:VCALENDAR\r\n";
        exit;
    }

    private function sanitizeIcsText(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\n"], "\\n", $text);
        $text = str_replace(",", "\\,", $text);
        $text = str_replace(";", "\\;", $text);
        return $text;
    }
}
