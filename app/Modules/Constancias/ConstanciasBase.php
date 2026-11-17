<?php
declare(strict_types=1);

namespace App\Modules\Constancias;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\View;

/** Utilidades compartidas por los controladores del módulo. */
abstract class ConstanciasBase extends Controller
{
    protected function privado(): void
    {
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
    }

    protected function noEncontrado(): never
    {
        http_response_code(404);
        View::render('errores/404', ['titulo' => 'Página no disponible'], Auth::usuario() ? 'app' : 'auth');
        exit;
    }

    protected function prohibido(string $detalle = 'Permisos insuficientes'): never
    {
        Audit::record('constancias::acceso', 'denegado', $detalle);
        http_response_code(403);
        View::render('errores/403', ['titulo' => 'Acceso restringido']);
        exit;
    }

    protected function soloSolicitantes(): void
    {
        if (!ConstanciasAccess::puedeSolicitar()) {
            $this->prohibido('Solicitar constancias: solo personal');
        }
    }

    protected static function id(string $id): ?int
    {
        return ctype_digit($id) && strlen($id) < 19 ? (int) $id : null;
    }

    /** Envía un PDF guardado en storage/, sin salir de la carpeta de almacenamiento. */
    protected function enviarArchivo(string $absoluta, string $nombre, bool $enLinea = true): never
    {
        $base = realpath((string) App::config('archivos.storage'));
        $ruta = realpath($absoluta);
        if ($base === false || $ruta === false || !is_file($ruta) || !str_starts_with($ruta, rtrim($base, '/\\') . DIRECTORY_SEPARATOR)) {
            $this->noEncontrado();
        }
        $this->privado();
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'constancia.pdf';
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment') . '; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
}
