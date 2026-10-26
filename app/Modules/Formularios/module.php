<?php

use App\Modules\Formularios\FormulariosController;

return [
    'clave' => 'formularios',
    'menu'  => [
        ['seccion' => 'ADMINISTRACIÓN', 'titulo' => 'Formularios', 'ruta' => 'formularios',
         'roles' => ['admin', 'titular', 'operativo', 'coordinador', 'profesor'], 'orden' => 90,
         'condicion' => 'App\Modules\Formularios\Formularios::puedeAlguno'],
    ],
    'rutas' => [
        'GET /formularios'                                => [FormulariosController::class, 'index'],
        'GET /formularios/{id}'                           => [FormulariosController::class, 'respuestas'],
        'POST /formularios/{id}/ajustes'                  => [FormulariosController::class, 'ajustes'],
        'POST /formularios/{id}/respuestas/{rid}'         => [FormulariosController::class, 'corregir'],
        'POST /formularios/{id}/respuestas/{rid}/eliminar' => [FormulariosController::class, 'eliminar'],
        'GET /formularios/{id}/excel'                     => [FormulariosController::class, 'excel', 'token'],
        // Enlace público: se llena sin cuenta en Traza FI.
        'GET /f/{slug}'                                   => [FormulariosController::class, 'llenar', 'publico'],
        'POST /f/{slug}'                                  => [FormulariosController::class, 'enviar', 'publico'],
    ],
];
