<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Acceso;
use App\Core\Auth;
use App\Core\Database;

/**
 * Quién hace qué en Consejos (docs/CONSEJOS.md):
 *  - Gestionan (capturan asuntos, conducen las sesiones, generan actas y dictámenes, buscan): administración, el Subdirector
 *    Académico, su asistente y quien tenga la concesión «consejos.gestor» con vigencia.
 *  - Cada profesor ve en «Comisiones» solo los asuntos en que se le mencionó, una vez concluida la sesión del Consejo de Gobierno.
 */
final class ConsejosAccess
{
    public static function esGestor(): bool
    {
        if (!Auth::id()) {
            return false;
        }
        if (Auth::tieneRol('admin') || Acceso::tiene(Auth::id(), 'consejos.gestor')) {
            return true;
        }
        try {
            return \App\Modules\Titulacion\Services\SubdireccionFirma::esSubdirector() || \App\Modules\Titulacion\Services\SubdireccionFirma::esAsistente();
        } catch (\Throwable) {
            return false;
        }
    }

    /** ¿La persona en sesión está mencionada en algún asunto de la reunión? */
    public static function mencionadoEn(int $reunionId): bool
    {
        if (!Auth::id()) {
            return false;
        }
        return (bool) Database::valor(
            'SELECT 1 FROM consejos_asunto_profesores p JOIN consejos_asuntos a ON a.id = p.asunto_id WHERE a.reunion_id = ? AND p.usuario_id = ? LIMIT 1',
            [$reunionId, Auth::id()]
        );
    }

    public static function tieneComisiones(): bool
    {
        if (!Auth::id()) {
            return false;
        }
        try {
            return (bool) Database::valor(
                'SELECT 1 FROM consejos_asunto_profesores p JOIN consejos_asuntos a ON a.id = p.asunto_id JOIN consejos_reuniones r ON r.id = a.reunion_id
                  WHERE p.usuario_id = ? AND r.gobierno_fin IS NOT NULL LIMIT 1',
                [Auth::id()]
            );
        } catch (\Throwable) {
            return false;
        }
    }
}
