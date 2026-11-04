<?php
declare(strict_types=1);

if (!hash_equals('__TOKEN_HASH__', hash('sha256', (string) ($_SERVER['HTTP_X_ENCUESTA_INSTALL'] ?? '')))) {
    http_response_code(404);
    exit;
}

ini_set('display_errors', '0');
require dirname(__DIR__, 2) . '/app/bootstrap.php';
(new ReflectionProperty(\App\Core\App::class, 'config'))->setValue(null, require APP_RAIZ . '/config.php');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = \App\Core\Database::pdo();
    $sql = file_get_contents(dirname(__DIR__, 2) . '/database/migrations/035_encuesta_privada_viajes_2027.sql');
    if ($sql === false) {
        throw new RuntimeException('No se encontró la migración.');
    }
    $pdo->exec($sql);
    $f = \App\Core\Database::una(
        'SELECT id, slug, estado, cierra_en, acceso, requiere_rol, varios_por_correo, campos FROM frm_formularios WHERE slug = ?',
        ['programacion-viajes-internacionales-2027-sf1500-q7m4k9x2']
    );
    $campos = $f ? (json_decode((string) $f['campos'], true) ?: []) : [];
    $claves = array_column($campos, 'k');
    $ok = $f
        && $f['estado'] === 'abierto'
        && $f['cierra_en'] === '2026-11-06 13:00:00'
        && $f['requiere_rol'] === null
        && (int) $f['varios_por_correo'] === 1
        && in_array('conf_profesor', $claves, true)
        && in_array('vehiculos_tipo', $claves, true);
    echo json_encode([
        'ok' => (bool) $ok,
        'formulario_id' => $f ? (int) $f['id'] : null,
        'estado' => $f['estado'] ?? null,
        'cierra_en' => $f['cierra_en'] ?? null,
        'acceso' => $f['acceso'] ?? null,
        'sin_cuenta' => $f ? $f['requiere_rol'] === null : false,
        'varios_por_profesor' => $f ? (int) $f['varios_por_correo'] === 1 : false,
        'campos' => count($campos),
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Instalación encuesta viajes: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
