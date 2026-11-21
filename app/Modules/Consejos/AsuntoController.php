<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Consejos\Services\Html;
use App\Modules\Consejos\Services\Texto;

/** Casos de una reunión: solicitud y antecedentes (con tablas pegadas desde Word) y profesores mencionados. */
final class AsuntoController extends ConsejosBase
{
    private const GRUPOS = ['profesor' => 'Caso de profesor', 'alumno' => 'Caso de alumno', 'general' => 'Asunto general'];

    public function nuevo(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $grupo = array_key_exists($_GET['grupo'] ?? '', self::GRUPOS) ? (string) $_GET['grupo'] : 'profesor';
        $a = ['id' => 0, 'grupo' => $grupo, 'numero' => $this->siguiente((int) $r['id'], $grupo), 'solicitud_html' => '', 'antecedentes_html' => ''];
        $this->vista('asunto_form', ['titulo' => 'Nuevo caso', 'r' => $r, 'a' => $a, 'profesores' => [], 'grupos' => self::GRUPOS]);
    }

    public function crear(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $grupo = array_key_exists($this->post('grupo'), self::GRUPOS) ? $this->post('grupo') : 'profesor';
        [$sol, $ant, $error] = $this->contenido();
        if ($error !== '') {
            flash('error', $error);
            redirigir('consejos/reunion/' . $r['id'] . '/asunto/nuevo?grupo=' . $grupo);
        }
        $numero = $this->siguiente((int) $r['id'], $grupo);
        $obs = mb_substr(trim(str_replace("\r", '', $this->post('observacion'))), 0, 3000);
        $aid = Database::ejecutar(
            'INSERT INTO consejos_asuntos (reunion_id, grupo, numero, solicitud_html, antecedentes_html, texto_busqueda, observacion, observacion_autor, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [(int) $r['id'], $grupo, $numero, $sol, $ant, trim(Html::texto($sol) . "\n" . Html::texto($ant)), $obs !== '' ? $obs : null, $obs !== '' ? (string) (Auth::usuario()['nombre'] ?? '') : null, Auth::id()]
        );
        $this->guardarProfesores($aid);
        Audit::record('consejos::asunto', 'correcto', 'Crea ' . Texto::clave($grupo, $numero) . ' reunión #' . $r['id']);
        flash('exito', 'Caso ' . Texto::clave($grupo, $numero) . ' guardado.');
        redirigir($this->post('seguir') === '1' ? 'consejos/reunion/' . $r['id'] . '/asunto/nuevo?grupo=' . $grupo : 'consejos/reunion/' . $r['id']);
    }

    public function editar(string $id): void
    {
        $this->gestor();
        $a = $this->asunto($id);
        $r = $this->reunion((string) $a['reunion_id']);
        $profes = Database::todas('SELECT u.id, u.nombre, u.email FROM consejos_asunto_profesores p JOIN usuarios u ON u.id = p.usuario_id WHERE p.asunto_id = ? ORDER BY u.nombre', [(int) $a['id']]);
        $this->vista('asunto_form', ['titulo' => 'Editar ' . Texto::clave($a['grupo'], (int) $a['numero']), 'r' => $r, 'a' => $a, 'profesores' => $profes, 'grupos' => self::GRUPOS]);
    }

