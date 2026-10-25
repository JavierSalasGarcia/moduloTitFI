<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Modules\Espacios\Agenda;
use App\Modules\Titulacion\TitulacionRepository;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * El pasante elige fecha y hora de su examen (previo o definitivo) y propone su sínodo; el auditorio se aparta en
 * el módulo Auditorios/Salas y la solicitud llega al Coordinador de Docencia, que ratifica o modifica el sínodo.
 */
final class AgendaExamen
{
    /** @return array{dias:int,inicio:string,fin:string,duracion:int,espacio:int,propietarios:int,suplentes:int} */
    public static function config(): array
    {
        $hora = static fn(string $v, string $def): string => preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $v) ? $v : $def;
        return [
            'dias' => max(0, (int) \ajuste('titulacion_examen_dias_min', '10')),
            'inicio' => $hora(\ajuste('titulacion_examen_hora_inicio', '09:00'), '09:00'),
            'fin' => $hora(\ajuste('titulacion_examen_hora_fin', '15:00'), '15:00'),
            'duracion' => max(30, min(480, (int) \ajuste('titulacion_examen_duracion', '120'))),
            'espacio' => (int) \ajuste('titulacion_examen_espacio', '0'),
            'propietarios' => max(1, min(5, (int) \ajuste('titulacion_sinodales_propietarios', '3'))),
            'suplentes' => max(0, min(5, (int) \ajuste('titulacion_sinodales_suplentes', '2'))),
        ];
    }

    public static function espacio(): ?array
    {
        $id = self::config()['espacio'];
        try {
            return $id > 0 ? Agenda::espacio($id) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Primer día que puede elegirse: los días hábiles mínimos después de hoy. */
    public static function fechaMinima(): DateTimeImmutable
    {
        $d = Calendario::agregarHabiles(new DateTimeImmutable('today'), self::config()['dias']);
        while (!Calendario::esHabil($d)) {
            $d = $d->modify('+1 day');
        }
        return $d;
    }

    /** Valida la fecha (hábil y con la anticipación mínima). Devuelve el motivo si no sirve. */
    public static function errorFecha(string $fecha): ?string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            return 'Elige una fecha válida.';
        }
        if ($d < self::fechaMinima()) {
            return 'La fecha debe ser al menos ' . self::config()['dias'] . ' días hábiles posterior a hoy (a partir del ' . self::fechaMinima()->format('d/m/Y') . ').';
        }
        if (!Calendario::esHabil($d)) {
            return 'Elige un día hábil (lunes a viernes, sin días festivos).';
        }
        return null;
    }

    /** @return string[] horas de inicio libres (HH:MM) en el espacio de exámenes */
    public static function horarios(string $fecha): array
    {
        $c = self::config();
        if (self::errorFecha($fecha) !== null || $c['espacio'] <= 0) {
            return [];
        }
        return Agenda::iniciosLibres($c['espacio'], $fecha, $c['duracion'], $c['inicio'], $c['fin']);
    }

    /**
     * @param array<int,array{nombre?:string,email?:string,grado?:string,genero?:string}> $entrada
     * @return array<int,array{calidad:string,orden:int,nombre:string,grado:?string,genero:string,email:string}>
     */
    public static function sinodo(array $entrada, ?int $propietarios = null, ?int $suplentes = null): array
    {
        $c = self::config();
        $propietarios ??= $c['propietarios'];
        $suplentes ??= $c['suplentes'];
        $salida = [];
        $vistos = [];
        for ($i = 0; $i < $propietarios + $suplentes; $i++) {
            $x = is_array($entrada[$i] ?? null) ? $entrada[$i] : [];
            $n = trim((string) ($x['nombre'] ?? ''));
            $gr = mb_substr(trim((string) ($x['grado'] ?? '')), 0, 30);
            $ge = in_array(($x['genero'] ?? ''), ['masculino', 'femenino'], true) ? $x['genero'] : null;
            $e = strtolower(trim((string) ($x['email'] ?? '')));
            if (mb_strlen($n) < 3 || !filter_var($e, FILTER_VALIDATE_EMAIL) || strlen($e) > 120 || $ge === null) {
                throw new RuntimeException('Indica nombre, correo válido y género de los ' . $propietarios . ' propietarios y ' . $suplentes . ' suplente(s).');
            }
            if (isset($vistos[$e])) {
                throw new RuntimeException('Una misma persona no puede repetirse en el sínodo.');
            }
            $vistos[$e] = true;
            $salida[] = ['calidad' => $i < $propietarios ? 'propietario' : 'suplente', 'orden' => $i + 1, 'nombre' => mb_substr($n, 0, 160), 'grado' => $gr !== '' ? $gr : null, 'genero' => $ge, 'email' => $e];
        }
        return $salida;
    }

    /** El pasante envía fecha, hora y sínodo: se aparta el espacio y se pide al Coordinador ratificar o modificar el sínodo. */
    public static function solicitar(array $tramite, string $etapa, string $tipo, string $fecha, string $hora, array $sinodoEntrada, int $usuarioId): void
    {
        $tid = (int) $tramite['id'];
        if (Agenda::reservaExamen($tid, $tipo) !== null) {
            throw new RuntimeException('Ya apartaste el espacio para tu ' . (Oficios::EXAMEN[$tipo] ?? 'examen') . '; solo se permite una vez. Si necesitas cambiarlo, pídelo al Departamento de Evaluación Profesional.');
        }
        if (($e = self::errorFecha($fecha)) !== null) {
            throw new RuntimeException($e);
        }
        $c = self::config();
        $espacio = self::espacio();
        if ($espacio === null) {
            throw new RuntimeException('Titulación aún no tiene configurado el auditorio de exámenes. Avisa al Departamento de Evaluación Profesional.');
        }
        if (!in_array($hora, self::horarios($fecha), true)) {
            throw new RuntimeException('Esa hora ya no está disponible en ' . $espacio['nombre'] . '. Elige otra.');
        }
        $sinodo = self::sinodo($sinodoEntrada);
        $fin = Agenda::hhmm((int) substr($hora, 0, 2) * 60 + (int) substr($hora, 3, 2) + $c['duracion']);
        $alumno = (string) ($tramite['datos']['datos']['nombre'] ?? $tramite['alumno_nombre'] ?? '');
        $reservaId = Agenda::reservar((int) $espacio['id'], [
            'fecha' => $fecha, 'inicio' => $hora, 'fin' => $fin,
            'descripcion' => ucfirst(Oficios::EXAMEN[$tipo] ?? 'examen') . ' de titulación · ' . $alumno . ' · ' . $tramite['folio'],
            'responsable' => $alumno, 'responsable_email' => (string) ($tramite['alumno_email'] ?? ''),
            'origen' => 'titulacion', 'tramite_id' => $tid, 'examen' => $tipo,
        ], $usuarioId);
        $datos = ['fecha' => $fecha, 'hora' => $hora, 'lugar' => (string) $espacio['nombre'], 'reserva_id' => $reservaId];
        try {
            Jurado::solicitar($tramite, $etapa, $tipo, $datos + ['propuesta' => $sinodo], $usuarioId);
            TitulacionRepository::guardarHito($tid, $etapa, $datos + ['sinodo' => $sinodo], $usuarioId);
        } catch (Throwable $ex) {
            Agenda::cancelar($reservaId, $usuarioId);
            Database::ejecutar("UPDATE tit_asignaciones_jurado SET estado = 'cancelada' WHERE tramite_id = ? AND etapa = ? AND estado = 'solicitada'", [$tid, $etapa]);
            throw $ex;
        }
        TitulacionRepository::evento($tid, $usuarioId, 'jurado', 'Solicitaste tu ' . (Oficios::EXAMEN[$tipo] ?? 'examen') . ' para el ' . Oficios::fecha($fecha) . ' a las ' . $hora . ' horas en ' . $espacio['nombre'] . '; el Coordinador de Docencia revisará tu sínodo.');
    }
}
