<?php

use App\Modules\Calendario\CalendarioController;

return [
    'clave' => 'calendario',
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'calendario', 'descripcion' => 'Tu calendario personal con reuniones y eventos; se puede vincular con Outlook.', 'palabras' => 'calendario agenda outlook horario'],
    ],
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
