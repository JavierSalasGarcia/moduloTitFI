<?php

use App\Modules\Eventos\AsistenciaController;
use App\Modules\Eventos\AvisoController;
use App\Modules\Eventos\AvisosController;
use App\Modules\Eventos\EventosController;
use App\Modules\Eventos\EstadisticasController;

return [
    'clave' => 'eventos',
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'eventos', 'descripcion' => 'Avisos, convocatorias, calendarios de exámenes y eventos de la Facultad, con registro de asistencia.', 'palabras' => 'aviso avisos evento eventos convocatoria conferencia taller curso asistencia examen examenes exámenes calendario definitivos ordinarios extraordinarios inauguración comunicado'],
        ['ruta' => 'eventos/expediente', 'descripcion' => 'Tu expediente de formación integral: eventos a los que asististe y sus evidencias.', 'palabras' => 'formación integral expediente créditos evidencia'],
        ['ruta' => 'eventos/oficios', 'descripcion' => 'Oficios firmados de la Subdirección Académica dirigidos a ti o a un grupo al que perteneces (todos los profesores, tiempo completo…): fecha, número de oficio y asunto, con el PDF firmado.', 'palabras' => 'oficio oficios circular subdirección académica número de oficio firmado profesores'],
        ['ruta' => 'eventos/biblioteca', 'descripcion' => 'Biblioteca de eventos pasados y sus materiales.', 'palabras' => 'evento pasado grabación material'],
    ],
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Avisos y eventos', 'ruta' => 'eventos',
         'roles' => ['admin', 'titular', 'operativo', 'coordinador', 'profesor', 'alumno'], 'orden' => 4],
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Mi expediente de formación integral', 'ruta' => 'eventos/expediente',
         'roles' => ['admin', 'titular', 'operativo', 'coordinador', 'profesor', 'alumno'], 'orden' => 5],
        ['seccion' => 'RECURSOS', 'titulo' => 'Biblioteca de eventos', 'ruta' => 'eventos/biblioteca',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 38, 'dinamico' => true],
        ['seccion' => 'RECURSOS', 'titulo' => 'Evidencias por carrera (acreditación)', 'ruta' => 'eventos/acreditacion',
         'roles' => ['admin', 'titular', 'operativo', 'coordinador'], 'orden' => 39],
    ],
    'rutas' => [
        // Las rutas fijas van antes que /eventos/{id}.
        // Consulta sin sesión: solo los avisos públicos (con sesión, todo lo que le corresponde a la persona).
        'GET /eventos'                              => [EventosController::class, 'index', 'publico'],
        'GET /eventos/estadisticas'                 => [EstadisticasController::class, 'index'],
        'GET /eventos/estadisticas/excel'           => [EstadisticasController::class, 'excel'],
        'GET /eventos/estadisticas/buscar'          => [EstadisticasController::class, 'buscar'],
        'POST /eventos/estadisticas/personas'       => [EstadisticasController::class, 'agregar'],
        'POST /eventos/estadisticas/personas/{usuario}/quitar' => [EstadisticasController::class, 'quitar'],
        'POST /eventos/leidos'                      => [AvisoController::class, 'marcarLeidos'],
        'POST /eventos/no-leido'                    => [AvisoController::class, 'noLeido'],
        'GET /aviso/{id}'                           => [AvisoController::class, 'ver', 'publico'],
        'GET /aviso/{id}/archivo/{aid}'             => [AvisoController::class, 'archivo', 'publico'],
        'POST /aviso/{id}/adjuntos'                 => [AvisoController::class, 'adjuntar'],
        'POST /aviso/{id}/adjuntos/{aid}/quitar' => [AvisoController::class, 'quitarAdjunto'],
        'POST /aviso/{id}/estado'                   => [AvisoController::class, 'estado'],
        // Oficios firmados de la Subdirección Académica dirigidos a la persona (módulo Oficios).
        'GET /eventos/oficios'                      => [\App\Modules\Oficios\RecibidosController::class, 'index'],
        'GET /eventos/oficios/{id}/pdf'             => [\App\Modules\Oficios\RecibidosController::class, 'pdf'],
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
