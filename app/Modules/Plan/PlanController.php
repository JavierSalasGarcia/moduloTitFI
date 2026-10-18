<?php
declare(strict_types=1);
namespace App\Modules\Plan;

use App\Core\{Auth, Controller};

final class PlanController extends Controller
{
    private static function canEdit(): bool
    {
        return Auth::tieneRol('admin', 'titular', 'coordinador', 'operativo', 'profesor');
    }

    private static function canManageContacts(): bool
    {
        return Auth::tieneRol('admin', 'titular', 'coordinador', 'operativo');
    }

    private function number(string $value): int
    {
        if (!ctype_digit($value) || (int)$value < 1 || (int)$value > 22) {
            throw new \OutOfBoundsException('Número de proyecto inválido.');
        }
        return (int)$value;
    }

    private function bundle(int $number): array
    {
        $data = PlanRepository::project($number);
        $revisions = PlanDraftStore::read($number);
        $data['version'] = $revisions['version'];
        $data['revisiones'] = $revisions['revisiones'];
        return $data;
    }

    private static function reply(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }

    public function index(): void
    {
        $catalog = PlanSurveyStore::catalog();
        if (self::canEdit()) {
            foreach ($catalog['encuestas'] as &$survey) {
                $survey['enlace'] = url('consulta-fi/' . PlanSurveyStore::tokenFor($survey['id']));
            }
            unset($survey);
        }
        $this->vista('index', ['titulo' => 'Plan de Desarrollo FI',
            'pilares' => PlanRepository::catalog(), 'instrumentos' => $catalog,
            'csrf' => Auth::csrfToken(), 'puedeEditar' => self::canEdit(),
            'puedeGestionarContactos' => self::canManageContacts()], 'plan');
    }

    public function estatus(): void
    {
        $this->vista('estatus', ['titulo' => 'Estatus del Plan de Desarrollo FI',
            'estado' => PlanStatusService::all(), 'csrf' => Auth::csrfToken(),
            'puedeEditar' => self::canEdit()], 'plan');
    }

    public function estatusGuardar(): void
    {
        if (!self::canEdit()) { http_response_code(403); exit('Inicia sesión con una cuenta de personal para modificar el plan.'); }
        $number = (int)($_POST['proyecto'] ?? 0);
        try {
            $number = $this->number((string)$number);
            $draft = PlanDraftStore::peek($number);
            $last = $draft['revisiones'] ? end($draft['revisiones']) : null;
            if (!$last) throw new \DomainException('Primero guarda una revisión de este proyecto con tu cuenta.');
            $content = $last['contenido'];
            $type = (string)($_POST['tipo'] ?? '');
            $checked = (string)($_POST['marcar'] ?? '0') === '1';
            if ($type === 'encuesta') {
                $id = strtoupper(trim((string)($_POST['cuestionario'] ?? '')));
                $proposal = PlanRepository::project($number)['propuesta'];
                $needed = (bool)array_filter($proposal['pendientes_foda'] ?? [],
                    static fn($tag) => str_starts_with((string)$tag, $id . '-'));
                $survey = PlanSurveyStore::survey($id);
                $stats = PlanSurveyStore::status()[$id] ?? null;
                $minimum = ($survey['segmentacion'] ?? '') === 'coordinacion' ? 1 : 5;
                if (!$survey || !$needed || !$stats || $stats['respondidas'] < $minimum) {
                    throw new \DomainException('El cuestionario aún no tiene datos suficientes para marcarlo listo.');
                }
                $content['encuestas_listas'][$id] = $checked;
            } elseif ($type === 'plantilla') {
                $reference = trim((string)($_POST['referencia'] ?? ''));
                if (strlen($reference) > 500 || ($checked && $reference === '')) {
                    throw new \DomainException('Indica el nombre o enlace del archivo de la plantilla terminada.');
                }
                $content['plantilla_completada'] = $checked;
                $content['plantilla_referencia'] = $reference;
            } else throw new \DomainException('Actualización no reconocida.');
            $user = Auth::usuario();
            $author = ['nombre' => (string)$user['nombre'], 'correo' => (string)$user['email'],
                'usuario_id' => $user['id'], 'identidad' => 'cuenta'];
            PlanDraftStore::save($number, (int)$draft['version'], $content, $author);
            header('Location: ' . url('plan/estatus') . '?guardado=1#proyecto-' . $number, true, 303);
        } catch (\DomainException|\OutOfBoundsException $e) {
            header('Location: ' . url('plan/estatus') . '?error=' . rawurlencode($e->getMessage())
                . '#proyecto-' . $number, true, 303);
        } catch (\Throwable $e) {
            error_log('Plan estatus: ' . $e->getMessage());
            header('Location: ' . url('plan/estatus') . '?error=' . rawurlencode('No se pudo guardar el estatus.')
                . '#proyecto-' . $number, true, 303);
        }
    }

