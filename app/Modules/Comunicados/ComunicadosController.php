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
    private const CLAVES_EMISORAS = [
        'coordinacion_de_estudios_avanzados',
        'departamento_de_servicios_escolares',
        'coordinacion_de_difusion_cultural',
    ];

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
        $puede = $c && (Auth::rol() === 'admin' || (int) $c['depto_id'] === Auth::departamentoId());
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
        if (!$this->puedeEmitir()) {
            flash('error', 'Tu departamento no está autorizado para emitir comunicados.');
            redirigir('eventos');
        }
        $this->vista('nuevo', [
            'titulo'    => 'Nuevo comunicado',
            'programas' => Database::todas("SELECT * FROM programas ORDER BY nivel = 'posgrado', siglas"),
        ]);
    }

    public function crear(): void
    {
        if (!$this->puedeEmitir()) {
            flash('error', 'Tu departamento no está autorizado para emitir comunicados.');
            redirigir('eventos');
        }
        $titulo = $this->post('titulo');
        $contenido = $this->post('contenido');
        $alcance = $this->post('alcance');
        $programaId = (int) $this->post('programa');

        if ($titulo === '' || $contenido === ''
            || !in_array($alcance, ['todos', 'licenciatura', 'posgrado', 'programa'], true)
            || ($alcance === 'programa' && !$programaId)) {
            flash('error', 'Completa título, contenido y alcance (elige el programa si el alcance es por programa).');
            redirigir('comunicados/nuevo');
        }

        $vence = $this->post('vence_en');
        if ($vence !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $vence) || date('Y-m-d', strtotime($vence)) !== $vence)) {
            flash('error','La fecha de vigencia no es válida.'); redirigir('comunicados/nuevo');
        }
        $nuevoId = Database::ejecutar(
            'INSERT INTO comunicados (autor_id, depto_id, titulo, contenido, alcance, programa_id) VALUES (?, ?, ?, ?, ?, ?)',
            [Auth::id(), Auth::departamentoId(), $titulo, $contenido, $alcance,
             $alcance === 'programa' ? $programaId : null]
        );
        if ($vence !== '') Database::ejecutar('INSERT INTO ajustes (clave,valor,descripcion) VALUES (?,?,?)', ['comunicado_vence_'.$nuevoId,$vence,'Vigencia del aviso']);
        flash('exito', 'Comunicado publicado.');
        redirigir('eventos');
    }

    public function eliminar(string $id): void
    {
        $c = Database::una('SELECT * FROM comunicados WHERE id = ?', [(int) $id]);
        $puede = $c && (Auth::rol() === 'admin' || (int) $c['depto_id'] === Auth::departamentoId());
        if (!$puede) {
            flash('error', 'Solo el departamento emisor puede retirar el comunicado.');
        } else {
            Database::ejecutar('DELETE FROM comunicados WHERE id = ?', [$c['id']]);
            flash('exito', 'Comunicado retirado.');
        }
        redirigir('eventos');
    }

    /** Coordinaciones de docencia, Estudios Avanzados, Escolares, Difusión o admin. */
    private function puedeEmitir(): bool
    {
        if (Auth::rol() === 'admin') {
            return true;
        }
        if (!Auth::tieneRol('titular', 'operativo')) {
            return false;
        }
        $clave = Auth::usuario()['depto_clave'];
        return in_array($clave, self::CLAVES_EMISORAS, true)
            || str_starts_with($clave, 'coordinacion_de_docencia_de_la_licenciatura');
    }
}
