<?php
declare(strict_types=1);
namespace App\Modules\Fila;
use App\Core\{Controller, Audit};

final class HorariosController extends Controller
{
    private function asegurarEsquema(): void
    {
        asegurar_esquema_horarios();
    }
    private function oficina(string $clave): array
    {
        FilaGateway::load();
        $dep=FilaAccess::oficina($clave);
        if (!$dep) { http_response_code(403); exit('No tienes permiso para configurar esta oficina.'); }
        return $dep;
    }
    public function index(string $clave): void
    {
        $dep=$this->oficina($clave);
        $this->asegurarEsquema();
        $hoy=(new \DateTimeImmutable('today'))->format('Y-m-d');
        $this->vista('horarios',['titulo'=>'Horarios · '.$dep['nombre'], 'dep'=>$dep,
            'horarios'=>horarios_configurados((int)$dep['id']),
            'inhabiles'=>excepciones((int)$dep['id'],$hoy,'9999-12-31'), 'error'=>'']);
    }
    public static function validar(array $rows): array
    {
        if (count($rows)>50) throw new \InvalidArgumentException('Demasiados intervalos.');
        $valid=[];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new \InvalidArgumentException('Formato de horario no válido.');
            if (empty($row['activo'])) continue;
            $day=(string)($row['dia']??''); $start=$row['inicio']??''; $end=$row['fin']??'';
            if ($start==='' && $end==='') continue;
            if (is_string($start) && preg_match('/^\d{2}:\d{2}:00$/',$start)) $start=substr($start,0,5);
            if (is_string($end) && preg_match('/^\d{2}:\d{2}:00$/',$end)) $end=substr($end,0,5);
            if (!preg_match('/^[1-7]$/',$day) || !is_string($start) || !is_string($end)
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$start)
                || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/',$end) || $start >= $end) {
                throw new \InvalidArgumentException('Elige un día y horas válidas; el fin debe ser posterior al inicio.');
            }
            $valid[]=['dia'=>(int)$day,'inicio'=>$start,'fin'=>$end,
                'activo'=>empty($row['inhabil'])?1:0];
        }
        usort($valid,fn($a,$b)=>[$a['dia'],$a['inicio']]<=>[$b['dia'],$b['inicio']]);
        $diasInhabiles=[];
        foreach ($valid as $row) if (empty($row['activo'])) $diasInhabiles[$row['dia']]=true;
        foreach ($valid as &$row) if (isset($diasInhabiles[$row['dia']])) $row['activo']=0;
        unset($row);
        foreach ($valid as $i=>$row) {
            if ($i && $valid[$i-1]['dia']===$row['dia'] && $valid[$i-1]['fin']>$row['inicio']) {
                throw new \InvalidArgumentException('Los intervalos del mismo día no deben superponerse.');
            }
        }
        return $valid;
    }
    public static function validarInhabiles(array $rows, ?string $hoy=null): array
    {
        if (count($rows)>100) throw new \InvalidArgumentException('Demasiados días inhábiles.');
        $hoy=$hoy??(new \DateTimeImmutable('today'))->format('Y-m-d');
        $valid=[];
        foreach ($rows as $row) {
            if (!is_array($row)) throw new \InvalidArgumentException('Formato de día inhábil no válido.');
            if (empty($row['activo'])) continue;
            $fecha=trim((string)($row['fecha']??''));
            $motivo=trim((string)($row['motivo']??''));
            $dia=\DateTimeImmutable::createFromFormat('!Y-m-d',$fecha);
            $errores=\DateTimeImmutable::getLastErrors();
            if ($fecha==='' || !$dia || ($errores!==false && ($errores['warning_count'] || $errores['error_count']))
                || $dia->format('Y-m-d')!==$fecha || $fecha<$hoy) {
                throw new \InvalidArgumentException('Elige fechas válidas de hoy en adelante para los días inhábiles.');
            }
            $longitud=function_exists('mb_strlen')?\mb_strlen($motivo):strlen($motivo);
            if ($longitud>160) throw new \InvalidArgumentException('El motivo del día inhábil debe tener hasta 160 caracteres.');
            if (isset($valid[$fecha])) throw new \InvalidArgumentException('No repitas la misma fecha inhábil.');
            $valid[$fecha]=['fecha'=>$fecha,'tipo'=>'cerrado','inicio'=>null,'fin'=>null,
                'motivo'=>$motivo?:'Día inhábil'];
        }
        ksort($valid);
        return array_values($valid);
    }
    public function guardar(string $clave): void
    {
        $dep=$this->oficina($clave);
        $this->asegurarEsquema();
        try {
            if (!is_array($_POST['horarios']??[])) throw new \InvalidArgumentException('Formato no válido.');
            $rows=self::validar($_POST['horarios']??[]);
            if (!is_array($_POST['inhabiles']??[])) throw new \InvalidArgumentException('Formato de días inhábiles no válido.');
            $inhabiles=self::validarInhabiles($_POST['inhabiles']??[]);
        } catch (\InvalidArgumentException $error) {
            http_response_code(422);
            $this->vista('horarios',['titulo'=>'Horarios · '.$dep['nombre'], 'dep'=>$dep,
                'horarios'=>is_array($_POST['horarios']??null) ? array_values(array_filter($_POST['horarios'],'is_array')) : horarios_configurados((int)$dep['id']),
                'inhabiles'=>is_array($_POST['inhabiles']??null) ? array_values(array_filter($_POST['inhabiles'],'is_array')) : [],
                'error'=>$error->getMessage()]);
            return;
        }
        db()->beginTransaction();
        try {
            consulta('SELECT id FROM dependencias WHERE id=? FOR UPDATE',[$dep['id']]);
            consulta('DELETE FROM horarios WHERE dependencia_id=?',[$dep['id']]);
            foreach ($rows as $row) consulta('INSERT INTO horarios (dependencia_id,dia,inicio,fin,activo) VALUES (?,?,?,?,?)',
                [$dep['id'],$row['dia'],$row['inicio'],$row['fin'],$row['activo']]);
            $hoy=(new \DateTimeImmutable('today'))->format('Y-m-d');
            consulta("DELETE FROM excepciones WHERE dependencia_id=? AND fecha>=? AND tipo='cerrado' AND inicio IS NULL",
                [$dep['id'],$hoy]);
            foreach ($inhabiles as $row) consulta('INSERT INTO excepciones (dependencia_id,fecha,tipo,inicio,fin,motivo) VALUES (?,?,?,?,?,?)',
                [$dep['id'],$row['fecha'],$row['tipo'],$row['inicio'],$row['fin'],$row['motivo']]);
            evento((int)$dep['id'],'horarios','Configurados por usuario '.\App\Core\Auth::id());
            db()->commit();
        } catch (\Throwable $error) { db()->rollBack(); throw $error; }
        resincronizar_jornadas($dep);
        Audit::record('fila::horarios','correcto',$clave);
        flash('exito','Horarios y días inhábiles guardados. Las jornadas programadas de hoy en adelante se ajustaron; esto no abre la atención ni modifica citas existentes.');
        redirigir('fila/horarios/'.$clave);
    }
}
