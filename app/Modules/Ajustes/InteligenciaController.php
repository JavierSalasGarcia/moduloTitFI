<?php
declare(strict_types=1);
namespace App\Modules\Ajustes;
use App\Core\{Controller,Auth,Database,Audit,SecretosIa};
use App\Modules\Portal\{GeminiClient,JevClient,Prompts};

final class InteligenciaController extends Controller
{
    private function admin(): void { if(!Auth::tieneRol('admin') || isset($_SESSION['impersonador'])) {http_response_code(403);exit('Vuelve a tu cuenta de administrador.');} }
    private function put(string $key,string $value): void {
        Database::ejecutar('INSERT INTO ajustes (clave,valor,descripcion) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)',[$key,$value,'Control del asistente']);
    }
    public function index(): void {
        $this->admin();header('Cache-Control: no-store, private');SecretosIa::asegurarCifrado();
        $this->vista('inteligencia',['titulo'=>'Asistente y privacidad','keySet'=>SecretosIa::clave('gemini')!=='','geminiOrigen'=>SecretosIa::origen('gemini'),'jevOrigen'=>SecretosIa::origen('jev'),
            'policies'=>Database::todas("SELECT clave,valor FROM ajustes WHERE clave LIKE 'ia_nivel_tickets_%' OR clave LIKE 'ia_nivel_reuniones_%' ORDER BY clave LIMIT 200"),
            'usage'=>(int)Database::valor('SELECT cantidad FROM acceso_limites WHERE clave=? AND ventana=?',[hash('sha256','ia:global'),(int)floor(time()/86400)])]);
    }
    public function guardar(): void {
        $this->admin();$provider=$this->post('proveedor');$key=$this->post('api_key');
        $daily=filter_var($_POST['limite_diario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
        $user=filter_var($_POST['limite_usuario']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>1000]]);
        $jevKey=$this->post('jev_api_key');$jevModelo=$this->post('jev_modelo')?:JevClient::MODELO_DEFECTO;$geminiModelo=$this->post('gemini_modelo')?:GeminiClient::MODEL;
        $umbral=filter_var($_POST['jev_umbral']??'0.5',FILTER_VALIDATE_FLOAT,['options'=>['min_range'=>0.05,'max_range'=>0.95]]);
        if(!in_array($provider,['local','gemini'],true) || !$daily || !$user || $user>$daily || strlen($key)>512 || preg_match('/\s/',$key) || strlen($jevKey)>512 || preg_match('/\s/',$jevKey)) {
            flash('error','Revisa los límites (de 1 a 10,000 por día y de 1 a 1,000 por usuario, sin superar el total) y que las claves no tengan espacios.');redirigir('ajustes/ia');
        }
        if($umbral===false || !preg_match('/^jev-[a-z0-9.\-]{1,30}$/D',$jevModelo) || !preg_match('/^gemini-[a-z0-9.\-]{1,40}$/D',$geminiModelo)) {
            flash('error','Revisa el modelo de Jev (jev-…), el de Gemini (gemini-…) y el umbral del árbitro (0.05 a 0.95).');redirigir('ajustes/ia');
        }
        if(($key!=='' || $jevKey!=='') && !SecretosIa::puedeGuardar()) {
            flash('error','El servidor no tiene llave de cifrado, así que las claves no se guardan en la base. Configura el cifrado (docs/CIFRADO_DE_DATOS.md) o define la clave como variable de entorno.');redirigir('ajustes/ia');
        }
        $jevActivo=isset($_POST['jev_activo'])?'1':'0';
        if($jevActivo==='1' && $jevKey==='' && (SecretosIa::clave('jev')==='' || isset($_POST['jev_eliminar_clave']))) {
            flash('error','Para activar Jev como árbitro agrega su clave.');redirigir('ajustes/ia');
        }
        $paid=isset($_POST['pago'])?'1':'0';
        if($provider==='gemini' && ($paid!=='1' || ($key==='' && SecretosIa::clave('gemini')==='') || isset($_POST['eliminar_clave']))) {
            flash('error','Para activar Gemini agrega la clave y confirma que el proyecto usa servicios de pago. Para eliminar la clave selecciona modo local.');redirigir('ajustes/ia');
        }
        Database::pdo()->beginTransaction();
        try {
            foreach(['ia_proveedor'=>$provider,'ia_gemini_pago'=>$paid,'ia_limite_diario'=>(string)$daily,'ia_limite_usuario'=>(string)$user,
                'ia_gemini_modelo'=>$geminiModelo,'ia_jev_activo'=>$jevActivo,'ia_jev_modelo'=>$jevModelo,'ia_jev_umbral'=>(string)$umbral] as $k=>$v) $this->put($k,$v);
            // Las claves se guardan cifradas en reposo (App\Core\SecretosIa); nunca vuelven al formulario.
            if(isset($_POST['eliminar_clave'])) SecretosIa::guardar('gemini',''); elseif($key!=='') SecretosIa::guardar('gemini',$key);
            if(isset($_POST['jev_eliminar_clave'])) SecretosIa::guardar('jev',''); elseif($jevKey!=='') SecretosIa::guardar('jev',$jevKey);
            Database::pdo()->commit();
        } catch(\Throwable $e) {Database::pdo()->rollBack();throw $e;}
        Audit::record('ia_configuracion','correcto','Proveedor: '.$provider.'; árbitro Jev: '.($jevActivo==='1'?'activo':'inactivo').'; cuota global: '.$daily.'; cuota usuario: '.$user);
        flash('exito','Configuración guardada. Al agotar la cuota se utilizará el modo local.');redirigir('ajustes/ia');
    }
    public function probar(): void {
        $this->admin();header('Content-Type: application/json');header('Cache-Control: no-store');
        $key=$this->post('api_key')?:SecretosIa::clave('gemini');
        if($key===''){echo json_encode(['ok'=>false,'mensaje'=>'Introduce una clave o guarda una primero.']);return;}
        if(!GeminiClient::quota()){echo json_encode(['ok'=>false,'mensaje'=>'Cuota alcanzada. Sigue disponible el modo local.']);return;}
        $data=GeminiClient::request($key,Prompts::GEMINI_PRUEBA,['type'=>'OBJECT','properties'=>['ok'=>['type'=>'BOOLEAN']],'required'=>['ok']]);
        $ok=($data['ok']??false)===true;
        echo json_encode(['ok'=>$ok,'mensaje'=>$ok?'Gemini respondió correctamente. Guarda la configuración para activarlo.':'No se pudo conectar. Revisa clave, facturación y disponibilidad del modelo. No se enviaron datos de usuarios.']);
    }
    public function probarJev(): void {
        $this->admin();header('Content-Type: application/json');header('Cache-Control: no-store');
        $key=$this->post('jev_api_key')?:SecretosIa::clave('jev');
        if($key==='' || preg_match('/\s/',$key) || strlen($key)>512){echo json_encode(['ok'=>false,'mensaje'=>'Introduce la clave de Jev o guarda una primero.']);return;}
        if(!GeminiClient::quota()){echo json_encode(['ok'=>false,'mensaje'=>'Cuota alcanzada. Sigue disponible el árbitro local.']);return;}
        $ok=JevClient::probar($key);
        echo json_encode(['ok'=>$ok,'mensaje'=>$ok?'Jev respondió correctamente. Guarda la configuración para activarlo.':'No se pudo conectar con Jev. Revisa la clave, el modelo y el acceso de tu organización en TypeSafe. No se enviaron datos de usuarios.']);
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
