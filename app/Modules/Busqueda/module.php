<?php

use App\Modules\Busqueda\BusquedaController;

return [
    'clave' => 'busqueda',
    'menu'  => [
        ['seccion' => 'RECURSOS', 'titulo' => '¿A dónde acudir?', 'ruta' => 'busqueda',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 13],
    ],
    'rutas' => [
        'GET /busqueda' => [BusquedaController::class, 'index'],
    ],
];
