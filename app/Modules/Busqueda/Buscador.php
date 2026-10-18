<?php
declare(strict_types=1);

namespace App\Modules\Busqueda;

use App\Core\Database;

/**
 * Motor de búsqueda semántica sobre las 564 funciones del Manual de
 * Organización.
 *
 * Estrategias (automático según configuración en Ajustes):
 *  1. EMBEDDINGS: si `ia_api_key` está configurada y existen vectores en
 *     `funcion_embeddings` (generados con database/generar_embeddings.php),
 *     se vectoriza la consulta vía la API (endpoint compatible con OpenAI)
 *     y se ordena por similitud coseno.
 *  2. LOCAL: sin API key, similitud TF-IDF con normalización de acentos,
 *     stopwords en español y stemming ligero. Funciona sin servicios
 *     externos y es suficiente para vocabulario administrativo.
 */
final class Buscador
{
    /** @return array[] [funcion_id, descripcion, depto_id, depto, score 0..1, metodo] */
    public function buscar(string $consulta, int $topN = 8, bool $soloLocal = false): array
    {
        $consulta = trim($consulta);
        if (mb_strlen($consulta) < 4) {
            return [];
        }

        $funciones = Database::todas(
            'SELECT f.id, f.descripcion, d.id AS depto_id, d.nombre AS depto
               FROM funciones f JOIN departamentos d ON d.id = f.departamento_id');

        $apiKey = ajuste('ia_api_key');
        if (!$soloLocal && $apiKey !== '' && $this->hayEmbeddings()) {
            $resultado = $this->buscarConEmbeddings($consulta, $funciones, $topN, $apiKey);
            if ($resultado !== null) {
                return $resultado;
            }
            // Si la API falla, degradar silenciosamente al buscador local
        }
        return $this->buscarLocal($consulta, $funciones, $topN);
    }

    public function metodoActivo(): string
    {
        return (ajuste('ia_api_key') !== '' && $this->hayEmbeddings()) ? 'embeddings' : 'local';
    }

    // ── Estrategia 1: embeddings remotos ────────────────────────────

    private function hayEmbeddings(): bool
    {
        return (bool) Database::valor('SELECT COUNT(*) FROM funcion_embeddings');
    }

    private function buscarConEmbeddings(string $consulta, array $funciones, int $topN, string $apiKey): ?array
    {
        $vectorConsulta = $this->embeber([$consulta], $apiKey)[0] ?? null;
        if (!$vectorConsulta) {
            return null;
        }

        $porId = array_column($funciones, null, 'id');
        $scores = [];
        foreach (Database::todas('SELECT funcion_id, vector FROM funcion_embeddings') as $fila) {
            if (!isset($porId[$fila['funcion_id']])) {
                continue;
            }
            $scores[$fila['funcion_id']] = $this->coseno($vectorConsulta, json_decode($fila['vector'], true));
        }
        return $this->top($scores, $porId, $topN, 'embeddings');
    }

    /** Llama al endpoint de embeddings (formato OpenAI). @return float[][]|null */
    public function embeber(array $textos, string $apiKey): ?array
    {
        $ch = curl_init(ajuste('ia_url_embeddings', 'https://api.openai.com/v1/embeddings'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode([
                'model' => ajuste('ia_modelo_embeddings', 'text-embedding-3-small'),
                'input' => $textos,
            ]),
        ]);
        $respuesta = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($respuesta === false || $codigo !== 200) {
            return null;
        }
        $datos = json_decode($respuesta, true);
        if (empty($datos['data'])) {
            return null;
        }
        usort($datos['data'], fn($a, $b) => $a['index'] <=> $b['index']);
        return array_column($datos['data'], 'embedding');
    }

    // ── Estrategia 2: TF-IDF local ──────────────────────────────────

    private function buscarLocal(string $consulta, array $funciones, int $topN): array
    {
        $docs = [];
        foreach ($funciones as $f) {
            $docs[$f['id']] = $this->tokenizar($f['descripcion']);
        }
        $tokensConsulta = $this->tokenizar($consulta);
        if (!$tokensConsulta) {
            return [];
        }

        // Frecuencia documental para IDF
        $n = count($docs);
        $df = [];
        foreach ($docs as $tokens) {
            foreach (array_unique($tokens) as $t) {
                $df[$t] = ($df[$t] ?? 0) + 1;
            }
        }
        $idf = fn(string $t): float => log(($n + 1) / (($df[$t] ?? 0) + 1)) + 1;

        $vConsulta = $this->tfidf($tokensConsulta, $idf);
        $scores = [];
        foreach ($docs as $id => $tokens) {
            $scores[$id] = $this->cosenoDisperso($vConsulta, $this->tfidf($tokens, $idf));
        }

        $porId = array_column($funciones, null, 'id');
        return $this->top($scores, $porId, $topN, 'local');
    }

    /** @return string[] tokens normalizados sin stopwords, con stemming ligero */
    private function tokenizar(string $texto): array
    {
        static $stop = ['los', 'las', 'les', 'una', 'uno', 'unos', 'unas', 'del', 'con', 'por', 'para', 'que',
            'como', 'sus', 'este', 'esta', 'estos', 'estas', 'ante', 'sobre', 'entre', 'ser', 'son', 'asi',
            'sea', 'sean', 'demas', 'cabo', 'traves', 'cada', 'segun', 'donde', 'cuando', 'persona', 'titular',
            'llevar', 'realizar', 'demas', 'ambito', 'competencia', 'funciones', 'inherentes', 'desarrollar',
            'facultad', 'ingenieria', 'universidad', 'autonoma', 'estado', 'mexico', 'uaemex'];

        $texto = mb_strtolower($texto, 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n~']);
        preg_match_all('/[a-zn~]{3,}/u', $texto, $m);

        $tokens = [];
        foreach ($m[0] as $t) {
            if (in_array($t, $stop, true)) {
                continue;
            }
            // Stemming ligero: plurales y sufijos frecuentes del español administrativo
            $t = preg_replace('/(ciones|cion|siones|sion|mientos|miento|idades|idad|mente)$/', '', $t) ?: $t;
            $t = preg_replace('/(es|s)$/', '', $t) ?: $t;
            if (mb_strlen($t) >= 3) {
                $tokens[] = $t;
            }
        }
        return $tokens;
    }

    /** @return array<string,float> vector disperso token => peso */
    private function tfidf(array $tokens, callable $idf): array
    {
        $tf = array_count_values($tokens);
        $v = [];
        foreach ($tf as $t => $c) {
            $v[$t] = (1 + log($c)) * $idf((string) $t);
        }
        return $v;
    }

    private function cosenoDisperso(array $a, array $b): float
    {
        $dot = 0.0;
        foreach ($a as $t => $w) {
            if (isset($b[$t])) {
                $dot += $w * $b[$t];
            }
        }
        if ($dot == 0.0) {
            return 0.0;
        }
        $na = sqrt(array_sum(array_map(fn($x) => $x * $x, $a)));
        $nb = sqrt(array_sum(array_map(fn($x) => $x * $x, $b)));
        return $dot / ($na * $nb);
    }

    /** @param float[] $a @param float[] $b */
    private function coseno(array $a, array $b): float
    {
        $dot = $na = $nb = 0.0;
        foreach ($a as $i => $x) {
            $y = $b[$i] ?? 0.0;
            $dot += $x * $y;
            $na += $x * $x;
            $nb += $y * $y;
        }
        return ($na && $nb) ? $dot / (sqrt($na) * sqrt($nb)) : 0.0;
    }

    private function top(array $scores, array $porId, int $topN, string $metodo): array
    {
        arsort($scores);
        $resultado = [];
        foreach (array_slice($scores, 0, $topN, true) as $id => $score) {
            if ($score <= 0.02) {
                continue;
            }
            $f = $porId[$id];
            $resultado[] = [
                'funcion_id'  => (int) $id,
                'descripcion' => $f['descripcion'],
                'depto_id'    => (int) $f['depto_id'],
                'depto'       => $f['depto'],
                'score'       => round($score, 4),
                'metodo'      => $metodo,
            ];
        }
        return $resultado;
    }
}
