<?php
declare(strict_types=1);
namespace App\Modules\Portal;

use App\Core\{Auth,AccessControl,Audit,Database,SecretosIa};

/** A bounded classifier, not an SQL agent. No documents or result rows leave the server. */
final class GeminiClient
{
    public const MODEL = 'gemini-2.5-flash-lite';
    /** Resultado de la reserva de cuota de esta petición: una consulta consume una sola reserva aunque use Jev y Gemini. */
    private static ?bool $reserva = null;

    /** Modelo configurable en Ajustes (ia_gemini_modelo); si el valor no tiene forma de modelo de Gemini se usa el predeterminado. */
    public static function modelo(): string
    {
        $m = ajuste('ia_gemini_modelo', self::MODEL);
        return preg_match('/^gemini-[a-z0-9.\-]{1,40}$/D', $m) ? $m : self::MODEL;
    }
    public static function disponible(): bool
    {
        return ajuste('ia_proveedor','local')==='gemini' && ajuste('ia_gemini_pago','0')==='1' && SecretosIa::clave('gemini')!=='';
    }
    /** Límite diario de consultas asistidas por persona, definido por el administrador (Ajustes → Asistente). */
    public static function limiteUsuario(): int
    {
        return max(1,min(1000,(int)ajuste('ia_limite_usuario','20')));
    }
    /** Consultas asistidas que le quedan hoy a la persona con sesión (día de cuota en UTC). */
    public static function restantes(): int
    {
        if (!Auth::id() || !AccessControl::ready()) return 0;
        $usadas=(int)Database::valor('SELECT cantidad FROM acceso_limites WHERE clave=? AND ventana=?',[hash('sha256','ia:usuario:'.Auth::id()),(int)floor(time()/86400)]);
        return max(0,self::limiteUsuario()-$usadas);
    }
    /** Reserva de cuota (por minuto, por persona y global). Se cuenta una vez por petición HTTP. */
    public static function quota(): bool
    {
        if (self::$reserva!==null) return self::$reserva;
        if (!Auth::id() || !AccessControl::ready()) return self::$reserva=false;
        $daily=max(1,min(10000,(int)ajuste('ia_limite_diario','100')));
        if (AccessControl::limited('ia:minuto:'.Auth::id(),5,60)
            || AccessControl::limited('ia:usuario:'.Auth::id(),self::limiteUsuario(),86400)
            || AccessControl::limited('ia:global',$daily,86400)) {
            Audit::record('ia_cuota','local','Límite de llamadas alcanzado'); return self::$reserva=false;
        }
        return self::$reserva=true;
    }
    /**
     * @param string $prompt   Datos de la petición (lo no confiable va aquí, como JSON).
     * @param string $sistema  Instrucción fija del servidor (Prompts::*); viaja separada en systemInstruction.
     */
    public static function request(string $key,string $prompt,array $schema,string $sistema=''): ?array
    {
        if ($key==='' || strlen($key)>512 || preg_match('/[\r\n]/',$key) || strlen($prompt)>12000 || !function_exists('curl_init')) return null;
        // Fixed host, no redirects, no tools, no conversation history, one attempt.
        $payload=['contents'=>[['role'=>'user','parts'=>[['text'=>$prompt]]]],'generationConfig'=>[
            'temperature'=>0,'maxOutputTokens'=>256,'thinkingConfig'=>['thinkingBudget'=>0],
            'responseMimeType'=>'application/json','responseSchema'=>$schema]];
        if ($sistema!=='') $payload['systemInstruction']=['parts'=>[['text'=>$sistema]]];
        $ch=curl_init('https://generativelanguage.googleapis.com/v1beta/models/'.self::modelo().':generateContent');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>20,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json','x-goog-api-key: '.$key],
            CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)]);
        $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
        $body=is_string($raw)?json_decode($raw,true):null;
        Audit::record('ia_gemini',$status===200?'respuesta':'error','HTTP '.$status.'; tokens: '.(int)($body['usageMetadata']['totalTokenCount']??0));
        if($status!==200 || ($body['candidates'][0]['finishReason']??'')!=='STOP') return null;
        $text=$body['candidates'][0]['content']['parts'][0]['text']??'';
        $data=is_string($text)?json_decode($text,true):null;
        return is_array($data)?$data:null;
    }
    /** Elige, entre funciones oficiales que el servidor ya seleccionó, la más pertinente. Solo devuelve un índice validado. */
    public static function select(string $query,array $candidates): ?int
    {
        if (!self::disponible()) return null;
        $options=[];foreach($candidates as $i=>$c) $options[]=['indice'=>$i,'area'=>mb_substr($c['depto'],0,120),'funcion'=>mb_substr($c['descripcion'],0,450)];
        $prompt=(string)json_encode(['pregunta'=>mb_substr($query,0,1200),'opciones'=>$options],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
        if(!self::quota()) return null;
        $data=self::request(SecretosIa::clave('gemini'),$prompt,['type'=>'OBJECT','properties'=>['indice'=>['type'=>'INTEGER']],'required'=>['indice']],Prompts::GEMINI_SISTEMA);
        return isset($data['indice']) && is_int($data['indice']) && isset($candidates[$data['indice']])?$data['indice']:null;
    }
}
