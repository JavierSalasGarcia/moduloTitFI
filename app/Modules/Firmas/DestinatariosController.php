<?php
declare(strict_types=1);
namespace App\Modules\Firmas;
use App\Core\{Auth,Audit,Controller,Database,RecipientGroups as Groups};

final class DestinatariosController extends Controller
{
    public function index(): void
    {
        $q=mb_substr(trim((string)($_GET['q']??'')),0,160);
        $this->vista('destinatarios',['titulo'=>'Grupos de destinatarios','grupos'=>Groups::visible($q),'q'=>$q,'listo'=>Groups::ready()]);
    }
    public function activar(): void { Groups::install(); flash('exito','Grupos de destinatarios activados.'); redirigir('grupos-destinatarios'); }
    public function nuevo(): void
    {
        if(!Groups::ready()) redirigir('grupos-destinatarios');
        $this->vista('destinatario_form',['titulo'=>'Crear grupo','g'=>['id'=>0,'nombre'=>'','creador_id'=>Auth::id(),'visibilidad'=>'privado','version'=>0],'miembros'=>[],'audiencia'=>[],'roles'=>[],'editable'=>true]);
    }
    public function detalle(string $id): void
    {
        $g=Groups::get((int)$id);
        $this->vista('destinatario_form',['titulo'=>'Grupo de destinatarios','g'=>$g,'miembros'=>Groups::members((int)$id),'editable'=>Groups::editable($g),
            'roles'=>array_column(Database::todas("SELECT clave FROM destinatario_grupo_acceso WHERE grupo_id=? AND tipo='rol'",[$id]),'clave'),
            'audiencia'=>Auth::tieneRol('admin')?Database::todas("SELECT u.id,u.nombre,u.email FROM destinatario_grupo_acceso a JOIN usuarios u ON u.id=CAST(a.clave AS UNSIGNED) WHERE a.grupo_id=? AND a.tipo='usuario' ORDER BY u.nombre",[$id]):[]]);
    }
    public function personas(): void
    {
        header('Content-Type: application/json; charset=utf-8'); $q=mb_substr(trim((string)($_GET['q']??'')),0,120);
        echo json_encode(['personas'=>mb_strlen($q)<2?[]:Database::todas('SELECT id,nombre,email FROM usuarios WHERE activo=1 AND (nombre LIKE ? OR email LIKE ?) ORDER BY nombre LIMIT 20',['%'.$q.'%','%'.$q.'%'])]);
    }
    public function crear(): void { $this->save(0); }
    public function actualizar(string $id): void { $this->save((int)$id); }
    private function save(int $id): void
    {
        if(!Groups::ready()) redirigir('grupos-destinatarios');
        if(!Auth::tieneRol('admin') && (isset($_POST['visibilidad']) || isset($_POST['roles']) || isset($_POST['audiencia']))) Groups::denied();
        $pdo=Database::pdo();
        try {
            $nombre=$this->post('nombre'); if($nombre==='' || mb_strlen($nombre)>160) throw new \InvalidArgumentException('Escribe un nombre de hasta 160 caracteres.');
            $miembros=Groups::ids($_POST['miembros']??[]); Groups::validateUsers($miembros);
            $audiencia=Auth::tieneRol('admin')?Groups::ids($_POST['audiencia']??[]):[]; Groups::validateUsers($audiencia);
            $roles=Auth::tieneRol('admin')?(array)($_POST['roles']??[]):[];
            foreach($roles as $rol) if(!is_string($rol) || !isset(Groups::CATEGORIES[$rol])) throw new \InvalidArgumentException('La categoría seleccionada no es válida.');
            $roles=array_values(array_unique($roles));
            $visibility=$this->post('visibilidad','privado');
            if(Auth::tieneRol('admin') && !in_array($visibility,['privado','publico'],true)) throw new \InvalidArgumentException('La visibilidad seleccionada no es válida.');
            if(Auth::tieneRol('admin') && $visibility==='publico' && !$roles && !$audiencia) throw new \InvalidArgumentException('Elige al menos una categoría o una persona con quien compartir el grupo.');
            $pdo->beginTransaction();
            if($id) { $g=Groups::get($id,true,true); if((int)$this->post('version')!==(int)$g['version']) throw new \InvalidArgumentException('Este grupo cambió mientras lo editabas. Recarga la página para revisar la versión actual.');
                Database::ejecutar('UPDATE destinatario_grupos SET nombre=?,version=version+1,actualizado_en=? WHERE id=?',[$nombre,date('Y-m-d H:i:s'),$id]);
            } else $id=Database::ejecutar('INSERT INTO destinatario_grupos (nombre,creador_id,creado_en,actualizado_en) VALUES (?,?,?,?)',[$nombre,Auth::id(),date('Y-m-d H:i:s'),date('Y-m-d H:i:s')]);
            Database::ejecutar('DELETE FROM destinatario_grupo_miembros WHERE grupo_id=?',[$id]);
            foreach($miembros as $user) Database::ejecutar('INSERT INTO destinatario_grupo_miembros (grupo_id,usuario_id) VALUES (?,?)',[$id,$user]);
            if(Auth::tieneRol('admin')) {
                Database::ejecutar('UPDATE destinatario_grupos SET visibilidad=? WHERE id=?',[$visibility,$id]);
                Database::ejecutar('DELETE FROM destinatario_grupo_acceso WHERE grupo_id=?',[$id]);
                if($visibility==='publico') {
                    foreach($roles as $rol) Database::ejecutar("INSERT INTO destinatario_grupo_acceso (grupo_id,tipo,clave) VALUES (?,'rol',?)",[$id,$rol]);
                    foreach($audiencia as $user) Database::ejecutar("INSERT INTO destinatario_grupo_acceso (grupo_id,tipo,clave) VALUES (?,'usuario',?)",[$id,(string)$user]);
                }
            }
            $pdo->commit(); Audit::record('grupo_destinatarios_guardado','correcto','Grupo #'.$id); flash('exito','Grupo guardado.'); redirigir('grupos-destinatarios/'.$id);
        } catch(\InvalidArgumentException $e) { if($pdo->inTransaction())$pdo->rollBack(); flash('error',$e->getMessage()); redirigir($id?'grupos-destinatarios/'.$id:'grupos-destinatarios/nuevo'); }
        catch(\Throwable $e) { if($pdo->inTransaction())$pdo->rollBack(); throw $e; }
    }
    public function eliminar(string $id): void
    {
        $pdo=Database::pdo();$pdo->beginTransaction();
        try { $g=Groups::get((int)$id,true,true); if((int)$this->post('version')!==(int)$g['version']) { $pdo->rollBack(); flash('error','El grupo cambió. Revísalo antes de eliminarlo.'); redirigir('grupos-destinatarios/'.$id); }
            Database::ejecutar('DELETE FROM destinatario_grupos WHERE id=?',[(int)$id]);$pdo->commit();
        } catch(\Throwable $e) { if($pdo->inTransaction())$pdo->rollBack();throw $e; }
        Audit::record('grupo_destinatarios_eliminado','correcto','Grupo #'.(int)$id);flash('exito','Grupo eliminado. Los envíos anteriores conservan sus destinatarios.');redirigir('grupos-destinatarios');
    }
}
