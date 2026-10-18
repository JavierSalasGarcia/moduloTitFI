[CmdletBinding()]
param([string]$CredentialsPath=(Join-Path $PSScriptRoot '..\cred.txt'))

$ErrorActionPreference='Stop'

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
    try{$input=$p.GetResponseStream();$output=[IO.File]::Create($Path);try{$input.CopyTo($output)}finally{$output.Dispose();$input.Dispose()}}finally{$p.Dispose()}
}
function Remove-Remote([string]$Uri,[pscredential]$Credential) {
    try{$r=Request $Uri ([Net.WebRequestMethods+Ftp]::DeleteFile) $Credential;$p=$r.GetResponse();$p.Dispose()}
    catch [Net.WebException]{if(-not $_.Exception.Response -or [int]$_.Exception.Response.StatusCode-ne 550){throw}}
}

$lines=Get-Content -Encoding UTF8 -LiteralPath $CredentialsPath
$base=(Get-LabeledValue $lines 'SITIO').TrimEnd('/')
$user=Get-LabeledValue $lines 'USUARIO'
$pass=ConvertTo-SecureString (Get-LabeledValue $lines 'CONTRASEÑA') -AsPlainText -Force
$cred=[pscredential]::new($user,$pass)
$migration=Resolve-Path (Join-Path $PSScriptRoot '..\database\migrations\015_estructura_organizacional.sql')
$nonce=[guid]::NewGuid().ToString('N')
$token=([guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N'))
$name="_org_migration_$nonce.php"
$tempDir=Join-Path ([IO.Path]::GetTempPath()) "trazafi-org-$nonce"
[IO.Directory]::CreateDirectory($tempDir)|Out-Null
$installer=Join-Path $tempDir $name
$originalHtaccess=Join-Path $tempDir 'assets.htaccess.original'
$temporaryHtaccess=Join-Path $tempDir 'assets.htaccess.temporary'
$sql64=[Convert]::ToBase64String([IO.File]::ReadAllBytes($migration))
$php=@"
<?php
declare(strict_types=1);
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!hash_equals('$token', (string)(`$_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? ''))) { http_response_code(404); exit('{}'); }
try {
  `$config=require dirname(__DIR__, 2).'/app/config.php'; `$db=`$config['db'];
  `$pdo=new PDO('mysql:host='.`$db['host'].';dbname='.`$db['nombre'].';charset='.`$db['charset'],`$db['usuario'],`$db['clave'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  `$sql=base64_decode('$sql64',true); if (`$sql===false) throw new RuntimeException('Migración inválida.');
  `$statements=preg_split('/;\s*(?:\r?\n|$)/',`$sql,-1,PREG_SPLIT_NO_EMPTY); `$applied=0;
  foreach (`$statements as `$statement) { if (trim(`$statement)!=='') { `$pdo->exec(`$statement); `$applied++; } }
  `$expected=['departamento_responsables','programa_responsables','programa_organos','programa_organo_miembros'];
  `$found=`$pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('departamento_responsables','programa_responsables','programa_organos','programa_organo_miembros') ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
  `$counts=[]; foreach (['programas','reuniones_posgrados','reuniones_coordinadores'] as `$table) { `$counts[`$table]=(int)`$pdo->query('SELECT COUNT(*) FROM '.`$table)->fetchColumn(); }
  sort(`$expected); sort(`$found); echo json_encode(['ok'=>`$expected===`$found,'statements'=>`$applied,'tables'=>`$found,'counts'=>`$counts]);
} catch (Throwable `$e) { error_log('Organizational migration: '.`$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false]); }
"@
[IO.File]::WriteAllText($installer,$php,[Text.UTF8Encoding]::new($false))
$htaccess=@"
Options -Indexes
<FilesMatch "(?i)\.(?:php[0-9]?|phtml|phar)$">
    Require all denied
</FilesMatch>
<Files "$name">
    Require all granted
</Files>
"@
[IO.File]::WriteAllText($temporaryHtaccess,$htaccess,[Text.UTF8Encoding]::new($false))
$htaccessReplaced=$false
try {
    Receive "$base/public/assets/.htaccess" $originalHtaccess $cred
    Send $temporaryHtaccess "$base/public/assets/.htaccess" $cred
    $htaccessReplaced=$true
    Send $installer "$base/public/assets/$name" $cred
    $result=Invoke-RestMethod -Method Post -Uri "https://fingenieria.mx/assets/$name" -Headers @{'X-Migration-Token'=$token} -TimeoutSec 90
    if(-not $result.ok -or $result.tables.Count-ne 4){throw ('La migración no se verificó: '+($result|ConvertTo-Json -Compress))}
    Send $migration "$base/database/migrations/015_estructura_organizacional.sql" $cred
    $result
} finally {
    Remove-Remote "$base/public/assets/$name" $cred
    if($htaccessReplaced -and (Test-Path $originalHtaccess)){Send $originalHtaccess "$base/public/assets/.htaccess" $cred}
    if(Test-Path $tempDir){Remove-Item -LiteralPath $tempDir -Recurse -Force}
}
