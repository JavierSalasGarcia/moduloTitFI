<?php
/** Local QA only. Never deploy this router. Start bound to 127.0.0.1. */
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(404); exit; }
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/public/assets/')) return false;
require dirname(__DIR__) . '/app/bootstrap.php';
$_SERVER['SCRIPT_NAME'] = '/public/index.php';
$config = require APP_RAIZ . '/config.php';
$config['db'] = ['host'=>'127.0.0.1','nombre'=>'fi_ux_test_20260909','usuario'=>'root','clave'=>'','charset'=>'utf8mb4'];
$config['correo'] = ['api_token'=>'','remitente'=>''];
$config['archivos']['storage'] = sys_get_temp_dir() . '/fi-ux-test-storage';
$config['documentos']['storage'] = sys_get_temp_dir() . '/fi-documentos-qa';
$config['app']['debug'] = true;
$property = new ReflectionProperty(App\Core\App::class, 'config'); $property->setAccessible(true); $property->setValue(null, $config);
error_reporting(E_ALL); ini_set('display_errors','1');
$router = new App\Core\Router();
foreach (App\Core\ModuleManager::descubrir() as $module) $router->registrarModulo($module);
$router->despachar();
