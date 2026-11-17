<?php
declare(strict_types=1);

/**
 * Genera TODOS los formatos y oficios del módulo Posgrado con datos ficticios (maestría y doctorado) en un directorio
 * temporal y falla si alguno no se produce. Comprueba además las cajas de firma en línea (cada firmante con su sello dentro
 * de la hoja y sin encimarse), que los textos ya no pidan firma autógrafa ni la hoja de prueba del SICDE, la regla de
 * vigencia de la firma electrónica UAEMéx (examen + 1 mes ≤ vigencia) y la huella de datos que decide si un documento
 * se regenera. No escribe en la base de datos; si hay base configurada, lee de ella los
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

use App\Modules\Posgrado\Services\Firmas;
use App\Modules\Posgrado\Services\Formatos;
use App\Modules\Perfil\FirmaUaemex;

$salida = $argv[1] ?? (sys_get_temp_dir() . '/posgrado-prueba');
@mkdir($salida, 0775, true);

$miembros = [
    ['nombre' => 'Dra. Ana Sofía Núñez Peña', 'tipo' => 'director', 'correo' => 's1@example.com', 'externo' => 0],
    ['nombre' => 'Dr. José Ángel Muñoz Ibáñez', 'tipo' => 'codirector', 'correo' => 's2@example.com', 'externo' => 0],
    ['nombre' => 'Mtro. Óscar Güemes Ruíz', 'tipo' => 'tutor', 'correo' => 's3@example.com', 'externo' => 0],
    ['nombre' => 'Dra. María Begoña Echeverría Solís', 'tipo' => 'nab', 'correo' => 's4@example.com', 'externo' => 0],
    ['nombre' => 'Dr. Raúl Íñiguez Cañedo de la Concepción y Santa María', 'tipo' => 'externo', 'correo' => 's5@example.com', 'externo' => 1],
    ['nombre' => 'Dra. Lucía Peñaloza Ávila', 'tipo' => 'externo', 'correo' => 's6@example.com', 'externo' => 1],
    ['nombre' => 'Dr. Ernesto Zúñiga Ortíz', 'tipo' => 'nab', 'correo' => 's7@example.com', 'externo' => 0],
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
// Cajas de firma en línea: cada firmante tiene dónde va su sello, dentro de la hoja y sin encimarse con otro.
$esperadas = [
    'voto' => ['director'], 'datos_interesado' => ['egresado'], 'solicitud' => ['director'],
    'visto_bueno' => ['sinodal_0', 'sinodal_1', 'sinodal_2', 'sinodal_3', 'sinodal_4', 'sinodal_5', 'sinodal_6'],
    'acuse' => ['coordinacion', 'sinodal_0', 'sinodal_1', 'sinodal_2', 'sinodal_3', 'sinodal_4', 'sinodal_5', 'sinodal_6'],
    'cotejo' => ['coordinacion'], 'invitaciones' => ['coordinacion'], 'notificacion' => ['coordinacion'], 'no_adeudo' => ['coordinacion'],
    'constancias_revisores' => ['coordinacion'], 'constancias_direccion' => ['coordinacion'], 'integracion' => ['coordinacion'],
];
$ALTO_SELLO = 20.0; // SelloPdf con tres renglones y la leyenda «registro interno»
foreach ($casos as $caso => $t) {
    foreach ($esperadas as $clave => $roles) {
        [, , $cajas] = Formatos::generarConCajas($clave, $t, $hitos);
        $ok = true;
        $puntos = [];
        foreach ($roles as $rol) {
            foreach ($cajas[$rol]['sellos'] ?? [] as $c) {
                $ok = $ok && $c['y'] > 40 && $c['y'] < 262 && $c['x'] > 40 && $c['x'] < 180;
                $puntos[] = $c + ['rol' => $rol];
            }
            $ok = $ok && !empty($cajas[$rol]['sellos']);
        }
        // Dos sellos en la misma página no pueden quedar a menos de un alto de sello en vertical si comparten columna.
        foreach ($puntos as $a => $p1) {
            foreach ($puntos as $b => $p2) {
                if ($a < $b && $p1['p'] === $p2['p'] && abs($p1['x'] - $p2['x']) < 62 && abs($p1['y'] - $p2['y']) < ($clave === 'visto_bueno' || $clave === 'acuse' ? 17.0 : $ALTO_SELLO)) {
                    $ok = false;
                }
            }
        }
        if ($clave === 'visto_bueno') {
            foreach (range(0, 6) as $k) {
                $ok = $ok && count($cajas['sinodal_' . $k]['marcas'][0]['si'] ?? []) === 3 && count($cajas['sinodal_' . $k]['marcas'][0]['no'] ?? []) === 3;
            }
        }
        if ($clave === 'invitaciones') {
            $ok = $ok && count($cajas['paginas'] ?? []) === 7 && count($cajas['coordinacion']['sellos']) === 7;
        }
        echo ($ok ? 'ok    ' : 'FALLA ') . "cajas $caso-$clave (" . count($puntos) . " sellos)\n";
        $fallos += $ok ? 0 : 1;
    }
}
// Textos: ya no se pide firma autógrafa ni la hoja de prueba del SICDE.
if (trim((string) shell_exec('command -v pdftotext')) !== '') {
    foreach (glob($salida . '/*.pdf') as $archivo) {
        $texto = (string) shell_exec('pdftotext ' . escapeshellarg($archivo) . ' - 2>/dev/null');
        $ok = !preg_match('/aut[oó]graf|hoja de prueba|comprobante de que la firma|de manera presencial/iu', $texto);
        if (!$ok) {
            echo 'FALLA texto prohibido en ' . basename($archivo) . "\n";
            $fallos++;
        }
    }
    echo "ok    sin «firma autógrafa» ni «hoja de prueba» en los PDF\n";
}
// Regla de vigencia de la firma electrónica UAEMéx: fecha de examen + 1 mes ≤ vigencia.
foreach ([['2026-12-04', '2027-01-04', true], ['2026-12-04', '2027-01-03', false], ['2027-01-31', '2027-02-28', true], ['2027-01-31', '2027-02-27', false], ['2026-12-04', null, false]] as [$examen, $vig, $esperado]) {
    $ok = Firmas::vigenciaAlcanza($vig, $examen) === $esperado;
    echo ($ok ? 'ok    ' : 'FALLA ') . "vigencia $vig para examen $examen: " . ($esperado ? 'alcanza' : 'no alcanza') . "\n";
    $fallos += $ok ? 0 : 1;
}
$ok = FirmaUaemex::porVencer(date('Y-m-d', strtotime('+20 days'))) && !FirmaUaemex::porVencer(date('Y-m-d', strtotime('+40 days'))) && FirmaUaemex::porVencer(date('Y-m-d', strtotime('-1 day')));
echo ($ok ? 'ok    ' : 'FALLA ') . "aviso de vigencia: 20 días sí, 40 días no, vencida sí\n";
$fallos += $ok ? 0 : 1;
// La huella de datos no cambia con el correo de un firmante (cambiarlo no regenera el documento) y sí con el título.
$t1 = $casos['maestria'];
$t2 = $t1;
$t2['datos']['interesado']['director_correo'] = 'otro@example.com';
$t2['datos']['sinodo']['miembros'][3]['correo'] = 'otra@example.com';
$t3 = $t1;
$t3['datos']['interesado']['titulo'] .= ' (versión 2)';
$ok = Firmas::huellaDatos('voto', $t1, []) === Firmas::huellaDatos('voto', $t2, []) && Firmas::huellaDatos('visto_bueno', $t1, []) === Firmas::huellaDatos('visto_bueno', $t2, [])
    && Firmas::huellaDatos('voto', $t1, []) !== Firmas::huellaDatos('voto', $t3, []);
echo ($ok ? 'ok    ' : 'FALLA ') . "huella de datos: el correo no regenera, el título sí\n";
$fallos += $ok ? 0 : 1;

// Impedimentos: sin datos no se generan.
$vacio = ['id' => 2, 'folio' => 'PG-2026-0002', 'nivel' => 'maestria', 'datos' => []];
$sinCorreos = $casos['maestria'];
$sinCorreos['datos']['sinodo']['miembros'][2]['correo'] = '';
$ok = Formatos::impedimento('visto_bueno', $sinCorreos, []) !== null;
echo ($ok ? 'ok    ' : 'FALLA ') . "impedimento: visto bueno sin el correo de un integrante\n";
$fallos += $ok ? 0 : 1;
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
echo "$n PDF intentados; $fallos falla(s) en total. Archivos en $salida\n";
exit($fallos > 0 ? 1 : 0);
