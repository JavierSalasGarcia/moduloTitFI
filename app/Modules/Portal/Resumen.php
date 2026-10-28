<?php
declare(strict_types=1);

namespace App\Modules\Portal;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Identidad;
use App\Core\Pendientes;
use App\Modules\Comunicados\Avisos;
use App\Modules\Eventos\Clasificacion;
use App\Modules\Notificaciones\Notificaciones;
use Throwable;

/** Resumen de Inicio: lo pendiente, las notificaciones sin leer, los próximos eventos y los avisos recientes de la persona con sesión. */
final class Resumen
{
    private const MAX = 5;

    /** @return array{pendientes:array,total_pendientes:int,notificaciones:array,total_notificaciones:int,eventos:array,avisos:array} */
    public static function de(): array
    {
        $pend = [];
        $total = 0;
        foreach (self::seguro(static fn(): array => Pendientes::agrupados()) as $g) {
            foreach ($g['items'] as $i) {
                $total++;
                if (count($pend) < self::MAX) {
                    $pend[] = $i + ['modulo' => $g['nombre']];
                }
            }
        }
        $u = Auth::usuario();
        $correos = array_merge(Identidad::correosDeUsuario((int) $u['id']), [(string) $u['email']]);
        $sinLeer = self::seguro(static fn(): array => array_values(array_filter(Notificaciones::de($correos, 100), static fn(array $n): bool => $n['leida_en'] === null)));
        return [
            'pendientes' => $pend,
            'total_pendientes' => $total,
            'notificaciones' => array_slice($sinLeer, 0, self::MAX),
            'total_notificaciones' => count($sinLeer),
            'eventos' => self::eventos(),
            'avisos' => self::avisos(),
        ];
    }

    /** Eventos que están en curso o empiezan en los próximos 30 días y van dirigidos a la persona. */
    private static function eventos(): array
    {
        return self::seguro(static function (): array {
            $filas = Database::todas(
                'SELECT id, titulo, inicio, fin, lugar, modalidad, categoria, publico, semestre_min, semestre_max FROM eventos
                  WHERE COALESCE(fin, inicio) >= NOW() AND inicio <= DATE_ADD(NOW(), INTERVAL 30 DAY) ORDER BY inicio LIMIT 60'
            );
            $perfil = Clasificacion::perfil();
            $lista = array_filter(Clasificacion::conProgramas($filas), static fn(array $e): bool => Clasificacion::dirigidoA($e, $perfil));
            return array_slice(array_values($lista), 0, self::MAX);
        });
    }

    /** Avisos oficiales de los últimos 14 días que no han vencido. */
    private static function avisos(): array
    {
        return self::seguro(static function (): array {
            $desde = date('Y-m-d H:i:s', strtotime('-14 days'));
            $lista = array_filter(Avisos::visibles(false), static fn(array $c): bool => $c['creado_en'] >= $desde && (empty($c['vence_en']) || $c['vence_en'] >= date('Y-m-d')));
            return array_slice(array_values($lista), 0, self::MAX);
        });
    }

    /** Un bloque que falle no debe romper la página de inicio. */
    private static function seguro(callable $f): array
    {
        try {
            return $f();
        } catch (Throwable $e) {
            error_log('Resumen de inicio: ' . $e->getMessage());
            return [];
        }
    }
}
