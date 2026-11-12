<?php
/**
 * Crea (o restablece) las cuentas de prueba de Titulación de licenciatura: una por actor del proceso y un pasante por
 * opción de titulación. SOLO para bases locales o de pruebas (rechaza la base de producción). Genera una contraseña
 * aleatoria por cuenta y las escribe en cred_titulacion_pruebas.txt (ignorado por git; nunca se versiona ni se despliega).
 *
 * Uso:  php tools/titulacion_pruebas_semilla.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\AccessControl;
use App\Core\App;
use App\Core\Database;

$config = require APP_RAIZ . '/config.php';
$propiedad = new ReflectionProperty(App::class, 'config');
$propiedad->setAccessible(true);
$propiedad->setValue(null, $config);
$db = (string) ($config['db']['nombre'] ?? '');
if (!in_array($db, ['plataforma_fi', 'fi_ux_test_20260909', 'titulacion'], true) || str_starts_with($db, 'u429286701')) {
    exit("Base «{$db}» no permitida: solo pruebas locales.\n");
}

$dominioPers = 'uaemex.mx';
$dominioAl = 'alumno.uaemex.mx';

/** [clave, nombre, grado, genero, email, rol, depto, cargo, descripción del papel] */
$personal = [
    ['jefe_titulacion', 'Mtra. Rosa Elena Jefatura Prueba', 'Mtra.', 'femenino', "prueba.jefatura.titulacion@$dominioPers", 'titular', 'departamento_de_evaluacion_profesional_y_grado', 'titular', 'Responsable del Departamento de Evaluación Profesional y Grado (Titulación): valida documentos, registra fechas y resultado, verifica el expediente final y lo envía a Servicios Escolares.'],
    ['apoyo_titulacion', 'Lic. Mario Apoyo Prueba', 'Lic.', 'masculino', "prueba.apoyo.titulacion@$dominioPers", 'operativo', 'departamento_de_evaluacion_profesional_y_grado', null, 'Apoyo a Titulación: valida documentos, coteja originales y recibe protocolos.'],
    ['subdirector', 'Dr. Ernesto Subdirección Prueba', 'Dr.', 'masculino', "prueba.subdirector.academico@$dominioPers", 'titular', 'subdireccion_academica', 'titular', 'Subdirector Académico: firma electrónicamente oficios y autorizaciones.'],
    ['director', 'Dra. Verónica Dirección Prueba', 'Dra.', 'femenino', "prueba.director@$dominioPers", 'titular', 'direccion', 'titular', 'Director de la Facultad: preside la comisión de toma de protesta.'],
    ['coord_docencia', 'Dr. Luis Coordinación Prueba', 'Dr.', 'masculino', "prueba.coordinador.computacion@$dominioPers", 'profesor', null, null, 'Coordinador de Docencia (Coordinador de Carrera) de Ingeniería en Computación: ratifica el sínodo y el jurado.'],
    ['enc_comision', 'Dra. Marta Comisión Prueba', 'Dra.', 'femenino', "prueba.encargada.comision@$dominioPers", 'profesor', null, null, 'Encargada de la comisión de revisión de protocolos: asigna evaluadores y firma el dictamen.'],
    ['evaluador1', 'Dr. Pablo Evaluador Uno Prueba', 'Dr.', 'masculino', "prueba.evaluador1@$dominioPers", 'profesor', null, null, 'Evaluador de protocolos (comisión).'],
    ['evaluador2', 'M. en C. Ana Evaluadora Dos Prueba', 'M. en C.', 'femenino', "prueba.evaluador2@$dominioPers", 'profesor', null, null, 'Evaluadora de protocolos (comisión).'],
    ['asesor', 'Dr. Raúl Asesor Prueba', 'Dr.', 'masculino', "prueba.asesor@$dominioPers", 'profesor', null, null, 'Asesor (director) del trabajo escrito.'],
    ['coasesor', 'Dra. Julia Coasesora Prueba', 'Dra.', 'femenino', "prueba.coasesor@$dominioPers", 'profesor', null, null, 'Coasesora (codirectora) del trabajo escrito.'],
    ['sinodal1', 'Dr. Sergio Sinodal Uno Prueba', 'Dr.', 'masculino', "prueba.sinodal1@$dominioPers", 'profesor', null, null, 'Sinodal (presidente del jurado / revisor).'],
    ['sinodal2', 'Dra. Laura Sinodal Dos Prueba', 'Dra.', 'femenino', "prueba.sinodal2@$dominioPers", 'profesor', null, null, 'Sinodal (secretaria del jurado / revisora).'],
    ['sinodal3', 'M. en I. Tomás Sinodal Tres Prueba', 'M. en I.', 'masculino', "prueba.sinodal3@$dominioPers", 'profesor', null, null, 'Sinodal (vocal del jurado / revisor).'],
];

/** Un pasante por opción: [clave, modalidad, variante, nombre] */
$pasantes = [
    ['aprovechamiento', 'aprovechamiento', null, 'Alicia Aprovechamiento Prueba'],
    ['egel', 'egel', 'pagando_menos', 'Eduardo Egel Prueba'],
    ['creditos', 'creditos', null, 'Carla Créditos Prueba'],
    ['articulo', 'articulo', null, 'Arturo Artículo Prueba'],
    ['ensayo', 'ensayo', null, 'Elisa Ensayo Prueba'],
    ['memoria', 'memoria', null, 'Mateo Memoria Prueba'],
    ['aplicacion', 'aplicacion', null, 'Andrea Aplicación Prueba'],
    ['autoempleo', 'autoempleo', null, 'Aurelio Autoempleo Prueba'],
    ['residencia', 'residencia', null, 'Regina Residencia Prueba'],
    ['tesina', 'tesina', null, 'Tadeo Tesina Prueba'],
    ['tesis', 'tesis', null, 'Teresa Tesis Prueba'],
];

