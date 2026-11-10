<?php
declare(strict_types=1);

/**
 * Impide que entren al repositorio datos de cuentas o de personas (docs/DATOS_SENSIBLES.md). El repositorio es público:
 * puede tener la estructura de las tablas, nunca su contenido personal.
 *
 *   php tools/verificar_datos_sensibles.php            Revisa todo lo versionado.
 *   php tools/verificar_datos_sensibles.php --staged   Revisa solo lo que está por entrar al commit (lo usa el hook pre-commit).
 *   php tools/verificar_datos_sensibles.php --instalar-hook   Instala el hook en .git/hooks/pre-commit.
 *
 * Sale con código 1 si encuentra algo. No imprime los datos encontrados, solo el archivo, la línea y el motivo.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(dirname(__DIR__));

if (in_array('--instalar-hook', $argv, true)) {
    $hook = ".git/hooks/pre-commit";
    file_put_contents($hook, "#!/bin/sh\n# Instalado por tools/verificar_datos_sensibles.php\nphp tools/verificar_datos_sensibles.php --staged || {\n  echo 'Commit detenido: hay datos sensibles. Revisa docs/DATOS_SENSIBLES.md.' >&2\n  exit 1\n}\n");
    @chmod($hook, 0755);
    echo "Hook instalado en $hook\n";
    exit(0);
}

$staged = in_array('--staged', $argv, true);
$lista = $staged ? 'git diff --cached --name-only --diff-filter=ACMR -z' : 'git ls-files -z';
$archivos = array_filter(explode("\0", (string) shell_exec($lista)));

/** Tablas cuyo contenido es de personas o de cuentas: solo se admite su estructura. */
const TABLAS_PERSONALES = 'usuarios|usuario_correos|usuario_roles|usuarios_biometria|usuarios_codigos_otp|profesores|profesor_correos|profesor_adscripciones'
    . '|tit_historico|tit_historico_protocolos|tit_constancias|tit_sinodales|tit_firmas_externas|tit_comision_miembros|tit_tramites'
    . '|tit_solicitudes_historicas|tit_protocolos_recibidos|tit_articulo5|egresado_solicitudes|egresado_documentos|frm_respuestas|cuenta_verificacion'
    . '|tmp_[a-z_]+';

$hallazgos = [];
$anotar = static function (string $archivo, int $linea, string $motivo) use (&$hallazgos): void {
    $hallazgos[] = sprintf('%s:%d  %s', $archivo, $linea, $motivo);
};

foreach ($archivos as $archivo) {
    $bajo = strtolower(str_replace('\\', '/', $archivo));
    // 1. Rutas que nunca se versionan.
    if (preg_match('#^storage/(?!(?:[^/]+/)*\.htaccess$)#', $bajo)) { $anotar($archivo, 0, 'archivo dentro de storage/ (documentos, firmas, bitácoras)'); continue; }
    if (preg_match('#^database/(datos_privados|production-backups)/#', $bajo)) { $anotar($archivo, 0, 'carpeta de datos privados o respaldos'); continue; }
    if (preg_match('#(^|/)(cred[^/]*\.txt|api-[^/]*\.json|config\.local\.php|[^/]+\.bak([-_.][^/]*)?|[^/]*\.(sqlite|db|pem|key|pfx|p12))$#', $bajo)) { $anotar($archivo, 0, 'credenciales, llaves o respaldos'); continue; }
    if (preg_match('#^\.codex-remote-attachments/|^\.claude/settings\.local\.json$#', $bajo)) { $anotar($archivo, 0, 'archivos locales de herramientas (pueden traer fotos o rutas personales)'); continue; }
    if (!is_file($archivo) || filesize($archivo) > 8 * 1048576) { continue; }

    $esSql = str_ends_with($bajo, '.sql');
    $esDato = $esSql || preg_match('#\.(csv|tsv|json|ndjson|xml|ya?ml)$#', $bajo);
    $esCodigo = (bool) preg_match('#\.(php|py|js|cjs|mjs|ts|sh|ps1|md)$#', $bajo);
    if (!$esDato && !$esCodigo) { continue; }
    $prueba = (bool) preg_match('#(^|/)(tests?|pruebas)/|(^|/)tools/(test_|prueba_)|fixture#', $bajo);

    $correos = 0;
    $enInsert = false;
    foreach (file($archivo, FILE_IGNORE_NEW_LINES) ?: [] as $i => $linea) {
        $n = $i + 1;
        // 2. Hashes de contraseña (bcrypt/argon) fuera de pruebas.
        if (!$prueba && preg_match('/\$2[aby]\$\d{2}\$[.\/A-Za-z0-9]{50,}|\$argon2(id|i|d)\$v=\d+\$/', $linea) && !preg_match('#^app/modules/alumnos/alumnoscontroller\.php$#', $bajo)) {
            $anotar($archivo, $n, 'hash de contraseña');
        }
        // 3. Llaves y claves de servicios.
        if (preg_match('/-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|\bAIza[0-9A-Za-z_\-]{30,}|\bsk-[A-Za-z0-9_\-]{30,}|\bghp_[A-Za-z0-9]{30,}/', $linea)) {
            $anotar($archivo, $n, 'llave privada o clave de servicio');
        }
        if (!$esSql) {
            if ($esDato) { $correos += preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', $linea); }
            continue;
        }
        // 4. SQL: carga de filas en tablas de personas (INSERT … VALUES). INSERT … SELECT entre tablas no trae datos.
        if (preg_match('/^\s*(INSERT|REPLACE)\b.*\bINTO\s+`?(' . TABLAS_PERSONALES . ')`?\b/i', $linea)) {
            $enInsert = true;
            if (preg_match('/\bVALUES\b/i', $linea)) { $anotar($archivo, $n, 'carga de datos en una tabla de personas o cuentas'); $enInsert = false; }
        } elseif ($enInsert) {
            if (preg_match('/^\s*\(|\bVALUES\b/i', $linea)) { $anotar($archivo, $n, 'carga de datos en una tabla de personas o cuentas'); $enInsert = false; }
            elseif (preg_match('/\bSELECT\b|;\s*$/i', $linea)) { $enInsert = false; }
        }
        // 5. RFC con homoclave en filas de datos.
        if (preg_match('/^\s*\(.*\'[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}\'/u', $linea)) { $anotar($archivo, $n, 'RFC en una fila de datos'); }
        $correos += preg_match_all('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[a-z]{2,}/', $linea);
    }
    // 6. Listados de correos en archivos de datos.
    if ($esDato && !$prueba && $correos > 5) { $anotar($archivo, 0, "contiene $correos direcciones de correo"); }
}

if ($hallazgos) {
    $hallazgos = array_values(array_unique($hallazgos));
    echo implode("\n", array_slice($hallazgos, 0, 60)), "\n";
    if (count($hallazgos) > 60) { echo '… y ' . (count($hallazgos) - 60) . " más\n"; }
    echo "\n" . count($hallazgos) . " hallazgo(s). El repositorio es público: mueve los datos a database/datos_privados/ (ver docs/DATOS_SENSIBLES.md).\n";
    exit(1);
}
echo 'Sin datos sensibles en ' . count($archivos) . ' archivo(s)' . ($staged ? ' por confirmar' : ' versionados') . ".\n";
