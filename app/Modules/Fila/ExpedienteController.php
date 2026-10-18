<?php
declare(strict_types=1);
namespace App\Modules\Fila;
use App\Core\{Controller, Auth, AccessControl};

final class ExpedienteController extends Controller
{
    private function oficina(string $clave): array
    {
        FilaGateway::load();
        if (AccessControl::pending()) AccessControl::deny();
        $dep=FilaAccess::oficina($clave);
        if (!$dep) { http_response_code(403); exit('No tienes acceso al expediente de esta oficina.'); }
        return $dep;
    }
    public static function turno(array $dep, int $id): ?array
    {
        $t=fila_una('SELECT * FROM turnos WHERE id=? AND dependencia_id=?',[$id,$dep['id']]);
        return $t ? self::identificar($dep,$t) : null;
    }
    private static function identificar(array $dep, array $t): array
    {
        $i=fila_identidad($dep,$t['email'],$t['nombre']===$t['email']?'':$t['nombre']);
        return [...$t,'nombre'=>$i['nombre'],'nombre_real'=>$i['nombre_real'],'alias'=>$i['alias']];
    }
    public static function historial(array $dep, string $email=''): array
    {
        $sql='SELECT t.*, (SELECT COUNT(*) FROM fila_expedientes e WHERE e.turno_id=t.id AND e.dependencia_id=t.dependencia_id) AS minutas FROM turnos t WHERE t.dependencia_id=?';
        $params=[$dep['id']];
        if ($email!=='') { $sql.=' AND t.email=?'; $params[]=u_email($email); }
        return array_map(fn($t)=>self::identificar($dep,$t),filas($sql.' ORDER BY t.fecha DESC,t.folio DESC LIMIT 200',$params));
    }
    public function index(string $clave): void
    {
        $dep=$this->oficina($clave);
        $this->mostrar($dep);
    }
    public function modalidad(string $clave): void
    {
        $dep=$this->oficina($clave); $id=(int)($_POST['turno_id']??0);
        if (!self::turno($dep,$id)) { http_response_code(404); exit('Turno no encontrado.'); }
        try { $datos=modalidad_de_atencion($_POST); }
        catch (\InvalidArgumentException $e) { $_GET['turno']=$id; http_response_code(422); $this->mostrar($dep,$e->getMessage(),$_POST); return; }
        consulta('UPDATE turnos SET videoconferencia=?,enlace_teams=? WHERE id=? AND dependencia_id=?',[$datos['videoconferencia'],$datos['enlace_teams'],$id,$dep['id']]);
        evento((int)$dep['id'],'modalidad-atencion','Turno '.$id.' · usuario '.Auth::id());
        flash('exito','Modalidad de atención actualizada.');
        redirigir('fila/expediente/'.$clave.'?turno='.$id);
    }
    private function mostrar(array $dep, string $error='', array $captura=[]): void
    {
        $email=u_email($_GET['email']??'');
        $turno=self::turno($dep,(int)($_GET['turno']??0));
        if (isset($_GET['turno']) && !$turno) { http_response_code(404); exit('El turno no existe en esta oficina.'); }
        $this->vista('expediente',['titulo'=>'Expediente · '.$dep['nombre'],'dep'=>$dep,
            'turno'=>$turno,'historial'=>self::historial($dep,$turno['email']??$email),
            'registros'=>$turno ? filas('SELECT * FROM fila_expedientes WHERE dependencia_id=? AND turno_id=? ORDER BY id DESC',[$dep['id'],$turno['id']]) : [],
            'guardadas'=>minutas_de((int)$dep['id'],$turno['email']??$email),
            'email'=>$email,'error'=>$error,'captura'=>$captura]);
    }
    public static function validarDirecta(array $datos, ?array $dep=null): array
    {
        $nombre=u_limpio($datos['nombre']??'',120);
        $email=u_email($datos['email']??'');
        $asunto=u_limpio($datos['asunto']??'',200);
        if (!filter_var($email,FILTER_VALIDATE_EMAIL) || $asunto==='') {
            throw new \InvalidArgumentException('Escribe un correo válido y el asunto. El correo identifica el historial de la persona.');
        }
        $alias=$nombre;
        $nombre=$dep ? fila_identidad($dep,$email,$alias)['nombre'] : ($alias!=='' ? $alias : $email);
        $fecha=(string)($datos['fecha_atencion']??'');
        $momento=$fecha==='' ? new \DateTimeImmutable('now') : \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$fecha);
        if (!$momento || ($fecha!=='' && $momento->format('Y-m-d\TH:i')!==$fecha) || $momento>new \DateTimeImmutable('now')) {
            throw new \InvalidArgumentException('La fecha de atención debe ser válida y no estar en el futuro.');
        }
        return [...compact('nombre','email','asunto','alias'),'momento'=>$momento->format('Y-m-d H:i:s'),...modalidad_de_atencion($datos)];
    }
    public static function crearDirecta(array $dep, array $datos): array
    {
        $d=self::validarDirecta($datos,$dep);
        db()->beginTransaction();
        try {
            if (db()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql') consulta('SELECT id FROM dependencias WHERE id=? FOR UPDATE',[$dep['id']]);
            $fecha=substr($d['momento'],0,10);
            $folio=(int)fila_una('SELECT COALESCE(MAX(folio),0)+1 AS siguiente FROM turnos WHERE dependencia_id=? AND fecha=?',[$dep['id'],$fecha])['siguiente'];
            $token=u_token();
            consulta('INSERT INTO turnos (dependencia_id,fecha,folio,nombre,nombre_publico,email,asunto,minutos,estado,token,creado,llamado,cerrado,videoconferencia,enlace_teams) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$dep['id'],$fecha,$folio,$d['nombre'],u_nombre_corto($d['nombre']),$d['email'],$d['asunto'],
                 (int)$dep['duracion_max'],'atendido',$token,ahora_texto(),$d['momento'],$d['momento'],$d['videoconferencia'],$d['enlace_teams']]);
            $t=turno_por_token($token);
            fila_guardar_alias($dep,$d['email'],$d['alias']);
            evento((int)$dep['id'],'atencion-directa','Turno '.$t['id'].' · usuario '.Auth::id());
            db()->commit();
            return self::turno($dep,(int)$t['id']);
        } catch (\Throwable $e) { db()->rollBack(); throw $e; }
    }
    public function directa(string $clave): void
    {
        $dep=$this->oficina($clave);
        try { $t=self::crearDirecta($dep,$_POST); }
        catch (\InvalidArgumentException $e) {
            http_response_code(422); $this->mostrar($dep,$e->getMessage(),$_POST); return;
        }
        flash('exito','Atención directa registrada. Ahora puedes añadir su minuta.');
        redirigir('fila/expediente/'.$clave.'?turno='.$t['id']);
    }
    public static function archivoValido(string $nombre, string $contenido): string
    {
        $ext=strtolower(pathinfo($nombre,PATHINFO_EXTENSION));
        if (strlen($contenido)>5*1024*1024 || $contenido==='') throw new \InvalidArgumentException('El archivo debe pesar entre 1 byte y 5 MB.');
        if (!in_array($ext,['pdf','txt','md'],true)) throw new \InvalidArgumentException('Solo se aceptan PDF, TXT y Markdown.');
        if ($ext==='pdf' && !str_starts_with($contenido,'%PDF-')) throw new \InvalidArgumentException('El archivo no es un PDF válido.');
        if ($ext!=='pdf' && (!mb_check_encoding($contenido,'UTF-8') || str_contains($contenido,"\0"))) {
            throw new \InvalidArgumentException('El texto debe estar codificado en UTF-8.');
        }
        return $ext;
    }
    public static function registrar(array $dep, int $id, string $resumen, string $archivo='', string $nombre='', bool $finalizar=false): int
    {
        if (!self::turno($dep,$id)) throw new \InvalidArgumentException('El turno no pertenece a esta oficina.');
        if (trim($resumen)==='' && $archivo==='') throw new \InvalidArgumentException('Escribe un resumen o carga una minuta.');
        consulta('INSERT INTO fila_expedientes (dependencia_id,turno_id,resumen,archivo,nombre_archivo,usuario_id,creado) VALUES (?,?,?,?,?,?,?)',
            [$dep['id'],$id,$resumen,$archivo,$nombre,Auth::id(),ahora_texto()]);
        $registroId=(int)db()->lastInsertId();
        if ($finalizar) consulta('UPDATE turnos SET estado=?,cerrado=? WHERE id=? AND dependencia_id=? AND estado IN (?,?)',
            ['atendido',ahora_texto(),$id,$dep['id'],'espera','llamado']);
        return $registroId;
    }
    public static function notificar(array $dep, int $registroId, array $correos=[]): bool
    {
        if (!empty(config()['integrado'])) {
            try {
                $id=\App\Modules\Reuniones\Services\MinutasService::vincularFila($dep,$registroId,$correos);
                $resultado=\App\Modules\Reuniones\Services\MinutasService::notificar($id);
                $ok=$resultado['enviados']===$resultado['total'];
            } catch (\Throwable $e) { $ok=false; }
            evento((int)$dep['id'],$ok?'minuta-correo':'minuta-correo-error','Registro '.$registroId.' · envío a asistentes');
            return $ok;
        }
        require_once dirname(APP_RAIZ).'/fila/publico/lib/correo.php';
        $t=fila_una('SELECT t.* FROM fila_expedientes e JOIN turnos t ON t.id=e.turno_id AND t.dependencia_id=e.dependencia_id WHERE e.id=? AND e.dependencia_id=?',[$registroId,$dep['id']]);
        if (!$t) return false;
        $enlace=rtrim((string)config()['sitio'],'/').'/t/'.$t['token'];
        $cuerpo='Hola '.$t['nombre'].':'."\n\n".'La minuta de tu atención en '.$dep['nombre'].' ya está disponible. Puedes consultar el resumen y descargar los archivos desde el enlace privado de tu turno:'."\n\n".$enlace."\n\n".'Asunto: '.$t['asunto']."\n".'Turno '.folio_publico($dep,$t).' · '.$t['fecha']."\n\n".'No necesitas iniciar sesión. Este enlace permite acceder a tus minutas; no lo compartas si contienen información privada.'."\n\n".$dep['titular']."\n".$dep['nombre'];
        try { $ok=correo_enviar($t['email'],'Minuta de tu atención · '.$dep['nombre'],$cuerpo); }
        catch (\Throwable $e) { $ok=false; }
        evento((int)$dep['id'],$ok?'minuta-correo':'minuta-correo-error','Registro '.$registroId.' · turno '.$t['id']);
        return $ok;
    }
    public function reenviar(string $clave): void
    {
        $dep=$this->oficina($clave);
        $id=(int)($_POST['registro_id']??0);
        $registro=fila_una('SELECT turno_id FROM fila_expedientes WHERE id=? AND dependencia_id=?',[$id,$dep['id']]);
        if (!$registro) { http_response_code(404); exit('La minuta no pertenece a esta oficina.'); }
        $ok=self::notificar($dep,$id);
        flash($ok?'exito':'error',$ok?'Correo enviado con el enlace privado de la minuta.':'La minuta sigue guardada, pero no se pudo enviar el correo. Puedes reintentar el envío.');
        redirigir('fila/expediente/'.$clave.'?turno='.$registro['turno_id']);
    }
    public function guardar(string $clave): void
    {
        $dep=$this->oficina($clave);
        $id=(int)($_POST['turno_id']??0);
        $t=self::turno($dep,$id);
        if (!$t) { http_response_code(404); exit('El turno no existe en esta oficina.'); }
        $ruta=''; $nombre='';
        try {
            $correos=\App\Modules\Reuniones\Services\MinutasService::correos((string)($_POST['presentes']??''));
            $resumen=u_texto_largo($_POST['resumen']??'',20000);
            $file=$_FILES['minuta']??null;
            if ($file && ($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE) {
                if ($file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name'])>5*1024*1024) {
                    throw new \InvalidArgumentException('No se pudo cargar el archivo. El límite es 5 MB.');
                }
                $contenido=file_get_contents($file['tmp_name']);
                $ext=self::archivoValido((string)$file['name'],$contenido);
                if ($resumen==='' && $ext!=='pdf') $resumen=u_texto_largo($contenido,20000);
                $nombre=u_limpio(basename((string)$file['name']),160);
                $rel='expedientes/'.(int)$dep['id'].'/'.u_token().'.'.$ext;
                $ruta=carpeta_subidas().'/'.$rel;
                if (!is_dir(dirname($ruta)) && !mkdir(dirname($ruta),0770,true) && !is_dir(dirname($ruta))) throw new \RuntimeException('No se pudo crear la carpeta privada.');
                if (!move_uploaded_file($file['tmp_name'],$ruta)) throw new \RuntimeException('No se pudo guardar el archivo.');
                chmod($ruta,0640);
            }
            if ($resumen==='' && $ruta==='') throw new \InvalidArgumentException('Escribe un resumen o carga una minuta.');
            db()->beginTransaction();
            $registroId=self::registrar($dep,(int)$t['id'],$resumen,$ruta!=='' ? 'subidas/'.$rel : '',$nombre,!empty($_POST['finalizar']));
            if (!empty(config()['integrado'])) foreach ($correos as $correo) consulta('INSERT INTO fila_minuta_presentes (registro_id,email) VALUES (?,?)',[$registroId,$correo]);
            evento((int)$dep['id'],'expediente-minuta','Turno '.$t['id'].' · usuario '.Auth::id());
            db()->commit();
        } catch (\Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            if ($ruta!=='' && is_file($ruta)) unlink($ruta);
            if (!($e instanceof \InvalidArgumentException)) throw $e;
            $_GET['turno']=$id;
            http_response_code(422); $this->mostrar($dep,$e->getMessage().' Si elegiste un archivo, selecciónalo de nuevo.',$_POST); return;
        }
        $ok=self::notificar($dep,$registroId,$correos);
        flash($ok?'exito':'error',$ok?'Minuta guardada y correos enviados a los asistentes con su historial privado.':'Minuta guardada, pero uno o más correos no se pudieron enviar. Usa “Reenviar por correo” para reintentar.');
        redirigir('fila/expediente/'.$clave.'?turno='.$id);
    }
    public function descargar(string $clave, string $origen, string $id): void
    {
        $dep=$this->oficina($clave);
        $registro=$origen==='registro' ? fila_una('SELECT archivo,nombre_archivo AS nombre FROM fila_expedientes WHERE id=? AND dependencia_id=?',[(int)$id,$dep['id']])
            : ($origen==='servidor' ? minuta((int)$id,(int)$dep['id']) : null);
        $ruta=$registro ? realpath(ruta_subida($registro['archivo'])) : false;
        $base=realpath(carpeta_subidas());
        if (!$ruta || !$base || !str_starts_with($ruta,$base.DIRECTORY_SEPARATOR) || !is_file($ruta)) {
            http_response_code(404); exit('El archivo no está disponible.');
        }
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"minuta.".pathinfo($ruta,PATHINFO_EXTENSION)."\"; filename*=UTF-8''".rawurlencode($registro['nombre']));
        readfile($ruta);
    }
}
