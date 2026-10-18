<?php
declare(strict_types=1);
// Alta puntual e idempotente, ejecutada mediante un instalador temporal protegido.
if (!hash_equals('__TOKEN_HASH__', hash('sha256', $_SERVER['HTTP_X_FILA_INSTALL'] ?? ''))) {
    http_response_code(404); exit;
}
ini_set('display_errors', '0');
require dirname(__DIR__).'/app/bootstrap.php';
try {
    \App\Modules\Fila\FilaGateway::load();
    db()->beginTransaction();
    $dep = fila_una('SELECT id FROM dependencias WHERE clave = ?', ['sa']);
    if (!$dep) {
        consulta('INSERT INTO dependencias (clave, nombre, titular, disponible, activa, push_tema, creado) '
            .'VALUES (?, ?, ?, 0, 1, ?, ?)',
            ['sa', 'Subdirección Académica', '', 'sa-'.u_token(8), ahora_texto()]);
    } else {
        consulta('UPDATE dependencias SET nombre = ? WHERE id = ?', ['Subdirección Académica', $dep['id']]);
    }
    db()->commit();
    $dep = fila_una('SELECT id, clave, nombre, disponible, activa FROM dependencias WHERE clave = ?', ['sa']);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>true, 'oficina'=>$dep,
        'horarios'=>count(horarios((int)$dep['id']))], JSON_UNESCAPED_UNICODE);
} catch (Throwable $error) {
    if (isset($GLOBALS['_pdo']) && db()->inTransaction()) db()->rollBack();
    http_response_code(500); echo '{"ok":false,"error":"No se pudo dar de alta la oficina."}';
}
