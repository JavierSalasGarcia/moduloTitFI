<?php
declare(strict_types=1);
namespace App\Modules\Plan;

/** Respuestas privadas; sólo se exponen agregados, con correo voluntario en C01–C04, C16 y C17. */
final class PlanSurveyStore
{
    private const COOKIE = 'fi_plan_encuesta';

    public static function catalog(): array
    {
        $file = dirname(APP_RAIZ) . '/Plan/instrumentos.json';
        $data = json_decode((string)file_get_contents($file), true, 128, JSON_THROW_ON_ERROR);
        return $data;
    }

    public static function survey(string $id): ?array
    {
        foreach (self::catalog()['encuestas'] as $item) {
            if ($item['id'] === $id) return $item;
        }
        return null;
    }

    /** Corte agregado inmutable; no contiene respuestas abiertas, correos ni identificadores. */
    public static function snapshot(): ?array
    {
        $file = dirname(APP_RAIZ) . '/Plan/estadisticas_encuestas.json';
        if (!is_file($file)) return null;
        $data = json_decode((string)file_get_contents($file), true);
        return is_array($data) ? $data : null;
    }

    public static function isOpen(?string $id = null): bool
    {
        $catalog = self::catalog();
        if (($catalog['encuestas_abiertas'] ?? true) === true) return true;
        if ($id === null) return false;
        return in_array($id, $catalog['encuestas_abiertas_ids'] ?? [], true);
    }

