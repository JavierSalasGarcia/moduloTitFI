<?php
declare(strict_types=1);
if (!hash_equals('__TOKEN_HASH__',hash('sha256',$_SERVER['HTTP_X_MINUTAS_INSTALL']??''))) { http_response_code(404); exit; }
ini_set('display_errors','0');
require dirname(__DIR__).'/app/bootstrap.php';
(new ReflectionProperty(\App\Core\App::class,'config'))->setValue(null,require APP_RAIZ.'/config.php');
header('Content-Type: application/json'); header('Cache-Control: no-store');
try {
    \App\Core\ModuleManager::descubrir();
    $u=\App\Core\Database::una('SELECT id,nombre,email,rol FROM usuarios WHERE id=4');
    $_SESSION['usuario']=$u;
    $rows=\App\Modules\Reuniones\Services\MinutasService::listar((int)$u['id'],$u['email']);
    $minutas=array_map([\App\Modules\Reuniones\Services\MinutasService::class,'contenido'],$rows);
    $html=\App\Core\View::capturar('reuniones:minutas',['minutas'=>$minutas,'token'=>'']);
    $form=\App\Core\View::capturar('reuniones:minuta_nueva',['reunion'=>null,'base'=>null,'captura'=>[],'error'=>'']);
    $guest=\App\Modules\Reuniones\Services\MinutasService::listar(99999999,'nadie-'.bin2hex(random_bytes(10)).'@example.invalid');
    $datos=['ok'=>str_contains($html,'Minutas de reuniones') && str_contains($form,'Correos de los presentes') && !$guest,'minutas_disponibles'=>count($rows),'contenido_fila_preservado'=>count(array_filter($minutas,fn($m)=>!empty($m['fila_registro_id']) && (trim((string)$m['resumen'])!=='' || $m['archivo']!=='')))];
    unset($_SESSION['usuario']);
    echo json_encode($datos);
} catch (Throwable $e) { http_response_code(500); echo '{"ok":false}'; }
