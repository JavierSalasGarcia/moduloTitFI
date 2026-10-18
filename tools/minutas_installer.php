<?php
declare(strict_types=1);
if (!hash_equals('__TOKEN_HASH__',hash('sha256',$_SERVER['HTTP_X_MINUTAS_INSTALL']??''))) { http_response_code(404); exit; }
ini_set('display_errors','0');
require dirname(__DIR__).'/app/bootstrap.php';
(new ReflectionProperty(\App\Core\App::class,'config'))->setValue(null,require APP_RAIZ.'/config.php');
header('Content-Type: application/json'); header('Cache-Control: no-store');
try {
    $pdo=\App\Core\Database::pdo();
    $pdo->exec(file_get_contents(dirname(__DIR__).'/database/migrations/006_reuniones_minutas.sql'));
    \App\Modules\Fila\FilaGateway::load();
    db()->exec('CREATE TABLE IF NOT EXISTS fila_minuta_presentes (registro_id BIGINT UNSIGNED NOT NULL,email VARCHAR(160) NOT NULL,PRIMARY KEY (registro_id,email)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $cantidad=0;
    foreach (filas('SELECT id,dependencia_id,usuario_id FROM fila_expedientes') as $r) {
        if (!\App\Core\Database::una('SELECT id FROM usuarios WHERE id=?',[$r['usuario_id']])) continue;
        \App\Modules\Reuniones\Services\MinutasService::vincularFila(dependencia_id((int)$r['dependencia_id']),(int)$r['id'],[]);
        $cantidad++;
    }
    echo json_encode(['ok'=>true,'minutas_indexadas'=>$cantidad]);
} catch (Throwable $e) { http_response_code(500); echo '{"ok":false}'; }
