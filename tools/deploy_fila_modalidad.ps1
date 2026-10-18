$ErrorActionPreference='Stop'
. "$PSScriptRoot/deploy_fila.ps1" -DryRun | Out-Null
$taskModeFiles=@('fila/publico/vistas/modalidad_form.php','fila/publico/vistas/modalidad_detalle.php','fila/publico/esquema.sql','fila/publico/lib/datos.php','fila/publico/lib/correo.php','fila/publico/vistas/turno.php','fila/publico/vistas/mi_turno.php','fila/publico/vistas/mi_cita.php','fila/publico/index.php','app/Modules/Fila/ExpedienteController.php','app/Modules/Fila/views/expediente.php','app/Modules/Fila/views/index.php','app/Modules/Fila/module.php')
$taskModeBackup=Join-Path $taskRoot ('database/production-backups/fila-modalidad-'+(Get-Date -Format 'yyyyMMdd-HHmmss'))
[IO.Directory]::CreateDirectory($taskModeBackup)|Out-Null
foreach ($taskModeFile in $taskModeFiles) {
    $taskModeBytes=Fetch-Bytes $taskModeFile
    if ($null -ne $taskModeBytes) { [IO.File]::WriteAllBytes((Join-Path $taskModeBackup $taskModeFile.Replace('/','__')),$taskModeBytes) }
}
$taskModeToken=[guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N')
$taskModeHash=(Get-Sha256Hex ([Text.Encoding]::UTF8.GetBytes($taskModeToken))).ToLowerInvariant()
$taskModeInstaller='public/_fila_modalidad_'+[guid]::NewGuid().ToString('N')+'.php'
try {
    Upload-Bytes $taskModeInstaller ([Text.Encoding]::UTF8.GetBytes((Get-Content -Raw -Encoding UTF8 "$PSScriptRoot/fila_modalidad_installer.php").Replace('__TOKEN_HASH__',$taskModeHash)))
    $taskModeResult=Invoke-RestMethod ('https://fingenieria.mx/'+$taskModeInstaller) -Headers @{'X-Fila-Install'=$taskModeToken}
    if (!$taskModeResult.ok) { throw 'No se pudo preparar la modalidad de atención' }
    foreach ($taskModeFile in $taskModeFiles) {
        Upload-Bytes $taskModeFile ([IO.File]::ReadAllBytes((Join-Path $taskRoot $taskModeFile)))
        Write-Output "Verificado: $taskModeFile"
    }
} finally { (Request-Ftp $taskModeInstaller ([Net.WebRequestMethods+Ftp]::DeleteFile)).GetResponse().Dispose() }
$taskModePage=Invoke-WebRequest 'https://fingenieria.mx/public/fila/publico/sa/turno'
if (!$taskModePage.Content.Contains('Videoconferencia por Teams') -or !$taskModePage.Content.Contains('name="enlace_teams"') -or $taskModePage.Content -match 'Fatal error|Warning:|Parse error') { throw 'No se verificó el formulario público' }
Write-Output 'Formulario público de modalidad verificado sin solicitar turnos.'
