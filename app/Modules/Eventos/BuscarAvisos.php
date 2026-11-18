<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\Auth;
use App\Core\Database;
use App\Modules\Comunicados\Avisos;

/**
 * Búsqueda en lo publicado en «Avisos y eventos» para la Consulta del portal: avisos vigentes que la persona puede ver
 * (título, texto y nombres de sus archivos, p. ej. «Ingeniería Civil (ICI)») y eventos próximos. Solo lectura, con el
 * mismo alcance que la página de avisos; los borradores no aparecen.
 */
final class BuscarAvisos
{
    private const VACIAS = ['para','como','donde','cuando','cual','cuales','sobre','tengo','quiero','necesito','ver','los','las','del','que','una','uno','por','con','mis','hay','esta','este','estan'];

    private static function normal(string $t): string
    {
        $t = mb_strtolower($t);
        return strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    }

    /** Raíces de las palabras de la consulta (sin plurales simples ni palabras vacías). */
    private static function terminos(string $q): array
    {
        $r = [];
        foreach (preg_split('/[^a-z0-9]+/', self::normal($q)) as $w) {
            if (strlen($w) < 3 || in_array($w, self::VACIAS, true)) {
                continue;
            }
            $r[] = strlen($w) > 5 ? preg_replace('/(es|s)$/', '', $w) : $w;
        }
        return array_values(array_unique($r));
    }

    /** @return array<int,array{titulo:string,detalle:string,ruta:string}> */
    public static function buscar(string $q, int $max = 6): array
    {
        if (!Auth::id()) {
            return [];
        }
        $terminos = self::terminos($q);
        if (!$terminos) {
            return [];
        }
        $hoy = date('Y-m-d');
        $candidatos = [];
        // Quien gestiona un aviso también encuentra sus borradores (marcados como tales).
        foreach (Avisos::visibles(Avisos::puedeEmitir()) as $c) {
            $borrador = $c['categoria'] === 'Ocultar';
            if ((!empty($c['vence_en']) && $c['vence_en'] < $hoy) || ($borrador && !Avisos::puedeGestionar($c))) {
                continue;
            }
            $archivos = [];
            try {
                $archivos = array_column(Database::todas('SELECT etiqueta, grupo FROM comunicado_adjuntos WHERE comunicado_id = ?', [(int) $c['id']]), 'etiqueta');
            } catch (\Throwable) {
            }
            $tipo = ['examenes' => 'Exámenes', 'convocatoria' => 'Convocatoria'][$c['clase'] ?? ''] ?? 'Aviso oficial';
            $texto = self::normal($c['titulo'] . ' ' . $c['titulo'] . ' ' . $c['contenido'] . ' ' . implode(' ', $archivos) . ' ' . $tipo . ' examen calendario');
            $candidatos[] = ['texto' => $texto, 'titulo' => $c['titulo'], 'ruta' => 'eventos/aviso/' . (int) $c['id'],
                'detalle' => $tipo . ($borrador ? ' (borrador, aún no publicado)' : '') . ' · ' . $c['depto'] . ($archivos ? ' · ' . count($archivos) . ' archivo' . (count($archivos) > 1 ? 's' : '') : '')];
        }
        foreach (Database::todas("SELECT ev.id, ev.titulo, ev.descripcion, ev.lugar, ev.inicio, d.nombre AS depto FROM eventos ev JOIN departamentos d ON d.id = ev.depto_id WHERE COALESCE(ev.fin, ev.inicio) >= NOW()") as $e) {
            $candidatos[] = ['texto' => self::normal($e['titulo'] . ' ' . $e['titulo'] . ' ' . $e['descripcion'] . ' ' . $e['lugar'] . ' evento'), 'titulo' => $e['titulo'],
                'ruta' => 'eventos/' . (int) $e['id'], 'detalle' => 'Evento · ' . fecha_corta($e['inicio']) . ($e['lugar'] ? ' · ' . $e['lugar'] : '')];
        }
        $res = [];
        foreach ($candidatos as $c) {
            $p = 0;
            foreach ($terminos as $t) {
                $p += preg_match_all('/\\b' . preg_quote($t, '/') . '/', $c['texto']);
            }
            if ($p > 0) {
                $res[] = ['p' => $p] + $c;
            }
        }
        usort($res, static fn($a, $b) => $b['p'] <=> $a['p']);
        return array_map(static fn($r) => ['titulo' => $r['titulo'], 'detalle' => $r['detalle'], 'ruta' => $r['ruta']], array_slice($res, 0, $max));
    }
}
