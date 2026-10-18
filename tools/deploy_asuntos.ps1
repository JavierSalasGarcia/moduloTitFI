[CmdletBinding()]
param([switch]$DryRun)
$ErrorActionPreference='Stop'

function Get-Sha256Hex([byte[]]$Bytes) {
    $hash=[Security.Cryptography.SHA256]::Create()
    try { return -join ($hash.ComputeHash($Bytes) | ForEach-Object { $_.ToString('X2') }) }
    finally { $hash.Dispose() }
}
function Read-Label($lines,$label) {
    for($i=0;$i -lt $lines.Count;$i++) {
        if($lines[$i] -match ('^\s*'+[regex]::Escape($label)+'\s*:\s*(.*)$')) {
            if($Matches[1].Trim()){return $Matches[1].Trim()}
            for($j=$i+1;$j -lt $lines.Count;$j++){if($lines[$j].Trim()){return $lines[$j].Trim()}}
        }
    }
    throw "Falta $label"
}

$taskRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$taskLines=Get-Content -Encoding UTF8 -LiteralPath (Join-Path $taskRoot 'cred.txt')
$taskFtp=(Read-Label $taskLines 'SITIO').TrimEnd('/')
$taskCredential=[Net.NetworkCredential]::new((Read-Label $taskLines 'USUARIO'),(Read-Label $taskLines 'CONTRASEÑA'))

function Request-Ftp($relative,$method) {
    $request=[Net.FtpWebRequest]::Create("$taskFtp/$relative")
    $request.Credentials=$taskCredential
    $request.Method=$method
    $request.UseBinary=$true; $request.UsePassive=$true; $request.KeepAlive=$false
    $request.Timeout=30000; $request.ReadWriteTimeout=30000
    return $request
}
function Fetch-Bytes($relative) {
    try {
        $response=(Request-Ftp $relative ([Net.WebRequestMethods+Ftp]::DownloadFile)).GetResponse()
        try {$stream=$response.GetResponseStream();$memory=[IO.MemoryStream]::new();$stream.CopyTo($memory);return ,$memory.ToArray()}
        finally {$response.Dispose()}
    } catch [Net.WebException] {
        if($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 550){return $null}
        throw
    }
}
function Upload-Bytes($relative,[byte[]]$bytes) {
    $request=Request-Ftp $relative ([Net.WebRequestMethods+Ftp]::UploadFile)
    $request.ContentLength=$bytes.Length
    $stream=$request.GetRequestStream()
    try {$stream.Write($bytes,0,$bytes.Length)} finally {$stream.Dispose()}
    $request.GetResponse().Dispose()
    $verify=Fetch-Bytes $relative
    if(!$verify -or [Convert]::ToBase64String($verify) -ne [Convert]::ToBase64String($bytes)){throw "Verificación fallida: $relative"}
}
function Ensure-Directory($relative) {
    try {(Request-Ftp $relative ([Net.WebRequestMethods+Ftp]::MakeDirectory)).GetResponse().Dispose()}
    catch [Net.WebException] {if(!$_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne 550){throw}}
}

$passive=@(
    'database/migrations/007_asuntos.sql',
    'database/migrations/008_generalizar_asuntos.sql',
    'app/Modules/Asuntos/AsuntosAccess.php',
    'app/Modules/Asuntos/AsuntosController.php',
    'app/Modules/Asuntos/views/abrir.php',
    'app/Modules/Asuntos/views/expediente.php',
    'app/Modules/Asuntos/LEEME.md'
)
$active=@(
    'app/Core/Auth.php',
    'app/Modules/Auth/AuthController.php',
    'app/Modules/Auth/views/login.php',
    'app/config.php',
    'app/Modules/Asuntos/module.php'
)
$files=@($passive+$active)
if($DryRun){$files;return}

$stamp=Get-Date -Format 'yyyyMMdd-HHmmss'
$backup=Join-Path $taskRoot "database/production-backups/asuntos-$stamp"
[IO.Directory]::CreateDirectory($backup)|Out-Null
foreach($file in $files) {
    $bytes=Fetch-Bytes $file
    if($null -ne $bytes){[IO.File]::WriteAllBytes((Join-Path $backup $file.Replace('/','__')),$bytes)}
}
foreach($dir in @('database/migrations','app/Modules/Asuntos','app/Modules/Asuntos/views','storage/sa')){Ensure-Directory $dir}
foreach($file in $passive){Upload-Bytes $file ([IO.File]::ReadAllBytes((Join-Path $taskRoot $file)))}

$token=[guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N')
$hash=(Get-Sha256Hex ([Text.Encoding]::UTF8.GetBytes($token))).ToLowerInvariant()
$installer='public/_asuntos_install_'+[guid]::NewGuid().ToString('N')+'.php'
try {
    $template=Get-Content -Raw -Encoding UTF8 (Join-Path $PSScriptRoot 'asuntos_installer.php')
    Upload-Bytes $installer ([Text.Encoding]::UTF8.GetBytes($template.Replace('__TOKEN_HASH__',$hash)))
    $result=Invoke-RestMethod ('https://fingenieria.mx/'+$installer) -Headers @{'X-Asuntos-Install'=$token}
    if(!$result.ok -or $result.tablas -ne 4){throw ('No se pudo preparar la base de asuntos: '+$result.etapa+' '+$result.error)}
    foreach($file in $active){Upload-Bytes $file ([IO.File]::ReadAllBytes((Join-Path $taskRoot $file)))}
} finally {
    try {(Request-Ftp $installer ([Net.WebRequestMethods+Ftp]::DeleteFile)).GetResponse().Dispose()}
    catch {Write-Warning "Revisar retirada del instalador temporal $installer"}
}
Write-Output "Publicado. Respaldo: $backup"
