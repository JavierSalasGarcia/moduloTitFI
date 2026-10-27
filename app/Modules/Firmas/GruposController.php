<?php
declare(strict_types=1);

namespace App\Modules\Firmas;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * Grupos de firmantes.
 * - personal: nombre arbitrario, editable solo por su creador.
 * - nab/cap (por posgrado) y cc (por licenciatura): los edita la persona
 *   titular de la coordinación del programa (programas.depto_id).
 * - cuerpo_academico: los crea y edita la persona titular del
 *   departamento del ajuste `firmas_depto_investigacion`.
 * El administrador puede editar todo. Cualquier creador de solicitudes
 * puede USAR cualquier grupo institucional.
 */
final class GruposController extends Controller
{
    public function index(): void
    {
        $grupos = Database::todas(
            "SELECT g.*, p.siglas, p.nombre AS programa,
                    (SELECT COUNT(*) FROM firma_grupo_miembros WHERE grupo_id = g.id) AS total
               FROM firma_grupos g
               LEFT JOIN programas p ON p.id = g.programa_id
              ORDER BY FIELD(g.tipo,'personal','nab','cap','cc','cuerpo_academico'), g.nombre");

        $editables = [];
        foreach ($grupos as $g) {
            if ($this->puedeEditar($g)) {
                $editables[] = $g['id'];
            }
        }
        // Solo mis personales + institucionales que administro (los demás
        // grupos se usan desde "Nueva solicitud", no desde aquí).
        $visibles = array_values(array_filter($grupos,
            fn($g) => in_array($g['id'], $editables, true)));

        $miembros = [];
        if ($visibles) {
            $ids = implode(',', array_map(fn($g) => (int) $g['id'], $visibles));
            foreach (Database::todas(
                "SELECT m.grupo_id, u.id, u.nombre, u.email
                   FROM firma_grupo_miembros m JOIN usuarios u ON u.id = m.usuario_id
                  WHERE m.grupo_id IN ({$ids}) ORDER BY u.nombre") as $fila) {
                $miembros[$fila['grupo_id']][] = $fila;
            }
        }

        $this->vista('grupos', [
            'titulo'          => 'Grupos de firmantes',
            'grupos'          => $visibles,
            'miembros'        => $miembros,
            'usuarios'        => Database::todas(
                'SELECT nombre, email FROM usuarios WHERE activo = 1 ORDER BY nombre'),
            'puedeCrearCuerpo'=> $this->esCoordInvestigacion(),
        ]);
    }

    public function crear(): void
    {
        $nombre = $this->post('nombre');
        $tipo   = $this->post('tipo') === 'cuerpo_academico' ? 'cuerpo_academico' : 'personal';
        if ($nombre === '') {
            flash('error', 'Escribe el nombre del grupo.');
            redirigir('firmas/grupos');
        }
        if ($tipo === 'cuerpo_academico' && !$this->esCoordInvestigacion() && !Auth::tieneRol('admin')) {
            flash('error', 'Solo la Coordinación de Investigación crea Cuerpos Académicos.');
            redirigir('firmas/grupos');
        }
        Database::ejecutar(
            'INSERT INTO firma_grupos (nombre, tipo, creador_id) VALUES (?, ?, ?)',
            [mb_strimwidth($nombre, 0, 160), $tipo, $tipo === 'personal' ? Auth::id() : null]);
        flash('exito', 'Grupo creado; ahora agrega a sus integrantes.');
        redirigir('firmas/grupos');
    }

    public function agregarMiembro(string $id): void
    {
        $g = $this->grupoEditable((int) $id);
        $texto = $this->post('usuario');
        $u = $this->resolverUsuario($texto);
        if (!$u) {
            flash('error', "No se encontró un usuario único para «{$texto}»; elígelo del listado.");
            redirigir('firmas/grupos');
        }
        Database::ejecutar(
            'INSERT IGNORE INTO firma_grupo_miembros (grupo_id, usuario_id) VALUES (?, ?)',
            [$g['id'], (int) $u['id']]);
        flash('exito', $u['nombre'] . ' se agregó al grupo.');
        redirigir('firmas/grupos');
    }

    public function quitarMiembro(string $id): void
    {
        $g = $this->grupoEditable((int) $id);
        Database::ejecutar(
            'DELETE FROM firma_grupo_miembros WHERE grupo_id = ? AND usuario_id = ?',
            [$g['id'], (int) $this->post('usuario_id')]);
        flash('exito', 'Integrante retirado del grupo.');
        redirigir('firmas/grupos');
    }

    public function eliminar(string $id): void
    {
        $g = $this->grupoEditable((int) $id);
        if (!in_array($g['tipo'], ['personal', 'cuerpo_academico'], true)) {
            flash('error', 'Los grupos NAB/CAP/CC no se eliminan; solo se actualizan sus integrantes.');
            redirigir('firmas/grupos');
        }
        Database::ejecutar('DELETE FROM firma_grupos WHERE id = ?', [$g['id']]);
        flash('exito', 'Grupo eliminado.');
        redirigir('firmas/grupos');
    }

    // ── Permisos ────────────────────────────────────────────────────

    private function puedeEditar(array $g): bool
    {
        if (Auth::tieneRol('admin')) {
            return true;
        }
        return match ($g['tipo']) {
            'personal'         => (int) $g['creador_id'] === Auth::id(),
            'nab', 'cap', 'cc' => Auth::tieneRol('titular') && $g['programa_id']
                && Auth::departamentoId() === (int) Database::valor(
                    'SELECT depto_id FROM programas WHERE id = ?', [$g['programa_id']]),
            'cuerpo_academico' => $this->esCoordInvestigacion(),
            default            => false,
        };
    }

    private function esCoordInvestigacion(): bool
    {
        $depto = (int) ajuste('firmas_depto_investigacion', '0');
        return $depto > 0 && Auth::tieneRol('titular') && Auth::departamentoId() === $depto;
    }

    private function grupoEditable(int $id): array
    {
        $g = Database::una('SELECT * FROM firma_grupos WHERE id = ?', [$id]);
        if (!$g || !$this->puedeEditar($g)) {
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
        return $g;
    }

    private function resolverUsuario(string $texto): ?array
    {
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+/i', $texto, $m)) {
            return Database::una('SELECT id, nombre FROM usuarios WHERE email = ? AND activo = 1', [$m[0]]);
        }
        $coincidencias = Database::todas(
            'SELECT id, nombre FROM usuarios WHERE nombre LIKE ? AND activo = 1 LIMIT 2',
            ['%' . $texto . '%']);
        return count($coincidencias) === 1 ? $coincidencias[0] : null;
    }
}
