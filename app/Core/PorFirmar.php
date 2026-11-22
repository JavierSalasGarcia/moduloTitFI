<?php
declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Bandeja única «Por firmar»: todo lo que espera la firma de la persona con sesión, de cualquier módulo y de cualquier
 * departamento (docs/FIRMAS_UNICA.md). Cada módulo con un flujo de firma declara en su manifiesto
 * `por_firmar: "App\Modules\X\Clase::metodo"`; esa función devuelve una lista de
 * ['tipo' => 'Constancia', 'texto' => '…', 'enlace' => 'ruta', 'fecha' => 'Y-m-d H:i:s'|null].
 * La bandeja vive en `/firmas` (módulo Firmas, pestaña «Por firmar»); un módulo que falle no afecta a los demás.
 */
final class PorFirmar
{
    /** @return array<int,array{origen:string,tipo:string,texto:string,enlace:string,fecha:?string}> las más antiguas primero */
    public static function lista(): array
    {
        $nombres = [];
        foreach (ModuleManager::descubrir() as $m) {
            $nombres[(string) $m['clave']] = (string) ($m['nombre'] ?? InterfaceUi::NAMES[$m['clave']] ?? ucfirst((string) $m['clave']));
        }
        $r = [];
        foreach (ModuleManager::proveedores('por_firmar') as $clave => $funcion) {
            if (!is_callable($funcion)) {
                continue;
            }
            try {
                foreach ((array) $funcion() as $i) {
                    if (is_array($i) && !empty($i['texto']) && isset($i['enlace'])) {
                        $r[] = ['origen' => $nombres[$clave] ?? $clave, 'tipo' => (string) ($i['tipo'] ?? 'Firma'), 'texto' => (string) $i['texto'],
                            'enlace' => (string) $i['enlace'], 'fecha' => isset($i['fecha']) ? (string) $i['fecha'] : null];
                    }
                }
            } catch (Throwable $e) {
                error_log('Por firmar de ' . $clave . ': ' . $e->getMessage());
            }
        }
        usort($r, static fn(array $a, array $b): int => strcmp((string) $a['fecha'], (string) $b['fecha']));
        return $r;
    }
}
