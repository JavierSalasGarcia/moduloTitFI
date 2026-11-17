<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\Audit;
use App\Modules\Posgrado\Services\Manuales;

/**
 * Páginas informativas de cada modalidad (sin iniciar trámite), tabla de manuales y descarga de los PDF.
 * Respetan la visibilidad del módulo: solo sus actores y la supervisión (PosgradoAccess); para los demás no existen.
 */
final class ManualesController extends PosgradoBase
{
    private function soloActores(): void
    {
        $this->privado();
        if (!Manuales::puedeVer()) {
            $this->noEncontrado();
        }
    }

    /** GET /posgrado/modalidad/{clave}: descripción, requisitos resumidos y manuales. No crea ningún registro. */
    public function modalidad(string $clave): void
    {
        $this->soloActores();
        $modalidades = Catalogo::modalidades();
        if (!array_key_exists($clave, $modalidades)) {
            $this->noEncontrado();
        }
        $this->vista('modalidad', [
            'titulo' => 'Modalidad: ' . $modalidades[$clave],
            'clave' => $clave,
            'nombre' => $modalidades[$clave],
            'descripcion' => Manuales::descripcion($clave),
            'requisitos' => Catalogo::requisitos(),
            'grupos' => Catalogo::grupos(),
            'puedeIniciar' => PosgradoAccess::puedeIniciar(),
        ]);
    }

    /** GET /posgrado/manuales: tabla modalidad × actor. */
    public function index(): void
    {
        $this->soloActores();
        $this->vista('manuales', ['titulo' => 'Manuales de evaluación de grado', 'modalidades' => Catalogo::modalidades()]);
    }

    /** GET /posgrado/manual/{actor}/{modalidad} (modalidad puede ser «consolidado»). */
    public function descargar(string $actor, string $modalidad): void
    {
        $this->soloActores();
        $ruta = Manuales::archivo($actor, $modalidad);
        if ($ruta === null) {
            $this->noEncontrado();
        }
        Audit::record('posgrado::manual', 'correcto', $actor . '/' . $modalidad);
        $nombre = Manuales::nombreArchivo($actor, $modalidad);
        $ascii = preg_replace('/[^A-Za-z0-9._ ()-]+/', '_', strtr($nombre, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'É' => 'E', 'Ó' => 'O'])) ?: 'manual.pdf';
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($nombre));
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }
}
