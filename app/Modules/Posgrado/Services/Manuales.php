<?php
declare(strict_types=1);

namespace App\Modules\Posgrado\Services;

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Posgrado\Catalogo;
use App\Modules\Posgrado\PosgradoAccess;

/**
 * Manuales en PDF de la evaluación de grado (posgrado): uno por actor y modalidad y uno consolidado por actor.
 *
 * Los produce `tools/manuales_publicar.sh` (fuentes en docs/manuales/posgrado/latex) y viven fuera de public/, en
 * storage/manuales/posgrado/<actor>/<modalidad>.pdf y .../<actor>/consolidado.pdf. Actor y modalidad se validan contra
 * estas listas antes de armar cualquier ruta.
 */
final class Manuales
{
    /** [nombre corto, título del manual] */
    public const ACTORES = [
        'egresado' => ['Egresado(a)', 'Manual del egresado'],
        'director' => ['Director(a) de tesis', 'Manual del director de tesis'],
        'sinodal' => ['Sinodal', 'Manual del sinodal'],
        'coordinacion' => ['Coordinación de Estudios Avanzados', 'Manual de la Coordinación de Estudios Avanzados'],
        'apoyo_grado' => ['Apoyo a Grado', 'Manual de Apoyo a Grado'],
        'supervision' => ['Supervisión', 'Manual de supervisión (administración y Subdirección)'],
    ];

    /**
     * Qué cambia en la plataforma según la modalidad. El recorrido (cuatro momentos, firmas y oficios) es el mismo en
     * todas; cambia cómo se nombra el trabajo en los oficios y la casilla del formato de integración.
     */
    public static function descripcion(string $modalidad): string
    {
        return match ($modalidad) {
            'tesis' => 'Presentas y defiendes tu tesis ante tu sínodo. Es la modalidad que la plataforma toma como referencia: los oficios hablan de «su tesis».',
            'trabajo_terminal' => 'Presentas y defiendes tu trabajo terminal de grado ante tu sínodo. El recorrido es el mismo que el de tesis; los oficios lo nombran «trabajo terminal de grado» y la notificación al director habla «del Trabajo Terminal de Grado».',
            'proyecto_terminal' => 'Presentas y defiendes tu proyecto terminal ante tu sínodo. El recorrido es el mismo que el de tesis; los oficios lo nombran «proyecto terminal».',
            'articulos' => 'Presentas tu tesis integrada por artículos especializados o un capítulo para libro. El recorrido es el mismo que el de tesis; los oficios la nombran «tesis por artículos especializados».',
            default => '',
        };
    }

    public static function esActor(string $actor): bool
    {
        return isset(self::ACTORES[$actor]);
    }

    public static function valido(string $actor, string $modalidad): bool
    {
        return self::esActor($actor) && ($modalidad === 'consolidado' || array_key_exists($modalidad, Catalogo::modalidades()));
    }

    public static function archivo(string $actor, string $modalidad): ?string
    {
        if (!self::valido($actor, $modalidad)) {
            return null;
        }
        $ruta = rtrim((string) App::config('archivos.storage'), '/\\') . '/manuales/posgrado/' . $actor . '/' . $modalidad . '.pdf';
        return is_file($ruta) ? $ruta : null;
    }

    public static function existe(string $actor, string $modalidad): bool
    {
        return self::archivo($actor, $modalidad) !== null;
    }

    public static function url(string $actor, string $modalidad): string
    {
        return 'posgrado/manual/' . $actor . '/' . $modalidad;
    }

    public static function nombreArchivo(string $actor, string $modalidad): string
    {
        $que = $modalidad === 'consolidado' ? 'todas las modalidades' : (Catalogo::modalidades()[$modalidad] ?? $modalidad);
        return (self::ACTORES[$actor][1] ?? 'Manual') . ' - ' . $que . ' (posgrado).pdf';
    }

    /** Quién puede ver y descargar los manuales: la misma visibilidad del módulo (sus actores y la supervisión). */
    public static function puedeVer(): bool
    {
        return PosgradoAccess::veTramite() || PosgradoAccess::tieneFirmas();
    }

    /** @return array<string,string> actor => título, de los papeles que tiene la persona con sesión. */
    public static function deLaSesion(): array
    {
        if (!Auth::usuario()) {
            return [];
        }
        $out = [];
        if (PosgradoAccess::esAlumnoPosgrado()) {
            $out[] = 'egresado';
        }
        if (PosgradoAccess::esPersonalOperativo()) {
            $out[] = 'apoyo_grado';
        }
        if (PosgradoAccess::puedeFirmarCoordinacion()) {
            $out[] = 'coordinacion';
        }
        if (PosgradoAccess::esSupervision()) {
            $out[] = 'supervision';
        }
        $correos = PosgradoAccess::correosSesion();
        if ($correos) {
            try {
                $marcas = implode(',', array_fill(0, count($correos), '?'));
                foreach (Database::todas("SELECT DISTINCT rol FROM pos_firmas WHERE LOWER(email) IN ($marcas)", $correos) as $r) {
                    if (in_array($r['rol'], ['director', 'sinodal'], true)) {
                        $out[] = $r['rol'];
                    }
                }
            } catch (\Throwable) {
                // Sin la tabla de firmas solo se ofrece lo ya calculado.
            }
        }
        $res = [];
        foreach (array_keys(self::ACTORES) as $a) {
            if (in_array($a, $out, true) && self::existe($a, 'consolidado')) {
                $res[$a] = self::ACTORES[$a][1];
            }
        }
        return $res;
    }
}
