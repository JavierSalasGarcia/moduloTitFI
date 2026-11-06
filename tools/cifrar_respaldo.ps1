<#
.SYNOPSIS
  Cifra un archivo de respaldo (ZIP, .sql.gz) con una frase de contraseña y elimina el original tras verificarlo.
.DESCRIPTION
  Envuelve tools/cifrar_archivo.php (docs/CIFRADO_DE_DATOS.md). La frase se toma de la variable de entorno
  TRAZAFI_BACKUP_FRASE o se pide dos veces sin mostrarla. Guárdala en un gestor de contraseñas: sin ella el respaldo
  no se puede recuperar. Requiere PHP 8 con la extensión sodium (variable TRAZAFI_PHP, `php` en el PATH o XAMPP).
.EXAMPLE
  powershell -File tools\cifrar_respaldo.ps1 -Path D:\trazafi-backups\respaldos-historicos-20261106.zip
  # Para recuperarlo: php tools\cifrar_archivo.php descifrar <archivo>.tzfi
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory)][string]$Path,
    [switch]$ConservarOriginal
)
$ErrorActionPreference = 'Stop'
$full = [IO.Path]::GetFullPath($Path)
if (-not (Test-Path -LiteralPath $full -PathType Leaf)) { throw "No existe el archivo: $full" }

$php = if ($env:TRAZAFI_PHP) { $env:TRAZAFI_PHP }
       elseif (Get-Command php -ErrorAction SilentlyContinue) { 'php' }
       elseif (Test-Path 'C:\xampp\php\php.exe') { 'C:\xampp\php\php.exe' }
       else { throw 'No se encontró PHP. Define TRAZAFI_PHP con la ruta de php.exe.' }
$script = Join-Path $PSScriptRoot 'cifrar_archivo.php'

$propia = $false
if (-not $env:TRAZAFI_BACKUP_FRASE) {
    $a = Read-Host 'Frase de contraseña del respaldo' -AsSecureString
    $b = Read-Host 'Repite la frase' -AsSecureString
    $pa = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($a))
    $pb = [Runtime.InteropServices.Marshal]::PtrToStringBSTR([Runtime.InteropServices.Marshal]::SecureStringToBSTR($b))
    if ([string]::IsNullOrEmpty($pa) -or $pa -ne $pb) { throw 'Las frases no coinciden o están vacías.' }
    $env:TRAZAFI_BACKUP_FRASE = $pa
    $propia = $true
}
try {
    $args = @($script, 'cifrar', $full)
    if (-not $ConservarOriginal) { $args += '--borrar' }
    & $php @args
    if ($LASTEXITCODE -ne 0) { throw "El cifrado falló (código $LASTEXITCODE); el original se conserva." }
    "$full.tzfi"
}
finally {
    if ($propia) { Remove-Item Env:\TRAZAFI_BACKUP_FRASE -ErrorAction SilentlyContinue }
}
