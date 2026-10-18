<?php
namespace App\Modules\Teams {
    // CLI-only transport doubles. Never deployed; no network calls.
    function curl_init($url){$GLOBALS['doc_test_url']=$url;return 'test';}
    function curl_setopt_array($ch,$options){$GLOBALS['doc_test_payload']=json_decode($options[CURLOPT_POSTFIELDS],true);}
    function curl_exec($ch){return json_encode(['candidates'=>[['finishReason'=>'STOP','content'=>['parts'=>[['text'=>json_encode($GLOBALS['doc_test_result'])]]]]],'usageMetadata'=>['totalTokenCount'=>200]]);}
    function curl_getinfo($ch,$option){return 200;}
    function curl_close($ch){}
}
namespace {
if(PHP_SAPI!=='cli')exit;
require dirname(__DIR__).'/app/bootstrap.php';$config=require APP_RAIZ.'/config.php';$config['db']=['host'=>'127.0.0.1','nombre'=>'fi_ux_test_20260909','usuario'=>'root','clave'=>'','charset'=>'utf8mb4'];$config['documentos']['storage']=sys_get_temp_dir().'/fi-documentos-qa';(new ReflectionProperty(App\Core\App::class,'config'))->setValue(null,$config);
use App\Core\{Database as DB,AccessControl,DocumentArchive};use App\Modules\Teams\DocumentExtractor;
function check($v,$name){if(!$v)throw new Exception('FAIL '.$name);echo 'PASS '.$name.PHP_EOL;}
$_SESSION['usuario']=AccessControl::user(1);$doc=DB::una('SELECT * FROM archivo_documentos WHERE subido_por=1 ORDER BY id DESC LIMIT 1');$files=DB::todas("SELECT * FROM archivo_adjuntos WHERE documento_id=? AND tipo='original' ORDER BY id",[$doc['id']]);check(count($files)===3,'three image originals available');$doc['nivel']='restringida';
DB::pdo()->beginTransaction();try{
foreach(['ia_proveedor'=>'gemini','ia_gemini_pago'=>'1','ia_gemini_api_key'=>'QA-FAKE-KEY-NO-NETWORK'] as $k=>$v)DB::ejecutar('INSERT INTO ajustes (clave,valor,descripcion) VALUES (?,?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)',[$k,$v,'QA']);
$GLOBALS['doc_test_result']=['campos'=>[['campo'=>'folio','valor'=>'QA-2026','archivo'=>1,'pagina'=>1],['campo'=>'palabras_clave','valor'=>'tutoría, ingeniería','archivo'=>3,'pagina'=>1]],'advertencias'=>'Verificar fecha en el original.'];
$r=DocumentExtractor::extract($doc,$files);check($r['campos'][0]['valor']==='QA-2026','structured extraction returned');$p=$GLOBALS['doc_test_payload'];check($p['generationConfig']['maxOutputTokens']===2000,'separate 2000 output cap');check(count(array_filter($p['contents'][0]['parts'],fn($x)=>isset($x['inlineData'])))===3,'all three image pages supplied');check(str_starts_with($GLOBALS['doc_test_url'],'https://generativelanguage.googleapis.com/'),'fixed provider host');
$GLOBALS['doc_test_result']['campos'][0]['campo']='permisos';try{DocumentExtractor::extract($doc,$files);check(false,'forged permission field');}catch(RuntimeException $e){echo "PASS rejects model permission fields\n";}
$GLOBALS['doc_test_result']['campos'][0]['campo']='folio';$GLOBALS['doc_test_result']['campos'][0]['pagina']=2;try{DocumentExtractor::extract($doc,$files);check(false,'invalid source');}catch(RuntimeException $e){echo "PASS rejects impossible page references\n";}
$doc['nivel']='confidencial';unset($GLOBALS['doc_test_payload']);try{DocumentExtractor::extract($doc,$files);check(false,'confidential');}catch(InvalidArgumentException $e){check(!isset($GLOBALS['doc_test_payload']),'confidential rejected before transport');}
echo "COMPLETE mocked Gemini only; no external calls\n";
}finally{DB::pdo()->rollBack();}
}
