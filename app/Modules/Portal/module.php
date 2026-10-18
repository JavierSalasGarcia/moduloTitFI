<?php

use App\Modules\Portal\PortalController;

return [
    'clave' => 'portal',
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Mi Portal', 'ruta' => '',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 1],
    ],
    'rutas' => [
        'GET /'          => [PortalController::class, 'index'],
        'POST /consulta' => [PortalController::class, 'consultar'],
    ],
];
