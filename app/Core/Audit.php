<?php
declare(strict_types=1);
namespace App\Core;

final class Audit
{
    private static array $queue=[];
    private static bool $hook=false;
    public static function record(string $action,string $result='correcto',string $detail=''): void
    {
        if (!AccessControl::ready()) return;
        $user=Auth::usuario()??[]; $actor=$_SESSION['impersonador']??$user;
        self::$queue[]=[ $actor['id']??null,$actor['email']??'', $user['id']??null,$user['email']??'',mb_substr($action,0,180),$result,mb_substr($detail,0,500),date('Y-m-d H:i:s') ];
        if (!self::$hook) { self::$hook=true; register_shutdown_function([self::class,'flush']); }
    }
    public static function sql(string $sql,int $rows): void
    {
        if (AccessControl::isInternal()) return;
        if (preg_match('/^\s*(INSERT(?:\s+IGNORE)?\s+INTO|REPLACE\s+INTO|UPDATE|DELETE\s+FROM)\s+`?([a-z_0-9]+)/i',$sql,$m)) {
            self::record('BD: '.strtoupper($m[1]).' '.$m[2],'ejecutado','Filas afectadas: '.$rows);
        }
    }
    public static function flush(): void
    {
        $queue=self::$queue; self::$queue=[];
        try { AccessControl::internal(function() use($queue): void {
            $aborted=Database::pdo()->inTransaction();
            if ($aborted) Database::pdo()->rollBack();
            $stmt=Database::pdo()->prepare('INSERT INTO auditoria_acciones (actor_id,actor_email,usuario_id,usuario_email,accion,resultado,detalle,creado_en) VALUES (?,?,?,?,?,?,?,?)');
            foreach($queue as $entry) {
                if ($entry[5]==='solicitado') { $status=http_response_code()?:200; $entry[5]=$status>=400?'error':'atendido'; $entry[6]='HTTP '.$status; }
                if ($aborted && $entry[5]==='ejecutado') $entry[5]='revertido';
                $stmt->execute($entry);
            }
        }); } catch (\Throwable $e) { error_log('No se pudo persistir la auditoría de acceso.'); }
    }
}
