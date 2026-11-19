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
            'declaracion' => \App\Core\Profesores::declaracion((int) $id),
            'correos' => Identidad::correosDeUsuario((int) $id),
            'cambioCorreo' => Identidad::cambioPendiente((int) $id),
            'avisos' => \App\Modules\Eventos\Clasificacion::preferencias($id),
            'grupoAvisos' => \App\Modules\Eventos\Clasificacion::grupo(Auth::roles()),
            'fiseVigencia' => FirmaUaemex::aplica() ? FirmaUaemex::deUsuario((int) $id) : null,
            'fiseAplica' => FirmaUaemex::aplica(),
            'catalogoCa' => Database::todas("SELECT id, nombre FROM firma_grupos WHERE tipo = 'cuerpo_academico' ORDER BY nombre"),
            'organosPosgrado' => Database::todas("SELECT po.id, po.tipo, p.siglas, p.nombre AS programa,
                    EXISTS(SELECT 1 FROM programa_organo_miembros m WHERE m.organo_id = po.id AND m.usuario_id = ? AND m.activo = 1 AND (m.fecha_fin IS NULL OR m.fecha_fin >= CURDATE())) AS soy
               FROM programa_organos po JOIN programas p ON p.id = po.programa_id WHERE p.nivel = 'posgrado' AND po.activo = 1 ORDER BY p.siglas LIKE 'D%', p.siglas, po.tipo DESC", [$id]),
            'dependencias' => Database::todas('SELECT dr.*,d.nombre AS dependencia FROM departamento_responsables dr JOIN departamentos d ON d.id=dr.departamento_id WHERE dr.usuario_id=? ORDER BY dr.activo DESC,dr.fecha_inicio DESC', [$id]),
        ]);
    }

    /** La persona corrige su grado y género (con los que se la trata en toda la plataforma) y, si es profesor, su número de empleado y categoría. */
    public function tratamiento(): void
    {
        try {
            \App\Core\Profesores::guardarDatos((int) Auth::id(), (string) ($_POST['grado'] ?? ''), (string) ($_POST['genero'] ?? ''),
                (string) ($_POST['num_empleado'] ?? ''), (string) ($_POST['categoria'] ?? ''));
            flash('exito', 'Tus datos se actualizaron.');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('perfil');
    }

    /**
     * Vigencia de la firma electrónica UAEMéx (la del SICDE, https://fise.uaemex.mx/): la persona la declara, la corrige
     * o la borra. No la ven los alumnos de licenciatura.
     */
    public function firmaUaemex(): void
    {
        if (!FirmaUaemex::aplica()) {
            http_response_code(404);
            \App\Core\View::render('errores/404', ['titulo' => 'Página no encontrada']);
            return;
        }
        $fecha = trim((string) ($_POST['fise_vigencia'] ?? ''));
        $borrar = ($_POST['accion'] ?? '') === 'borrar' || $fecha === '';
        if (!$borrar && !FirmaUaemex::fechaValida($fecha)) {
            flash('error', 'La vigencia de tu firma electrónica UAEMéx no es una fecha válida.');
            redirigir('perfil#firma-uaemex');
        }
        FirmaUaemex::guardar((int) Auth::id(), $borrar ? null : $fecha, 'Mi perfil');
        unset($_SESSION['_fise_aviso_en']);
        flash('exito', $borrar ? 'Borramos la vigencia de tu firma electrónica UAEMéx.' : 'Guardamos la vigencia de tu firma electrónica UAEMéx: ' . FirmaUaemex::fechaLarga($fecha) . '.');
        redirigir('perfil#firma-uaemex');
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
            Identidad::vincularDatos($id);   // el correo recién verificado puede traer registros previos
            AccessControl::reset();
        }
        flash($ok ? 'exito' : 'error', $ok ? 'Tu correo se actualizó. Sigue siendo la misma cuenta: conservas tus constancias y accesos; ya puedes entrar con el correo nuevo.' : 'Código incorrecto, vencido o sin intentos disponibles. Solicita otro.');
        redirigir('perfil');
    }

    /**
     * El profesor declara su Cuerpo Académico y si integra el NAB o la CAP de cada posgrado. Queda vigente de inmediato;
     * la Subdirección Académica puede corregirlo en Organización. Solo se cierran las membresías que la persona desmarca.
     */
    public function academico(): void
    {
        $id = (int) Auth::id();
        if (!Auth::tieneRol('profesor') && !\App\Core\Profesores::deUsuario($id)) {
            flash('error', 'Esta sección es para profesores.');
            redirigir('perfil');
        }
        $ca = (int) ($_POST['cuerpo_academico'] ?? 0);
        $marcados = array_map('intval', (array) ($_POST['organos'] ?? []));
        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            Database::ejecutar("DELETE gm FROM firma_grupo_miembros gm JOIN firma_grupos g ON g.id = gm.grupo_id WHERE gm.usuario_id = ? AND g.tipo = 'cuerpo_academico'", [$id]);
            if ($ca > 0 && Database::valor("SELECT 1 FROM firma_grupos WHERE id = ? AND tipo = 'cuerpo_academico'", [$ca])) {
                Database::ejecutar('INSERT IGNORE INTO firma_grupo_miembros (grupo_id, usuario_id) VALUES (?, ?)', [$ca, $id]);
            }
            foreach (Database::todas("SELECT po.id FROM programa_organos po JOIN programas p ON p.id = po.programa_id WHERE p.nivel = 'posgrado' AND po.activo = 1") as $o) {
                $oid = (int) $o['id'];
                $actual = Database::una('SELECT id FROM programa_organo_miembros WHERE organo_id = ? AND usuario_id = ? AND activo = 1 AND (fecha_fin IS NULL OR fecha_fin >= CURDATE()) LIMIT 1', [$oid, $id]);
                if (in_array($oid, $marcados, true) && !$actual) {
                    Database::ejecutar("INSERT INTO programa_organo_miembros (organo_id, usuario_id, cargo, fecha_inicio, observaciones) VALUES (?, ?, 'miembro', CURDATE(), 'Declarado por el profesor en Mi perfil')
                        ON DUPLICATE KEY UPDATE activo = 1, fecha_fin = NULL, observaciones = VALUES(observaciones)", [$oid, $id]);
                } elseif (!in_array($oid, $marcados, true) && $actual) {
                    Database::ejecutar('UPDATE programa_organo_miembros SET activo = 0, fecha_fin = CURDATE() WHERE id = ?', [(int) $actual['id']]);
                }
            }
            $pdo->commit();
            Audit::record('perfil::academico', 'correcto', 'CA ' . $ca . '; órganos ' . implode(',', $marcados));
            flash('exito', 'Se guardaron tu Cuerpo Académico y tus órganos de posgrado.');
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Perfil académico: ' . $e->getMessage());
            flash('error', 'No fue posible guardar. Intenta de nuevo.');
        }
        redirigir('perfil#academico');
    }
}
