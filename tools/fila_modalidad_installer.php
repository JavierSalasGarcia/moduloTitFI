<?php
declare(strict_types=1);
if (!hash_equals('__TOKEN_HASH__',hash('sha256',$_SERVER['HTTP_X_FILA_INSTALL']??''))) { http_response_code(404); exit; }
ini_set('display_errors','0'); require dirname(__DIR__).'/app/bootstrap.php';
(new ReflectionProperty(\App\Core\App::class,'config'))->setValue(null,require APP_RAIZ.'/config.php');
header('Content-Type: application/json'); header('Cache-Control: no-store');
try {
    \App\Modules\Fila\FilaGateway::load();
    foreach (['turnos','citas'] as $tabla) {
        $columns=array_column(filas('SHOW COLUMNS FROM '.$tabla),'Field');
        foreach (['videoconferencia'=>'TINYINT NOT NULL DEFAULT 0','enlace_teams'=>"VARCHAR(2048) NOT NULL DEFAULT ''"] as $campo=>$tipo) {
            if (!in_array($campo,$columns,true)) db()->exec('ALTER TABLE '.$tabla.' ADD COLUMN '.$campo.' '.$tipo);
        }
    }
    echo '{"ok":true}';
} catch (Throwable $e) { http_response_code(500); echo '{"ok":false}'; }
