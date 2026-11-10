<?php

use App\Modules\Directorio\DirectorioController;

return [
    'clave' => 'directorio',
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'directorio', 'descripcion' => 'Directorio de áreas, titulares y contactos de la Facultad.', 'palabras' => 'teléfono correo extensión contacto quién titular jefe coordinador'],
    ],
    'menu'  => [
        ['seccion' => 'RECURSOS', 'titulo' => 'Directorio', 'ruta' => 'directorio',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 39],
    ],
    'rutas' => [
        'GET /directorio'      => [DirectorioController::class, 'index'],
        'GET /directorio/{id}' => [DirectorioController::class, 'detalle'],
    ],
];
