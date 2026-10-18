<?php
declare(strict_types=1);
namespace App\Core;

/** Private document repository. All list/detail/download paths share the same ACL. */
final class DocumentArchive
{
    private static ?bool $ready=null;
    public static function ready(): bool {
        return self::$ready??=(int)Database::valor("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('archivo_documentos','archivo_adjuntos','archivo_accesos','archivo_acciones','archivo_extracciones')")===5;
    }
    public static function uploader(): bool {
        return Auth::tieneRol('admin','coordinador') || (bool)Database::valor('SELECT 1 FROM reuniones_coordinadores WHERE usuario_id=? AND activo=1 LIMIT 1',[Auth::id()]);
    }
    public static function assertUploader(): void {if(!self::uploader())self::deny();}
    public static function deny(): never {Audit::record('documentos_acceso','denegado');http_response_code(404);exit('Documento no disponible.');}
    public static function storage(): string {
        $dir=App::config('documentos.storage',dirname(APP_RAIZ,2).'/.fi-documentos');
        if(!is_dir($dir) && !@mkdir($dir,0700,true)) throw new \RuntimeException('No se pudo preparar el almacenamiento privado. Contacta al administrador.');
        $real=realpath($dir);$web=realpath(dirname(APP_RAIZ));
        if(!$real || !$web || str_starts_with(strtolower(str_replace('\\','/',$real)).'/',strtolower(str_replace('\\','/',$web)).'/') || !is_writable($real)) throw new \RuntimeException('El almacenamiento debe estar fuera del directorio público y ser escribible.');
        return $real;
    }
    public static function path(string $key): string {if(!preg_match('/^[a-f0-9]{32}\.bin$/D',$key))throw new \RuntimeException('Archivo no disponible.');return self::storage().DIRECTORY_SEPARATOR.$key;}
    public static function install(): void {
        if(!Auth::tieneRol('admin') || isset($_SESSION['impersonador']))self::deny();self::storage();
        foreach(self::schema() as $sql) Database::ejecutar($sql);
        self::$ready=null;Audit::record('documentos_activar');
    }
    public static function schema(): array {return [
        "CREATE TABLE IF NOT EXISTS archivo_documentos (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, subido_por INT UNSIGNED NOT NULL,
          departamento_id SMALLINT UNSIGNED NOT NULL, coordinacion_id SMALLINT UNSIGNED NULL, programa_id SMALLINT UNSIGNED NULL,
          titulo VARCHAR(200) NOT NULL, tipo_documento VARCHAR(100) NOT NULL DEFAULT '', folio VARCHAR(100) NOT NULL DEFAULT '',
          fecha_documento DATE NULL, emisor VARCHAR(200) NOT NULL DEFAULT '', destinatario VARCHAR(200) NOT NULL DEFAULT '',
          palabras_clave TEXT NOT NULL, resumen TEXT NOT NULL, nivel ENUM('restringida','confidencial') NOT NULL DEFAULT 'restringida',
          estado ENUM('borrador','indizado') NOT NULL DEFAULT 'borrador', version INT UNSIGNED NOT NULL DEFAULT 1,
          creado_en DATETIME NOT NULL, actualizado_en DATETIME NOT NULL, indizado_en DATETIME NULL,
          INDEX (subido_por,estado,creado_en), INDEX (departamento_id,creado_en), INDEX (coordinacion_id), INDEX(programa_id), INDEX(fecha_documento), INDEX(folio),
          FULLTEXT KEY busqueda (titulo,tipo_documento,folio,emisor,destinatario,palabras_clave,resumen)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS archivo_accesos (
          documento_id BIGINT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL, permiso ENUM('leer','responder') NOT NULL DEFAULT 'leer',
          otorgado_por INT UNSIGNED NOT NULL, creado_en DATETIME NOT NULL, PRIMARY KEY(documento_id,usuario_id), INDEX(usuario_id),
          FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS archivo_acciones (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, documento_id BIGINT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL,
          tipo VARCHAR(40) NOT NULL, descripcion TEXT NOT NULL, responsable_id INT UNSIGNED NULL, vence_en DATE NULL,
          estado ENUM('registrada','pendiente','completada') NOT NULL DEFAULT 'registrada', creado_en DATETIME NOT NULL, completado_en DATETIME NULL,
          INDEX(documento_id,creado_en), INDEX(responsable_id,estado), FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS archivo_adjuntos (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, documento_id BIGINT UNSIGNED NOT NULL, usuario_id INT UNSIGNED NOT NULL,
          clave VARCHAR(40) NOT NULL UNIQUE, nombre_original VARCHAR(200) NOT NULL, mime VARCHAR(80) NOT NULL, bytes INT UNSIGNED NOT NULL,
          sha256 CHAR(64) NOT NULL, tipo ENUM('original','respuesta','anexo') NOT NULL, creado_en DATETIME NOT NULL,
          INDEX(documento_id,tipo), INDEX(sha256), FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        "CREATE TABLE IF NOT EXISTS archivo_extracciones (
          documento_id BIGINT UNSIGNED PRIMARY KEY, version_documento INT UNSIGNED NOT NULL, estado ENUM('procesando','lista','error') NOT NULL,
          resultado MEDIUMTEXT NULL, solicitado_por INT UNSIGNED NOT NULL, creado_en DATETIME NOT NULL, actualizado_en DATETIME NOT NULL,
          FOREIGN KEY(documento_id) REFERENCES archivo_documentos(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];}
    public static function scope(string $alias='d'): string {
        return "($alias.subido_por=? OR ($alias.estado='indizado' AND EXISTS (SELECT 1 FROM archivo_accesos acl WHERE acl.documento_id=$alias.id AND acl.usuario_id=?)))";
    }
    public static function get(int $id,bool $owner=false,bool $lock=false): array {
        if(!self::ready())self::deny();
        $d=Database::una('SELECT d.* FROM archivo_documentos d WHERE d.id=? AND '.self::scope().($lock?' FOR UPDATE':''),[$id,Auth::id(),Auth::id()]);
        if(!$d || ($owner && (int)$d['subido_por']!==Auth::id()))self::deny();return $d;
    }
    public static function canRespond(array $d): bool {
        return (int)$d['subido_por']===Auth::id() || (bool)Database::valor("SELECT 1 FROM archivo_accesos WHERE documento_id=? AND usuario_id=? AND permiso='responder'",[$d['id'],Auth::id()]);
    }
    public static function event(int $id,string $type,string $text=''): void {
        Database::ejecutar('INSERT INTO archivo_acciones (documento_id,usuario_id,tipo,descripcion,creado_en) VALUES (?,?,?,?,?)',[$id,Auth::id(),$type,$text,date('Y-m-d H:i:s')]);
        Audit::record('documentos_'.$type,'correcto','Documento #'.$id);
    }
    public static function org(): array {
        $all=Auth::tieneRol('admin');
        $coord=Database::todas('SELECT rp.id,rp.codigo,rp.nombre FROM reuniones_posgrados rp JOIN reuniones_coordinadores rc ON rc.posgrado_id=rp.id WHERE rc.usuario_id=? AND rc.activo=1 AND rp.activo=1',[Auth::id()]);
        $programs=Database::todas('SELECT id,siglas,nombre,depto_id FROM programas ORDER BY nombre');
        if(!$all){$codes=array_column($coord,'codigo');$programs=array_values(array_filter($programs,fn($p)=>$codes?in_array($p['siglas'],$codes,true):(int)$p['depto_id']===Auth::departamentoId()));}
        $deptIds=array_unique(array_merge(array_column($programs,'depto_id'),[Auth::departamentoId()]));
        $departments=Database::todas('SELECT id,nombre FROM departamentos ORDER BY nombre');
        if(!$all)$departments=array_values(array_filter($departments,fn($d)=>in_array($d['id'],$deptIds)));
        if($all)$coord=Database::todas('SELECT id,codigo,nombre FROM reuniones_posgrados WHERE activo=1 ORDER BY nombre');
        return ['departamentos'=>$departments,'programas'=>$programs,'coordinaciones'=>$coord];
    }
    public static function validateOrg(int $dept,?int $program,?int $coord): void {
        $org=self::org();if(!in_array($dept,array_column($org['departamentos'],'id')))throw new \InvalidArgumentException('Selecciona un departamento autorizado.');
        if($program){$p=array_values(array_filter($org['programas'],fn($x)=>(int)$x['id']===$program));if(!$p || (int)$p[0]['depto_id']!==$dept)throw new \InvalidArgumentException('El programa no corresponde al departamento o a tu coordinación.');}
        if($coord && !in_array($coord,array_column($org['coordinaciones'],'id')))throw new \InvalidArgumentException('Coordinación no autorizada.');
        if($coord && $program){$c=array_values(array_filter($org['coordinaciones'],fn($x)=>(int)$x['id']===$coord))[0];if($c['codigo']!==$p[0]['siglas'])throw new \InvalidArgumentException('La coordinación no corresponde al programa.');}
    }
    public static function uploads(): array {
        $f=$_FILES['archivos']??[];$names=$f['name']??[];
        if(!is_array($names) || count($names)<1 || count($names)>3)throw new \InvalidArgumentException('Sube entre 1 y 3 archivos: PDF, JPG o PNG.');
        $out=[];$total=0;
        foreach($names as $i=>$name){
            $tmp=$f['tmp_name'][$i]??'';$error=$f['error'][$i]??UPLOAD_ERR_NO_FILE;
            if($error!==UPLOAD_ERR_OK || !is_uploaded_file($tmp))throw new \InvalidArgumentException('No se recibió el archivo completo. Revisa el límite de subida del servidor.');
            $size=filesize($tmp);$total+=$size;
            if($size<1 || $size>4*1048576 || $total>8*1048576)throw new \InvalidArgumentException('Máximo 4 MB por archivo y 8 MB por envío.');
            $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
            $ext=strtolower(pathinfo((string)$name,PATHINFO_EXTENSION));
            if(!in_array($mime,['application/pdf','image/jpeg','image/png'],true) || !in_array($ext,['pdf','jpg','jpeg','png'],true))throw new \InvalidArgumentException('Solo se admiten PDF, JPG y PNG reales.');
            if(str_starts_with($mime,'image/')){$dim=@getimagesize($tmp);if(!$dim || $dim[0]*$dim[1]>24000000)throw new \InvalidArgumentException('Imagen inválida o demasiado grande: máximo 24 megapíxeles.');}
            $out[]=['tmp'=>$tmp,'nombre'=>mb_substr(basename(str_replace('\\','/',(string)$name)),0,200),'mime'=>$mime,'bytes'=>$size,'sha256'=>hash_file('sha256',$tmp)];
        }return $out;
    }
    public static function persistFiles(int $id,array $files,string $type,array &$created): void {
        foreach($files as $f){$key=bin2hex(random_bytes(16)).'.bin';$path=self::path($key);
            if(!move_uploaded_file($f['tmp'],$path))throw new \RuntimeException('No se pudo guardar el archivo en el almacenamiento privado.');
            $created[]=$path;@chmod($path,0600);
            Database::ejecutar('INSERT INTO archivo_adjuntos (documento_id,usuario_id,clave,nombre_original,mime,bytes,sha256,tipo,creado_en) VALUES (?,?,?,?,?,?,?,?,?)',[$id,Auth::id(),$key,$f['nombre'],$f['mime'],$f['bytes'],$f['sha256'],$type,date('Y-m-d H:i:s')]);
        }
    }
}
