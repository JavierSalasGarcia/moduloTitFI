<?php
declare(strict_types=1);

/**
 * Prueba de las concesiones de acceso (App\Core\Acceso) contra la base local. Todo ocurre en una transacción que se
 * revierte: no deja datos. Uso: php tools/prueba_acceso.php   (migración 043 aplicada)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('APP_RAIZ', dirname(__DIR__) . '/app');
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'App\\')) { $r = APP_RAIZ . '/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($r)) require $r; }
});

use App\Core\Acceso;
use App\Core\App;
use App\Core\Database;

App::cargarConfiguracion();
$pdo = Database::pdo();
$pdo->beginTransaction();
$fallos = 0;
$ok = function (string $m, bool $c) use (&$fallos): void { echo ($c ? 'ok   ' : 'FALLA') . ' ' . $m . "\n"; $fallos += $c ? 0 : 1; };
$lanza = function (callable $f): bool { try { $f(); return false; } catch (\Throwable) { return true; } };

try {
    $admin = (int) Database::valor("SELECT id FROM usuarios WHERE rol='admin' AND activo=1 ORDER BY id LIMIT 1");
    $titular = Database::una("SELECT id, departamento_id FROM usuarios WHERE rol='titular' AND activo=1 AND departamento_id > 0 ORDER BY id LIMIT 1");
    $otroDepto = (int) Database::valor('SELECT id FROM departamentos WHERE id <> ? ORDER BY id LIMIT 1', [$titular['departamento_id']]);
    $persona = (int) Database::valor("SELECT id FROM usuarios WHERE rol IN ('profesor','alumno') AND activo=1 ORDER BY id LIMIT 1");
    $depto = (int) $titular['departamento_id'];
    $manana = date('Y-m-d', strtotime('+1 day'));

    $ok('sin concesión no hay acceso', !Acceso::tiene($persona, 'documentos', 5));
    $id = Acceso::conceder((int) $titular['id'], $persona, 'documentos', $manana, 5, $depto, 'leer', 'Prueba');
    $ok('con concesión vigente sí hay acceso al recurso', Acceso::tiene($persona, 'documentos', 5, 'leer'));
    $ok('no se extiende a otro recurso', !Acceso::tiene($persona, 'documentos', 6, 'leer'));
    $ok('no se extiende a otro permiso', !Acceso::tiene($persona, 'documentos', 5, 'responder'));
    $ok('no se extiende a otra función', !Acceso::tiene($persona, 'titulacion.panel', 5, 'leer'));
    $ok('aparece en las vigentes', count(Acceso::vigentes($persona)) === 1);

    $pdo->exec("UPDATE acceso_concesiones SET vigente_hasta = CURDATE() - INTERVAL 1 DAY WHERE id = $id");
    $ok('vencida: caduca sola', !Acceso::tiene($persona, 'documentos', 5, 'leer'));
    $pdo->exec("UPDATE acceso_concesiones SET vigente_hasta = CURDATE() WHERE id = $id");
    $ok('el último día aún vale', Acceso::tiene($persona, 'documentos', 5, 'leer'));
    $ok('revocar la corta de inmediato', Acceso::revocar($id, (int) $titular['id']) && !Acceso::tiene($persona, 'documentos', 5, 'leer'));

    $ok('vigencia pasada se rechaza', $lanza(fn() => Acceso::conceder($admin, $persona, 'documentos', '2020-01-01', 5, $depto)));
    $ok('vigencia mal formada se rechaza', $lanza(fn() => Acceso::conceder($admin, $persona, 'documentos', 'mañana', 5, $depto)));
    $ok('autoconcesión se rechaza', $lanza(fn() => Acceso::conceder($admin, $admin, 'documentos', $manana, 5, $depto)));
    $ok('un titular no concede en otro departamento', $lanza(fn() => Acceso::conceder((int) $titular['id'], $persona, 'documentos', $manana, 5, $otroDepto)));
    $ok('un titular no concede sobre toda la plataforma', $lanza(fn() => Acceso::conceder((int) $titular['id'], $persona, 'documentos', $manana, null, null)));
    $ok('quien no es titular ni admin no concede', $lanza(fn() => Acceso::conceder($persona, $admin, 'documentos', $manana, 5, $depto)));
    $ok('el admin sí concede en cualquier dependencia', Acceso::conceder($admin, $persona, 'documentos', $manana, 7, $otroDepto) > 0);
    $ok('concesión sin recurso cubre toda la función', Acceso::conceder($admin, $persona, 'titulacion.panel', $manana) > 0 && Acceso::tiene($persona, 'titulacion.panel', 99));

    // --- alcance, avisos y etiquetas ---
    $ok('el admin concede en todas las dependencias (null)', Acceso::dependenciasQueConcede($admin) === null);
    $ok('el titular concede solo en la suya', Acceso::dependenciasQueConcede((int) $titular['id']) === [$depto]);
    $ok('quien no es titular no tiene dependencias', Acceso::dependenciasQueConcede($persona) === []);
    $pdo->exec("DELETE FROM acceso_concesiones");
    Acceso::conceder((int) $titular['id'], $persona, 'documentos.subir', date('Y-m-d', strtotime('+3 days')), null, $depto);
    Acceso::conceder($admin, $persona, 'asuntos.gestor', date('Y-m-d', strtotime('+60 days')), null, $otroDepto);
    $ok('por vencer (15 días) solo trae la próxima', count(Acceso::porVencer(15)) === 1 && Acceso::porVencer(15)[0]['funcion'] === 'documentos.subir');
    $ok('por vencer filtra por dependencia', Acceso::porVencer(90, [$depto]) !== [] && count(Acceso::porVencer(90, [$depto])) === 1);
    $ok('listar filtra por dependencia', count(Acceso::listar([$otroDepto])) === 1 && count(Acceso::listar()) === 2);
    $ok('etiqueta de catálogo y de módulo', Acceso::etiqueta('documentos.subir') !== 'documentos.subir' && str_contains(Acceso::etiqueta('modulo.fila'), 'fila'));
    Acceso::conceder($admin, $persona, 'modulo.fila', $manana);
    $ok('funcionesVigentes incluye el módulo concedido', in_array('modulo.fila', Acceso::funcionesVigentes($persona), true));
} finally {
    $pdo->rollBack();
}
echo $fallos === 0 ? "\nTodo en orden.\n" : "\n$fallos prueba(s) fallaron.\n";
exit($fallos === 0 ? 0 : 1);
