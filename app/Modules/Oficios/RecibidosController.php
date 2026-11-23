<?php
declare(strict_types=1);

namespace App\Modules\Oficios;

use App\Core\App;
use App\Core\Audit;
use App\Core\Controller;
use App\Modules\Oficios\Services\Destinatarios;
use App\Modules\Oficios\Services\OficiosSub;

/**
 * «Avisos y eventos → Oficios»: los oficios firmados de la Subdirección Académica dirigidos a la persona con sesión
 * (a ella o a un grupo al que pertenece, como «Todos los profesores» o «Tiempo completo»), en tabla, con el PDF firmado.
 */
final class RecibidosController extends Controller
{
    public function index(): void
    {
        header('Cache-Control: no-store, private');
        $this->vista('recibidos', ['titulo' => 'Oficios', 'filas' => Destinatarios::recibidos()]);
    }

    public function pdf(string $id): void
    {
        $o = ctype_digit($id) ? OficiosSub::obtener((int) $id) : null;
        // Quien redacta o firma puede abrirlo; los destinatarios, solo si el oficio va dirigido a ellos.
        $permitido = $o && $o['estado'] === 'firmado' && $o['pdf_firmado'] && (OficiosAccess::puedeRedactar() || Destinatarios::leDirige((int) $o['id']));
        if (!$permitido) {
            http_response_code(404);
            exit('Oficio no disponible.');
        }
        $f = rtrim((string) App::config('archivos')['storage'], '/\\') . '/oficios_sub/' . basename((string) $o['pdf_firmado']);
        if (!is_file($f)) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }
        Audit::record('oficios_sub', 'correcto', 'Consulta oficio #' . $o['id']);
        $bytes = (string) file_get_contents($f);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $o['numero_texto']) . '.pdf"');
        header('Content-Length: ' . strlen($bytes));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        echo $bytes;
        exit;
    }
}
