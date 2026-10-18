<?php

use App\Modules\Auth\AuthController;

return [
    'clave'   => 'auth',
    'rutas'   => [
        'POST /cuenta/volver' => [\App\Modules\Ajustes\AccesosController::class,'volver'],
        'GET /registro' => [\App\Modules\Auth\RegistroController::class, 'formulario', 'publico'],
        'POST /registro' => [\App\Modules\Auth\RegistroController::class, 'crear', 'publico'],
        'GET /cuenta/verificar' => [\App\Modules\Auth\RegistroController::class, 'verificar'],
        'POST /cuenta/verificar' => [\App\Modules\Auth\RegistroController::class, 'confirmar'],
        'POST /cuenta/reenviar' => [\App\Modules\Auth\RegistroController::class, 'reenviar'],
        'GET /login'  => [AuthController::class, 'formulario', 'publico'],
        'POST /login' => [AuthController::class, 'entrar', 'publico'],
        'POST /logout' => [AuthController::class, 'salir'],
        'GET /login/biometria-challenge' => [AuthController::class, 'biometriaChallenge', 'publico'],
        'POST /login/biometria' => [AuthController::class, 'loginBiometrico', 'publico'],
        'POST /login/solicitar-otp' => [AuthController::class, 'solicitarOtp', 'publico'],
        'POST /login/verificar-otp' => [AuthController::class, 'verificarOtp', 'publico'],
        'POST /login/solicitar-restablecimiento' => [AuthController::class, 'solicitarRestablecimiento', 'publico'],
        'POST /login/verificar-restablecimiento' => [AuthController::class, 'verificarRestablecimiento', 'publico'],
        'POST /login/restablecer-contrasena' => [AuthController::class, 'restablecerContrasena', 'publico'],
        'POST /login/totp' => [AuthController::class, 'loginTotp', 'publico'],
        'POST /login/consultar-permiso-contrasena' => [AuthController::class, 'consultarPermisoContrasena', 'publico'],
        'POST /login/verificar-primer-paso' => [AuthController::class, 'verificarPrimerPaso', 'publico'],
    ],
];
