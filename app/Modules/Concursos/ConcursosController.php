<?php
declare(strict_types=1);

namespace App\Modules\Concursos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/** «Concursos»: convocatorias públicas, envío de obras, prefiltro, votación anónima con recibo y resultados. */
final class ConcursosController extends Controller
{
    private function concurso(string $slug): array
    {
        $c = Concursos::porSlug($slug);
        if (!$c || !Concursos::visible($c)) {
            http_response_code(404);
            \App\Core\View::render('errores/404', ['titulo' => 'Página no encontrada'], Auth::usuario() ? 'app' : 'auth');
            exit;
        }
        return $c;
    }

    private function soloGestion(): void
    {
        if (!Concursos::puedeGestionar()) {
            http_response_code(403);
            \App\Core\View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }

    public function index(): void
    {
        $this->vista('index', ['titulo' => 'Concursos', 'lista' => Concursos::listar()['actuales'], 'pasados' => false]);
    }

    public function pasados(): void
    {
        $this->vista('index', ['titulo' => 'Concursos pasados', 'lista' => Concursos::listar()['pasados'], 'pasados' => true]);
    }

    /** Convocatoria pública; con sesión, además envío de obras o votación según la fase. */
    public function ver(string $slug): void
    {
        $c = $this->concurso($slug);
        $fase = Concursos::fase($c);
        $uid = (int) Auth::id();
        $conteo = in_array($fase, ['votacion', 'resultados'], true) ? Concursos::conteo($c) : [];
        $recibo = $_SESSION['recibo_concurso_' . $c['id']] ?? null;
        unset($_SESSION['recibo_concurso_' . $c['id']]);
        $this->vista('ver', [
            'titulo' => $c['titulo'], 'c' => $c, 'fase' => $fase, 'conteo' => $conteo,
            'misObras' => $uid ? Concursos::misObras((int) $c['id'], $uid) : [],
            'yaVoto' => $uid && Concursos::yaVoto((int) $c['id'], $uid), 'recibo' => $recibo,
            'gestiona' => Concursos::puedeGestionar(), 'seccion' => 'Concursos',
        ], Auth::usuario() ? 'app' : 'publico');
    }

    public function enviar(string $slug): void
    {
        $c = $this->concurso($slug);
        $id = ctype_digit($this->post('obra')) ? (int) $this->post('obra') : null;
        try {
            $n = Concursos::enviar($c, (int) Auth::id(), $this->post('titulo'), (string) ($_POST['texto'] ?? ''), $id);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            $_SESSION['concurso_borrador'] = ['titulo' => $this->post('titulo'), 'texto' => (string) ($_POST['texto'] ?? '')];
            redirigir('concursos/' . $c['slug'] . '#participar');
        }
        Audit::record('concursos::obra', 'correcto', $c['slug'] . ' · obra ' . $n);
        flash('exito', $id ? 'Obra corregida.' : 'Obra recibida con el número ' . $n . '. Pasará por el prefiltro y te avisaremos el resultado.');
        redirigir('concursos/' . $c['slug'] . '#mis-obras');
    }

    public function retirar(string $slug, string $id): void
    {
        $c = $this->concurso($slug);
        try {
            Concursos::retirar($c, (int) Auth::id(), (int) $id);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('concursos/' . $c['slug'] . '#mis-obras');
        }
        flash('exito', 'Obra retirada.');
        redirigir('concursos/' . $c['slug'] . '#mis-obras');
    }

    public function votar(string $slug): void
    {
        $c = $this->concurso($slug);
        try {
            $recibo = Concursos::votar($c, (int) Auth::id(), (int) $this->post('numero'));
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('concursos/' . $c['slug'] . '#votacion');
        }
        // El recibo y la obra elegida se muestran una sola vez; no quedan ligados a la cuenta.
        $_SESSION['recibo_concurso_' . $c['id']] = ['recibo' => $recibo, 'numero' => (int) $this->post('numero')];
        redirigir('concursos/' . $c['slug'] . '#votacion');
    }

    /** Verificación pública: un recibo durante la votación; la lista completa al cerrarla. */
    public function recibos(string $slug): void
    {
        $c = $this->concurso($slug);
        $fase = Concursos::fase($c);
        $buscar = strtoupper(trim((string) ($_GET['recibo'] ?? '')));
        $this->vista('recibos', [
            'titulo' => 'Verificación de votos · ' . $c['titulo'], 'c' => $c, 'fase' => $fase, 'buscar' => $buscar,
            'encontrado' => $buscar !== '' ? Concursos::verificar($c, $buscar) : null,
            'lista' => in_array($fase, ['computo', 'resultados'], true) ? Concursos::recibos($c) : [], 'seccion' => 'Concursos',
        ], Auth::usuario() ? 'app' : 'publico');
    }

    // ------------------------------------------------------------------ prefiltro

    public function prefiltro(string $slug): void
    {
        if (!Concursos::puedePrefiltrar()) {
            $this->soloGestion();
        }
        $c = $this->concurso($slug);
        $fase = Concursos::fase($c);
        $obras = Database::todas("SELECT id, numero, titulo, texto, estado, motivo, revisado_en, creado_en, actualizado_en FROM con_participaciones WHERE concurso_id = ? AND estado <> 'retirada' ORDER BY estado <> 'enviada', numero", [(int) $c['id']]);
        $conteo = in_array($fase, ['computo', 'resultados'], true) ? Concursos::conteo($c) : [];
        $this->vista('prefiltro', [
            'titulo' => 'Prefiltro · ' . $c['titulo'], 'c' => $c, 'fase' => $fase, 'obras' => $obras,
            'conteo' => $conteo, 'empates' => Concursos::empates($conteo),
        ]);
    }

    public function dictaminar(string $slug, string $id): void
    {
        $this->soloGestion();
        $c = $this->concurso($slug);
        try {
            Concursos::dictaminar($c, (int) $id, $this->post('dictamen') === 'aprobada', $this->post('motivo'), (int) Auth::id());
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('concursos/' . $c['slug'] . '/prefiltro');
        }
        Audit::record('concursos::prefiltro', 'correcto', $c['slug'] . ' · obra ' . (int) $id . ' · ' . $this->post('dictamen'));
        redirigir('concursos/' . $c['slug'] . '/prefiltro#obra-' . (int) $id);
    }

    public function desempate(string $slug): void
    {
        $this->soloGestion();
        $c = $this->concurso($slug);
        if (Concursos::fase($c) !== 'computo') {
            flash('error', 'Los empates se resuelven después de la votación y antes de la premiación.');
            redirigir('concursos/' . $c['slug'] . '/prefiltro');
        }
        Concursos::desempatar($c, array_map('intval', is_array($_POST['orden'] ?? null) ? $_POST['orden'] : []));
        Audit::record('concursos::desempate', 'correcto', $c['slug']);
        flash('exito', 'Orden de los empates guardado.');
        redirigir('concursos/' . $c['slug'] . '/prefiltro#resultados');
    }

    // ------------------------------------------------------------------ gestión

    public function gestion(): void
    {
        $this->soloGestion();
        $lista = Database::todas('SELECT c.*, (SELECT COUNT(*) FROM con_participaciones p WHERE p.concurso_id = c.id AND p.estado <> \'retirada\') AS obras,
            (SELECT COUNT(*) FROM con_participaciones p WHERE p.concurso_id = c.id AND p.estado = \'enviada\') AS por_revisar,
            (SELECT COUNT(*) FROM con_votantes v WHERE v.concurso_id = c.id) AS votantes FROM con_concursos c ORDER BY c.premiacion DESC');
        foreach ($lista as &$c) {
            $c['fase'] = Concursos::fase($c);
        }
        unset($c);
        $this->vista('gestion', ['titulo' => 'Concursos · Gestión', 'lista' => $lista]);
    }

    public function nuevo(): void
    {
        $this->soloGestion();
        $hoy = strtotime('tomorrow 09:00');
        $this->vista('formulario', ['titulo' => 'Nuevo concurso', 'c' => [
            'id' => null, 'titulo' => '', 'slug' => '', 'resumen' => '', 'tematica' => '', 'estado' => 'borrador', 'bases' => '',
            'publicacion' => date('Y-m-d H:i:s', $hoy), 'recepcion_fin' => date('Y-m-d 23:59:00', $hoy + 6 * 86400),
            'votacion_inicio' => date('Y-m-d 00:00:00', $hoy + 7 * 86400), 'votacion_fin' => date('Y-m-d 23:59:00', $hoy + 9 * 86400),
            'premiacion' => date('Y-m-d 12:00:00', $hoy + 10 * 86400), 'premio_1' => null, 'premio_2' => null, 'premio_3' => null,
            'max_por_persona' => 1, 'max_caracteres' => 1500,
        ]]);
    }

    public function editar(string $id): void
    {
        $this->soloGestion();
        $c = ctype_digit($id) ? Concursos::porId((int) $id) : null;
        if (!$c) {
            redirigir('concursos/gestion');
        }
        $this->vista('formulario', ['titulo' => 'Editar concurso', 'c' => $c]);
    }

    public function guardar(): void
    {
        $this->soloGestion();
        $id = ctype_digit($this->post('id')) ? (int) $this->post('id') : null;
        try {
            $id = Concursos::guardar($id, $_POST, (int) Auth::id());
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir($id ? 'concursos/gestion/' . $id : 'concursos/gestion/nuevo');
        }
        Audit::record('concursos::gestion', 'correcto', 'Concurso ' . $id . ' · ' . $this->post('estado'));
        flash('exito', 'Concurso guardado.');
        redirigir('concursos/gestion/' . $id);
    }
}
