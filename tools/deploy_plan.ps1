[CmdletBinding()]
param([switch]$DryRun)
$ErrorActionPreference = 'Stop'
function Get-Sha256Hex([byte[]]$Bytes) { $h=[Security.Cryptography.SHA256]::Create(); try { -join ($h.ComputeHash($Bytes) | ForEach-Object { $_.ToString('X2') }) } finally { $h.Dispose() } }
function Get-RandomHex([int]$Count) { $b=New-Object byte[] $Count; $r=[Security.Cryptography.RandomNumberGenerator]::Create(); try { $r.GetBytes($b) } finally { $r.Dispose() }; -join ($b | ForEach-Object { $_.ToString('X2') }) }
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))

& python -X utf8 (Join-Path $PSScriptRoot 'auditar_metodologia_plan.py')
if ($LASTEXITCODE -ne 0) { throw 'La auditoría metodológica impidió la publicación.' }
& python -X utf8 (Join-Path $PSScriptRoot 'validar_congruencia_plan.py')
if ($LASTEXITCODE -ne 0) { throw 'La auditoría triangular impidió la publicación.' }

function Label([string[]]$lines, [string]$name) {
    for ($i = 0; $i -lt $lines.Count; $i++) {
        if ($lines[$i] -match ('^\s*' + [regex]::Escape($name) + '\s*:\s*(.*)$')) {
            if ($Matches[1].Trim()) { return $Matches[1].Trim() }
            for ($j = $i + 1; $j -lt $lines.Count; $j++) { if ($lines[$j].Trim()) { return $lines[$j].Trim() } }
        }
    }
    throw "Falta $name en credenciales."
}

$files = @('Plan/.htaccess', 'storage/plan/.htaccess')
$files += @(Get-ChildItem -LiteralPath (Join-Path $root 'Plan') -Filter '*.json' -File |
    Sort-Object Name | ForEach-Object { 'Plan/' + $_.Name })
$files += @(
    'app/Modules/Plan/PlanController.php',
    'app/Modules/Plan/PlanRepository.php',
    'app/Modules/Plan/PlanDraftStore.php',
    'app/Modules/Plan/PlanSurveyStore.php',
    'app/Modules/Plan/PlanStatusService.php',
    'app/Modules/Plan/PlanXlsx.php',
    'app/Modules/Plan/views/index.php',
    'app/Modules/Plan/views/encuesta.php',
    'app/Modules/Plan/views/agenda.php',
    'app/Modules/Plan/views/estatus.php',
    'app/Views/layouts/plan.php',
    'app/Views/layouts/plan_encuesta.php',
    'public/assets/css/plan.css',
    'public/assets/js/plan-chart-editor.js',
    'public/assets/js/plan.js',
    'public/assets/js/plan-encuestas.js',
    'app/Core/Router.php',
    'app/Core/InterfaceUi.php',
    'app/Modules/Auth/views/login.php',
    'fila/publico/vistas/base.php'
)
$files += 'app/Modules/Plan/module.php' # activar al final
foreach ($file in $files) {
    $local = Join-Path $root $file
    if ($file.Contains('..') -or -not (Test-Path -LiteralPath $local -PathType Leaf)) { throw "Archivo inválido: $file" }
}
if ($DryRun) { $files; return }

$lines = Get-Content -Encoding UTF8 -LiteralPath (Join-Path $root 'cred.txt')
$ftp = (Label $lines 'SITIO').TrimEnd('/')
$credential = [Net.NetworkCredential]::new((Label $lines 'USUARIO'), (Label $lines 'CONTRASEÑA'))
function Request([string]$file, [string]$method) {
    $r = [Net.FtpWebRequest]::Create("$ftp/$file")
    $r.Credentials = $credential; $r.Method = $method; $r.UseBinary = $true
    $r.UsePassive = $true; $r.KeepAlive = $false; $r.Timeout = 120000; $r.ReadWriteTimeout = 120000
    return $r
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
function Directory([string]$path) {
    try { (Request $path ([Net.WebRequestMethods+Ftp]::MakeDirectory)).GetResponse().Dispose() }
    catch [Net.WebException] { if (!$_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne 550) { throw } }
}
function Upload([string]$file) {
    $bytes = [IO.File]::ReadAllBytes((Join-Path $root $file))
    $request = Request $file ([Net.WebRequestMethods+Ftp]::UploadFile)
    $request.ContentLength = $bytes.Length
    $stream = $request.GetRequestStream()
    try { $stream.Write($bytes, 0, $bytes.Length) } finally { $stream.Dispose() }
    $request.GetResponse().Dispose()
    $remote = Receive $file
    if ($null -eq $remote -or (Get-Sha256Hex ($bytes)) -ne
        (Get-Sha256Hex ($remote))) {
        throw "Verificación fallida: $file"
    }
    Write-Output "Publicado y verificado: $file"
}

$backup = Join-Path $root ('database/production-backups/plan-' + (Get-Date -Format 'yyyyMMdd-HHmmss'))
[IO.Directory]::CreateDirectory($backup) | Out-Null
$existing = 0
foreach ($file in $files) {
    $bytes = Receive $file
    if ($null -ne $bytes) {
        [IO.File]::WriteAllBytes((Join-Path $backup $file.Replace('/', '__')), $bytes)
        $existing++
    }
}
Write-Output "Respaldo listo: $existing archivos previos en $backup"
foreach ($directory in @('Plan','storage','storage/plan','storage/plan/revisiones','storage/plan/encuestas',
    'app/Modules/Plan','app/Modules/Plan/views','app/Views/layouts','public/assets/css','public/assets/js')) {
    Directory $directory
}
foreach ($file in $files) { Upload $file }
Write-Output "Publicación completa: $($files.Count) archivos."
