<?php

use App\Modules\Solicitudes\SolicitudesController;

return [
    'clave' => 'solicitudes',
    'roles' => ['admin', 'titular', 'operativo'],
    'menu'  => [],
    'rutas' => [
        // El orden importa: /nueva debe evaluarse antes que /{id}
        'GET /solicitudes'                => [SolicitudesController::class, 'index'],
        'GET /solicitudes/nueva'          => [SolicitudesController::class, 'nueva'],
        'POST /solicitudes'               => [SolicitudesController::class, 'crear'],
        'GET /solicitudes/{id}'           => [SolicitudesController::class, 'detalle'],
        'POST /solicitudes/{id}/comentar' => [SolicitudesController::class, 'comentar'],
        'POST /solicitudes/{id}/estado'   => [SolicitudesController::class, 'cambiarEstado'],
    ],
];
