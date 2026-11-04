[CmdletBinding()]
param([string]$CredentialsPath = (Join-Path $PSScriptRoot '..\cred.txt'))

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
function Request-Ftp([string]$Uri, [string]$Method, [pscredential]$Credential) {
    $r = [Net.FtpWebRequest]::Create($Uri); $r.Method = $Method; $r.Credentials = $Credential.GetNetworkCredential()
    $r.UseBinary = $true; $r.UsePassive = $true; $r.KeepAlive = $false; return $r
}
function Send-Bytes([byte[]]$Bytes, [string]$Uri, [pscredential]$Credential) {
    $r = Request-Ftp $Uri ([Net.WebRequestMethods+Ftp]::UploadFile) $Credential; $r.ContentLength = $Bytes.Length
    $s = $r.GetRequestStream(); try { $s.Write($Bytes, 0, $Bytes.Length) } finally { $s.Dispose() }
    $p = $r.GetResponse(); $p.Dispose()
}
function Receive-Bytes([string]$Uri, [pscredential]$Credential) {
    $r = Request-Ftp $Uri ([Net.WebRequestMethods+Ftp]::DownloadFile) $Credential; $p = $r.GetResponse()
    try {
        $input = $p.GetResponseStream(); $memory = [IO.MemoryStream]::new()
        try { $input.CopyTo($memory); return $memory.ToArray() } finally { $memory.Dispose(); $input.Dispose() }
    } finally { $p.Dispose() }
}
function Remove-Remote([string]$Uri, [pscredential]$Credential) {
    try { $r = Request-Ftp $Uri ([Net.WebRequestMethods+Ftp]::DeleteFile) $Credential; $p = $r.GetResponse(); $p.Dispose() }
    catch [Net.WebException] { if (-not $_.Exception.Response -or [int] $_.Exception.Response.StatusCode -ne 550) { throw } }
}

$lines = Get-Content -Encoding UTF8 -LiteralPath $CredentialsPath
$base = (Get-LabeledValue $lines 'SITIO').TrimEnd('/')
$user = Get-LabeledValue $lines 'USUARIO'
$password = ConvertTo-SecureString (Get-LabeledValue $lines 'CONTRASEÑA') -AsPlainText -Force
$credential = [pscredential]::new($user, $password)
$migration = Join-Path $root 'database\migrations\035_encuesta_privada_viajes_2027.sql'
$template = Join-Path $root 'tools\encuesta_viajes_installer.php'
$nonce = [guid]::NewGuid().ToString('N')
$token = [guid]::NewGuid().ToString('N') + [guid]::NewGuid().ToString('N')
$sha = [Security.Cryptography.SHA256]::Create()
try { $hashBytes = $sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($token)) } finally { $sha.Dispose() }
$tokenHash = -join ($hashBytes | ForEach-Object { $_.ToString('x2') })
$remoteName = "_encuesta_viajes_install_$nonce.php"
$remoteInstaller = "$base/public/assets/$remoteName"
$remoteHtaccess = "$base/public/assets/.htaccess"
$originalHtaccess = $null
$htaccessReplaced = $false

try {
    Send-Bytes ([IO.File]::ReadAllBytes($migration)) "$base/database/migrations/035_encuesta_privada_viajes_2027.sql" $credential
    $originalHtaccess = Receive-Bytes $remoteHtaccess $credential
    $temporaryHtaccess = "Options -Indexes`n<FilesMatch `"(?i)\.(?:php[0-9]?|phtml|phar)`$`">`n    Require all denied`n</FilesMatch>`n<Files `"$remoteName`">`n    Require all granted`n</Files>`n"
    Send-Bytes ([Text.UTF8Encoding]::new($false).GetBytes($temporaryHtaccess)) $remoteHtaccess $credential
    $htaccessReplaced = $true
    $installer = (Get-Content -Raw -Encoding UTF8 -LiteralPath $template).Replace('__TOKEN_HASH__', $tokenHash)
    Send-Bytes ([Text.UTF8Encoding]::new($false).GetBytes($installer)) $remoteInstaller $credential
    $result = Invoke-RestMethod -Method Post -Uri "https://fingenieria.mx/assets/$remoteName" -Headers @{'X-Encuesta-Install' = $token} -TimeoutSec 120
    if (-not $result.ok) { throw 'La migración no superó la verificación posterior.' }
    $result
} finally {
    Remove-Remote $remoteInstaller $credential
    if ($htaccessReplaced -and $null -ne $originalHtaccess) { Send-Bytes $originalHtaccess $remoteHtaccess $credential }
}
