<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$config=require APP_RAIZ.'/config.php';$config['db']=['host'=>'127.0.0.1','nombre'=>'fi_ux_test_20260909','usuario'=>'root','clave'=>'','charset'=>'utf8mb4'];
$p=new ReflectionProperty(App\Core\App::class,'config');$p->setAccessible(true);$p->setValue(null,$config);
$users=[];
foreach(['profesor','profesor2','alumno','alumno2','coordinador','operativo','titular','pending'] as $kind){$role=match($kind){'profesor2'=>'profesor','alumno2','pending'=>'alumno',default=>$kind};$email='grupos.'.$kind.'@example.com';
App\Core\Database::ejecutar('INSERT INTO usuarios (nombre,email,password_hash,rol,departamento_id,activo,permite_contrasena,logins_contrasena_restantes) VALUES (?,?,?,?,20,1,1,100) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),activo=1,permite_contrasena=1,logins_contrasena_restantes=100',['QA Grupos '.$kind,$email,password_hash('Groups-test-2026',PASSWORD_DEFAULT),$role]);
$users[$kind]=(int)App\Core\Database::valor('SELECT id FROM usuarios WHERE email=?',[$email]);
if($kind==='pending')App\Core\Database::ejecutar('INSERT INTO cuenta_verificacion (usuario_id,creado_en) VALUES (?,?) ON DUPLICATE KEY UPDATE verificado_en=NULL',[$users[$kind],date('Y-m-d H:i:s')]);
}
echo json_encode($users);
