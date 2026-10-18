<?php
declare(strict_types=1);
// Router exclusivamente local: fixtures en memoria, sin acceso a bases reales.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if (str_starts_with($path,'/estatico/')) return false;
require dirname(__DIR__).'/fila/publico/lib/datos.php';
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$pdo->exec(file_get_contents(dirname(__DIR__).'/fila/publico/pruebas/esquema_sqlite.sql'));
db_usar($pdo);
date_default_timezone_set('America/Mexico_City');
config_usar(['base_url'=>'','sitio'=>'http://127.0.0.1:8097','zona'=>'America/Mexico_City',
    'correo'=>['modo'=>'bitacora']]);
consulta('INSERT INTO dependencias (clave,nombre,titular,disponible,activa,creado) VALUES (?,?,?,1,1,?)',
    ['sa','Subdirección Académica','Javier Salas García',ahora_texto()]);
consulta('INSERT INTO horarios (dependencia_id,dia,inicio,fin) VALUES (1,?,?,?)',[(int)date('N'),'00:00','23:59']);
$dep=dependencia('sa');
foreach (['Ana Ruiz López','Luis Mora Pérez','María García Soto','Carlos Díaz Vega','Sofía López Cruz','Pedro Sánchez Gil','Elena Torres Ruiz','Diego Ramírez León'] as $name) {
    crear_turno($dep,$name,'fixture'.u_token(2).'@example.com','Datos ficticios de prueba',5);
}
consulta('UPDATE turnos SET estado=?,llamado=? WHERE folio=1',['llamado',ahora_texto()]);
consulta('UPDATE dependencias SET atendiendo_turno=1 WHERE id=1');
abrir_jornada($dep);
$_GET['r']=trim($path,'/');
require dirname(__DIR__).'/fila/publico/index.php';
