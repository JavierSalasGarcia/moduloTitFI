<?php
use App\Modules\Fila\FilaController;
use App\Modules\Fila\HorariosController;
use App\Modules\Fila\ExpedienteController;
return [
    'clave' => 'fila',
    'menu' => [['seccion'=>'COMUNIDAD', 'titulo'=>'Fila', 'ruta'=>'fila',
        'icono'=>'reloj', 'roles'=>['admin','titular','operativo','profesor','alumno'], 'orden'=>35]],
    'rutas' => ['GET /fila'=>[FilaController::class, 'index'],
        'POST /fila/atender/{clave}/siguiente'=>[FilaController::class,'siguiente'],
        'POST /fila/atender/{clave}/directa'=>[FilaController::class,'directa'],
        'POST /fila/atender/{clave}/disponibilidad'=>[FilaController::class,'disponibilidad'],
        'POST /fila/atender/{clave}/token'=>[FilaController::class,'token'],
        'GET /fila/horarios/{clave}'=>[HorariosController::class,'index'],
        'POST /fila/horarios/{clave}'=>[HorariosController::class,'guardar'],
        'GET /fila/expediente/{clave}'=>[ExpedienteController::class,'index'],
        'POST /fila/expediente/{clave}/directa'=>[ExpedienteController::class,'directa'],
        'POST /fila/expediente/{clave}/modalidad'=>[ExpedienteController::class,'modalidad'],
        'POST /fila/expediente/{clave}/minuta'=>[ExpedienteController::class,'guardar'],
        'POST /fila/expediente/{clave}/minuta/reenviar'=>[ExpedienteController::class,'reenviar'],
        'GET /fila/expediente/{clave}/archivo/{origen}/{id}'=>[ExpedienteController::class,'descargar', 'token']],
];
