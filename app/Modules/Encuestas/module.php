<?php

use App\Modules\Encuestas\EncuestasController;

return [
    'clave' => 'encuestas',
    'pendientes' => 'App\Modules\Encuestas\PendientesEncuestas::de',
    'menu'  => [
        ['seccion' => 'COMUNIDAD', 'titulo' => 'Encuestas', 'ruta' => 'encuestas',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 21],
    ],
    'rutas' => [
        'GET /encuestas'              => [EncuestasController::class, 'index'],
        'GET /encuestas/nueva'        => [EncuestasController::class, 'nueva'],
        'POST /encuestas'             => [EncuestasController::class, 'crear'],
        'GET /encuestas/{id}'         => [EncuestasController::class, 'detalle'],
        // Requiere cuenta, pero la bitácora omite su identidad para no crear
        // una relación indirecta entre el votante y el recibo.
        'POST /encuestas/{id}/votar'  => [EncuestasController::class, 'votar', 'auditoria_anonima'],
        'POST /encuestas/{id}/cerrar' => [EncuestasController::class, 'cerrar'],
        // Tablero público: verificable sin iniciar sesión
        'GET /encuestas/{id}/tablero'     => [EncuestasController::class, 'tablero', 'publico'],
        'GET /encuestas/{id}/tablero/csv' => [EncuestasController::class, 'tableroCsv', 'publico'],
    ],
];