    public function encuesta(string $token): void
    {
        $survey = PlanSurveyStore::surveyByToken($token);
        if (!$survey) { http_response_code(404); exit('Cuestionario no disponible.'); }
        $this->vista('encuesta', ['titulo' => $survey['id'] . ' · Encuesta FI 360',
            'encuesta' => $survey, 'instrumentos' => PlanSurveyStore::catalog(),
            'csrf' => Auth::csrfToken(), 'enviado' => false, 'error' => '',
            'surveyToken' => $token], 'plan_encuesta');
    }

    public function encuestaGuardar(string $token): void
    {
        $survey = PlanSurveyStore::surveyByToken($token);
        if (!$survey) { http_response_code(404); exit('Cuestionario no disponible.'); }
        if (!PlanSurveyStore::isOpen((string)$survey['id'])) {
            http_response_code(410);
            $this->vista('encuesta', ['titulo' => $survey['id'] . ' · Encuesta FI 360',
                'encuesta' => $survey, 'instrumentos' => PlanSurveyStore::catalog(),
                'csrf' => Auth::csrfToken(), 'enviado' => false, 'error' => '',
                'surveyToken' => $token], 'plan_encuesta');
            return;
        }
        $error = '';
        $saved = false;
        try {
            PlanSurveyStore::submit($survey, $_POST);
            $saved = true;
        } catch (\InvalidArgumentException|\DomainException $e) {
            $error = $e->getMessage();
            http_response_code(422);
        } catch (\Throwable $e) {
            error_log('Plan encuesta: ' . $e->getMessage());
            $error = 'No se pudo guardar la respuesta. Intenta de nuevo.';
            http_response_code(500);
        }
        $this->vista('encuesta', ['titulo' => $survey['id'] . ' · Encuesta FI 360',
            'encuesta' => $survey, 'instrumentos' => PlanSurveyStore::catalog(),
            'csrf' => Auth::csrfToken(), 'enviado' => $saved, 'error' => $error,
            'surveyToken' => $token], 'plan_encuesta');
    }

    public function agenda(): void
    {
        $this->vista('agenda', ['titulo' => 'Agenda de coordinaciones · FI 360',
            'instrumentos' => PlanSurveyStore::catalog()], 'plan');
    }

    public function encuestasApi(): void
    {
        if (!self::canEdit()) { self::reply(['error' => 'Acceso restringido.'], 403); return; }
        self::reply(['instrumentos' => PlanSurveyStore::catalog()['encuestas'],
            'avance' => PlanSurveyStore::status(), 'corte_estadistico' => PlanSurveyStore::snapshot()]);
    }

    public function encuestasResultados(string $id): void
    {
        if (!self::canEdit()) { self::reply(['error' => 'Acceso restringido.'], 403); return; }
        try {
            $segment = trim((string)($_GET['segmento'] ?? ''));
            if (strlen($segment) > 100) throw new \OutOfBoundsException('Segmento inválido.');
            self::reply(PlanSurveyStore::cross(strtoupper($id), (int)($_GET['cruce'] ?? 0), $segment));
        } catch (\OutOfBoundsException $e) { self::reply(['error' => $e->getMessage()], 404); }
    }

