<?php
declare(strict_types=1);
function e($s): string { return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
ob_start();
require __DIR__.'/../app/Modules/Recursos/views/calendario_2026_b.php';
$html=ob_get_clean();
$check=function(bool $ok,string $label) { if (!$ok) throw new RuntimeException($label); };
$check(count($meses)===6,'Seis meses');
$check(count($eventos['2026-09-28'])===2,'Dos eventos el 28 de septiembre');
$check(!isset($eventos['2026-09-27']),'Sin parcial el domingo 27');
$check(!isset($eventos['2026-12-26']),'No inventar vacaciones');
$check($eventos['2027-01-25'][0]['tipo']==='tramites','Entrega de actas');
foreach ($eventos as $fecha=>$lista) {
    $date=DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
    $check($date && $date->format('Y-m-d')===$fecha,'Fecha válida');
    $check(str_contains($html,'data-escolar-fecha="'.$fecha.'"'),'Fecha pulsable');
}
preg_match('/const eventos=(.*);/',$html,$matches);
$check(json_decode($matches[1],true,512,JSON_THROW_ON_ERROR)===$eventos,'Detalles completos en JS');
echo 'Calendario: seis meses, '.count($eventos)." fechas señaladas y detalles verificados.\n";
