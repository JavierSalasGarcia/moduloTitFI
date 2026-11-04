<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') exit;

$raiz = dirname(__DIR__);
$migracion = (string) file_get_contents($raiz . '/database/migrations/035_encuesta_privada_viajes_2027.sql');
$modelo = (string) file_get_contents($raiz . '/app/Modules/Formularios/Formularios.php');
$controller = (string) file_get_contents($raiz . '/app/Modules/Formularios/FormulariosController.php');
$auth = (string) file_get_contents($raiz . '/app/Core/Auth.php');
$guia = (string) file_get_contents($raiz . '/docs/ENCUESTAS_PRIVADAS.md');

function comprobar(bool $condicion, string $mensaje): void
{
    if (!$condicion) {
        fwrite(STDERR, "FALLÓ: {$mensaje}\n");
        exit(1);
    }
    echo "OK: {$mensaje}\n";
}

comprobar(str_contains($migracion, "'director,subdirector', NULL"), 'la encuesta limita la consulta a autoridades y permite responder sin cuenta');
comprobar(str_contains($migracion, "'pais', 1, 'abierto'"), 'cada profesor puede enviar varios viajes');
comprobar(str_contains($migracion, "'2026-11-06 13:00:00'"), 'el cierre corresponde al 6 de noviembre de 2026 a las 13:00');
comprobar(str_contains($migracion, '"k":"conf_profesor"'), 'se exige la declaración de pertenencia al profesorado');
foreach (['competencia', 'ciudad', 'pais', 'fecha_salida', 'fecha_regreso', 'duracion_dias', 'alumnos', 'profesores', 'edad_min', 'edad_max', 'vehiculos_cantidad', 'vehiculos_tipo'] as $campo) {
    comprobar(str_contains($migracion, '"k":"' . $campo . '"'), "se definió el campo {$campo}");
}
comprobar(str_contains($modelo, "'director' => 'Director(a)'"), 'Dirección es una autoridad configurable');
comprobar(str_contains($controller, 'Auth::tieneRol($rol)'), 'el servidor exige el rol de captura');
comprobar(str_contains($controller, "Auth::usuario()[\$c['de_cuenta']]"), 'la identidad se toma de la sesión');
comprobar(str_contains($auth, "#^f/[a-z0-9-]{3,80}\$#"), 'el formulario puede retomarse después del login');
comprobar(str_contains($guia, 'Modelo de acceso'), 'la guía reutilizable quedó documentada');
comprobar(str_contains((string) file_get_contents($raiz . '/app/Modules/Formularios/views/llenar.php'), 'te recomendamos'), 'se sugiere registro o inicio de sesión');

echo "Encuesta privada: comprobaciones completas.\n";
