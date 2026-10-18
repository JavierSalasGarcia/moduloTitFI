<?php

use App\Modules\Calendario\CalendarioController;

return [
    'clave' => 'calendario',
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Mi Calendario', 'ruta' => 'calendario',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 30],
    ],
    'rutas' => [
        'GET /calendario' => [CalendarioController::class, 'index'],
        'POST /calendario/vincular-ics' => [CalendarioController::class, 'vincularIcs'],
        'POST /calendario/sincronizar' => [CalendarioController::class, 'sincronizarIcs'],
    ],
];
