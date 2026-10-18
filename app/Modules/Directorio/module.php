<?php

use App\Modules\Directorio\DirectorioController;

return [
    'clave' => 'directorio',
    'menu'  => [
        ['seccion' => 'RECURSOS', 'titulo' => 'Directorio', 'ruta' => 'directorio',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 39],
    ],
    'rutas' => [
        'GET /directorio'      => [DirectorioController::class, 'index'],
        'GET /directorio/{id}' => [DirectorioController::class, 'detalle'],
    ],
];
