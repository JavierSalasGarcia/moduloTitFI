[CmdletBinding()]
param(
    [string]$CredentialsPath = (Join-Path $PSScriptRoot '..\cred.txt'),
    [string]$SanitizedConfigPath = (Join-Path $PSScriptRoot '..\app\config.php'),
    [string]$BackupRoot = $(if ($env:TRAZAFI_BACKUP_ROOT) { $env:TRAZAFI_BACKUP_ROOT } else { Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'trazafi-backups' })
)

$ErrorActionPreference = 'Stop'

function Get-LabeledValue([string[]]$Lines, [string]$Label) {
    for ($i=0; $i -lt $Lines.Count; $i++) {
        if ($Lines[$i] -match ('^\s*' + [regex]::Escape($Label) + '\s*:\s*(.*)$')) {
            if ($Matches[1].Trim()) { return $Matches[1].Trim() }
            for ($j=$i+1; $j -lt $Lines.Count; $j++) { if ($Lines[$j].Trim()) { return $Lines[$j].Trim() } }
        }
    }
    throw "No se encontró $Label."
}
function New-FtpRequest([string]$Uri,[string]$Method,[pscredential]$Credential) {
    $r=[Net.FtpWebRequest]::Create($Uri);$r.Method=$Method;$r.Credentials=$Credential.GetNetworkCredential();$r.UseBinary=$true;$r.UsePassive=$true;$r.KeepAlive=$false;return $r
}
function Receive-Ftp([string]$Uri,[string]$Path,[pscredential]$Credential) {
    $r=New-FtpRequest $Uri ([Net.WebRequestMethods+Ftp]::DownloadFile) $Credential;$resp=$r.GetResponse()
    try{$input=$resp.GetResponseStream();$output=[IO.File]::Create($Path);try{$input.CopyTo($output)}finally{$output.Dispose();$input.Dispose()}}finally{$resp.Dispose()}
}
function Send-Ftp([string]$Path,[string]$Uri,[pscredential]$Credential) {
    $bytes=[IO.File]::ReadAllBytes($Path);$r=New-FtpRequest $Uri ([Net.WebRequestMethods+Ftp]::UploadFile) $Credential;$r.ContentLength=$bytes.Length
    $stream=$r.GetRequestStream();try{$stream.Write($bytes,0,$bytes.Length)}finally{$stream.Dispose()};$resp=$r.GetResponse();$resp.Dispose()
}
function Get-PhpValue([string]$Block,[string]$Key) {
    $m=[regex]::Match($Block,"(?m)^\s*'"+[regex]::Escape($Key)+"'\s*=>\s*'((?:\\.|[^'])*)'")
    if(-not $m.Success){throw "No se encontró $Key en la configuración remota."}
    return $m.Groups[1].Value.Replace("\'","'").Replace('\\','\')
}
function Quote-Php([string]$Value){return $Value.Replace('\','\\').Replace("'","\'")}

$lines=Get-Content -Encoding UTF8 -LiteralPath $CredentialsPath
$ftpBase=(Get-LabeledValue $lines 'SITIO').TrimEnd('/')
$user=Get-LabeledValue $lines 'USUARIO';$password=ConvertTo-SecureString (Get-LabeledValue $lines 'CONTRASEÑA') -AsPlainText -Force
$credential=[pscredential]::new($user,$password)
$backupFull=[IO.Path]::GetFullPath($BackupRoot);$project=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
if($backupFull.StartsWith($project.TrimEnd('\')+'\',[StringComparison]::OrdinalIgnoreCase)){throw 'BackupRoot debe estar fuera del proyecto.'}
$stamp=Get-Date -Format 'yyyyMMdd-HHmmss';$backup=Join-Path $backupFull "secrets-migration-$stamp";[IO.Directory]::CreateDirectory($backup)|Out-Null
$remoteConfig=Join-Path $backup 'config.php';Receive-Ftp "$ftpBase/app/config.php" $remoteConfig $credential
$text=[IO.File]::ReadAllText($remoteConfig);$db=[regex]::Match($text,"(?ms)^\s*'db'\s*=>\s*\[(.*?)^\s*\],").Groups[1].Value;$mail=[regex]::Match($text,"(?ms)^\s*'correo'\s*=>\s*\[(.*?)^\s*\],").Groups[1].Value
if(-not $db -or -not $mail){throw 'No se pudieron leer los bloques privados de producción.'}
$localConfig=Join-Path $backup 'config.local.php'
$content=@"
<?php
/** Configuración privada de producción. No versionar ni servir directamente. */
return [
    'db' => [
        'host' => '$(Quote-Php (Get-PhpValue $db 'host'))',
        'nombre' => '$(Quote-Php (Get-PhpValue $db 'nombre'))',
        'usuario' => '$(Quote-Php (Get-PhpValue $db 'usuario'))',
        'clave' => '$(Quote-Php (Get-PhpValue $db 'clave'))',
    ],
    'correo' => [
        'remitente' => '$(Quote-Php (Get-PhpValue $mail 'remitente'))',
        'nombre_remitente' => '$(Quote-Php (Get-PhpValue $mail 'nombre_remitente'))',
        'api_token' => '$(Quote-Php (Get-PhpValue $mail 'api_token'))',
    ],
];
"@
[IO.File]::WriteAllText($localConfig,$content,[Text.UTF8Encoding]::new($false))

# Primero instala los secretos privados; después reemplaza el archivo público de configuración.
Send-Ftp $localConfig "$ftpBase/app/config.local.php" $credential
Send-Ftp $SanitizedConfigPath "$ftpBase/app/config.php" $credential
foreach($pair in @(@($localConfig,'app/config.local.php'),@($SanitizedConfigPath,'app/config.php'))){$verify=Join-Path $backup ('verify-'+[IO.Path]::GetFileName($pair[0]));Receive-Ftp "$ftpBase/$($pair[1])" $verify $credential;if((Get-FileHash $pair[0]).Hash -ne (Get-FileHash $verify).Hash){throw "Falló la verificación de $($pair[1])."};Remove-Item -LiteralPath $verify -Force}
[pscustomobject]@{Migrated=$true;RemoteFiles=2;ProtectedBackup=$backup}
