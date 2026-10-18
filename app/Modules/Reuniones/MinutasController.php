<?php
declare(strict_types=1);
namespace App\Modules\Reuniones;
use App\Core\{Auth,App,Controller,Database};
use App\Modules\Reuniones\Services\MinutasService as Minutas;
use App\Modules\Fila\ExpedienteController;
final class MinutasController extends Controller
{
    public function nueva(): void
    {
        $id=(int)($_GET['reunion']??0);
        $r=$id?Minutas::reunion($id,Auth::id(),Auth::usuario()['email']):null;
        if ($id && !$r) { http_response_code(403); exit('Solo los asistentes pueden añadir minutas.'); }
        $baseId=(int)($_GET['base']??0);
        $base=$baseId?Minutas::permitido($baseId,Auth::id(),Auth::usuario()['email']):null;
        if ($baseId && !$base) { http_response_code(403); exit('No eres asistente de esta reunión.'); }
        $this->vista('minuta_nueva',['titulo'=>'Compartir minuta','reunion'=>$r,'base'=>$base,'error'=>'','captura'=>[]]);
    }
    private function cargar(): array
    {
        $resumen=trim((string)($_POST['resumen']??''));
        if (mb_strlen($resumen)>20000) throw new \InvalidArgumentException('El resumen admite hasta 20 000 caracteres.');
        $ruta=''; $nombre=''; $file=$_FILES['minuta']??null;
        if ($file && $file['error']!==UPLOAD_ERR_NO_FILE) {
            if ($file['error']!==UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name'])>5*1024*1024) throw new \InvalidArgumentException('Archivo inválido. El límite es 5 MB.');
            $contenido=file_get_contents($file['tmp_name']);
            $ext=ExpedienteController::archivoValido((string)$file['name'],$contenido);
            $nombre=mb_substr(basename((string)$file['name']),0,160);
            if (!$resumen && $ext!=='pdf') $resumen=mb_substr($contenido,0,20000);
            $ruta='reuniones/minutas/'.bin2hex(random_bytes(24)).'.'.$ext;
            $destino=rtrim(App::config('archivos.storage'),'/\\').'/'.$ruta;
            if (!is_dir(dirname($destino)) && !mkdir(dirname($destino),0770,true)) throw new \RuntimeException('No se pudo guardar el archivo.');
            if (!move_uploaded_file($file['tmp_name'],$destino)) throw new \RuntimeException('No se pudo guardar el archivo.');
            chmod($destino,0640);
        }
        if (!$resumen && !$ruta) throw new \InvalidArgumentException('Escribe un resumen o carga un archivo.');
        return [$resumen,$ruta,$nombre];
    }
    public function guardar(): void
    {
        $reunionId=(int)($_POST['reunion_id']??0); $usuario=Auth::usuario();
        $r=$reunionId?Minutas::reunion($reunionId,Auth::id(),$usuario['email']):null;
        if ($reunionId && !$r) { http_response_code(403); exit('No eres asistente de esta reunión.'); }
        $baseId=(int)($_POST['base_id']??0);
        $base=$baseId?Minutas::permitido($baseId,Auth::id(),$usuario['email']):null;
        if ($baseId && !$base) { http_response_code(403); exit('No eres asistente de esta reunión.'); }
        $ruta='';
        if ($base) $reunionId=(int)($base['reunion_id']??0);
        try {
            $correos=Minutas::correos((string)($_POST['presentes']??''));
            if ($base) $correos=array_unique([...$correos,...array_column(Database::todas('SELECT email FROM reuniones_minuta_presentes WHERE minuta_id=?',[$baseId]),'email')]);
            if ($r) $correos=array_unique([...$correos,...array_column(Database::todas("SELECT correo FROM reuniones_participantes WHERE reunion_id=? AND estado_invitacion<>'revocada'",[$reunionId]),'correo')]);
            $correos=Minutas::correos(implode(';',[...$correos,$usuario['email']]));
            $asunto=$base['asunto']??$r['asunto']??trim((string)($_POST['asunto']??''));
            $fecha=$r && $r['fecha_confirmada']?$r['fecha_confirmada'].' '.($r['hora_confirmada_inicio']??'00:00:00'):str_replace('T',' ',(string)($_POST['fecha']??''));
            if ($base) $fecha=$base['fecha'];
            $dia=\DateTimeImmutable::createFromFormat('!Y-m-d H:i',$fecha)?:\DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$fecha);
            if (!$dia || !in_array($dia->format('Y-m-d H:i'),[$fecha,substr($fecha,0,16)],true)) throw new \InvalidArgumentException('Indica una fecha y hora válidas.');
            if (!$asunto || mb_strlen($asunto)>200) throw new \InvalidArgumentException('Indica el asunto (hasta 200 caracteres).');
            $calidad=(string)($_POST['calidad']??'profesor');
            if (!in_array($calidad,['profesor','oficina','asistente'],true)) throw new \InvalidArgumentException('Selecciona la calidad de participación.');
            $convocante=trim((string)($_POST['convocante']??''));
            if (mb_strlen($convocante)>200) throw new \InvalidArgumentException('El convocante admite hasta 200 caracteres.');
            [$resumen,$ruta,$nombre]=$this->cargar();
            Database::pdo()->beginTransaction();
            Database::ejecutar('INSERT INTO reuniones_minutas (reunion_id,autor_id,subido_por_email,asunto,fecha,calidad,convocante,resumen,archivo,nombre_archivo) VALUES (?,?,?,?,?,?,?,?,?,?)',[$reunionId?:null,Auth::id(),strtolower($usuario['email']),$asunto,$dia->format('Y-m-d H:i:s'),$calidad,$convocante,$resumen,$ruta,$nombre]);
            $id=(int)Database::pdo()->lastInsertId(); Minutas::presentes($id,$correos); Database::pdo()->commit();
        } catch (\Throwable $e) {
            if (Database::pdo()->inTransaction()) Database::pdo()->rollBack();
            if ($ruta) unlink(rtrim(App::config('archivos.storage'),'/\\').'/'.$ruta);
            if (!$e instanceof \InvalidArgumentException) throw $e;
            http_response_code(422); $this->vista('minuta_nueva',['titulo'=>'Compartir minuta','reunion'=>$r,'base'=>$base,'error'=>$e->getMessage(),'captura'=>$_POST]); return;
        }
        $this->enviar($id); redirigir('reuniones');
    }
    private function enviar(int $id): void
    {
        $resultado=Minutas::notificar($id);
        flash($resultado['enviados']===$resultado['total']?'success':'warning','Minuta guardada. Correos enviados: '.$resultado['enviados'].' de '.$resultado['total'].'.');
    }
    public function reenviar(string $id): void
    {
        $m=Minutas::permitido((int)$id,Auth::id(),Auth::usuario()['email']);
        if (!$m || (int)$m['autor_id']!==Auth::id()) { http_response_code(403); exit('No puedes reenviar esta minuta.'); }
        $this->enviar((int)$id); redirigir('reuniones');
    }
    private function privado(): void { header('Cache-Control: no-store'); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow'); }
    public function historial(string $token): void
    {
        $this->privado(); $enlace=Minutas::enlace($token);
        if (!$enlace) { http_response_code(404); exit('Enlace no disponible.'); }
        $minutas=array_map([Minutas::class,'contenido'],Minutas::historial($enlace));
        $this->vista('minutas',['titulo'=>'Historial privado de reuniones','minutas'=>$minutas,'token'=>$token],'auth');
    }
    public function guardarExterna(string $token,string $id): void
    {
        $this->privado(); $enlace=Minutas::enlace($token); $base=null;
        if ($enlace) foreach (Minutas::historial($enlace) as $m) if ((int)$m['id']===(int)$id) $base=$m;
        if (!$base) { http_response_code(403); exit('No puedes añadir una minuta a esta reunión.'); }
        $cantidad=(int)Database::valor('SELECT COUNT(*) FROM reuniones_minutas WHERE subido_por_email=? AND creado>=DATE_SUB(NOW(),INTERVAL 1 DAY)',[$enlace['email']]);
        if ($cantidad>=10) { http_response_code(429); exit('Límite diario alcanzado. Intenta mañana.'); }
        $ruta='';
        try {
            [$resumen,$ruta,$nombre]=$this->cargar();
            Database::pdo()->beginTransaction();
            Database::ejecutar('INSERT INTO reuniones_minutas (reunion_id,autor_id,subido_por_email,asunto,fecha,calidad,convocante,resumen,archivo,nombre_archivo) VALUES (?,?,?,?,?,?,?,?,?,?)',[$base['reunion_id'],$base['autor_id'],$enlace['email'],$base['asunto'],$base['fecha'],$base['calidad'],$base['convocante'],$resumen,$ruta,$nombre]);
            $nuevo=(int)Database::pdo()->lastInsertId();
            Minutas::presentes($nuevo,array_column(Database::todas('SELECT email FROM reuniones_minuta_presentes WHERE minuta_id=?',[$base['id']]),'email'));
            Database::pdo()->commit();
        } catch (\Throwable $e) {
            if (Database::pdo()->inTransaction()) Database::pdo()->rollBack();
            if ($ruta) unlink(rtrim(App::config('archivos.storage'),'/\\').'/'.$ruta);
            if (!$e instanceof \InvalidArgumentException) throw $e;
            $_SESSION['minuta_captura'][(int)$id]=mb_substr((string)($_POST['resumen']??''),0,20000);
            flash('warning',$e->getMessage().' Si elegiste un archivo, selecciónalo nuevamente.'); redirigir('reuniones/historial/'.$token);
        }
        $this->enviar($nuevo); redirigir('reuniones/historial/'.$token);
    }
    public function archivo(string $id): void
    {
        $this->descargar(Minutas::permitido((int)$id,Auth::id(),Auth::usuario()['email']));
    }
    public function archivoPublico(string $token,string $id): void
    {
        $this->privado(); $enlace=Minutas::enlace($token); $m=null;
        if ($enlace) foreach (Minutas::historial($enlace) as $item) if ((int)$item['id']===(int)$id) $m=Minutas::contenido($item);
        $this->descargar($m);
    }
    private function descargar(?array $m): never
    {
        if (!$m || !$m['archivo']) { http_response_code(404); exit('Archivo no disponible.'); }
        $base=!empty($m['fila_registro_id'])?dirname(APP_RAIZ).'/storage/fila':App::config('archivos.storage');
        $base=realpath($base); $ruta=realpath($base.'/'.$m['archivo']);
        if (!$base || !$ruta || !str_starts_with($ruta,$base.DIRECTORY_SEPARATOR) || !is_file($ruta)) { http_response_code(404); exit('Archivo no disponible.'); }
        $this->privado(); header('Content-Type: application/octet-stream'); header('X-Content-Type-Options: nosniff');
        header("Content-Disposition: attachment; filename=\"minuta.".pathinfo($ruta,PATHINFO_EXTENSION)."\"; filename*=UTF-8''".rawurlencode($m['nombre_archivo']));
        header('Content-Length: '.filesize($ruta)); readfile($ruta); exit;
    }
}
