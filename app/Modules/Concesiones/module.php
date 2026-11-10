<?php

use App\Modules\Concesiones\ConcesionesController;

return [
    'clave' => 'concesiones',
    'nombre' => 'Accesos especiales',
    'descripcion' => 'Concesiones de acceso por persona, con vigencia: para quien, por su función, necesita algo que su rol no incluye.',
    'roles' => ['admin', 'titular'],
    'menu' => [
        ['seccion' => 'ADMINISTRACIÓN', 'titulo' => 'Accesos especiales', 'ruta' => 'concesiones', 'roles' => ['admin', 'titular'], 'orden' => 45],
    ],
    // Cualquier persona con una concesión por vencer recibe el aviso; quien concede ve además las de su dependencia.
    'pendientes' => 'App\Modules\Concesiones\PendientesConcesiones::de',
    'rutas' => [
        'GET /concesiones' => [ConcesionesController::class, 'index'],
        'POST /concesiones' => [ConcesionesController::class, 'crear'],
        'POST /concesiones/{id}/revocar' => [ConcesionesController::class, 'revocar'],
    ],
];
