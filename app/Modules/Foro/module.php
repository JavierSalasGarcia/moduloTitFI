<?php

use App\Modules\Foro\ForoController;

return [
    'clave' => 'foro',
    'menu'  => [
        ['seccion' => 'COMUNIDAD', 'titulo' => 'Foro', 'ruta' => 'foro',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 20],
    ],
    'rutas' => [
        'GET /foro'                => [ForoController::class, 'index'],
        'POST /foro'               => [ForoController::class, 'publicar'],
        'GET /foro/{id}'           => [ForoController::class, 'detalle'],
        'POST /foro/{id}/comentar' => [ForoController::class, 'comentar'],
        'POST /foro/{id}/like'     => [ForoController::class, 'likePublicacion'],
        'POST /foro/{id}/umbral'   => [ForoController::class, 'actualizarUmbral'],
        'POST /foro/comentario/{id}/like' => [ForoController::class, 'likeComentario'],
    ],
];
