<?php
declare(strict_types=1);
namespace App\Modules\Portal;

use App\Core\{Auth,Database,Audit,AccessControl};

/** Authorization is evaluated in SQL, before fetching rows; no model determines identity or scope. */
final class ConsultasSeguras
{
    public static function resolver(string $q): ?array
    {
        if (!Auth::id()) return self::result('Inicia sesión para consultar tu información.',[]);
        $text=mb_strtolower($q);
        if(preg_match('/buscar documentos|buscar archivos|consultar documentos|archivo documental/u',$text)) {
            $terms=trim(preg_replace('/buscar documentos|buscar archivos|consultar documentos|archivo documental|\bsobre\b/ui','',$q));
            return self::result('Busca en el archivo documental. Los filtros y resultados respetan tus permisos; no se envían documentos a Gemini para buscar.',[
                ['titulo'=>'Consultar archivo documental','detalle'=>'Palabras clave, departamento, coordinación, programa, autor y fechas.','ruta'=>'documentos'.($terms!==''?'?q='.rawurlencode($terms):'')]
            ]);
        }
        if(preg_match('/qu[eé] puedes hacer|qu[eé] puedo consultar|herramientas disponibles/u',$text)) {
            return self::result('Puedo orientar trámites, consultar el estado de tus solicitudes y mostrar tus reuniones autorizadas. No modifico registros ni consulto expedientes académicos, laborales o confidenciales.',[
                ['titulo'=>'Mis solicitudes','detalle'=>'Consulta tus solicitudes en la plataforma.','ruta'=>'tickets'],
                ['titulo'=>'Mis reuniones','detalle'=>'Revisa las reuniones a las que tienes acceso.','ruta'=>'reuniones'],
            ]);
        }
        $isMeeting=(bool)preg_match('/reuni[oó]n|reuniones|invitaciones|mi agenda/u',$text);
        $isTicket=(bool)preg_match('/solicitud|solicitudes|ticket|tickets/u',$text);
        $isRead=(bool)preg_match('/\b(mis|estado|estatus|ver|mostrar|muestra|consulta|consultar|pendientes|listar|lista|cu[aá]les|cu[aá]ntas|tengo)\b/u',$text);
        if ((!$isMeeting && !$isTicket) || !$isRead) return null;
        if (!AccessControl::ready() || AccessControl::limited('ia:lectura:'.Auth::id(),30,60)) return self::result('Espera un momento antes de volver a consultar.',[]);
        $kind=$isMeeting?'reuniones':'tickets';$uid=Auth::id();$params=[];
        // Explicit integer lookup is always constrained by the same ownership predicates.
        preg_match('/(?:#|\b(?:solicitud|ticket|reuni[oó]n)\s*(?:n[uú]mero\s*)?)(\d{1,10})/u',$text,$match);
        $id=isset($match[1])?(int)$match[1]:0;
        if ($kind==='tickets') {
            $scope='t.usuario_id = ?';$params=[$uid];
            if(preg_match('/mi [aá]rea|mi departamento|recibidas/u',$text)) {
                if(!Auth::tieneRol('titular','operativo') || !Auth::departamentoId()) {
                    Audit::record('ia_consulta','denegado','tickets: ámbito de área no autorizado');
                    return self::result('Esta consulta de área no está habilitada para tu cuenta. Puedes consultar tus propias solicitudes.',[]);
                }
                $scope='t.depto_destino_id = ?';$params=[Auth::departamentoId()];
            }
            $sql='SELECT t.id,t.estado,t.actualizado_en FROM tickets t WHERE ('.$scope.')';
        } else {
            $params=[$uid,$uid,$uid];
            $sql="SELECT t.id,t.estado,t.fecha_confirmada,t.hora_confirmada_inicio,t.actualizado_en FROM reuniones t WHERE (t.creador_id=? OR t.organizador_id=? OR EXISTS (SELECT 1 FROM reuniones_participantes p WHERE p.reunion_id=t.id AND p.usuario_id=? AND p.estado_invitacion<>'revocada'))";
        }
        // Personal records default restricted. Confidential or malformed classification is denied to everyone, including admin.
        $sql.=" AND NOT EXISTS (SELECT 1 FROM ajustes a WHERE a.clave=CONCAT('ia_nivel_".$kind."_',t.id) AND a.valor<>'restringida')";
        if($id){$sql.=' AND t.id=?';$params[]=$id;}
        $sql.=' ORDER BY t.actualizado_en DESC,t.id DESC LIMIT 10';
        $rows=Database::todas($sql,$params);$items=[];
        foreach($rows as $row){
            // Intentionally exclude subject, descriptions, attachments, messages, names and emails.
            $detail='Estado: '.str_replace('_',' ',$row['estado']);
            if($kind==='reuniones' && $row['fecha_confirmada']) $detail.=' · '.$row['fecha_confirmada'].' '.($row['hora_confirmada_inicio']??'');
            $items[]=['titulo'=>($kind==='tickets'?'Solicitud':'Reunión').' #'.$row['id'],'detalle'=>$detail,'ruta'=>$kind.'/'.$row['id']];
        }
        Audit::record('ia_consulta','filtrada',$kind.'; resultados: '.count($items));
        return self::result($items?'Estos son hasta 10 registros recientes dentro de tu acceso. Abre la fuente para ver el detalle.':'No hay resultados disponibles dentro de tu acceso. No se confirma la existencia de registros ajenos o confidenciales.',$items);
    }
    private static function result(string $message,array $items): array
    {
        return ['tipo'=>'datos','titulo'=>'Consulta segura','explicacion'=>$message,'items'=>$items,'metodo'=>'local'];
    }
}
