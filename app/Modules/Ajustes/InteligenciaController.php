<?php
declare(strict_types=1);
namespace App\Modules\Ajustes;
use App\Core\{Controller,Auth,Database,Audit};
use App\Modules\Portal\GeminiClient;

final class InteligenciaController extends Controller
{
    private function admin(): void { if(!Auth::tieneRol('admin') || isset($_SESSION['impersonador'])) {http_response_code(403);exit('Vuelve a tu cuenta de administrador.');} }
    private function put(string $key,string $value): void {
        Database::ejecutar('INSERT INTO ajustes (clave,valor,descripcion) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)',[$key,$value,'Control del asistente']);
    }
    public function index(): void {
        $this->admin();header('Cache-Control: no-store, private');
        $this->vista('inteligencia',['titulo'=>'Asistente y privacidad','keySet'=>ajuste('ia_gemini_api_key')!=='',
            'policies'=>Database::todas("SELECT clave,valor FROM ajustes WHERE clave LIKE 'ia_nivel_tickets_%' OR clave LIKE 'ia_nivel_reuniones_%' ORDER BY clave LIMIT 200"),
            'usage'=>(int)Database::valor('SELECT cantidad FROM acceso_limites WHERE clave=? AND ventana=?',[hash('sha256','ia:global'),(int)floor(time()/86400)])]);
    }
    public function guardar(): void {
        $this->admin();$provider=$this->post('proveedor');$key=$this->post('api_key');
        $daily=filter_var($_POST['limite_diario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
        $user=filter_var($_POST['limite_usuario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000]]);
        if(!in_array($provider,['local','gemini'],true) || !$daily || !$user || $user>$daily || strlen($key)>512 || preg_match('/[\r\n]/',$key)) {
            flash('error','Revisa los límites: de 1 a 10,000 por día y de 1 a 1,000 por usuario, sin superar el total.');redirigir('ajustes/ia');
        }
        $paid=isset($_POST['pago'])?'1':'0';
        if($provider==='gemini' && ($paid!=='1' || ($key==='' && ajuste('ia_gemini_api_key')==='') || isset($_POST['eliminar_clave']))) {
            flash('error','Para activar Gemini agrega la clave y confirma que el proyecto usa servicios de pago. Para eliminar la clave selecciona modo local.');redirigir('ajustes/ia');
        }
        Database::pdo()->beginTransaction();
        try {
            foreach(['ia_proveedor'=>$provider,'ia_gemini_pago'=>$paid,'ia_limite_diario'=>(string)$daily,'ia_limite_usuario'=>(string)$user] as $k=>$v) $this->put($k,$v);
            if(isset($_POST['eliminar_clave'])) $this->put('ia_gemini_api_key',''); elseif($key!=='') $this->put('ia_gemini_api_key',$key);
            Database::pdo()->commit();
        } catch(\Throwable $e) {Database::pdo()->rollBack();throw $e;}
        Audit::record('ia_configuracion','correcto','Proveedor: '.$provider.'; cuota global: '.$daily.'; cuota usuario: '.$user);
        flash('exito','Configuración guardada. Al agotar la cuota se utilizará el modo local.');redirigir('ajustes/ia');
    }
    public function probar(): void {
        $this->admin();header('Content-Type: application/json');header('Cache-Control: no-store');
        $key=$this->post('api_key')?:ajuste('ia_gemini_api_key');
        if($key===''){echo json_encode(['ok'=>false,'mensaje'=>'Introduce una clave o guarda una primero.']);return;}
        if(!GeminiClient::quota()){echo json_encode(['ok'=>false,'mensaje'=>'Cuota alcanzada. Sigue disponible el modo local.']);return;}
        $data=GeminiClient::request($key,'Prueba técnica sin datos personales: responde con ok igual a true.',['type'=>'OBJECT','properties'=>['ok'=>['type'=>'BOOLEAN']],'required'=>['ok']]);
        $ok=($data['ok']??false)===true;
        echo json_encode(['ok'=>$ok,'mensaje'=>$ok?'Gemini respondió correctamente. Guarda la configuración para activarlo.':'No se pudo conectar. Revisa clave, facturación y disponibilidad del modelo. No se enviaron datos de usuarios.']);
    }
    public function clasificar(): void {
        $this->admin();$kind=$this->post('tipo');$level=$this->post('nivel');
        $id=filter_var($_POST['registro_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        if(!in_array($kind,['tickets','reuniones'],true) || !in_array($level,['restringida','confidencial'],true) || !$id) {http_response_code(422);exit('Clasificación no válida.');}
        // Fixed table allowlist; only fetch existence, never content for classification administration.
        if(!Database::valor('SELECT id FROM '.$kind.' WHERE id=?',[$id])) {flash('error','No se encontró ese registro.');redirigir('ajustes/ia');}
        $this->put('ia_nivel_'.$kind.'_'.$id,$level);
        Audit::record('ia_clasificacion','correcto',$kind.' #'.$id.'; '.$level);
        flash('exito','Clasificación del asistente actualizada. No cambia los permisos de las pantallas originales.');redirigir('ajustes/ia');
    }
}
