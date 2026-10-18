<?php
declare(strict_types=1);

namespace App\Modules\Directorio;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

final class DirectorioController extends Controller
{
    public function index(): void
    {
        $q = trim((string) ($_GET['q'] ?? ''));
        $sql = 'SELECT d.id, d.nombre, d.origen_mo, a.id AS area_id, a.nombre AS area,
                       (SELECT COUNT(*) FROM funciones f WHERE f.departamento_id = d.id) AS num_funciones
                  FROM departamentos d JOIN areas a ON a.id = d.area_id';
        $params = [];
        if ($q !== '') {
            $sql .= ' WHERE d.nombre LIKE ? OR EXISTS (SELECT 1 FROM usuarios u WHERE u.departamento_id=d.id AND u.activo=1 AND (u.nombre LIKE ? OR u.email LIKE ?)) OR EXISTS (SELECT 1 FROM funciones f WHERE f.departamento_id=d.id AND f.descripcion LIKE ?)';
            $params = array_fill(0,4,"%{$q}%");
        }
        $deptos = Database::todas($sql . ' ORDER BY a.orden, d.nombre', $params);

        // Agrupar por área para el organigrama
        $porArea = [];
        foreach ($deptos as $d) {
            $porArea[$d['area']][] = $d;
        }

        $this->vista('index', ['titulo' => 'Directorio', 'porArea' => $porArea, 'q' => $q]);
    }

    public function detalle(string $id): void
    {
        $d = Database::una(
            'SELECT d.*, a.nombre AS area FROM departamentos d JOIN areas a ON a.id = d.area_id WHERE d.id = ?',
            [(int) $id]
        );
        if (!$d) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No encontrado']);
            return;
        }

        $funciones = Database::todas(
            'SELECT numero, descripcion FROM funciones WHERE departamento_id = ? ORDER BY numero', [$d['id']]);
        $titulares = Database::todas(
            "SELECT nombre, email, rol FROM usuarios WHERE departamento_id = ? AND activo = 1", [$d['id']]);
        $canalesSalida = Database::todas(
            'SELECT r.descripcion, x.id, x.nombre FROM relaciones r JOIN departamentos x ON x.id = r.depto_destino_id
              WHERE r.depto_origen_id = ? AND r.activa = 1 ORDER BY x.nombre', [$d['id']]);
        $canalesEntrada = Database::todas(
            'SELECT r.descripcion, x.id, x.nombre FROM relaciones r JOIN departamentos x ON x.id = r.depto_origen_id
              WHERE r.depto_destino_id = ? AND r.activa = 1 ORDER BY x.nombre', [$d['id']]);
        $puedeSolicitar = (bool) Database::una(
            'SELECT 1 FROM relaciones WHERE depto_origen_id = ? AND depto_destino_id = ? AND activa = 1',
            [Auth::departamentoId(), $d['id']]);

        $this->vista('detalle', [
            'titulo'         => $d['nombre'],
            'depto'          => $d,
            'funciones'      => $funciones,
            'titulares'      => $titulares,
            'canalesSalida'  => $canalesSalida,
            'canalesEntrada' => $canalesEntrada,
            'puedeSolicitar' => $puedeSolicitar,
        ]);
    }
}