$ids = [];
$cred = [];
AccessControl::internal(function () use ($personal, $pasantes, $dominioAl, &$ids, &$cred): void {
    $clave = static fn(string $c): ?int => ($v = Database::valor('SELECT id FROM departamentos WHERE clave = ?', [$c])) ? (int) $v : null;
    $upsert = static function (string $nombre, string $grado, string $genero, string $email, string $rol, ?int $depto, ?string $cargo, ?int $programa) use (&$cred): int {
        $pass = bin2hex(random_bytes(5)) . 'Aa1!';
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $id = Database::valor('SELECT id FROM usuarios WHERE email = ?', [$email]);
        if ($id) {
            Database::ejecutar('UPDATE usuarios SET nombre=?, grado=?, genero=?, rol=?, departamento_id=?, programa_id=?, cargo=?, activo=1, permite_contrasena=1, logins_contrasena_restantes=9999, password_hash=? WHERE id=?',
                [$nombre, $grado, $genero, $rol, $depto ?? 0, $programa, $cargo, $hash, $id]);
        } else {
            $id = Database::ejecutar('INSERT INTO usuarios (nombre, grado, genero, email, password_hash, departamento_id, programa_id, rol, cargo, activo, permite_contrasena, logins_contrasena_restantes) VALUES (?,?,?,?,?,?,?,?,?,1,1,9999)',
                [$nombre, $grado, $genero, $email, $hash, $depto ?? 0, $programa, $rol, $cargo]);
        }
        $cred[$email] = $pass;
        return (int) $id;
    };
    foreach ($personal as [$k, $nombre, $grado, $genero, $email, $rol, $dep, $cargo]) {
        $ids[$k] = $upsert($nombre, $grado, $genero, $email, $rol, $dep ? $clave($dep) : null, $cargo, null);
    }
    foreach ($pasantes as [$k, , , $nombre]) {
        $ids['pasante_' . $k] = $upsert($nombre, '', 'femenino', "prueba.pasante.$k@$dominioAl", 'alumno', 7, null, 1);
    }
    $ico = (int) Database::valor("SELECT id FROM programas WHERE siglas = 'ICO' AND nivel = 'licenciatura'");
    // Coordinador de Docencia de Ingeniería en Computación: el periodo más reciente manda.
    Database::ejecutar("UPDATE programa_responsables SET activo = 0 WHERE programa_id = ? AND cargo = 'coordinador' AND usuario_id <> ?", [$ico, $ids['coord_docencia']]);
    if (!Database::valor("SELECT id FROM programa_responsables WHERE programa_id = ? AND usuario_id = ? AND cargo = 'coordinador'", [$ico, $ids['coord_docencia']])) {
        Database::ejecutar("INSERT INTO programa_responsables (programa_id, usuario_id, cargo, fecha_inicio, activo) VALUES (?, ?, 'coordinador', '2026-01-01', 1)", [$ico, $ids['coord_docencia']]);
    } else {
        Database::ejecutar("UPDATE programa_responsables SET activo = 1, fecha_fin = NULL WHERE programa_id = ? AND usuario_id = ? AND cargo = 'coordinador'", [$ico, $ids['coord_docencia']]);
    }
    // Comisión de revisión de protocolos de Computación.
    Database::ejecutar("DELETE FROM tit_comision_miembros WHERE carrera = 'computacion' AND email LIKE '%@uaemex.mx' AND email LIKE 'prueba.%'");
    foreach ([['enc_comision', 'encargado'], ['evaluador1', 'miembro'], ['evaluador2', 'miembro']] as [$k, $rol]) {
        $f = Database::una('SELECT nombre, email FROM usuarios WHERE id = ?', [$ids[$k]]);
        Database::ejecutar("INSERT INTO tit_comision_miembros (carrera, nombre, email, rol) VALUES ('computacion', ?, ?, ?)", [$f['nombre'], $f['email'], $rol]);
    }
});

$out = "CUENTAS DE PRUEBA · Titulación de licenciatura (SOLO base local de pruebas; no existen en producción)\n"
    . "Generado " . date('Y-m-d H:i') . ". Este archivo no se versiona ni se despliega.\n\n";
$out .= str_pad('ACTOR', 34) . str_pad('CORREO', 52) . "CONTRASEÑA\n" . str_repeat('-', 110) . "\n";
foreach ($personal as [$k, $nombre, , , $email, , , , $papel]) {
    $out .= str_pad($nombre, 34) . str_pad($email, 52) . $cred[$email] . "\n    → $papel\n";
}
foreach ($pasantes as [$k, $mod, $var, $nombre]) {
    $email = "prueba.pasante.$k@$dominioAl";
    $out .= str_pad($nombre, 34) . str_pad($email, 52) . $cred[$email] . "\n    → Pasante · opción: $mod" . ($var ? " ($var)" : '') . "\n";
}
$out .= "\nServicios Escolares (Administración Central) no usa la plataforma: recibe la carpeta comprimida que exporta el Departamento de Titulación.\n";
$out .= "Los asesores, coasesores y sinodales que firman por correo usan el enlace personal del mensaje (en pruebas, storage/logs/titulacion-correos.log).\n";
file_put_contents(dirname(__DIR__) . '/cred_titulacion_pruebas.txt', $out);
echo "Listo: " . count($cred) . " cuentas. Credenciales en cred_titulacion_pruebas.txt\n";
