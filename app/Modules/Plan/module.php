<?php
use App\Modules\Plan\PlanController;

return [
    'clave' => 'plan',
    'menu' => [[
        'seccion' => 'FILA', 'titulo' => 'Plan de Desarrollo FI', 'ruta' => 'fila/plan',
        'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 8,
    ]],
    'rutas' => [
        'GET /plan' => [PlanController::class, 'index', 'publico'],
        'GET /fila/plan' => [PlanController::class, 'index', 'publico'],
        'GET /plan/graficas' => [PlanController::class, 'graficas', 'publico'],
        'GET /plan/graficas/datos/{id}' => [PlanController::class, 'graficasDatos', 'publico'],
        'GET /plan/estatus' => [PlanController::class, 'estatus', 'publico'],
        'POST /plan/estatus' => [PlanController::class, 'estatusGuardar', 'publico'],
        'GET /plan/api/{id}' => [PlanController::class, 'api', 'publico'],
        'GET /consulta-fi/{token}' => [PlanController::class, 'encuesta', 'publico'],
        'POST /consulta-fi/{token}' => [PlanController::class, 'encuestaGuardar', 'publico'],
        'GET /plan/agenda' => [PlanController::class, 'agenda', 'publico'],
        'GET /plan/encuestas/api' => [PlanController::class, 'encuestasApi', 'publico'],
        'GET /plan/encuestas/resultados/{id}' => [PlanController::class, 'encuestasResultados', 'publico'],
        'GET /plan/encuestas/contactos/{id}' => [PlanController::class, 'encuestasContactos', 'publico'],
        'POST /plan/guardar' => [PlanController::class, 'guardar', 'publico'],
        'GET /plan/exportar/{id}/json' => [PlanController::class, 'json', 'publico'],
        'GET /plan/exportar/{id}/xlsx' => [PlanController::class, 'xlsx', 'publico'],
    ],
];
