<?php

use App\Modules\Reuniones\ReunionesController;

return [
    'clave' => 'reuniones',
    'pendientes' => 'App\Modules\Reuniones\PendientesReuniones::de',
    'menu' => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Reuniones', 'ruta' => 'reuniones',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno', 'coordinador'], 'orden' => 32],
    ],
    'rutas' => [
        'GET /reuniones/minutas/nueva' => [\App\Modules\Reuniones\MinutasController::class, 'nueva'],
        'POST /reuniones/minutas' => [\App\Modules\Reuniones\MinutasController::class, 'guardar'],
        'POST /reuniones/minutas/{id}/reenviar' => [\App\Modules\Reuniones\MinutasController::class, 'reenviar'],
        'GET /reuniones/minutas/{id}/archivo' => [\App\Modules\Reuniones\MinutasController::class, 'archivo', 'token'],
        'GET /reuniones/historial/{token}' => [\App\Modules\Reuniones\MinutasController::class, 'historial', 'publico'],
        'GET /reuniones/historial/{token}/archivo/{id}' => [\App\Modules\Reuniones\MinutasController::class, 'archivoPublico', 'publico'],
        'POST /reuniones/historial/{token}/minuta/{id}' => [\App\Modules\Reuniones\MinutasController::class, 'guardarExterna', 'publico'],
        'GET /reuniones' => [ReunionesController::class, 'index'],
        'POST /reuniones/categorias' => [ReunionesController::class, 'crearCategoria'],
        'GET /reuniones/nueva' => [ReunionesController::class, 'nueva'],
        'GET /reuniones/coordinadores' => [ReunionesController::class, 'coordinadores'],
        'POST /reuniones/coordinadores' => [ReunionesController::class, 'asignarCoordinador'],
        'POST /reuniones/coordinadores/{id}/revocar' => [ReunionesController::class, 'revocarCoordinador'],
        'POST /reuniones' => [ReunionesController::class, 'crear'],
        'POST /reuniones/{id}/categoria' => [ReunionesController::class, 'asignarCategoria'],
        'GET /reuniones/{id}' => [ReunionesController::class, 'detalle'],
        'GET /reuniones/{id}/calendario.ics' => [ReunionesController::class, 'descargarIcs', 'token'],
        'POST /reuniones/{id}/disponibilidad' => [ReunionesController::class, 'guardarDisponibilidad'],
        'POST /reuniones/{id}/confirmar' => [ReunionesController::class, 'confirmarHorario'],
        'POST /reuniones/{id}/convocar' => [ReunionesController::class, 'enviarConvocatoria'],
        'POST /reuniones/{id}/invitado/{participanteId}/revocar' => [ReunionesController::class, 'revocarInvitacionExterna'],
        'POST /reuniones/{id}/mensaje' => [ReunionesController::class, 'enviarMensaje'],
        'POST /reuniones/{id}/archivo' => [ReunionesController::class, 'subirArchivoComplementario'],
        'POST /reuniones/{id}/finalizar' => [ReunionesController::class, 'finalizar'],
        'GET /reuniones/{id}/respaldo' => [ReunionesController::class, 'descargarRespaldo', 'token'],
        'GET /reuniones/{id}/archivo/{archivoId}' => [ReunionesController::class, 'descargarArchivo', 'token'],
        'GET /reuniones/invitacion/{token}' => [ReunionesController::class, 'invitacionExterna', 'publico'],
        'GET /reuniones/invitacion/{token}/calendario.ics' => [ReunionesController::class, 'descargarIcsPublico', 'publico'],
        'POST /reuniones/invitacion/{token}/disponibilidad' => [ReunionesController::class, 'guardarDisponibilidadExterna', 'publico'],
        'POST /reuniones/invitacion/{token}/mensaje' => [ReunionesController::class, 'enviarMensajeExterno', 'publico'],
    ],
];
