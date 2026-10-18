<?php
declare(strict_types=1);

if (!hash_equals('__TOKEN_HASH__', hash('sha256', $_SERVER['HTTP_X_ASUNTOS_INSTALL'] ?? ''))) {
    http_response_code(404);
    exit;
}
ini_set('display_errors', '0');
require dirname(__DIR__) . '/app/bootstrap.php';
(new ReflectionProperty(\App\Core\App::class, 'config'))->setValue(null, require APP_RAIZ . '/config.php');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$etapa = 'inicio';
try {
    \App\Core\AccessControl::internal(function () use (&$etapa): void {
        $pdo = \App\Core\Database::pdo();
        // Primero adapta una instalación anterior; después crea lo que falte.
        foreach (['008_generalizar_asuntos.sql', '007_asuntos.sql'] as $archivo) {
            $sql = file_get_contents(dirname(__DIR__) . '/database/migrations/' . $archivo);
            foreach (explode(';', $sql) as $indice => $sentencia) {
                $sentencia = trim($sentencia);
                if ($sentencia !== '') {
                    $etapa = $archivo . ':' . ($indice + 1);
                    if (preg_match('/^EXECUTE\b/i', $sentencia)) {
                        $resultado = $pdo->query($sentencia);
                        if ($resultado) {
                            $resultado->fetchAll();
                            $resultado->closeCursor();
                        }
                    } else {
                        $pdo->exec($sentencia);
                    }
                }
            }
        }
    });
    $tablas = (int) \App\Core\Database::valor(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('asunto_expedientes','asunto_expediente_accesos','asunto_expediente_archivos','asunto_expediente_eventos')"
    );
    echo json_encode(['ok' => $tablas === 4, 'tablas' => $tablas]);
} catch (Throwable $error) {
    error_log('Instalación de asuntos fallida: ' . $error->getMessage());
    echo json_encode(['ok' => false, 'etapa' => $etapa, 'error' => mb_substr($error->getMessage(), 0, 300)]);
}
