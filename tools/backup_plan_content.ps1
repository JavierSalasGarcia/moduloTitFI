[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$root = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))

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

$lines = Get-Content -LiteralPath (Join-Path $root 'cred.txt')
$ftp = (Label $lines 'SITIO').TrimEnd('/')
$credential = [Net.NetworkCredential]::new((Label $lines 'USUARIO'), (Label $lines 'CONTRASEÑA'))

function Request([string]$path, [string]$method) {
    $request = [Net.FtpWebRequest]::Create("$ftp/$path")
    $request.Credentials = $credential
    $request.Method = $method
    $request.UseBinary = $true
    $request.UsePassive = $true
    $request.KeepAlive = $false
    $request.Timeout = 120000
    $request.ReadWriteTimeout = 120000
    return $request
}

function Entries([string]$path) {
    $response = (Request $path ([Net.WebRequestMethods+Ftp]::ListDirectoryDetails)).GetResponse()
    try {
        $reader = [IO.StreamReader]::new($response.GetResponseStream())
        $raw = $reader.ReadToEnd()
    } finally {
        $response.Dispose()
    }
    $result = @()
    foreach ($line in ($raw -split "`r?`n" | Where-Object { $_ })) {
        if ($line -notmatch '^([d-])[rwx-]+\s+\d+\s+\S+\s+\S+\s+\d+\s+\w+\s+\d+\s+[\d:]+\s+(.+)$') { continue }
        $kind = $Matches[1]
        $name = $Matches[2]
        if ($name -eq '.' -or $name -eq '..') { continue }
        if ($name.Contains('/') -or $name.Contains('\')) { throw "Nombre FTP no seguro en $path" }
        $result += [pscustomobject]@{ Name = $name; Directory = ($kind -eq 'd') }
    }
    return $result
}

function Download([string]$remote, [string]$local) {
    $response = (Request $remote ([Net.WebRequestMethods+Ftp]::DownloadFile)).GetResponse()
    try {
        [IO.Directory]::CreateDirectory((Split-Path -Parent $local)) | Out-Null
        $file = [IO.File]::Create($local)
        try { $response.GetResponseStream().CopyTo($file) } finally { $file.Dispose() }
    } finally {
        $response.Dispose()
    }
}

function MirrorDirectory([string]$remote, [string]$local) {
    [IO.Directory]::CreateDirectory($local) | Out-Null
    $count = 0
    foreach ($entry in (Entries $remote)) {
        if ($entry.Directory) {
            $count += MirrorDirectory "$remote/$($entry.Name)" (Join-Path $local $entry.Name)
        } else {
            Download "$remote/$($entry.Name)" (Join-Path $local $entry.Name)
            $count++
        }
    }
    return $count
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backup = Join-Path $root "database/production-backups/plan-content-$stamp"
[IO.Directory]::CreateDirectory($backup) | Out-Null
$count = 0
foreach ($remote in @('storage/plan/revisiones', 'storage/plan/encuestas')) {
    $count += MirrorDirectory $remote (Join-Path $backup ($remote.Replace('/', [IO.Path]::DirectorySeparatorChar)))
}
foreach ($remote in @('Plan/preliminar_fi.json', 'Plan/foda_interpretaciones.json', 'Plan/instrumentos.json', 'Plan/enlaces_encuesta.json')) {
    Download $remote (Join-Path $backup ($remote.Replace('/', [IO.Path]::DirectorySeparatorChar)))
    $count++
}
Write-Output "Respaldo de contenido: $backup"
Write-Output "Archivos descargados: $count"
