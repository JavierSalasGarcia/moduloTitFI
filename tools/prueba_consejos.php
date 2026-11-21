<?php
declare(strict_types=1);

/**
 * Prueba del módulo Consejos contra la base local (migración 061 aplicada): captura casos con tablas, genera actas y
 * oficios en Word, busca con comodines y limpia todo lo que creó. Uso: php tools/prueba_consejos.php [carpeta_de_salida]
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('APP_RAIZ', dirname(__DIR__) . '/app');
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'App\\')) { $r = APP_RAIZ . '/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($r)) require $r; }
});

use App\Core\App;
use App\Core\Database;
use App\Modules\Consejos\Services\Busqueda;
use App\Modules\Consejos\Services\Dictamenes;
use App\Modules\Consejos\Services\Html;
use App\Modules\Consejos\Services\Word;

App::cargarConfiguracion();
\App\Core\AccessControl::ready();   // sin sesión, la auditoría haría su primera consulta justo después del INSERT y lastInsertId() devolvería 0
$salida = $argv[1] ?? sys_get_temp_dir();
$fallos = 0;
$ok = function (string $m, bool $c) use (&$fallos): void { echo ($c ? 'ok   ' : 'FALLA') . ' ' . $m . "\n"; $fallos += $c ? 0 : 1; };

$ultimoAntes = (string) Database::valor("SELECT valor FROM ajustes WHERE clave = 'consejos_oficio_ultimo'");
$rid = 0;
try {
    // Lo que llega al pegar desde Word: estilos, o:p, celdas combinadas.
    $word = '<html><body><!--StartFragment--><p class="MsoNormal" style="margin:0"><b style="mso-bidi-font-weight:normal"><span style="font-size:9pt">Oficio de solicitud</span></b><o:p></o:p></p>'
        . '<p class=MsoNormal>Comit&eacute; de trabajo terminal:<o:p></o:p></p>'
        . '<table class=MsoTableGrid border=1><tr><td rowspan=2 style="width:100pt"><p>Proyecto</p></td><td colspan=2><p><b>Comit&eacute;</b></p></td></tr>'
        . '<tr><td><p>Dr. Uno</p></td><td><p>Dra. Dos</p></td></tr></table><script>alert(1)</script><!--EndFragment--></body></html>';
    $limpio = Html::limpiar($word);
    $ok('Html: conserva tabla con celdas combinadas', str_contains($limpio, 'rowspan="2"') && str_contains($limpio, 'colspan="2"'));
    $ok('Html: sin scripts ni estilos', !str_contains($limpio, 'script') && !str_contains($limpio, 'style') && !str_contains($limpio, 'class'));
    $ok('Html: texto plano para el buscador', str_contains(Html::texto($limpio), 'Comité de trabajo terminal'));

    $rid = Database::ejecutar(
        "INSERT INTO consejos_reuniones (etiqueta, tipo_sesion, presidente, secretario, consejeros_academico, consejeros_gobierno, actas_anteriores_academico, academico_fecha, academico_hora, academico_lugar, gobierno_fecha, gobierno_hora, gobierno_lugar)
         VALUES ('PRUEBA 2099-01', 'ordinaria', 'Dr. Presidente Prueba', 'Dr. Javier Secretario', 'Dra. Uno\nMtro. Dos', 'Dra. Uno\nC. Alumno Consejero', 'ordinaria del día 9 de octubre de 2099 a las 09:00 horas en segunda convocatoria', '2099-01-18', '09:30:00', 'la Sala de Usos Múltiples', '2099-01-19', '09:30:00', 'la Sala de Usos Múltiples')"
    );
    $nuevo = static fn(string $g, int $n, string $sol, string $ant, string $ac, string $go) => Database::ejecutar(
        'INSERT INTO consejos_asuntos (reunion_id, grupo, numero, solicitud_html, antecedentes_html, texto_busqueda, aval_academico, acuerdo_academico, aprob_gobierno, acuerdo_gobierno) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$rid, $g, $n, Html::limpiar($sol), Html::limpiar($ant), Html::texto($sol) . "\n" . Html::texto($ant), 'avala', $ac, 'aprueba', $go]
    );
    $p1 = $nuevo('profesor', 1, '<p>El Dr. José Antonio Pérez Gómez, profesor de esta institución, solicita a los H.H. Consejos Académico y de Gobierno la autorización para gozar la prestación de año sabático para los periodos escolares 2099-A y 2099-B.</p>', '<p>Oficio de solicitud/Constancia de Recursos Humanos</p>',
        'El H. Consejo Académico avala el año sabático del Dr. José Antonio Pérez Gómez para los periodos 2099-A y 2099-B.', 'Se aprueba el Acuerdo del H. Consejo Académico.');
    $nuevo('profesor', 2, '<p>La Dra. María Torres, profesora de tiempo completo, presenta el dictamen del comité curricular.</p>', $word,
        'El H. Consejo Académico avala la integración del Comité Curricular.', 'No se aprueba el aval del H. Consejo Académico en virtud de que no se apega a la estructura del Plan Curricular.');
    $nuevo('alumno', 1, '<p>La M. en C. Lorena Díaz, Jefa del Departamento de Control Escolar, solicita a los H.H. Consejos autorizar el examen especial de los siguientes alumnos.</p>',
        '<table><tr><th>Nombre</th><th>Número de cuenta</th><th>UA</th></tr><tr><td>Ana Pérez Luna</td><td>2012345</td><td>Cálculo Integral</td></tr><tr><td>Luis Mora Ríos</td><td>2054321</td><td>Álgebra Lineal</td></tr></table>',
        'El H. Consejo Académico avala los exámenes especiales. En el caso de Luis Mora Ríos, no se avala por no cumplir el requisito.', 'Se aprueba el Acuerdo del H. Consejo Académico.');
    $nuevo('general', 1, '<p>Consideración para la recepción de solicitudes a los H. H. Consejos Académico y de Gobierno</p>', '', '', 'El H. Consejo de Gobierno acuerda que todas las solicitudes deberán tener el Vo. Bo. del Coordinador.');
    Database::ejecutar("UPDATE consejos_reuniones SET academico_fin = '2099-01-18 12:10:00', gobierno_fin = '2099-01-19 13:46:00', estado = 'cerrada' WHERE id = ?", [$rid]);

    $r = Database::una('SELECT * FROM consejos_reuniones WHERE id = ?', [$rid]);
    $asuntos = Database::todas("SELECT * FROM consejos_asuntos WHERE reunion_id = ? ORDER BY FIELD(grupo, 'profesor', 'alumno', 'general'), numero", [$rid]);
    foreach (['academico', 'gobierno'] as $c) {
        $docx = Word::acta($r, $c, $asuntos);
        file_put_contents($salida . "/acta_{$c}.docx", $docx);
        $zip = new ZipArchive();
        $zip->open($salida . "/acta_{$c}.docx");
        $x = (string) $zip->getFromName('word/document.xml');
        $d = new DOMDocument();
        $ok("Acta {$c}: XML bien formado", @$d->loadXML($x));
        $ok("Acta {$c}: encabezado, clave y tabla con celdas combinadas", str_contains($x, 'ACTA DE LA SESIÓN ORDINARIA') && str_contains($x, '>P1<') && str_contains($x, 'w:vMerge') && str_contains($x, 'w:gridSpan'));
        $ok("Acta {$c}: hora de terminación", str_contains($x, $c === 'gobierno' ? '13:46' : '12:10'));
    }

    $res = Dictamenes::generar($rid, '2099-01-20', 9001);
    $ok('Oficios: P1, P2, dos alumnos y AG1 = 5', $res['creados'] === 5);
    $ok('Oficios: numeración desde el número indicado', $res['primero'] === 'ACAD/FI/9001/2099' && $res['ultimo'] === 'ACAD/FI/9005/2099');
    $of = Database::todas("SELECT o.*, a.grupo, a.numero AS n FROM consejos_oficios o JOIN consejos_asuntos a ON a.id = o.asunto_id WHERE a.reunion_id = ? ORDER BY o.id", [$rid]);
    $o1 = $of[0];
    $ok('Oficio P1: destinatario y cargo', $o1['destinatario'] === 'DR. JOSÉ ANTONIO PÉREZ GÓMEZ' && str_contains($o1['cargo'], 'PROFESOR DE LA FACULTAD'));
    $ok('Oficio P1: cuerpo con «En respuesta a su solicitud» y fechas', str_starts_with($o1['cuerpo'], 'En respuesta a su solicitud') && str_contains($o1['cuerpo'], 'sesiones ordinarias de los días 18 y 19 de enero'));
    $ok('Oficio P1: acuerdo en voz de ambos Consejos', str_starts_with($o1['acuerdo'], 'Se aprueba') || str_contains($o1['acuerdo'], 'Se avala') === false);
    $ok('Oficio P2: Gobierno no aprueba → su texto', str_starts_with($of[1]['acuerdo'], 'No se aprueba el aval'));
    $ok('Oficios de alumnos', str_contains($of[2]['destinatario'], 'C. ANA PÉREZ LUNA') && str_contains($of[3]['destinatario'], 'C. LUIS MORA RÍOS') && str_contains($of[2]['cuerpo'], 'examen especial'));
    $ok('Oficio de alumno con acuerdo propio', str_contains($of[3]['acuerdo'], 'Luis Mora Ríos') || str_contains($of[3]['acuerdo'], 'no se avala'));
    $ok('Oficio sin destinatario deducible: se marca revisar', (int) $of[4]['revisar'] === 1 && str_contains($of[4]['destinatario'], '['));
    [$conNota] = Dictamenes::acuerdoFinal(['acuerdo_academico' => 'El H. Consejo Académico avala el aval.', 'acuerdo_gobierno' => 'Se aprueba el Acuerdo del H. Consejo Académico.', 'nota_gobierno' => 'Se envía copia a la Coordinación.']);
    $ok('Acuerdo final con nota adicional', $conNota === 'Se aprueba el aval. Se envía copia a la Coordinación.');
    $again = Dictamenes::generar($rid, '2099-01-20', null);
    $ok('Generar de nuevo no duplica', $again['creados'] === 0);
    $bin = Dictamenes::docx($of);
    file_put_contents($salida . '/dictamenes.docx', $bin);
    $zip = new ZipArchive();
    $zip->open($salida . '/dictamenes.docx');
    $x = (string) $zip->getFromName('word/document.xml');
    $d = new DOMDocument();
    $ok('Dictámenes: XML bien formado', @$d->loadXML($x));
    $ok('Dictámenes: números de oficio y membrete conservado', str_contains($x, 'ACAD/FI/9001/2099') && str_contains($x, 'ACAD/FI/9005/2099') && $zip->locateName('word/header1.xml') !== false);
    $ok('Dictámenes: pendientes resaltados en amarillo', str_contains($x, 'w:highlight'));
    $ok('Dictámenes: sin marcadores sin reemplazar', !str_contains($x, '{{'));

    $b = Busqueda::buscar('sabátic*', 'frase', 'todo', null, '');
    $ok('Búsqueda con comodín * y sin acentos', in_array('P1', array_column($b['filas'], 'clave'), true));
    $b = Busqueda::buscar('Garc?a Pérez', 'frase', 'todo', $rid, '');
    $ok('Búsqueda sin coincidencias', $b['total'] === 0);
    $b = Busqueda::buscar('luis mora', 'palabras', 'acuerdos', $rid, 'alumno');
    $ok('Búsqueda por palabras en acuerdos', $b['total'] === 1 && $b['filas'][0]['clave'] === 'A1' && str_contains($b['filas'][0]['fragmentos'][0]['html'], '<mark>'));
    $b = Busqueda::buscar('100%_', 'frase', 'todo', $rid, '');
    $ok('Búsqueda escapa % y _', $b['total'] === 0);
} finally {
    if ($rid) {
        Database::ejecutar('DELETE FROM consejos_reuniones WHERE id = ?', [$rid]);
    }
    Database::ejecutar("UPDATE ajustes SET valor = ? WHERE clave = 'consejos_oficio_ultimo'", [$ultimoAntes]);
}
echo $fallos === 0 ? "\nTodo bien.\n" : "\n{$fallos} falla(s).\n";
exit($fallos === 0 ? 0 : 1);
