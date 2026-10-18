<?php

use App\Modules\Funciones\FuncionesController;

return [
    'clave' => 'funciones',
    'roles' => ['admin', 'titular', 'operativo', 'profesor'],
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Mis Funciones', 'ruta' => 'funciones',
         'roles' => ['admin', 'titular', 'operativo', 'profesor'], 'orden' => 2],
    ],
    'rutas' => [
        'GET /funciones' => [FuncionesController::class, 'index'],
    ],
];
