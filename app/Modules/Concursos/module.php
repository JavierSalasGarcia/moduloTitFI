<?php

use App\Modules\Concursos\ConcursosController;

$todos = ['admin', 'titular', 'operativo', 'coordinador', 'profesor', 'alumno'];

return [
    'clave' => 'concursos',
    'pendientes' => 'App\Modules\Concursos\PendientesConcursos::de',
    'menu'  => [
        ['seccion' => 'CONCURSOS', 'titulo' => 'Actuales', 'ruta' => 'concursos', 'roles' => $todos, 'orden' => 40],
        ['seccion' => 'CONCURSOS', 'titulo' => 'Pasados', 'ruta' => 'concursos/pasados', 'roles' => $todos, 'orden' => 41],
        ['seccion' => 'CONCURSOS', 'titulo' => 'Gestión', 'ruta' => 'concursos/gestion', 'roles' => $todos, 'orden' => 42,
         'condicion' => 'App\Modules\Concursos\Concursos::puedeGestionar'],
    ],
    'rutas' => [
        'GET /concursos'                         => [ConcursosController::class, 'index'],
        'GET /concursos/pasados'                 => [ConcursosController::class, 'pasados'],
        'GET /concursos/gestion'                 => [ConcursosController::class, 'gestion'],
        'GET /concursos/gestion/nuevo'           => [ConcursosController::class, 'nuevo'],
        'GET /concursos/gestion/{id}'            => [ConcursosController::class, 'editar'],
        'POST /concursos/gestion'                => [ConcursosController::class, 'guardar'],
        'GET /concursos/{slug}/prefiltro'        => [ConcursosController::class, 'prefiltro'],
        'POST /concursos/{slug}/prefiltro/{id}'  => [ConcursosController::class, 'dictaminar'],
        'POST /concursos/{slug}/desempate'       => [ConcursosController::class, 'desempate'],
        'POST /concursos/{slug}/obra'            => [ConcursosController::class, 'enviar'],
        'POST /concursos/{slug}/obra/{id}/retirar' => [ConcursosController::class, 'retirar'],
        'POST /concursos/{slug}/votar'           => [ConcursosController::class, 'votar'],
        'POST /concursos/{slug}/favorito'        => [ConcursosController::class, 'favorito'],
        // La convocatoria y la verificación de recibos se ven sin iniciar sesión.
        'GET /concursos/{slug}'                  => [ConcursosController::class, 'ver', 'publico'],
        'GET /concursos/{slug}/recibos'          => [ConcursosController::class, 'recibos', 'publico'],
    ],
];
