<?php
declare(strict_types=1);

namespace App\Modules\Espacios;

use App\Core\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Auditorios y salas: horarios de cada espacio y registro de su ocupación sin empalmes.
 * Otros módulos (Titulación) reservan a través de esta clase para que todo quede en un solo calendario.
 */
final class Agenda
{
    public const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    // ------------------------------------------------------------------ espacios

    public static function espacios(bool $soloActivos = true): array
    {
        return Database::todas('SELECT * FROM esp_espacios' . ($soloActivos ? ' WHERE activo = 1' : '') . ' ORDER BY orden, nombre');
    }

    public static function espacio(int $id): ?array
    {
        return Database::una('SELECT * FROM esp_espacios WHERE id = ?', [$id]);
    }

    /** @return array<int,array<int,array{inicio:string,fin:string}>> día => intervalos HH:MM */
    public static function horario(int $espacioId): array
    {
        $salida = [];
        foreach (Database::todas('SELECT dia, hora_inicio, hora_fin FROM esp_horarios WHERE espacio_id = ? ORDER BY dia, hora_inicio', [$espacioId]) as $h) {
            $salida[(int) $h['dia']][] = ['inicio' => substr((string) $h['hora_inicio'], 0, 5), 'fin' => substr((string) $h['hora_fin'], 0, 5)];
        }
        return $salida;
    }