    public function encuestasContactos(string $id): void
    {
        if (!self::canManageContacts()) { http_response_code(403); exit('Acceso restringido a responsables de la convocatoria.'); }
        try { $rows = PlanSurveyStore::contacts(strtoupper($id)); }
        catch (\OutOfBoundsException $e) { http_response_code(404); exit('Cuestionario no disponible.'); }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="contactos-' . strtoupper($id) . '.csv"');
        header('Cache-Control: private, no-store');
        $stream = fopen('php://output', 'w');
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['Fecha', 'Programa o sector', 'Correo voluntario', 'Propuesta', 'Capítulo estudiantil', 'Necesidades del capítulo']);
        foreach ($rows as $row) {
            $safe = array_map(static function ($value): string {
                $cell = (string)$value;
                return preg_match('/^[\s]*[=+\-@]/u', $cell) ? "'" . $cell : $cell;
            }, array_values($row));
            fputcsv($stream, $safe);
        }
        fclose($stream);
    }

    public function api(string $id): void
    {
        try { self::reply($this->bundle($this->number($id))); }
        catch (\OutOfBoundsException $e) { self::reply(['error' => $e->getMessage()], 404); }
    }

    public function guardar(): void
    {
        if (!self::canEdit()) {
            self::reply(['error' => 'Inicia sesión con una cuenta de personal para guardar cambios en el plan.'], 403);
            return;
        }
        try {
            $number = $this->number($this->post('proyecto'));
            PlanRepository::project($number);
            $name = trim($this->post('autor_nombre'));
            $email = trim($this->post('autor_correo'));
            $user = Auth::usuario();
            if ($user) {
                $name = (string)$user['nombre'];
                $email = (string)$user['email'];
            }
            if (strlen($name) < 3 || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
                self::reply(['error' => 'Indica tu nombre y un correo válido para atribuir la revisión.'], 422); return;
            }
            $raw = (string)($_POST['contenido'] ?? '');
            if (strlen($raw) > 240000) { self::reply(['error' => 'La revisión es demasiado extensa.'], 413); return; }
            $content = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            if (is_array($content)) $content = self::normalizeAnnualPrograms($number, $content);
            if (is_array($content)) {
                $objectiveTexts = [(string)($content['objetivo_general'] ?? '')];
                foreach (($content['objetivos_especificos'] ?? []) as $objective) {
                    if (is_array($objective)) $objectiveTexts[] = (string)($objective['texto'] ?? '');
                }
                if (array_filter($objectiveTexts, static fn($text) => preg_match('/\bfomentar\b/iu', $text) === 1)) {
                    self::reply(['error' => 'Sustituye «fomentar» por un verbo que exprese un compromiso verificable en los objetivos.'], 422);
                    return;
                }
            }
            if (!is_array($content) || !self::validContent($content)) {
                self::reply(['error' => 'La matriz no contiene los campos requeridos.'], 422); return;
            }
            $saved = PlanDraftStore::save($number, (int)$this->post('version'), $content,
                ['nombre' => $name, 'correo' => $email, 'usuario_id' => $user['id'] ?? null,
                 'identidad' => $user ? 'cuenta' : 'declarada_sin_verificar']);
            self::reply(['ok' => true, 'version' => $saved['version'], 'revision' => end($saved['revisiones'])]);
        } catch (\OutOfBoundsException $e) {
            self::reply(['error' => $e->getMessage()], 404);
        } catch (\DomainException $e) {
            self::reply(['error' => $e->getMessage()], 409);
        } catch (\JsonException $e) {
            self::reply(['error' => 'El contenido JSON no es válido.'], 422);
        } catch (\Throwable $e) {
            error_log('Plan guardar: ' . $e->getMessage());
            self::reply(['error' => 'No se pudo guardar la revisión.'], 500);
        }
    }

    private static function validContent(array $content): bool
    {
        foreach (['diagnostico','objetivo_general'] as $key) {
            if (!isset($content[$key]) || !is_string($content[$key]) || strlen($content[$key]) > 20000) return false;
        }
        if (preg_match('/\bfomentar\b/iu', $content['objetivo_general'])) return false;
        if (!isset($content['politicas']) || !is_array($content['politicas']) || count($content['politicas']) < 2 || count($content['politicas']) > 20) return false;
        foreach ($content['politicas'] as $policy) {
            if (!is_array($policy) || empty($policy['id']) || empty($policy['texto']) || !is_string($policy['texto']) || strlen($policy['texto']) > 10000
                || !isset($policy['objetivos']) || !is_array($policy['objetivos']) || count($policy['objetivos']) !== 1) return false;
        }
        if (!isset($content['objetivos_especificos']) || !is_array($content['objetivos_especificos'])
            || count($content['objetivos_especificos']) < 1 || count($content['objetivos_especificos']) > 12) return false;
        $objectiveIds = array_column($content['objetivos_especificos'], 'id');
        if (count(array_unique($objectiveIds)) !== count($objectiveIds)) return false;
        foreach ($content['politicas'] as $policy) {
            foreach ($policy['objetivos'] as $objectiveId) if (!in_array($objectiveId, $objectiveIds, true)) return false;
        }
        foreach ($content['objetivos_especificos'] as $item) {
            if (empty($item['id']) || empty($item['texto']) || !is_string($item['texto']) || strlen($item['texto']) > 10000
                || !isset($item['estrategias']) || !is_array($item['estrategias']) || count($item['estrategias']) < 1 || count($item['estrategias']) > 12) return false;
            if (preg_match('/\bfomentar\b/iu', $item['texto'])) return false;
            foreach ($item['estrategias'] as $strategy) {
                if (empty($strategy['id']) || empty($strategy['texto']) || !is_string($strategy['texto']) || strlen($strategy['texto']) > 10000
                    || !isset($strategy['metas']) || !is_array($strategy['metas']) || count($strategy['metas']) < 1 || count($strategy['metas']) > 12) return false;
                if (!preg_match('/\[(?:C|P|I)(?:,\s*(?:C|P|I))*\]$/', trim($strategy['texto']))) return false;
                foreach ($strategy['metas'] as $goal) {
                    if (empty($goal['descripcion']) || !is_string($goal['descripcion']) || strlen($goal['descripcion']) > 10000) return false;
                    if (isset($goal['valor_meta_2030']) && (!is_numeric($goal['valor_meta_2030'])
                        || !is_finite((float)$goal['valor_meta_2030']) || (float)$goal['valor_meta_2030'] < 0)) return false;
                    if (isset($goal['valor_base'], $goal['valor_meta_2030'])
                        && (float)$goal['valor_meta_2030'] < (float)$goal['valor_base']) return false;
                    if (isset($goal['programacion_variables'])) {
                        if (!is_array($goal['programacion_variables']) || count($goal['programacion_variables']) > 12) return false;
                        foreach ($goal['programacion_variables'] as $variable) {
                            if (!is_array($variable) || !is_string($variable['nombre'] ?? null)
                                || strlen($variable['nombre']) > 1000 || !is_array($variable['programacion'] ?? null)) return false;
                            foreach (['2027','2028','2029','2030'] as $year) {
                                $value = $variable['programacion'][$year] ?? null;
                                if (!is_int($value) && !is_float($value)) return false;
                                if (!is_finite((float)$value) || (float)$value < 0) return false;
                            }
                        }
                    }
                }
            }
        }
        if (!isset($content['foda']) || !is_array($content['foda'])) return false;
        if (!isset($content['prosa_foda']) || !is_string($content['prosa_foda'])
            || strlen($content['prosa_foda']) > 20000) return false;
        if (!isset($content['solicitar_integracion_foda']) || !is_bool($content['solicitar_integracion_foda'])) return false;
        if (!isset($content['foda_modificada']) || !is_bool($content['foda_modificada'])) return false;
        if (!isset($content['diagnostico_modificado']) || !is_bool($content['diagnostico_modificado'])) return false;
        if (!in_array($content['diagnostico_fuente'] ?? '', ['propuesta_fi','foda_usuario'], true)) return false;
        if (!is_string($content['reunion_resultados'] ?? null) || strlen($content['reunion_resultados']) > 10000) return false;
        if (!is_string($content['reunion_fecha'] ?? null)
            || ($content['reunion_fecha'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $content['reunion_fecha']))) return false;
        if (!is_array($content['encuestas_listas'] ?? null)) return false;
        foreach ($content['encuestas_listas'] as $id => $ready) {
            if (!preg_match('/^C(0[1-9]|1[0-7])$/', (string)$id) || !is_bool($ready)) return false;
        }
        if (!is_bool($content['plantilla_completada'] ?? null) || !is_string($content['plantilla_referencia'] ?? null)
            || strlen($content['plantilla_referencia']) > 500
            || ($content['plantilla_completada'] && trim($content['plantilla_referencia']) === '')) return false;
        foreach (['fortalezas','oportunidades','debilidades','amenazas'] as $key) {
            if (!isset($content['foda'][$key]) || !is_array($content['foda'][$key]) || count($content['foda'][$key]) > 100) return false;
            foreach ($content['foda'][$key] as $value) if (!is_string($value) || strlen($value) > 2000) return false;
        }
        return true;
    }

    /** Conserva Ref. 2026 en la fuente oficial y acepta únicamente programación editable 2027-2030. */
    private static function normalizeAnnualPrograms(int $number, array $content): array
    {
        $proposal = PlanRepository::project($number)['propuesta'];
        $references = [];
        foreach ($proposal['referencia_base_2026']['metas'] ?? [] as $item) {
            $key = strtoupper(preg_replace('/\s+/', '', (string)($item['clave'] ?? '')));
            if ($key !== '') $references[$key] = $item;
        }
        foreach ($content['objetivos_especificos'] ?? [] as &$objective) {
            foreach ($objective['estrategias'] ?? [] as &$strategy) {
                foreach ($strategy['metas'] ?? [] as &$goal) {
                    $key = strtoupper(preg_replace('/\s+/', '', (string)($goal['indicador'] ?? '')));
                    $reference = $references[$key] ?? null;
                    if (!$reference || empty($reference['variables'])) {
                        unset($goal['programacion_variables']);
                        continue;
                    }
                    $submitted = is_array($goal['programacion_variables'] ?? null) ? $goal['programacion_variables'] : [];
                    $byName = [];
                    foreach ($submitted as $variable) {
                        if (is_array($variable) && is_string($variable['nombre'] ?? null)) {
                            $byName[trim($variable['nombre'])] = $variable;
                        }
                    }
                    $canonical = [];
                    foreach ($reference['variables'] as $index => $variable) {
                        $name = trim((string)($variable['nombre'] ?? ''));
                        $candidate = $byName[$name] ?? ($submitted[$index] ?? []);
                        $program = [];
                        foreach (['2027','2028','2029','2030'] as $year) {
                            $value = $candidate['programacion'][$year] ?? ($variable['programacion'][$year] ?? null);
                            if (!is_numeric($value) || !is_finite((float)$value) || (float)$value < 0) {
                                throw new \DomainException("La programación anual de {$name} para {$year} no es válida.");
                            }
                            $numeric = (float)$value;
                            $program[$year] = floor($numeric) === $numeric ? (int)$numeric : $numeric;
                        }
                        $canonical[] = ['nombre' => $name, 'programacion' => $program];
                    }
                    $goal['programacion_variables'] = $canonical;
                    unset($goal['variables'], $goal['valor_ref_2026']);
                }
                unset($goal);
            }
            unset($strategy);
        }
        unset($objective);
        return $content;
    }

    public function json(string $id): void
    {
        try { $bundle = $this->bundle($this->number($id)); }
        catch (\OutOfBoundsException $e) { http_response_code(404); exit('Proyecto no disponible.'); }
        header('Content-Disposition: attachment; filename="plan-fi-proyecto-' . $bundle['proyecto'] . '.json"');
        self::reply($bundle);
    }

    public function xlsx(string $id): void
    {
        try {
            $bundle = $this->bundle($this->number($id));
            $file = PlanXlsx::create(PlanXlsx::rows($bundle));
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="plan-fi-proyecto-' . $bundle['proyecto'] . '.xlsx"');
            header('Content-Length: ' . filesize($file));
            header('Cache-Control: private, no-store');
            readfile($file);
            unlink($file);
        } catch (\OutOfBoundsException $e) {
            http_response_code(404); exit('Proyecto no disponible.');
        } catch (\Throwable $e) {
            error_log('Plan Excel: ' . $e->getMessage());
            http_response_code(500); exit('No se pudo generar el Excel.');
        }
    }
}

