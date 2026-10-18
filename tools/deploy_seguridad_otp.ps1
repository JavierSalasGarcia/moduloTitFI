[CmdletBinding()]
param([string]$CredentialsPath=(Join-Path $PSScriptRoot '..\cred.txt'))
$ErrorActionPreference='Stop'
function Get-LabeledValue([string[]]$Lines,[string]$Label){for($i=0;$i-lt$Lines.Count;$i++){if($Lines[$i]-match('^\s*'+[regex]::Escape($Label)+'\s*:\s*(.*)$')){if($Matches[1].Trim()){return $Matches[1].Trim()};for($j=$i+1;$j-lt$Lines.Count;$j++){if($Lines[$j].Trim()){return $Lines[$j].Trim()}}}};throw "Falta $Label."}
function Request([string]$Uri,[string]$Method,[pscredential]$Credential){$r=[Net.FtpWebRequest]::Create($Uri);$r.Method=$Method;$r.Credentials=$Credential.GetNetworkCredential();$r.UseBinary=$true;$r.UsePassive=$true;$r.KeepAlive=$false;return $r}
function Send([string]$Path,[string]$Uri,[pscredential]$Credential){$b=[IO.File]::ReadAllBytes($Path);$r=Request $Uri ([Net.WebRequestMethods+Ftp]::UploadFile) $Credential;$r.ContentLength=$b.Length;$s=$r.GetRequestStream();try{$s.Write($b,0,$b.Length)}finally{$s.Dispose()};$p=$r.GetResponse();$p.Dispose()}
function Remove([string]$Uri,[pscredential]$Credential) {
    try {
        $r=Request $Uri ([Net.WebRequestMethods+Ftp]::DeleteFile) $Credential
        $p=$r.GetResponse(); $p.Dispose()
    } catch [Net.WebException] {
        if (-not $_.Exception.Response -or [int]$_.Exception.Response.StatusCode -ne 550) { throw }
    }
}
$lines=Get-Content -Encoding UTF8 -LiteralPath $CredentialsPath;$base=(Get-LabeledValue $lines 'SITIO').TrimEnd('/');$user=Get-LabeledValue $lines 'USUARIO';$pass=ConvertTo-SecureString (Get-LabeledValue $lines 'CONTRASEÑA') -AsPlainText -Force;$cred=[pscredential]::new($user,$pass)
$nonce=[guid]::NewGuid().ToString('N');$token=([guid]::NewGuid().ToString('N')+[guid]::NewGuid().ToString('N'));$name="_otp_migration_$nonce.php";$temp=Join-Path ([IO.Path]::GetTempPath()) $name
$php=@"
<?php
declare(strict_types=1);
header('Content-Type: application/json'); header('Cache-Control: no-store');
if (!hash_equals('$token', (string)(`$_SERVER['HTTP_X_MIGRATION_TOKEN'] ?? ''))) { http_response_code(404); exit('{}'); }
try {
  `$config=require dirname(__DIR__, 2) . '/app/config.php'; `$db=`$config['db'];
  `$pdo=new PDO('mysql:host='.`$db['host'].';dbname='.`$db['nombre'].';charset='.`$db['charset'],`$db['usuario'],`$db['clave'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  `$before=`$pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios_codigos_otp' AND COLUMN_NAME='codigo'")->fetchColumn();
  if ((int)`$before < 255) { `$pdo->exec("ALTER TABLE usuarios_codigos_otp MODIFY COLUMN codigo VARCHAR(255) NOT NULL"); }
  `$after=`$pdo->query("SELECT CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='usuarios_codigos_otp' AND COLUMN_NAME='codigo'")->fetchColumn();
  echo json_encode(['ok'=>(int)`$after===255,'before'=>(int)`$before,'after'=>(int)`$after]);
} catch (Throwable `$e) { error_log('OTP migration: '.`$e->getMessage()); http_response_code(500); echo json_encode(['ok'=>false]); }
"@
[IO.File]::WriteAllText($temp,$php,[Text.UTF8Encoding]::new($false))
try {
    Send $temp "$base/public/assets/$name" $cred
    $result=Invoke-RestMethod -Method Post -Uri "https://fingenieria.mx/assets/$name" -Headers @{'X-Migration-Token'=$token} -TimeoutSec 60
    if (-not $result.ok -or $result.after -ne 255) { throw ('La migración OTP no se verificó: ' + ($result | ConvertTo-Json -Compress)) }
    $result
} finally {
    Remove "$base/public/assets/$name" $cred
    if (Test-Path -LiteralPath $temp) { Remove-Item -LiteralPath $temp -Force }
}
