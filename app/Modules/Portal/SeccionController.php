<?php
declare(strict_types=1);

namespace App\Modules\Portal;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\InterfaceUi;
use App\Core\ModuleManager;
use App\Core\View;

/**
 * Página de entrada de un grupo del menú lateral (`seccion/<slug>`).
 * No decide permisos: muestra las mismas entradas que ya devolvió el menú para esta persona
 * (ModuleManager::menu + InterfaceUi::menu), así que roles, concesiones y condiciones no cambian.
 */
final class SeccionController extends Controller
{
    public function ver(string $clave): void
    {
        $grupo = null;
        foreach (InterfaceUi::navegacion()['grupos'] as $g) {
            if ($g['slug'] === $clave) { $grupo = $g; break; }
        }
        if ($grupo === null) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'Página no encontrada'], Auth::usuario() ? 'app' : 'auth');
            return;
        }

        $descripciones = $this->descripciones();
        $porModulo = array_count_values(array_column($grupo['items'], 'clave'));
        $bloques = [];
        $posicion = [];
        foreach ($grupo['items'] as $item) {
            // Preferencia: «descripcion» de la propia entrada de menú; después la del catálogo de consulta.
            $texto = trim((string) ($item['descripcion'] ?? '')) ?: ($descripciones['rutas'][$item['ruta']] ?? '');
            // La descripción general del módulo solo describe bien una entrada si el módulo aporta pocas; con varias se repetiría en cada tarjeta.
            if ($texto === '' && $porModulo[$item['clave']] <= 2) $texto = $descripciones['modulos'][$item['clave']] ?? '';
            $tarjeta = ['titulo' => $item['titulo'], 'ruta' => $item['ruta'], 'descripcion' => self::unaLinea($texto), 'contador' => (int) $item['contador']];
            $sub = trim((string) ($item['grupo'] ?? ''));
            $llave = $sub === '' ? '' : $sub;
            if (!isset($posicion[$llave])) { $posicion[$llave] = count($bloques); $bloques[] = ['subtitulo' => $sub, 'tarjetas' => []]; }
            $bloques[$posicion[$llave]]['tarjetas'][] = $tarjeta;
        }
        // Las entradas sin subgrupo van primero; los subgrupos conservan el orden de su primera entrada.
        usort($bloques, fn($a, $b) => ($a['subtitulo'] !== '') <=> ($b['subtitulo'] !== ''));

        $this->vista('seccion', ['titulo' => $grupo['nombre'], 'grupo' => $grupo, 'bloques' => $bloques]);
    }

    /** Descripciones de los manifiestos: por ruta (clave `consulta`) y, como respaldo, la del módulo. */
    private function descripciones(): array
    {
        $rutas = [];
        $modulos = [];
        foreach (ModuleManager::descubrir() as $m) {
            $modulos[$m['clave']] = trim((string) ($m['descripcion'] ?? ''));
            foreach ($m['consulta'] ?? [] as $c) $rutas[$c['ruta']] ??= trim((string) $c['descripcion']);
        }
        return ['rutas' => $rutas, 'modulos' => $modulos];
    }

    /** Recorta a una línea: primera oración si es corta, o corte en palabra con puntos suspensivos. */
    private static function unaLinea(string $texto, int $max = 110): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        if ($texto === '') return '';
        if (preg_match('/^(.+?[.!?])(\s|$)/u', $texto, $m) && mb_strlen($m[1]) <= $max) return $m[1];
        if (mb_strlen($texto) <= $max) return $texto;
        $corte = mb_substr($texto, 0, $max);
        $espacio = mb_strrpos($corte, ' ');
        return rtrim($espacio !== false && $espacio > 40 ? mb_substr($corte, 0, $espacio) : $corte, " ,;:.") . '…';
    }
}
