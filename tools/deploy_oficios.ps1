[CmdletBinding()]
param(
    [string]$CredentialsPath = (Join-Path $PSScriptRoot '..\cred.txt'),
    [string]$SiteUrl = 'https://fingenieria.mx',
    # SQL adicional de una sola vez (datos iniciales); se mantiene fuera del repositorio.
    [string]$ExtraSqlPath = '',
    # PDFs a copiar a storage/oficios/<departamento>/ (ruta relativa => archivo local).
    [string[]]$PdfFiles = @(),
    [switch]$DryRun
)

$ErrorActionPreference = 'Stop'

function Get-LabeledValue([string[]]$Lines,[string]$Label) {
    for($i=0;$i-lt$Lines.Count;$i++) {
        if($Lines[$i]-match('^\s*'+[regex]::Escape($Label)+'\s*:\s*(.*)$')) {
            if($Matches[1].Trim()){return $Matches[1].Trim()}
            for($j=$i+1;$j-lt$Lines.Count;$j++){if($Lines[$j].Trim()){return $Lines[$j].Trim()}}
        }
    }
    throw "Falta $Label."
}
function Request([string]$Uri,[string]$Method,[pscredential]$Credential) {
    $r=[Net.FtpWebRequest]::Create($Uri);$r.Method=$Method;$r.Credentials=$Credential.GetNetworkCredential()
    $r.UseBinary=$true;$r.UsePassive=$true;$r.KeepAlive=$false;return $r
}
function Send([string]$Path,[string]$Uri,[pscredential]$Credential) {
    $b=[IO.File]::ReadAllBytes($Path);$r=Request $Uri ([Net.WebRequestMethods+Ftp]::UploadFile) $Credential
    $r.ContentLength=$b.Length;$s=$r.GetRequestStream();try{$s.Write($b,0,$b.Length)}finally{$s.Dispose()}
    $p=$r.GetResponse();$p.Dispose()
}
function Receive([string]$Uri,[string]$Path,[pscredential]$Credential) {
    $r=Request $Uri ([Net.WebRequestMethods+Ftp]::DownloadFile) $Credential;$p=$r.GetResponse()
    try{$in=$p.GetResponseStream();$out=[IO.File]::Create($Path);try{$in.CopyTo($out)}finally{$out.Dispose();$in.Dispose()}}finally{$p.Dispose()}
}
function Remove-Remote([string]$Uri,[pscredential]$Credential) {
    try{$r=Request $Uri ([Net.WebRequestMethods+Ftp]::DeleteFile) $Credential;$p=$r.GetResponse();$p.Dispose()}
    catch [Net.WebException]{if(-not $_.Exception.Response -or [int]$_.Exception.Response.StatusCode-ne 550){throw}}
}
function Ensure-Dir([string]$Uri,[pscredential]$Credential) {
    try{$r=Request $Uri ([Net.WebRequestMethods+Ftp]::MakeDirectory) $Credential;$p=$r.GetResponse();$p.Dispose()}
    catch [Net.WebException]{if(-not $_.Exception.Response -or [int]$_.Exception.Response.StatusCode-ne 550){throw}}
}

