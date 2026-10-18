<?php

use App\Modules\Encuestas\EncuestasController;

return [
    'clave' => 'encuestas',
    'menu'  => [
        ['seccion' => 'COMUNIDAD', 'titulo' => 'Encuestas', 'ruta' => 'encuestas',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 21],
    ],
    'rutas' => [
        'GET /encuestas'              => [EncuestasController::class, 'index'],
        'GET /encuestas/nueva'        => [EncuestasController::class, 'nueva'],
        'POST /encuestas'             => [EncuestasController::class, 'crear'],
        'GET /encuestas/{id}'         => [EncuestasController::class, 'detalle'],
        'POST /encuestas/{id}/votar'  => [EncuestasController::class, 'votar'],
        'POST /encuestas/{id}/cerrar' => [EncuestasController::class, 'cerrar'],
        // Tablero público: verificable sin iniciar sesión
        'GET /encuestas/{id}/tablero'     => [EncuestasController::class, 'tablero', 'publico'],
        'GET /encuestas/{id}/tablero/csv' => [EncuestasController::class, 'tableroCsv', 'publico'],
    ],
];
