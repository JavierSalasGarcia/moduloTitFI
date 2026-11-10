<?php

use App\Modules\Formularios\FormulariosController;

return [
    'clave' => 'formularios',
    'vinculacion' => 'App\Modules\Formularios\Formularios::vincular',
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'formularios', 'descripcion' => 'Formularios de registro por enlace privado: creación y consulta de respuestas.', 'palabras' => 'formulario registro respuestas excel'],
    ],
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
        'GET /formularios/{id}/respuestas/{rid}/evidencia/{k}' => [FormulariosController::class, 'evidencia', 'token'],
        'GET /formularios/{id}/excel'                     => [FormulariosController::class, 'excel', 'token'],
        // Enlace público: se llena sin cuenta en Traza FI.
        'GET /f/{slug}'                                   => [FormulariosController::class, 'llenar', 'publico'],
        'POST /f/{slug}'                                  => [FormulariosController::class, 'enviar', 'publico'],
    ],
];
