<?php
declare(strict_types=1);
namespace App\Modules\Plan;

/** Minimal OOXML workbook with typed numeric cells and no external dependency. */
final class PlanXlsx
{
    private static function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function column(int $index): string
    {
        $name = '';
        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $name = chr(65 + ($index - 1) % 26) . $name;
        }
        return $name;
    }

    private static function source2027(array $variable): string
    {
        $source = is_array($variable['fuente_programacion_2027'] ?? null) ? $variable['fuente_programacion_2027'] : [];
        if (!$source) return 'Estimación metodológica pendiente de documentar.';
        $labels = ['tablero_uaemex' => 'Tablero UAEMéx', 'calculo_con_tablero' => 'Cálculo con Tablero UAEMéx',
            'poa_2026' => 'POA 2026', 'estimacion' => 'Estimación'];
        $parts = [$labels[(string)($source['tipo'] ?? '')] ?? 'Fuente documentada'];
        $files = array_values(array_filter(array_merge(
            is_array($source['archivos'] ?? null) ? $source['archivos'] : [],
            isset($source['archivo']) ? [(string)$source['archivo']] : []
        )));
        if ($files) $parts[] = implode(', ', array_unique($files));
        if (!empty($source['pagina'])) $parts[] = 'p. ' . $source['pagina'];
        if (!empty($source['metrica'])) $parts[] = 'métrica «' . $source['metrica'] . '»';
        if (!empty($source['periodo'])) $parts[] = 'periodo ' . $source['periodo'];
        if (array_key_exists('valor_base', $source)) $parts[] = 'valor base ' . $source['valor_base'];
        if (!empty($source['formula'])) $parts[] = 'Cálculo: ' . $source['formula'];
        return implode(' · ', $parts);
    }

    private static function sheet(array $rows): string
    {
        $lastColumn = self::column(max(0, count($rows[0] ?? []) - 1));
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<dimension ref="A1:' . $lastColumn . max(1, count($rows)) . '"/>'
            . '<sheetViews><sheetView workbookViewId="0"/></sheetViews><sheetFormatPr defaultRowHeight="18"/>'
            . '<sheetData>';
        foreach ($rows as $r => $row) {
            $xml .= '<row r="' . ($r + 1) . '">';
            foreach (array_values($row) as $c => $value) {
                $ref = self::column($c) . ($r + 1);
                $style = $r === 0 ? ' s="1"' : '';
                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="' . $ref . '"' . $style . '><v>' . $value . '</v></c>';
                } else {
                    $string = self::xml((string)$value);
                    $xml .= '<c r="' . $ref . '" t="inlineStr"' . $style
                        . '><is><t xml:space="preserve">' . $string . '</t></is></c>';
                }
            }
            $xml .= '</row>';
        }
        return $xml . '</sheetData></worksheet>';
    }

    public static function create(array $sheets): string
    {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException('La extensión ZIP de PHP es necesaria para Excel.');
        $file = tempnam(sys_get_temp_dir(), 'fi-plan-');
        if ($file === false) throw new \RuntimeException('No se pudo crear el archivo Excel.');
        $zip = new \ZipArchive();
        if ($zip->open($file, \ZipArchive::OVERWRITE) !== true) throw new \RuntimeException('No se pudo abrir el archivo Excel.');
        $names = array_keys($sheets);
        $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        $types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        foreach ($sheets as $name => $rows) {
            $index = array_search($name, $names, true) + 1;
            $workbook .= '<sheet name="' . self::xml(substr($name, 0, 31)) . '" sheetId="' . $index . '" r:id="rId' . $index . '"/>';
            $rels .= '<Relationship Id="rId' . $index . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $index . '.xml"/>';
            $types .= '<Override PartName="/xl/worksheets/sheet' . $index . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $zip->addFromString('xl/worksheets/sheet' . $index . '.xml', self::sheet($rows));
        }
        $workbook .= '</sheets></workbook>';
        $rels .= '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
        $types .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $types);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $workbook);
        $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Aptos"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Aptos"/></font></fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF526855"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFill="1" applyFont="1"/></cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $zip->close();
        return $file;
    }

    public static function rows(array $bundle): array
    {
        $data = [['Categoría', 'Nivel', 'Programa', 'Visual', 'Periodo', 'Campo', 'Valor', 'Fuente', 'Proyectos']];
        foreach ($bundle['evidencia'] as $category) {
            foreach ([$category['facultad'], ...array_values($category['carreras'])] as $section) {
                foreach ($section['indicadores'] as $indicator) {
                    foreach ($indicator['registros'] as $row) {
                        $period = $row['Ciclo escolar'] ?? $row['Año'] ?? $row['ciclo_escolar'] ?? '';
                        foreach ($row as $field => $value) {
                            if (!is_int($value) && !is_float($value)) continue;
                            if ($field === 'Año') continue;
                            $data[] = [$category['categoria'], $section['nivel'], $section['nombre'],
                                $indicator['visual'], (string)$period, $field, $value,
                                $category['url_categoria'], implode(', ', $row['proyectos'] ?? [])];
                        }
                    }
                }
            }
        }
        $proposal = $bundle['propuesta'];
        $revisions = $bundle['revisiones'];
        $latest = $revisions ? end($revisions) : null;
        $edited = $latest['contenido'] ?? null;
        $matrix = [['Versión', 'Elemento', 'Texto', 'Meta', 'Línea base', 'Periodo base', 'Valor 2030', 'Método']];
        foreach ([['Propuesta FI', $proposal], ['Edición', $edited]] as [$label, $content]) {
            if (!$content) continue;
            $matrix[] = [$label, 'Diagnóstico', (string)($content['diagnostico'] ?? '')];
            $matrix[] = [$label, 'Objetivo general', (string)($content['objetivo_general'] ?? '')];
            $matrix[] = [$label, 'Síntesis FODA en prosa', (string)($content['prosa_foda'] ?? '')];
            if ($label === 'Propuesta FI') $matrix[] = [$label, 'Referencia de trabajo', '{Planeación FI360, ' . (string)($content['alineacion_fi360']['referencia'] ?? '') . '}'];
            if ($label === 'Edición') {
                $matrix[] = [$label, 'Bandera de integración FODA', !empty($content['solicitar_integracion_foda']) ? 'Sí' : 'No'];
                $matrix[] = [$label, 'Matriz FODA propia modificada', !empty($content['foda_modificada']) ? 'Sí' : 'No'];
                $matrix[] = [$label, 'Base del diagnóstico', (string)($content['diagnostico_fuente'] ?? '')];
                $matrix[] = [$label, 'Resultados de reunión', (string)($content['reunion_resultados'] ?? '')];
                $matrix[] = [$label, 'Fecha de reunión', (string)($content['reunion_fecha'] ?? '')];
                $matrix[] = [$label, 'Encuestas listas para procesar', implode(', ', array_keys(array_filter($content['encuestas_listas'] ?? [])))];
                $matrix[] = [$label, 'Archivo de plantilla terminado', !empty($content['plantilla_completada']) ? 'Sí' : 'No'];
                $matrix[] = [$label, 'Referencia de plantilla', (string)($content['plantilla_referencia'] ?? '')];
            }
            foreach ($content['politicas'] ?? [] as $policy) {
                $matrix[] = [$label, 'Política ' . ($policy['id'] ?? ''), (string)($policy['texto'] ?? ''), implode(', ', $policy['objetivos'] ?? [])];
            }
            foreach ($content['objetivos_especificos'] ?? [] as $specific) {
                $matrix[] = [$label, 'Objetivo específico ' . ($specific['id'] ?? ''), (string)($specific['texto'] ?? '')];
                foreach ($specific['estrategias'] ?? [] as $strategy) {
                    foreach ($strategy['metas'] ?? [] as $meta) {
                        $matrix[] = [$label, 'Estrategia ' . ($strategy['id'] ?? ''), (string)($strategy['texto'] ?? ''),
                            (string)($meta['descripcion'] ?? ''), $meta['valor_base'] ?? '',
                            $meta['periodo_base'] ?? '', $meta['valor_meta_2030'] ?? '',
                            (string)($meta['metodo'] ?? '')];
                    }
                }
                if (isset($specific['politica'])) $matrix[] = [$label, 'Política anterior', (string)$specific['politica']];
                if (isset($specific['estrategia'])) {
                    $meta = $specific['meta'] ?? [];
                    $matrix[] = [$label, 'Estrategia anterior', (string)$specific['estrategia'], (string)($meta['descripcion'] ?? '')];
                }
            }
        }
        $foda = [['Versión', 'Factor', 'Descripción', 'Categoría fuente', 'Ámbito', 'Fuente externa', 'URL', 'Alcance']];
        foreach ([['Propuesta FI', $proposal['foda']], ['Edición', $edited['foda'] ?? []]] as [$label, $matrixFoda]) {
            foreach (['fortalezas','oportunidades','debilidades','amenazas'] as $key) {
                foreach ($matrixFoda[$key] ?? [] as $factor) {
                    $text = is_array($factor) ? ($factor['texto'] ?? (($factor['metrica'] ?? '') . ': ' . ($factor['valor_inicial'] ?? '') . ' → ' . ($factor['valor_final'] ?? ''))) : (string)$factor;
                    $foda[] = [$label, ucfirst($key), $text,
                        is_array($factor) ? ($factor['categoria'] ?? '') : '',
                        is_array($factor) ? ($factor['ambito'] ?? '') : '',
                        is_array($factor) ? ($factor['fuente'] ?? '') : '',
                        is_array($factor) ? ($factor['url'] ?? '') : '',
                        is_array($factor) ? ($factor['limite'] ?? '') : ''];
                }
            }
        }
        $foda[] = ['Propuesta FI', 'Síntesis en prosa', (string)($proposal['prosa_foda'] ?? ''), '', ''];
        if ($edited) $foda[] = ['Edición', 'Síntesis en prosa', (string)($edited['prosa_foda'] ?? ''), '', ''];
        $memory = [['Clave', 'Tipo', 'Meta PRDI', 'Meta del espacio académico', 'Método de cálculo',
            'Valor Ref. 2026', 'Estado Ref. 2026', 'Variables Ref. 2026', 'Fuentes y cálculo 2027',
            'Variables 2027', 'Variables 2028', 'Variables 2029', 'Variables 2030',
            'Indicador 2027', 'Indicador 2028', 'Indicador 2029', 'Indicador 2030',
            'Medición', 'Área responsable', 'Criterio de programación']];
        $activeContent = $edited ?: $proposal;
        $editableGoals = [];
        foreach ($activeContent['objetivos_especificos'] ?? [] as $specific) {
            foreach ($specific['estrategias'] ?? [] as $strategy) {
                foreach ($strategy['metas'] ?? [] as $goal) {
                    $goalKey = strtoupper(str_replace(' ', '', (string)($goal['indicador'] ?? '')));
                    if ($goalKey !== '') $editableGoals[$goalKey] = $goal;
                }
            }
        }
        foreach ($proposal['referencia_base_2026']['metas'] ?? [] as $item) {
            $variables = implode('; ', array_map(static fn(array $v): string => ($v['nombre'] ?? '') . ': '
                . (($v['valor_ref_2026'] ?? null) === null ? 'n.d.' : (string)$v['valor_ref_2026']), $item['variables'] ?? []));
            $sources2027 = implode('; ', array_map(static fn(array $v): string => ($v['nombre'] ?? '') . ': '
                . self::source2027($v), $item['variables'] ?? []));
            $itemKey = strtoupper(str_replace(' ', '', (string)($item['clave'] ?? '')));
            $activeGoal = $editableGoals[$itemKey] ?? [];
            $editedPrograms = [];
            foreach ($activeGoal['programacion_variables'] ?? [] as $variable) {
                if (is_array($variable)) $editedPrograms[(string)($variable['nombre'] ?? '')] = $variable['programacion'] ?? [];
            }
            $annualVariables = [];
            foreach (['2027','2028','2029','2030'] as $year) {
                $annualVariables[] = implode('; ', array_map(static function (array $v) use ($year, $editedPrograms): string {
                    $name = (string)($v['nombre'] ?? '');
                    $value = $editedPrograms[$name][$year] ?? ($v['programacion'][$year] ?? null);
                    return $name . ': ' . ($value === null ? 'n.d.' : (string)$value);
                }, $item['variables'] ?? []));
            }
            $indicators = array_map(static fn(string $year) => $item['proyeccion_indicador'][$year] ?? 'n.d.', ['2027','2028','2029','2030']);
            $localGoal = (string)($activeGoal['descripcion'] ?? ($item['meta_espacio_academico'] ?? ''));
            $memory[] = [(string)($item['clave'] ?? ''), (string)($item['tipo'] ?? ''), (string)($item['meta_prdi'] ?? ''),
                $localGoal, (string)($item['metodo_calculo'] ?? ''),
                $item['valor_ref_2026'] ?? 'n.d.', (string)($item['estado_ref_2026'] ?? ''), $variables, $sources2027,
                ...$annualVariables, ...$indicators, (string)($item['medicion'] ?? 'Anual'),
                (string)($item['area_responsable'] ?? ''), (string)($item['criterio_programacion'] ?? '')];
        }
        $references = [['Referencia APA']];
        foreach ($proposal['referencias_apa'] ?? [] as $reference) $references[] = [(string)$reference];
        return ['Datos' => $data, 'Matriz' => $matrix, 'FODA' => $foda,
            'Memoria 2026' => $memory, 'Referencias APA' => $references];
    }
}