    /**
     * Intervalos en que el espacio puede usarse en esa fecha. Ya no hay horario de servicio por espacio (la ocupación
     * es por evento): se usa la jornada completa de la Facultad para proponer horas libres.
     */
    public static function horarioDelDia(int $espacioId, string $fecha): array
    {
        return DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) ? [['inicio' => '07:00', 'fin' => '22:00']] : [];
    }

    // ------------------------------------------------------------------ ocupación

    public static function reservasDelDia(int $espacioId, string $fecha): array
    {
        return Database::todas(
            "SELECT * FROM esp_reservas WHERE espacio_id = ? AND fecha = ? AND estado = 'activa' ORDER BY hora_inicio",
            [$espacioId, $fecha]
        );
    }

    public static function reservasEntre(int $espacioId, string $desde, string $hasta): array
    {
        return Database::todas(
            "SELECT r.*, u.nombre AS registro_nombre FROM esp_reservas r JOIN usuarios u ON u.id = r.registrado_por
             WHERE r.espacio_id = ? AND r.fecha BETWEEN ? AND ? AND r.estado = 'activa' ORDER BY r.fecha, r.hora_inicio",
            [$espacioId, $desde, $hasta]
        );
    }

    public static function reserva(int $id): ?array
    {
        return Database::una('SELECT r.*, e.nombre AS espacio_nombre FROM esp_reservas r JOIN esp_espacios e ON e.id = r.espacio_id WHERE r.id = ?', [$id]);
    }

    /** Reserva vigente de un examen de titulación (el pasante solo puede apartar una por examen). */
    public static function reservaExamen(int $tramiteId, string $examen): ?array
    {
        return Database::una(
            "SELECT r.*, e.nombre AS espacio_nombre FROM esp_reservas r JOIN esp_espacios e ON e.id = r.espacio_id
             WHERE r.tramite_id = ? AND r.examen = ? AND r.estado = 'activa' ORDER BY r.id DESC LIMIT 1",
            [$tramiteId, $examen]
        );
    }

    private static function minutos(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm) + [0, 0]);
        return $h * 60 + $m;
    }

    public static function hhmm(int $minutos): string
    {
        return sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60);
    }

    /** ¿El intervalo cabe completo en alguno de los horarios del día? */
    public static function dentroDeHorario(int $espacioId, string $fecha, string $inicio, string $fin): bool
    {
        $a = self::minutos($inicio);
        $b = self::minutos($fin);
        foreach (self::horarioDelDia($espacioId, $fecha) as $h) {
            if ($a >= self::minutos($h['inicio']) && $b <= self::minutos($h['fin'])) {
                return true;
            }
        }
        return false;
    }

    public static function libre(int $espacioId, string $fecha, string $inicio, string $fin): bool
    {
        return (int) Database::valor(
            "SELECT COUNT(*) FROM esp_reservas WHERE espacio_id = ? AND fecha = ? AND estado = 'activa' AND hora_inicio < ? AND hora_fin > ?",
            [$espacioId, $fecha, $fin . ':00', $inicio . ':00']
        ) === 0;
    }

    /**
     * Horas de inicio disponibles en una fecha para una duración, opcionalmente acotadas a una ventana (p. ej. 9:00 a 15:00).
     * @return string[] HH:MM
     */
    public static function iniciosLibres(int $espacioId, string $fecha, int $duracion, ?string $desde = null, ?string $hasta = null, int $paso = 30): array
    {
        $ocupadas = array_map(static fn(array $r): array => [self::minutos(substr((string) $r['hora_inicio'], 0, 5)), self::minutos(substr((string) $r['hora_fin'], 0, 5))], self::reservasDelDia($espacioId, $fecha));
        $salida = [];
        foreach (self::horarioDelDia($espacioId, $fecha) as $h) {
            $ini = max(self::minutos($h['inicio']), $desde !== null ? self::minutos($desde) : 0);
            $fin = min(self::minutos($h['fin']), $hasta !== null ? self::minutos($hasta) : 24 * 60);
            for ($t = $ini; $t + $duracion <= $fin; $t += $paso) {
                $choca = false;
                foreach ($ocupadas as [$a, $b]) {
                    if ($t < $b && $t + $duracion > $a) {
                        $choca = true;
                        break;
                    }
                }
                if (!$choca) {
                    $salida[self::hhmm($t)] = self::hhmm($t);
                }
            }
        }
        ksort($salida);
        return array_values($salida);
    }

    /**
     * Registra la ocupación revisando horario y empalmes dentro de una transacción (bloquea el espacio).
     * @param array{fecha:string,inicio:string,fin:string,descripcion:string,responsable:string,responsable_email?:?string,origen?:string,tramite_id?:?int,examen?:?string} $r
     */
    public static function reservar(int $espacioId, array $r, int $usuarioId): int
    {
        $fecha = (string) $r['fecha'];
        $inicio = (string) $r['inicio'];
        $fin = (string) $r['fin'];
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) {
            throw new RuntimeException('La fecha no es válida.');
        }
        if ($fecha < date('Y-m-d')) {
            throw new RuntimeException('No se puede registrar una fecha pasada.');
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $inicio) || !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $fin) || $inicio >= $fin) {
            throw new RuntimeException('Revisa la hora de inicio y de término.');
        }
        $descripcion = trim((string) $r['descripcion']);
        $responsable = trim((string) $r['responsable']);
        if (mb_strlen($descripcion) < 3 || mb_strlen($responsable) < 3) {
            throw new RuntimeException('Describe el evento e indica a la persona responsable que lo solicitó.');
        }
        $email = strtolower(trim((string) ($r['responsable_email'] ?? '')));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('El correo de la persona responsable no es válido.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $e = Database::una('SELECT * FROM esp_espacios WHERE id = ? FOR UPDATE', [$espacioId]);
            if (!$e || !(int) $e['activo']) {
                throw new RuntimeException('El espacio no está disponible.');
            }
            if (!self::libre($espacioId, $fecha, $inicio, $fin)) {
                throw new RuntimeException($e['nombre'] . ' ya está ocupado en ese horario.');
            }
            if (!empty($r['tramite_id']) && !empty($r['examen']) && self::reservaExamen((int) $r['tramite_id'], (string) $r['examen']) !== null) {
                throw new RuntimeException('Ya hay un espacio apartado para este examen.');
            }
            $id = Database::ejecutar(
                'INSERT INTO esp_reservas (espacio_id, serie_id, fecha, hora_inicio, hora_fin, descripcion, responsable, responsable_email, registrado_por, origen, tramite_id, examen) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$espacioId, $r['serie_id'] ?? null, $fecha, $inicio . ':00', $fin . ':00', mb_substr($descripcion, 0, 500), mb_substr($responsable, 0, 160), $email !== '' ? mb_substr($email, 0, 120) : null,
                    $usuarioId, ($r['origen'] ?? 'manual') === 'titulacion' ? 'titulacion' : 'manual', $r['tramite_id'] ?? null, $r['examen'] ?? null]
            );
            $pdo->commit();
            return $id;
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }

    /** Fechas de una serie semanal: días de la semana (1 = lunes … 7 = domingo) entre dos fechas, hasta 300 sesiones y un año. */
    public static function fechasSerie(string $desde, string $hasta, array $dias): array
    {
        $a = DateTimeImmutable::createFromFormat('!Y-m-d', $desde);
        $b = DateTimeImmutable::createFromFormat('!Y-m-d', $hasta);
        $dias = array_values(array_unique(array_filter(array_map('intval', $dias), static fn(int $d): bool => $d >= 1 && $d <= 7)));
        if (!$a || !$b || $b < $a || !$dias) {
            throw new RuntimeException('Para repetir el evento elige al menos un día de la semana y una fecha de término posterior a la de inicio.');
        }
        if ($b > $a->modify('+1 year')) {
            throw new RuntimeException('Una serie puede durar como máximo un año.');
        }
        $fechas = [];
        for ($d = $a; $d <= $b; $d = $d->modify('+1 day')) {
            if (in_array((int) $d->format('N'), $dias, true)) {
                $fechas[] = $d->format('Y-m-d');
            }
        }
        if (count($fechas) > 300) {
            throw new RuntimeException('La serie tendría más de 300 sesiones; acórtala.');
        }
        return $fechas;
    }

    /** Fechas de la lista en las que el horario ya está ocupado: fecha => «HH:MM–HH:MM descripción». */
    public static function choques(int $espacioId, array $fechas, string $inicio, string $fin): array
    {
        if (!$fechas) {
            return [];
        }
        $salida = [];
        foreach (Database::todas(
            "SELECT fecha, hora_inicio, hora_fin, descripcion FROM esp_reservas WHERE espacio_id = ? AND estado = 'activa' AND hora_inicio < ? AND hora_fin > ?
              AND fecha IN (" . implode(',', array_fill(0, count($fechas), '?')) . ') ORDER BY fecha, hora_inicio',
            [$espacioId, $fin . ':00', $inicio . ':00', ...$fechas]) as $r) {
            $salida[$r['fecha']] = substr((string) $r['hora_inicio'], 0, 5) . '–' . substr((string) $r['hora_fin'], 0, 5) . ' ' . $r['descripcion'];
        }
        return $salida;
    }

    /**
     * Registra una serie semanal: crea la serie y una reserva por fecha libre (las que chocan se omiten).
     * @return array{serie:int,creadas:int,omitidas:string[]}
     */
    public static function reservarSerie(int $espacioId, array $r, array $dias, string $hasta, int $usuarioId): array
    {
        $fechas = array_values(array_filter(self::fechasSerie((string) $r['fecha'], $hasta, $dias), static fn(string $f): bool => $f >= date('Y-m-d')));
        if (!$fechas) {
            throw new RuntimeException('La serie no tiene fechas futuras.');
        }
        $choques = self::choques($espacioId, $fechas, (string) $r['inicio'], (string) $r['fin']);
        $libres = array_values(array_diff($fechas, array_keys($choques)));
        if (!$libres) {
            throw new RuntimeException('Todas las fechas de la serie chocan con otros registros.');
        }
        $serie = Database::ejecutar(
            'INSERT INTO esp_series (espacio_id, dias, hora_inicio, hora_fin, fecha_inicio, fecha_fin, descripcion, responsable, responsable_email, registrado_por) VALUES (?,?,?,?,?,?,?,?,?,?)',
            [$espacioId, implode(',', array_map('intval', $dias)), $r['inicio'] . ':00', $r['fin'] . ':00', $fechas[0], end($fechas), mb_substr(trim((string) $r['descripcion']), 0, 500),
                mb_substr(trim((string) $r['responsable']), 0, 160), trim((string) ($r['responsable_email'] ?? '')) ?: null, $usuarioId]
        );
        $creadas = 0;
        $omitidas = array_keys($choques);
        foreach ($libres as $f) {
            try {
                self::reservar($espacioId, ['fecha' => $f, 'serie_id' => $serie] + $r, $usuarioId);
                $creadas++;
            } catch (RuntimeException) {
                $omitidas[] = $f;   // se ocupó mientras se guardaba
            }
        }
        sort($omitidas);
        return ['serie' => $serie, 'creadas' => $creadas, 'omitidas' => $omitidas];
    }

    /** Cancela una sesión, esa y las siguientes de su serie, o la serie completa (solo fechas de hoy en adelante). */
    public static function cancelarAlcance(array $reserva, string $alcance, int $usuarioId): int
    {
        if (empty($reserva['serie_id']) || $alcance === 'esta') {
            self::cancelar((int) $reserva['id'], $usuarioId);
            return 1;
        }
        $desde = $alcance === 'siguientes' ? (string) $reserva['fecha'] : date('Y-m-d');
        $params = [(int) $reserva['serie_id'], max($desde, date('Y-m-d'))];
        $n = (int) Database::valor("SELECT COUNT(*) FROM esp_reservas WHERE serie_id = ? AND estado = 'activa' AND fecha >= ?", $params);
        Database::ejecutar("UPDATE esp_reservas SET estado = 'cancelada', cancelada_por = ?, cancelada_en = NOW() WHERE serie_id = ? AND estado = 'activa' AND fecha >= ?",
            [$usuarioId, ...$params]);
        return $n;
    }

    public static function cancelar(int $reservaId, int $usuarioId): void
    {
        Database::ejecutar("UPDATE esp_reservas SET estado = 'cancelada', cancelada_por = ?, cancelada_en = NOW() WHERE id = ? AND estado = 'activa'", [$usuarioId, $reservaId]);
    }

    /** Libera el espacio apartado para un examen (cuando Titulación reabre la etapa). */
    public static function cancelarExamen(int $tramiteId, string $examen, int $usuarioId): void
    {
        Database::ejecutar("UPDATE esp_reservas SET estado = 'cancelada', cancelada_por = ?, cancelada_en = NOW() WHERE tramite_id = ? AND examen = ? AND estado = 'activa'", [$usuarioId, $tramiteId, $examen]);
    }
}
