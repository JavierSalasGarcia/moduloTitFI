<?php
/**
 * Genera database/actualizar.sql: un solo archivo, acumulativo y repetible, que lleva una base de Traza FI desde
 * cualquier versión anterior (instalada con schema.sql) hasta la actual. Se importa en phpMyAdmin (o con la consola
 * mysql) las veces que haga falta: solo crea o cambia lo que falta y no borra datos.
 *
 * Uso, cada vez que cambie alguna migración:  php database/construir_actualizar.php
 */
declare(strict_types=1);

$dir = __DIR__;

/** Fuentes en el orden en que deben aplicarse. La 008 va antes que la 007: renombra las tablas sa_* antiguas. */
$fuentes = [
    'schema_reuniones.sql', 'seed_reuniones.sql', 'schema_documentos.sql', 'schema_portal_consultas.sql',
    'migrations/006_reuniones_minutas.sql', 'migrations/008_generalizar_asuntos.sql', 'migrations/007_asuntos.sql',
    'migrations/009_firma_pades_pki.sql', 'migrations/014_seguridad_otp.sql', 'migrations/015_estructura_organizacional.sql',
    'migrations/016_profesores_por_programa.sql', 'migrations/017_titulacion.sql',
    'migrations/018_anonimato_encuestas.sql', 'migrations/019_oficios_enviados.sql',
    'migrations/020_cambio_titulares_subdireccion_investigacion.sql', 'migrations/021_cuentas_subdireccion_y_admin.sql',
    'migrations/022_genero_no_binario.sql',
    'migrations/023_justificantes_faltas.sql',
    'migrations/024_roles_multiples.sql',
    'migrations/025_historico_sistema_anterior.sql',
    'migrations/026_directorio_profesores.sql',
    'migrations/027_identidad_estable.sql',
    'migrations/028_historial_titulos.sql',
    'migrations/029_formulario_galardonados.sql',
    'migrations/030_avisos_clasificados_y_asistencias.sql',
];

/** Sentencias de las fuentes que no son repetibles por sí mismas: se ejecutan solo si hace falta. */
$protegidas = [
    'schema_reuniones.sql' => [
        "ALTER TABLE usuarios\n  MODIFY COLUMN rol enum('admin','titular','operativo','profesor','alumno','coordinador')\n  NOT NULL DEFAULT 'operativo';"
        => "SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE usuarios MODIFY COLUMN rol enum(''admin'',''titular'',''operativo'',''profesor'',''alumno'',''coordinador'') NOT NULL DEFAULT ''operativo''', 'DO 0')\n"
         . "          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'rol' AND COLUMN_TYPE NOT LIKE '%coordinador%');\n"
         . "PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;",
    ],
    'migrations/014_seguridad_otp.sql' => [
        "ALTER TABLE usuarios_codigos_otp\n  MODIFY COLUMN codigo VARCHAR(255) NOT NULL;"
        => "SET @s = (SELECT IF(COUNT(*) > 0, 'ALTER TABLE usuarios_codigos_otp MODIFY COLUMN codigo VARCHAR(255) NOT NULL', 'DO 0')\n"
         . "          FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios_codigos_otp' AND COLUMN_NAME = 'codigo' AND (DATA_TYPE <> 'varchar' OR CHARACTER_MAXIMUM_LENGTH < 255));\n"
         . "PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;",
    ],
];

$salida = "-- ============================================================================================================\n"
    . "-- Traza FI · Actualización acumulativa de la base de datos (generada: " . date('Y-m-d H:i') . ")\n"
    . "--\n"
    . "-- Lleva la base desde cualquier versión anterior hasta la actual. Es repetible: se puede importar las veces que\n"
    . "-- haga falta (phpMyAdmin → Importar, o mysql < actualizar.sql); solo crea o cambia lo que falta y no borra datos.\n"
    . "-- Requiere una base ya instalada con schema.sql. No editar a mano: se genera con database/construir_actualizar.php\n"
    . "-- a partir de las migraciones (" . count($fuentes) . " fuentes, en orden).\n"
    . "-- ============================================================================================================\n\n"
    . "SET NAMES utf8mb4;\n\n";

foreach ($fuentes as $f) {
    $sql = file_get_contents($dir . '/' . $f);
    if ($sql === false) {
        fwrite(STDERR, "No se encontró $f\n");
        exit(1);
    }
    $sql = str_replace("\r\n", "\n", $sql);
    foreach ($protegidas[$f] ?? [] as $original => $protegida) {
        if (!str_contains($sql, $original)) {
            fwrite(STDERR, "La sentencia protegida de $f cambió; revisa construir_actualizar.php\n");
            exit(1);
        }
        $sql = str_replace($original, $protegida, $sql);
    }
    $sql = preg_replace('/^\s*SET NAMES [^;]+;\s*$/mi', '', $sql) ?? $sql;
    $sql = str_replace("'SELECT 1'", "'DO 0'", $sql);   // «no hacer nada» sin dejar resultados vacíos en phpMyAdmin
    $salida .= "-- ------------------------------------------------------------------------------------------------------------\n"
        . "-- $f\n"
        . "-- ------------------------------------------------------------------------------------------------------------\n"
        . rtrim($sql) . "\n\n";
}

file_put_contents($dir . '/actualizar.sql', $salida);
echo 'database/actualizar.sql: ' . number_format(strlen($salida)) . " bytes, " . count($fuentes) . " fuentes\n";
