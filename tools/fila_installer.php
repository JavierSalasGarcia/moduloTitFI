<?php
declare(strict_types=1);
// Plantilla de instalador temporal: el despliegue sustituye el hash y lo retira.
if (!hash_equals('__TOKEN_HASH__', hash('sha256', $_SERVER['HTTP_X_FILA_INSTALL'] ?? ''))) {
    http_response_code(404); exit;
}
ini_set('display_errors', '0');
require dirname(__DIR__).'/app/bootstrap.php';
try {
    \App\Modules\Fila\FilaGateway::load();
    $schema = file_get_contents(dirname(__DIR__).'/fila/publico/esquema.sql');
    db()->exec($schema);
    $columns = [
        'dependencias'=>[
            'token_reloj_hash'=>"VARCHAR(64) NOT NULL DEFAULT ''",
            'push_tema'=>"VARCHAR(80) NOT NULL DEFAULT ''",
            'push_detalle'=>'TINYINT(1) NOT NULL DEFAULT 1',
            'push_avisos'=>"VARCHAR(80) NOT NULL DEFAULT 'formado,excedido,cita,vacia'",
            'mostrar_nombres'=>'TINYINT(1) NOT NULL DEFAULT 1',
        ],
        'horarios'=>[
            'activo'=>'TINYINT(1) NOT NULL DEFAULT 1',
        ],
    ];
    foreach ($columns as $table=>$fields) {
        $existing = array_column(filas('SHOW COLUMNS FROM '.$table), 'Field');
        foreach ($fields as $name=>$definition) {
            if (!in_array($name, $existing, true)) db()->exec("ALTER TABLE $table ADD COLUMN $name $definition");
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok'=>true, 'oficinas'=>(int)fila_una('SELECT COUNT(*) AS n FROM dependencias')['n']]);
} catch (Throwable $error) {
    http_response_code(500); echo '{"ok":false,"error":"No se pudo inicializar Fila; revisar configuración privada y esquema."}';
}
