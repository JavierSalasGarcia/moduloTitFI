<?php

use App\Modules\Eventos\EventosController;

return [
    'clave' => 'eventos',
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Avisos y eventos', 'ruta' => 'eventos',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 4],
        ['seccion' => 'RECURSOS', 'titulo' => 'Biblioteca de eventos', 'ruta' => 'eventos/biblioteca',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 38, 'dinamico' => true],
    ],
    'rutas' => [
        'GET /eventos'                 => [EventosController::class, 'index'],
        'GET /eventos/biblioteca'      => [EventosController::class, 'biblioteca'],
        'POST /eventos'                => [EventosController::class, 'crear'],
        'GET /eventos/{id}'            => [EventosController::class, 'detalle'],
        'POST /eventos/{id}/suscribir' => [EventosController::class, 'suscribir'],
        'GET /eventos/{id}/exportar-ics'=> [EventosController::class, 'exportarIcs'],
    ],
];
