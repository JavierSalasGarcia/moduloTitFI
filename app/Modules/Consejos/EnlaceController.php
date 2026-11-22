<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Identidad;
use App\Modules\Consejos\Services\Enlaces;
use App\Modules\Consejos\Services\Texto;

/** Página a la que llega el enlace del correo «Notificar dictamen»: pide iniciar sesión (o crear la cuenta) y da el PDF. */
final class EnlaceController extends ConsejosBase
{
    public function ver(string $token): void
    {
        $this->privado();
        $e = Enlaces::buscar($token);
        $o = $e ? Database::una('SELECT * FROM consejos_oficios WHERE id = ?', [(int) $e['oficio_id']]) : null;
        $a = $o ? Database::una('SELECT * FROM consejos_asuntos WHERE id = ?', [(int) $o['asunto_id']]) : null;
        $f = $o ? Database::una("SELECT * FROM consejos_archivos WHERE oficio_id = ? AND tipo = 'dictamen' ORDER BY id DESC LIMIT 1", [(int) $o['id']]) : null;
        $usuario = Auth::usuario();
        $layout = $usuario ? 'app' : 'auth';
        if (!$e || !$o || !$a) {
            http_response_code(404);
            $this->vista('enlace', ['titulo' => 'Dictamen', 'estado' => 'invalido'], $layout);
            return;
        }
        $datos = ['titulo' => 'Dictamen ' . $o['numero'], 'oficio' => $o, 'clave' => Texto::clave($a['grupo'], (int) $a['numero']), 'correo' => (string) $e['email'],
            'correoMascara' => Identidad::enmascarar((string) $e['email']), 'token' => $token, 'pdf' => $f];

        if (!$usuario) {
            // Al iniciar sesión (o tras crear la cuenta y volver a abrir el enlace) regresa a esta misma página.
            Auth::recordarDestino('consejos/dictamen/' . $token);
            $this->vista('enlace', $datos + ['estado' => Enlaces::tieneCuenta((string) $e['email']) ? 'login' : 'registro',
                'registro' => \App\Core\AccessControl::isUaemexEmail((string) $e['email']) ? 'registro' : 'registro/egresado'], 'auth');
            return;
        }
        $propios = array_map('strtolower', Identidad::correosDeUsuario((int) Auth::id()));
        if (!in_array(strtolower((string) $e['email']), $propios, true) && !ConsejosAccess::esGestor()) {
            Audit::record('consejos::enlace', 'denegado', 'Dictamen ' . $o['numero'] . ' con otra cuenta');
            $this->vista('enlace', $datos + ['estado' => 'otra_cuenta', 'cuenta' => (string) ($usuario['email'] ?? '')], 'app');
            return;
        }
        Database::ejecutar('UPDATE consejos_enlaces SET ultimo_acceso = NOW() WHERE id = ?', [(int) $e['id']]);
        Audit::record('consejos::enlace', 'correcto', 'Abre dictamen ' . $o['numero']);
        $this->vista('enlace', $datos + ['estado' => $f ? 'listo' : 'sin_archivo'], 'app');
    }
}
