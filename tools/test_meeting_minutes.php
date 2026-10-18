<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
use App\Modules\Reuniones\Services\MinutasService as M;
use App\Core\Database as D;
    $pdo=new class('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]) extends PDO {
        public function prepare(string $query,array $options=[]): PDOStatement|false {
            return parent::prepare(str_replace(['INSERT IGNORE','NOW()'],['INSERT OR IGNORE',"datetime('now')"],$query),$options);
        }
    };
    (new ReflectionProperty(D::class,'pdo'))->setValue(null,$pdo);
    $pdo->exec("CREATE TABLE reuniones_minutas (id INTEGER PRIMARY KEY AUTOINCREMENT,reunion_id INTEGER NULL,autor_id INTEGER,subido_por_email TEXT DEFAULT '',asunto TEXT,fecha TEXT,calidad TEXT DEFAULT 'profesor',convocante TEXT DEFAULT '',resumen TEXT,archivo TEXT DEFAULT '',nombre_archivo TEXT DEFAULT '',fila_registro_id INTEGER NULL,creado TEXT DEFAULT CURRENT_TIMESTAMP); CREATE TABLE reuniones_minuta_presentes (minuta_id INTEGER,email TEXT,enviado TEXT,PRIMARY KEY(minuta_id,email)); CREATE TABLE reuniones_historial_enlaces (autor_id INTEGER,email TEXT,token_hash TEXT PRIMARY KEY,creado TEXT DEFAULT CURRENT_TIMESTAMP)");
    $pdo->exec('CREATE TABLE usuarios (id INT PRIMARY KEY,nombre VARCHAR(120),email VARCHAR(160)); CREATE TABLE reuniones (id INT PRIMARY KEY,organizador_id INT); CREATE TABLE reuniones_participantes (reunion_id INT,usuario_id INT NULL,correo VARCHAR(160),estado_invitacion VARCHAR(30))');
    $pdo->exec("INSERT INTO usuarios VALUES (1,'Profesor Uno','uno@example.com'),(2,'Profesor Dos','dos@example.com'),(3,'Otro','otro@example.com'); INSERT INTO reuniones VALUES (10,1); INSERT INTO reuniones_participantes VALUES (10,1,'uno@example.com','pendiente'),(10,2,'dos@example.com','pendiente'),(10,NULL,'invitado@gmail.com','pendiente'),(10,3,'otro@example.com','revocada')");
    $check=fn($ok,$label)=>$ok?:throw new RuntimeException($label);
    $check(M::correos(" A@GMAIL.COM; a@gmail.com\nb@example.org")===['a@gmail.com','b@example.org'],'Normalización y duplicados');
    try { M::correos('correo-mal'); throw new RuntimeException('Aceptó correo inválido'); } catch (InvalidArgumentException $e) {}
    $check(M::reunion(10,2,'dos@example.com')!==null,'Asistente distinto del organizador');
    $check(M::reunion(10,4,'invitado@gmail.com')!==null,'Asistente externo luego registrado');
    $check(M::reunion(10,3,'otro@example.com')===null,'Revocado sin acceso');
    D::ejecutar('INSERT INTO reuniones_minutas (reunion_id,autor_id,asunto,fecha,calidad,resumen) VALUES (10,2,?,?,?,?)',['Minuta compartida','2026-09-17 10:00:00','profesor','Resumen']);
    $id=(int)$pdo->lastInsertId(); M::presentes($id,['uno@example.com','dos@example.com','invitado@gmail.com']);
    D::ejecutar('INSERT INTO reuniones_minutas (autor_id,asunto,fecha,resumen) VALUES (1,?,?,?)',['Privada','2026-09-17 11:00:00','No compartir']);
    $private=(int)$pdo->lastInsertId(); M::presentes($private,['uno@example.com']);
    $check(M::permitido($id,1,'uno@example.com')!==null,'Otro asistente lee');
    $check(M::permitido($private,2,'dos@example.com')===null,'No filtra otra minuta');
    $mailer=new class implements \App\Modules\Reuniones\Services\MailerInterface {
        public array $mails=[];
        public function enviar(string $destinatario,string $asunto,string $texto,string $html=''): bool { $this->mails[]=[$destinatario,$texto]; return true; }
    };
    $sent=M::notificar($id,$mailer); $check($sent===['enviados'=>3,'total'=>3],'Todos reciben correo');
    $invitado=array_values(array_filter($mailer->mails,fn($mail)=>$mail[0]==='invitado@gmail.com'))[0];
    preg_match('#https://fingenieria.mx/public/reuniones/historial/([a-f0-9]{64})#',$invitado[1],$matches);
    $check(isset($matches[1]),'URL absoluta privada');
    $enlace=M::enlace($matches[1]); $check($enlace['email']==='invitado@gmail.com','Enlace individual');
    $check(count(M::historial($enlace))===1,'Historial solo del invitado');
    M::notificar($id,$mailer); $check(M::enlace($matches[1])!==null,'Reenvío no invalida enlaces previos');
    $check(M::enlace(str_repeat('0',64))===null,'Token inválido');
    echo "Minutas: destinatarios, permisos, historial y envío privado verificados sin correos reales.\n";
