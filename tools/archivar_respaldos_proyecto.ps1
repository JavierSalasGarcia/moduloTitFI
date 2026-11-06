[CmdletBinding(SupportsShouldProcess)]
param(
    [string]$Source = (Join-Path $PSScriptRoot '..\database\production-backups'),
    [string]$ArchiveRoot = $(if ($env:TRAZAFI_BACKUP_ROOT) { $env:TRAZAFI_BACKUP_ROOT } else { Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'trazafi-backups' }),
    [ValidateRange(0, 100)][int]$KeepLatest = 20,
    # Por defecto el ZIP se cifra (tools/cifrar_respaldo.ps1); -SinCifrar sólo para pruebas.
    [switch]$SinCifrar
)

$ErrorActionPreference = 'Stop'
$projectRoot = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$sourceFull = [IO.Path]::GetFullPath($Source)
$archiveFull = [IO.Path]::GetFullPath($ArchiveRoot)
if (-not $sourceFull.StartsWith($projectRoot.TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'Source debe estar dentro del proyecto.' }
if ($archiveFull.StartsWith($projectRoot.TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)) { throw 'ArchiveRoot debe estar fuera del proyecto.' }
if (-not (Test-Path -LiteralPath $sourceFull -PathType Container)) { Write-Output 'No hay respaldos históricos que archivar.'; return }

$dirs = @(Get-ChildItem -LiteralPath $sourceFull -Directory | Sort-Object LastWriteTime -Descending)
$looseFiles = @(Get-ChildItem -LiteralPath $sourceFull -File)
$toArchive = @($dirs | Select-Object -Skip $KeepLatest) + $looseFiles
if (-not $toArchive) { Write-Output 'La retención ya se cumple.'; return }

[IO.Directory]::CreateDirectory($archiveFull) | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$zip = Join-Path $archiveFull "respaldos-historicos-$stamp.zip"
if ($PSCmdlet.ShouldProcess("$($toArchive.Count) carpetas", "Comprimir en $zip y retirar originales verificados")) {
    Compress-Archive -LiteralPath $toArchive.FullName -DestinationPath $zip -CompressionLevel Optimal
    if (-not (Test-Path -LiteralPath $zip -PathType Leaf) -or (Get-Item -LiteralPath $zip).Length -le 0) { throw 'No se creó un archivo de respaldo válido.' }
    Add-Type -AssemblyName System.IO.Compression.FileSystem
    $entries = [IO.Compression.ZipFile]::OpenRead($zip)
    try { if ($entries.Entries.Count -lt $toArchive.Count) { throw 'El ZIP no contiene todos los elementos esperados.' } }
    finally { $entries.Dispose() }
    # Cifrar antes de retirar los originales: si falla, no se borra nada y el ZIP en claro queda para reintentar.
    if (-not $SinCifrar) {
        $zip = & (Join-Path $PSScriptRoot 'cifrar_respaldo.ps1') -Path $zip | Select-Object -Last 1
    }
    foreach ($dir in $toArchive) {
        $resolved = [IO.Path]::GetFullPath($dir.FullName)
        if (-not $resolved.StartsWith($sourceFull.TrimEnd('\') + '\', [StringComparison]::OrdinalIgnoreCase)) { throw "Ruta fuera de Source: $resolved" }
        if ($dir.PSIsContainer) { Remove-Item -LiteralPath $resolved -Recurse -Force }
        else { Remove-Item -LiteralPath $resolved -Force }
    }
    [pscustomobject]@{ ArchivedDirectories=$toArchive.Count; KeptDirectories=[Math]::Min($KeepLatest,$dirs.Count); Archive=$zip }
}
