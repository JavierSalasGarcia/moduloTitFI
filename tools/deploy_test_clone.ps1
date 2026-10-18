[CmdletBinding()]
param(
    [string]$ClonePath = 'C:\trazafi',
    [string]$ProjectPath = (Split-Path -Parent $PSScriptRoot),
    [string]$DatabaseCredentials = 'D:\sitios\trazafi\cred2.txt',
    [string]$HostingCredentials = (Join-Path (Split-Path -Parent $PSScriptRoot) 'cred.txt'),
    [string]$RemoteFolder = 'pruebas',
    [string]$PublicUrl = 'https://fingenieria.mx/pruebas'
)

$ErrorActionPreference = 'Stop'

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
    throw "No se encontro '$Label'."
}

function Read-ColonCredentials {
    param([string]$Path)
    $values = @{}
    foreach ($line in Get-Content -Encoding UTF8 -LiteralPath $Path) {
        if ($line -match '^\s*([^:]+?)\s*:\s*(.+?)\s*$') {
            $values[$Matches[1].Trim().ToLowerInvariant()] = $Matches[2]
        }
    }
    return $values
}

function ConvertTo-PhpSingleQuoted {
    param([string]$Value)
    return $Value.Replace('\', '\\').Replace("'", "\'")
}

function Get-RemoteUri {
    param([string]$RelativePath)
    $encoded = (($RelativePath -replace '\\', '/').Split('/') | Where-Object { $_ } | ForEach-Object { [uri]::EscapeDataString($_) }) -join '/'
    return "$script:FtpBase/$encoded"
}

function New-FtpDirectory {
    param([string]$RelativePath)
    if ([string]::IsNullOrWhiteSpace($RelativePath)) { return }
    $request = [Net.FtpWebRequest]::Create((Get-RemoteUri $RelativePath))
    $request.Method = [Net.WebRequestMethods+Ftp]::MakeDirectory
    $request.Credentials = $script:FtpCredential.GetNetworkCredential()
    $request.UsePassive = $true
    $request.KeepAlive = $false
    try {
        $response = $request.GetResponse()
        $response.Dispose()
    } catch [Net.WebException] {
        if (-not $_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne 550) { throw }
        $_.Exception.Response.Dispose()
    }
}

function Ensure-FtpPath {
    param([string]$RelativeDirectory)
    $current = ''
    foreach ($segment in (($RelativeDirectory -replace '\\', '/').Split('/') | Where-Object { $_ })) {
        $current = if ($current) { "$current/$segment" } else { $segment }
        if ($script:CreatedDirectories.Add($current)) { New-FtpDirectory $current }
    }
}

function Send-FtpFile {
    param([string]$LocalPath, [string]$RemoteRelativePath)
    $parent = Split-Path -Parent ($RemoteRelativePath -replace '/', '\')
    if ($parent) { Ensure-FtpPath ($parent -replace '\\', '/') }
    $request = [Net.FtpWebRequest]::Create((Get-RemoteUri $RemoteRelativePath))
    $request.Method = [Net.WebRequestMethods+Ftp]::UploadFile
    $request.Credentials = $script:FtpCredential.GetNetworkCredential()
    $request.UseBinary = $true
    $request.UsePassive = $true
    $request.KeepAlive = $false
    $bytes = [IO.File]::ReadAllBytes($LocalPath)
    $request.ContentLength = $bytes.Length
    $stream = $request.GetRequestStream()
    try { $stream.Write($bytes, 0, $bytes.Length) } finally { $stream.Dispose() }
    $response = $request.GetResponse()
    $response.Dispose()
}

foreach ($required in @($ClonePath, $ProjectPath, $DatabaseCredentials, $HostingCredentials)) {
    if (-not (Test-Path -LiteralPath $required)) { throw "No existe: $required" }
}
if ($RemoteFolder -notmatch '^[a-z0-9_-]+$') { throw 'La carpeta remota no es valida.' }

$hostingLines = Get-Content -Encoding UTF8 -LiteralPath $HostingCredentials
$script:FtpBase = (Get-LabeledValue $hostingLines 'SITIO').TrimEnd('/')
$ftpUser = Get-LabeledValue $hostingLines 'USUARIO'
$ftpPassword = Get-LabeledValue $hostingLines 'CONTRASEÑA'
$script:FtpCredential = [pscredential]::new($ftpUser, (ConvertTo-SecureString $ftpPassword -AsPlainText -Force))
$script:CreatedDirectories = [Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)

$database = Read-ColonCredentials $DatabaseCredentials
foreach ($key in @('database', 'mysqluser', 'password')) {
    if (-not $database.ContainsKey($key)) { throw "Falta '$key' en las credenciales de prueba." }
}

# Staging no debe reutilizar secretos de producción. El correo queda deshabilitado
# salvo que el operador proporcione credenciales exclusivas para pruebas.
$mailToken = [Environment]::GetEnvironmentVariable('TRAZAFI_STAGE_MAIL_TOKEN')
$mailSender = [Environment]::GetEnvironmentVariable('TRAZAFI_STAGE_MAIL_SENDER')
if ([string]::IsNullOrWhiteSpace($mailToken)) { $mailToken = '' }
if ([string]::IsNullOrWhiteSpace($mailSender)) { $mailSender = 'pruebas@fingenieria.mx' }

$temporaryDirectory = Join-Path ([IO.Path]::GetTempPath()) ('trazafi-stage-' + [guid]::NewGuid().ToString('N'))
[IO.Directory]::CreateDirectory($temporaryDirectory) | Out-Null
try {
    $stageConfig = @"
<?php
/** Configuracion privada del entorno de pruebas. */
return [
    'app' => [
        'debug' => false,
        'url_publica' => '$(ConvertTo-PhpSingleQuoted $PublicUrl)',
    ],
    'db' => [
        'host' => 'localhost',
        'nombre' => '$(ConvertTo-PhpSingleQuoted $database.database)',
        'usuario' => '$(ConvertTo-PhpSingleQuoted $database.mysqluser)',
        'clave' => '$(ConvertTo-PhpSingleQuoted $database.password)',
        'charset' => 'utf8mb4',
    ],
    'correo' => [
        'remitente' => '$(ConvertTo-PhpSingleQuoted $mailSender)',
        'nombre_remitente' => '[PRUEBAS] Traza FI',
        'api_token' => '$(ConvertTo-PhpSingleQuoted $mailToken)',
    ],
];
"@
    $stageConfigPath = Join-Path $temporaryDirectory 'config.local.php'
    [IO.File]::WriteAllText($stageConfigPath, $stageConfig, [Text.UTF8Encoding]::new($false))

    $stageRootHtaccess = @"
Options -Indexes
DirectoryIndex index.php
<IfModule mod_headers.c>
    Header always set X-Robots-Tag "noindex, nofollow, noarchive"
</IfModule>
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteCond %{THE_REQUEST} \s/+pruebas/public(?:[/?\x20]) [NC]
RewriteRule ^public(?:/(.*))?$ /pruebas/`$1 [R=301,L,NE]
RewriteRule ^fila/publico(?:/.*)?$ index.php [L,QSA]
RewriteRule ^assets/(.+)$ public/assets/`$1 [L]
RewriteRule ^ index.php [L,QSA]
</IfModule>
<FilesMatch "(?i)^(?:cred.*\.txt|api-.*\.json|.*\.(?:sql|key|pem|env|ini|log|bak|dist))$">
    Require all denied
</FilesMatch>
"@
    $stageRootHtaccessPath = Join-Path $temporaryDirectory 'root.htaccess'
    [IO.File]::WriteAllText($stageRootHtaccessPath, $stageRootHtaccess, [Text.UTF8Encoding]::new($false))

    $stagePublicHtaccess = @"
Options -Indexes
RewriteEngine On
RewriteCond %{THE_REQUEST} \s/+pruebas/public(?:[/?\x20]) [NC]
RewriteRule ^(.*)$ /pruebas/`$1 [R=301,L,NE]
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
"@
    $stagePublicHtaccessPath = Join-Path $temporaryDirectory 'public.htaccess'
    [IO.File]::WriteAllText($stagePublicHtaccessPath, $stagePublicHtaccess, [Text.UTF8Encoding]::new($false))

    $stageFilaHtaccess = @"
Options -Indexes
RewriteEngine On
RewriteRule ^ /pruebas/index.php [L,QSA]
"@
    $stageFilaHtaccessPath = Join-Path $temporaryDirectory 'fila-publico.htaccess'
    [IO.File]::WriteAllText($stageFilaHtaccessPath, $stageFilaHtaccess, [Text.UTF8Encoding]::new($false))

    # Habilita /pruebas en el sitio principal y publica la configuracion de URL
    # independiente sin tocar datos ni credenciales de produccion.
    foreach ($relative in @(
        '.htaccess',
        'app/config.php',
        'app/bootstrap.php',
        'app/Modules/Fila/FilaGateway.php',
        'app/Modules/Reuniones/Services/MinutasService.php'
    )) {
        Send-FtpFile (Join-Path $ProjectPath ($relative -replace '/', '\')) $relative
    }

    Ensure-FtpPath $RemoteFolder
    $files = [Collections.Generic.List[object]]::new()
    foreach ($rootName in @('app', 'public', 'lib', 'fila', 'Plan')) {
        $rootPath = Join-Path $ClonePath $rootName
        if (-not (Test-Path -LiteralPath $rootPath -PathType Container)) { continue }
        foreach ($file in Get-ChildItem -LiteralPath $rootPath -Recurse -Force -File) {
            $relative = $file.FullName.Substring($ClonePath.Length).TrimStart('\') -replace '\\', '/'
            if ($relative -in @('app/config.local.php', 'public/.htaccess')) { continue }
            if ($relative -like 'fila/publico/pruebas/*' -or $relative -like 'fila/tests/*') { continue }
            $files.Add([pscustomobject]@{ Local = $file.FullName; Remote = "$RemoteFolder/$relative" })
        }
    }
    $files.Add([pscustomobject]@{ Local = (Join-Path $ClonePath 'index.php'); Remote = "$RemoteFolder/index.php" })
    foreach ($relative in @('storage/.htaccess', 'storage/plan/.htaccess')) {
        $local = Join-Path $ClonePath ($relative -replace '/', '\')
        if (Test-Path -LiteralPath $local -PathType Leaf) {
            $files.Add([pscustomobject]@{ Local = $local; Remote = "$RemoteFolder/$relative" })
        }
    }

    $count = 0
    foreach ($file in $files) {
        Send-FtpFile $file.Local $file.Remote
        $count++
        if (($count % 50) -eq 0) { Write-Host "Archivos subidos: $count / $($files.Count)" }
    }
    Send-FtpFile $stageRootHtaccessPath "$RemoteFolder/.htaccess"
    Send-FtpFile $stagePublicHtaccessPath "$RemoteFolder/public/.htaccess"
    Send-FtpFile $stageFilaHtaccessPath "$RemoteFolder/fila/publico/.htaccess"
    Send-FtpFile $stageConfigPath "$RemoteFolder/app/config.local.php"

    [pscustomobject]@{
        Success = $true
        Url = $PublicUrl + '/'
        UploadedFiles = $count + 4
        Database = 'base de prueba configurada'
        Mail = 'activo; remitente marcado como PRUEBAS'
    }
} finally {
    if (Test-Path -LiteralPath $temporaryDirectory) {
        Get-ChildItem -LiteralPath $temporaryDirectory -File | Remove-Item -Force
        Remove-Item -LiteralPath $temporaryDirectory -Force
    }
}
