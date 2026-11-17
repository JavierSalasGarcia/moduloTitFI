<?php
declare(strict_types=1);

namespace App\Modules\Constancias;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Constancias\Services\Solicitudes;

/**
 * Página pública (sin sesión) a la que lleva el QR de cada constancia: folio, fecha, firmante, persona y estado.
 * El código es aleatorio (20 caracteres de 32 símbolos, 100 bits) y la consulta se limita por dirección IP.
 */
final class VerificacionController extends ConstanciasBase
{
    public function ver(string $codigo): void
    {
        $this->privado();
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (AccessControl::limited('cst_verificar|' . $ip, 60, 600)) {
            Audit::record('constancias::verificar', 'denegado', 'Demasiadas consultas');
            http_response_code(429);
            exit('Demasiadas consultas. Intenta de nuevo en unos minutos.');
        }
        $r = Solicitudes::verificar($codigo);
        if ($r === null) {
            http_response_code(404);
        }
        $this->vista('verificar', ['titulo' => 'Verificación de constancias', 'r' => $r], Auth::usuario() ? 'app' : 'publico');
    }
}
