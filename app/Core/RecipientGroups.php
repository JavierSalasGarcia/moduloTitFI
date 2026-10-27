<?php
declare(strict_types=1);
namespace App\Core;

/** Shared recipient groups. Visibility never grants editing permission. */
final class RecipientGroups
{
    public const CATEGORIES=['coordinadores'=>'Coordinadores','administrativos'=>'Administrativos','profesores'=>'Profesores','alumnos'=>'Alumnos'];
    private static ?bool $ready=null;
    public static function ready(): bool
    {
        return self::$ready ??= (int)Database::valor("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name IN ('destinatario_grupos','destinatario_grupo_miembros','destinatario_grupo_acceso')")===3;
    }
    public static function install(): void
    {
        if (!Auth::tieneRol('admin')) self::denied();
        foreach([
            "CREATE TABLE IF NOT EXISTS destinatario_grupos (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,nombre VARCHAR(160) NOT NULL,creador_id INT UNSIGNED NOT NULL,visibilidad ENUM('privado','publico') NOT NULL DEFAULT 'privado',version INT UNSIGNED NOT NULL DEFAULT 1,creado_en DATETIME NOT NULL,actualizado_en DATETIME NOT NULL,INDEX propietario(creador_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS destinatario_grupo_miembros (grupo_id INT UNSIGNED NOT NULL,usuario_id INT UNSIGNED NOT NULL,PRIMARY KEY(grupo_id,usuario_id),CONSTRAINT fk_dest_miembros_grupo FOREIGN KEY(grupo_id) REFERENCES destinatario_grupos(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS destinatario_grupo_acceso (grupo_id INT UNSIGNED NOT NULL,tipo ENUM('rol','usuario') NOT NULL,clave VARCHAR(32) NOT NULL,PRIMARY KEY(grupo_id,tipo,clave),INDEX audiencia(tipo,clave),CONSTRAINT fk_dest_acceso_grupo FOREIGN KEY(grupo_id) REFERENCES destinatario_grupos(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        ] as $sql) Database::pdo()->exec($sql);
        self::$ready=null; Audit::record('grupos_destinatarios_activados');
    }
    public static function categories(): array
    {
        $roles=[]; foreach(Auth::roles() as $rol) $roles=array_merge($roles,match($rol) {'coordinador'=>['coordinadores'],'titular','operativo'=>['administrativos'],'profesor'=>['profesores'],'alumno'=>['alumnos'],default=>[]});
        if (Auth::id() && Database::valor('SELECT COUNT(*) FROM reuniones_coordinadores WHERE usuario_id=? AND activo=1',[Auth::id()])) $roles[]='coordinadores';
        return array_unique($roles);
    }
    public static function visibility(): array
    {
        if(Auth::tieneRol('admin')) return ['1=1',[]];
        $categories=self::categories(); $conditions=["(a.tipo='usuario' AND a.clave=?)"]; $params=[Auth::id(),(string)Auth::id()];
        if($categories) { $conditions[]="(a.tipo='rol' AND a.clave IN (".implode(',',array_fill(0,count($categories),'?')).'))'; $params=array_merge($params,$categories); }
        return ["(g.creador_id=? OR (g.visibilidad='publico' AND EXISTS(SELECT 1 FROM destinatario_grupo_acceso a WHERE a.grupo_id=g.id AND (".implode(' OR ',$conditions).'))))',$params];
    }
    public static function visible(string $q=''): array
    {
        if (!Auth::id() || !self::ready()) return [];
        [$where,$params]=self::visibility(); $params[]='%'.$q.'%';
        return Database::todas("SELECT g.*,COALESCE(u.nombre,'Cuenta no disponible') AS creador,(SELECT COUNT(*) FROM destinatario_grupo_miembros m JOIN usuarios mu ON mu.id=m.usuario_id AND mu.activo=1 WHERE m.grupo_id=g.id) AS total FROM destinatario_grupos g LEFT JOIN usuarios u ON u.id=g.creador_id WHERE $where AND g.nombre LIKE ? ORDER BY g.nombre,g.id",$params);
    }
    public static function get(int $id,bool $edit=false,bool $lock=false): array
    {
        if(!self::ready()) self::denied();
        [$where,$params]=self::visibility(); $params[]=$id;
        $g=Database::una("SELECT g.* FROM destinatario_grupos g WHERE $where AND g.id=?".($lock?' FOR UPDATE':''),$params);
        if(!$g || ($edit && !self::editable($g))) self::denied();
        return $g;
    }
    public static function editable(array $g): bool { return Auth::tieneRol('admin') || (int)$g['creador_id']===Auth::id(); }
    public static function members(int $id): array
    {
        self::get($id);
        return Database::todas('SELECT u.id,u.nombre,u.email,u.activo FROM destinatario_grupo_miembros m JOIN usuarios u ON u.id=m.usuario_id WHERE m.grupo_id=? ORDER BY u.nombre,u.id',[$id]);
    }
    public static function recipients(string $field): array
    {
        $ids=self::ids($_POST[$field]??[],10); $users=[];
        foreach($ids as $id) {
            $members=array_filter(self::members($id),fn($u)=>(bool)$u['activo']);
            if(!$members) throw new \InvalidArgumentException('Uno de los grupos elegidos no tiene destinatarios activos.');
            foreach($members as $u) $users[(int)$u['id']]=(int)$u['id'];
        }
        if(count($users)>500) throw new \InvalidArgumentException('Selecciona grupos que sumen como máximo 500 destinatarios distintos.');
        return array_values($users);
    }
    public static function ids(mixed $values,int $max=500): array
    {
        if (!is_array($values) || count($values)>$max) throw new \InvalidArgumentException('Revisa la selección: supera el límite permitido.');
        $ids=[]; foreach($values as $id) { if(!is_scalar($id) || !ctype_digit((string)$id) || (int)$id<1) throw new \InvalidArgumentException('La selección contiene un identificador inválido.'); $ids[(int)$id]=(int)$id; }
        return array_values($ids);
    }
    public static function validateUsers(array $ids): void
    {
        if(!$ids) return;
        if((int)Database::valor('SELECT COUNT(*) FROM usuarios WHERE activo=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids)!==count($ids)) throw new \InvalidArgumentException('Una persona seleccionada ya no está activa. Retírala de la selección.');
    }
    public static function selector(string $name,string $label): string
    {
        return View::capturar('grupo_selector',['grupos'=>self::visible(),'nombreCampo'=>$name,'etiqueta'=>$label]);
    }
    public static function denied(): never
    {
        Audit::record('grupo_destinatarios_acceso','denegado'); http_response_code(403); exit('Este grupo no está disponible o no tienes permiso para modificarlo.');
    }
}
