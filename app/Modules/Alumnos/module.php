<?php

use App\Modules\Alumnos\AlumnosController;

return [
    'clave' => 'alumnos',
    'roles' => ['admin', 'titular', 'operativo'],
    'menu'  => [],
    'rutas' => [
        'GET /alumnos'  => [AlumnosController::class, 'index'],
        'POST /alumnos' => [AlumnosController::class, 'crear'],
    ],
];
