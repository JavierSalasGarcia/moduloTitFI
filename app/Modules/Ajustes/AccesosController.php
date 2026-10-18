<?php
declare(strict_types=1);
namespace App\Modules\Ajustes;
use App\Core\{AccessControl,AccessSchema,Audit,Auth,Controller,Database};
final class AccesosController extends Controller
{
    private function admin(): void { if(!Auth::tieneRol('admin') || isset($_SESSION['impersonador'])) { http_response_code(403); exit('Solo la administración puede acceder.'); } }
    public function index(): void
    {
        $this->admin(); $q=mb_substr(trim((string)($_GET['q']??'')),0,120); $page=max(1,(int)($_GET['pagina']??1)); $offset=($page-1)*30;
        $filter='nombre LIKE ? OR email LIKE ?'; $params=['%'.$q.'%','%'.$q.'%'];
        $this->vista('accesos',['titulo'=>'Cuentas y acceso','listo'=>AccessControl::ready(),'q'=>$q,'pagina'=>$page,
            'total'=>(int)Database::valor('SELECT COUNT(*) FROM usuarios WHERE '.$filter,$params),
            'cuentas'=>Database::todas('SELECT id,nombre,email,rol,activo FROM usuarios WHERE '.$filter.' ORDER BY nombre LIMIT 30 OFFSET '.$offset,$params)]);
    }
    public function activar(): void
    {
        $this->admin(); AccessSchema::install(); Audit::record('activar_autorregistro_auditoria'); flash('exito','Autorregistro, verificación y auditoría disponibles. Umbral inicial: 100 apoyos.'); redirigir('ajustes/accesos');
    }
    public function impersonar(): void
    {
        $this->admin(); if (!AccessControl::ready()) redirigir('ajustes/accesos');
        if (AccessControl::limited('impersonar:'.Auth::id(),10,900)) { flash('error','Demasiados intentos. Intenta más tarde.'); redirigir('ajustes/accesos'); }
        $hash=(string)Database::valor('SELECT password_hash FROM usuarios WHERE id=?',[Auth::id()]);
        $target=AccessControl::user((int)$this->post('usuario_id'));
        if (!$target || $target['rol']==='admin' || (int)$target['id']===Auth::id() || !password_verify((string)($_POST['clave_admin']??''),$hash)) {
            Audit::record('impersonacion_inicio','denegado'); flash('error','No se pudo iniciar. Revisa tu contraseña y elige una cuenta activa no administrativa.'); redirigir('ajustes/accesos');
        }
        $_SESSION['impersonador']=Auth::usuario(); $_SESSION['usuario']=$target; $_SESSION['impersonacion_expira']=time()+1800;
        session_regenerate_id(true); unset($_SESSION['_csrf']); AccessControl::reset();
        Audit::record('impersonacion_inicio','correcto','Duración máxima: 30 minutos'); redirigir('');
    }
    public function volver(): void
    {
        if (!isset($_SESSION['impersonador'])) redirigir('');
        $admin=AccessControl::user((int)$_SESSION['impersonador']['id']);
        if (!$admin || $admin['rol']!=='admin') { Auth::salir(); redirigir('login'); }
        Audit::record('impersonacion_fin'); $_SESSION['usuario']=$admin; unset($_SESSION['impersonador'],$_SESSION['impersonacion_expira'],$_SESSION['_csrf']); session_regenerate_id(true); AccessControl::reset(); redirigir('ajustes/accesos');
    }
    public function historial(): void
    {
        $this->admin(); if (!AccessControl::ready()) redirigir('ajustes/accesos');
        $q=mb_substr(trim((string)($_GET['q']??'')),0,120); $page=max(1,(int)($_GET['pagina']??1)); $offset=($page-1)*50;
        $where='actor_email LIKE ? OR usuario_email LIKE ? OR accion LIKE ?'; $p=['%'.$q.'%','%'.$q.'%','%'.$q.'%'];
        $this->vista('auditoria',['titulo'=>'Registro de acciones','q'=>$q,'pagina'=>$page,
            'total'=>(int)Database::valor('SELECT COUNT(*) FROM auditoria_acciones WHERE '.$where,$p),
            'eventos'=>Database::todas('SELECT * FROM auditoria_acciones WHERE '.$where.' ORDER BY id DESC LIMIT 50 OFFSET '.$offset,$p)]);
    }
    public function probarCorreo(): void
    {
        $this->admin(); if (!AccessControl::ready()) redirigir('ajustes/accesos');
        $email=trim($this->post('email'));
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || AccessControl::limited('prueba_correo:'.Auth::id(),3,3600)) {
            flash('error','Revisa el correo o espera antes de repetir la prueba.'); redirigir('ajustes/accesos');
        }
        $code=(string)random_int(100000,999999);
        $ok=(new \App\Modules\Reuniones\Services\HostingerMailer())->enviar($email,'Código de prueba · Traza FI',"Tu código de prueba es: $code\n\nEste correo comprueba la entrega de los códigos de Traza FI. No cambia tu contraseña ni tus permisos y no necesitas introducirlo en la plataforma.\n\nMenos trámites. Todo a la mano y con seguimiento.");
        Audit::record('prueba_correo',$ok?'correcto':'error',$email);
        flash($ok?'exito':'error',$ok?'El proveedor aceptó el correo de prueba. Confirma su recepción en el buzón.':'No se pudo confirmar el envío. Revisa la configuración del correo.'); redirigir('ajustes/accesos');
    }
}
