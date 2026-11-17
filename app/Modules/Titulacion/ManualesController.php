<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Modules\Titulacion\Services\Manuales;

/**
 * Manuales en PDF de Titulación de licenciatura: descarga por actor y opción, y la tabla para compararlas.
 * Basta con tener sesión: no hace falta haber iniciado un trámite (un pasante compara opciones antes de elegir).
 */
final class ManualesController extends TitulacionBase
{
    /** Tabla opción × actor con todas las descargas disponibles. */
    public function index(): void
    {
        $this->privado();
        $this->vista('manuales', [
            'titulo' => 'Manuales de titulación',
            'modalidades' => Catalogo::modalidades(),
        ]);
    }

    /** GET /titulacion/manual/{actor}/{opcion}: el PDF como descarga (opción puede ser «consolidado»). */
    public function descargar(string $actor, string $opcion): void
    {
        $ruta = Manuales::archivo($actor, $opcion);   // valida actor y opción contra las listas blancas
        if ($ruta === null) {
            $this->noEncontrado();
        }
        Audit::record('titulacion::manual', 'correcto', $actor . '/' . $opcion);
        self::enviarPdf($ruta, Manuales::nombreArchivo($actor, $opcion));
    }

    /** Envía un PDF como adjunto, sin caché, con nombre legible (UTF-8) y respaldo ASCII. */
    public static function enviarPdf(string $ruta, string $nombre): never
    {
        $ascii = preg_replace('/[^A-Za-z0-9._ ()-]+/', '_', strtr($nombre, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'ü' => 'u'])) ?: 'manual.pdf';
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
}
