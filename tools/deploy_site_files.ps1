[CmdletBinding()]
param(
    [string]$CredentialsPath = (Join-Path $PSScriptRoot '..\cred.txt'),
    [string]$ManifestPath = '',
    [string]$BackupRoot = $(if ($env:TRAZAFI_BACKUP_ROOT) { $env:TRAZAFI_BACKUP_ROOT } else { Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'trazafi-backups' }),
    [ValidateRange(5, 200)][int]$KeepBackupSets = 30
)

$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$backupRootFull = [IO.Path]::GetFullPath($BackupRoot)
if ($backupRootFull.TrimEnd('\') -eq $projectRoot.TrimEnd('\') -or $backupRootFull.StartsWith($projectRoot.TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)) {
    throw 'BackupRoot debe estar fuera del proyecto y del webroot.'
}
[IO.Directory]::CreateDirectory($backupRootFull) | Out-Null
$paths = @(
    'app/Modules/Portal/PortalController.php',
    'app/Modules/Portal/AsistenteConsulta.php',
    'app/Modules/Portal/module.php',
    'app/Modules/Portal/views/index.php',
    'app/Views/layouts/app.php',
    'app/Modules/Ajustes/views/index.php',
    'app/Modules/Ajustes/AjustesController.php',
    'app/Modules/Reuniones/Services/VisorReunionesService.php',
    'public/assets/css/app.css',
    'database/schema_portal_consultas.sql'
)

function Get-LabeledValue {
    param([string[]]$Lines, [string]$Label)
    for ($i = 0; $i -lt $Lines.Count; $i++) {
        if ($Lines[$i] -match ('^\s*' + [regex]::Escape($Label) + '\s*:\s*(.*)$')) {
            if ($Matches[1].Trim()) { return $Matches[1].Trim() }
            for ($j = $i + 1; $j -lt $Lines.Count; $j++) {
                if ($Lines[$j].Trim()) { return $Lines[$j].Trim() }
            }
        }
    }
    throw "No se encontro '$Label' en el archivo de credenciales."
}

function New-FtpRequest {
    param([string]$Uri, [string]$Method, [pscredential]$Credential)
    $request = [Net.FtpWebRequest]::Create($Uri)
    $request.Method = $Method
    $request.Credentials = $Credential.GetNetworkCredential()
    $request.UseBinary = $true
    $request.UsePassive = $true
    $request.KeepAlive = $false
    return $request
}

function Send-FtpFile {
    param([string]$LocalPath, [string]$RemoteUri, [pscredential]$Credential)
    $request = New-FtpRequest $RemoteUri ([Net.WebRequestMethods+Ftp]::UploadFile) $Credential
    $bytes = [IO.File]::ReadAllBytes($LocalPath)
    $request.ContentLength = $bytes.Length
    $stream = $request.GetRequestStream()
    try { $stream.Write($bytes, 0, $bytes.Length) } finally { $stream.Dispose() }
    $response = $request.GetResponse()
    $response.Dispose()
}

function Ensure-FtpDirectory {
    param([string]$BaseUri, [string]$RelativeDirectory, [pscredential]$Credential)
    if ([string]::IsNullOrWhiteSpace($RelativeDirectory) -or $RelativeDirectory -eq '.') { return }
    $current = $BaseUri.TrimEnd('/')
    foreach ($segment in ($RelativeDirectory -split '/')) {
        if ([string]::IsNullOrWhiteSpace($segment)) { continue }
        $current += '/' + $segment
        try {
            $request = New-FtpRequest $current ([Net.WebRequestMethods+Ftp]::MakeDirectory) $Credential
            $response = $request.GetResponse()
            $response.Dispose()
        } catch [Net.WebException] {
            # Los servidores FTP responden 550 cuando el directorio ya existe.
            # Un fallo real se detectará inmediatamente al subir/verificar el archivo.
            if (-not $_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne 550) { throw }
        }
    }
}

function Receive-FtpFileIfPresent {
    param([string]$RemoteUri, [string]$LocalPath, [pscredential]$Credential)
    try {
        $request = New-FtpRequest $RemoteUri ([Net.WebRequestMethods+Ftp]::DownloadFile) $Credential
        $response = $request.GetResponse()
        try {
            $input = $response.GetResponseStream()
            $output = [IO.File]::Create($LocalPath)
            try { $input.CopyTo($output) } finally { $output.Dispose(); $input.Dispose() }
        } finally { $response.Dispose() }
        return $true
    } catch [Net.WebException] {
        if ($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 550) { return $false }
        throw
    }
}

$lines = Get-Content -Encoding UTF8 -LiteralPath $CredentialsPath
if ($ManifestPath) { $paths = @(Get-Content -Raw -LiteralPath $ManifestPath | ConvertFrom-Json | ForEach-Object { $_ }) }
foreach ($relativePath in $paths) {
    $allowedApp = $relativePath -match '^(app|public)/[A-Za-z0-9_./-]+$'
    $allowedPlanProposal = $relativePath -eq 'Plan/preliminar_fi.json'
    $allowedFilaAsset = $relativePath -match '^fila/publico/(lib|vistas|estatico)/[A-Za-z0-9_./-]+$'
    $allowedFilaEntry = $relativePath -in @('fila/publico/index.php','fila/publico/api.php')
    if ((-not $allowedApp -and -not $allowedPlanProposal -and -not $allowedFilaAsset -and -not $allowedFilaEntry -and $relativePath -ne 'database/schema_portal_consultas.sql') -or $relativePath.Contains('..') -or $relativePath -eq 'app/config.php') {
        throw "Ruta no autorizada en el manifiesto: $relativePath"
    }
    if (-not (Test-Path -LiteralPath (Join-Path $projectRoot $relativePath) -PathType Leaf)) { throw "Falta $relativePath" }
}
$ftpBase = (Get-LabeledValue $lines 'SITIO').TrimEnd('/')
$ftpUser = Get-LabeledValue $lines 'USUARIO'
$ftpPassword = ConvertTo-SecureString (Get-LabeledValue $lines 'CONTRASEÑA') -AsPlainText -Force
$credential = [pscredential]::new($ftpUser, $ftpPassword)
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupDirectory = Join-Path $backupRootFull "site-update-$stamp"
[IO.Directory]::CreateDirectory($backupDirectory) | Out-Null
$backedUp = 0

foreach ($relativePath in $paths) {
    $localPath = Join-Path $projectRoot ($relativePath -replace '/', '\')
    if (-not (Test-Path -LiteralPath $localPath -PathType Leaf)) {
        throw "No existe el archivo local: $relativePath"
    }
    $backupPath = Join-Path $backupDirectory ($relativePath -replace '/', '__')
    if (Receive-FtpFileIfPresent "$ftpBase/$relativePath" $backupPath $credential) {
        $backedUp++
    }
}

# Back up every existing file before changing production.
Write-Output "Respaldo listo: $backedUp archivos en $backupDirectory"
foreach ($relativePath in $paths) {
    $localPath = Join-Path $projectRoot $relativePath
    $remoteParent = [IO.Path]::GetDirectoryName($relativePath).Replace('\','/')
    Ensure-FtpDirectory $ftpBase $remoteParent $credential
    Send-FtpFile $localPath "$ftpBase/$relativePath" $credential
    $verifyPath = Join-Path $backupDirectory 'upload-verification.tmp'
    if (-not (Receive-FtpFileIfPresent "$ftpBase/$relativePath" $verifyPath $credential)) { throw "No se pudo verificar $relativePath" }
    if ((Get-FileHash -LiteralPath $localPath).Hash -ne (Get-FileHash -LiteralPath $verifyPath).Hash) { throw "Contenido distinto en $relativePath" }
    Write-Output "Publicado y verificado: $relativePath"
}
if (Test-Path -LiteralPath $verifyPath) { Remove-Item -LiteralPath $verifyPath }

# Retención acotada: sólo directorios site-update creados dentro del BackupRoot validado.
$sets = @(Get-ChildItem -LiteralPath $backupRootFull -Directory -Filter 'site-update-*' | Sort-Object Name -Descending)
foreach ($old in ($sets | Select-Object -Skip $KeepBackupSets)) {
    $resolved = [IO.Path]::GetFullPath($old.FullName)
    if (-not $resolved.StartsWith($backupRootFull.TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)) {
        throw "Ruta de respaldo fuera del destino autorizado: $resolved"
    }
    Remove-Item -LiteralPath $resolved -Recurse -Force
    Write-Output "Respaldo vencido eliminado por retencion: $resolved"
}

[pscustomobject]@{
    Uploaded = $paths.Count
    BackedUp = $backedUp
    BackupDirectory = $backupDirectory
}