    public function actualizar(string $id): void
    {
        $this->gestor();
        $a = $this->asunto($id);
        [$sol, $ant, $error] = $this->contenido();
        if ($error !== '') {
            flash('error', $error);
            redirigir('consejos/asunto/' . $a['id'] . '/editar');
        }
        $numero = max(1, (int) $this->post('numero', (string) $a['numero']));
        if ($numero !== (int) $a['numero'] && Database::valor('SELECT 1 FROM consejos_asuntos WHERE reunion_id = ? AND grupo = ? AND numero = ? AND id <> ?', [(int) $a['reunion_id'], $a['grupo'], $numero, (int) $a['id']])) {
            flash('error', 'Ya existe el caso ' . Texto::clave($a['grupo'], $numero) . ' en esta reunión.');
            redirigir('consejos/asunto/' . $a['id'] . '/editar');
        }
        // El comentario conserva a quien lo escribió mientras no cambie su texto.
        $obs = mb_substr(trim(str_replace("\r", '', $this->post('observacion'))), 0, 3000);
        $autor = $obs === '' ? null : ($obs === trim((string) $a['observacion']) ? $a['observacion_autor'] : (string) (Auth::usuario()['nombre'] ?? ''));
        Database::ejecutar('UPDATE consejos_asuntos SET numero = ?, solicitud_html = ?, antecedentes_html = ?, texto_busqueda = ?, observacion = ?, observacion_autor = ? WHERE id = ?',
            [$numero, $sol, $ant, trim(Html::texto($sol) . "
" . Html::texto($ant)), $obs !== '' ? $obs : null, $autor, (int) $a['id']]);
        $this->guardarProfesores((int) $a['id']);
        Audit::record('consejos::asunto', 'correcto', 'Actualiza ' . Texto::clave($a['grupo'], $numero) . ' reunión #' . $a['reunion_id']);
        flash('exito', 'Caso ' . Texto::clave($a['grupo'], $numero) . ' actualizado.');
        redirigir('consejos/reunion/' . $a['reunion_id']);
    }

    public function eliminar(string $id): void
    {
        $this->gestor();
        $a = $this->asunto($id);
        Database::ejecutar('DELETE FROM consejos_asuntos WHERE id = ?', [(int) $a['id']]);
        Audit::record('consejos::asunto', 'correcto', 'Elimina ' . Texto::clave($a['grupo'], (int) $a['numero']) . ' reunión #' . $a['reunion_id']);
        flash('exito', 'Caso eliminado.');
        redirigir('consejos/reunion/' . $a['reunion_id']);
    }

    /** Buscador de personas para mencionarlas en un caso (JSON). Solo gestores. */
    public function personas(): void
    {
        $this->gestor();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        if (mb_strlen($q) < 2) {
            $this->json(['personas' => []]);
        }
        $filas = Database::todas(
            "SELECT id, nombre, email FROM usuarios WHERE activo = 1 AND rol <> 'alumno' AND (nombre LIKE ? OR email LIKE ?) ORDER BY nombre LIMIT 15",
            ['%' . strtr($q, ['%' => '\\%', '_' => '\\_']) . '%', '%' . strtr($q, ['%' => '\\%', '_' => '\\_']) . '%']
        );
        $this->json(['personas' => array_map(static fn($f) => ['id' => (int) $f['id'], 'nombre' => $f['nombre'], 'email' => $f['email']], $filas)]);
    }

    private function siguiente(int $reunionId, string $grupo): int
    {
        return (int) Database::valor('SELECT COALESCE(MAX(numero), 0) + 1 FROM consejos_asuntos WHERE reunion_id = ? AND grupo = ?', [$reunionId, $grupo]);
    }

    /** @return array{0:string,1:string,2:string} solicitud limpia, antecedentes limpios y error */
    private function contenido(): array
    {
        $sol = Html::limpiar((string) ($_POST['solicitud_html'] ?? ''));
        $ant = Html::limpiar((string) ($_POST['antecedentes_html'] ?? ''));
        if (trim(Html::texto($sol)) === '') {
            return ['', '', 'Escribe o pega la solicitud del caso.'];
        }
        return [$sol, $ant, ''];
    }

    private function guardarProfesores(int $asuntoId): void
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['profesores'] ?? [])))));
        Database::ejecutar('DELETE FROM consejos_asunto_profesores WHERE asunto_id = ?', [$asuntoId]);
        if (!$ids) {
            return;
        }
        $validos = Database::todas('SELECT id FROM usuarios WHERE activo = 1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        foreach ($validos as $v) {
            Database::ejecutar('INSERT IGNORE INTO consejos_asunto_profesores (asunto_id, usuario_id) VALUES (?, ?)', [$asuntoId, (int) $v['id']]);
        }
    }
}
