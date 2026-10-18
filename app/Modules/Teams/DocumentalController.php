<?php
declare(strict_types=1);
namespace App\Modules\Teams;
use App\Core\{Auth,Controller,Database as DB,DocumentArchive as A,Audit,RecipientGroups};

final class DocumentalController extends Controller
{
    private function ready(): void {if(!A::ready()){flash('error','El administrador debe activar el archivo documental.');redirigir('documentos');}}
    private function fail(\Throwable $e,string $route): never {
        if(DB::pdo()->inTransaction())DB::pdo()->rollBack();
        $message=$e instanceof \InvalidArgumentException?$e->getMessage():'No se pudo completar la operación. El documento conservó sus datos; intenta de nuevo.';
        Audit::record('documentos_error','error',get_class($e));flash('error',$message);redirigir($route);
    }
    public function index(): void {
        header('Cache-Control: no-store, private');$rows=[];$more=false;$q=mb_substr(trim((string)($_GET['q']??'')),0,160);$page=max(1,min(10000,(int)($_GET['pagina']??1)));
        if(A::ready()){
            $where=[A::scope()];$params=[Auth::id(),Auth::id()];
            if($q!==''){$like='%'.strtr($q,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';$where[]="(MATCH(d.titulo,d.tipo_documento,d.folio,d.emisor,d.destinatario,d.palabras_clave,d.resumen) AGAINST (? IN NATURAL LANGUAGE MODE) OR d.folio LIKE ? ESCAPE '!' OR d.titulo LIKE ? ESCAPE '!')";array_push($params,$q,$like,$like);}
            foreach(['departamento_id','programa_id','coordinacion_id','subido_por'] as $f)if(!empty($_GET[$f])){$where[]='d.'.$f.'=?';$params[]=(int)$_GET[$f];}
            foreach(['estado'=>['borrador','indizado'],'nivel'=>['restringida','confidencial']] as $f=>$allowed)if(in_array($_GET[$f]??'',$allowed,true)){$where[]='d.'.$f.'=?';$params[]=$_GET[$f];}
            $action=$_GET['accion']??'';
            if(in_array($action,['pendiente','completada'],true)){$where[]="EXISTS (SELECT 1 FROM archivo_acciones tarea WHERE tarea.documento_id=d.id AND tarea.tipo='tarea' AND tarea.estado=?)";$params[]=$action;}
            elseif($action==='respuesta'){$where[]="EXISTS (SELECT 1 FROM archivo_adjuntos respuesta WHERE respuesta.documento_id=d.id AND respuesta.tipo='respuesta')";}
            foreach(['subida_desde'=>['creado_en','>='],'subida_hasta'=>['creado_en','<='],'documento_desde'=>['fecha_documento','>='],'documento_hasta'=>['fecha_documento','<=']] as $f=>[$col,$op])if(!empty($_GET[$f]) && self::date((string)$_GET[$f])){$where[]='d.'.$col.$op.'?';$params[]=$_GET[$f].($col==='creado_en'?($op==='>='?' 00:00:00':' 23:59:59'):'');}
            $rows=DB::todas('SELECT d.id,d.titulo,d.folio,d.nivel,d.estado,d.creado_en,d.fecha_documento,u.nombre AS autor,dep.nombre AS departamento,p.nombre AS programa,c.nombre AS coordinacion FROM archivo_documentos d JOIN usuarios u ON u.id=d.subido_por JOIN departamentos dep ON dep.id=d.departamento_id LEFT JOIN programas p ON p.id=d.programa_id LEFT JOIN reuniones_posgrados c ON c.id=d.coordinacion_id WHERE '.implode(' AND ',$where).' ORDER BY d.creado_en DESC,d.id DESC LIMIT 21 OFFSET '.(($page-1)*20),$params);
            $more=count($rows)>20;$rows=array_slice($rows,0,20);Audit::record('documentos_buscar','filtrada','Resultados: '.count($rows));
        }
        $authors=A::ready()?DB::todas('SELECT DISTINCT u.id,u.nombre FROM usuarios u JOIN archivo_documentos d ON d.subido_por=u.id WHERE '.A::scope().' ORDER BY u.nombre',[Auth::id(),Auth::id()]):[];
        $this->vista('documentos',['titulo'=>'Archivo documental','rows'=>$rows,'q'=>$q,'page'=>$page,'more'=>$more,'listo'=>A::ready(),'puedeSubir'=>A::uploader(),'autores'=>$authors,
            'departamentos'=>DB::todas('SELECT id,nombre FROM departamentos ORDER BY nombre'),'programas'=>DB::todas('SELECT id,nombre FROM programas ORDER BY nombre'),'coordinaciones'=>DB::todas('SELECT id,nombre FROM reuniones_posgrados ORDER BY nombre')]);
    }
    public function activar(): void {try{A::install();flash('exito','Archivo documental activado con almacenamiento privado.');redirigir('documentos');}catch(\Throwable $e){$this->fail($e,'documentos');}}
    public function nuevo(): void {$this->ready();A::assertUploader();$this->vista('documento_nuevo',['titulo'=>'Subir documento','org'=>A::org()]);}
    public function crear(): void {
        $this->ready();A::assertUploader();$created=[];
        try{$dept=(int)($_POST['departamento_id']??0);$program=(int)($_POST['programa_id']??0)?:null;$coord=(int)($_POST['coordinacion_id']??0)?:null;A::validateOrg($dept,$program,$coord);
            $title=$this->post('titulo');$level=$this->post('nivel');if($title===''||mb_strlen($title)>200||!in_array($level,['restringida','confidencial'],true))throw new \InvalidArgumentException('Indica título y nivel de confidencialidad.');
            $files=A::uploads();DB::pdo()->beginTransaction();
            $id=DB::ejecutar('INSERT INTO archivo_documentos (subido_por,departamento_id,coordinacion_id,programa_id,titulo,palabras_clave,resumen,nivel,creado_en,actualizado_en) VALUES (?,?,?,?,?,?,?,?,?,?)',[Auth::id(),$dept,$coord,$program,$title,'','',$level,date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);
            A::persistFiles($id,$files,'original',$created);A::event($id,'subida','Borrador creado.');DB::pdo()->commit();redirigir('documentos/'.$id);
        }catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::pdo()->rollBack();foreach($created as $file)if(is_file($file))unlink($file);$this->fail($e,'documentos/nuevo');}
    }
    public function detalle(string $id): void {
        header('Cache-Control: no-store, private');$d=A::get((int)$id);$owner=(int)$d['subido_por']===Auth::id();
        $this->vista('documento',['titulo'=>'Documento #'.$d['id'],'d'=>$d,'owner'=>$owner,'org'=>$owner?A::org():[],
            'files'=>DB::todas('SELECT id,nombre_original,mime,bytes,tipo,creado_en FROM archivo_adjuntos WHERE documento_id=? ORDER BY id',[$d['id']]),
            'actions'=>DB::todas('SELECT a.*,u.nombre AS autor,r.nombre AS responsable FROM archivo_acciones a JOIN usuarios u ON u.id=a.usuario_id LEFT JOIN usuarios r ON r.id=a.responsable_id WHERE a.documento_id=? ORDER BY a.id DESC LIMIT 100',[$d['id']]),
            'shares'=>$owner?DB::todas('SELECT acl.usuario_id,acl.permiso,u.nombre FROM archivo_accesos acl JOIN usuarios u ON u.id=acl.usuario_id WHERE acl.documento_id=?',[$d['id']]):[],
            'extraction'=>$owner?DB::una("SELECT resultado,version_documento FROM archivo_extracciones WHERE documento_id=? AND estado='lista'",[$d['id']]):null,
            'respond'=>A::canRespond($d)]);Audit::record('documentos_ver','correcto','Documento #'.$id);
    }
    public static function date(string $value): bool {$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$value);return $d && $d->format('Y-m-d')===$value;}
    private function version(array $d): void {if((int)($_POST['version']??0)!==(int)$d['version'])throw new \InvalidArgumentException('El documento cambió. Recarga la página antes de continuar.');}
    public function guardar(string $id): void {
        try{DB::pdo()->beginTransaction();$d=A::get((int)$id,true,true);$this->version($d);
            $values=[];foreach(['titulo'=>200,'tipo_documento'=>100,'folio'=>100,'emisor'=>200,'destinatario'=>200,'palabras_clave'=>1000,'resumen'=>4000] as $field=>$max){$v=$this->post($field);if(mb_strlen($v)>$max||($field==='titulo'&&$v===''))throw new \InvalidArgumentException('Revisa la longitud y el título de los campos.');$values[]=$v;}
            $date=$this->post('fecha_documento');if($date!==''&&!self::date($date))throw new \InvalidArgumentException('La fecha del documento no es válida.');
            $level=$this->post('nivel');if(!in_array($level,['restringida','confidencial'],true))throw new \InvalidArgumentException('Clasificación no válida.');
            $state=isset($_POST['confirmar'])?'indizado':$d['estado'];
            array_push($values,$date?:null,$level,$state,$state==='indizado'?($d['indizado_en']?:date('Y-m-d H:i:s')):null,date('Y-m-d H:i:s'),$d['id']);
            DB::ejecutar('UPDATE archivo_documentos SET titulo=?,tipo_documento=?,folio=?,emisor=?,destinatario=?,palabras_clave=?,resumen=?,fecha_documento=?,nivel=?,estado=?,indizado_en=?,actualizado_en=?,version=version+1 WHERE id=?',$values);
            A::event($d['id'],$state==='indizado'?'indizar':'editar','Metadatos revisados por el propietario.');DB::pdo()->commit();flash('exito','Datos guardados'.($state==='indizado'?' e indizados.':'.'));redirigir('documentos/'.$id);
        }catch(\Throwable $e){$this->fail($e,'documentos/'.$id);}
    }
    public function extraer(string $id): void {
        try{A::assertUploader();$initial=A::get((int)$id,true);$this->version($initial);
            if(!isset($_POST['consentimiento']) || $initial['nivel']!=='restringida')throw new \InvalidArgumentException('Solo se extraen documentos restringidos con tu autorización expresa para enviarlos a Google.');
            $cached=DB::valor("SELECT 1 FROM archivo_extracciones WHERE documento_id=? AND estado='lista'",[$initial['id']]);
            if(!$cached && (ajuste('ia_proveedor','local')!=='gemini' || ajuste('ia_gemini_pago','0')!=='1' || ajuste('ia_gemini_api_key')===''))throw new \InvalidArgumentException('Configura Gemini con un proyecto de pago en Ajustes. Puedes llenar e indizar el formulario manualmente sin consumir IA.');
            if(!$cached && (\App\Core\AccessControl::limited('ia:documento:usuario:'.Auth::id(),5,86400) || \App\Core\AccessControl::limited('ia:documento:global',20,86400) || !\App\Modules\Portal\GeminiClient::quota()))throw new \InvalidArgumentException('Se alcanzó la cuota de extracción. Puedes continuar manualmente.');
            DB::pdo()->beginTransaction();$d=A::get((int)$id,true,true);$this->version($d);
            if(!isset($_POST['consentimiento']))throw new \InvalidArgumentException('Confirma el envío de las páginas a Google para extraer sus datos.');
            if($d['nivel']!=='restringida')throw new \InvalidArgumentException('Los documentos confidenciales se indizan manualmente, sin enviarlos a Google.');
            $old=DB::una("SELECT documento_id FROM archivo_extracciones WHERE documento_id=? AND estado='lista'",[$d['id']]);
            if(!$old){$files=DB::todas("SELECT * FROM archivo_adjuntos WHERE documento_id=? AND tipo='original' ORDER BY id",[$d['id']]);$data=DocumentExtractor::extract($d,$files);
                DB::ejecutar("INSERT INTO archivo_extracciones (documento_id,version_documento,estado,resultado,solicitado_por,creado_en,actualizado_en) VALUES (?,?,'lista',?,?,?,?)",[$d['id'],$d['version'],json_encode($data,JSON_UNESCAPED_UNICODE),Auth::id(),date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);A::event($d['id'],'extraer','Propuesta pendiente de revisión.');}
            DB::pdo()->commit();flash('exito','Propuesta disponible. Revisa cada campo antes de guardar; no se modificaron los permisos ni los datos del formulario.');redirigir('documentos/'.$id);
        }catch(\Throwable $e){$this->fail($e,'documentos/'.$id);}
    }
    public function compartir(string $id): void {
        try{DB::pdo()->beginTransaction();$d=A::get((int)$id,true,true);if($d['estado']!=='indizado')throw new \InvalidArgumentException('Revisa e indiza el documento antes de compartirlo.');
            $ids=RecipientGroups::ids($_POST['usuarios']??[],50);$ids=array_values(array_unique(array_merge($ids,RecipientGroups::recipients('grupos_documento'))));
            if(!$ids || count($ids)>50)throw new \InvalidArgumentException('Selecciona entre 1 y 50 destinatarios.');RecipientGroups::validateUsers($ids);
            $permission=$this->post('permiso');if(!in_array($permission,['leer','responder'],true))throw new \InvalidArgumentException('Permiso no válido.');
            foreach($ids as $uid)if($uid!==Auth::id())DB::ejecutar('INSERT INTO archivo_accesos (documento_id,usuario_id,permiso,otorgado_por,creado_en) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE permiso=VALUES(permiso)',[$d['id'],$uid,$permission,Auth::id(),date('Y-m-d H:i:s')]);
            A::event($d['id'],'compartir','Acceso interno concedido a '.count($ids).' destinatario(s).');DB::pdo()->commit();flash('exito','Documento compartido dentro de la plataforma. No se envió correo ni una copia pública.');redirigir('documentos/'.$id);
        }catch(\Throwable $e){$this->fail($e,'documentos/'.$id);}
    }
    public function revocar(string $id): void {
        try{DB::pdo()->beginTransaction();$d=A::get((int)$id,true,true);DB::ejecutar('DELETE FROM archivo_accesos WHERE documento_id=? AND usuario_id=?',[$d['id'],(int)($_POST['usuario_id']??0)]);A::event($d['id'],'revocar','Acceso retirado.');DB::pdo()->commit();redirigir('documentos/'.$id);}catch(\Throwable $e){$this->fail($e,'documentos/'.$id);}
    }
    public function adjuntar(string $id): void {
        $created=[];try{DB::pdo()->beginTransaction();$d=A::get((int)$id,false,true);if(!A::canRespond($d))A::deny();
            $files=A::uploads();if((int)DB::valor('SELECT COUNT(*) FROM archivo_adjuntos WHERE documento_id=?',[$d['id']])+count($files)>30)throw new \InvalidArgumentException('Máximo 30 archivos por expediente.');
            A::persistFiles($d['id'],$files,'respuesta',$created);A::event($d['id'],'respuesta',mb_substr($this->post('descripcion'),0,1500));DB::pdo()->commit();redirigir('documentos/'.$id);
        }catch(\Throwable $e){if(DB::pdo()->inTransaction())DB::pdo()->rollBack();foreach($created as $file)if(is_file($file))unlink($file);$this->fail($e,'documentos/'.$id);}
    }
    public function accion(string $id): void {
        try{DB::pdo()->beginTransaction();$d=A::get((int)$id,true,true);$text=$this->post('descripcion');$uid=(int)($_POST['responsable_id']??Auth::id());$date=$this->post('vence_en');
            if($text===''||mb_strlen($text)>1500||($date!==''&&!self::date($date)))throw new \InvalidArgumentException('Indica una acción de hasta 1,500 caracteres y una fecha válida.');
            if($uid!==Auth::id()&&!DB::valor("SELECT 1 FROM archivo_accesos WHERE documento_id=? AND usuario_id=? AND permiso='responder'",[$d['id'],$uid]))throw new \InvalidArgumentException('El responsable debe tener permiso para responder al documento.');
            DB::ejecutar("INSERT INTO archivo_acciones (documento_id,usuario_id,tipo,descripcion,responsable_id,vence_en,estado,creado_en) VALUES (?,?,'tarea',?,?,?,'pendiente',?)",[$d['id'],Auth::id(),$text,$uid,$date?:null,date('Y-m-d H:i:s')]);Audit::record('documentos_tarea','correcto','Documento #'.$id);DB::pdo()->commit();redirigir('documentos/'.$id);
        }catch(\Throwable $e){$this->fail($e,'documentos/'.$id);}
    }
    public function completar(string $id,string $accion): void {
        try{DB::pdo()->beginTransaction();$d=A::get((int)$id,false,true);$a=DB::una('SELECT * FROM archivo_acciones WHERE id=? AND documento_id=?',[(int)$accion,$d['id']]);
            if(!$a || ((int)$d['subido_por']!==Auth::id() && ((int)$a['responsable_id']!==Auth::id()||!A::canRespond($d))))A::deny();
            DB::ejecutar("UPDATE archivo_acciones SET estado='completada',completado_en=? WHERE id=? AND estado='pendiente'",[date('Y-m-d H:i:s'),$a['id']]);A::event($d['id'],'completar','Acción #'.$a['id']);DB::pdo()->commit();redirigir('documentos/'.$id);
        }catch(\Throwable $e){$this->fail($e,'documentos/'.$id);}
    }
    public function archivo(string $id,string $archivo): void {
        A::get((int)$id);$f=DB::una('SELECT * FROM archivo_adjuntos WHERE id=? AND documento_id=?',[(int)$archivo,(int)$id]);if(!$f)A::deny();$path=A::path($f['clave']);if(!is_file($path))A::deny();
        header('Cache-Control: no-store, private');header('X-Content-Type-Options: nosniff');header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Content-Type: '.$f['mime']);$inline=($_GET['vista']??'')==='1';header('Content-Disposition: '.($inline?'inline':'attachment').'; filename="documento-'.(int)$archivo.'.'.($f['mime']==='application/pdf'?'pdf':($f['mime']==='image/png'?'png':'jpg')).'"');
        header('Content-Length: '.filesize($path));Audit::record('documentos_descargar','correcto','Documento #'.$id.'; archivo #'.$archivo);readfile($path);
    }
}
