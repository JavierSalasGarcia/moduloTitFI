<?php
declare(strict_types=1);

namespace App\Modules\Perfil;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

final class PerfilController extends Controller
{
    public function index(): void
    {
        $id = Auth::id();
        $this->vista('index', [
            'titulo' => 'Mi perfil',
            'usuarioPerfil' => Auth::usuario(),
            'responsabilidades' => Database::todas('SELECT pr.*,p.siglas,p.nombre AS programa FROM programa_responsables pr JOIN programas p ON p.id=pr.programa_id WHERE pr.usuario_id=? ORDER BY pr.activo DESC,pr.fecha_inicio DESC', [$id]),
            'adscripciones' => Database::todas('SELECT pp.*,p.siglas,p.nombre AS programa,p.nivel FROM programa_profesores pp JOIN programas p ON p.id=pp.programa_id WHERE pp.usuario_id=? ORDER BY pp.activo DESC,p.nivel,p.siglas',[$id]),
            'organos' => Database::todas('SELECT pom.*,po.tipo,p.siglas,p.nombre AS programa FROM programa_organo_miembros pom JOIN programa_organos po ON po.id=pom.organo_id JOIN programas p ON p.id=po.programa_id WHERE pom.usuario_id=? ORDER BY pom.activo DESC,p.siglas,po.tipo', [$id]),
            'cuerpos' => Database::todas("SELECT g.id,g.nombre FROM firma_grupo_miembros gm JOIN firma_grupos g ON g.id=gm.grupo_id WHERE gm.usuario_id=? AND g.tipo='cuerpo_academico' ORDER BY g.nombre",[$id]),
            'profesor' => \App\Core\Profesores::deUsuario((int) $id),
            'dependencias' => Database::todas('SELECT dr.*,d.nombre AS dependencia FROM departamento_responsables dr JOIN departamentos d ON d.id=dr.departamento_id WHERE dr.usuario_id=? ORDER BY dr.activo DESC,dr.fecha_inicio DESC', [$id]),
        ]);
    }

    /** La persona corrige su grado y género, que se usan para tratarla correctamente en toda la plataforma. */
    public function tratamiento(): void
    {
        $grado = mb_substr(trim((string) ($_POST['grado'] ?? '')), 0, 30);
        $genero = (string) ($_POST['genero'] ?? '');
        Database::ejecutar(
            'UPDATE usuarios SET grado = ?, genero = ? WHERE id = ?',
            [$grado !== '' ? $grado : null, in_array($genero, ['masculino', 'femenino', 'no_binario'], true) ? $genero : null, Auth::id()]
        );
        flash('exito', 'Tu tratamiento se actualizó.');
        redirigir('perfil');
    }

    /** El profesor del directorio corrige acentos y mayúsculas de su nombre. */
    public function nombre(): void
    {
        try {
            \App\Core\Profesores::corregirNombre((int) Auth::id(), (string) ($_POST['nombre'] ?? ''), (string) ($_POST['apellido1'] ?? ''), (string) ($_POST['apellido2'] ?? ''));
            flash('exito', 'Tu nombre se actualizó.');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('perfil');
    }
}
