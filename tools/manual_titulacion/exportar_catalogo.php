<?php
/** Exporta el catálogo de etapas y documentos por opción (para generar los manuales). Uso: php tools/manual_titulacion/exportar_catalogo.php */
require dirname(__DIR__, 2) . '/app/bootstrap.php';
$config = require APP_RAIZ . '/config.php';
$p = new ReflectionProperty(App\Core\App::class, 'config'); $p->setAccessible(true); $p->setValue(null, $config);
use App\Modules\Titulacion\Catalogo;
$out = [];
App\Core\AccessControl::internal(function () use (&$out) {
foreach (Catalogo::modalidades() as $k => $m) {
    $variante = $k === 'egel' ? 'pagando_menos' : null;
    $et = [];
    foreach (Catalogo::etapasBase($k, $variante) as $e) {
        $et[] = ['clave' => $e['clave'], 'titulo' => $e['titulo'], 'tipo' => $e['tipo'], 'descripcion' => $e['descripcion'] ?? '',
            'campos' => array_map(fn($c) => $c['label'], $e['campos'] ?? []),
            'formatos' => $e['formatos'] ?? [],
            'docs' => array_map(fn($d) => ['nombre' => $d['nombre'], 'nota' => $d['nota'], 'condicion' => $d['condicion'], 'generado' => $d['generado']], $e['documentos'] ?? []),
            'personal' => isset($e['personal']) ? ['titulo' => $e['personal']['titulo'], 'descripcion' => $e['personal']['descripcion'] ?? '', 'campos' => array_map(fn($c) => $c['label'], $e['personal']['campos'] ?? []), 'entrega' => $e['personal']['entrega']['nombre'] ?? null] : null,
            'agenda' => $e['agenda'] ?? null, 'confirmar' => $e['confirmar'] ?? null];
    }
    $out[$k] = ['renglones' => array_map(fn($r) => ['texto' => $r['texto'], 'cond' => $r['cond'], 'doc' => $r['docs'] !== [], 'generado' => $r['generado'] ?? null], App\Modules\Titulacion\Services\ExpedienteFinal::renglones($k)), 'titulo_exp' => App\Modules\Titulacion\Services\ExpedienteFinal::titulo($k), 'nombre' => $m['nombre'], 'tipo' => $m['tipo'], 'art' => $m['art'], 'resumen' => $m['resumen'], 'requisitos' => $m['requisitos'], 'etapas' => $et];
}
});
file_put_contents(__DIR__ . '/catalogo.json', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo count($out), "\n";
