<?php
declare(strict_types=1);

namespace App\Modules\Busqueda;

use App\Core\Controller;
use App\Core\ModuleManager;
use App\Core\Titulos;

/**
 * Búsqueda del encabezado (`/busqueda?q=`): muestra los resultados directamente, sin pasar por el asistente de inicio y sin IA externa.
 *  1. Opciones del sitio que la persona puede abrir (catálogo `consulta` de los manifiestos, ya filtrado por rol y condiciones).
 *  2. Avisos y eventos publicados que coinciden.
 *  3. Consulta de sus propios registros (reuniones, solicitudes, documentos), con el alcance de la sesión.
 *  4. Funciones oficiales del Manual de Organización (a qué área dirigir un asunto), con acceso a crear un ticket.
 * Sin término, muestra todo lo que la persona puede abrir, agrupado por módulo («qué comandos hay»).
 */
final class BusquedaController extends Controller
{
    private const VACIAS = ['de', 'la', 'el', 'los', 'las', 'un', 'una', 'que', 'para', 'por', 'con', 'del', 'al', 'mi', 'mis', 'en', 'y', 'o', 'se', 'como', 'quiero', 'necesito'];

    public function index(): void
    {
        $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 200));
        $catalogo = ModuleManager::consulta();
        $resultado = ['opciones' => [], 'avisos' => [], 'datos' => null, 'funciones' => []];
        if ($q !== '') {
            $resultado['opciones'] = self::coincidencias($q, $catalogo);
            try {
                $resultado['avisos'] = \App\Modules\Eventos\BuscarAvisos::buscar($q);
            } catch (\Throwable $e) {
                error_log('Búsqueda: avisos no disponibles: ' . $e->getMessage());
            }
            try {
                $resultado['datos'] = \App\Modules\Portal\ConsultasSeguras::resolver($q);
            } catch (\Throwable $e) {
                error_log('Búsqueda: consulta de registros no disponible: ' . $e->getMessage());
            }
            try {
                $resultado['funciones'] = mb_strlen($q) >= 4 ? (new Buscador())->buscar($q, 5, true) : [];
            } catch (\Throwable $e) {
                error_log('Búsqueda: funciones no disponibles: ' . $e->getMessage());
            }
        }
        $this->vista('index', [
            'titulo'    => $q === '' ? 'Buscar en Traza FI' : 'Resultados de «' . mb_strimwidth($q, 0, 60, '…') . '»',
            'q'         => $q,
            'r'         => $resultado,
            'catalogo'  => self::porModulo($catalogo),
            'hayAlgo'   => $resultado['opciones'] || $resultado['avisos'] || ($resultado['datos']['items'] ?? []) || $resultado['funciones'],
        ]);
    }

    /** Opciones del catálogo que coinciden con las palabras buscadas, de mayor a menor coincidencia. */
    private static function coincidencias(string $q, array $catalogo): array
    {
        $palabras = array_values(array_filter(explode(' ', Titulos::normalizar($q)), static fn(string $p): bool => strlen($p) >= 3 && !in_array($p, self::VACIAS, true)));
        if (!$palabras) {
            return [];
        }
        $r = [];
        foreach ($catalogo as $e) {
            $titulo = Titulos::normalizar((string) $e['titulo']);
            $claves = Titulos::normalizar((string) ($e['palabras'] ?? '') . ' ' . $e['modulo']);
            $detalle = Titulos::normalizar((string) $e['descripcion']);
            $puntos = 0;
            foreach ($palabras as $p) {
                $puntos += (str_contains($titulo, $p) ? 5 : 0) + (str_contains($claves, $p) ? 3 : 0) + (str_contains($detalle, $p) ? 1 : 0);
            }
            if ($puntos > 0) {
                $r[] = $e + ['puntos' => $puntos];
            }
        }
        usort($r, static fn(array $a, array $b): int => $b['puntos'] <=> $a['puntos']);
        return array_slice($r, 0, 12);
    }

    /** @return array<string,array> opciones agrupadas por módulo */
    private static function porModulo(array $catalogo): array
    {
        $g = [];
        foreach ($catalogo as $e) {
            $g[(string) $e['modulo']][] = $e;
        }
        ksort($g, SORT_LOCALE_STRING);
        return $g;
    }
}
