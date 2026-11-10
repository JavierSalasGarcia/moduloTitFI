<?php

use App\Modules\Tickets\TicketsController;

return [
    'clave' => 'tickets',
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'tickets', 'descripcion' => 'Crea y da seguimiento a tus solicitudes a las áreas de la Facultad; consulta su estado y las respuestas.', 'palabras' => 'solicitud ticket petición seguimiento estado respuesta'],
    ],
    'pendientes' => 'App\Modules\Tickets\PendientesTickets::de',
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
