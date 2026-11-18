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
 * personal (menú RECURSOS → Mi Calendario). Incluye buscador, filtros por
 * carrera, semestre, tema y formato (ver Clasificacion) y una biblioteca de
 * eventos concluidos en carruseles por categoría. La publicación, la
 * asistencia y las evidencias están en AvisosController y AsistenciaController.
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
        'convocatoria'=> 'Convocatorias',
        'otro'        => 'Otros',
    ];

    public function index(): void
    {
        if (!Auth::usuario()) {
            $this->indexPublico();
            return;
        }
        $puedeEmitirEventos = Clasificacion::puedePublicar();
        $puedeEmitirComunicados = $this->puedeEmitirComunicados();

        // 1. Eventos próximos (con los filtros de carrera, semestre, tema… de Clasificacion)
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
        $perfil = Clasificacion::perfil();
        $filtros = Clasificacion::filtrosGet($perfil, self::CATEGORIAS);
        $eventos = Clasificacion::filtrar(Clasificacion::conProgramas($eventos), $filtros, $perfil);

        // 2. Avisos oficiales: cada aviso aparece una sola vez, aunque la persona pertenezca a varios programas destinatarios
        $comunicados = \App\Modules\Comunicados\Avisos::visibles($puedeEmitirComunicados);
        $archivo = ($_GET['avisos'] ?? '') === 'archivo';
        $comunicados = array_values(array_filter($comunicados, static function($c) use ($archivo) {
            $vencido = !empty($c['vence_en']) && $c['vence_en'] < date('Y-m-d');
            return $archivo === $vencido;
        }));

        $this->vista('index', [
            'titulo'     => 'Avisos y eventos',
            'items'      => self::lista($comunicados, $archivo ? [] : $eventos, Lecturas::leidos()),
            'eventos'    => $eventos,
            'avisosArchivo' => $archivo,
            'categorias' => self::CATEGORIAS,
            'puedeEmitir'=> $puedeEmitirEventos,
            'puedeEmitirComunicados' => $puedeEmitirComunicados,
            'emisores'      => \App\Modules\Comunicados\Avisos::emisores(),
            'programas'     => Database::todas("SELECT * FROM programas ORDER BY nivel = 'posgrado', siglas"),
            'filtros'       => $filtros,
            'perfil'        => $perfil,
            'preferencias'  => Clasificacion::preferencias(Auth::id()),
            'visitante'     => false,
        ]);
    }

    /** Consulta sin sesión: solo los avisos marcados como públicos y vigentes. */
    private function indexPublico(): void
    {
        $hoy = date('Y-m-d');
        $comunicados = [];
        try {
            $comunicados = Database::todas(
                "SELECT c.*, d.nombre AS depto, u2.nombre AS autor, p.siglas,
                        (SELECT a.valor FROM ajustes a WHERE a.clave = CONCAT('comunicado_vence_', c.id)) AS vence_en
                   FROM comunicados c JOIN departamentos d ON d.id = c.depto_id JOIN usuarios u2 ON u2.id = c.autor_id
                   LEFT JOIN programas p ON p.id = c.programa_id
                  WHERE c.publico = 1 AND c.categoria != 'Ocultar'
                  ORDER BY CASE WHEN c.categoria = 'Importantes' THEN 1 ELSE 2 END, c.creado_en DESC");
        } catch (\Throwable) {
        }
        $comunicados = array_values(array_filter($comunicados, static fn($c) => empty($c['vence_en']) || $c['vence_en'] >= $hoy));
        foreach ($comunicados as &$c) {
            $c['programas_lista'] = array_column(Database::todas('SELECT p.siglas FROM comunicado_programas cp JOIN programas p ON p.id = cp.programa_id WHERE cp.comunicado_id = ? ORDER BY p.siglas', [(int) $c['id']]), 'siglas');
        }
        unset($c);
        $this->vista('index', [
            'titulo' => 'Avisos', 'seccion' => 'Avisos', 'items' => self::lista($comunicados, [], ['comunicado' => [], 'evento' => []]),
            'visitante' => true, 'avisosArchivo' => false, 'puedeEmitir' => false, 'puedeEmitirComunicados' => false,
        ], 'publico');
    }

    /** Une avisos y eventos en una sola lista con su tipo para los filtros (Avisos oficiales, Convocatorias, Eventos, Exámenes). */
    private static function lista(array $comunicados, array $eventos, array $leidos): array
    {
        $items = [];
        $adjuntos = [];
        if ($comunicados) {
            $ids = array_map('intval', array_column($comunicados, 'id'));
            try {
                foreach (Database::todas('SELECT comunicado_id, COUNT(*) n FROM comunicado_adjuntos WHERE comunicado_id IN (' . implode(',', $ids) . ') GROUP BY comunicado_id') as $r) {
                    $adjuntos[(int) $r['comunicado_id']] = (int) $r['n'];
                }
            } catch (\Throwable) {
            }
        }
        foreach ($comunicados as $c) {
            $clase = $c['clase'] ?? 'aviso';
            $items[] = ['tipo' => 'comunicado', 'id' => (int) $c['id'], 'filtro' => ['aviso' => 'avisos', 'convocatoria' => 'convocatorias', 'examenes' => 'examenes'][$clase] ?? 'avisos',
                'titulo' => $c['titulo'], 'texto' => $c['contenido'], 'fecha' => $c['creado_en'], 'origen' => $c['depto'],
                'destino' => \App\Modules\Comunicados\Avisos::destino($c), 'importante' => $c['categoria'] === 'Importantes',
                'borrador' => $c['categoria'] === 'Ocultar', 'publico' => (int) ($c['publico'] ?? 0) === 1, 'vence' => $c['vence_en'] ?? null,
                'adjuntos' => $adjuntos[(int) $c['id']] ?? 0, 'leido' => !empty($leidos['comunicado'][(int) $c['id']]), 'url' => AvisoController::ruta($c), 'dato' => $c];
        }
        foreach ($eventos as $ev) {
            $items[] = ['tipo' => 'evento', 'id' => (int) $ev['id'], 'filtro' => $ev['categoria'] === 'convocatoria' ? 'convocatorias' : 'eventos',
                'titulo' => $ev['titulo'], 'texto' => (string) ($ev['descripcion'] ?? ''), 'fecha' => $ev['creado_en'] ?? $ev['inicio'], 'origen' => $ev['depto'],
                'importante' => false, 'borrador' => false, 'leido' => !empty($leidos['evento'][(int) $ev['id']]), 'url' => 'eventos/' . (int) $ev['id'], 'dato' => $ev];
        }
        // Lo importante primero; después lo no leído y lo más reciente.
        usort($items, static fn($a, $b) => [$b['importante'], $a['leido'], $b['fecha']] <=> [$a['importante'], $b['leido'], $a['fecha']]);
        return $items;
    }

    private function puedeEmitirComunicados(): bool
    {
        return \App\Modules\Comunicados\Avisos::puedeEmitir();
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

        Lecturas::marcar('evento', (int) $ev['id']);
        $ev = Clasificacion::conProgramas([$ev])[0];
        $gestiona = Clasificacion::puedeGestionar($ev);
        $this->vista('detalle', [
            'titulo'     => 'Evento',
            'ev'         => $ev,
            'destinatarios' => $destinatarios,
            'concluido'  => strtotime($ev['fin'] ?: $ev['inicio']) < time(),
            'categorias' => self::CATEGORIAS,
            'siglas'     => array_column(Database::todas('SELECT id, siglas FROM programas'), 'siglas', 'id'),
            'programas'  => Database::todas("SELECT id, siglas, nombre FROM programas ORDER BY nivel = 'posgrado', siglas"),
            'gestiona'   => $gestiona,
            'asistencia' => Asistencias::de((int) $ev['id'], Auth::id()),
            'asistentes' => $gestiona ? Asistencias::delEvento((int) $ev['id']) : [],
            'evidencias' => $gestiona ? Database::todas('SELECT x.*, p.siglas, u.nombre AS autor FROM evento_evidencias x LEFT JOIN programas p ON p.id = x.programa_id JOIN usuarios u ON u.id = x.autor_id WHERE x.evento_id = ? ORDER BY x.id DESC', [(int) $ev['id']]) : [],
        ]);
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
