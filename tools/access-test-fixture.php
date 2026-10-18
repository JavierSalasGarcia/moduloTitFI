<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
$config=require APP_RAIZ.'/config.php';
$config['db']=['host'=>'127.0.0.1','nombre'=>'fi_ux_test_20260909','usuario'=>'root','clave'=>'','charset'=>'utf8mb4'];
$property=new ReflectionProperty(App\Core\App::class,'config'); $property->setAccessible(true); $property->setValue(null,$config);
$email=$argv[2]??''; $id=(int)App\Core\Database::valor('SELECT id FROM usuarios WHERE email=?',[$email]);
if (!$id || !str_ends_with($email,'@example.com')) exit('Solo cuentas locales de prueba.');
if (($argv[1]??'')==='issue') {
    App\Core\AccessControl::internal(fn()=>App\Core\Database::ejecutar('UPDATE cuenta_verificacion SET codigo_hash=?,expira_en=?,intentos=0,enviado_en=? WHERE usuario_id=?',[password_hash('654321',PASSWORD_DEFAULT),date('Y-m-d H:i:s',time()+600),date('Y-m-d H:i:s',time()-61),$id])); echo 'Código simulado listo';
} elseif (($argv[1]??'')==='expire') {
    App\Core\AccessControl::internal(fn()=>App\Core\Database::ejecutar('UPDATE cuenta_verificacion SET expira_en=? WHERE usuario_id=?',[date('Y-m-d H:i:s',time()-60),$id])); echo 'Código vencido';
} elseif (($argv[1]??'')==='audit') {
    echo json_encode(App\Core\Database::todas('SELECT actor_email,usuario_email,accion,resultado,creado_en FROM auditoria_acciones WHERE usuario_id=? ORDER BY id DESC LIMIT 30',[$id]));
} elseif (($argv[1]??'')==='direct') {
    $_SESSION['usuario']=App\Core\AccessControl::user($id); $_SERVER['SCRIPT_NAME']='/public/index.php'; $_SERVER['REQUEST_URI']='/public/'; $_SERVER['REQUEST_METHOD']='GET';
    $_SERVER['HTTP_ACCEPT']='application/json';
    App\Core\Database::pdo()->prepare("INSERT INTO foro_publicaciones (autor_id,tipo,titulo,contenido) VALUES (?,'publicacion','BYPASS MUST NOT EXIST','test')")->execute([$id]); echo 'BYPASS';
}
