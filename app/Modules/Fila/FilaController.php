<?php
declare(strict_types=1);
namespace App\Modules\Fila;
use App\Core\{Auth, AccessControl, Controller};

final class FilaController extends Controller
{
    public function directa(string $clave): void
    {
        FilaGateway::load();
        if (AccessControl::pending()) AccessControl::deny();
        $dep=FilaAccess::oficina($clave);
        if (!$dep) { http_response_code(403); exit('No puedes atender esta oficina.'); }
        try {
            self::iniciarDirecta($dep);
            flash('exito','El kiosco ya muestra que hay una atención en curso.');
        } catch (\DomainException $e) {
            flash('error',$e->getMessage());
        }
        redirigir('fila?vista=atender');
    }

    public static function iniciarDirecta(array $dep, ?\DateTimeImmutable $ahora=null): array
    {
        $ahora=$ahora??new \DateTimeImmutable('now');
        $estado=fila_estado($dep,$ahora);
        if (!empty($estado['pausa_manual'])) throw new \DomainException('Primero vuelve a marcar la oficina como disponible.');
        if (!$estado['abierta']) throw new \DomainException('La atención directa no está disponible en este horario.');
        if ($estado['actual'] || $estado['espera']) throw new \DomainException('La fila ya cambió. Actualiza el panel antes de iniciar otra atención.');

        db()->beginTransaction();
        try {
            $mysql=db()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql';
            if ($mysql) consulta('SELECT id FROM dependencias WHERE id=? FOR UPDATE',[$dep['id']]);
            $activos=filas('SELECT * FROM turnos WHERE dependencia_id=? AND fecha=? AND estado IN (?,?) ORDER BY folio'.($mysql?' FOR UPDATE':''),
                [$dep['id'],$ahora->format('Y-m-d'),'llamado','espera']);
            if ($activos) throw new \DomainException('La fila ya cambió. Actualiza el panel antes de iniciar otra atención.');
            $folio=(int)(fila_una('SELECT COALESCE(MAX(folio),0)+1 AS siguiente FROM turnos WHERE dependencia_id=? AND fecha=?',
                [$dep['id'],$ahora->format('Y-m-d')])['siguiente']??1);
            $token=u_token();
            $momento=$ahora->format('Y-m-d H:i:s');
            consulta('INSERT INTO turnos (dependencia_id,fecha,folio,nombre,nombre_publico,email,asunto,minutos,estado,token,creado,llamado,videoconferencia,enlace_teams) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [$dep['id'],$ahora->format('Y-m-d'),$folio,'Visita sin turno','','atencion-directa@fila.local',
                 'Atención directa',(int)$dep['duracion_max'],'llamado',$token,$momento,$momento,0,'']);
            $id=(int)db()->lastInsertId();
            consulta('UPDATE dependencias SET atendiendo_turno=? WHERE id=?',[$id,$dep['id']]);
            evento((int)$dep['id'],'atencion-directa-iniciada','Usuario '.Auth::id());
            db()->commit();
            return fila_una('SELECT * FROM turnos WHERE id=?',[$id]);
        } catch (\Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
    }

