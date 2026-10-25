<?php

use App\Modules\Tickets\TicketsController;

return [
    'clave' => 'tickets',
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Tickets', 'ruta' => 'tickets',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 11],
    ],
    'rutas' => [
        'GET /tickets'                => [TicketsController::class, 'index'],
        'GET /tickets/nuevo'          => [TicketsController::class, 'nuevo'],
        'POST /tickets'               => [TicketsController::class, 'crear'],
        'GET /tickets/{id}'           => [TicketsController::class, 'detalle'],
        'POST /tickets/{id}/comentar'   => [TicketsController::class, 'comentar'],
        'POST /tickets/{id}/estado'     => [TicketsController::class, 'cambiarEstado'],
        'POST /tickets/{id}/clasificar' => [TicketsController::class, 'clasificar'],
        'POST /tickets/{id}/rechazar'   => [TicketsController::class, 'rechazar'],
        'POST /tickets/{id}/redirigir'  => [TicketsController::class, 'redirigir'],
        'GET /tickets/archivo/{id}'     => [TicketsController::class, 'descargarArchivo', 'token'],
    ],
];
