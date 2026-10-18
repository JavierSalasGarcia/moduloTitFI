[CmdletBinding()]
param(
    [string]$BackupPath = 'C:\Users\edigi\Downloads\plataforma_fi.sql',
    [string]$CredentialsPath = (Join-Path $PSScriptRoot '..\cred.txt'),
    [string]$SiteUrl = 'https://fingenieria.mx/public',
    [string]$DatabaseName = '',
    [string]$DatabaseUser = '',
    [string]$DatabasePassword = '',
    [string]$AdminEmail = 'admin@fingenieria.mx',
    [Parameter(Mandatory = $true)]
    [string]$AdminPassword,
    [string]$LocalSafetyBackupDirectory = (Join-Path $PSScriptRoot '..\database\production-backups')
)

$ErrorActionPreference = 'Stop'
function Get-Sha256Hex([byte[]]$Bytes) { $h=[Security.Cryptography.SHA256]::Create(); try { -join ($h.ComputeHash($Bytes) | ForEach-Object { $_.ToString('X2') }) } finally { $h.Dispose() } }
function Get-RandomHex([int]$Count) { $b=New-Object byte[] $Count; $r=[Security.Cryptography.RandomNumberGenerator]::Create(); try { $r.GetBytes($b) } finally { $r.Dispose() }; -join ($b | ForEach-Object { $_.ToString('X2') }) }

function Get-LabeledValue {
    param([string[]]$Lines, [string]$Label)

    for ($i = 0; $i -lt $Lines.Count; $i++) {
        if ($Lines[$i] -match ('^\s*' + [regex]::Escape($Label) + '\s*:\s*(.*)$')) {
            $inline = $Matches[1].Trim()
            if ($inline) { return $inline }
            for ($j = $i + 1; $j -lt $Lines.Count; $j++) {
                if ($Lines[$j].Trim()) { return $Lines[$j].Trim() }
            }
        }
    }
    throw "No se encontro '$Label' en el archivo de credenciales."
}

function New-FtpRequest {
    param([string]$Uri, [string]$Method, [pscredential]$Credential)

    $request = [System.Net.FtpWebRequest]::Create($Uri)
    $request.Method = $Method
    $request.Credentials = $Credential.GetNetworkCredential()
    $request.UseBinary = $true
    $request.UsePassive = $true
    $request.KeepAlive = $false
    return $request
}

function Send-FtpFile {
    param([string]$LocalPath, [string]$RemoteUri, [pscredential]$Credential)

    $request = New-FtpRequest $RemoteUri ([System.Net.WebRequestMethods+Ftp]::UploadFile) $Credential
    $bytes = [System.IO.File]::ReadAllBytes($LocalPath)
    $request.ContentLength = $bytes.Length
    $stream = $request.GetRequestStream()
    try { $stream.Write($bytes, 0, $bytes.Length) } finally { $stream.Dispose() }
    $response = $request.GetResponse()
    $response.Dispose()
}

function Receive-FtpFile {
    param([string]$RemoteUri, [string]$LocalPath, [pscredential]$Credential)

    $request = New-FtpRequest $RemoteUri ([System.Net.WebRequestMethods+Ftp]::DownloadFile) $Credential
    $response = $request.GetResponse()
    try {
        $input = $response.GetResponseStream()
        $output = [System.IO.File]::Create($LocalPath)
        try { $input.CopyTo($output) } finally { $output.Dispose(); $input.Dispose() }
    } finally { $response.Dispose() }
}

function Remove-FtpFile {
    param([string]$RemoteUri, [pscredential]$Credential)

    try {
        $request = New-FtpRequest $RemoteUri ([System.Net.WebRequestMethods+Ftp]::DeleteFile) $Credential
        $response = $request.GetResponse()
        $response.Dispose()
    } catch [System.Net.WebException] {
        if ($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 550) { return }
        throw
    }
}

if (-not (Test-Path -LiteralPath $BackupPath -PathType Leaf)) {
    throw "No existe el respaldo: $BackupPath"
}
if (-not (Test-Path -LiteralPath $CredentialsPath -PathType Leaf)) {
    throw "No existe el archivo de credenciales: $CredentialsPath"
}
if ($AdminPassword.Length -lt 10) {
    throw 'La contrasena del administrador debe tener al menos 10 caracteres.'
}

$credentialLines = Get-Content -Encoding UTF8 -LiteralPath $CredentialsPath
$ftpBase = (Get-LabeledValue $credentialLines 'SITIO').TrimEnd('/')
$ftpUser = Get-LabeledValue $credentialLines 'USUARIO'
$ftpPassword = Get-LabeledValue $credentialLines 'CONTRASEÑA'
$secureFtpPassword = ConvertTo-SecureString $ftpPassword -AsPlainText -Force
$ftpCredential = [pscredential]::new($ftpUser, $secureFtpPassword)
$effectiveDatabaseUser = if ($DatabaseUser) { $DatabaseUser } else { $ftpUser }
$effectiveDatabasePassword = if ($DatabasePassword) { $DatabasePassword } else { $ftpPassword }

