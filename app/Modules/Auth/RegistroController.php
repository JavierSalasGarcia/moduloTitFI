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
        // La persona ya tiene cuenta con otro correo (por ejemplo, el de @profesor.uaemex.mx antes de pasar a @uaemex.mx): no se crea otra.
        $previa=\App\Core\Profesores::cuentaConOtroCorreo($email);
        if ($previa!==null) {
            flash('error','Ya tienes una cuenta con el correo '.\App\Core\Identidad::enmascarar($previa).'. Inicia sesión con ella y, si cambió tu correo institucional, actualízalo en «Mi perfil».'); redirigir('login');
        }
        try {
            $id=AccessControl::internal(function() use($nombre,$email,$password,$genero,$grado): int {
                $pdo=Database::pdo(); $pdo->beginTransaction();
                try {
                    $id=Database::ejecutar("INSERT INTO usuarios (nombre,grado,genero,email,password_hash,rol,activo,permite_contrasena,logins_contrasena_restantes) VALUES (?,?,?,?,?,'alumno',1,1,20)",[$nombre,$grado,$genero,$email,password_hash($password,PASSWORD_DEFAULT)]);
                    Database::ejecutar('INSERT INTO cuenta_verificacion (usuario_id,creado_en) VALUES (?,?)',[$id,date('Y-m-d H:i:s')]);
                    try { Database::ejecutar("INSERT IGNORE INTO usuario_correos (usuario_id,email,principal,origen) VALUES (?,?,1,'cuenta')",[$id,$email]); } catch(\Throwable) {}
                    $pdo->commit(); return $id;
                } catch(\Throwable $e) { $pdo->rollBack(); throw $e; }
            });
        } catch(\PDOException $e) {
            if ($e->getCode()!=='23000') throw $e;
            flash('error','No se pudo registrar ese correo. Si ya tienes cuenta, inicia sesión o recupera tu acceso.'); redirigir('registro');
        }
        AccessControl::reset(); Auth::iniciarSesion(AccessControl::user($id)); Audit::record('autorregistro');
        $this->send(); redirigir('cuenta/verificar');
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
        if ($ok) AccessControl::internal(fn()=>\App\Core\Profesores::vincularCuenta((int)Auth::id(), (string)(Auth::usuario()['email']??'')));
        flash($ok?'exito':'error',$ok?'Correo verificado. Ya puedes enviar solicitudes y participar.':'Código incorrecto, vencido o sin intentos disponibles. Revisa el código o solicita otro.');
        redirigir($ok?'':'cuenta/verificar');
    }
}
