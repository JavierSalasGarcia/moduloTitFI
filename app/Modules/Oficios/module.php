<?php

use App\Modules\Oficios\OficiosController;

return [
    'clave' => 'oficios',
    'roles' => ['admin', 'titular', 'coordinador', 'operativo'],
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Oficios enviados', 'ruta' => 'oficios',
         'roles' => ['admin', 'titular', 'coordinador', 'operativo'], 'orden' => 36],
    ],
    'rutas' => [
        // El orden importa: /nuevo debe evaluarse antes que /{id}
        'GET /oficios'              => [OficiosController::class, 'index'],
        'GET /oficios/nuevo'        => [OficiosController::class, 'nuevo'],
        'POST /oficios'             => [OficiosController::class, 'crear'],
        'GET /oficios/{id}/archivo' => [OficiosController::class, 'archivo'],
        'GET /oficios/{id}'         => [OficiosController::class, 'detalle'],
        'POST /oficios/{id}'        => [OficiosController::class, 'actualizar'],
    ],
];
