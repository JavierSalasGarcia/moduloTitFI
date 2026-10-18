<?php

use App\Modules\Comunicados\ComunicadosController;

return [
    'clave' => 'comunicados',
    'menu'  => [],
    'rutas' => [
        'GET /comunicados'                => [ComunicadosController::class, 'index'],
        'GET /comunicados/nuevo'          => [ComunicadosController::class, 'nuevo'],
        'POST /comunicados'               => [ComunicadosController::class, 'crear'],
        'POST /comunicados/{id}/eliminar' => [ComunicadosController::class, 'eliminar'],
        'POST /comunicados/{id}/categoria' => [ComunicadosController::class, 'actualizarCategoria'],
    ],
];
