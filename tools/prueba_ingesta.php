<?php
declare(strict_types=1);

/**
 * Prueba de la ingesta documental y del grafo de persona (docs/INGESTA_DOCUMENTAL.md, docs/GRAFO_DE_PERSONA.md):
 * enlaces admitidos, lector de PDF, alcance por nivel de acceso y visibilidad del grafo por pertenencia y concesión.
 * Todo ocurre en una transacción que se revierte. Uso: php tools/prueba_ingesta.php   (migraciones 043 y 044 aplicadas)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

define('APP_RAIZ', dirname(__DIR__) . '/app');
spl_autoload_register(function (string $c): void {
    if (str_starts_with($c, 'App\\')) { $r = APP_RAIZ . '/' . str_replace('\\', '/', substr($c, 4)) . '.php'; if (is_file($r)) require $r; }
});
require APP_RAIZ . '/Core/helpers.php';
require dirname(__DIR__) . '/lib/fpdf/fpdf.php';

use App\Core\AccessControl;
use App\Core\Acceso;
use App\Core\App;
use App\Core\Database;
use App\Core\GrafoPersona;
use App\Modules\Ingesta\Ingesta;
use App\Modules\Ingesta\Pdf;

App::cargarConfiguracion();
$_SESSION = [];
$fallos = 0;
$ok = function (string $m, bool $c) use (&$fallos): void { echo ($c ? 'ok   ' : 'FALLA') . ' ' . $m . "\n"; $fallos += $c ? 0 : 1; };
$id = static fn(string $rol, int $salto = 0): int => (int) Database::valor("SELECT id FROM usuarios WHERE rol = ? AND activo = 1 AND departamento_id > 0 ORDER BY id LIMIT 1 OFFSET $salto", [$rol]);
$como = function (int $usuario): void { $_SESSION['usuario'] = AccessControl::user($usuario); GrafoPersona::reiniciar(); };

// --- 1. Enlaces: solo https de Teams, SharePoint u OneDrive
foreach (['https://uaemex.sharepoint.com/sites/FI/Documentos/acta12.pdf', 'https://teams.microsoft.com/l/file/ABC?tenantId=1', 'https://uaemex-my.sharepoint.com/:b:/g/personal/x/EaBc', 'https://1drv.ms/b/s!Abc'] as $u) {
    $ok('admite ' . parse_url($u, PHP_URL_HOST), Ingesta::enlaceValido($u) === $u);
}
foreach (['http://uaemex.sharepoint.com/a.pdf', 'https://sharepoint.com.evil.example/a.pdf', 'https://evil.example/?x=sharepoint.com', 'javascript:alert(1)', 'https://usuario:clave@uaemex.sharepoint.com/a', 'https://uaemex.sharepoint.com:8443/a', "https://uaemex.sharepoint.com/a\"><script>", 'https://fingenieria.mx/x.pdf'] as $u) {
    $ok('rechaza ' . mb_substr($u, 0, 46), Ingesta::enlaceValido($u) === null);
}

// --- 2. Lector de PDF
$pdf = new FPDF();
$pdf->SetCompression(true);
$pdf->SetTitle('Reglamento de prueba');
$pdf->SetAuthor('Secretaria Academica');
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);
$pdf->MultiCell(0, 8, 'Este reglamento establece los requisitos de titulacion para los pasantes de la Facultad de Ingenieria.');
$pdf->AddPage();
$pdf->Cell(0, 8, 'Segunda pagina con el transitorio unico.');
$ruta = tempnam(sys_get_temp_dir(), 'ing');
file_put_contents($ruta, $pdf->Output('S'));
$leido = Pdf::leer($ruta);
$ok('PDF: cuenta 2 páginas', $leido['paginas'] === 2);
$ok('PDF: lee título y autor', $leido['titulo'] === 'Reglamento de prueba' && $leido['autor'] === 'Secretaria Academica');
$ok('PDF: lee la fecha de creación', $leido['fecha'] === date('Y-m-d'));
$ok('PDF: extrae texto para la búsqueda', str_contains($leido['texto'], 'requisitos de titulacion') && str_contains($leido['texto'], 'transitorio'));
file_put_contents($ruta, "%PDF-1.4\n" . random_bytes(4000));
$basura = Pdf::leer($ruta);
$ok('PDF dañado: no falla ni inventa texto', $basura['texto'] === '' && $basura['titulo'] === '');
file_put_contents($ruta, "%PDF-1.4\n1 0 obj<</Encrypt 2 0 R/Title(Secreto)>>endobj");
$ok('PDF cifrado: no se lee su contenido', Pdf::leer($ruta)['titulo'] === '');
@unlink($ruta);

$admin = $id('admin');
$titular = $id('titular');
$otroTitular = $id('titular', 1);
$profesor = $id('profesor');
$alumno = $id('alumno');
$deptoTitular = (int) Database::valor('SELECT departamento_id FROM usuarios WHERE id = ?', [$titular]);

$pdo = Database::pdo();
$pdo->beginTransaction();
try {
    // --- 3. Alcance: cada quien ve solo lo que le corresponde
    $como($titular);
    $ok('el titular puede ingresar; el alumno y el profesor no', Ingesta::puedeIngresar());
    $como($alumno);
    $noAlumno = !Ingesta::puedeIngresar();
    $como($profesor);
    $ok('alumno y profesor no pueden ingresar sin concesión', $noAlumno && !Ingesta::puedeIngresar());

    $como($titular);
    $carpetas = [];
    foreach (array_keys(Ingesta::ALCANCES) as $alcance) {
        [$a, $d] = Ingesta::destino($alcance, $deptoTitular);
        $carpetas[$alcance] = Ingesta::crearCarpeta('Carpeta ' . $alcance, 'prueba', 'teams', null, $a, $d);
        $r = Ingesta::ingresarEnlaces("Acta zzprueba $alcance | https://uaemex.sharepoint.com/sites/FI/acta-$alcance.pdf\nhttps://malo.example/x.pdf", ['id' => $carpetas[$alcance]], $a, $d);
        if ($r['nuevos'] !== 1 || count($r['rechazados']) !== 1) { $ok("ingesta de enlaces ($alcance)", false); }
    }
    $ok('enlaces: admite el de SharePoint y rechaza el ajeno', true);
    $repetido = Ingesta::ingresarEnlaces('https://uaemex.sharepoint.com/sites/FI/acta-comunidad.pdf', ['id' => $carpetas['comunidad']], 'comunidad', $deptoTitular);
    $ok('enlaces: el mismo enlace no se duplica en la carpeta', $repetido['nuevos'] === 0);
    $ve = static fn(): array => array_map(static fn(array $d): string => $d['alcance'], Ingesta::buscar('zzprueba'));
    $igual = static function (array $a, array $b): bool { sort($a); sort($b); return $a === $b; };

    $ok('quien ingresó ve sus cuatro documentos', $igual($ve(), ['comunidad', 'departamento', 'personal', 'propio']));
    $como($alumno);
    $ok('el alumno solo ve lo de «comunidad»', $ve() === ['comunidad']);
    $ok('el alumno no puede abrir la carpeta de la dependencia', Ingesta::carpeta($carpetas['departamento']) === null && Ingesta::carpeta($carpetas['comunidad']) !== null);
    $como($profesor);
    $ok('el profesor de otra dependencia ve «comunidad» y «personal»', $igual($ve(), ['comunidad', 'personal']));
    $como($otroTitular);
    $ok('otro titular no ve lo de una dependencia ajena', $igual($ve(), ['comunidad', 'personal']));
    $como($admin);
    $ok('la administración ve todo menos lo «propio» de otra persona', $igual($ve(), ['comunidad', 'departamento', 'personal']));
    Acceso::conceder($admin, $profesor, 'ingesta.carpeta', date('Y-m-d', strtotime('+2 days')), $carpetas['departamento'], null, 'usar', 'prueba');
    $como($profesor);
    $ok('con concesión sobre la carpeta, el profesor la ve', $igual($ve(), ['comunidad', 'departamento', 'personal']));
    $ok('la concesión no abre lo «propio»', Ingesta::carpeta($carpetas['propio']) === null);
    $ok('el profesor no es dueño: no puede editar ni eliminar', !Ingesta::esDueno(Ingesta::carpeta($carpetas['departamento'])));
    $ok('búsqueda sin coincidencias devuelve vacío', Ingesta::buscar('qqqinexistenteqqq') === []);

    // --- 4. Grafo: contenido
    $como($titular);
    $g = GrafoPersona::de($titular);
    $titulos = array_column($g['grupos'], 'titulo');
    $ok('grafo: trae adscripción y las carpetas ingresadas', in_array('Adscripción y cargos', $titulos, true) && in_array('Carpetas de documentos ingresadas', $titulos, true));
    $ok('grafo: los enlaces son rutas internas relativas', !array_filter(array_merge(...array_column($g['grupos'], 'items')), static fn(array $i): bool => $i['enlace'] !== null && preg_match('#^(/|https?:)#', $i['enlace'])));
    $svg = GrafoPersona::svg($g);
    $ok('grafo: el SVG es XML bien formado y sin scripts', @simplexml_load_string($svg) !== false && !str_contains($svg, '<script') && !str_contains($svg, 'href'));
    $como($admin);
    $mio = array_column(GrafoPersona::de($titular)['grupos'], null, 'titulo')['Carpetas de documentos ingresadas'] ?? ['total' => 0];
    $ok('grafo visto por otra persona: no asoma lo «propio»', $mio['total'] === 3);

    // --- 5. Grafo: quién puede ver el de quién
    $como($alumno);
    $ok('el alumno ve su grafo y ningún otro', GrafoPersona::puedeVer($alumno) && !GrafoPersona::puedeVer($profesor) && !GrafoPersona::puedeVer($admin) && !GrafoPersona::puedeBuscar());
    $como($profesor);
    $ok('el profesor ve su grafo y ningún otro', GrafoPersona::puedeVer($profesor) && !GrafoPersona::puedeVer($alumno) && !GrafoPersona::puedeVer($titular) && !GrafoPersona::puedeBuscar());
    $como($admin);
    $ok('la administración ve el de cualquiera y busca entre todas las cuentas', GrafoPersona::puedeVer($alumno) && GrafoPersona::idsVisibles() === null && GrafoPersona::buscarPersonas('a') !== []);
    $ok('la administración debe escribir a quién busca', GrafoPersona::buscarPersonas('') === []);

    // Coordinador de programa → profesores de ese programa
    $prog = (int) Database::valor('SELECT id FROM programas ORDER BY id LIMIT 1');
    $prog2 = (int) Database::valor('SELECT id FROM programas ORDER BY id LIMIT 1 OFFSET 1');
    $coordinador = (int) Database::valor("SELECT id FROM usuarios WHERE rol = 'profesor' AND activo = 1 AND id <> ? ORDER BY id LIMIT 1", [$profesor]);
    $colega = (int) Database::valor("SELECT id FROM usuarios WHERE rol = 'profesor' AND activo = 1 AND id NOT IN (?, ?) ORDER BY id LIMIT 1", [$profesor, $coordinador]);
    Database::ejecutar('DELETE FROM programa_profesores WHERE usuario_id IN (?, ?)', [$profesor, $colega]);
    Database::ejecutar("INSERT INTO programa_responsables (programa_id, usuario_id, cargo, fecha_inicio, activo) VALUES (?, ?, 'coordinador', CURDATE(), 1)", [$prog, $coordinador]);
    Database::ejecutar("INSERT INTO programa_profesores (programa_id, usuario_id, tipo_vinculo, fecha_inicio, activo) VALUES (?, ?, 'docente', CURDATE(), 1)", [$prog, $profesor]);
    Database::ejecutar("INSERT INTO programa_profesores (programa_id, usuario_id, tipo_vinculo, fecha_inicio, activo) VALUES (?, ?, 'docente', CURDATE(), 1)", [$prog2, $colega]);
    $como($coordinador);
    $ok('el coordinador de un programa ve a sus profesores', GrafoPersona::puedeVer($profesor) && GrafoPersona::puedeBuscar());
    $ok('…pero no a los de otro programa ni a los alumnos', !GrafoPersona::puedeVer($colega) && !GrafoPersona::puedeVer($alumno));
    $ok('su buscador solo lista a las personas a su cargo', array_map('intval', array_column(GrafoPersona::buscarPersonas(''), 'id')) == array_values(array_unique([$coordinador, $profesor])) || in_array($profesor, array_map('intval', array_column(GrafoPersona::buscarPersonas(''), 'id')), true) && !in_array($colega, array_map('intval', array_column(GrafoPersona::buscarPersonas(''), 'id')), true));
    $programas = array_column(GrafoPersona::de($profesor)['grupos'], 'titulo');
    $ok('el grafo del profesor muestra el programa en el que imparte', in_array('Programas en los que imparte clases', $programas, true));

    // Líder de un órgano académico → sus miembros
    Database::ejecutar("INSERT INTO programa_organos (programa_id, tipo, nombre, activo) VALUES (?, 'NAB', 'Órgano de prueba', 1)", [$prog2]);
    $organo = (int) Database::valor('SELECT LAST_INSERT_ID()');
    Database::ejecutar("INSERT INTO programa_organo_miembros (organo_id, usuario_id, cargo, fecha_inicio, activo) VALUES (?, ?, 'presidente', CURDATE(), 1), (?, ?, 'miembro', CURDATE(), 1)", [$organo, $profesor, $organo, $colega]);
    $como($profesor);
    $ok('el líder del cuerpo académico ve a sus miembros', GrafoPersona::puedeVer($colega) && !GrafoPersona::puedeVer($coordinador));
    $como($colega);
    $ok('un miembro sin cargo no ve a los demás', !GrafoPersona::puedeVer($profesor));

    // Titular → personal de su dependencia, no alumnos
    $deptoProfesor = (int) Database::valor('SELECT departamento_id FROM usuarios WHERE id = ?', [$profesor]);
    Database::ejecutar('UPDATE usuarios SET departamento_id = ? WHERE id IN (?, ?)', [$deptoTitular, $profesor, $alumno]);
    $como($titular);
    $ok('el titular ve al personal de su dependencia, no a los alumnos', GrafoPersona::puedeVer($profesor) && !GrafoPersona::puedeVer($alumno));
    Database::ejecutar('UPDATE usuarios SET departamento_id = ? WHERE id = ?', [$deptoProfesor, $profesor]);

    // Concesiones temporales (comisión): por programa, por órgano y por persona
    $como($otroTitular);
    $antes = !GrafoPersona::puedeVer($colega) && !GrafoPersona::puedeVer($profesor);
    Acceso::conceder($admin, $otroTitular, 'grafo.programa', date('Y-m-d', strtotime('+7 days')), $prog2, null, 'usar', 'Comisión de prueba');
    GrafoPersona::reiniciar();
    $ok('concesión temporal sobre un programa: ve a sus profesores', $antes && GrafoPersona::puedeVer($colega));
    Database::ejecutar("UPDATE acceso_concesiones SET vigente_hasta = CURDATE() - INTERVAL 1 DAY WHERE usuario_id = ? AND funcion = 'grafo.programa'", [$otroTitular]);
    GrafoPersona::reiniciar();
    $ok('al vencer la concesión, deja de verlos', !GrafoPersona::puedeVer($colega));
    Acceso::conceder($admin, $otroTitular, 'grafo.persona', date('Y-m-d', strtotime('+7 days')), $alumno, null, 'usar', 'Comisión de prueba');
    GrafoPersona::reiniciar();
    $ok('concesión sobre una persona: solo esa persona', GrafoPersona::puedeVer($alumno) && !GrafoPersona::puedeVer($colega));
    Acceso::conceder($admin, $otroTitular, 'grafo.organo', date('Y-m-d', strtotime('+7 days')), $organo, null, 'usar', 'Comisión de prueba');
    GrafoPersona::reiniciar();
    $ok('concesión sobre un órgano: sus miembros', GrafoPersona::puedeVer($colega) && GrafoPersona::puedeVer($profesor));
} finally {
    $pdo->rollBack();
}

echo $fallos === 0 ? "\nTodo en orden.\n" : "\n$fallos prueba(s) fallaron.\n";
exit($fallos === 0 ? 0 : 1);
