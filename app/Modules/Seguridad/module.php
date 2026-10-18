<?php
declare(strict_types=1);

use App\Modules\Seguridad\SeguridadController;

return [
    'clave' => 'seguridad',
    'menu'  => [
        ['seccion' => 'MI ESPACIO', 'titulo' => 'Mi Seguridad (MFA)', 'ruta' => 'seguridad', 'orden' => 90, 'roles' => ['admin', 'titular', 'operativo', 'coordinador', 'profesor', 'alumno']],
    ],
    'rutas' => [
        'GET /seguridad' => [SeguridadController::class, 'index'],
        'GET /seguridad/biometria/registro-challenge' => [SeguridadController::class, 'biometriaRegistroChallenge'],
        'POST /seguridad/biometria/registrar' => [SeguridadController::class, 'registrarBiometria'],
        'POST /seguridad/biometria/eliminar' => [SeguridadController::class, 'eliminarBiometria'],
        'GET /seguridad/totp/generar' => [SeguridadController::class, 'totpGenerar'],
        'POST /seguridad/totp/vincular' => [SeguridadController::class, 'totpVincular'],
        'POST /seguridad/totp/desvincular' => [SeguridadController::class, 'totpDesvincular'],
    ],
];
