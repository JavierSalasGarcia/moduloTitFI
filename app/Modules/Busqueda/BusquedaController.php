<?php
declare(strict_types=1);

namespace App\Modules\Busqueda;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\ModuleManager;
use App\Core\Titulos;
use App\Modules\Eventos\BuscarAvisos;
use App\Modules\Portal\ConsultasSeguras;

/**
 * Búsqueda del encabezado (`/busqueda?q=`): muestra los resultados directamente, sin pasar por el asistente de inicio y sin IA externa.
 *  1. Opciones del sitio que la persona puede abrir (catálogo `consulta` de los manifiestos, ya filtrado por rol y condiciones).
 *  2. Avisos y eventos vigentes que la persona puede ver.
 *  3. Consulta de sus propios registros (reuniones, solicitudes, documentos), con el alcance de la sesión.
 *  4. Funciones oficiales del Manual de Organización (a qué área dirigir un asunto), con acceso a crear un ticket.
 * Sin término, muestra todo lo que la persona puede abrir, agrupado por módulo.
 *
 * Reglas de seguridad (docs/BUSQUEDA.md): la búsqueda NUNCA consulta personas, expedientes ni casos de los Consejos; solo lo que ya
 * es visible para la sesión (menú, avisos publicados a su público, sus propios registros, el manual de organización). Requiere sesión
 * y correo verificado, tiene límite de uso por persona y en la bitácora solo se guarda la longitud del texto y cuántos resultados hubo.
 */
final class BusquedaController extends Controller
{
    private const VACIAS = ['de', 'la', 'el', 'los', 'las', 'un', 'una', 'que', 'para', 'por', 'con', 'del', 'al', 'mi', 'mis', 'en', 'y', 'o', 'se', 'como', 'quiero', 'necesito'];
    private const LIMITE_PAGINA = 40;   // consultas por minuto y persona
    private const LIMITE_SUGERENCIAS = 90;

    public function index(): void
    {
        header('Cache-Control: no-store, private');
        $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 200));
        $catalogo = ModuleManager::consulta();
        $resultado = ['opciones' => [], 'avisos' => [], 'datos' => null, 'funciones' => []];
        $aviso = null;
        if ($q !== '') {
            if (AccessControl::limited('busqueda:' . (int) Auth::id(), self::LIMITE_PAGINA, 60)) {
                $aviso = 'Hiciste muchas búsquedas seguidas. Espera un minuto y vuelve a intentarlo.';
            } else {
                $resultado['opciones'] = self::coincidencias($q, $catalogo);
                if (!AccessControl::pending()) {
                    try {
                        $resultado['avisos'] = BuscarAvisos::buscar($q);
                    } catch (\Throwable $e) {
                        error_log('Búsqueda: avisos no disponibles: ' . $e->getMessage());
                    }
                    try {
                        $resultado['datos'] = ConsultasSeguras::resolver($q);
                    } catch (\Throwable $e) {
                        error_log('Búsqueda: consulta de registros no disponible: ' . $e->getMessage());
                    }
                }
                try {
                    $resultado['funciones'] = mb_strlen($q) >= 4 ? (new Buscador())->buscar($q, 5, true) : [];
                } catch (\Throwable $e) {
                    error_log('Búsqueda: funciones no disponibles: ' . $e->getMessage());
                }
                // Solo cantidades: el texto buscado puede contener nombres y no se guarda.
                Audit::record('busqueda', 'correcto', 'long=' . mb_strlen($q) . '; opciones=' . count($resultado['opciones']) . '; avisos=' . count($resultado['avisos']));
            }
        }
        $this->vista('index', [
            'titulo'    => $q === '' ? 'Buscar en Traza FI' : 'Resultados de «' . mb_strimwidth($q, 0, 60, '…') . '»',
            'q'         => $q,
            'r'         => $resultado,
            'aviso'     => $aviso,
            'catalogo'  => self::porModulo($catalogo),
            'hayAlgo'   => $resultado['opciones'] || $resultado['avisos'] || ($resultado['datos']['items'] ?? []) || $resultado['funciones'],
        ]);
    }

    /**
     * Sugerencias mientras se escribe (JSON): avisos y eventos vigentes que la persona puede ver, sin importar mayúsculas ni acentos.
     * Solo título, tipo y enlace; sin texto de los avisos. Mínimo 3 letras, hasta 5 resultados.
     */
    public function sugerencias(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        $q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 80));
        $vacio = json_encode(['avisos' => []]);
        if (mb_strlen($q) < 3 || !Auth::id() || AccessControl::pending()
            || AccessControl::limited('busqueda_sug:' . (int) Auth::id(), self::LIMITE_SUGERENCIAS, 60)) {
            echo $vacio;
            return;
        }
        $r = [];
        try {
            foreach (BuscarAvisos::buscar($q, 8) as $a) {
                if (str_contains((string) $a['detalle'], 'borrador') || count($r) >= 5) {
                    continue;   // las sugerencias nunca muestran borradores
                }
                $r[] = ['titulo' => mb_strimwidth((string) $a['titulo'], 0, 110, '…'), 'detalle' => (string) $a['detalle'], 'ruta' => (string) $a['ruta']];
            }
        } catch (\Throwable $e) {
            error_log('Sugerencias de búsqueda: ' . $e->getMessage());
        }
        echo json_encode(['avisos' => $r], JSON_UNESCAPED_UNICODE);
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
