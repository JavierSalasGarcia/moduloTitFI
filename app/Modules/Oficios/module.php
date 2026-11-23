<?php

use App\Modules\Oficios\OficiosController;
use App\Modules\Oficios\SubdireccionController;

return [
    'clave' => 'oficios',
    'por_firmar' => 'App\\Modules\\Oficios\\SubdireccionController::porFirmar',
    // Catálogo de consulta del asistente (docs/ASISTENTE_JEV_GEMINI.md).
    'consulta' => [
        ['ruta' => 'oficios', 'descripcion' => 'Oficios enviados por tu área: consulta y seguimiento.', 'palabras' => 'oficio enviado folio'],
        ['ruta' => 'oficios/subdireccion', 'descripcion' => 'Oficios de la Subdirección Académica: redactar, enviar a firma y firmar con e.firma del SAT.', 'palabras' => 'oficio subdirección académica redactar firmar fiel e.firma número de oficio',
         'condicion' => 'App\\Modules\\Oficios\\OficiosAccess::puedeRedactar'],
    ],
    'roles' => ['admin', 'titular', 'coordinador', 'operativo'],
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Oficios enviados', 'ruta' => 'oficios',
         'roles' => ['admin', 'titular', 'coordinador', 'operativo'], 'orden' => 36],
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Oficios de la Subdirección', 'ruta' => 'oficios/subdireccion',
         'descripcion' => 'Redacta oficios de la Subdirección Académica con número consecutivo y fírmalos con la e.firma del SAT.',
         'roles' => ['admin', 'titular', 'operativo'], 'condicion' => 'App\\Modules\\Oficios\\OficiosAccess::puedeRedactar', 'orden' => 35],
    ],
    'rutas' => [
        // Las rutas literales van antes que /oficios/{id}.
        'GET /oficios/subdireccion'                          => [SubdireccionController::class, 'index'],
        'GET /oficios/subdireccion/nuevo'                    => [SubdireccionController::class, 'nuevo'],
        'POST /oficios/subdireccion'                         => [SubdireccionController::class, 'crear'],
        'GET /oficios/subdireccion/destinatarios'            => [SubdireccionController::class, 'buscarDestinatarios'],
        'POST /oficios/subdireccion/destinatarios'           => [SubdireccionController::class, 'crearDestinatario'],
        'GET /oficios/subdireccion/configuracion'            => [SubdireccionController::class, 'configuracion'],
        'POST /oficios/subdireccion/configuracion'           => [SubdireccionController::class, 'guardarConfiguracion'],
        'POST /oficios/subdireccion/configuracion/certificado/{id}/quitar' => [SubdireccionController::class, 'quitarCertificado'],
        'GET /oficios/subdireccion/firmar-lote'              => [SubdireccionController::class, 'firmarLote'],
        'POST /oficios/subdireccion/enviar-lote'             => [SubdireccionController::class, 'enviarLote'],
        'GET /oficios/subdireccion/{id}'                     => [SubdireccionController::class, 'ver'],
        'GET /oficios/subdireccion/{id}/editar'              => [SubdireccionController::class, 'editar'],
        'POST /oficios/subdireccion/{id}'                    => [SubdireccionController::class, 'actualizar'],
        'GET /oficios/subdireccion/{id}/pdf'                 => [SubdireccionController::class, 'pdf'],
        'POST /oficios/subdireccion/{id}/enviar'             => [SubdireccionController::class, 'enviar'],
        'POST /oficios/subdireccion/{id}/devolver'           => [SubdireccionController::class, 'devolver'],
        'POST /oficios/subdireccion/{id}/cancelar'           => [SubdireccionController::class, 'cancelar'],
        'GET /oficios/subdireccion/{id}/firmar'              => [SubdireccionController::class, 'firmar'],
        'POST /oficios/subdireccion/{id}/firma/preparar'     => [SubdireccionController::class, 'firmaPreparar'],
        'POST /oficios/subdireccion/{id}/firma/guardar'      => [SubdireccionController::class, 'firmaGuardar'],
        // Verificación del QR y kit de confianza: sin iniciar sesión.
        'GET /oficios/verificar/{codigo}'                    => [SubdireccionController::class, 'verificar', 'publico'],
        'GET /oficios/confianza'                             => [SubdireccionController::class, 'confianza', 'publico'],
        'GET /oficios/confianza/{id}'                        => [SubdireccionController::class, 'descargarCertificado', 'publico'],
        // Oficios enviados (módulo original)
        'GET /oficios'              => [OficiosController::class, 'index'],
        'GET /oficios/nuevo'        => [OficiosController::class, 'nuevo'],
        'POST /oficios'             => [OficiosController::class, 'crear'],
        'GET /oficios/{id}/archivo' => [OficiosController::class, 'archivo'],
        'GET /oficios/{id}'         => [OficiosController::class, 'detalle'],
        'POST /oficios/{id}'        => [OficiosController::class, 'actualizar'],
    ],
];
