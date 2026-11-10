<?php
declare(strict_types=1);

namespace App\Modules\Portal;

use App\Core\Audit;

/**
 * Árbitro de la consulta de inicio (docs/ASISTENTE_JEV_GEMINI.md). Decide, antes de cualquier otra cosa:
 *   1. si el texto es un intento de manipulación o de sondeo interno → se rechaza;
 *   2. si la pregunta corresponde a lo que se consulta en el sitio → si no, se rechaza;
 *   3. a qué secciones (de las que la persona puede abrir) corresponde → enlaces que se muestran bajo la consulta.
 * Jev es el árbitro principal; si no está configurado, falla o se agotó la cuota, decide el motor local de palabras.
 * El árbitro solo elige entre entradas del catálogo ya filtrado por permisos: no puede ofrecer nada fuera del acceso.
 */
final class Arbitro
{
    public const MAX_ENLACES = 5;
    private const MAX_OPCIONES = 120;          // Jev admite 255 criterios; se deja margen
    private const UMBRAL_MANIPULACION = 0.6;
    private const MIN_PROBABILIDAD_ENLACE = 0.06;

    /** Filtro local previo: devuelve true si el texto coincide con un patrón de manipulación o sondeo (Prompts::PATRONES_BLOQUEO). */
    public static function bloqueadoLocal(string $q): bool
    {
        foreach (Prompts::PATRONES_BLOQUEO as $patron) {
            if (preg_match($patron, $q)) {
                return true;
            }
        }
        return false;
    }

