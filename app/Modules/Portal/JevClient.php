<?php
declare(strict_types=1);

namespace App\Modules\Portal;

use App\Core\Audit;
use App\Core\SecretosIa;

/**
 * Cliente de Jev (TypeSafe AI): modelo de decisiones que no genera texto; devuelve respuestas tipadas con probabilidad.
 * Un solo destino fijo por HTTPS, sin redirecciones ni reintentos. No registra preguntas ni respuestas, solo estado y consumo.
 * Referencia: https://docs.typesafe.ai  ·  Arquitectura: docs/ASISTENTE_JEV_GEMINI.md
 */
final class JevClient
{
    private const URL = 'https://api.typesafe.ai/v1/systemone';
    public const MODELO_DEFECTO = 'jev-latest';
    /** Solo para pruebas (tools/prueba_asistente.php): sustituye la llamada HTTP por una función fn(array $estado, array $preguntas): ?array. */
    public static ?\Closure $simulador = null;

    public static function activo(): bool
    {
        return self::$simulador !== null || (ajuste('ia_jev_activo', '0') === '1' && SecretosIa::clave('jev') !== '');
    }

    public static function modelo(): string
    {
        $m = ajuste('ia_jev_modelo', self::MODELO_DEFECTO);
        return preg_match('/^jev-[a-z0-9.\-]{1,30}$/D', $m) ? $m : self::MODELO_DEFECTO;
    }

    /**
     * @param array $estado     Datos a evaluar (la pregunta de la persona va aquí, nunca en las instrucciones).
     * @param array $preguntas  nombre => ['type' => 'noul'|'choice', 'instructions' => …, 'criteria' => …]
     * @return array|null       `answers` de Jev, o null si no hay clave, falló la llamada o la respuesta no es válida.
     */
    public static function decidir(array $estado, array $preguntas, ?string $clave = null): ?array
    {
        if (self::$simulador !== null) {
            return (self::$simulador)($estado, $preguntas);
        }
        $clave ??= SecretosIa::clave('jev');
        if ($clave === '' || !$preguntas || !function_exists('curl_init')) {
            return null;
        }
        $cuerpo = json_encode(['model' => self::modelo(), 'state' => $estado, 'questions' => $preguntas], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($cuerpo === false || strlen($cuerpo) > 60000) {
            return null;
        }
        $ch = curl_init(self::URL);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $clave],
            CURLOPT_POSTFIELDS => $cuerpo,
        ]);
        $crudo = curl_exec($ch);
        $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $datos = is_string($crudo) ? json_decode($crudo, true) : null;
        Audit::record('ia_jev', $http === 200 ? 'respuesta' : 'error', 'HTTP ' . $http . '; tokens: ' . (int) ($datos['usage']['input_tokens'] ?? 0));
        if ($http !== 200 || !is_array($datos) || !is_array($datos['answers'] ?? null)) {
            return null;
        }
        return $datos['answers'];
    }

    /** Prueba mínima desde Ajustes: una pregunta sí/no sin datos de personas. */
    public static function probar(string $clave): bool
    {
        $r = self::decidir(['texto' => 'Prueba de conexión.'], ['prueba' => ['type' => 'noul', 'instructions' => '¿El estado contiene la palabra "conexión"?']], $clave);
        return isset($r['prueba']['noul']) && is_numeric($r['prueba']['noul']);
    }
}
