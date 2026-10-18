<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/Totp.php';
$secret='JBSWY3DPEHPK3PXP'; // Secreto ficticio, no corresponde a ninguna cuenta.
$uri=\App\Core\Totp::uri('jsalasg@uaemex.mx',$secret);
$parts=parse_url($uri);
parse_str($parts['query'],$query);
$checks=[
    $parts['scheme']==='otpauth',
    $parts['host']==='totp',
    rawurldecode(ltrim($parts['path'],'/'))==='Traza FI:jsalasg@uaemex.mx',
    $query['issuer']==='Traza FI',
    $query['secret']===$secret,
    !str_contains($uri,'PlataformaFI'),
];
if (in_array(false,$checks,true)) { fwrite(STDERR,"Falló la marca TOTP.\n"); exit(1); }
echo count($checks)." comprobaciones TOTP correctas.\n";
