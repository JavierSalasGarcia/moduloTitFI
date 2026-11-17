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
        $correo = strtolower(trim((string) ($entrada['posgrado_coordinador_correo'] ?? '')));
        if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Revisa el correo de la cuenta que firma por la Coordinación.');
            redirigir('posgrado/configuracion');
        }
        $entrada['posgrado_coordinador_correo'] = $correo;
        $dias = trim((string) ($entrada['posgrado_token_dias'] ?? ''));
        if (!ctype_digit($dias) || (int) $dias < 1 || (int) $dias > 90) {
            flash('error', 'La vigencia de los enlaces de firma debe ser de 1 a 90 días.');
            redirigir('posgrado/configuracion');
        }
        Configuracion::guardar($entrada);
        Audit::record('posgrado::configuracion', 'correcto', 'Ajustes de firma y contacto actualizados');
        flash('exito', 'Configuración guardada. Los oficios nuevos ya la usan.');
        redirigir('posgrado/configuracion');
    }
}
