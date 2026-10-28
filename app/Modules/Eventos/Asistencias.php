<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\Auth;
use App\Core\Database;

/**
 * Asistencia de la comunidad a los eventos, para su expediente de formación integral y para los organismos
 * acreditadores (por carrera). Con el QR del evento o la palabra clave que se da en la sesión, la asistencia queda
 * validada al momento; con una constancia o capturas de pantalla queda pendiente hasta que Difusión Cultural la
 * revisa. Cada asistencia tiene un folio que cualquiera (por ejemplo, el profesor que dio permiso) puede verificar.
 */
final class Asistencias
{
    /** Niveles por puntos acumulados en el periodo, del más alto al más bajo. */
    public const NIVELES = [100 => 'Oro', 60 => 'Plata', 30 => 'Bronce', 1 => 'Participante'];

    public const MAX_ARCHIVOS = 3;

    /** Minutos antes del inicio y después del fin en que se acepta el QR o la palabra clave. */
    private const ANTES = 30;
    private const DESPUES = 120;

    /** Días después del evento en que todavía se puede subir una constancia. */
    private const DIAS_CONSTANCIA = 30;

    public static function fin(array $ev): int
    {
        return $ev['fin'] ? (int) strtotime((string) $ev['fin']) : (int) strtotime((string) $ev['inicio']) + 3 * 3600;
    }

    /** ¿El evento registra asistencia? Solo los que cuentan para la formación integral. */
    public static function aplica(array $ev): bool
    {
        return (int) ($ev['formacion_integral'] ?? 0) === 1;
    }

    public static function ventanaAbierta(array $ev): bool
    {
        $t = time();
        return $t >= (int) strtotime((string) $ev['inicio']) - self::ANTES * 60 && $t <= self::fin($ev) + self::DESPUES * 60;
    }

    public static function constanciaPermitida(array $ev): bool
    {
        $t = time();
        return $t >= (int) strtotime((string) $ev['inicio']) - self::ANTES * 60 && $t <= self::fin($ev) + self::DIAS_CONSTANCIA * 86400;
    }

    public static function de(int $eventoId, int $usuarioId): ?array
    {
        $r = Database::una('SELECT * FROM evento_asistencias WHERE evento_id = ? AND usuario_id = ?', [$eventoId, $usuarioId]);
        if ($r) {
            $r['archivos'] = json_decode((string) $r['archivos'], true) ?: [];
        }
        return $r;
    }

    private static function folio(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        do {
            $f = 'FI';
            for ($i = 0; $i < 9; $i++) {
                $f .= $alfabeto[random_int(0, 31)];
            }
        } while (Database::valor('SELECT id FROM evento_asistencias WHERE folio = ?', [$f]));
        return $f;
    }