$nonce = [guid]::NewGuid().ToString('N')
$deployToken = (Get-RandomHex 32).ToLowerInvariant()
$remoteInstallerName = "_db_install_$nonce.php"
$remoteDumpName = "_db_dump_$nonce.sql.gz"
$remoteBackupName = "_db_previous_$nonce.sql.gz"
$remotePublic = "$ftpBase/public"
$installerUrl = "$($SiteUrl.TrimEnd('/'))/$remoteInstallerName"
$templatePath = Join-Path $PSScriptRoot 'database_installer.php'
$temporaryDirectory = Join-Path ([IO.Path]::GetTempPath()) "plataforma-fi-$nonce"
$temporaryInstaller = Join-Path $temporaryDirectory $remoteInstallerName
$compressedDump = Join-Path $temporaryDirectory $remoteDumpName

[IO.Directory]::CreateDirectory($temporaryDirectory) | Out-Null
[IO.Directory]::CreateDirectory($LocalSafetyBackupDirectory) | Out-Null
$localSafetyBackup = Join-Path $LocalSafetyBackupDirectory ("production-before-{0}.sql.gz" -f (Get-Date -Format 'yyyyMMdd-HHmmss'))
$localConfigBackup = Join-Path $LocalSafetyBackupDirectory ("config.local-before-{0}.php" -f (Get-Date -Format 'yyyyMMdd-HHmmss'))
$removedDevelopmentConfig = $false

try {
    $input = [IO.File]::OpenRead((Resolve-Path -LiteralPath $BackupPath))
    $output = [IO.File]::Create($compressedDump)
    $gzip = [IO.Compression.GZipStream]::new($output, [IO.Compression.CompressionLevel]::Optimal)
    try { $input.CopyTo($gzip) } finally { $gzip.Dispose(); $output.Dispose(); $input.Dispose() }

    $dumpHash = (Get-FileHash -LiteralPath $compressedDump -Algorithm SHA256).Hash.ToLowerInvariant()
    $installer = [IO.File]::ReadAllText($templatePath)
    $installer = $installer.Replace('__DEPLOY_TOKEN__', $deployToken)
    $installer = $installer.Replace('__DUMP_FILE__', $remoteDumpName)
    $installer = $installer.Replace('__BACKUP_FILE__', $remoteBackupName)
    $installer = $installer.Replace('__DUMP_SHA256__', $dumpHash)
    [IO.File]::WriteAllText($temporaryInstaller, $installer, [Text.UTF8Encoding]::new($false))

    try {
        Receive-FtpFile "$ftpBase/app/config.local.php" $localConfigBackup $ftpCredential
        Remove-FtpFile "$ftpBase/app/config.local.php" $ftpCredential
        $removedDevelopmentConfig = $true
        Write-Host 'Se retiro del servidor la configuracion local de desarrollo.'
    } catch [System.Net.WebException] {
        if ($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -ne 550) { throw }
        if (Test-Path -LiteralPath $localConfigBackup) { Remove-Item -LiteralPath $localConfigBackup -Force }
    }

    Write-Host 'Subiendo instalador temporal y respaldo...'
    Send-FtpFile $compressedDump "$remotePublic/$remoteDumpName" $ftpCredential
    Send-FtpFile $temporaryInstaller "$remotePublic/$remoteInstallerName" $ftpCredential

    $headers = @{
        'X-Deploy-Token' = $deployToken
        'X-Database-Name' = $DatabaseName
        'X-Database-User' = $effectiveDatabaseUser
        'X-Database-Password' = $effectiveDatabasePassword
    }
    Write-Host 'Creando copia de seguridad de la base actual...'
    $backupResult = Invoke-RestMethod -Method Post -Uri "${installerUrl}?action=backup" -Headers $headers -TimeoutSec 600
    if (-not $backupResult.ok) { throw 'El servidor no pudo crear la copia de seguridad previa.' }
    Receive-FtpFile "$remotePublic/$remoteBackupName" $localSafetyBackup $ftpCredential
    if ((Get-Item -LiteralPath $localSafetyBackup).Length -eq 0) { throw 'La copia de seguridad previa esta vacia.' }

    Write-Host 'Importando el respaldo y creando el administrador...'
    $body = @{ admin_email = $AdminEmail; admin_password = $AdminPassword } | ConvertTo-Json -Compress
    $installResult = Invoke-RestMethod -Method Post -Uri "${installerUrl}?action=install" -Headers $headers -ContentType 'application/json' -Body $body -TimeoutSec 1200
    if (-not $installResult.ok) { throw 'El servidor no pudo completar la importacion.' }

    Send-FtpFile (Join-Path $PSScriptRoot '..\app\config.php') "$ftpBase/app/config.php" $ftpCredential

    [pscustomobject]@{
        Success = $true
        ImportedStatements = $installResult.install.statements
        Tables = $installResult.install.tables
        AdminEmail = $AdminEmail
        SafetyBackup = $localSafetyBackup
        RemovedDevelopmentConfig = $removedDevelopmentConfig
        DevelopmentConfigBackup = if ($removedDevelopmentConfig) { $localConfigBackup } else { $null }
    }
} finally {
    Write-Host 'Eliminando archivos temporales del servidor...'
    Remove-FtpFile "$remotePublic/$remoteInstallerName" $ftpCredential
    Remove-FtpFile "$remotePublic/$remoteDumpName" $ftpCredential
    Remove-FtpFile "$remotePublic/$remoteBackupName" $ftpCredential
    if (Test-Path -LiteralPath $temporaryDirectory) {
        Remove-Item -LiteralPath $temporaryDirectory -Recurse -Force
    }
}
