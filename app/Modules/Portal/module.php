<?php

use App\Modules\Portal\PortalController;
use App\Modules\Portal\SeccionController;

return [
    'clave' => 'portal',
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Mi Portal', 'ruta' => '',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 1],
    ],
    'rutas' => [
        'GET /'          => [PortalController::class, 'index'],
        'POST /consulta' => [PortalController::class, 'consultar'],
        // Página de entrada de un grupo del menú (tarjetas con las entradas que la persona puede ver).
        'GET /seccion/{clave}' => [SeccionController::class, 'ver'],
    ],
];
