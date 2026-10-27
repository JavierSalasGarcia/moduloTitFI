[CmdletBinding()]
param(
    [switch]$DryRun,
    [string[]]$Only
)

$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$files = @(
    'Plan/4001_solicitudes.json',
    'Plan/4002_tasa_de_crecimiento_de_solicitudes.json',
    'Plan/4003_nuevo_ingreso_uaemex.json',
    'Plan/4004_tasa_de_crecimiento_de_ingreso.json',
    'Plan/4005_matricula_total.json',
    'Plan/4006_tasa_de_crecimiento_de_matricula.json',
    'Plan/4007_egresados.json',
    'Plan/4008_tasa_de_crecimiento_egresados.json',
    'Plan/4031_personal_academico.json',
    'Plan/4032_tasa_de_crecimiento_de_pa.json',
    'Plan/4033_personal_administrativo.json',
    'Plan/4034_tasa_de_crecimiento_de_per_admv.json'
)
if ($Only) {
    $requested = @($Only | ForEach-Object { $_.Replace('\\', '/') })
    $files = @($files | Where-Object { $requested -contains $_ })
    if ($files.Count -ne $requested.Count) { throw 'La selección contiene archivos no autorizados por este despliegue.' }
}

foreach ($file in $files) {
    $local = Join-Path $root $file
    if (-not (Test-Path -LiteralPath $local -PathType Leaf)) { throw "Archivo local faltante: $file" }
    Get-Content -LiteralPath $local -Raw | ConvertFrom-Json | Out-Null
}
if ($DryRun) { $files; return }

function Label([string[]]$lines, [string]$name) {
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match ('^\s*' + [regex]::Escape($name) + '\s*:\s*(.*)$')) {
            if ($Matches[1].Trim()) { return $Matches[1].Trim() }
            for ($j = $i + 1; $j -lt $lines.Count; $j++) {
                if ($lines[$j].Trim()) { return $lines[$j].Trim() }
            }
        }
    }
    throw "Falta $name en credenciales."
}

function Get-Sha256Hex([byte[]]$bytes) {
    $hasher = [Security.Cryptography.SHA256]::Create()
    try { return -join ($hasher.ComputeHash($bytes) | ForEach-Object { $_.ToString('X2') }) }
    finally { $hasher.Dispose() }
}

$lines = Get-Content -Encoding UTF8 -LiteralPath (Join-Path $root 'cred.txt')
$ftp = (Label $lines 'SITIO').TrimEnd('/')
$credential = [Net.NetworkCredential]::new((Label $lines 'USUARIO'), (Label $lines 'CONTRASEÑA'))

function Request([string]$file, [string]$method) {
    $request = [Net.FtpWebRequest]::Create("$ftp/$file")
    $request.Credentials = $credential
    $request.Method = $method
    $request.UseBinary = $true
    $request.UsePassive = $true
    $request.KeepAlive = $false
    $request.Timeout = 120000
    $request.ReadWriteTimeout = 120000
    return $request
}

function Receive([string]$file) {
    try {
        $response = (Request $file ([Net.WebRequestMethods+Ftp]::DownloadFile)).GetResponse()
        try {
            $memory = [IO.MemoryStream]::new()
            $response.GetResponseStream().CopyTo($memory)
            return ,$memory.ToArray()
        } finally { $response.Dispose() }
    } catch [Net.WebException] {
        if ($_.Exception.Response -and [int]$_.Exception.Response.StatusCode -eq 550) { return $null }
        throw
    }
}

function Upload([string]$file) {
    $bytes = [IO.File]::ReadAllBytes((Join-Path $root $file))
    $request = Request $file ([Net.WebRequestMethods+Ftp]::UploadFile)
    $request.ContentLength = $bytes.Length
    $stream = $request.GetRequestStream()
    try { $stream.Write($bytes, 0, $bytes.Length) }
    finally { $stream.Dispose() }
    $request.GetResponse().Dispose()
    $remote = Receive $file
    if ($null -eq $remote -or (Get-Sha256Hex $bytes) -ne (Get-Sha256Hex $remote)) {
        throw "La verificación SHA-256 falló para $file"
    }
    Write-Output "Publicado y verificado: $file"
}

$backup = Join-Path $root ('database/production-backups/graficas-datos2026-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
[IO.Directory]::CreateDirectory($backup) | Out-Null
foreach ($file in $files) {
    $bytes = Receive $file
    if ($null -eq $bytes) { throw "No existe el archivo remoto que se iba a reemplazar: $file" }
    [IO.File]::WriteAllBytes((Join-Path $backup ([IO.Path]::GetFileName($file))), $bytes)
}
Write-Output "Respaldo remoto guardado: $backup"
foreach ($file in $files) { Upload $file }
Write-Output "Publicación completa: $($files.Count) archivos JSON."
