<?php
declare(strict_types=1);

/**
 * Vinculación de cuentas con los datos previos de la persona (docs/VINCULACION_DE_CUENTAS.md).
 *
 *   php tools/vincular_cuentas.php              Informe: cuántos registros quedarían vinculados y cuántos siguen huérfanos. No escribe.
 *   php tools/vincular_cuentas.php --aplicar    Vincula (solo por correo verificado o ficha de profesor; nunca por nombre).
 *   php tools/vincular_cuentas.php --detalle    Además lista los nombres de constancias sin correo ni profesor (datos personales:
 *                                               úsalo solo en una terminal de confianza, no lo redirijas a archivos del sitio).
 *
 * Ejecutar en el servidor desde la raíz del sitio. Haz un respaldo de la base antes de --aplicar.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('APP_RAIZ', dirname(__DIR__) . '/app');
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'App\\')) { $r = APP_RAIZ . '/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($r)) require $r; }
});
require APP_RAIZ . '/Core/helpers.php';

use App\Core\AccessControl;
use App\Core\App;
use App\Core\Database;
use App\Core\Identidad;

App::cargarConfiguracion();
$_SESSION = [];
$aplicar = in_array('--aplicar', $argv, true);
$detalle = in_array('--detalle', $argv, true);
$pdo = Database::pdo();

// Solo cuentas activas con correo verificado (las anteriores al autorregistro no tienen fila y se consideran verificadas).
$cuentas = array_column(Database::todas(
    'SELECT u.id FROM usuarios u LEFT JOIN cuenta_verificacion v ON v.usuario_id = u.id
      WHERE u.activo = 1 AND (v.usuario_id IS NULL OR v.verificado_en IS NOT NULL) ORDER BY u.id'
), 'id');

$pdo->beginTransaction();
$totales = [];
$conVinculo = 0;
try {
    AccessControl::internal(function () use ($cuentas, &$totales, &$conVinculo, $aplicar): void {
        foreach ($cuentas as $id) {
            $r = Identidad::vincularDatos((int) $id, $aplicar);   // el informe no deja rastro en la bitácora
            $conVinculo += $r ? 1 : 0;
            foreach ($r as $modulo => $n) {
                $totales[$modulo] = ($totales[$modulo] ?? 0) + $n;
            }
        }
    });
    $aplicar ? $pdo->commit() : $pdo->rollBack();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}

echo $aplicar ? "VINCULADO\n" : "INFORME (no se escribió nada; usa --aplicar)\n";
echo 'Cuentas revisadas: ' . count($cuentas) . "; con registros por vincular: $conVinculo\n";
foreach ($totales as $modulo => $n) {
    echo "  $modulo: $n registro(s)\n";
}

// Lo que sigue huérfano, por causa (constancias de titulación).
try {
    $conCuenta = '(EXISTS (SELECT 1 FROM usuarios u WHERE u.email COLLATE utf8mb4_unicode_ci = t.email COLLATE utf8mb4_unicode_ci) OR EXISTS (SELECT 1 FROM usuario_correos uc WHERE uc.email COLLATE utf8mb4_unicode_ci = t.email COLLATE utf8mb4_unicode_ci))';
    $sinCuenta = (int) Database::valor("SELECT COUNT(DISTINCT t.email) FROM tit_constancias t WHERE t.usuario_id IS NULL AND t.email <> '' AND NOT $conCuenta");
    $soloProfesor = (int) Database::valor("SELECT COUNT(DISTINCT t.profesor_id) FROM tit_constancias t WHERE t.usuario_id IS NULL AND t.profesor_id IS NOT NULL AND (t.email = '' OR t.email IS NULL)");
    $soloNombre = Database::todas("SELECT t.nombre, COUNT(*) n FROM tit_constancias t WHERE t.usuario_id IS NULL AND t.profesor_id IS NULL AND (t.email = '' OR t.email IS NULL) GROUP BY t.nombre ORDER BY t.nombre");
    echo "\nConstancias de titulación sin cuenta:\n";
    echo "  Personas con correo que aún no se registran: $sinCuenta  → se vinculan solas cuando creen su cuenta y verifiquen ese correo.\n";
    echo "  Fichas de profesor sin correo ni cuenta: $soloProfesor  → RR. HH. agrega el correo a la ficha o enlaza la cuenta (Directorio de profesores).\n";
    echo '  Nombres sin correo ni ficha de profesor: ' . count($soloNombre) . "  → revisión humana: Titulación identifica a la persona y corrige la constancia.\n";
    if ($detalle) {
        foreach ($soloNombre as $f) {
            echo '    - ' . $f['nombre'] . ' (' . $f['n'] . ")\n";
        }
    }
} catch (Throwable $e) {
    echo "\n(No se pudo revisar tit_constancias: " . $e->getMessage() . ")\n";
}