    /** Texto que viaja a los modelos: sin caracteres de control ni marcas invisibles, espacios colapsados y longitud acotada. */
    public static function sanear(string $q): string
    {
        $q = mb_scrub($q, 'UTF-8');   // bytes inválidos no deben anular los filtros que trabajan en UTF-8
        $q = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]|\xE2\x80[\x8B-\x8F\xAA-\xAE]|\xE2\x81[\xA0-\xAF]|\xEF\xBB\xBF/', ' ', $q) ?? '';
        return mb_substr(trim(preg_replace('/\s+/u', ' ', $q) ?? ''), 0, 1200);
    }

    /**
     * @param array $catalogo  ModuleManager::consulta() (ya filtrado por permisos)
     * @param bool  $externo   false = no llamar a Jev (sin consentimiento, sin cuota o desactivado)
     * @return array{en_ambito:bool,sospechosa:bool,enlaces:array,metodo:string}
     */
    public static function evaluar(string $q, array $catalogo, bool $externo): array
    {
        if (self::bloqueadoLocal($q)) {
            Audit::record('ia_arbitro', 'denegado', 'Filtro local: patrón de manipulación o sondeo');
            return ['en_ambito' => false, 'sospechosa' => true, 'enlaces' => [], 'metodo' => 'local'];
        }
        $local = self::enlacesLocales($q, $catalogo);
        if ($externo && JevClient::activo()) {
            $r = self::conJev($q, $catalogo, $local);
            if ($r !== null) {
                Audit::record('ia_arbitro', $r['sospechosa'] ? 'denegado' : ($r['en_ambito'] ? 'en_ambito' : 'fuera_de_ambito'), 'Jev; enlaces: ' . count($r['enlaces']));
                return $r;
            }
        }
        // Motor local: está en ámbito si coincide con alguna sección o con una función oficial del Manual de Organización.
        $enAmbito = $local !== [] || (bool) preg_match('/reuni[oó]n|solicitud|ticket|tr[aá]mite|constancia|titulaci[oó]n|documento|archivo/iu', $q)
            || self::coincideFuncion($q);
        Audit::record('ia_arbitro', $enAmbito ? 'en_ambito' : 'fuera_de_ambito', 'Local; enlaces: ' . count($local));
        return ['en_ambito' => $enAmbito, 'sospechosa' => false, 'enlaces' => array_slice($local, 0, self::MAX_ENLACES), 'metodo' => 'local'];
    }

    private static function conJev(string $q, array $catalogo, array $local): ?array
    {
        // Si el catálogo excede el máximo, se envían primero las secciones con coincidencia local y luego el resto.
        $orden = array_merge($local, array_values(array_filter($catalogo, static fn(array $e): bool => !in_array($e['id'], array_column($local, 'id'), true))));
        $opciones = [];
        $porClave = [];
        foreach (array_slice($orden, 0, self::MAX_OPCIONES) as $i => $e) {
            $clave = 's' . $i;                                  // claves opacas: al modelo no se le dan rutas ni nombres internos
            $porClave[$clave] = $e;
            $opciones[$clave] = mb_substr($e['titulo'] . ' (' . $e['modulo'] . '). ' . $e['descripcion'], 0, 300);
        }
        $preguntas = [
            'ambito' => ['type' => 'noul', 'instructions' => Prompts::JEV_AMBITO, 'criteria' => ['true' => Prompts::JEV_AMBITO_SI, 'false' => Prompts::JEV_AMBITO_NO]],
            'manipulacion' => ['type' => 'noul', 'instructions' => Prompts::JEV_MANIPULACION, 'criteria' => ['true' => Prompts::JEV_MANIPULACION_SI, 'false' => Prompts::JEV_MANIPULACION_NO]],
        ];
        if ($opciones) {
            $preguntas['destino'] = ['type' => 'choice', 'instructions' => Prompts::JEV_DESTINO, 'criteria' => $opciones + ['ninguna' => Prompts::JEV_DESTINO_NINGUNA]];
        }
        $r = JevClient::decidir(['pregunta' => $q], $preguntas);
        if ($r === null || !isset($r['ambito']['noul'], $r['manipulacion']['noul']) || !is_numeric($r['ambito']['noul']) || !is_numeric($r['manipulacion']['noul'])) {
            return null;
        }
        $umbral = max(0.05, min(0.95, (float) ajuste('ia_jev_umbral', '0.5')));
        $sospechosa = (float) $r['manipulacion']['noul'] >= self::UMBRAL_MANIPULACION;
        $enAmbito = !$sospechosa && (float) $r['ambito']['noul'] >= $umbral;
        $enlaces = [];
        if ($enAmbito && is_array($r['destino']['probabilities'] ?? null)) {
            $prob = array_filter($r['destino']['probabilities'], 'is_numeric');
            arsort($prob);
            foreach ($prob as $clave => $p) {
                // Solo se aceptan claves que el servidor envió; cualquier otra cosa en la respuesta se ignora.
                if (!isset($porClave[$clave]) || (float) $p < self::MIN_PROBABILIDAD_ENLACE || count($enlaces) >= self::MAX_ENLACES) {
                    continue;
                }
                $enlaces[] = $porClave[$clave] + ['afinidad' => (int) round((float) $p * 100)];
            }
        }
        return ['en_ambito' => $enAmbito, 'sospechosa' => $sospechosa, 'enlaces' => $enlaces, 'metodo' => 'jev'];
    }

    /** Coincidencia por palabras entre la pregunta y el catálogo (título, descripción y palabras clave). */
    public static function enlacesLocales(string $q, array $catalogo): array
    {
        $terminos = self::terminos($q);
        if (!$terminos) {
            return [];
        }
        $puntuados = [];
        foreach ($catalogo as $e) {
            $titulo = self::terminos($e['titulo'] . ' ' . $e['palabras']);
            $cuerpo = self::terminos($e['descripcion'] . ' ' . $e['modulo']);
            $puntos = 0;
            foreach ($terminos as $t) {
                $puntos += in_array($t, $titulo, true) ? 3 : (in_array($t, $cuerpo, true) ? 1 : 0);
            }
            if ($puntos > 0) {
                $puntuados[] = $e + ['afinidad' => min(100, (int) round(100 * $puntos / (3 * count($terminos))))];
            }
        }
        usort($puntuados, static fn(array $a, array $b): int => $b['afinidad'] <=> $a['afinidad']);
        return array_slice($puntuados, 0, self::MAX_ENLACES);
    }

    private static function coincideFuncion(string $q): bool
    {
        try {
            $c = (new \App\Modules\Busqueda\Buscador())->buscar($q, 1, true);
            return $c && (float) $c[0]['score'] >= 0.12;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Palabras significativas: minúsculas, sin acentos, sin palabras vacías y con plural recortado. @return string[] */
    private static function terminos(string $texto): array
    {
        static $vacias = ['para', 'como', 'donde', 'cuando', 'cual', 'cuales', 'que', 'quiero', 'necesito', 'puedo', 'debo', 'hacer', 'tengo', 'tiene', 'esta', 'este', 'estos', 'estas', 'una', 'unos', 'unas',
            'los', 'las', 'del', 'con', 'por', 'sin', 'sobre', 'mis', 'sus', 'ver', 'mostrar', 'consultar', 'consulta', 'saber', 'hay', 'son', 'ser', 'the', 'and', 'facultad', 'ingenieria'];
        $t = mb_strtolower($texto);
        $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
        $r = [];
        foreach (preg_split('/[^a-z0-9]+/', $t, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $p) {
            if (strlen($p) < 3 || in_array($p, $vacias, true)) {
                continue;
            }
            $r[] = strlen($p) > 4 ? preg_replace('/(es|s)$/', '', $p) : $p;
        }
        return array_values(array_unique($r));
    }
}
