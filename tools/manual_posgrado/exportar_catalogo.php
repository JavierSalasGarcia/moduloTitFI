<?php
/** Exporta el catálogo de Posgrado (momentos, requisitos, modalidades, firmables) a catalogo.json para los manuales.
 *  Uso: php tools/manual_posgrado/exportar_catalogo.php */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/app/bootstrap.php';
(new ReflectionProperty(App\Core\App::class, 'config'))->setValue(null, require APP_RAIZ . '/config.php');
use App\Modules\Posgrado\Catalogo;
use App\Modules\Posgrado\Services\Manuales;
$out = [];
App\Core\AccessControl::internal(function () use (&$out): void {
    $mods = [];
    foreach (Catalogo::modalidades() as $k => $n) {
        $mods[$k] = ['nombre' => $n, 'trabajo' => Catalogo::trabajo($k), 'descripcion' => Manuales::descripcion($k)];
    }
    $out = ['modalidades' => $mods, 'momentos' => Catalogo::momentos(), 'grupos' => Catalogo::grupos(), 'requisitos' => Catalogo::requisitos(),
        'firmables' => Catalogo::firmables(), 'emitibles' => Catalogo::emitibles(), 'dias_minimos' => Catalogo::DIAS_MINIMOS, 'dias_sugeridos' => Catalogo::DIAS_SUGERIDOS];
});
file_put_contents(__DIR__ . '/catalogo.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo count($out['requisitos']), " requisitos\n";
