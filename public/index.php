<?php
/**
 * Plataforma FI · Front Controller
 * Toda petición HTTP entra por aquí (ver public/.htaccess para Apache).
 */
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\App;

App::run();
