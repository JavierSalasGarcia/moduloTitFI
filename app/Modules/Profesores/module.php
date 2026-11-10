<?php

use App\Modules\Profesores\ProfesoresController;

return [
    'clave' => 'profesores',
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'profesores/rrhh', 'descripcion' => 'Directorio de profesores para Recursos Humanos: adscripciones y cuentas.', 'palabras' => 'profesor docente rrhh adscripción'],
    ],
    'menu'  => [
        ['seccion' => 'ADMINISTRACIÓN', 'titulo' => 'Directorio de profesores', 'ruta' => 'profesores/rrhh',
         'roles' => ['admin', 'titular', 'operativo'], 'orden' => 45],
    ],
    'rutas' => [
        'GET /profesores/rrhh'                       => [ProfesoresController::class, 'index'],
        'POST /profesores/rrhh/subir'                => [ProfesoresController::class, 'subir'],
        'POST /profesores/rrhh/enlazar'              => [ProfesoresController::class, 'enlazar'],
        'POST /profesores/rrhh/correo'               => [ProfesoresController::class, 'usarCorreo'],
        'POST /profesores/rrhh/responsables/agregar' => [ProfesoresController::class, 'agregarResponsable'],
        'POST /profesores/rrhh/responsables/quitar'  => [ProfesoresController::class, 'quitarResponsable'],
    ],
];
