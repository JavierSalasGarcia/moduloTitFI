<?php
declare(strict_types=1);
if (!hash_equals('__TOKEN_HASH__', hash('sha256', $_SERVER['HTTP_X_FILA_INSTALL'] ?? ''))) { http_response_code(404); exit; }
ini_set('display_errors', '0');
require dirname(__DIR__).'/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
try {
    $cfg = require APP_RAIZ.'/config.php';
    $c = $cfg['db'];
    $main = new PDO("mysql:host={$c['host']};dbname={$c['nombre']};charset={$c['charset']}", $c['usuario'], $c['clave'],
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $emails = ['jsalasg@uaemex.mx','electrovigia.salas@gmail.com'];
    $stmt=$main->prepare('SELECT id,nombre,email,rol,departamento_id FROM usuarios WHERE email IN (?, ?)');
    $stmt->execute($emails);
    $users=$stmt->fetchAll();
    $departments=$main->query("SELECT id,clave,nombre FROM departamentos WHERE nombre LIKE '%Acad%' OR clave='SA'")->fetchAll();
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET')==='GET') {
        echo json_encode(['ok'=>true,'usuarios'=>$users,'departamentos'=>$departments], JSON_UNESCAPED_UNICODE); exit;
    }
    $input=json_decode(file_get_contents('php://input'),true);
    if (!is_array($input) || empty($input['password']) || empty($input['departamento_id'])) throw new RuntimeException('Datos incompletos');
    $dept=(int)$input['departamento_id'];
    if (!array_filter($departments, fn($d)=>(int)$d['id']===$dept && $d['nombre']==='Subdirección Académica')) throw new RuntimeException('Departamento no válido');
    \App\Modules\Fila\FilaGateway::load();
    $dep=dependencia('sa');
    if (!$dep) throw new RuntimeException('Falta sa');
    $mainStmt=$main->prepare('SELECT * FROM usuarios WHERE email IN (?, ?)'); $mainStmt->execute($emails);
    $backup=['usuarios'=>$mainStmt->fetchAll(),'sa'=>$dep];
    $backupPath=dirname(APP_RAIZ).'/storage/fila/accounts-before-'.date('Ymd-His').'-'.u_token(4).'.json';
    if (file_put_contents($backupPath,json_encode($backup,JSON_UNESCAPED_UNICODE))===false) throw new RuntimeException('No se pudo respaldar');
    chmod($backupPath,0600);
    db()->exec('CREATE TABLE IF NOT EXISTS fila_responsables (usuario_id INT UNSIGNED NOT NULL, dependencia_id INT NOT NULL, cargo VARCHAR(120) NOT NULL, PRIMARY KEY (usuario_id, dependencia_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $main->beginTransaction(); db()->beginTransaction();
    $result=[];
    foreach (array_combine($emails,['Javier Salas García','JavierPrueba SalasP GarciaP']) as $email=>$name) {
        $get=$main->prepare('SELECT id,rol FROM usuarios WHERE email=?'); $get->execute([$email]); $existing=$get->fetch();
        if ($existing && $existing['rol']==='admin') throw new RuntimeException('No se modifica una cuenta administrativa');
        $hash=password_hash($input['password'],PASSWORD_DEFAULT);
        if ($existing) {
            $update=$main->prepare('UPDATE usuarios SET nombre=?,password_hash=?,rol=?,departamento_id=?,activo=1,permite_contrasena=1,logins_contrasena_restantes=20 WHERE id=?');
            $update->execute([$name,$hash,'titular',$dept,$existing['id']]); $id=(int)$existing['id'];
        } else {
            $insert=$main->prepare('INSERT INTO usuarios (nombre,email,password_hash,rol,departamento_id,activo,permite_contrasena,logins_contrasena_restantes) VALUES (?,?,?,?,?,1,1,20)');
            $insert->execute([$name,$email,$hash,'titular',$dept]); $id=(int)$main->lastInsertId();
        }
        consulta('INSERT INTO fila_responsables (usuario_id,dependencia_id,cargo) VALUES (?,?,?) ON DUPLICATE KEY UPDATE cargo=VALUES(cargo)',[$id,$dep['id'],'Subdirector Académico']);
        $result[]=['id'=>$id,'email'=>$email,'nombre'=>$name,'rol'=>'titular','cargo'=>'Subdirector Académico'];
    }
    consulta('UPDATE dependencias SET titular=?,correo_titular=? WHERE id=?',['Javier Salas García',$emails[0],$dep['id']]);
    db()->commit(); $main->commit();
    echo json_encode(['ok'=>true,'usuarios'=>$result],JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    if (isset($main) && $main->inTransaction()) $main->rollBack();
    if (($GLOBALS['_pdo'] ?? null) instanceof PDO && db()->inTransaction()) db()->rollBack();
    http_response_code(500); echo '{"ok":false,"error":"No se pudo configurar el acceso."}';
}
