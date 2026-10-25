<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use DateTimeImmutable;

/** Días hábiles (lunes a viernes, sin las fechas no laborables de Ajustes) y semáforo de plazos. */
final class Calendario
{
    private static ?array $inhabiles = null;

    /** @return array<string,true> */
    private static function inhabiles(): array
    {
        if (self::$inhabiles === null) {
            self::$inhabiles = [];
            $texto = function_exists('ajuste') ? \ajuste('titulacion_dias_inhabiles', '') : '';
            foreach (preg_split('/[\s,;]+/', $texto, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $f) {
                $d = DateTimeImmutable::createFromFormat('!Y-m-d', $f);
                if ($d && $d->format('Y-m-d') === $f) {
                    self::$inhabiles[$f] = true;
                }
            }
        }
        return self::$inhabiles;
    }

    public static function reiniciar(): void
    {
        self::$inhabiles = null;
    }

    public static function esHabil(DateTimeImmutable $d): bool
    {
        return (int) $d->format('N') < 6 && !isset(self::inhabiles()[$d->format('Y-m-d')]);
    }

    /** Fecha límite: $dias hábiles después de $desde (el día de asignación no cuenta). */
    public static function agregarHabiles(DateTimeImmutable $desde, int $dias): DateTimeImmutable
    {
        $d = $desde;
        while ($dias > 0) {
            $d = $d->modify('+1 day');
            if (self::esHabil($d)) {
                $dias--;
            }
        }
        return $d;
    }

    /** Días hábiles que faltan hasta el límite (negativo si ya venció). Hoy no cuenta. */
    public static function restantes(DateTimeImmutable $hoy, DateTimeImmutable $limite): int
    {
        $hoy = $hoy->setTime(0, 0);
        $limite = $limite->setTime(0, 0);
        if ($limite == $hoy) {
            return 0;
        }
        $signo = $limite > $hoy ? 1 : -1;
        [$a, $b] = $signo > 0 ? [$hoy, $limite] : [$limite, $hoy];
        $n = 0;
        for ($d = $a->modify('+1 day'); $d <= $b; $d = $d->modify('+1 day')) {
            if (self::esHabil($d)) {
                $n++;
            }
        }
        return $signo * $n;
    }

    /**
     * Semáforo de una revisión: verde mientras no pasen los días hábiles del plazo (0 a 7 por omisión),
     * rojo a partir del día hábil siguiente; «listo» si ya se firmó.
     */
    public static function semaforo(string $estadoAsignacion, string $fechaLimite, ?DateTimeImmutable $hoy = null): string
    {
        if ($estadoAsignacion === 'firmada') {
            return 'listo';
        }
        if ($estadoAsignacion === 'cancelada') {
            return 'cancelada';
        }
        $hoy ??= new DateTimeImmutable('today');
        return self::restantes($hoy, new DateTimeImmutable($fechaLimite)) < 0 ? 'rojo' : 'verde';
    }

    public static function diasPlazo(): int
    {
        return max(1, (int) (function_exists('ajuste') ? \ajuste('titulacion_dias_revision', '7') : 7));
    }
}
