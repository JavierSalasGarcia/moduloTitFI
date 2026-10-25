<?php
/**
 * Firmas de documentos: envío de PDFs a firma con rutas secuenciales
 * (Bo.Vo. de intermediarios hasta un destinatario final) o paralelas
 * (un conjunto de firmantes), grupos personales e institucionales
 * (NAB, CAP, Comités Curriculares, Cuerpos Académicos) y firma
 * estampada sobre el PDF con hoja de firmas de respaldo.
 */

use App\Modules\Firmas\FirmasController;
use App\Modules\Firmas\GruposController;

return [
    'clave' => 'firmas',
    // Todos los roles: los alumnos también firman. La restricción de
    // CREAR solicitudes (no alumnos) se aplica en el controlador.
    'menu'  => [
        ['seccion'=>'MI ESPACIO','titulo'=>'Grupos de destinatarios','ruta'=>'grupos-destinatarios','roles'=>['admin','titular','operativo','coordinador','profesor','alumno'],'orden'=>31],
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Firmas de documentos', 'ruta' => 'firmas',
         'roles' => ['admin', 'titular', 'operativo', 'profesor', 'alumno'], 'orden' => 30],
    ],
    'rutas' => [
        'GET /grupos-destinatarios' => [\App\Modules\Firmas\DestinatariosController::class,'index'],
        'GET /grupos-destinatarios/nuevo' => [\App\Modules\Firmas\DestinatariosController::class,'nuevo'],
        'GET /grupos-destinatarios/personas' => [\App\Modules\Firmas\DestinatariosController::class,'personas'],
        'POST /grupos-destinatarios/activar' => [\App\Modules\Firmas\DestinatariosController::class,'activar'],
        'POST /grupos-destinatarios' => [\App\Modules\Firmas\DestinatariosController::class,'crear'],
        'GET /grupos-destinatarios/{id}' => [\App\Modules\Firmas\DestinatariosController::class,'detalle'],
        'POST /grupos-destinatarios/{id}' => [\App\Modules\Firmas\DestinatariosController::class,'actualizar'],
        'POST /grupos-destinatarios/{id}/eliminar' => [\App\Modules\Firmas\DestinatariosController::class,'eliminar'],
        // Rutas literales ANTES de las de patrón {id}
        'GET /firmas'                       => [FirmasController::class, 'index'],
        'GET /firmas/nuevo'                 => [FirmasController::class, 'nuevo'],
        'POST /firmas'                      => [FirmasController::class, 'crear'],
        'GET /firmas/mi-firma'              => [FirmasController::class, 'miFirma'],
        'POST /firmas/mi-firma'             => [FirmasController::class, 'guardarMiFirma'],
        'GET /firmas/mi-firma-imagen'       => [FirmasController::class, 'miFirmaImagen'],
        'GET /firmas/ayuda'                 => [FirmasController::class, 'ayuda'],
        'GET /firmas/certificado'           => [FirmasController::class, 'descargarCertificado'],
        'GET /firmas/documento/{id}'        => [FirmasController::class, 'descargarDocumento', 'token'],
        'GET /firmas/grupos'                => [GruposController::class, 'index'],
        'POST /firmas/grupos'               => [GruposController::class, 'crear'],
        'POST /firmas/grupos/{id}/miembros' => [GruposController::class, 'agregarMiembro'],
        'POST /firmas/grupos/{id}/quitar'   => [GruposController::class, 'quitarMiembro'],
        'POST /firmas/grupos/{id}/eliminar' => [GruposController::class, 'eliminar'],
        'GET /firmas/{id}'                  => [FirmasController::class, 'detalle'],
        'POST /firmas/{id}/firmar'          => [FirmasController::class, 'firmar'],
        'POST /firmas/{id}/rechazar'        => [FirmasController::class, 'rechazar'],
        'POST /firmas/{id}/anexar'          => [FirmasController::class, 'anexar'],
        'POST /firmas/{id}/cancelar'        => [FirmasController::class, 'cancelar'],
    ],
];
