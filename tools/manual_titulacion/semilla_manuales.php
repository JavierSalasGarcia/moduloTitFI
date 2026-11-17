<?php
/**
 * Prepara la base LOCAL para tomar las capturas de los manuales (licenciatura y posgrado):
 *  1. Vacía los datos de trámites de las tablas tit_* y pos_* (no toca la configuración: documentos, textos de
 *     constancias, plantillas, asistentes, piloto) y sus archivos en storage/titulacion y storage/posgrado.
 *  2. Da nombres ficticios y verosímiles a las cuentas de prueba (las crea si faltan) y asigna programa a pasantes y
 *     egresados.
 *  3. Pone datos ficticios en los ajustes de Posgrado que salen en los oficios (quién firma por la Coordinación y el
 *     contacto de Apoyo a Grado). Los valores anteriores se guardan en storage/manuales/_ajustes_previos.json y se
 *     restauran con --restaurar-ajustes.
 *
 * Es autosuficiente (crea cuentas, comisión de protocolos y Coordinador de Docencia de Computación). Se niega a correr
 * fuera de las bases locales de pruebas. Uso: php tools/manual_titulacion/semilla_manuales.php [--restaurar-ajustes]
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\AccessControl;
use App\Core\App;
use App\Core\Database;

$config = require APP_RAIZ . '/config.php';
(new ReflectionProperty(App::class, 'config'))->setValue(null, $config);
$db = (string) ($config['db']['nombre'] ?? '');
if (!in_array($db, ['plataforma_fi', 'fi_ux_test_20260909', 'titulacion'], true) || str_starts_with($db, 'u429286701')) {
    exit("Base «{$db}» no permitida: solo pruebas locales.\n");
}
$storage = (string) $config['archivos']['storage'];
$previos = $storage . '/manuales/_ajustes_previos.json';

$ajustes = [
    'posgrado_coordinador_nombre' => 'Dra. Mariana Velázquez Ortiz',
    'posgrado_coordinador_cargo' => 'Coordinadora de Estudios Avanzados F.I.',
    'posgrado_coordinador_correo' => 'coord.avanzados@uaemex.mx',
    'posgrado_apoyo_nombre' => 'Mtra. Silvia Romero Castañeda',
    'posgrado_apoyo_correos' => 'apoyo.grado@uaemex.mx',
    'posgrado_apoyo_telefono' => '722 000 00 00 extensión 0000',
];

if (in_array('--restaurar-ajustes', $argv, true)) {
    if (!is_file($previos)) {
        exit("No hay ajustes guardados.\n");
    }
    AccessControl::internal(function () use ($previos): void {
        foreach (json_decode((string) file_get_contents($previos), true) as $k => $v) {
            if ($v === null) {
                Database::ejecutar('DELETE FROM ajustes WHERE clave = ?', [$k]);
            } else {
                Database::ejecutar('UPDATE ajustes SET valor = ? WHERE clave = ?', [$v, $k]);
            }
        }
    });
    unlink($previos);
    exit("Ajustes de Posgrado restaurados.\n");
}

// [correo, nombre, grado, género, rol, clave de dependencia o null, siglas del programa o null]
$cuentas = [
    ['prueba.jefatura.titulacion@uaemex.mx', 'Mtra. Rosa Elena Galindo Vera', 'Mtra.', 'femenino', 'titular', 'departamento_de_evaluacion_profesional_y_grado', null],
    ['prueba.apoyo.titulacion@uaemex.mx', 'Lic. Mario Esquivel Ruiz', 'Lic.', 'masculino', 'operativo', 'departamento_de_evaluacion_profesional_y_grado', null],
    ['apoyo.titulacion@uaemex.mx', 'Lic. Norma Patricia Ugalde Sosa', 'Lic.', 'femenino', 'operativo', 'departamento_de_evaluacion_profesional_y_grado', null],
    ['prueba.subdirector.academico@uaemex.mx', 'Dr. Ernesto Villaseñor Díaz', 'Dr.', 'masculino', 'titular', 'subdireccion_academica', null],
    ['prueba.director@uaemex.mx', 'Dra. Verónica Salgado Ibarra', 'Dra.', 'femenino', 'titular', 'direccion', null],
    ['prueba.coordinador.computacion@uaemex.mx', 'Dr. Luis Fernando Arriaga Peña', 'Dr.', 'masculino', 'profesor', null, null],
    ['prueba.encargada.comision@uaemex.mx', 'Dra. Marta Cecilia Robles Treviño', 'Dra.', 'femenino', 'profesor', null, null],
    ['prueba.evaluador1@uaemex.mx', 'Dr. Pablo Andrade Soto', 'Dr.', 'masculino', 'profesor', null, null],
    ['prueba.evaluador2@uaemex.mx', 'M. en C. Ana Lucía Ferrer Montes', 'M. en C.', 'femenino', 'profesor', null, null],
    ['prueba.asesor@uaemex.mx', 'Dr. Raúl Medina Ortega', 'Dr.', 'masculino', 'profesor', null, null],
    ['prueba.coasesor@uaemex.mx', 'Dra. Julia Paredes Luna', 'Dra.', 'femenino', 'profesor', null, null],
    ['prueba.sinodal1@uaemex.mx', 'Dr. Sergio Cárdenas Bravo', 'Dr.', 'masculino', 'profesor', null, null],
    ['prueba.sinodal2@uaemex.mx', 'Dra. Laura Inés Quintero Gil', 'Dra.', 'femenino', 'profesor', null, null],
    ['prueba.sinodal3@uaemex.mx', 'M. en I. Tomás Rendón Aguilar', 'M. en I.', 'masculino', 'profesor', null, null],
    ['profesor.prueba@uaemex.mx', 'Dr. Juan Carlos Ponce Ríos', 'Dr.', 'masculino', 'profesor', null, null],
    ['admin.local@uaemex.mx', 'Ing. Adrián Lozano Prieto', 'Ing.', 'masculino', 'admin', 'direccion', null],
    ['apoyo.grado@uaemex.mx', 'Mtra. Silvia Romero Castañeda', 'Mtra.', 'femenino', 'operativo', 'coordinacion_de_estudios_avanzados', null],
    ['coord.avanzados@uaemex.mx', 'Dra. Mariana Velázquez Ortiz', 'Dra.', 'femenino', 'titular', 'coordinacion_de_estudios_avanzados', null],
    // Pasantes de licenciatura: uno por opción y uno para borrar, pausar y retomar.
    ['prueba.pasante.aprovechamiento@alumno.uaemex.mx', 'Alicia Morales Fuentes', '', 'femenino', 'alumno', null, 'ICO'],
    ['prueba.pasante.egel@alumno.uaemex.mx', 'Eduardo Rangel Silva', '', 'masculino', 'alumno', null, 'ICO'],
    ['prueba.pasante.creditos@alumno.uaemex.mx', 'Carla Benítez Ochoa', '', 'femenino', 'alumno', null, 'ICO'],
    ['prueba.pasante.articulo@alumno.uaemex.mx', 'Arturo Delgado Cruz', '', 'masculino', 'alumno', null, 'ICO'],
    ['prueba.pasante.ensayo@alumno.uaemex.mx', 'Elisa Navarro Pineda', '', 'femenino', 'alumno', null, 'ICO'],
    ['prueba.pasante.memoria@alumno.uaemex.mx', 'Mateo Guerrero León', '', 'masculino', 'alumno', null, 'ICO'],
    ['prueba.pasante.aplicacion@alumno.uaemex.mx', 'Andrea Campos Rivas', '', 'femenino', 'alumno', null, 'ICO'],
    ['prueba.pasante.autoempleo@alumno.uaemex.mx', 'Aurelio Sandoval Mejía', '', 'masculino', 'alumno', null, 'ICO'],
    ['prueba.pasante.residencia@alumno.uaemex.mx', 'Regina Montiel Ávila', '', 'femenino', 'alumno', null, 'ICO'],
    ['prueba.pasante.tesina@alumno.uaemex.mx', 'Tadeo Estrada Lara', '', 'masculino', 'alumno', null, 'ICO'],
    ['prueba.pasante.tesis@alumno.uaemex.mx', 'Teresa Hinojosa Bernal', '', 'femenino', 'alumno', null, 'ICO'],
    ['licenciatura@alumno.uaemex.mx', 'Lucía Herrera Pacheco', '', 'femenino', 'alumno', null, 'ICO'],
    // Egresados de posgrado: uno por modalidad (el de artículos, de doctorado) y uno para borrar, pausar y retomar.
    ['maestria@alumno.uaemex.mx', 'Marco Antonio Juárez Téllez', '', 'masculino', 'alumno', null, 'MMT'],
    ['doctorado@alumno.uaemex.mx', 'Dora Valdés Castañeda', '', 'femenino', 'alumno', null, 'DCI'],
    ['prueba.egresado.tt@alumno.uaemex.mx', 'Iván Ledesma Corona', '', 'masculino', 'alumno', null, 'MMT'],
    ['prueba.egresado.pt@alumno.uaemex.mx', 'Paula Sierra Mondragón', '', 'femenino', 'alumno', null, 'MMT'],
    ['prueba.egresado.pausa@alumno.uaemex.mx', 'Hugo Barrera Olvera', '', 'masculino', 'alumno', null, 'MMT'],
    // Sin trámite: para probar que descargar manuales no crea registros.
    ['prueba.pasante.nuevo@alumno.uaemex.mx', 'Sofía Ibarra Quiroz', '', 'femenino', 'alumno', null, 'ICO'],
    ['prueba.egresado.nuevo@alumno.uaemex.mx', 'Rubén Toledo Arce', '', 'masculino', 'alumno', null, 'DCI'],
];

AccessControl::internal(function () use ($cuentas, $ajustes, $storage, $previos): void {
    // 1. Datos de trámites (no la configuración).
    $conservar = ['tit_docs_cfg', 'tit_constancia_textos', 'tit_plantillas_mensaje', 'tit_asistentes', 'tit_piloto', 'tit_config_bitacora', 'tit_comision_miembros'];
    Database::ejecutar('SET FOREIGN_KEY_CHECKS = 0');
    foreach (Database::todas("SHOW TABLES LIKE 'tit\\_%'") as $r) {
        $t = (string) array_values($r)[0];
        if (!in_array($t, $conservar, true)) {
            Database::ejecutar("TRUNCATE TABLE `$t`");
        }
    }
    foreach (Database::todas("SHOW TABLES LIKE 'pos\\_%'") as $r) {
        Database::ejecutar('TRUNCATE TABLE `' . array_values($r)[0] . '`');
    }
    Database::ejecutar('SET FOREIGN_KEY_CHECKS = 1');
    Database::ejecutar("DELETE FROM notificaciones WHERE email LIKE 'prueba.%' OR email IN ('licenciatura@alumno.uaemex.mx', 'maestria@alumno.uaemex.mx', 'doctorado@alumno.uaemex.mx', 'profesor.prueba@uaemex.mx', 'apoyo.grado@uaemex.mx', 'coord.avanzados@uaemex.mx', 'admin.local@uaemex.mx', 'apoyo.titulacion@uaemex.mx')");
    foreach (['titulacion', 'posgrado'] as $carpeta) {
        $base = $storage . '/' . $carpeta;
        foreach (glob($base . '/*', GLOB_ONLYDIR) ?: [] as $d) {
            if (preg_match('#/\d+$#', $d)) {
                exec('rm -rf ' . escapeshellarg($d));
            }
        }
    }
    foreach (['titulacion-correos.log', 'posgrado-correos.log'] as $l) {
        @file_put_contents($storage . '/logs/' . $l, '');
    }

    // 2. Cuentas.
    $depto = static fn(?string $c): int => $c ? (int) Database::valor('SELECT id FROM departamentos WHERE clave = ?', [$c]) : 0;
    $prog = static fn(?string $s): ?int => $s ? ((int) Database::valor('SELECT id FROM programas WHERE siglas = ?', [$s]) ?: null) : null;
    foreach ($cuentas as [$email, $nombre, $grado, $genero, $rol, $dep, $siglas]) {
        $id = Database::valor('SELECT id FROM usuarios WHERE email = ?', [$email]);
        if ($id) {
            Database::ejecutar('UPDATE usuarios SET nombre = ?, grado = ?, genero = ?, rol = ?, departamento_id = ?, programa_id = ?, activo = 1, fise_vigencia = NULL, fise_avisada = NULL WHERE id = ?',
                [$nombre, $grado, $genero, $rol, $depto($dep), $prog($siglas), $id]);
        } else {
            Database::ejecutar('INSERT INTO usuarios (nombre, grado, genero, email, password_hash, departamento_id, programa_id, rol, activo, permite_contrasena, logins_contrasena_restantes) VALUES (?,?,?,?,?,?,?,?,1,0,0)',
                [$nombre, $grado, $genero, $email, password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $depto($dep), $prog($siglas), $rol]);
        }
    }
    // Comisión de revisión de protocolos de Computación y Coordinador de Docencia de Ingeniería en Computación.
    Database::ejecutar("DELETE FROM tit_comision_miembros WHERE carrera = 'computacion' AND email LIKE 'prueba.%'");
    foreach ([['prueba.encargada.comision@uaemex.mx', 'encargado'], ['prueba.evaluador1@uaemex.mx', 'miembro'], ['prueba.evaluador2@uaemex.mx', 'miembro']] as [$c, $r]) {
        Database::ejecutar("INSERT INTO tit_comision_miembros (carrera, nombre, email, rol) SELECT 'computacion', nombre, email, ? FROM usuarios WHERE email = ?", [$r, $c]);
    }
    $ico = (int) Database::valor("SELECT id FROM programas WHERE siglas = 'ICO'");
    $coord = (int) Database::valor("SELECT id FROM usuarios WHERE email = 'prueba.coordinador.computacion@uaemex.mx'");
    Database::ejecutar("UPDATE programa_responsables SET activo = 0 WHERE programa_id = ? AND cargo = 'coordinador' AND usuario_id <> ?", [$ico, $coord]);
    if (!Database::valor("SELECT id FROM programa_responsables WHERE programa_id = ? AND usuario_id = ? AND cargo = 'coordinador'", [$ico, $coord])) {
        Database::ejecutar("INSERT INTO programa_responsables (programa_id, usuario_id, cargo, fecha_inicio, activo) VALUES (?, ?, 'coordinador', '2026-01-01', 1)", [$ico, $coord]);
    } else {
        Database::ejecutar("UPDATE programa_responsables SET activo = 1, fecha_fin = NULL WHERE programa_id = ? AND usuario_id = ? AND cargo = 'coordinador'", [$ico, $coord]);
    }

    // 3. Ajustes de Posgrado que aparecen en los oficios.
    if (!is_file($previos)) {
        $antes = [];
        foreach (array_keys($ajustes) as $k) {
            $v = Database::valor('SELECT valor FROM ajustes WHERE clave = ?', [$k]);
            $antes[$k] = $v === false || $v === null ? null : (string) $v;
        }
        @mkdir(dirname($previos), 0770, true);
        file_put_contents($previos, json_encode($antes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    foreach ($ajustes as $k => $v) {
        Database::ejecutar('INSERT INTO ajustes (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)', [$k, $v, 'Posgrado (datos ficticios de pruebas)']);
    }
});
echo "Listo: tablas tit_*/pos_* vacías, " . count($cuentas) . " cuentas con nombres ficticios y ajustes de Posgrado de prueba.\n";