    private static function links(): array
    {
        $file = dirname(APP_RAIZ) . '/Plan/enlaces_encuesta.json';
        $links = json_decode((string)file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        return is_array($links) ? $links : [];
    }

    public static function tokenFor(string $id): ?string
    {
        $token = self::links()[$id] ?? null;
        return is_string($token) && preg_match('/^[a-f0-9]{48}$/', $token) ? $token : null;
    }

    public static function surveyByToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) return null;
        foreach (self::links() as $id => $secret) {
            if (is_string($secret) && hash_equals($secret, $token)) return self::survey((string)$id);
        }
        return null;
    }

    private static function token(): string
    {
        $token = (string)($_COOKIE[self::COOKIE] ?? '');
        if (preg_match('/^[a-f0-9]{64}$/', $token)) return $token;
        $token = bin2hex(random_bytes(32));
        setcookie(self::COOKIE, $token, [
            'expires' => time() + 365 * 86400, 'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $token;
        return $token;
    }

    private static function file(string $id): string
    {
        if (!preg_match('/^C(0[1-9]|1[0-7])$/', $id)) throw new \OutOfBoundsException('Cuestionario no disponible.');
        $folder = dirname(APP_RAIZ) . '/storage/plan/encuestas';
        if (!is_dir($folder) && !mkdir($folder, 0770, true) && !is_dir($folder)) {
            throw new \RuntimeException('No se pudo crear el almacenamiento de encuestas.');
        }
        return $folder . '/' . $id . '.jsonl';
    }

    private static function records(string $id): array
    {
        $file = self::file($id);
        if (!is_file($file)) return [];
        $records = [];
        $handle = fopen($file, 'r');
        if (!$handle) return [];
        try {
            flock($handle, LOCK_SH);
            while (($line = fgets($handle)) !== false) {
                $record = json_decode($line, true);
                if (is_array($record)) $records[] = $record;
            }
            flock($handle, LOCK_UN);
        } finally { fclose($handle); }
        return $records;
    }

    public static function status(): array
    {
        $catalog = self::catalog();
        $out = [];
        foreach ($catalog['encuestas'] as $survey) {
            $records = self::records($survey['id']);
            $segments = [];
            foreach ($records as $record) {
                $segment = (string)($record['segmento'] ?? 'facultad');
                $segments[$segment] = ($segments[$segment] ?? 0) + 1;
            }
            $target = $survey['meta_respuestas'];
            $out[$survey['id']] = [
                'respondidas' => count($records), 'meta' => $target,
                'porcentaje' => $target ? round(100 * count($records) / $target, 1) : null,
                'segmentos' => $segments, 'base_meta' => $survey['base_meta'],
            ];
        }
        return $out;
    }

    public static function contacts(string $id): array
    {
        if (!in_array($id, ['C01', 'C02', 'C03', 'C04', 'C16', 'C17'], true)) {
            throw new \OutOfBoundsException('Este cuestionario no recoge correo voluntario.');
        }
        $survey = self::survey($id);
        if (!$survey) throw new \OutOfBoundsException('Cuestionario no disponible.');
        $emailId = '';
        $proposalId = '';
        foreach ($survey['preguntas'] as $question) {
            if (($question['tipo'] ?? '') === 'correo') $emailId = (string)$question['id'];
            if (($question['tipo'] ?? '') === 'abierta' && stripos((string)$question['texto'], 'propuestas concretas') !== false) {
                $proposalId = (string)$question['id'];
            }
        }
        if ($emailId === '' || $proposalId === '') throw new \RuntimeException('El instrumento no define sus campos de contacto y propuesta.');
        $rows = [];
        foreach (self::records($id) as $record) {
            $answers = $record['respuestas'] ?? [];
            $email = trim((string)($answers[$emailId] ?? ''));
            if ($email === '') continue;
            $rows[] = ['fecha' => (string)($record['fecha'] ?? ''), 'segmento' => (string)($record['segmento'] ?? ''),
                'correo' => $email, 'propuesta' => (string)($answers[$proposalId] ?? ''),
                'capitulo' => $id === 'C01' ? (string)($answers['C01-P12'] ?? '') : '',
                'necesidades_capitulo' => $id === 'C01' ? (string)($answers['C01-P13'] ?? '') : ''];
        }
        return $rows;
    }

    public static function submit(array $survey, array $input): void
    {
        if (!self::isOpen((string)($survey['id'] ?? ''))) {
            throw new \DomainException('La consulta ha cerrado y ya no recibe respuestas.');
        }
        if (!empty($input['website'])) throw new \InvalidArgumentException('No se pudo procesar el formulario.');
        $catalog = self::catalog();
        $segments = match ($survey['segmentacion']) {
            'carrera' => array_column($catalog['carreras'], 'id'),
            'programa' => $catalog['programas_posgrado'],
            default => ['facultad'],
        };
        $segment = $survey['segmentacion'] === 'coordinacion'
            ? (string)$survey['carrera'] : (string)($input['segmento'] ?? 'facultad');
        if (!in_array($segment, $segments, true) && $survey['segmentacion'] !== 'coordinacion') {
            throw new \InvalidArgumentException('Selecciona el programa o la carrera correspondiente.');
        }
        if ($survey['segmentacion'] === 'coordinacion' && $segment === '') $segment = $survey['id'];
        $answers = [];
        foreach ($survey['preguntas'] as $question) {
            if ($question['tipo'] === 'ranking_asignaturas') {
                $raw = $input[$question['id']] ?? [];
                if (!is_array($raw)) throw new \InvalidArgumentException('Selecciona las asignaturas desde la lista.');
                $ranking = array_values(array_filter(array_map(static fn($item): string => trim((string)$item), $raw)));
                $allowed = $question['opciones_por_segmento'][$segment] ?? [];
                if (!$ranking || count($ranking) > (int)($question['max_selecciones'] ?? 5)
                    || count(array_unique($ranking)) !== count($ranking)) {
                    throw new \InvalidArgumentException('Selecciona asignaturas distintas en orden de dificultad.');
                }
                foreach ($ranking as $subject) {
                    if (!in_array($subject, $allowed, true)) throw new \InvalidArgumentException('Una asignatura no corresponde a la licenciatura seleccionada.');
                }
                $answers[$question['id']] = $ranking;
                continue;
            }
            $value = trim((string)($input[$question['id']] ?? ''));
            if ($question['tipo'] === 'opcion') {
                if (!in_array($value, $question['opciones'], true)) {
                    throw new \InvalidArgumentException('Responde todas las preguntas de opción múltiple.');
                }
            } elseif ($question['tipo'] === 'correo') {
                if ($value !== '' && (strlen($value) > 190 || !filter_var($value, FILTER_VALIDATE_EMAIL))) {
                    throw new \InvalidArgumentException('El correo opcional no tiene un formato válido.');
                }
            } elseif (strlen($value) > 2000) {
                throw new \InvalidArgumentException('La respuesta abierta supera el límite de 2,000 caracteres.');
            }
            $answers[$question['id']] = $value;
        }
        if ($survey['id'] === 'C01' && ($answers['C01-P11'] ?? '') === 'Sí') {
            if (trim($answers['C01-P12'] ?? '') === '') {
                throw new \InvalidArgumentException('Especifica el capítulo estudiantil al que perteneces.');
            }
            $needs = array_values(array_filter(array_map('trim', explode(';', $answers['C01-P13'] ?? ''))));
            if (count($needs) !== 3) {
                throw new \InvalidArgumentException('Escribe exactamente tres necesidades del capítulo, separadas por punto y coma.');
            }
        }
        $record = [
            'id' => bin2hex(random_bytes(12)), 'fecha' => date(DATE_ATOM),
            'instrumento' => $survey['id'], 'segmento' => $segment,
            'respuestas' => $answers, 'token_hash' => hash('sha256', self::token()),
        ];
        $file = self::file($survey['id']);
        $handle = fopen($file, 'c+');
        if (!$handle) throw new \RuntimeException('No se pudo abrir el registro de respuestas.');
        try {
            if (!flock($handle, LOCK_EX)) throw new \RuntimeException('No se pudo bloquear el registro.');
            rewind($handle);
            while (($line = fgets($handle)) !== false) {
                $old = json_decode($line, true);
                if (is_array($old) && ($old['token_hash'] ?? '') === $record['token_hash']
                    && ($old['segmento'] ?? '') === $segment) {
                    throw new \DomainException('Este navegador ya envió una respuesta para este cuestionario y segmento.');
                }
            }
            fseek($handle, 0, SEEK_END);
            fwrite($handle, json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n");
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally { fclose($handle); }
    }

    public static function cross(string $id, int $index, string $segment = ''): array
    {
        $survey = self::survey($id);
        if (!$survey) throw new \OutOfBoundsException('Cuestionario no disponible.');
        $pool = $survey['segmentacion'] === 'coordinacion';
        $records = [];
        if ($pool) {
            foreach (self::catalog()['encuestas'] as $other) {
                if ($other['segmentacion'] === 'coordinacion') $records = array_merge($records, self::records($other['id']));
            }
            $cross = ['fila' => 'P01', 'columna' => 'P02', 'titulo' => 'Coordinaciones: estado de procesos por obstáculo principal'];
        } else {
            $records = self::records($id);
            $cross = $survey['cruces'][$index] ?? $survey['cruces'][0];
        }
        if ($segment !== '') {
            $records = array_values(array_filter($records, fn($r) => ($r['segmento'] ?? '') === $segment));
        }
        if (count($records) < 5) return ['disponible' => false, 'respondidas' => count($records), 'minimo' => 5];
        $rowQuestion = $pool ? self::questionBySuffix($survey, 'P01') : self::question($survey, $cross['fila']);
        $colQuestion = $pool ? self::questionBySuffix($survey, 'P02') : self::question($survey, $cross['columna']);
        $matrix = [];
        foreach ($rowQuestion['opciones'] as $row) {
            $matrix[$row] = array_fill_keys($colQuestion['opciones'], 0);
        }
        foreach ($records as $record) {
            $answers = $record['respuestas'] ?? [];
            $row = $pool ? self::answerBySuffix($answers, 'P01') : ($answers[$cross['fila']] ?? '');
            $col = $pool ? self::answerBySuffix($answers, 'P02') : ($answers[$cross['columna']] ?? '');
            if (isset($matrix[$row][$col])) $matrix[$row][$col]++;
        }
        return [
            'disponible' => true, 'respondidas' => count($records), 'titulo' => $cross['titulo'],
            'filas' => array_keys($matrix), 'columnas' => $colQuestion['opciones'],
            'valores' => array_values(array_map('array_values', $matrix)),
            'preguntas' => [$rowQuestion['texto'], $colQuestion['texto']], 'fuente' => $pool ? 'C05–C15' : $id,
            'segmento' => $segment,
        ];
    }

    private static function question(array $survey, string $id): array
    {
        foreach ($survey['preguntas'] as $item) if ($item['id'] === $id) return $item;
        throw new \OutOfBoundsException('Pregunta no disponible.');
    }

    private static function questionBySuffix(array $survey, string $suffix): array
    {
        foreach ($survey['preguntas'] as $item) if (str_ends_with($item['id'], $suffix)) return $item;
        throw new \OutOfBoundsException('Pregunta no disponible.');
    }

    private static function answerBySuffix(array $answers, string $suffix): string
    {
        foreach ($answers as $key => $value) if (str_ends_with((string)$key, $suffix)) return (string)$value;
        return '';
    }
}
