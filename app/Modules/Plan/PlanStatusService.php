<?php
declare(strict_types=1);
namespace App\Modules\Plan;

/** Resumen público de hitos; no expone correos ni respuestas individuales. */
final class PlanStatusService
{
    public static function all(): array
    {
        $draft = PlanRepository::read('preliminar_fi.json');
        $interpretations = PlanRepository::read('foda_interpretaciones.json')['proyectos'] ?? [];
        $surveyStatus = PlanSurveyStore::status();
        $catalog = PlanSurveyStore::catalog();
        $surveys = array_column($catalog['encuestas'], null, 'id');
        $revisions = self::revisions();
        $projects = [];
        foreach ($draft['proyectos'] as $proposal) {
            $number = (int)$proposal['numero'];
            $tags = $proposal['pendientes_foda'] ?? [];
            $needed = array_values(array_unique(array_filter(array_map(
                static fn($tag) => preg_match('/^(C\d\d)-P\d\d$/', (string)$tag, $m) ? $m[1] : null,
                $tags
            ))));
            $agenda = array_values(array_unique(array_filter($tags,
                static fn($tag) => str_starts_with((string)$tag, 'AG-'))));
            $interpretation = $interpretations[(string)$number] ?? [];
            $processed = $interpretation['encuestas_procesadas'] ?? [];
            $surveyRows = [];
            foreach ($needed as $id) {
                $stats = $surveyStatus[$id] ?? ['respondidas' => 0, 'meta' => null, 'porcentaje' => null];
                $summary = 'Sin suficientes respuestas para publicar un cruce anónimo.';
                $available = $stats['respondidas'] >= ($surveys[$id]['segmentacion'] === 'coordinacion' ? 1 : 5);
                if ($surveys[$id]['segmentacion'] === 'coordinacion' && $available) {
                    $summary = 'Respuesta de coordinación recibida; el cargo es reconocible y su contenido requiere revisión manual.';
                } elseif ($available) {
                    $cross = PlanSurveyStore::cross($id, 0);
                    $best = ['n' => 0, 'fila' => '', 'columna' => ''];
                    foreach ($cross['valores'] ?? [] as $i => $row) {
                        foreach ($row as $j => $count) {
                            if ($count > $best['n']) $best = ['n' => $count,
                                'fila' => $cross['filas'][$i], 'columna' => $cross['columnas'][$j]];
                        }
                    }
                    $summary = $best['n'] > 0
                        ? "Cruce más frecuente: {$best['fila']} / {$best['columna']} ({$best['n']} respuestas); requiere interpretación y revisión de sesgos."
                        : 'Hay respuestas agregadas, pero el cruce seleccionado aún no muestra una asociación interpretable.';
                }
                $surveyRows[] = [
                    'id' => $id, 'titulo' => $surveys[$id]['titulo'] ?? $id,
                    'preguntas' => array_values(array_filter($tags, static fn($tag) => str_starts_with((string)$tag, $id . '-'))),
                    'respondidas' => $stats['respondidas'], 'meta' => $stats['meta'],
                    'porcentaje' => $stats['porcentaje'], 'datos_disponibles' => $available,
                    'procesada' => isset($processed[$id]) || in_array($id, $processed, true),
                    'resumen' => $summary,
                ];
            }
            $people = [];
            foreach ($revisions[$number] ?? [] as $entry) {
                $content = $entry['contenido'] ?? [];
                $name = trim((string)($entry['autor']['nombre'] ?? 'Sin nombre'));
                $email = strtolower(trim((string)($entry['autor']['correo'] ?? '')));
                $key = $email !== '' ? hash('sha256', $email) : hash('sha256', $name);
                $personalFoda = !empty($content['foda_modificada']);
                $selected = ($content['diagnostico_fuente'] ?? '') === 'foda_usuario';
                $diagnosisEdited = !empty($content['diagnostico_modificado']);
                $meeting = trim((string)($content['reunion_resultados'] ?? ''));
                $ready = $content['encuestas_listas'] ?? [];
                $allReady = !array_filter($surveyRows,
                    static fn($item) => empty($ready[$item['id']]));
                $allProcessed = !array_filter($surveyRows, static fn($item) => !$item['procesada']);
                $meetingReady = !$agenda || $meeting !== '';
                $template = !empty($content['plantilla_completada']) && trim((string)($content['plantilla_referencia'] ?? '')) !== '';
                $milestones = [
                    'foda_propio' => $personalFoda, 'foda_elegido' => $selected,
                    'foda_revision_solicitada' => !empty($content['solicitar_integracion_foda']),
                    'diagnostico_editado' => $diagnosisEdited,
                    'insumos_listos' => $allReady && $meetingReady,
                    'plantilla_completada' => $template,
                ];
                $people[$key] = [
                    'nombre' => $name, 'identidad' => $entry['autor']['identidad'] ?? 'declarada_sin_verificar',
                    'fecha' => $entry['fecha'] ?? '', 'version' => $entry['version'] ?? 0,
                    'hitos' => $milestones, 'avance' => round(100 * count(array_filter($milestones)) / count($milestones)),
                    'completado' => !in_array(false, $milestones, true),
                    'encuestas_listas' => array_values(array_keys(array_filter($ready))),
                    'insumos_procesados' => $allProcessed,
                    'reunion_resultados' => $meeting, 'reunion_fecha' => $content['reunion_fecha'] ?? '',
                    'plantilla_referencia' => $content['plantilla_referencia'] ?? '',
                ];
            }
            $people = array_values($people);
            usort($people, static fn($a, $b) => strcmp((string)$b['fecha'], (string)$a['fecha']));
            foreach ($surveyRows as &$surveyRow) {
                $surveyRow['responsables_listos'] = array_values(array_map(
                    static fn($person) => $person['nombre'],
                    array_filter($people, static fn($person) => in_array($surveyRow['id'], $person['encuestas_listas'], true))
                ));
            }
            unset($surveyRow);
            $mine = PlanDraftStore::peek($number)['revisiones'] ?? [];
            $myLast = $mine ? end($mine) : null;
            $myContent = $myLast['contenido'] ?? [];
            $projects[] = [
                'numero' => $number, 'pilar' => $proposal['pilar'], 'nombre' => $proposal['nombre'],
                'responsables' => $people, 'completado' => (bool)array_filter($people, static fn($p) => $p['completado']),
                'avance' => $people ? max(array_column($people, 'avance')) : 0,
                'encuestas' => $surveyRows, 'agenda' => $agenda,
                'interpretacion_foda' => !empty($interpretation['foda']) && !empty($interpretation['prosa']),
                'mi_revision' => $myLast !== null,
                'mis_encuestas_listas' => array_values(array_keys(array_filter($myContent['encuestas_listas'] ?? []))),
                'mi_plantilla_completada' => !empty($myContent['plantilla_completada']),
                'mi_plantilla_referencia' => $myContent['plantilla_referencia'] ?? '',
            ];
        }
        return [
            'proyectos' => $projects, 'total' => count($projects),
            'completados' => count(array_filter($projects, static fn($p) => $p['completado'])),
            'nota_autoria' => 'Los nombres sin cuenta son declarados por quien guardó cada revisión; el sistema no verifica su identidad.',
        ];
    }

    private static function revisions(): array
    {
        $root = dirname(APP_RAIZ) . '/storage/plan/revisiones';
        $out = [];
        foreach (glob($root . '/*/proyecto-*.json') ?: [] as $file) {
            if (!preg_match('/proyecto-(\d+)\.json$/', $file, $match)) continue;
            $number = (int)$match[1];
            $data = json_decode((string)file_get_contents($file), true);
            if (!is_array($data)) continue;
            foreach ($data['revisiones'] ?? [] as $revision) {
                if (is_array($revision)) $out[$number][] = $revision;
            }
        }
        foreach ($out as &$items) {
            usort($items, static fn($a, $b) => strcmp((string)($a['fecha'] ?? ''), (string)($b['fecha'] ?? '')));
        }
        return $out;
    }
}
