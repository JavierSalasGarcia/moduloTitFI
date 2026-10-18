<?php
declare(strict_types=1);
if (!hash_equals('__TOKEN_HASH__',hash('sha256',$_SERVER['HTTP_X_RESOURCE_UPDATE']??''))) { http_response_code(404); exit; }
ini_set('display_errors','0');
require dirname(__DIR__).'/app/bootstrap.php';
$configuration=new ReflectionProperty(\App\Core\App::class,'config');
$configuration->setValue(null,require APP_RAIZ.'/config.php');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
try {
    $pdo=\App\Core\Database::pdo();
    if ($_SERVER['REQUEST_METHOD']==='GET') {
        $row=\App\Core\Database::una('SELECT * FROM recursos WHERE id=1');
        echo json_encode(['ok'=>true,'recurso'=>$row,'hash'=>hash('sha256',json_encode($row))],JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit; }
    $input=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
    $pdo->beginTransaction();
    $row=\App\Core\Database::una('SELECT * FROM recursos WHERE id=1 FOR UPDATE');
    if (!$row || !hash_equals(hash('sha256',json_encode($row)),(string)($input['hash']??''))) throw new RuntimeException('El recurso cambió.');
    $stmt=$pdo->prepare('UPDATE recursos SET descripcion=?,notas_admin=? WHERE id=1');
    $stmt->execute([$input['descripcion'],$input['notas_admin']]);
    $pdo->commit();
    $updated=\App\Core\Database::una('SELECT * FROM recursos WHERE id=1');
    echo json_encode(['ok'=>true,'verificado'=>$updated['descripcion']===$input['descripcion'] && $updated['notas_admin']===$input['notas_admin']],JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    http_response_code(409); echo json_encode(['ok'=>false,'error'=>'No se modificó el recurso.']);
}
