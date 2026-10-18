<?php

use App\Modules\Ajustes\AjustesController;

return [
    'clave' => 'ajustes',
    'roles' => ['admin'],
    'menu'  => [
        ['seccion'=>'ADMINISTRACIÓN','titulo'=>'Cuentas y acceso','ruta'=>'ajustes/accesos','roles'=>['admin'],'orden'=>43],
        ['seccion'=>'ADMINISTRACIÓN','titulo'=>'Registro de acciones','ruta'=>'ajustes/auditoria','roles'=>['admin'],'orden'=>44],
        ['seccion' => 'ADMINISTRACIÓN', 'titulo' => 'Ajustes', 'ruta' => 'ajustes',
         'roles' => ['admin'], 'orden' => 40],
        ['seccion' => 'ADMINISTRACIÓN', 'titulo' => 'Permisos y coordinaciones', 'ruta' => 'ajustes/usuarios',
         'roles' => ['admin'], 'orden' => 42],
    ],
    'rutas' => [
        'GET /ajustes/ia' => [\App\Modules\Ajustes\InteligenciaController::class,'index'],
        'POST /ajustes/ia' => [\App\Modules\Ajustes\InteligenciaController::class,'guardar'],
        'POST /ajustes/ia/probar' => [\App\Modules\Ajustes\InteligenciaController::class,'probar'],
        'POST /ajustes/ia/clasificar' => [\App\Modules\Ajustes\InteligenciaController::class,'clasificar'],
        'GET /ajustes/accesos' => [\App\Modules\Ajustes\AccesosController::class,'index'],
        'POST /ajustes/accesos/probar-correo' => [\App\Modules\Ajustes\AccesosController::class,'probarCorreo'],
        'POST /ajustes/accesos/activar' => [\App\Modules\Ajustes\AccesosController::class,'activar'],
        'POST /ajustes/accesos/impersonar' => [\App\Modules\Ajustes\AccesosController::class,'impersonar'],
        'GET /ajustes/auditoria' => [\App\Modules\Ajustes\AccesosController::class,'historial'],
        'GET /ajustes'  => [AjustesController::class, 'index'],
        'POST /ajustes' => [AjustesController::class, 'guardar'],
        'POST /ajustes/probar-nvidia' => [AjustesController::class, 'probarNvidia'],
        'GET /ajustes/usuarios' => [AjustesController::class, 'usuarios'],
        'GET /ajustes/usuarios/exportar' => [AjustesController::class, 'exportar'],
        'POST /ajustes/usuarios/crear' => [AjustesController::class, 'crearUsuario'],
        'POST /ajustes/usuarios/{id}/actualizar' => [AjustesController::class, 'actualizarUsuario'],
        'POST /ajustes/usuarios/{id}/eliminar' => [AjustesController::class, 'eliminarUsuario'],
    ],
];
