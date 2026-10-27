<?php
declare(strict_types=1);

namespace App\Modules\Encuestas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * Votaciones/encuestas anónimas organizadas por alumnos.
 *
 * Anonimato: `encuesta_participantes` solo registra QUE un usuario ya
 * votó (para permitir un voto por persona); el voto se guarda en
 * `encuesta_votos` SIN referencia al usuario, con un token aleatorio.
 * El recibo se muestra únicamente en la respuesta al POST. No se guarda
 * en la sesión, la cuenta ni el navegador: sólo existe en la fila anónima
 * del voto y en la copia que decida conservar la persona.
 */
final class EncuestasController extends Controller
{
    public function index(): void
    {
        $this->borrarRecibosLegadosDeSesion();
        $encuestas = Database::todas(
            "SELECT e.*, u.nombre AS creador,
                    (SELECT COUNT(*) FROM encuesta_votos v WHERE v.encuesta_id = e.id) AS votos,
                    EXISTS(SELECT 1 FROM encuesta_participantes p WHERE p.encuesta_id=e.id AND p.usuario_id=?) AS ya_voto
               FROM encuestas e JOIN usuarios u ON u.id = e.creador_id
              ORDER BY e.estado = 'cerrada', e.creado_en DESC", [Auth::id()]);
        $vista = in_array($_GET['vista'] ?? '', ['pendientes','respondidas','cerradas'], true) ? $_GET['vista'] : 'todas';
        $encuestas = array_values(array_filter($encuestas, static fn($e) => match($vista) {
            'pendientes' => $e['estado']==='abierta' && !$e['ya_voto'],
            'respondidas' => (bool)$e['ya_voto'], 'cerradas' => $e['estado']==='cerrada', default=>true
        }));

        $this->vista('index', [
            'titulo'     => 'Encuestas y votaciones',
            'encuestas'  => $encuestas,
            'vistaFiltro' => $vista,
            'puedeCrear' => Auth::tieneRol('alumno', 'admin'),
        ]);
    }

    public function nueva(): void
    {
        if (!Auth::tieneRol('alumno', 'admin')) {
            flash('error', 'Las encuestas las organizan los alumnos.');
            redirigir('encuestas');
        }
        $this->vista('nueva', ['titulo' => 'Nueva encuesta']);
    }

    public function crear(): void
    {
        \App\Core\AccessControl::exigirCorreoVerificado('crear encuestas');
        if (!Auth::tieneRol('alumno', 'admin')) {
            flash('error', 'Las encuestas las organizan los alumnos.');
            redirigir('encuestas');
        }
        $titulo = $this->post('titulo');
        $opciones = array_values(array_filter(array_map('trim', explode("\n", $this->post('opciones')))));

        if ($titulo === '' || count($opciones) < 2) {
            flash('error', 'Escribe un título y al menos dos opciones (una por línea).');
            redirigir('encuestas/nueva');
        }

        $id = Database::ejecutar(
            'INSERT INTO encuestas (titulo, descripcion, creador_id) VALUES (?, ?, ?)',
            [$titulo, $this->post('descripcion'), Auth::id()]
        );
        foreach (array_slice($opciones, 0, 10) as $texto) {
            Database::ejecutar('INSERT INTO encuesta_opciones (encuesta_id, texto) VALUES (?, ?)', [$id, $texto]);
        }
        flash('exito', 'Encuesta abierta. Comparte el tablero público para que todos verifiquen los votos.');
        redirigir('encuestas/' . $id);
    }

    public function detalle(string $id): void
    {
        $this->borrarRecibosLegadosDeSesion();
        $e = $this->obtener((int) $id);
        $this->vista('detalle', $this->datosDetalle($e));
    }

    public function votar(string $id): void
    {
        \App\Core\AccessControl::exigirCorreoVerificado('responder encuestas');
        $e = $this->obtener((int) $id);
        $opcion = Database::una(
            'SELECT * FROM encuesta_opciones WHERE id = ? AND encuesta_id = ?',
            [(int) $this->post('opcion'), $e['id']]);

        if ($e['estado'] !== 'abierta' || !$opcion) {
            flash('error', 'La encuesta está cerrada o la opción no es válida.');
            redirigir('encuestas/' . $e['id']);
        }
        if (Database::una('SELECT 1 FROM encuesta_participantes WHERE encuesta_id = ? AND usuario_id = ?', [$e['id'], Auth::id()])) {
            flash('error', 'Ya emitiste tu voto en esta encuesta.');
            redirigir('encuestas/' . $e['id']);
        }

        // Registrar participación (sin el sentido del voto) y el voto anónimo
        $token = 'V-' . strtoupper(bin2hex(random_bytes(4)));
        Database::pdo()->beginTransaction();
        try {
            Database::ejecutar('INSERT INTO encuesta_participantes (encuesta_id, usuario_id) VALUES (?, ?)', [$e['id'], Auth::id()]);
            Database::ejecutar('INSERT INTO encuesta_votos (encuesta_id, opcion_id, token) VALUES (?, ?, ?)', [$e['id'], $opcion['id'], $token]);
            Database::pdo()->commit();
        } catch (\Throwable $ex) {
            Database::pdo()->rollBack();
            throw $ex;
        }

        // Entregar el recibo sólo en esta respuesta. Guardarlo en sesión o en
        // localStorage crearía precisamente la relación cuenta/recibo que el
        // modelo de anonimato busca evitar.
        header('Cache-Control: no-store, private');
        header('Pragma: no-cache');
        $this->vista('detalle', $this->datosDetalle($e, $token));
    }

    public function cerrar(string $id): void
    {
        $e = $this->obtener((int) $id);
        if ((int) $e['creador_id'] !== Auth::id() && !Auth::tieneRol('admin')) {
            flash('error', 'Solo quien organizó la encuesta puede cerrarla.');
        } else {
            Database::ejecutar("UPDATE encuestas SET estado = 'cerrada' WHERE id = ?", [$e['id']]);
            flash('exito', 'Encuesta cerrada; el tablero público queda como constancia.');
        }
        redirigir('encuestas/' . $e['id']);
    }

    /**
     * Tablero PÚBLICO: resultados y registro tabular de cada voto, sin
     * sesión. Paginado (miles de votantes) y con búsqueda por token.
     */
    public function tablero(string $id): void
    {
        $e = Database::una('SELECT * FROM encuestas WHERE id = ?', [(int) $id]);
        if (!$e) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No encontrada'], 'auth');
            return;
        }

        $porPagina = 500;
        $buscar = strtoupper(trim((string) ($_GET['token'] ?? '')));
        $condicion = '';
        $params = [$e['id']];
        if ($buscar !== '') {
            $condicion = ' AND v.token LIKE ?';
            $params[] = $buscar . '%';
        }

        $total = (int) Database::valor(
            "SELECT COUNT(*) FROM encuesta_votos v WHERE v.encuesta_id = ?{$condicion}", $params);
        $paginas = max(1, (int) ceil($total / $porPagina));
        $pagina = min($paginas, max(1, (int) ($_GET['p'] ?? 1)));

        // Orden por token (no cronológico) para no correlacionar con participantes
        $votos = Database::todas(
            "SELECT v.token, o.texto FROM encuesta_votos v
               JOIN encuesta_opciones o ON o.id = v.opcion_id
              WHERE v.encuesta_id = ?{$condicion}
              ORDER BY v.token LIMIT {$porPagina} OFFSET " . (($pagina - 1) * $porPagina),
            $params);

        View::render('encuestas:tablero', [
            'titulo'    => 'Tablero público',
            'encuesta'  => $e,
            'opciones'  => $this->opcionesConVotos($e['id']),
            'votos'     => $votos,
            'total'     => $total,
            'pagina'    => $pagina,
            'paginas'   => $paginas,
            'porPagina' => $porPagina,
            'buscar'    => $buscar,
        ], 'auth');
    }

