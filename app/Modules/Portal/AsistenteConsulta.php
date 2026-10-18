<?php
declare(strict_types=1);
namespace App\Modules\Portal;
use App\Modules\Busqueda\Buscador;
use App\Core\Audit;

final class AsistenteConsulta
{
    public function resolver(string $consulta): ?array
    {
        $secure=ConsultasSeguras::resolver($consulta);
        if($secure!==null) return $secure;
        // Never use remote embeddings in this portal, even if enabled elsewhere.
        $candidates=(new Buscador())->buscar($consulta,6,true);
        if(!$candidates) return null;
        // Explicit consent per query. No private result rows are passed to Gemini.
        $selected=($_POST['usar_gemini']??'')==='1'?GeminiClient::select($consulta,$candidates):null;
        $candidate=$candidates[$selected??0];
        Audit::record('ia_orientacion',$selected===null?'local':'gemini','Catálogo de funciones oficiales; sin expedientes');
        return ['tipo'=>'formulario','titulo'=>'Canalizar tu solicitud','explicacion'=>'La función oficial más relacionada corresponde a '.$candidate['depto'].'. Revisa el área y el formulario antes de enviar tu solicitud.',
            'depto_id'=>(int)$candidate['depto_id'],'depto'=>$candidate['depto'],'funcion'=>$candidate['descripcion'],
            'coincidencia'=>(int)round($candidate['score']*100),'asunto'=>mb_strimwidth(ucfirst($consulta),0,150,'…'),
            'metodo'=>$selected===null?'local':'gemini'];
    }
}
