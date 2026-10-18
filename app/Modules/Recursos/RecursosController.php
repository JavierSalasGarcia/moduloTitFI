<?php
declare(strict_types=1);

namespace App\Modules\Recursos;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * Recursos del menú: archivos o enlaces configurables por el
 * administrador. Cada recurso tiene una ficha con descripción, notas
 * del administrador y notas personales editables de cada usuario.
 */
final class RecursosController extends Controller
{
    // ── Ficha para todos los usuarios ───────────────────────────────

    public function ficha(string $id): void
    {
        $r = Database::una('SELECT * FROM recursos WHERE id = ? AND activo = 1', [(int) $id]);
        if (!$r) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'Recurso no encontrado']);
            return;
        }
        $notas = Database::todas(
            'SELECT * FROM recurso_notas WHERE recurso_id = ? AND usuario_id = ? ORDER BY creado_en',
            [$r['id'], Auth::id()]);

        $this->vista('ficha', ['titulo' => $r['titulo'], 'r' => $r, 'notas' => $notas]);
    }

    public function archivo(string $id): void
    {
        $r = Database::una("SELECT archivo FROM recursos WHERE id = ? AND activo = 1 AND tipo = 'archivo'", [(int) $id]);
        if (!$r || !$r['archivo']) {
            http_response_code(404);
            exit('El administrador aún no sube este archivo.');
        }
        descargar_archivo($r['archivo']);
    }

    // ── Notas personales del usuario ────────────────────────────────

    public function agregarNota(string $id): void
    {
        $contenido = $this->post('contenido');
        if ($contenido !== '' && Database::una('SELECT 1 FROM recursos WHERE id = ? AND activo = 1', [(int) $id])) {
            Database::ejecutar(
                'INSERT INTO recurso_notas (recurso_id, usuario_id, contenido) VALUES (?, ?, ?)',
                [(int) $id, Auth::id(), mb_strimwidth($contenido, 0, 500)]);
        }
        redirigir('recursos/' . (int) $id);
    }

    public function editarNota(string $id): void
    {
        $nota = $this->notaPropia((int) $id);
        $contenido = $this->post('contenido');
        if ($contenido !== '') {
            Database::ejecutar('UPDATE recurso_notas SET contenido = ? WHERE id = ?',
                [mb_strimwidth($contenido, 0, 500), $nota['id']]);
        }
        redirigir('recursos/' . $nota['recurso_id']);
    }

    public function eliminarNota(string $id): void
    {
        $nota = $this->notaPropia((int) $id);
        Database::ejecutar('DELETE FROM recurso_notas WHERE id = ?', [$nota['id']]);
        redirigir('recursos/' . $nota['recurso_id']);
    }

    // ── Panel del administrador ─────────────────────────────────────

    public function panel(): void
    {
        $this->exigirAdmin();
        $this->vista('panel', [
            'titulo'   => 'Recursos del menú',
            'recursos' => Database::todas('SELECT * FROM recursos ORDER BY activo DESC, orden, titulo'),
        ]);
    }

    public function crear(): void
    {
        $this->exigirAdmin();
        $this->guardarDesdeFormulario(null);
        flash('exito', 'Recurso agregado al menú.');
        redirigir('recursos');
    }

    public function actualizar(string $id): void
    {
        $this->exigirAdmin();
        $r = Database::una('SELECT * FROM recursos WHERE id = ?', [(int) $id]);
        if ($r) {
            $this->guardarDesdeFormulario($r);
            flash('exito', 'Recurso actualizado.');
        }
        redirigir('recursos');
    }

    public function eliminar(string $id): void
    {
        $this->exigirAdmin();
        Database::ejecutar('UPDATE recursos SET activo = 0 WHERE id = ?', [(int) $id]);
        flash('exito', 'Recurso retirado del menú (sus notas se conservan).');
        redirigir('recursos');
    }

    // ── Auxiliares ──────────────────────────────────────────────────

    private function guardarDesdeFormulario(?array $existente): void
    {
        $titulo = $this->post('titulo');
        $tipo   = $this->post('tipo') === 'enlace' ? 'enlace' : 'archivo';
        $url    = $this->post('url');
        if ($titulo === '' || ($tipo === 'enlace' && !filter_var($url, FILTER_VALIDATE_URL))) {
            flash('error', 'Verifica el título y, para enlaces, una URL válida.');
            redirigir('recursos');
        }

        $archivo = $existente['archivo'] ?? null;
        try {
            $subido = guardar_archivo('archivo', 'recursos');
            if ($subido !== null) {
                $archivo = $subido;
            }
        } catch (\InvalidArgumentException $ex) {
            flash('error', $ex->getMessage());
            redirigir('recursos');
        }

        $datos = [$titulo, $this->post('descripcion'), $tipo,
                  $tipo === 'archivo' ? $archivo : null,
                  $tipo === 'enlace' ? $url : null,
                  $this->post('notas_admin'), (int) ($this->post('orden') ?: 50)];

        if ($existente) {
            Database::ejecutar(
                'UPDATE recursos SET titulo=?, descripcion=?, tipo=?, archivo=?, url=?, notas_admin=?, orden=?, activo=1 WHERE id=?',
                [...$datos, $existente['id']]);
        } else {
            Database::ejecutar(
                'INSERT INTO recursos (titulo, descripcion, tipo, archivo, url, notas_admin, orden) VALUES (?,?,?,?,?,?,?)',
                $datos);
        }
    }

    private function notaPropia(int $id): array
    {
        $nota = Database::una('SELECT * FROM recurso_notas WHERE id = ? AND usuario_id = ?', [$id, Auth::id()]);
        if (!$nota) {
            http_response_code(404);
            exit('Nota no encontrada.');
        }
        return $nota;
    }

    private function exigirAdmin(): void
    {
        if (Auth::rol() !== 'admin') {
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }
}