    /** Genera un token nuevo para la laptop/monitor; solo se devuelve esta vez (en BD queda el hash). */
    public function token(string $clave): void
    {
        FilaGateway::load();
        if (AccessControl::pending()) AccessControl::deny();
        $dep=FilaAccess::oficina($clave);
        header('Content-Type: application/json; charset=utf-8');
        if (!$dep) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'No puedes administrar esta oficina.']); return; }
        $token=u_token(20);
        consulta('UPDATE dependencias SET token_hash=? WHERE id=?',[hash('sha256',$token),$dep['id']]);
        evento((int)$dep['id'],'token','regenerado · Usuario '.Auth::id());
        echo json_encode(['ok'=>true,'token'=>$token]);
    }

    public function disponibilidad(string $clave): void
    {
        FilaGateway::load();
        if (AccessControl::pending()) AccessControl::deny();
        $dep=FilaAccess::oficina($clave);
        if (!$dep) { http_response_code(403); exit('No puedes atender esta oficina.'); }
        $disponible=($_POST['disponible']??'0')==='1';
        try {
            self::cambiarDisponibilidad($dep,$disponible);
            flash('exito',$disponible?'El kiosco vuelve a mostrar la disponibilidad normal.':'El kiosco muestra “No disponible”.');
        } catch (\DomainException $e) {
            flash('error',$e->getMessage());
        }
        redirigir('fila?vista=atender');
    }

    public static function cambiarDisponibilidad(array $dep, bool $disponible, ?\DateTimeImmutable $ahora=null): bool
    {
        $ahora=$ahora??new \DateTimeImmutable('now');
        $momento=$ahora->format('Y-m-d H:i:s');
        if ($disponible) {
            consulta('DELETE FROM bloqueos WHERE dependencia_id=? AND origen=? AND inicio<=? AND fin>?',
                [$dep['id'],'pausa',$momento,$momento]);
            evento((int)$dep['id'],'disponibilidad','Usuario '.Auth::id().' · disponible');
            return false;
        }
        $estado=fila_estado($dep,$ahora);
        $cierre=$estado['cierre']??null;
        if (!$cierre || $cierre<=$ahora) throw new \DomainException('No hay un intervalo de atención activo o pendiente para marcar como no disponible.');
        if (!empty($estado['pausa_manual'])) return true;
        $finPausa=$cierre;
        foreach ($estado['ventanas'] as $ventana) {
            if ($ventana['inicio']<=$ahora && $ventana['fin']>$ahora) { $finPausa=$ventana['fin']; break; }
            if ($ventana['inicio']>$ahora) { $finPausa=$ventana['fin']; break; }
        }
        consulta('INSERT INTO bloqueos (dependencia_id,inicio,fin,motivo,origen) VALUES (?,?,?,?,?)',
            [$dep['id'],$momento,$finPausa->format('Y-m-d H:i:s'),'No disponible','pausa']);
        evento((int)$dep['id'],'disponibilidad','Usuario '.Auth::id().' · no disponible');
        return true;
    }

    public function siguiente(string $clave): void
    {
        FilaGateway::load();
        if (AccessControl::pending()) AccessControl::deny();
        $dep=FilaAccess::oficina($clave);
        if (!$dep) { http_response_code(403); exit('No puedes atender esta oficina.'); }
        try {
            $turno=self::avanzar($dep,(int)($_POST['actual_id']??-1),(int)($_POST['siguiente_id']??-1));
            flash('exito',$turno ? 'Ahora atiendes a '.persona_referencia_interna($dep,$turno).'.' : 'Atención finalizada. No hay más personas en espera.');
        } catch (\DomainException $e) {
            flash('error',$e->getMessage());
        }
        redirigir('fila?vista=atender');
    }

    /** La instantánea evita avanzar dos turnos por doble clic o una pestaña antigua. */
    public static function avanzar(array $dep, int $actualId, int $siguienteId): ?array
    {
        db()->beginTransaction();
        try {
            $mysql=db()->getAttribute(\PDO::ATTR_DRIVER_NAME)==='mysql';
            if ($mysql) consulta('SELECT id FROM dependencias WHERE id=? FOR UPDATE',[$dep['id']]);
            $activos=filas('SELECT * FROM turnos WHERE dependencia_id=? AND fecha=? AND estado IN (?,?) ORDER BY folio'.($mysql?' FOR UPDATE':''),
                [$dep['id'],date('Y-m-d'),'llamado','espera']);
            $actual=null; $siguiente=null;
            foreach ($activos as $t) {
                if ($t['estado']==='llamado') {
                    if ($actual) throw new \DomainException('Hay más de un turno llamado; revisa el estado de la oficina antes de avanzar.');
                    $actual=$t;
                } elseif (!$siguiente) $siguiente=$t;
            }
            if ($actualId!==(int)($actual['id']??0) || $siguienteId!==(int)($siguiente['id']??0)) {
                throw new \DomainException('La fila ya cambió. Revisa el turno actual antes de volver a pulsar “Pasar al siguiente”.');
            }
            if ($actual) consulta('UPDATE turnos SET estado=?,cerrado=? WHERE id=? AND dependencia_id=?',
                ['atendido',ahora_texto(),$actual['id'],$dep['id']]);
            if ($siguiente) consulta('UPDATE turnos SET estado=?,llamado=? WHERE id=? AND dependencia_id=?',
                ['llamado',ahora_texto(),$siguiente['id'],$dep['id']]);
            consulta('UPDATE dependencias SET atendiendo_turno=? WHERE id=?',[$siguiente['id']??null,$dep['id']]);
            evento((int)$dep['id'],'pasar-siguiente','Usuario '.Auth::id().' · actual '.($actual['id']??0).' · siguiente '.($siguiente['id']??0));
            db()->commit();
            return $siguiente ? fila_una('SELECT * FROM turnos WHERE id=? AND dependencia_id=?',[$siguiente['id'],$dep['id']]) : null;
        } catch (\Throwable $e) { if (db()->inTransaction()) db()->rollBack(); throw $e; }
    }

    public function index(): void
    {
        FilaGateway::load();
        $turnos = [];
        $pendiente = AccessControl::pending();
        $oficinasGestion = FilaAccess::oficinas();
        $filtros=['departamento'=>(string)($_GET['departamento']??''),'orden'=>(string)($_GET['orden']??'recientes')];
        $errorFiltros=''; $departamentosFiltro=[];
        $vista = ($_GET['vista'] ?? '') === 'otros' ? 'otros'
            : (($_GET['vista'] ?? '') === 'atender' ? 'atender' : ($oficinasGestion ? 'atender' : 'otros'));
        if (!$pendiente) {
            $email=u_email(Auth::usuario()['email']);
            $departamentosFiltro=filas('SELECT DISTINCT d.id,d.nombre FROM dependencias d JOIN turnos t ON t.dependencia_id=d.id WHERE LOWER(t.email)=? ORDER BY d.nombre',[$email]);
            try { $turnos=self::turnosPersonales($email,$vista==='otros'?$filtros:['desde'=>date('Y-m-d'),'hasta'=>date('Y-m-d')]); }
            catch (\InvalidArgumentException $e) { $errorFiltros=$e->getMessage(); }
            $estados = [];
            foreach ($turnos as &$turno) {
                $id = (int)$turno['dependencia_id'];
                $turno['adelante'] = 0;
                $turno['estimado'] = null;
                if ($turno['fecha']!==date('Y-m-d')) continue;
                $estado = $estados[$id] ??= fila_estado(dependencia_id($id));
                foreach ($estado['turnos'] as $t) {
                    if ((int)$t['id'] === (int)$turno['id']) { $turno['estimado']=$t['estimado']; break; }
                    if ($t['estado'] === 'espera') $turno['adelante']++;
                }
            }
            unset($turno);
        }
        $this->vista('index', ['titulo'=>'Fila', 'turnos'=>$turnos, 'pendiente'=>$pendiente,
            'oficinasGestion'=>$oficinasGestion, 'pestana'=>$vista,
            'filtros'=>$filtros,'errorFiltros'=>$errorFiltros,'departamentosFiltro'=>$departamentosFiltro,
            ...self::paneles($oficinasGestion, $vista, $pendiente)]);
    }

    public static function turnosPersonales(string $email, array $filtros): array
    {
        $where='LOWER(t.email)=?'; $params=[u_email($email)];
        foreach (['desde'=>'>=','hasta'=>'<='] as $campo=>$operador) {
            $fecha=(string)($filtros[$campo]??'');
            if ($fecha==='') continue;
            $dia=\DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
            if (!$dia || $dia->format('Y-m-d')!==$fecha) throw new \InvalidArgumentException('Elige fechas válidas para el filtro.');
            $where.=' AND t.fecha'.$operador.'?'; $params[]=$fecha;
        }
        if (!empty($filtros['desde']) && !empty($filtros['hasta']) && $filtros['desde']>$filtros['hasta']) throw new \InvalidArgumentException('La fecha inicial debe ser anterior o igual a la final.');
        $dep=(string)($filtros['departamento']??'');
        if ($dep!=='') {
            if (!ctype_digit($dep) || (int)$dep<1) throw new \InvalidArgumentException('Selecciona un departamento válido.');
            $where.=' AND t.dependencia_id=?'; $params[]=(int)$dep;
        }
        $orden=['recientes'=>'t.fecha DESC,t.creado DESC,t.id DESC','antiguos'=>'t.fecha ASC,t.creado ASC,t.id ASC',
            'departamento'=>'d.nombre ASC,t.fecha DESC,t.creado DESC,t.id DESC'][(string)($filtros['orden']??'recientes')]??'t.fecha DESC,t.creado DESC,t.id DESC';
        return filas('SELECT t.*, d.nombre AS oficina,d.clave AS oficina_clave, '
            .'(SELECT COUNT(*) FROM fila_expedientes e WHERE e.turno_id=t.id AND e.dependencia_id=t.dependencia_id) AS cantidad_minutas '
            .'FROM turnos t JOIN dependencias d ON d.id=t.dependencia_id WHERE '.$where.' ORDER BY '.$orden.' LIMIT 200',$params);
    }

    public static function paneles(array $oficinasGestion, string $vista, bool $pendiente): array
    {
        $atencion = [];
        $otras = [];
        if ($vista === 'atender' && !$pendiente) {
            foreach ($oficinasGestion as $dep) $atencion[] = ['oficina'=>$dep, 'estado'=>fila_estado($dep)];
        } elseif ($vista === 'otros') {
            $propias = array_column($oficinasGestion, 'id');
            foreach (dependencias() as $dep) {
                if (!(int)$dep['activa'] || in_array($dep['id'], $propias)) continue;
                $counts = fila_una('SELECT SUM(estado=?) AS espera, SUM(estado=?) AS atendiendo '
                    .'FROM turnos WHERE dependencia_id=? AND fecha=?', ['espera','llamado',$dep['id'],date('Y-m-d')]);
                $otras[] = ['oficina'=>$dep, 'espera'=>(int)($counts['espera']??0),
                    'atendiendo'=>(int)($counts['atendiendo']??0)];
            }
        }
        return ['oficinasAtencion'=>$atencion, 'otrasOficinas'=>$otras];
    }
}
