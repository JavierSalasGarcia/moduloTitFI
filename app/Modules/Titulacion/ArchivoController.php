<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Modules\Titulacion\Services\Archivos;

/** Descarga por token (titulacion/archivo/{token}); la acción original revisa sesión y permiso. */
final class ArchivoController extends TitulacionBase
{
    public function descargar(string $token): void
    {
        $a = Archivos::abrir($token);
        if ($a === null) {
            $this->noEncontrado();
        }
        [$clase, $metodo] = Archivos::ACCIONES[$a[0]];
        (new $clase('titulacion'))->$metodo(...$a[1]);
    }
}
