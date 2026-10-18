[CmdletBinding()]
param([switch]$DryRun)
$ErrorActionPreference='Stop'
function Get-Sha256Hex([byte[]]$Bytes) { $h=[Security.Cryptography.SHA256]::Create(); try { -join ($h.ComputeHash($Bytes) | ForEach-Object { $_.ToString('X2') }) } finally { $h.Dispose() } }
function Get-RandomHex([int]$Count) { $b=New-Object byte[] $Count; $r=[Security.Cryptography.RandomNumberGenerator]::Create(); try { $r.GetBytes($b) } finally { $r.Dispose() }; -join ($b | ForEach-Object { $_.ToString('X2') }) }
$taskRoot=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
function Read-Label($lines,$label) {
    for ($i=0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match ('^\s*'+[regex]::Escape($label)+'\s*:\s*(.*)$')) {
            if ($Matches[1].Trim()) { return $Matches[1].Trim() }
            for ($j=$i+1; $j -lt $lines.Count; $j++) { if ($lines[$j].Trim()) { return $lines[$j].Trim() } }
        }
    }
    throw "Falta $label"
}
function Request-Ftp($relative,$method) {
    $req=[Net.FtpWebRequest]::Create("$taskFtp/$relative")
    $req.Credentials=$taskCredential
    $req.Method=$method
    $req.UseBinary=$true; $req.UsePassive=$true; $req.KeepAlive=$false
    $req.Timeout=30000; $req.ReadWriteTimeout=30000
    return $req
}
function Fetch-Bytes($relative) {
    try {
        $res=(Request-Ftp $relative ([Net.WebRequestMethods+Ftp]::DownloadFile)).GetResponse()
        try { $stream=$res.GetResponseStream(); $mem=[IO.MemoryStream]::new(); $stream.CopyTo($mem); return ,$mem.ToArray() }
        finally { $res.Dispose() }
    } catch [Net.WebException] {
        if ($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 550) { return $null }; throw
    }
}
function Upload-Bytes($relative,[byte[]]$bytes) {
    $req=Request-Ftp $relative ([Net.WebRequestMethods+Ftp]::UploadFile)
    $req.ContentLength=$bytes.Length
    $stream=$req.GetRequestStream(); try { $stream.Write($bytes,0,$bytes.Length) } finally { $stream.Dispose() }
    $req.GetResponse().Dispose()
    $verify=Fetch-Bytes $relative
    if (!$verify -or [Convert]::ToBase64String($verify) -ne [Convert]::ToBase64String($bytes)) { throw "Verificación fallida: $relative" }
}
function Ensure-Directory($relative) {
    try { (Request-Ftp $relative ([Net.WebRequestMethods+Ftp]::MakeDirectory)).GetResponse().Dispose() }
    catch [Net.WebException] { if (!$_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne 550) { throw } }
}
$taskLines=Get-Content -Encoding UTF8 -LiteralPath (Join-Path $taskRoot 'cred.txt')
$taskFtp=(Read-Label $taskLines 'SITIO').TrimEnd('/')
$taskCredential=[Net.NetworkCredential]::new((Read-Label $taskLines 'USUARIO'),(Read-Label $taskLines 'CONTRASEÑA'))
$files=@('fila/credenciales.txt','fila/publico/config.example.php','fila/publico/esquema.sql','fila/publico/index.php','fila/publico/admin.php','fila/publico/api.php')
foreach ($dir in @('fila/publico/lib','fila/publico/vistas','fila/publico/estatico','app/Modules/Fila')) {
    $files+=Get-ChildItem -LiteralPath (Join-Path $taskRoot $dir) -File -Recurse | ForEach-Object { $_.FullName.Substring($taskRoot.TrimEnd('\').Length+1).Replace('\','/') }
}
$files=@($files | Where-Object { $_ -ne 'app/Modules/Fila/module.php' })
$files+=@('app/Modules/Fila/module.php','app/Core/App.php') # activar al final
if ($DryRun) { $files; return }
$stamp=Get-Date -Format 'yyyyMMdd-HHmmss'
$backup=Join-Path $taskRoot "database/production-backups/fila-$stamp"
[IO.Directory]::CreateDirectory($backup) | Out-Null
foreach ($file in $files) {
    $bytes=Fetch-Bytes $file
    if ($null -ne $bytes) { [IO.File]::WriteAllBytes((Join-Path $backup $file.Replace('/','__')),$bytes) }
}
foreach ($dir in @('fila','fila/publico','fila/publico/lib','fila/publico/vistas','fila/publico/estatico','app/Modules/Fila','app/Modules/Fila/views','storage/fila','storage/fila/subidas','storage/fila/subidas/minutas')) { Ensure-Directory $dir }
# En este hosting /fila es también la ruta pública del módulo; no colocar
# una regla local que intercepte esa ruta antes del enrutador de la plataforma.
$installer='_fila_install_'+[guid]::NewGuid().ToString('N')+'.php'
$token=[guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N')
$hash=(Get-Sha256Hex ([Text.Encoding]::UTF8.GetBytes($token))).ToLowerInvariant()
try {
    # Subir librerías y credenciales antes de inicializar; activar el núcleo al final.
    foreach ($file in $files | Where-Object { $_ -notin @('app/Core/App.php','app/Modules/Fila/module.php') }) {
        Upload-Bytes $file ([IO.File]::ReadAllBytes((Join-Path $taskRoot $file)))
        Write-Output "Verificado: $file"
    }
    $template=Get-Content -Raw -LiteralPath (Join-Path $PSScriptRoot 'fila_installer.php')
    Upload-Bytes "public/$installer" ([Text.Encoding]::UTF8.GetBytes($template.Replace('__TOKEN_HASH__',$hash)))
    $result=Invoke-RestMethod "https://fingenieria.mx/$installer" -Headers @{'X-Fila-Install'=$token}
    if (!$result.ok) { throw 'Falló la inicialización de la base exclusiva' }
    Write-Output "Base Fila inicializada. Oficinas existentes: $($result.oficinas)"
    foreach ($file in @('app/Modules/Fila/module.php','app/Core/App.php')) { Upload-Bytes $file ([IO.File]::ReadAllBytes((Join-Path $taskRoot $file))) }
} finally {
    try { (Request-Ftp "public/$installer" ([Net.WebRequestMethods+Ftp]::DeleteFile)).GetResponse().Dispose() }
    catch { Write-Warning "Revisar retirada del instalador temporal $installer" }
}
Write-Output "Publicado. Respaldo: $backup"
