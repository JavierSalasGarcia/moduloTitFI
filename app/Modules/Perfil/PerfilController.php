<?php
declare(strict_types=1);

namespace App\Modules\Perfil;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Identidad;
use App\Modules\Reuniones\Services\HostingerMailer;

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
            'correos' => Identidad::correosDeUsuario((int) $id),
            'cambioCorreo' => Identidad::cambioPendiente((int) $id),
            'avisos' => \App\Modules\Eventos\Clasificacion::preferencias($id),
            'grupoAvisos' => \App\Modules\Eventos\Clasificacion::grupo(Auth::roles()),
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

    /**
     * Cambio de correo institucional (por ejemplo, de @profesor.uaemex.mx a @uaemex.mx): se envía un código al correo NUEVO y, al
     * confirmarlo, la cuenta sigue siendo la misma (mismas constancias, adscripciones y accesos); el correo anterior queda como alias.
     */
    public function solicitarCorreo(): void
    {
        $id = (int) Auth::id();
        $nuevo = Auth::normalizarCorreoInstitucional((string) ($_POST['email'] ?? ''));
        if ($nuevo === null || !AccessControl::isUaemexEmail($nuevo)) {
            flash('error', 'Escribe un correo institucional válido (uaemex.mx).');
        } elseif ($nuevo === strtolower((string) (Auth::usuario()['email'] ?? ''))) {
            flash('error', 'Ese ya es tu correo.');
        } elseif (AccessControl::limited('cambio-correo:' . $id, 5, 3600)) {
            flash('error', 'Ya solicitaste varios códigos. Intenta de nuevo en una hora.');
        } elseif (!Identidad::disponible($nuevo, $id)) {
            flash('error', 'No se puede usar ese correo: ya pertenece a otra cuenta. Si es tuyo, pide ayuda a la Subdirección Académica.');
        } else {
            $codigo = Identidad::iniciarCambio($id, $nuevo);
            $ok = (new HostingerMailer())->enviar($nuevo, 'Confirma tu nuevo correo · Traza FI', "Tu código para cambiar el correo de tu cuenta es: $codigo

Es válido durante 10 minutos. Si no lo solicitaste, ignora este mensaje: tu cuenta no cambia.");
            Audit::record('cambio_correo_solicitado', $ok ? 'correcto' : 'error');
            flash($ok ? 'exito' : 'error', $ok ? 'Enviamos un código de 6 dígitos a tu correo nuevo. Escríbelo abajo para confirmar.' : 'No pudimos enviar el código. Intenta de nuevo en un minuto.');
        }
        redirigir('perfil');
    }

    public function confirmarCorreo(): void
    {
        $id = (int) Auth::id();
        $ok = Identidad::confirmarCambio($id, trim((string) ($_POST['codigo'] ?? '')));
        Audit::record('cambio_correo', $ok ? 'correcto' : 'denegado');
        if ($ok) {
            AccessControl::reset();
        }
        flash($ok ? 'exito' : 'error', $ok ? 'Tu correo se actualizó. Sigue siendo la misma cuenta: conservas tus constancias y accesos; ya puedes entrar con el correo nuevo.' : 'Código incorrecto, vencido o sin intentos disponibles. Solicita otro.');
        redirigir('perfil');
    }
}