    /**
     * Registra la asistencia de la persona en sesión. QR y clave la validan; una constancia agrega archivos y, si la
     * asistencia no estaba validada, la deja pendiente de revisión. @throws \InvalidArgumentException
     */
    public static function registrar(array $ev, string $metodo, array $archivos = []): array
    {
        if (!self::aplica($ev)) {
            throw new \InvalidArgumentException('Este aviso no registra asistencia.');
        }
        $uid = Auth::id();
        $previa = self::de((int) $ev['id'], $uid);
        if ($metodo === 'constancia') {
            if (!self::constanciaPermitida($ev)) {
                throw new \InvalidArgumentException('El plazo para subir constancias de este evento terminó.');
            }
            $todos = array_slice(array_merge($previa['archivos'] ?? [], $archivos), 0, self::MAX_ARCHIVOS);
            if ($previa) {
                $estado = $previa['estado'] === 'validada' ? 'validada' : 'pendiente';
                Database::ejecutar('UPDATE evento_asistencias SET archivos = ?, estado = ?, motivo = IF(? = \'pendiente\', NULL, motivo) WHERE id = ?',
                    [json_encode($todos, JSON_UNESCAPED_UNICODE), $estado, $estado, (int) $previa['id']]);
                return self::de((int) $ev['id'], $uid);
            }
        } elseif (!self::ventanaAbierta($ev)) {
            throw new \InvalidArgumentException('El registro con QR o palabra clave solo está abierto durante el evento. Si asististe, sube tu constancia o capturas.');
        } elseif ($previa && $previa['estado'] === 'validada') {
            return $previa;
        }
        $validada = $metodo !== 'constancia';
        $pref = Clasificacion::preferencias($uid);
        $datos = [$validada ? 'validada' : 'pendiente', $validada ? (int) $ev['puntos'] : 0];
        if ($previa) {
            Database::ejecutar('UPDATE evento_asistencias SET metodo = ?, estado = ?, puntos = ?, motivo = NULL, revisado_por = NULL, revisado_en = NULL WHERE id = ?',
                [$metodo, ...$datos, (int) $previa['id']]);
        } else {
            Database::ejecutar(
                'INSERT INTO evento_asistencias (evento_id, usuario_id, programa_id, semestre, metodo, archivos, estado, puntos, folio) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $ev['id'], $uid, ((int) (Auth::usuario()['programa_id'] ?? 0)) ?: null, $pref['semestre'], $metodo,
                 $archivos ? json_encode($archivos, JSON_UNESCAPED_UNICODE) : null, ...$datos, self::folio()]
            );
        }
        return self::de((int) $ev['id'], $uid);
    }

    /** Difusión Cultural valida o rechaza una asistencia con constancia. */
    public static function revisar(array $ev, int $id, bool $aprobar, string $motivo): void
    {
        Database::ejecutar(
            'UPDATE evento_asistencias SET estado = ?, puntos = ?, motivo = ?, revisado_por = ?, revisado_en = NOW() WHERE id = ? AND evento_id = ?',
            [$aprobar ? 'validada' : 'rechazada', $aprobar ? (int) $ev['puntos'] : 0, $aprobar ? null : mb_substr($motivo, 0, 300), Auth::id(), $id, (int) $ev['id']]
        );
    }

    /** Asistencias de un evento con nombre, correo y carrera de cada persona. */
    public static function delEvento(int $eventoId, ?string $estado = null): array
    {
        $filas = Database::todas(
            'SELECT a.*, u.nombre, u.email, p.siglas
               FROM evento_asistencias a JOIN usuarios u ON u.id = a.usuario_id LEFT JOIN programas p ON p.id = a.programa_id
              WHERE a.evento_id = ?' . ($estado !== null ? ' AND a.estado = ?' : '') . ' ORDER BY a.estado = \'pendiente\' DESC, p.siglas, u.nombre',
            $estado !== null ? [$eventoId, $estado] : [$eventoId]
        );
        foreach ($filas as &$f) {
            $f['archivos'] = json_decode((string) $f['archivos'], true) ?: [];
        }
        return $filas;
    }

    /** Expediente de una persona: sus asistencias con el evento, más recientes primero. */
    public static function delUsuario(int $usuarioId): array
    {
        return Database::todas(
            'SELECT a.*, ev.titulo, ev.inicio, ev.categoria, ev.tema, ev.horas, ev.organizador, p.siglas
               FROM evento_asistencias a JOIN eventos ev ON ev.id = a.evento_id LEFT JOIN programas p ON p.id = a.programa_id
              WHERE a.usuario_id = ? ORDER BY ev.inicio DESC',
            [$usuarioId]
        );
    }

    /** Puntos, horas y nivel por periodo (solo asistencias validadas). @return array<string,array{puntos:int,horas:float,eventos:int,nivel:string}> */
    public static function resumen(array $asistencias): array
    {
        $porPeriodo = [];
        foreach ($asistencias as $a) {
            if ($a['estado'] !== 'validada') {
                continue;
            }
            $p = Clasificacion::periodo((string) $a['inicio']);
            $porPeriodo[$p] ??= ['puntos' => 0, 'horas' => 0.0, 'eventos' => 0];
            $porPeriodo[$p]['puntos'] += (int) $a['puntos'];
            $porPeriodo[$p]['horas'] += (float) ($a['horas'] ?? 0);
            $porPeriodo[$p]['eventos']++;
        }
        krsort($porPeriodo);
        foreach ($porPeriodo as &$r) {
            $r['nivel'] = self::nivel($r['puntos']);
        }
        return $porPeriodo;
    }

    public static function nivel(int $puntos): string
    {
        foreach (self::NIVELES as $min => $nombre) {
            if ($puntos >= $min) {
                return $nombre;
            }
        }
        return '';
    }

    /** Siguiente nivel y puntos que faltan, o null si ya alcanzó el más alto. */
    public static function siguiente(int $puntos): ?array
    {
        $sig = null;
        foreach (self::NIVELES as $min => $nombre) {
            if ($puntos < $min) {
                $sig = ['nivel' => $nombre, 'faltan' => $min - $puntos];
            }
        }
        return $sig;
    }

    /** Asistencia por folio, para la verificación pública. */
    public static function porFolio(string $folio): ?array
    {
        if (!preg_match('/^FI[A-Z2-9]{9}$/D', $folio)) {
            return null;
        }
        return Database::una(
            'SELECT a.folio, a.estado, a.metodo, a.creado_en, a.revisado_en, u.nombre, p.siglas, p.nombre AS programa,
                    ev.titulo, ev.inicio, ev.fin, ev.horas, ev.organizador, ev.modalidad
               FROM evento_asistencias a JOIN usuarios u ON u.id = a.usuario_id JOIN eventos ev ON ev.id = a.evento_id
               LEFT JOIN programas p ON p.id = a.programa_id
              WHERE a.folio = ?',
            [$folio]
        ) ?: null;
    }

    /** Ranking de puntos validados por carrera en un periodo (para reconocer a quienes más participan). */
    public static function ranking(string $periodo, int $programaId = 0, int $limite = 10): array
    {
        $filas = Database::todas(
            'SELECT a.usuario_id, u.nombre, p.siglas, a.puntos, ev.inicio
               FROM evento_asistencias a JOIN usuarios u ON u.id = a.usuario_id JOIN eventos ev ON ev.id = a.evento_id
               LEFT JOIN programas p ON p.id = a.programa_id
              WHERE a.estado = \'validada\'' . ($programaId ? ' AND a.programa_id = ?' : ''),
            $programaId ? [$programaId] : []
        );
        $suma = [];
        foreach ($filas as $f) {
            if (Clasificacion::periodo((string) $f['inicio']) !== $periodo) {
                continue;
            }
            $k = (int) $f['usuario_id'];
            $suma[$k] ??= ['nombre' => $f['nombre'], 'siglas' => $f['siglas'], 'puntos' => 0, 'eventos' => 0];
            $suma[$k]['puntos'] += (int) $f['puntos'];
            $suma[$k]['eventos']++;
        }
        usort($suma, static fn(array $a, array $b): int => $b['puntos'] <=> $a['puntos'] ?: strcmp((string) $a['nombre'], (string) $b['nombre']));
        return array_slice($suma, 0, $limite);
    }
}
