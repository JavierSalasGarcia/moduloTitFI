<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

use App\Modules\Plan\PlanController;

$draft = json_decode(file_get_contents(dirname(__DIR__) . '/Plan/preliminar_fi.json'), true, 512, JSON_THROW_ON_ERROR);
$method = new ReflectionMethod(PlanController::class, 'validContent');
foreach ($draft['proyectos'] as $project) {
    $content = $project;
    $content['foda'] = array_map(static fn(array $factors): array => array_map(
        static fn(array $factor): string => (string)($factor['texto'] ?? $factor['metrica'] ?? 'Dato'), $factors), $project['foda']);
    $content += ['diagnostico_modificado' => false, 'diagnostico_fuente' => 'propuesta_fi',
        'foda_modificada' => false, 'reunion_fecha' => '', 'reunion_resultados' => '',
        'encuestas_listas' => [], 'plantilla_completada' => false, 'plantilla_referencia' => ''];
    if (!$method->invoke(null, $content)) throw new RuntimeException('Matriz inválida en proyecto ' . $project['numero']);
    $invalid = $content;
    $invalid['politicas'][0]['objetivos'] = ['OE1', 'OE2'];
    if ($method->invoke(null, $invalid)) throw new RuntimeException('Política asignada a dos objetivos no rechazada.');
    $invalid = $content;
    $invalid['objetivos_especificos'][0]['estrategias'][0]['texto'] = 'Estrategia sin clasificación.';
    if ($method->invoke(null, $invalid)) throw new RuntimeException('Estrategia sin clasificación no rechazada.');
}
echo '21 matrices válidas; políticas con dueño único y etiquetas obligatorias verificadas.', PHP_EOL;
