<?php
declare(strict_types=1);

/**
 * Instalador temporal para despliegues de la base de datos.
 *
 * Este archivo se personaliza y sube mediante deploy_database.ps1. No debe
 * permanecer en el servidor después de terminar el despliegue.
 */

const DEPLOY_TOKEN = '__DEPLOY_TOKEN__';
const DUMP_FILE = '__DUMP_FILE__';
const BACKUP_FILE = '__BACKUP_FILE__';
const DUMP_SHA256 = '__DUMP_SHA256__';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function connectDatabase(
    ?string $nameOverride = null,
    ?string $userOverride = null,
    ?string $passwordOverride = null
): PDO
{
    $configPath = dirname(__DIR__) . '/app/config.php';
    if (!is_file($configPath)) {
        throw new RuntimeException('No se encontro la configuracion de la aplicacion.');
    }

    $config = require $configPath;
    $db = $config['db'] ?? null;
    if (!is_array($db)) {
        throw new RuntimeException('La configuracion de base de datos no es valida.');
    }

    $databaseName = $nameOverride !== null && $nameOverride !== '' ? $nameOverride : $db['nombre'];
    $dsn = sprintf(
        'mysql:host=%s;dbname=%s;charset=%s',
        $db['host'],
        $databaseName,
        $db['charset'] ?? 'utf8mb4'
    );

    $user = $userOverride !== null && $userOverride !== '' ? $userOverride : $db['usuario'];
    $password = $passwordOverride !== null && $passwordOverride !== '' ? $passwordOverride : $db['clave'];
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/** @return Generator<int, string> */
function splitSqlStatements(string $sql): Generator
{
    $length = strlen($sql);
    $buffer = '';
    $quote = null;
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            $buffer .= $char;
            if ($char === "\n") {
                $lineComment = false;
            }
            continue;
        }

        if ($blockComment) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= '/';
                $i++;
                $blockComment = false;
            }
            continue;
        }

        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === $quote) {
                if ($next === $quote) {
                    $buffer .= $next;
                    $i++;
                } else {
                    $quote = null;
                }
            }
            continue;
        }

        if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $char === '#') {
            $buffer .= $char;
            if ($char === '-') {
                $buffer .= $next;
                $i++;
            }
            $lineComment = true;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $buffer .= '/*';
            $i++;
            $blockComment = true;
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $quote = $char;
            $buffer .= $char;
            continue;
        }

        if ($char === ';') {
            $statement = trim($buffer);
            if ($statement !== '') {
                yield $statement;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $statement = trim($buffer);
    if ($statement !== '') {
        yield $statement;
    }
}

function createBackup(PDO $pdo, string $destination): array
{
    $handle = gzopen($destination, 'wb9');
    if ($handle === false) {
        throw new RuntimeException('No se pudo crear el respaldo previo.');
    }

    $tables = [];
    $views = [];

    try {
        gzwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\nSET NAMES utf8mb4;\n\n");
        $rows = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($rows as $row) {
            if (strcasecmp((string) ($row[1] ?? ''), 'VIEW') === 0) {
                $views[] = (string) $row[0];
            } else {
                $tables[] = (string) $row[0];
            }
        }

        foreach ($tables as $table) {
            $quoted = quoteIdentifier($table);
            $create = $pdo->query("SHOW CREATE TABLE {$quoted}")->fetch(PDO::FETCH_NUM);
            gzwrite($handle, "DROP TABLE IF EXISTS {$quoted};\n" . $create[1] . ";\n\n");

            $query = $pdo->query("SELECT * FROM {$quoted}");
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $columns = array_map('quoteIdentifier', array_keys($row));
                $values = array_map(
                    static fn(mixed $value): string => $value === null ? 'NULL' : $pdo->quote((string) $value),
                    array_values($row)
                );
                gzwrite(
                    $handle,
                    "INSERT INTO {$quoted} (" . implode(',', $columns) . ') VALUES (' . implode(',', $values) . ");\n"
                );
            }
            gzwrite($handle, "\n");
        }

        foreach ($views as $view) {
            $quoted = quoteIdentifier($view);
            $create = $pdo->query("SHOW CREATE VIEW {$quoted}")->fetch(PDO::FETCH_ASSOC);
            $definition = $create['Create View'] ?? null;
            if (is_string($definition)) {
                gzwrite($handle, "DROP VIEW IF EXISTS {$quoted};\n{$definition};\n\n");
            }
        }

        gzwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    } finally {
        gzclose($handle);
    }

    return ['tables' => count($tables), 'views' => count($views), 'bytes' => filesize($destination) ?: 0];
}

