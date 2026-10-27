<?php

use App\Modules\Justificantes\JustificantesController;

$todos = ['admin', 'titular', 'operativo', 'coordinador', 'profesor'];

return [
    'clave' => 'justificantes',
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Justificar faltas de alumnos', 'ruta' => 'justificantes',
         'roles' => $todos, 'orden' => 37, 'condicion' => 'App\Modules\Justificantes\Justificantes::puede'],
    ],
    'rutas' => [
        // El orden importa: /nuevo debe evaluarse antes que /{id}
        'GET /justificantes'              => [JustificantesController::class, 'index'],
        'GET /justificantes/nuevo'        => [JustificantesController::class, 'nuevo'],
        'POST /justificantes'             => [JustificantesController::class, 'crear'],
        'GET /justificantes/{id}/archivo' => [JustificantesController::class, 'archivo'],
    ],
];
