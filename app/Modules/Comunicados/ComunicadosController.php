<?php
declare(strict_types=1);

namespace App\Modules\Comunicados;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Comunicados granulares para alumnos: todos, por nivel (licenciatura/
 * posgrado) o por programa específico. Emiten: coordinaciones de
 * docencia, Estudios Avanzados, Servicios Escolares (control escolar)
 * y Difusión Cultural (además de la administración de la plataforma).
 */
final class ComunicadosController extends Controller
{
    public function index(): void
    {
        redirigir('eventos');
        $u = Auth::usuario();
        $puedeEmitir = $this->puedeEmitir();

        $sql = 'SELECT c.*, d.nombre AS depto, u2.nombre AS autor, p.siglas
                  FROM comunicados c
                  JOIN departamentos d ON d.id = c.depto_id
                  JOIN usuarios u2 ON u2.id = c.autor_id
                  LEFT JOIN programas p ON p.id = c.programa_id';
        
        $conds = [];
        $params = [];
        if ($u['rol'] === 'alumno' && $u['programa_id']) {
            $nivel = $u['programa_nivel'] === 'posgrado' ? 'posgrado' : 'licenciatura';
            $conds[] = "(c.alcance = 'todos' OR c.alcance = ? OR (c.alcance = 'programa' AND c.programa_id = ?))";
            $params[] = $nivel;
            $params[] = $u['programa_id'];
        }
        
        if (!$puedeEmitir) {
            $conds[] = "c.categoria != 'Ocultar'";
        }

        if (!empty($conds)) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }

        $comunicados = Database::todas($sql . ' ORDER BY CASE WHEN c.categoria = \'Importantes\' THEN 1 ELSE 2 END, c.creado_en DESC LIMIT 100', $params);

        $this->vista('index', [
            'titulo'      => 'Comunicados',
            'comunicados' => $comunicados,
            'puedeEmitir' => $puedeEmitir,
        ]);
    }

    public function actualizarCategoria(string $id): void
    {
        if (!$this->puedeEmitir()) {
            flash('error', 'No tienes permisos para modificar este comunicado.');
            redirigir('eventos');
        }
        $c = Database::una('SELECT * FROM comunicados WHERE id = ?', [(int) $id]);
        $puede = $c && (Auth::tieneRol('admin') || in_array((int) $c['depto_id'], Auth::departamentoIds(), true));
        if (!$puede) {
            flash('error', 'Solo el departamento emisor puede editar este comunicado.');
            redirigir('eventos');
        }

        $cat = $this->post('categoria');
        if (!in_array($cat, ['General', 'Importantes', 'Ocultar'], true)) {
            flash('error', 'Categoría no válida.');
            redirigir('eventos');
        }

        $vence = $this->post('vence_en');
        if ($vence !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $vence) || date('Y-m-d', strtotime($vence)) !== $vence)) {
            flash('error','La fecha de vigencia no es válida.'); redirigir('eventos');
        }
        Database::ejecutar('UPDATE comunicados SET categoria = ? WHERE id = ?', [$cat, $c['id']]);
        Database::ejecutar('INSERT INTO ajustes (clave,valor,descripcion) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)', ['comunicado_vence_'.$c['id'], $vence, 'Vigencia del aviso']);
        flash('exito', 'Categoría del comunicado actualizada.');
        redirigir('eventos');
    }

    public function nuevo(): void
    {
        redirigir('eventos');
    }

    public function crear(): void
    {
        if (!Avisos::puedeEmitir()) {
            flash('error', 'Tu cuenta no está autorizada para emitir comunicados.');
            redirigir('eventos');
        }
        try {
            Avisos::publicar([
                'titulo' => $this->post('titulo'), 'contenido' => $this->post('contenido'), 'alcance' => $this->post('alcance'),
                'destinatarios' => $this->post('destinatarios') ?: 'todos', 'programas' => $_POST['programas'] ?? [],
                'vence_en' => $this->post('vence_en'), 'depto_id' => (int) $this->post('depto_id'),
            ]);
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('eventos');
        }
        flash('exito', 'Comunicado publicado.');
        redirigir('eventos');
    }

    public function eliminar(string $id): void
    {
        $c = Database::una('SELECT * FROM comunicados WHERE id = ?', [(int) $id]);
        $puede = $c && (Auth::tieneRol('admin') || in_array((int) $c['depto_id'], Auth::departamentoIds(), true));
        if (!$puede) {
            flash('error', 'Solo el departamento emisor puede retirar el comunicado.');
        } else {
            Database::ejecutar('DELETE FROM comunicados WHERE id = ?', [$c['id']]);
            flash('exito', 'Comunicado retirado.');
        }
        redirigir('eventos');
    }

    /** Coordinaciones de docencia, Estudios Avanzados, Escolares, Difusión, Subdirección Académica o admin. */
    private function puedeEmitir(): bool
    {
        return Avisos::puedeEmitir();
    }
}
