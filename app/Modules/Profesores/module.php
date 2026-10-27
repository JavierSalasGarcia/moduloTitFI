<?php

use App\Modules\Profesores\ProfesoresController;

return [
    'clave' => 'profesores',
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
