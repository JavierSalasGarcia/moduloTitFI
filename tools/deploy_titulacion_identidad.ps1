[CmdletBinding()]
param(
    [string]$CredentialsPath = (Join-Path $PSScriptRoot '..\cred.txt'),
    [string]$SiteUrl = 'https://fingenieria.mx',
    # Los archivos de app/ y public/ que cambiaron desde este commit (el último que se subió a producción).
    [string]$DesdeCommit = '6cca120',
    [string[]]$Migraciones = @('025_historico_sistema_anterior.sql', '026_directorio_profesores.sql', '027_identidad_estable.sql', '028_historial_titulos.sql'),
    [switch]$SoloBaseDeDatos,
    [switch]$SoloCodigo,
    [switch]$DryRun
)

# Sube a producción lo de «identidad estable / directorio de profesores / historial de constancias»:
#  1) Base de datos: aplica las migraciones (aditivas y repetibles) mediante un instalador temporal protegido con token, que se borra
#     al terminar, y antes copia las tablas que se modifican a zz_bak_<fecha>_<tabla> para poder volver atrás.
#  2) Código: sube los archivos cambiados con tools/deploy_site_files.ps1 (respalda cada archivo remoto antes de reemplazarlo).
# Primero la base de datos: el código nuevo necesita las columnas nuevas, y el código viejo funciona con el esquema nuevo.

$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))

function Get-LabeledValue([string[]]$Lines, [string]$Label) {
    for ($i = 0; $i -lt $Lines.Count; $i++) {
        if ($Lines[$i] -match ('^\s*' + [regex]::Escape($Label) + '\s*:\s*(.*)$')) {
            if ($Matches[1].Trim()) { return $Matches[1].Trim() }
            for ($j = $i + 1; $j -lt $Lines.Count; $j++) { if ($Lines[$j].Trim()) { return $Lines[$j].Trim() } }
        }
    }
    throw "Falta $Label."
}
function Request([string]$Uri, [string]$Method, [pscredential]$Credential) {
    $r = [Net.FtpWebRequest]::Create($Uri); $r.Method = $Method; $r.Credentials = $Credential.GetNetworkCredential()
    $r.UseBinary = $true; $r.UsePassive = $true; $r.KeepAlive = $false; return $r
}
function Send([string]$Path, [string]$Uri, [pscredential]$Credential) {
    $b = [IO.File]::ReadAllBytes($Path); $r = Request $Uri ([Net.WebRequestMethods+Ftp]::UploadFile) $Credential
    $r.ContentLength = $b.Length; $s = $r.GetRequestStream(); try { $s.Write($b, 0, $b.Length) } finally { $s.Dispose() }
    $p = $r.GetResponse(); $p.Dispose()
}
function Receive([string]$Uri, [string]$Path, [pscredential]$Credential) {
    $r = Request $Uri ([Net.WebRequestMethods+Ftp]::DownloadFile) $Credential; $p = $r.GetResponse()
    try { $in = $p.GetResponseStream(); $out = [IO.File]::Create($Path); try { $in.CopyTo($out) } finally { $out.Dispose(); $in.Dispose() } } finally { $p.Dispose() }
}
function Remove-Remote([string]$Uri, [pscredential]$Credential) {
    try { $r = Request $Uri ([Net.WebRequestMethods+Ftp]::DeleteFile) $Credential; $p = $r.GetResponse(); $p.Dispose() }
    catch [Net.WebException] { if (-not $_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne 550) { throw } }
}

$archivos = @(git -C $root diff --name-only $DesdeCommit HEAD -- app public | Where-Object { $_ })
$sql = ($Migraciones | ForEach-Object { [IO.File]::ReadAllText((Join-Path $root "database\migrations\$_"), [Text.Encoding]::UTF8) }) -join "`n"

if ($DryRun) {
    Write-Output "Migraciones ($($Migraciones.Count), $([math]::Round($sql.Length/1024)) KB): $($Migraciones -join ', ')"
    Write-Output "Archivos de código ($($archivos.Count)):"; $archivos | ForEach-Object { "  $_" }
    return
}

$lines = Get-Content -Encoding UTF8 -LiteralPath $CredentialsPath
$base = (Get-LabeledValue $lines 'SITIO').TrimEnd('/')
$user = Get-LabeledValue $lines 'USUARIO'
$cred = [pscredential]::new($user, (ConvertTo-SecureString (Get-LabeledValue $lines ('CONTRASE' + [char]0x00D1 + 'A')) -AsPlainText -Force))