    /** Exportación CSV de todos los votos (auditoría), pública. */
    public function tableroCsv(string $id): void
    {
        $e = Database::una('SELECT * FROM encuestas WHERE id = ?', [(int) $id]);
        if (!$e) {
            http_response_code(404);
            exit('Encuesta no encontrada.');
        }
        $votos = Database::todas(
            'SELECT v.token, o.texto FROM encuesta_votos v
               JOIN encuesta_opciones o ON o.id = v.opcion_id
              WHERE v.encuesta_id = ? ORDER BY v.token', [$e['id']]);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="encuesta-' . $e['id'] . '-votos.csv"');
        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF"); // BOM para Excel
        fputcsv($salida, ['Encuesta', $e['titulo']]);
        fputcsv($salida, ['Estado', $e['estado'], 'Total de votos', count($votos), 'Exportado', date('Y-m-d H:i:s')]);
        fputcsv($salida, []);
        fputcsv($salida, ['#', 'Token de voto', 'Opción votada']);
        foreach ($votos as $i => $v) {
            fputcsv($salida, [$i + 1, $v['token'], $v['texto']]);
        }
        fclose($salida);
        exit;
    }

    private function opcionesConVotos(int $encuestaId): array
    {
        return Database::todas(
            'SELECT o.*, (SELECT COUNT(*) FROM encuesta_votos v WHERE v.opcion_id = o.id) AS votos
               FROM encuesta_opciones o WHERE o.encuesta_id = ? ORDER BY o.id', [$encuestaId]);
    }

    private function datosDetalle(array $encuesta, ?string $recibo = null): array
    {
        return [
            'titulo'   => 'Encuesta',
            'encuesta' => $encuesta,
            'opciones' => $this->opcionesConVotos((int) $encuesta['id']),
            // Sólo indica participación. No contiene ni permite obtener el
            // recibo o la opción elegida por la cuenta.
            'yaVoto'   => (bool) Database::una(
                'SELECT 1 FROM encuesta_participantes WHERE encuesta_id = ? AND usuario_id = ?',
                [$encuesta['id'], Auth::id()]
            ),
            'esDueno'  => (int) $encuesta['creador_id'] === Auth::id() || Auth::tieneRol('admin'),
            'recibo'   => $recibo,
        ];
    }

    /** Elimina asociaciones cuenta/recibo creadas por versiones anteriores. */
    private function borrarRecibosLegadosDeSesion(): void
    {
        foreach (array_keys($_SESSION) as $clave) {
            if (is_string($clave) && str_starts_with($clave, 'token_encuesta_')) {
                unset($_SESSION[$clave]);
            }
        }
    }

    private function obtener(int $id): array
    {
        $e = Database::una(
            'SELECT e.*, u.nombre AS creador FROM encuestas e JOIN usuarios u ON u.id = e.creador_id WHERE e.id = ?',
            [$id]);
        if (!$e) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No encontrada']);
            exit;
        }
        return $e;
    }
}
