<?php
declare(strict_types=1);
namespace App\Modules\Auth;
use App\Core\{AccessControl,Audit,Auth,Controller,Database};
use App\Modules\Reuniones\Services\HostingerMailer;

final class RegistroController extends Controller
{
    private function ready(): void { if (!AccessControl::ready()) { http_response_code(503); exit('El autorregistro aún no está disponible. Intenta más tarde.'); } }
    public function formulario(): void
    {
        $this->ready(); if (Auth::id()) redirigir('');
        $this->vista('registro',['titulo'=>'Crear cuenta'],'auth');
    }
    public function crear(): void
    {
        $this->ready(); if (Auth::id()) redirigir('');
        if (AccessControl::limited('registro:'.($_SERVER['REMOTE_ADDR']??''),5,3600)) { flash('error','Se alcanzó el límite de registros. Intenta más tarde.'); redirigir('registro'); }
        $nombre=trim($this->post('nombre')); $email=Auth::normalizarCorreoInstitucional((string)($_POST['email']??''))??''; $password=(string)($_POST['clave']??''); $grado=mb_substr(trim((string)($_POST['grado']??'')),0,30)?:null; $genero=in_array(($_POST['genero']??''),['masculino','femenino','no_binario'],true)?$_POST['genero']:null;
        if ($nombre==='' || mb_strlen($nombre)>120 || strlen($email)>120 || !AccessControl::isUaemexEmail($email) || $genero===null || strlen($password)<10 || strlen($password)>72 || $password!==($_POST['confirmacion']??'')) {
            flash('error','Revisa tu nombre, correo UAEMEX y las contraseñas: se admite uaemex.mx y cualquiera de sus subdominios; las contraseñas deben coincidir y tener entre 10 y 72 caracteres.'); redirigir('registro');
        }
        // Una persona, una cuenta: si el correo ya existe (alta de Titulación, Organización o la administración) no se crea
        // otra; se activa la existente con un código enviado a ese correo.
        $existente=AccessControl::internal(fn()=>Database::una('SELECT id, activo FROM usuarios WHERE email=?',[$email]));
        if ($existente) $this->iniciarActivacion($existente,$email,$password,$grado,$genero);
        try {
            $id=AccessControl::internal(function() use($nombre,$email,$password,$genero,$grado): int {
                $pdo=Database::pdo(); $pdo->beginTransaction();
                try {
                    $id=Database::ejecutar("INSERT INTO usuarios (nombre,grado,genero,email,password_hash,rol,activo,permite_contrasena,logins_contrasena_restantes) VALUES (?,?,?,?,?,'alumno',1,1,20)",[$nombre,$grado,$genero,$email,password_hash($password,PASSWORD_DEFAULT)]);
                    Database::ejecutar('INSERT INTO cuenta_verificacion (usuario_id,creado_en) VALUES (?,?)',[$id,date('Y-m-d H:i:s')]);
                    $pdo->commit(); return $id;
                } catch(\Throwable $e) { $pdo->rollBack(); throw $e; }
            });
        } catch(\PDOException $e) {
            if ($e->getCode()!=='23000') throw $e;
            flash('error','No se pudo registrar ese correo. Si ya tienes cuenta, inicia sesión o recupera tu acceso.'); redirigir('registro');
        }
        AccessControl::internal(fn()=>\App\Modules\Formularios\Formularios::ligarCuenta($id,$email));
        AccessControl::reset(); Auth::iniciarSesion(AccessControl::user($id)); Audit::record('autorregistro');
        $this->send(); redirigir('cuenta/verificar');
    }
    /** Envía el código para activar una cuenta que ya existía con ese correo; la contraseña elegida espera al código. */
    private function iniciarActivacion(array $u, string $email, string $password, ?string $grado, ?string $genero): never
    {
        $id=(int)$u['id'];
        if ((int)$u['activo']!==1 || AccessControl::limited('activacion:'.$id,5,3600)) { flash('error','No se pudo registrar ese correo. Si ya tienes cuenta, inicia sesión o recupera tu acceso.'); redirigir('registro'); }
        $code=(string)random_int(100000,999999);
        AccessControl::internal(fn()=>Database::ejecutar('REPLACE INTO cuenta_activaciones (usuario_id,codigo_hash,password_hash,grado,genero,expira_en,intentos,enviado_en) VALUES (?,?,?,?,?,?,0,?)',
            [$id,password_hash($code,PASSWORD_DEFAULT),password_hash($password,PASSWORD_DEFAULT),$grado,$genero,date('Y-m-d H:i:s',time()+600),date('Y-m-d H:i:s')]));
        $ok=(new HostingerMailer())->enviar($email,'Activa tu cuenta · Traza FI',"Tu código para activar tu cuenta de Traza FI es: $code\n\nYa tenías una cuenta con este correo (por ejemplo, la creó Titulación o la administración). Al escribir el código se activa esa misma cuenta con la contraseña que elegiste; no se crea otra.\n\nEs válido durante 10 minutos. Si no lo solicitaste, ignora este mensaje: tu cuenta no cambia.");
        Audit::record('activacion_cuenta_enviada',$ok?'correcto':'error');
        $_SESSION['_activacion']=$id; $_SESSION['_registro_email']=$email;
        flash($ok?'exito':'error',$ok?'Ese correo ya tiene una cuenta. Te enviamos un código para activarla con tu contraseña; no se crea una cuenta nueva.':'No pudimos enviar el código. Intenta de nuevo en unos minutos.');
        redirigir($ok?'registro/activar':'registro');
    }
    public function activar(): void
    {
        $this->ready(); if (Auth::id()) redirigir('');
        if (empty($_SESSION['_activacion'])) redirigir('registro');
        $this->vista('activar',['titulo'=>'Activar cuenta','email'=>(string)($_SESSION['_registro_email']??'')],'auth');
    }
    public function confirmarActivacion(): void
    {
        $this->ready(); if (Auth::id()) redirigir('');
        $id=(int)($_SESSION['_activacion']??0); if (!$id) redirigir('registro');
        $code=$this->post('codigo');
        $ok=AccessControl::internal(function() use($id,$code): bool {
            $pdo=Database::pdo(); $pdo->beginTransaction();
            try {
                $row=Database::una('SELECT * FROM cuenta_activaciones WHERE usuario_id=? FOR UPDATE',[$id]);
                if (!$row || strtotime($row['expira_en'])<time() || (int)$row['intentos']>=5) { $pdo->rollBack(); return false; }
                Database::ejecutar('UPDATE cuenta_activaciones SET intentos=intentos+1 WHERE usuario_id=?',[$id]);
                if (!preg_match('/^\d{6}$/D',$code) || !password_verify($code,$row['codigo_hash'])) { $pdo->commit(); return false; }
                Database::ejecutar('UPDATE usuarios SET password_hash=?, permite_contrasena=1, logins_contrasena_restantes=GREATEST(COALESCE(logins_contrasena_restantes,0),20), grado=COALESCE(grado,?), genero=COALESCE(genero,?) WHERE id=? AND activo=1',
                    [$row['password_hash'],$row['grado'],$row['genero'],$id]);
                $ahora=date('Y-m-d H:i:s');
                Database::ejecutar('INSERT INTO cuenta_verificacion (usuario_id,verificado_en,creado_en) VALUES (?,?,?) ON DUPLICATE KEY UPDATE verificado_en=COALESCE(verificado_en,VALUES(verificado_en)),codigo_hash=NULL,expira_en=NULL',[$id,$ahora,$ahora]);
                Database::ejecutar('DELETE FROM cuenta_activaciones WHERE usuario_id=?',[$id]);
                $email=(string)Database::valor('SELECT email FROM usuarios WHERE id=?',[$id]);
                \App\Modules\Formularios\Formularios::ligarCuenta($id,$email);
                $pdo->commit(); return true;
            } catch(\Throwable $e) { $pdo->rollBack(); throw $e; }
        });
        Audit::record('activacion_cuenta',$ok?'correcto':'denegado');
        if (!$ok) { flash('error','Código incorrecto, vencido o sin intentos disponibles. Regístrate de nuevo para recibir otro.'); redirigir('registro/activar'); }
        unset($_SESSION['_activacion'],$_SESSION['_registro_email']);
        AccessControl::reset(); Auth::iniciarSesion(AccessControl::user($id));
        flash('exito','Tu cuenta quedó activada. Conservas tus datos, programas e historial; desde ahora entras con tu correo y tu contraseña.');
        redirigir('');
    }
    public function verificar(): void
    {
        $this->ready(); if (!Auth::id()) redirigir('login');
        $this->vista('verificar',['titulo'=>'Verificar correo','pendiente'=>AccessControl::pending()]);
    }
    public function reenviar(): void
    {
        $this->ready(); if (!Auth::id()) redirigir('login');
        if (AccessControl::pending()) $this->send();
        redirigir('cuenta/verificar');
    }
    private function send(): void
    {
        if (AccessControl::limited('codigo:'.Auth::id(),5,3600)) { flash('error','Ya solicitaste varios códigos. Intenta de nuevo en una hora.'); return; }
        $code=(string)random_int(100000,999999);
        $saved=AccessControl::internal(function() use($code): bool {
            $pdo=Database::pdo(); $pdo->beginTransaction();
            try {
                $row=Database::una('SELECT * FROM cuenta_verificacion WHERE usuario_id=? FOR UPDATE',[Auth::id()]);
                if (!$row || $row['verificado_en'] || ($row['enviado_en'] && strtotime($row['enviado_en'])>time()-60)) { $pdo->rollBack(); return false; }
                Database::ejecutar('UPDATE cuenta_verificacion SET codigo_hash=?,expira_en=?,intentos=0,enviado_en=? WHERE usuario_id=?',[password_hash($code,PASSWORD_DEFAULT),date('Y-m-d H:i:s',time()+600),date('Y-m-d H:i:s'),Auth::id()]);
                $pdo->commit(); return true;
            } catch(\Throwable $e) { $pdo->rollBack(); throw $e; }
        });
        if (!$saved) { flash('alerta','Espera un minuto antes de pedir otro código.'); return; }
        $ok=(new HostingerMailer())->enviar(Auth::usuario()['email'],'Verifica tu correo · Traza FI',"Tu código de verificación es: $code\n\nEs válido durante 10 minutos y solo puede usarse una vez. Verifica tu correo en Traza FI para habilitar la escritura. No compartas este código.\n\nSi no solicitaste esta cuenta, ignora el mensaje.");
        Audit::record('codigo_verificacion_enviado',$ok?'correcto':'error');
        flash($ok?'exito':'error',$ok?'Enviamos un código de 6 dígitos a tu correo. Revisa también la carpeta de spam.':'Tu cuenta está creada en modo lectura, pero no pudimos confirmar el envío del correo. Puedes solicitar otro código en un minuto.');
    }
    public function confirmar(): void
    {
        $this->ready(); if (!Auth::id()) redirigir('login');
        if (!AccessControl::pending()) redirigir('cuenta/verificar');
        $code=$this->post('codigo');
        $ok=AccessControl::internal(function() use($code): bool {
            $pdo=Database::pdo(); $pdo->beginTransaction();
            try {
                $row=Database::una('SELECT * FROM cuenta_verificacion WHERE usuario_id=? FOR UPDATE',[Auth::id()]);
                if (!$row || $row['verificado_en'] || !$row['codigo_hash'] || strtotime($row['expira_en'])<time() || $row['intentos']>=5) { $pdo->rollBack(); return false; }
                Database::ejecutar('UPDATE cuenta_verificacion SET intentos=intentos+1 WHERE usuario_id=?',[Auth::id()]);
                $valid=preg_match('/^\d{6}$/D',$code) && password_verify($code,$row['codigo_hash']);
                if ($valid) Database::ejecutar('UPDATE cuenta_verificacion SET verificado_en=?,codigo_hash=NULL,expira_en=NULL WHERE usuario_id=?',[date('Y-m-d H:i:s'),Auth::id()]);
                $pdo->commit(); return (bool)$valid;
            } catch(\Throwable $e) { $pdo->rollBack(); throw $e; }
        });
        AccessControl::reset(); Audit::record('verificacion_correo',$ok?'correcto':'denegado');
        flash($ok?'exito':'error',$ok?'Correo verificado. Ya puedes enviar solicitudes y participar.':'Código incorrecto, vencido o sin intentos disponibles. Revisa el código o solicita otro.');
        redirigir($ok?'':'cuenta/verificar');
    }
}
