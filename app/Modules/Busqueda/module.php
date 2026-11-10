<?php

use App\Modules\Busqueda\BusquedaController;

return [
    'clave' => 'busqueda',
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'busqueda', 'descripcion' => '¿A dónde acudir? Encuentra el área responsable de un trámite según el Manual de Organización.', 'palabras' => 'área responsable trámite dónde acudir departamento'],
    ],
    'menu'  => [
        ['seccion' => 'RECURSOS', 'titulo' => '¿A dónde acudir?', 'ruta' => 'busqueda',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 13],
    ],
    'rutas' => [
        'GET /busqueda' => [BusquedaController::class, 'index'],
    ],
];
