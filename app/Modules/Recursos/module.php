<?php

use App\Core\Database;
use App\Modules\Recursos\RecursosController;

// El menú RECURSOS es dinámico: cada fila activa de la tabla `recursos`
// (configurada por el administrador en su panel) genera una entrada.
$menu = [
    ['seccion' => 'ADMINISTRACIÓN', 'titulo' => 'Recursos del menú', 'ruta' => 'recursos',
     'roles' => ['admin'], 'orden' => 41],
];
try {
    $todos = ['admin', 'titular', 'operativo', 'profesor', 'alumno'];
    foreach (Database::todas('SELECT id, titulo, orden FROM recursos WHERE activo = 1 ORDER BY orden, titulo') as $i => $r) {
        $menu[] = [
            'seccion'  => 'RECURSOS',
            'titulo'   => $r['titulo'],
            'ruta'     => 'recursos/' . $r['id'],
            'roles'    => $todos,
            'orden'    => 31 + $i,
            'dinamico' => true, // resaltar solo si la ruta coincide exactamente
        ];
    }
} catch (Throwable $e) {
    // BD sin migrar todavía: el menú dinámico simplemente no aparece
}

return [
    'clave' => 'recursos',
    'menu'  => $menu,
    'rutas' => [
        // Panel del administrador
        'GET /recursos'                  => [RecursosController::class, 'panel'],
        'POST /recursos'                 => [RecursosController::class, 'crear'],
        'POST /recursos/{id}/actualizar' => [RecursosController::class, 'actualizar'],
        'POST /recursos/{id}/eliminar'   => [RecursosController::class, 'eliminar'],
        // Ficha y notas personales (las rutas literales van antes de {id})
        'GET /recursos/archivo/{id}'     => [RecursosController::class, 'archivo', 'token'],
        'GET /recursos/{id}'             => [RecursosController::class, 'ficha'],
        'POST /recursos/{id}/notas'      => [RecursosController::class, 'agregarNota'],
        'POST /recursos/notas/{id}/editar'   => [RecursosController::class, 'editarNota'],
        'POST /recursos/notas/{id}/eliminar' => [RecursosController::class, 'eliminarNota'],
    ],
];
