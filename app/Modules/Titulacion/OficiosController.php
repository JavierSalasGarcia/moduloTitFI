<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Modules\Titulacion\Services\Constancias;
use App\Modules\Titulacion\Services\NumeroOficio;

/** Bitácora de oficios numerados (sustituye los controles de oficios en Excel). */
final class OficiosController extends TitulacionBase
{
    public function index(): void
    {
        if (!Constancias::puedeBuscar()) {
            $this->prohibido('Bitácora de oficios');
        }
        $this->privado();
        $anio = ctype_digit((string) ($_GET['anio'] ?? '')) ? (int) $_GET['anio'] : (int) date('Y');
        $serie = (string) ($_GET['serie'] ?? '');
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $this->vista('oficios', [
            'titulo' => 'Titulación · Oficios',
            'oficios' => NumeroOficio::bitacora($anio, $serie, $q),
            'anio' => $anio, 'serie' => $serie, 'q' => $q,
            'siguientes' => array_map(static fn(string $s): string => NumeroOficio::texto($s, NumeroOficio::siguiente($s), (int) date('Y')), array_combine(array_keys(NumeroOficio::SERIES), array_keys(NumeroOficio::SERIES))),
        ]);
    }
}
