<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;

/**
 * Números de acta de evaluación profesional y de aplazamiento, por licenciatura (los asigna el sistema de actas y
 * Titulación los registra con el resultado). No se repiten en la licenciatura, contando también el historial importado.
 */
final class Actas
{
    /** Pasante o registro histórico que ya tiene el acta; null si está libre. */
    public static function ocupada(array $tramite, string $numero, bool $aplazamiento): ?string
    {
        $carrera = (string) ($tramite['datos']['datos']['carrera'] ?? '');
        $tipo = $aplazamiento ? 'aplazamiento' : 'evaluacion';
        $f = Database::una('SELECT t.folio FROM tit_actas a JOIN tit_tramites t ON t.id = a.tramite_id WHERE a.carrera = ? AND a.tipo = ? AND a.numero = ? AND a.tramite_id <> ?',
            [$carrera, $tipo, (int) $numero, (int) $tramite['id']]);
        if ($f) {
            return 'el trámite ' . $f['folio'];
        }
        $h = $aplazamiento ? null : Database::una('SELECT alumno FROM tit_historico WHERE carrera = ? AND acta = ? AND (tramite_id IS NULL OR tramite_id <> ?)', [$carrera, (int) $numero, (int) $tramite['id']]);
        return $h ? (string) $h['alumno'] . ' (historial)' : null;
    }

    public static function registrar(array $tramite, string $numero, bool $aplazamiento, string $fecha): void
    {
        if (!ctype_digit($numero)) {
            return;
        }
        Database::ejecutar(
            'INSERT INTO tit_actas (tramite_id, carrera, tipo, numero, fecha) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE numero = VALUES(numero), fecha = VALUES(fecha)',
            [(int) $tramite['id'], (string) ($tramite['datos']['datos']['carrera'] ?? ''), $aplazamiento ? 'aplazamiento' : 'evaluacion', (int) $numero, $fecha !== '' ? $fecha : null]
        );
    }
}
