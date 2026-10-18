<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Auth;
use App\Core\Database;

/** Estructura: integrantes de las comisiones de revisión de protocolos de cada licenciatura (solo administración). */
final class EstructuraController extends TitulacionBase
{
    private function admin(): void
    {
        if (!Auth::tieneRol('admin')) {
            $this->prohibido('Estructura de titulación');
        }
        $this->privado();
    }

    public function index(): void
    {
        $this->admin();
        $por = [];
        foreach (RevisionRepository::todosLosMiembros() as $m) {
            $por[$m['carrera']][] = $m;
        }
        $this->vista('estructura', [
            'titulo' => 'Titulación · Estructura de comisiones',
            'carreras' => Catalogo::carreras(),
            'miembros' => $por,
            'dias' => (int) \ajuste('titulacion_dias_revision', '7'),
        ]);
    }

    public function agregar(): void
    {
        $this->admin();
        $carrera = $this->post('carrera');
        $nombre = mb_substr($this->post('nombre'), 0, 160);
        $email = strtolower(trim($this->post('email')));
        $rol = $this->post('rol') === 'encargado' ? 'encargado' : 'miembro';
        if (!isset(Catalogo::carreras()[$carrera]) || mb_strlen($nombre) < 3 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 120) {
            flash('error', 'Indica la comisión, el nombre y un correo válido.');
            redirigir('titulacion/estructura');
        }
        Database::ejecutar(
            'INSERT INTO tit_comision_miembros (carrera, nombre, email, rol, creado_por) VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), rol = VALUES(rol), activo = 1',
            [$carrera, $nombre, $email, $rol, Auth::id()]
        );
        Configuracion::bitacora(Auth::id(), 'estructura', 'Integrante ' . $email . ' (' . $rol . ') en comisión ' . $carrera);
        flash('exito', 'Integrante guardado.');
        redirigir('titulacion/estructura#c-' . $carrera);
    }

    public function actualizar(string $id): void
    {
        $this->admin();
        $m = ctype_digit($id) ? RevisionRepository::miembro((int) $id) : null;
        if ($m === null) {
            $this->noEncontrado();
        }
        $rol = $this->post('rol') === 'encargado' ? 'encargado' : 'miembro';
        $activo = $this->post('activo') === '1' ? 1 : 0;
        Database::ejecutar('UPDATE tit_comision_miembros SET nombre = ?, rol = ?, activo = ? WHERE id = ?', [mb_substr($this->post('nombre') ?: $m['nombre'], 0, 160), $rol, $activo, (int) $id]);
        Configuracion::bitacora(Auth::id(), 'estructura', 'Integrante ' . $m['email'] . ' actualizado (' . $rol . ($activo ? '' : ', baja') . ')');
        flash('exito', 'Integrante actualizado.');
        redirigir('titulacion/estructura#c-' . $m['carrera']);
    }

    public function eliminar(string $id): void
    {
        $this->admin();
        $m = ctype_digit($id) ? RevisionRepository::miembro((int) $id) : null;
        if ($m === null) {
            $this->noEncontrado();
        }
        $uso = (int) Database::valor('SELECT COUNT(*) FROM tit_revision_asignaciones WHERE miembro_id = ?', [(int) $id]);
        if ($uso > 0) {
            Database::ejecutar('UPDATE tit_comision_miembros SET activo = 0 WHERE id = ?', [(int) $id]);
            flash('exito', 'Tiene revisiones registradas: se dio de baja en lugar de eliminarse.');
        } else {
            Database::ejecutar('DELETE FROM tit_comision_miembros WHERE id = ?', [(int) $id]);
            flash('exito', 'Integrante eliminado.');
        }
        Configuracion::bitacora(Auth::id(), 'estructura', 'Integrante ' . $m['email'] . ' retirado de ' . $m['carrera']);
        redirigir('titulacion/estructura#c-' . $m['carrera']);
    }
}
