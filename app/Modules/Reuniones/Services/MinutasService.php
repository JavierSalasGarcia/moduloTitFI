<?php
declare(strict_types=1);
namespace App\Modules\Reuniones\Services;
use App\Core\{Database,Auth};
use App\Modules\Fila\FilaGateway;
final class MinutasService
{
    public static function correos(string $texto): array
    {
        $result=[];
        foreach (preg_split('/[\s,;]+/',trim($texto),-1,PREG_SPLIT_NO_EMPTY) as $correo) {
            $correo=strtolower(trim($correo));
            if (strlen($correo)>160 || !filter_var($correo,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Correo inválido: '.$correo);
            $result[$correo]=$correo;
        }
        if (count($result)>50) throw new \InvalidArgumentException('El límite es 50 asistentes por minuta.');
        return array_values($result);
    }
    public static function reunion(int $id,int $usuarioId,string $email): ?array
    {
        return Database::una("SELECT r.* FROM reuniones r WHERE r.id=? AND (r.organizador_id=? OR EXISTS (SELECT 1 FROM reuniones_participantes p WHERE p.reunion_id=r.id AND p.estado_invitacion<>'revocada' AND (p.usuario_id=? OR LOWER(p.correo)=?)))",[$id,$usuarioId,$usuarioId,strtolower($email)]);
    }
    public static function listar(int $usuarioId,string $email): array
    {
        return Database::todas("SELECT m.*,u.nombre AS autor FROM reuniones_minutas m LEFT JOIN usuarios u ON u.id=m.autor_id WHERE m.autor_id=? OR EXISTS (SELECT 1 FROM reuniones_minuta_presentes p WHERE p.minuta_id=m.id AND p.email=?) OR (m.reunion_id IS NOT NULL AND EXISTS (SELECT 1 FROM reuniones_participantes p WHERE p.reunion_id=m.reunion_id AND p.estado_invitacion<>'revocada' AND (p.usuario_id=? OR LOWER(p.correo)=?))) ORDER BY m.fecha DESC,m.id DESC LIMIT 200",[$usuarioId,strtolower($email),$usuarioId,strtolower($email)]);
    }
    public static function permitido(int $id,int $usuarioId,string $email): ?array
    {
        foreach (self::listar($usuarioId,$email) as $m) if ((int)$m['id']===$id) return self::contenido($m);
        return null;
    }
    public static function contenido(array $m): array
    {
        if (!empty($m['fila_registro_id'])) {
            FilaGateway::load();
            $r=\fila_una('SELECT resumen,archivo,nombre_archivo FROM fila_expedientes WHERE id=?',[$m['fila_registro_id']]);
            if (!$r) return [...$m,'resumen'=>'La minuta ya no está disponible.','archivo'=>''];
            $m=[...$m,...$r];
        }
        return $m;
    }
    public static function vincularFila(array $dep,int $id,array $correos): int
    {
        $r=\fila_una('SELECT e.*,t.asunto,t.fecha,t.email FROM fila_expedientes e JOIN turnos t ON t.id=e.turno_id AND t.dependencia_id=e.dependencia_id WHERE e.id=? AND e.dependencia_id=?',[$id,$dep['id']]);
        if (!$r) throw new \InvalidArgumentException('Minuta no encontrada.');
        // Solo índice y destinatarios en Traza FI; resumen y archivo permanecen en Fila.
        $pdo=Database::pdo(); $pdo->beginTransaction();
        try {
            $m=Database::una('SELECT id FROM reuniones_minutas WHERE fila_registro_id=?',[$id]);
            if (!$m) {
                Database::ejecutar('INSERT INTO reuniones_minutas (autor_id,asunto,fecha,calidad,convocante,fila_registro_id) VALUES (?,?,?,?,?,?)',[(int)$r['usuario_id'],$r['asunto'],$r['fecha'].' 00:00:00','oficina',$dep['nombre'],$id]);
                $minutaId=(int)$pdo->lastInsertId();
            } else $minutaId=(int)$m['id'];
            $autor=Database::una('SELECT email FROM usuarios WHERE id=?',[(int)$r['usuario_id']]);
            $otros=array_column(\filas('SELECT email FROM fila_minuta_presentes WHERE registro_id=?',[$id]),'email');
            self::presentes($minutaId,self::correos(implode(';',[...$correos,...$otros,$r['email'],$autor['email']??''])));
            $pdo->commit(); return $minutaId;
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
    public static function presentes(int $id,array $correos): void
    {
        foreach ($correos as $correo) Database::ejecutar('INSERT IGNORE INTO reuniones_minuta_presentes (minuta_id,email) VALUES (?,?)',[$id,$correo]);
    }
    public static function notificar(int $id,?MailerInterface $mailer=null): array
    {
        $m=Database::una('SELECT m.*,u.nombre AS autor FROM reuniones_minutas m JOIN usuarios u ON u.id=m.autor_id WHERE m.id=?',[$id]);
        if (!$m) throw new \RuntimeException('Minuta no encontrada.');
        $mailer??=new HostingerMailer(); $ok=0; $total=0;
        foreach (Database::todas('SELECT email FROM reuniones_minuta_presentes WHERE minuta_id=?',[$id]) as $p) {
            $total++; $token=bin2hex(random_bytes(32));
            Database::ejecutar('INSERT INTO reuniones_historial_enlaces (autor_id,email,token_hash) VALUES (?,?,?)',[$m['autor_id'],$p['email'],hash('sha256',$token)]);
            $enlace=rtrim((string)App::config('app.url_publica', 'https://fingenieria.mx'), '/')
                .'/reuniones/historial/'.$token;
            $texto='La minuta de “'.$m['asunto'].'” está disponible.' ."\n\n".'Fecha: '.$m['fecha']."\n".'Compartida por: '.($m['subido_por_email']?:$m['autor'])."\n\n".'Consulta esta minuta y tu historial de reuniones compartidas contigo:'."\n".$enlace."\n\n".'No necesitas una cuenta en Traza FI. El enlace es privado y no debe compartirse.';
            try { $enviado=$mailer->enviar($p['email'],'Minuta · '.$m['asunto'],$texto); } catch (\Throwable $e) { $enviado=false; }
            if ($enviado) { $ok++; Database::ejecutar('UPDATE reuniones_minuta_presentes SET enviado=NOW() WHERE minuta_id=? AND email=?',[$id,$p['email']]); }
        }
        return ['enviados'=>$ok,'total'=>$total];
    }
    public static function enlace(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D',$token)) return null;
        return Database::una('SELECT autor_id,email FROM reuniones_historial_enlaces WHERE token_hash=?',[hash('sha256',$token)]);
    }
    public static function historial(array $enlace): array
    {
        return Database::todas('SELECT m.*,u.nombre AS autor FROM reuniones_minutas m JOIN reuniones_minuta_presentes p ON p.minuta_id=m.id JOIN usuarios u ON u.id=m.autor_id WHERE p.email=? ORDER BY m.fecha DESC,m.id DESC LIMIT 200',[$enlace['email']]);
    }
}
