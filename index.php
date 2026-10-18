<?php
/**
 * Plataforma FI · Front Controller del dominio.
 *
 * Mantener esta entrada en la raiz permite usar https://fingenieria.mx/ sin
 * publicar app/, database/, storage/ ni las demas carpetas internas.
 */
require __DIR__ . '/app/bootstrap.php';

use App\Core\App;

App::run();
