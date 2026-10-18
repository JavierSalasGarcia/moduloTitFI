$ErrorActionPreference='Stop'
. "$PSScriptRoot/deploy_fila.ps1" -DryRun | Out-Null
$taskMinutesPassive=@('database/migrations/006_reuniones_minutas.sql','app/Modules/Reuniones/Services/MinutasService.php','app/Modules/Reuniones/MinutasController.php','app/Modules/Reuniones/views/minuta_nueva.php','app/Modules/Reuniones/views/minutas.php')
$taskMinutesActive=@('fila/publico/esquema.sql','app/Modules/Fila/ExpedienteController.php','app/Modules/Fila/views/expediente.php','app/Modules/Reuniones/views/index.php','app/Modules/Reuniones/views/detalle.php','app/Modules/Reuniones/ReunionesController.php','app/Modules/Reuniones/module.php')
$taskMinutesBackup=Join-Path $taskRoot ('database/production-backups/reuniones-minutas-'+(Get-Date -Format 'yyyyMMdd-HHmmss'))
[IO.Directory]::CreateDirectory($taskMinutesBackup)|Out-Null
foreach ($taskMinutesFile in @($taskMinutesPassive+$taskMinutesActive)) {
    $taskMinutesBytes=Fetch-Bytes $taskMinutesFile
    if ($null -ne $taskMinutesBytes) { [IO.File]::WriteAllBytes((Join-Path $taskMinutesBackup $taskMinutesFile.Replace('/','__')),$taskMinutesBytes) }
}
Ensure-Directory 'database/migrations'
foreach ($taskMinutesFile in $taskMinutesPassive) { Upload-Bytes $taskMinutesFile ([IO.File]::ReadAllBytes((Join-Path $taskRoot $taskMinutesFile))) }
$taskMinutesToken=[guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N')
$taskMinutesHash=(Get-Sha256Hex ([Text.Encoding]::UTF8.GetBytes($taskMinutesToken))).ToLowerInvariant()
$taskMinutesRemote='public/_minutas_install_'+[guid]::NewGuid().ToString('N')+'.php'
try {
    Upload-Bytes $taskMinutesRemote ([Text.Encoding]::UTF8.GetBytes((Get-Content -Raw -Encoding UTF8 "$PSScriptRoot/minutas_installer.php").Replace('__TOKEN_HASH__',$taskMinutesHash)))
    $taskMinutesResult=Invoke-RestMethod ('https://fingenieria.mx/'+$taskMinutesRemote) -Headers @{'X-Minutas-Install'=$taskMinutesToken}
    if (!$taskMinutesResult.ok) { throw 'No se pudo inicializar el historial de minutas' }
    Write-Output ('Historial inicializado. Minutas anteriores indexadas: '+$taskMinutesResult.minutas_indexadas)
    foreach ($taskMinutesFile in $taskMinutesActive) {
        Upload-Bytes $taskMinutesFile ([IO.File]::ReadAllBytes((Join-Path $taskRoot $taskMinutesFile)))
        Write-Output "Verificado: $taskMinutesFile"
    }
} finally { (Request-Ftp $taskMinutesRemote ([Net.WebRequestMethods+Ftp]::DeleteFile)).GetResponse().Dispose() }
