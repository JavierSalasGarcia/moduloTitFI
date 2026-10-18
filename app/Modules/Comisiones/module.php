<?php

use App\Modules\Comisiones\ComisionesController;

return [
    'clave' => 'comisiones',
    'menu'  => [
        // Visible para todos: la bitácora es auditable por la comunidad
        ['seccion' => 'COMUNIDAD', 'titulo' => 'Comisiones', 'ruta' => 'comisiones',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 22],
    ],
    'rutas' => [
        'GET /comisiones'                 => [ComisionesController::class, 'index'],
        'GET /comisiones/nueva'           => [ComisionesController::class, 'nueva'],
        'POST /comisiones'                => [ComisionesController::class, 'crear'],
        'GET /comisiones/archivo/{id}'    => [ComisionesController::class, 'archivo'],
        'GET /comisiones/{id}'            => [ComisionesController::class, 'detalle'],
        'POST /comisiones/{id}/responder' => [ComisionesController::class, 'responder'],
        'POST /comisiones/{id}/postular'  => [ComisionesController::class, 'postular'],
        'POST /comisiones/{id}/invitar'   => [ComisionesController::class, 'invitar'],
        'POST /comisiones/{id}/bitacora'  => [ComisionesController::class, 'agregarBitacora'],
        'POST /comisiones/{id}/estado'    => [ComisionesController::class, 'cambiarEstado'],
        'POST /comisiones/{id}/compromiso'=> [ComisionesController::class, 'compromiso'],
    ],
];
