<?php
declare(strict_types=1);
namespace App\Modules\Plan;

final class PlanRepository
{
    private static function path(string $name): string
    {
        return dirname(APP_RAIZ) . '/Plan/' . $name;
    }

    public static function read(string $name): array
    {
        $raw = file_get_contents(self::path($name));
        if ($raw === false) throw new \RuntimeException("No se pudo leer $name");
        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \RuntimeException("Formato inválido: $name");
        return $data;
    }

    public static function catalog(): array
    {
        $prdi = self::read('PRDI.json');
        $draft = self::read('preliminar_fi.json');
        $available = array_fill_keys(array_column($draft['proyectos'], 'numero'), true);
        $pillars = [];
        foreach ($prdi['pilares'] as $pillar) {
            $projects = [];
            foreach ($pillar['proyectos'] as $project) {
                if (!isset($available[$project['numero']])) continue;
                $projects[] = ['numero' => $project['numero'], 'nombre' => $project['nombre']];
            }
            if ($projects) $pillars[] = [
                'numero_romano' => $pillar['numero_romano'], 'nombre' => $pillar['nombre'],
                'proyectos' => $projects,
            ];
        }
        return $pillars;
    }

    public static function project(int $number): array
    {
        $draft = self::read('preliminar_fi.json');
        $proposal = null;
        foreach ($draft['proyectos'] as $item) {
            if ((int)$item['numero'] === $number) { $proposal = $item; break; }
        }
        if (!$proposal) throw new \OutOfBoundsException('Proyecto no disponible.');
        $prdiProject = null;
        foreach (self::read('PRDI.json')['pilares'] as $pillar) {
            foreach ($pillar['proyectos'] as $item) {
                if ((int)$item['numero'] === $number) { $prdiProject = $item; break 2; }
            }
        }
        $evidence = [];
        foreach ($proposal['evidencia'] as $reference) {
            $file = basename((string)$reference['archivo']);
            if (!preg_match('/^\d{4}_[a-z0-9_]+\.json$/', $file)) continue;
            $category = self::read($file);
            $faculty = self::filterSection($category['facultad'], $number);
            $careers = [];
            foreach ($category['carreras'] as $name => $section) {
                $filtered = self::filterSection($section, $number);
                if ($filtered['indicadores']) $careers[$name] = $filtered;
            }
            if ($faculty['indicadores'] || $careers) $evidence[] = [
                'id_categoria' => $category['id_categoria'], 'categoria' => $category['categoria'],
                'url_categoria' => $category['url_categoria'], 'facultad' => $faculty,
                'carreras' => $careers,
                'cita_apa' => $category['cita_apa'] ?? '',
                'referencia_apa' => $category['referencia_apa'] ?? '',
                'conciliacion_ultimo_periodo' => $category['conciliacion_ultimo_periodo'] ?? null,
            ];
        }
        $interpretations = is_file(self::path('foda_interpretaciones.json'))
            ? self::read('foda_interpretaciones.json') : ['proyectos' => []];
        return ['proyecto' => $number, 'prdi' => $prdiProject,
                'propuesta' => $proposal, 'evidencia' => $evidence,
                'interpretacion_foda' => $interpretations['proyectos'][(string)$number] ?? null,
                'fuentes' => [
                    'PRDI' => 'Plan/PRDI.json', 'propuesta' => 'Plan/preliminar_fi.json',
                    'guia' => 'Guía para la formulación de planes de desarrollo, pp. 20-29',
                    'tablero' => 'https://spdi2.uaemex.mx/tableros/public/index.php/mosaico/uaemex',
                    'memoria_2026' => 'Plan/memoria_calculo_2026.json',
                ]];
    }

    private static function filterSection(array $section, int $number): array
    {
        $indicators = [];
        foreach ($section['indicadores'] as $indicator) {
            $rows = array_values(array_filter($indicator['registros'],
                static fn(array $row): bool => in_array($number, $row['proyectos'] ?? [], true)));
            if (!$rows) continue;
            $indicator['registros'] = $rows;
            $indicator['numero_registros'] = count($rows);
            $indicators[] = $indicator;
        }
        return ['nombre' => $section['nombre'], 'nivel' => $section['nivel'],
                'indicadores' => $indicators, 'foda' => $section['foda']];
    }
}
