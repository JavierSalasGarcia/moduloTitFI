<?php

use App\Modules\Eventos\AsistenciaController;
use App\Modules\Eventos\AvisosController;
use App\Modules\Eventos\EventosController;

return [
    'clave' => 'eventos',
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Avisos y eventos', 'ruta' => 'eventos',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 4],
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Mi expediente de formación integral', 'ruta' => 'eventos/expediente',
         'roles' => ['admin', 'titular', 'operativo', 'coordinador', 'profesor', 'alumno'], 'orden' => 5],
        ['seccion' => 'RECURSOS', 'titulo' => 'Biblioteca de eventos', 'ruta' => 'eventos/biblioteca',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 38, 'dinamico' => true],
        ['seccion' => 'RECURSOS', 'titulo' => 'Evidencias por carrera (acreditación)', 'ruta' => 'eventos/acreditacion',
         'roles' => ['admin', 'titular', 'operativo', 'coordinador'], 'orden' => 39],
    ],
    'rutas' => [
        // Las rutas fijas van antes que /eventos/{id}.
        'GET /eventos'                              => [EventosController::class, 'index'],
        'GET /eventos/biblioteca'                   => [EventosController::class, 'biblioteca'],
        'GET /eventos/nuevo'                        => [AvisosController::class, 'nuevo'],
        'POST /eventos'                             => [AvisosController::class, 'guardar'],
        'POST /eventos/sugerir'                     => [AvisosController::class, 'sugerir'],
        'POST /eventos/preferencias'                => [AvisosController::class, 'preferencias'],
        'GET /eventos/expediente'                   => [AsistenciaController::class, 'expediente'],
        'GET /eventos/acreditacion'                 => [AsistenciaController::class, 'acreditacion'],
        'GET /eventos/acreditacion/excel'           => [AsistenciaController::class, 'acreditacionExcel', 'token'],
        'GET /eventos/evidencia/{eid}'              => [AsistenciaController::class, 'evidencia', 'token'],
        'GET /eventos/asistencia/{aid}/archivo/{n}' => [AsistenciaController::class, 'archivoAsistencia', 'token'],
        'GET /eventos/{id}'                         => [EventosController::class, 'detalle'],
        'POST /eventos/{id}'                        => [AvisosController::class, 'guardar'],
        'GET /eventos/{id}/editar'                  => [AvisosController::class, 'editar'],
        'GET /eventos/{id}/imagen'                  => [AvisosController::class, 'imagen', 'token'],
        'POST /eventos/{id}/suscribir'              => [EventosController::class, 'suscribir'],
        'GET /eventos/{id}/exportar-ics'            => [EventosController::class, 'exportarIcs', 'token'],
        'GET /eventos/{id}/qr'                      => [AsistenciaController::class, 'qr'],
        // Al escanear el QR sin sesión se pide iniciarla y se vuelve aquí.
        'GET /eventos/{id}/asistir/{token}'         => [AsistenciaController::class, 'asistir', 'publico'],
        'POST /eventos/{id}/asistir/{token}'        => [AsistenciaController::class, 'confirmarQr'],
        'POST /eventos/{id}/asistencia'             => [AsistenciaController::class, 'registrar'],
        'POST /eventos/{id}/asistencias/{aid}'      => [AsistenciaController::class, 'revisar'],
        'POST /eventos/{id}/evidencias'             => [AsistenciaController::class, 'agregarEvidencia'],
        'POST /eventos/{id}/evidencias/{eid}/eliminar' => [AsistenciaController::class, 'eliminarEvidencia'],
        // Verificación pública de una asistencia por folio.
        'GET /verificar/asistencia/{folio}'         => [AsistenciaController::class, 'verificar', 'publico'],
    ],
];
