<?php
declare(strict_types=1);

namespace App\Modules\Foro;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/**
 * Foro de la comunidad. Las PETICIONES que alcanzan el umbral de likes
 * (ajuste `umbral_likes_trazabilidad`) pasan automáticamente a la etapa
 * de trazabilidad, donde una autoridad puede formar una comisión de
 * profesores para darles seguimiento auditable.
 */
final class ForoController extends Controller
{
    public function index(): void
    {
        $tipo = in_array($_GET['tipo'] ?? '', ['publicacion', 'peticion'], true) ? $_GET['tipo'] : '';
        $sql = "SELECT p.*, u.nombre AS autor,
                       (SELECT COUNT(*) FROM foro_likes l WHERE l.publicacion_id = p.id) AS likes,
                       (SELECT COUNT(*) FROM foro_comentarios c WHERE c.publicacion_id = p.id) AS comentarios,
                       EXISTS(SELECT 1 FROM foro_likes l2 WHERE l2.publicacion_id = p.id AND l2.usuario_id = ?) AS mi_like
                  FROM foro_publicaciones p JOIN usuarios u ON u.id = p.autor_id";
        $params = [Auth::id()];
        if ($tipo) {
            $sql .= ' WHERE p.tipo = ?';
            $params[] = $tipo;
        }
        $publicaciones = Database::todas($sql . ' ORDER BY p.creado_en DESC LIMIT 100', $params);

        $this->vista('index', [
            'titulo'        => 'Foro de la comunidad',
            'publicaciones' => $publicaciones,
            'tipo'          => $tipo,
            'umbral'        => (int) ajuste('umbral_likes_trazabilidad', '100'),
        ]);
    }

    public function publicar(): void
    {
        $titulo = $this->post('titulo');
        $contenido = $this->post('contenido');
        $tipo = $this->post('tipo') === 'peticion' ? 'peticion' : 'publicacion';
        if ($titulo === '' || $contenido === '') {
            flash('error', 'Escribe título y contenido.');
            redirigir('foro');
        }
        $id = Database::ejecutar(
            'INSERT INTO foro_publicaciones (autor_id, tipo, titulo, contenido, umbral_likes) VALUES (?, ?, ?, ?, ?)',
            [Auth::id(), $tipo, $titulo, $contenido, (int) ajuste('umbral_likes_trazabilidad','100')]
        );
        flash('exito', $tipo === 'peticion'
            ? 'Petición publicada: al reunir los likes suficientes pasará a trazabilidad.'
            : 'Publicación creada.');
        redirigir('foro/' . $id);
    }

    public function detalle(string $id): void
    {
        $p = $this->obtener((int) $id);
        $comentarios = Database::todas(
            'SELECT c.*, u.nombre AS autor,
                    (SELECT COUNT(*) FROM foro_likes l WHERE l.comentario_id = c.id) AS likes,
                    EXISTS(SELECT 1 FROM foro_likes l2 WHERE l2.comentario_id = c.id AND l2.usuario_id = ?) AS mi_like
               FROM foro_comentarios c JOIN usuarios u ON u.id = c.autor_id
              WHERE c.publicacion_id = ?
              ORDER BY likes DESC, c.creado_en',
            [Auth::id(), $p['id']]
        );
        $comision = Database::una(
            'SELECT id, titulo, estado FROM comisiones WHERE publicacion_id = ?', [$p['id']]);

        $this->vista('detalle', [
            'titulo'       => $p['tipo'] === 'peticion' ? 'Petición' : 'Publicación',
            'p'            => $p,
            'comentarios'  => $comentarios,
            'comision'     => $comision,
            'umbral'       => (int) ajuste('umbral_likes_trazabilidad', '100'),
            'puedeComision'=> Auth::tieneRol('admin', 'titular'),
        ]);
    }

    public function comentar(string $id): void
    {
        $p = $this->obtener((int) $id);
        $texto = $this->post('contenido');
        if ($texto !== '') {
            Database::ejecutar(
                'INSERT INTO foro_comentarios (publicacion_id, autor_id, contenido) VALUES (?, ?, ?)',
                [$p['id'], Auth::id(), $texto]
            );
        }
        redirigir('foro/' . $p['id']);
    }

    public function likePublicacion(string $id): void
    {
        $p = $this->obtener((int) $id);
        $this->alternarLike('publicacion_id', $p['id']);
        $this->evaluarUmbral($p);
        redirigir('foro/' . $p['id']);
    }

    public function likeComentario(string $id): void
    {
        $c = Database::una('SELECT * FROM foro_comentarios WHERE id = ?', [(int) $id]);
        if ($c) {
            $this->alternarLike('comentario_id', (int) $c['id']);
            redirigir('foro/' . $c['publicacion_id']);
        }
        redirigir('foro');
    }

    /** Un like por usuario y objeto; volver a pulsar lo quita. */
    private function alternarLike(string $campo, int $objetoId): void
    {
        $existe = Database::una(
            "SELECT id FROM foro_likes WHERE usuario_id = ? AND {$campo} = ?", [Auth::id(), $objetoId]);
        if ($existe) {
            Database::ejecutar('DELETE FROM foro_likes WHERE id = ?', [$existe['id']]);
        } else {
            Database::ejecutar(
                "INSERT INTO foro_likes (usuario_id, {$campo}) VALUES (?, ?)", [Auth::id(), $objetoId]);
        }
    }

    /** Detonador b): petición que alcanza el umbral pasa a trazabilidad. */
    private function evaluarUmbral(array $p): void
    {
        if ($p['tipo'] !== 'peticion' || $p['estado'] !== 'abierta') {
            return;
        }
        $likes = (int) Database::valor(
            'SELECT COUNT(*) FROM foro_likes WHERE publicacion_id = ?', [$p['id']]);
        if ($likes >= (int) $p['umbral_likes']) {
            Database::ejecutar(
                "UPDATE foro_publicaciones SET estado = 'en_trazabilidad' WHERE id = ? AND estado = 'abierta'",
                [$p['id']]
            );
            flash('exito', 'La petición alcanzó el umbral de apoyos y pasó a la etapa de trazabilidad.');
        }
    }

    public function actualizarUmbral(string $id): void
    {
        if (!Auth::tieneRol('admin')) {
            flash('error', 'Solo los administradores pueden cambiar el umbral de apoyos.');
            redirigir('foro/' . $id);
        }
        $umbral = (int) $this->post('umbral_likes');
        if ($umbral < 1) {
            flash('error', 'El umbral debe ser mayor o igual a 1.');
            redirigir('foro/' . $id);
        }
        
        Database::ejecutar(
            'UPDATE foro_publicaciones SET umbral_likes = ? WHERE id = ?',
            [$umbral, (int) $id]
        );
        flash('exito', 'Umbral de apoyos actualizado.');

        $p = $this->obtener((int) $id);
        $this->evaluarUmbral($p);

        redirigir('foro/' . $p['id']);
    }

    private function obtener(int $id): array
    {
        $p = Database::una(
            'SELECT p.*, u.nombre AS autor,
                    (SELECT COUNT(*) FROM foro_likes l WHERE l.publicacion_id = p.id) AS likes,
                    EXISTS(SELECT 1 FROM foro_likes l2 WHERE l2.publicacion_id = p.id AND l2.usuario_id = ?) AS mi_like
               FROM foro_publicaciones p JOIN usuarios u ON u.id = p.autor_id WHERE p.id = ?',
            [Auth::id(), $id]);
        if (!$p) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No encontrada']);
            exit;
        }
        return $p;
    }
}