$root=[IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..'))
$migrations='019_oficios_enviados.sql','020_cambio_titulares_subdireccion_investigacion.sql','021_cuentas_subdireccion_y_admin.sql'
$sql=($migrations|ForEach-Object{[IO.File]::ReadAllText((Join-Path $root "database\migrations\$_"),[Text.Encoding]::UTF8)}) -join "`n"
if($ExtraSqlPath){$sql+="`n"+[IO.File]::ReadAllText($ExtraSqlPath,[Text.Encoding]::UTF8)}
if($DryRun){$migrations;$PdfFiles;return}

$lines=Get-Content -Encoding UTF8 -LiteralPath $CredentialsPath
$base=(Get-LabeledValue $lines 'SITIO').TrimEnd('/')
$user=Get-LabeledValue $lines 'USUARIO'
$cred=[pscredential]::new($user,(ConvertTo-SecureString (Get-LabeledValue $lines ('CONTRASE'+[char]0x00D1+'A')) -AsPlainText -Force))

$nonce=[guid]::NewGuid().ToString('N')
$token=[guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N')
$name="_oficios_migration_$nonce.php"
$tempDir=Join-Path ([IO.Path]::GetTempPath()) "trazafi-oficios-$nonce"
[IO.Directory]::CreateDirectory($tempDir)|Out-Null
$installer=Join-Path $tempDir $name
$originalHtaccess=Join-Path $tempDir 'assets.htaccess.original'
$temporaryHtaccess=Join-Path $tempDir 'assets.htaccess.temporary'
$sql64=[Convert]::ToBase64String([Text.UTF8Encoding]::new($false).GetBytes($sql))
$php=@"
<?php
declare(strict_types=1);
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!hash_equals('$token', (string)(`$_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? ''))) { http_response_code(404); exit('{}'); }
try {
  `$config=require dirname(__DIR__, 2).'/app/config.php'; `$db=`$config['db'];
  `$pdo=new PDO('mysql:host='.`$db['host'].';dbname='.`$db['nombre'].';charset='.`$db['charset'],`$db['usuario'],`$db['clave'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  `$sql=base64_decode('$sql64',true); if (`$sql===false) throw new RuntimeException('Migracion invalida.');
  `$statements=preg_split('/;\s*(?:\r?\n|$)/',`$sql,-1,PREG_SPLIT_NO_EMPTY); `$applied=0;
  foreach (`$statements as `$statement) { if (trim(preg_replace('/^--.*$/m','',`$statement))!=='') { `$pdo->exec(`$statement); `$applied++; } }
  `$usuarios=`$pdo->query("SELECT email,rol,departamento_id,activo FROM usuarios WHERE email IN ('jsalasg@uaemex.mx','academica_fi@uaemex.mx','hbanuelosg589@profesor.uaemex.mx','subacademica@fi.uaemex.mx') ORDER BY email")->fetchAll(PDO::FETCH_ASSOC);
  `$oficios=(int)`$pdo->query('SELECT COUNT(*) FROM oficios_enviados')->fetchColumn();
  echo json_encode(['ok'=>true,'statements'=>`$applied,'oficios'=>`$oficios,'usuarios'=>`$usuarios]);
} catch (Throwable `$e) { error_log('Oficios migration: '.`$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false]); }
"@
[IO.File]::WriteAllText($installer,$php,[Text.UTF8Encoding]::new($false))
[IO.File]::WriteAllText($temporaryHtaccess,@"
Options -Indexes
<FilesMatch "(?i)\.(?:php[0-9]?|phtml|phar)$">
    Require all denied
</FilesMatch>
<Files "$name">
    Require all granted
</Files>
"@,[Text.UTF8Encoding]::new($false))

$htaccessReplaced=$false
try {
    foreach($pdf in $PdfFiles){
        $remote,$local=$pdf -split '=',2
        if($remote -notmatch '^storage/oficios/\d+/[A-Za-z0-9_.-]+\.pdf$'){throw "Ruta remota no permitida: $remote"}
        foreach($dir in 'storage/oficios',($remote -replace '/[^/]+$','')){Ensure-Dir "$base/$dir" $cred}
        Send $local "$base/$remote" $cred
        Write-Output "PDF publicado: $remote"
    }
    Receive "$base/public/assets/.htaccess" $originalHtaccess $cred
    Send $temporaryHtaccess "$base/public/assets/.htaccess" $cred
    $htaccessReplaced=$true
    Send $installer "$base/public/assets/$name" $cred
    $result=Invoke-RestMethod -Method Post -Uri "$SiteUrl/assets/$name" -Headers @{'X-Migration-Token'=$token} -TimeoutSec 90
    if(-not $result.ok){throw 'La migracion no se verifico.'}
    $result|ConvertTo-Json -Depth 5
} finally {
    Remove-Remote "$base/public/assets/$name" $cred
    if($htaccessReplaced -and (Test-Path $originalHtaccess)){Send $originalHtaccess "$base/public/assets/.htaccess" $cred}
    if(Test-Path $tempDir){Remove-Item -LiteralPath $tempDir -Recurse -Force}
}
