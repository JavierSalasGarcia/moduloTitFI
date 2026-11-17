<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\Audit;

/** Quién firma los oficios y los datos de contacto de Apoyo a Grado (tabla ajustes, claves posgrado_*). */
final class ConfiguracionController extends PosgradoBase
{
    public function index(): void
    {
        $this->privado();
        $this->soloPersonal();
        $valores = [];
        foreach (array_keys(Configuracion::CAMPOS) as $clave) {
            $valores[$clave] = Configuracion::valor($clave);
        }
        $this->vista('configuracion', ['titulo' => 'Posgrado: configuración', 'valores' => $valores]);
    }

    public function guardar(): void
    {
        $this->soloPersonal();
        $entrada = is_array($_POST['a'] ?? null) ? $_POST['a'] : [];
        foreach (['posgrado_coordinador_nombre', 'posgrado_coordinador_cargo'] as $obligatorio) {
            if (trim((string) ($entrada[$obligatorio] ?? '')) === '') {
                flash('error', 'El nombre y el cargo de quien firma no pueden quedar vacíos.');
                redirigir('posgrado/configuracion');
            }
        }
        Configuracion::guardar($entrada);
        Audit::record('posgrado::configuracion', 'correcto', 'Ajustes de firma y contacto actualizados');
        flash('exito', 'Configuración guardada. Los oficios nuevos ya la usan.');
        redirigir('posgrado/configuracion');
    }
}
