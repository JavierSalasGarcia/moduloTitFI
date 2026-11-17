<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\App;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionAccess;

/**
 * Manuales en PDF de Titulación de licenciatura: uno por actor y opción y uno consolidado por actor.
 *
 * Los PDF los produce `tools/manuales_publicar.sh` (fuentes LaTeX en docs/manuales/titulacion/latex) y viven fuera de
 * public/, en storage/manuales/licenciatura/<actor>/<opción>.pdf y .../<actor>/consolidado.pdf. Actor y opción se
 * validan siempre contra las listas de esta clase: nunca se arma una ruta con lo que llega en la URL sin validarlo.
 */
final class Manuales
{
    /** Actores, en el orden en que se muestran: [nombre corto, a quién va dirigido el manual]. */
    public const ACTORES = [
        'pasante' => ['Pasante', 'Manual del pasante'],
        'asesor' => ['Asesor', 'Manual del asesor'],
        'coasesor' => ['Coasesor', 'Manual del coasesor'],
        'comision_protocolos' => ['Comité', 'Manual del comité de revisión de protocolos'],
        'coordinador_carrera' => ['Coordinación', 'Manual de la Coordinación de carrera'],
        'sinodal' => ['Sinodal', 'Manual del sinodal'],
        'responsable_titulacion' => ['Titulación', 'Manual del Departamento de Titulación'],
        'subdirector' => ['Subdirección', 'Manual de la Subdirección Académica'],
        'servicios_escolares' => ['Servicios Escolares', 'Guía de Servicios Escolares'],
    ];

    /** Quiénes intervienen en las opciones sin trabajo escrito (las demás opciones involucran a todos). */
    public const SIN_TRABAJO = ['pasante', 'responsable_titulacion', 'subdirector', 'servicios_escolares'];

    public static function esActor(string $actor): bool
    {
        return isset(self::ACTORES[$actor]);
    }

    /** @return string[] actores que intervienen en la opción */
    public static function actoresDe(string $opcion): array
    {
        $m = Catalogo::modalidad($opcion);
        if ($m === null) {
            return [];
        }
        return $m['tipo'] === 'sin_trabajo' ? self::SIN_TRABAJO : array_keys(self::ACTORES);
    }

    /** ¿Es una combinación válida? La opción puede ser «consolidado». */
    public static function valido(string $actor, string $opcion): bool
    {
        if (!self::esActor($actor)) {
            return false;
        }
        if ($opcion === 'consolidado') {
            return true;
        }
        return in_array($actor, self::actoresDe($opcion), true);
    }

    /** Ruta absoluta del PDF (solo para combinaciones válidas) o null. */
    public static function archivo(string $actor, string $opcion): ?string
    {
        if (!self::valido($actor, $opcion)) {
            return null;
        }
        $ruta = rtrim((string) App::config('archivos.storage'), '/\\') . '/manuales/licenciatura/' . $actor . '/' . $opcion . '.pdf';
        return is_file($ruta) ? $ruta : null;
    }

    public static function existe(string $actor, string $opcion): bool
    {
        return self::archivo($actor, $opcion) !== null;
    }

    public static function url(string $actor, string $opcion): string
    {
        return 'titulacion/manual/' . $actor . '/' . $opcion;
    }

    /** Nombre legible del archivo descargado: «Manual del pasante - Tesis.pdf». */
    public static function nombreArchivo(string $actor, string $opcion): string
    {
        $quien = self::ACTORES[$actor][1] ?? 'Manual';
        $que = $opcion === 'consolidado' ? 'todas las opciones' : (Catalogo::modalidad($opcion)['nombre'] ?? $opcion);
        return $quien . ' - ' . $que . ' (licenciatura).pdf';
    }

    /**
     * Manuales consolidados que le corresponden a la persona con sesión, según lo que hace en Titulación.
     * @return array<string,string> actor => etiqueta
     */
    public static function deLaSesion(): array
    {
        if (!Auth::usuario()) {
            return [];
        }
        $out = [];
        $email = TitulacionAccess::correoSesion();
        try {
            if (TitulacionAccess::puedeIniciar()) {
                $out[] = 'pasante';
            }
            if (TitulacionAccess::esPersonal()) {
                $out[] = 'responsable_titulacion';
            }
            if (SubdireccionFirma::esSubdirector() || SubdireccionFirma::esAsistente()) {
                $out[] = 'subdirector';
            }
            if (TitulacionAccess::esEvaluador() || TitulacionAccess::esEncargado()) {
                $out[] = 'comision_protocolos';
            }
            if ($email !== '') {
                foreach (Database::todas('SELECT DISTINCT rol FROM tit_firmas_externas WHERE email = ?', [$email]) as $r) {
                    if (in_array($r['rol'], ['asesor', 'coasesor'], true)) {
                        $out[] = $r['rol'];
                    } elseif ($r['rol'] === 'revisor') {
                        $out[] = 'sinodal';   // los revisores del trabajo escrito son los sinodales del examen previo
                    }
                }
                if (Database::valor('SELECT 1 FROM tit_sinodales WHERE email = ? LIMIT 1', [$email])) {
                    $out[] = 'sinodal';
                }
                if (Database::valor('SELECT 1 FROM tit_asignaciones_jurado WHERE coord_email = ? LIMIT 1', [$email])) {
                    $out[] = 'coordinador_carrera';
                }
            }
        } catch (\Throwable) {
            // Sin las tablas (instalación parcial) solo se ofrece lo que ya se calculó.
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
