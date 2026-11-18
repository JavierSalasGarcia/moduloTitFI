<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\Auth;
use App\Core\Database;
use App\Modules\Comunicados\Avisos;

/**
 * Lo que cada persona ya leyó de «Avisos y eventos» (globito del menú) y el cintillo de la inauguración.
 * Un aviso cuenta como leído al abrirlo; también con «Marcar todo como leído». Solo cuentan los avisos vigentes
 * y los eventos próximos publicados en los últimos 60 días, para que una cuenta nueva no arranque con cientos.
 */
final class Lecturas
{
    /** Inauguración de la plataforma: el cintillo se muestra hasta el final de ese día. */
    public const INAUGURACION = ['fin' => '2026-12-07 23:59:59', 'texto' => 'lunes 7 de diciembre de 2026, 11:00 h, en el Auditorio 120 A'];

    public static function inauguracionVigente(): bool
    {
        return time() <= strtotime(self::INAUGURACION['fin']);
    }

    public static function marcar(string $tipo, int $id): void
    {
        if (!Auth::id() || !in_array($tipo, ['comunicado', 'evento'], true)) {
            return;
        }
        try {
            Database::ejecutar('INSERT IGNORE INTO aviso_lecturas (usuario_id, tipo, item_id) VALUES (?, ?, ?)', [Auth::id(), $tipo, $id]);
        } catch (\Throwable) {
        }
    }

    /** @return array{comunicado:array<int,bool>,evento:array<int,bool>} */
    public static function leidos(): array
    {
        $r = ['comunicado' => [], 'evento' => []];
        if (!Auth::id()) {
            return $r;
        }
        try {
            foreach (Database::todas('SELECT tipo, item_id FROM aviso_lecturas WHERE usuario_id = ?', [Auth::id()]) as $f) {
                $r[$f['tipo']][(int) $f['item_id']] = true;
            }
        } catch (\Throwable) {
        }
        return $r;
    }

    /** Avisos y eventos que cuentan para el globito: [['tipo'=>…, 'id'=>…], …]. */
    public static function pendientesDeLeer(): array
    {
        $desde = date('Y-m-d H:i:s', strtotime('-60 days'));
        $hoy = date('Y-m-d');
        $items = [];
        foreach (Avisos::visibles(false) as $c) {
            if ((!empty($c['vence_en']) && $c['vence_en'] < $hoy) || $c['creado_en'] < $desde) {
                continue;
            }
            $items[] = ['tipo' => 'comunicado', 'id' => (int) $c['id']];
        }
        foreach (Database::todas('SELECT id FROM eventos WHERE COALESCE(fin, inicio) >= NOW() AND creado_en >= ?', [$desde]) as $e) {
            $items[] = ['tipo' => 'evento', 'id' => (int) $e['id']];
        }
        $leidos = self::leidos();
        return array_values(array_filter($items, static fn($i) => empty($leidos[$i['tipo']][$i['id']])));
    }

    public static function noLeidos(): int
    {
        try {
            return count(self::pendientesDeLeer());
        } catch (\Throwable) {
            return 0;
        }
    }

    public static function marcarTodos(): void
    {
        foreach (self::pendientesDeLeer() as $i) {
            self::marcar($i['tipo'], $i['id']);
        }
    }
}
