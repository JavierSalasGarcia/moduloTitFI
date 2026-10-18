<?php
// Cron (cada 5 minutos): php tools/titulacion_piloto.php
if (PHP_SAPI !== 'cli') {
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
$n = App\Modules\Titulacion\Services\SubdireccionFirma::procesarPiloto();
echo date('c') . " firmas automáticas: $n\n";
