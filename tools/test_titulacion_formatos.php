<?php
declare(strict_types=1);

/** Genera todos los formatos del módulo Titulación con datos de ejemplo (sin base de datos). */
define('APP_RAIZ', dirname(__DIR__) . '/app');
foreach (['Catalogo', 'Flujo', 'Services/PdfFormato', 'Services/DocxEditor', 'Services/ProtocoloDocx', 'Services/Formatos'] as $f) {
    require APP_RAIZ . "/Modules/Titulacion/$f.php";
}

use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\PdfFormato;
use App\Modules\Titulacion\Services\ProtocoloDocx;

$salida = sys_get_temp_dir() . '/titulacion-prueba';
@mkdir($salida, 0775, true);
$valores = [
    'fecha_dia' => '18', 'fecha_mes' => 'octubre', 'fecha_anio' => '26', 'nombre' => 'María José Hernández Peña',
    'cuenta' => '1912345', 'carrera' => 'computacion', 'sistema' => 'fi', 'anio_ingreso' => '2019', 'anio_egreso' => '2024',
    'telefono' => '7221234567', 'correo_inst' => 'mjhp@uaemex.mx', 'correo_pers' => 'maria@example.com', 'vez' => 'primera',
    'modalidad' => 'tesis', 'titulo' => 'Título de prueba del trabajo escrito', 'acentos' => 'Sí',
    'licenciatura' => 'Ingeniería en Computación', 'ocupacion' => 'Desarrolladora', 'motivos' => 'Motivos de prueba.',
];
$fallos = 0;
foreach (array_keys(PdfFormato::specs()) as $clave) {
    $pdf = PdfFormato::generar($clave, $valores);
    $ok = str_starts_with($pdf, '%PDF');
    file_put_contents("$salida/$clave.pdf", $pdf);
    echo ($ok ? 'ok   ' : 'FALLA ') . "$clave.pdf\n";
    $fallos += $ok ? 0 : 1;
}
foreach (array_keys(array_filter(Catalogo::modalidades(), static fn(array $m): bool => $m['tipo'] === 'con_trabajo')) as $mod) {
    $secciones = [];
    foreach (Catalogo::protocolo($mod)['campos'] as $c) {
        $c['valor'] = $c['tipo'] === 'cronograma' ? [['a' => 'Actividad', 'b' => [1, 2]]] : 'Texto de prueba';
        $secciones[] = $c;
    }
    $docx = ProtocoloDocx::generar($mod, ['dia' => '18', 'mes' => 'octubre', 'anio' => '2026', 'nombre' => 'Prueba', 'cuenta' => '1', 'carrera' => 'civil', 'tema' => 'Tema', 'etiquetas' => [], 'secciones' => $secciones]);
    $ok = str_starts_with($docx, 'PK');
    file_put_contents("$salida/protocolo-$mod.docx", $docx);
    echo ($ok ? 'ok   ' : 'FALLA ') . "protocolo-$mod.docx\n";
    $fallos += $ok ? 0 : 1;
}
echo "Archivos en $salida\n";
exit($fallos > 0 ? 1 : 0);
