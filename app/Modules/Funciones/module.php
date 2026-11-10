<?php

use App\Modules\Funciones\FuncionesController;

return [
    'clave' => 'funciones',
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'funciones', 'descripcion' => 'Funciones oficiales de tu área según el Manual de Organización.', 'palabras' => 'funciones manual organización atribuciones'],
    ],
    'roles' => ['admin', 'titular', 'operativo', 'profesor'],
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Mis Funciones', 'ruta' => 'funciones',
         'roles' => ['admin', 'titular', 'operativo', 'profesor'], 'orden' => 2],
    ],
    'rutas' => [
        'GET /funciones' => [FuncionesController::class, 'index'],
    ],
];