if (-not $SoloCodigo) {
    $nonce = [guid]::NewGuid().ToString('N')
    $token = [guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N')
    $name = "_titulacion_migration_$nonce.php"
    $tempDir = Join-Path ([IO.Path]::GetTempPath()) "trazafi-titulacion-$nonce"
    [IO.Directory]::CreateDirectory($tempDir) | Out-Null
    $installer = Join-Path $tempDir $name
    $originalHtaccess = Join-Path $tempDir 'assets.htaccess.original'
    $temporaryHtaccess = Join-Path $tempDir 'assets.htaccess.temporary'
    $sql64 = [Convert]::ToBase64String([Text.UTF8Encoding]::new($false).GetBytes($sql))
    $php = @'
<?php
declare(strict_types=1);
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!hash_equals('__TOKEN__', (string)($_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? ''))) { http_response_code(404); exit('{}'); }
@set_time_limit(280);
try {
  $config = require dirname(__DIR__, 2) . '/app/config.php'; $db = $config['db'];
  $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['nombre'] . ';charset=' . $db['charset'], $db['usuario'], $db['clave'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  // 1) Requisitos: lo que ya debe existir en producción.
  $faltan = [];
  foreach (['usuarios', 'programas', 'programa_profesores', 'tit_historico', 'tit_tramites', 'tit_constancias', 'cuenta_verificacion', 'usuario_roles'] as $t) {
    if (!(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($t))->fetchColumn()) { $faltan[] = $t; }
  }
  if ($faltan) { http_response_code(409); echo json_encode(['ok' => false, 'faltan_tablas' => $faltan]); exit; }
  $antes = [];
  foreach (['tit_historico', 'tit_constancias', 'usuarios', 'programa_profesores'] as $t) { $antes[$t] = (int)$pdo->query("SELECT COUNT(*) FROM `$t`")->fetchColumn(); }
  // 2) Copia de seguridad de las tablas que se modifican.
  $sello = date('Ymd_His'); $copias = [];
  foreach (['tit_historico', 'tit_constancias', 'usuarios', 'programa_profesores', 'eventos', 'frm_formularios', 'frm_respuestas'] as $t) {
    if (!(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = " . $pdo->quote($t))->fetchColumn()) { continue; }
    $copia = "zz_bak_{$sello}_$t"; $pdo->exec("CREATE TABLE `$copia` AS SELECT * FROM `$t`"); $copias[] = $copia;
  }
  // 3) Migraciones.
  $sql = base64_decode('__SQL64__', true); if ($sql === false) throw new RuntimeException('Migracion invalida.');
  $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql, -1, PREG_SPLIT_NO_EMPTY); $applied = 0;
  foreach ($statements as $statement) { if (trim(preg_replace('/^--.*$/m', '', $statement)) !== '') { $pdo->exec($statement); $applied++; } }
  // 4) Verificación.
  $q = fn(string $s) => (int)$pdo->query($s)->fetchColumn();
  echo json_encode(['ok' => true, 'statements' => $applied, 'copias' => $copias, 'antes' => $antes, 'despues' => [
    'tit_historico' => $q('SELECT COUNT(*) FROM tit_historico'),
    'historico_sistema_anterior' => $q("SELECT COUNT(*) FROM tit_historico WHERE hoja = 'SISTEMA ANTERIOR'"),
    'profesores' => $q('SELECT COUNT(*) FROM profesores'), 'profesor_correos' => $q('SELECT COUNT(*) FROM profesor_correos'),
    'profesor_adscripciones' => $q('SELECT COUNT(*) FROM profesor_adscripciones'), 'profesores_con_cuenta' => $q('SELECT COUNT(*) FROM profesores WHERE usuario_id IS NOT NULL'),
    'usuario_correos' => $q('SELECT COUNT(*) FROM usuario_correos'), 'usuarios' => $q('SELECT COUNT(*) FROM usuarios'),
    'constancias_con_persona' => $q('SELECT COUNT(*) FROM tit_constancias WHERE usuario_id IS NOT NULL OR profesor_id IS NOT NULL'), 'constancias' => $q('SELECT COUNT(*) FROM tit_constancias'),
    'columna_titulo_origen' => $q("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'tit_historico' AND column_name = 'titulo_origen'"),
  ]]);
} catch (Throwable $e) { error_log('Titulacion migration: ' . $e->getMessage()); http_response_code(500); echo json_encode(['ok' => false, 'error' => $e->getMessage()]); }
'@
    $php = $php.Replace('__TOKEN__', $token).Replace('__SQL64__', $sql64)
    [IO.File]::WriteAllText($installer, $php, [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText($temporaryHtaccess, @"
Options -Indexes
<FilesMatch "(?i)\.(?:php[0-9]?|phtml|phar)$">
    Require all denied
</FilesMatch>
<Files "$name">
    Require all granted
</Files>
"@, [Text.UTF8Encoding]::new($false))

    $htaccessReplaced = $false
    try {
        Receive "$base/public/assets/.htaccess" $originalHtaccess $cred
        Send $temporaryHtaccess "$base/public/assets/.htaccess" $cred
        $htaccessReplaced = $true
        Send $installer "$base/public/assets/$name" $cred
        try {
            $result = Invoke-RestMethod -Method Post -Uri "$SiteUrl/assets/$name" -Headers @{ 'X-Migration-Token' = $token } -TimeoutSec 290
        } catch {
            $detalle = ''
            if ($_.Exception.Response) { try { $detalle = (New-Object IO.StreamReader($_.Exception.Response.GetResponseStream())).ReadToEnd() } catch { } }
            throw "La migración falló: $($_.Exception.Message) $detalle"
        }
        if (-not $result.ok) { throw 'La migración no se verificó.' }
        Write-Output '--- Base de datos ---'
        $result | ConvertTo-Json -Depth 6
    } finally {
        Remove-Remote "$base/public/assets/$name" $cred
        if ($htaccessReplaced -and (Test-Path $originalHtaccess)) { Send $originalHtaccess "$base/public/assets/.htaccess" $cred }
        if (Test-Path $tempDir) { Remove-Item -LiteralPath $tempDir -Recurse -Force }
    }
}

if (-not $SoloBaseDeDatos) {
    $manifiesto = Join-Path ([IO.Path]::GetTempPath()) ("trazafi-manifiesto-" + [guid]::NewGuid().ToString('N') + '.json')
    try {
        [IO.File]::WriteAllText($manifiesto, (ConvertTo-Json -InputObject @($archivos)), [Text.UTF8Encoding]::new($false))
        Write-Output '--- Código ---'
        & (Join-Path $PSScriptRoot 'deploy_site_files.ps1') -CredentialsPath $CredentialsPath -ManifestPath $manifiesto
    } finally { if (Test-Path $manifiesto) { Remove-Item -LiteralPath $manifiesto -Force } }
}
