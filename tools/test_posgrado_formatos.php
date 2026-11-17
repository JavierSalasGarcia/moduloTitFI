<?php
declare(strict_types=1);

/**
 * Genera TODOS los formatos y oficios del módulo Posgrado con datos ficticios (maestría y doctorado) en un directorio
 * temporal y falla si alguno no se produce. No escribe en la base de datos; si hay base configurada, lee de ella los
 * ajustes (quién firma) y la leyenda del año.
 *
 * Uso: php tools/test_posgrado_formatos.php [directorio_de_salida]
 */
define('APP_RAIZ', dirname(__DIR__) . '/app');
date_default_timezone_set('America/Mexico_City');
spl_autoload_register(static function (string $clase): void {
    if (str_starts_with($clase, 'App\\')) {
        $ruta = APP_RAIZ . '/' . str_replace('\\', '/', substr($clase, 4)) . '.php';
        if (is_file($ruta)) {
            require $ruta;
        }
    }
});
require APP_RAIZ . '/Core/helpers.php';
\App\Core\App::cargarConfiguracion();

use App\Modules\Posgrado\Services\Formatos;

$salida = $argv[1] ?? (sys_get_temp_dir() . '/posgrado-prueba');
@mkdir($salida, 0775, true);

$miembros = [
    ['nombre' => 'Dra. Ana Sofía Núñez Peña', 'tipo' => 'director', 'externo' => 0],
    ['nombre' => 'Dr. José Ángel Muñoz Ibáñez', 'tipo' => 'codirector', 'externo' => 0],
    ['nombre' => 'Mtro. Óscar Güemes Ruíz', 'tipo' => 'tutor', 'externo' => 0],
    ['nombre' => 'Dra. María Begoña Echeverría Solís', 'tipo' => 'nab', 'externo' => 0],
    ['nombre' => 'Dr. Raúl Íñiguez Cañedo de la Concepción y Santa María', 'tipo' => 'externo', 'externo' => 1],
    ['nombre' => 'Dra. Lucía Peñaloza Ávila', 'tipo' => 'externo', 'externo' => 1],
    ['nombre' => 'Dr. Ernesto Zúñiga Ortíz', 'tipo' => 'nab', 'externo' => 0],
];
$base = static fn(string $nivel, string $modalidad, string $genero, bool $art53): array => [
    'id' => 1, 'folio' => 'PG-2026-0001', 'alumno_id' => 1, 'nivel' => $nivel, 'estado' => 'abierto', 'alumno_genero' => $genero,
    'datos' => [
        'interesado' => [
            'nombre' => 'Begoña Ximena Muñoz Íñiguez', 'acentos' => 'si', 'cuenta' => '2120456', 'programa' => $nivel === 'doctorado' ? 'Doctorado en Ciencias de la Ingeniería' : 'Maestría en Movilidad y Transporte',
            'programa_siglas' => $nivel === 'doctorado' ? 'DCI' : 'MMT', 'nivel' => $nivel, 'plan' => $nivel === 'doctorado' ? 'DCI 2019' : 'MMT 2021',
            'telefono' => '722 123 4567', 'correo' => 'begona.ficticia@example.com', 'ocupacion' => 'Investigadora asociada en diseño de señalización',
            'periodo_inicio' => '2024B', 'titulo' => 'Simulación interactiva de escenarios urbanos para la enseñanza de educación vial en niñas y niños: diseño, validación y evaluación con métricas de aprendizaje',
            'modalidad' => $modalidad, 'fecha_previo' => '2026-09-14', 'director_grado' => 'Dra.', 'director_nombre' => 'Ana Sofía Núñez Peña', 'director_correo' => 'director.ficticio@example.com',
            'codirector_grado' => 'Dr.', 'codirector_nombre' => 'José Ángel Muñoz Ibáñez', 'codirector_correo' => '', 'tutor_grado' => 'Mtro.', 'tutor_nombre' => 'Óscar Güemes Ruíz', 'tutor_correo' => '',
            'art53' => $art53 ? 'si' : 'no',
        ],
        'sinodo' => ['miembros' => $miembros, 'fecha' => '2026-12-04', 'hora' => '12:00', 'modalidad' => 'presencial'],
    ],
];
$hitos = [
    'cotejo' => ['datos' => ['fecha' => '2026-11-17', 'hora' => '10:30']],
    'programacion' => ['datos' => ['fecha' => '2026-12-04', 'hora' => '12:00', 'lugar' => 'el Auditorio 120 de la Facultad de Ingeniería', 'modalidad' => 'presencial']],
    'resultado' => ['datos' => ['resultado' => 'unanimidad', 'fecha' => '2026-12-04', 'definitivo' => [0, 1, 2, 3, 5]]],
];

$casos = [
    'maestria' => $base('maestria', 'trabajo_terminal', 'femenino', false),
    'doctorado' => $base('doctorado', 'articulos', 'masculino', true),
];
$fallos = 0;
$n = 0;
foreach ($casos as $caso => $t) {
    foreach (array_keys(Formatos::NOMBRES) as $clave) {
        try {
            [$pdf] = Formatos::generar($clave, $t, $hitos);
            $ok = str_starts_with($pdf, '%PDF') && strlen($pdf) > 1000;
        } catch (Throwable $e) {
            $ok = false;
            $pdf = '';
            fwrite(STDERR, "  $caso/$clave: " . $e->getMessage() . "\n");
        }
        if ($ok) {
            file_put_contents("$salida/$caso-$clave.pdf", $pdf);
        }
        echo ($ok ? 'ok    ' : 'FALLA ') . "$caso-$clave.pdf\n";
        $fallos += $ok ? 0 : 1;
        $n++;
    }
}
// Impedimentos: sin datos no se generan.
$vacio = ['id' => 2, 'folio' => 'PG-2026-0002', 'nivel' => 'maestria', 'datos' => []];
foreach (['voto', 'solicitud', 'invitaciones'] as $clave) {
    $ok = Formatos::impedimento($clave, $vacio, []) !== null;
    echo ($ok ? 'ok    ' : 'FALLA ') . "impedimento sin datos: $clave\n";
    $fallos += $ok ? 0 : 1;
}
// Números en letras de las constancias.
foreach ([2 => 'dos', 27 => 'veintisiete', 2026 => 'dos mil veintiséis', 31 => 'treinta y uno'] as $num => $esperado) {
    $ok = Formatos::letras($num) === $esperado;
    echo ($ok ? 'ok    ' : 'FALLA ') . "letras($num) = " . Formatos::letras($num) . "\n";
    $fallos += $ok ? 0 : 1;
}
echo "$n PDF intentados, $fallos falla(s). Archivos en $salida\n";
exit($fallos > 0 ? 1 : 0);
