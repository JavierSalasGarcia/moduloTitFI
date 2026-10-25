<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Número de oficio: consecutivo anual por quien firma. Lo que firma la Subdirección (o la Dirección) usa la serie
 * «subdireccion» (ACAD/FI/###/AAAA) y lo que firma la Jefatura de Evaluación Profesional y Grado, la serie «jefatura».
 * El número se aparta al generar el oficio y queda en la bitácora con el destinatario y el asunto; sustituye a los
 * controles de oficios en Excel.
 */
final class NumeroOficio
{
    public const SERIES = ['subdireccion' => 'Subdirección Académica', 'jefatura' => 'Evaluación Profesional y Grado'];
    public const FORMATOS = ['subdireccion' => 'ACAD/FI/{n}/{AAAA}', 'jefatura' => 'FI/DEPyG/{n}/{AAAA}'];

    public static function formato(string $serie): string
    {
        return (string) \ajuste('titulacion_oficio_formato_' . $serie, self::FORMATOS[$serie] ?? '{n}/{AAAA}');
    }

    public static function texto(string $serie, int $numero, int $anio): string
    {
        return strtr(self::formato($serie), ['{n}' => str_pad((string) $numero, 3, '0', STR_PAD_LEFT), '{AAAA}' => (string) $anio]);
    }

    /** Número que tomaría el siguiente oficio de la serie en el año en curso. */
    public static function siguiente(string $serie, ?int $anio = null): int
    {
        $anio ??= (int) date('Y');
        $ultimo = Database::valor('SELECT ultimo FROM tit_oficio_series WHERE serie = ? AND anio = ?', [$serie, $anio]);
        return $ultimo !== null ? (int) $ultimo + 1 : 1;
    }

    /**
     * Aparta el siguiente número y lo registra en la bitácora. Si el folio no es de un trámite (vista previa), no
     * aparta nada y devuelve el número con guiones.
     */
    public static function apartar(string $serie, string $folio, string $asunto, string $dirigidoA, ?string $codigo): string
    {
        $anio = (int) date('Y');
        $tid = $folio !== '' ? Database::valor('SELECT id FROM tit_tramites WHERE folio = ?', [strtok($folio, ' ')]) : null;
        if (!$tid || !isset(self::SERIES[$serie])) {
            return strtr(self::formato($serie), ['{n}' => '___', '{AAAA}' => (string) $anio]);
        }
        $pdo = Database::pdo();
        $propia = !$pdo->inTransaction();
        if ($propia) {
            $pdo->beginTransaction();
        }
        try {
            Database::ejecutar('INSERT IGNORE INTO tit_oficio_series (serie, anio, ultimo) VALUES (?, ?, 0)', [$serie, $anio]);
            $n = (int) Database::valor('SELECT ultimo FROM tit_oficio_series WHERE serie = ? AND anio = ? FOR UPDATE', [$serie, $anio]) + 1;
            Database::ejecutar('UPDATE tit_oficio_series SET ultimo = ? WHERE serie = ? AND anio = ?', [$n, $serie, $anio]);
            $texto = self::texto($serie, $n, $anio);
            Database::ejecutar(
                'INSERT INTO tit_oficios (serie, anio, numero, texto, tramite_id, asunto, dirigido_a, verificacion, generado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$serie, $anio, $n, $texto, (int) $tid, mb_substr($asunto, 0, 250), mb_substr($dirigidoA, 0, 250), $codigo, Auth::id() ?: null]
            );
            if ($propia) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return $texto;
    }

    /** Fija el último número usado de la serie en el año (para continuar la numeración de los controles anteriores). */
    public static function fijarUltimo(string $serie, int $anio, int $ultimo): void
    {
        Database::ejecutar('INSERT INTO tit_oficio_series (serie, anio, ultimo) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ultimo = VALUES(ultimo)', [$serie, $anio, $ultimo]);
    }

    /** Bitácora de oficios con el documento al que quedó ligado cada número (por su código de verificación). */
    public static function bitacora(?int $anio, ?string $serie, string $q = ''): array
    {
        $donde = ['1 = 1'];
        $p = [];
        if ($anio) {
            $donde[] = 'o.anio = ?';
            $p[] = $anio;
        }
        if ($serie && isset(self::SERIES[$serie])) {
            $donde[] = 'o.serie = ?';
            $p[] = $serie;
        }
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $donde[] = '(o.texto LIKE ? OR o.asunto LIKE ? OR o.dirigido_a LIKE ? OR t.folio LIKE ?)';
            array_push($p, $like, $like, $like, $like);
        }
        return Database::todas(
            'SELECT o.*, t.folio, u.nombre AS generado_nombre, v.documento_id, v.ruta AS guardado, v.revision_id
             FROM tit_oficios o JOIN tit_tramites t ON t.id = o.tramite_id
             LEFT JOIN usuarios u ON u.id = o.generado_por
             LEFT JOIN tit_verificaciones v ON v.codigo = o.verificacion
             WHERE ' . implode(' AND ', $donde) . ' ORDER BY o.anio DESC, o.serie, o.numero DESC LIMIT 500',
            $p
        );
    }
}