function dropExistingObjects(PDO $pdo): void
{
    $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    $rows = $pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);

    foreach ($rows as $row) {
        if (strcasecmp((string) ($row[1] ?? ''), 'VIEW') === 0) {
            $pdo->exec('DROP VIEW IF EXISTS ' . quoteIdentifier((string) $row[0]));
        }
    }
    foreach ($rows as $row) {
        if (strcasecmp((string) ($row[1] ?? ''), 'VIEW') !== 0) {
            $pdo->exec('DROP TABLE IF EXISTS ' . quoteIdentifier((string) $row[0]));
        }
    }
}

function installDump(PDO $pdo, string $dumpPath, string $adminEmail, string $adminPassword): array
{
    if (!is_file($dumpPath) || !hash_equals(DUMP_SHA256, hash_file('sha256', $dumpPath))) {
        throw new RuntimeException('El respaldo subido no coincide con su suma de verificacion.');
    }

    $sql = gzdecode((string) file_get_contents($dumpPath));
    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException('El respaldo comprimido no pudo leerse.');
    }

    dropExistingObjects($pdo);
    $count = 0;
    foreach (splitSqlStatements($sql) as $statement) {
        $pdo->exec($statement);
        $count++;
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

    $hash = password_hash($adminPassword, PASSWORD_DEFAULT);
    if ($hash === false) {
        throw new RuntimeException('No se pudo generar la clave del administrador.');
    }

    $stmt = $pdo->prepare(
        "INSERT INTO usuarios
            (nombre, email, password_hash, departamento_id, programa_id, rol, activo, tipo_contratacion, totp_secret, permite_contrasena, logins_contrasena_restantes, outlook_ics_url)
         VALUES
            ('Administrador', ?, ?, NULL, NULL, 'admin', 1, NULL, NULL, 1, 20, NULL)
         ON DUPLICATE KEY UPDATE
            nombre = VALUES(nombre), password_hash = VALUES(password_hash), departamento_id = NULL,
            programa_id = NULL, rol = 'admin', activo = 1, totp_secret = NULL,
            permite_contrasena = 1, logins_contrasena_restantes = 20"
    );
    $stmt->execute([$adminEmail, $hash]);

    $verify = $pdo->prepare("SELECT id, rol, activo FROM usuarios WHERE email = ? LIMIT 1");
    $verify->execute([$adminEmail]);
    $admin = $verify->fetch();
    if (!$admin || $admin['rol'] !== 'admin' || (int) $admin['activo'] !== 1) {
        throw new RuntimeException('No se pudo verificar el administrador.');
    }

    return [
        'statements' => $count,
        'tables' => (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'")->fetchColumn(),
        'admin_id' => (int) $admin['id'],
    ];
}

$providedToken = $_SERVER['HTTP_X_DEPLOY_TOKEN'] ?? '';
if (!is_string($providedToken) || !hash_equals(DEPLOY_TOKEN, $providedToken)) {
    respond(404, ['ok' => false]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false]);
}

set_time_limit(0);
ini_set('memory_limit', '512M');

try {
    $databaseName = $_SERVER['HTTP_X_DATABASE_NAME'] ?? null;
    $databaseUser = $_SERVER['HTTP_X_DATABASE_USER'] ?? null;
    $databasePassword = $_SERVER['HTTP_X_DATABASE_PASSWORD'] ?? null;
    $pdo = connectDatabase(
        is_string($databaseName) ? $databaseName : null,
        is_string($databaseUser) ? $databaseUser : null,
        is_string($databasePassword) ? $databasePassword : null
    );
    $action = $_GET['action'] ?? '';

    if ($action === 'backup') {
        $result = createBackup($pdo, __DIR__ . '/' . BACKUP_FILE);
        respond(200, ['ok' => true, 'backup' => $result]);
    }

    if ($action === 'install') {
        $input = json_decode((string) file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
        $email = trim((string) ($input['admin_email'] ?? ''));
        $password = (string) ($input['admin_password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
            throw new InvalidArgumentException('Los datos del administrador no son validos.');
        }
        $result = installDump($pdo, __DIR__ . '/' . DUMP_FILE, $email, $password);
        respond(200, ['ok' => true, 'install' => $result]);
    }

    respond(400, ['ok' => false, 'error' => 'Accion no valida.']);
} catch (Throwable $error) {
    error_log('Temporary database installer: ' . $error->getMessage());
    respond(500, [
        'ok' => false,
        'error' => 'El despliegue no pudo completarse.',
        'detail' => substr($error->getMessage(), 0, 300),
    ]);
}
