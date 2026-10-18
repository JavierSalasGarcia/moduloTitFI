<?php
use App\Modules\Asuntos\AsuntosController;

return [
    'clave' => 'asuntos',
    'rutas' => [
        // El segmento identifica la oficina; SA conserva los QR ya impresos.
        'GET /fila/{oficina}/reg.php' => [AsuntosController::class, 'index', 'publico'],
        'POST /fila/{oficina}/reg.php' => [AsuntosController::class, 'procesar', 'publico'],
        'GET /fila/{oficina}/archivo/{id}' => [AsuntosController::class, 'descargar'],
    ],
];
