<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

use App\Core\Database;

/**
 * Folio de las constancias: consecutivo anual por departamento firmante, con el formato <SIGLAS>/CONST/<n>/<AAAA>
 * (n con tres dígitos como mínimo). Las siglas salen del ajuste `constancias_siglas` (JSON {"clave_del_departamento":
 * "SIGLAS"}) y, si el departamento no está ahí, de las iniciales de su nombre sin artículos ni preposiciones («Titular
 * de la …» se omite): Coordinación de Estudios Avanzados → CEA, Subdirección Académica → SA, Departamento de
 * Evaluación Profesional y Grado → DEPG. Un nombre de una sola palabra toma sus tres primeras letras (Dirección → DIR).
 * El número se aparta con bloqueo de fila (SELECT … FOR UPDATE) para que dos envíos simultáneos no lo repitan.
 */
final class Folio
{
    private const VACIAS = ['de', 'del', 'la', 'las', 'el', 'los', 'y', 'e', 'en', 'a', 'para', 'por'];

    public static function siglas(int $departamentoId): string
    {
        $d = Database::una('SELECT clave, nombre FROM departamentos WHERE id = ?', [$departamentoId]);
        if (!$d) {
            return 'FI';
        }
        $ajuste = json_decode(\ajuste('constancias_siglas', '{}'), true);
        $propia = is_array($ajuste) ? trim((string) ($ajuste[$d['clave']] ?? '')) : '';
        if ($propia !== '' && preg_match('/^[A-Za-z0-9.-]{1,20}$/', $propia)) {
            return strtoupper($propia);
        }
        return self::derivar((string) $d['nombre']);
    }

    public static function derivar(string $nombre): string
    {
        $nombre = preg_replace('/^Titular\s+de(l| la)\s+/iu', '', trim($nombre)) ?? $nombre;
        $plano = iconv('UTF-8', 'ASCII//TRANSLIT', $nombre) ?: $nombre;
        $palabras = array_values(array_filter(preg_split('/[^A-Za-z0-9]+/', $plano) ?: [], static fn(string $p): bool => $p !== '' && !in_array(strtolower($p), self::VACIAS, true)));
        if (!$palabras) {
            return 'FI';
        }
        if (count($palabras) === 1) {
            return strtoupper(substr($palabras[0], 0, 3));
        }
        return strtoupper(implode('', array_map(static fn(string $p): string => $p[0], $palabras)));
    }

    public static function texto(string $siglas, int $numero, int $anio): string
    {
        return $siglas . '/CONST/' . str_pad((string) $numero, 3, '0', STR_PAD_LEFT) . '/' . $anio;
    }

    /** Folio provisional para la vista previa (el número se aparta al enviar a firma). */
    public static function provisional(int $departamentoId): string
    {
        return self::siglas($departamentoId) . '/CONST/___/' . date('Y');
    }

    /**
     * Aparta el siguiente número del departamento en el año en curso. Si otro departamento tiene las mismas siglas y
     * ya usó ese folio, se agregan las del id del departamento para no repetirlo.
     * @return array{folio:string,anio:int,numero:int}
     */
    public static function apartar(int $departamentoId): array
    {
        $anio = (int) date('Y');
        $pdo = Database::pdo();
        $propia = !$pdo->inTransaction();
        if ($propia) {
            $pdo->beginTransaction();
        }
        try {
            Database::ejecutar('INSERT IGNORE INTO cst_folios (departamento_id, anio, ultimo) VALUES (?, ?, 0)', [$departamentoId, $anio]);
            $n = (int) Database::valor('SELECT ultimo FROM cst_folios WHERE departamento_id = ? AND anio = ? FOR UPDATE', [$departamentoId, $anio]) + 1;
            Database::ejecutar('UPDATE cst_folios SET ultimo = ? WHERE departamento_id = ? AND anio = ?', [$n, $departamentoId, $anio]);
            $siglas = self::siglas($departamentoId);
            $folio = self::texto($siglas, $n, $anio);
            if (Database::valor('SELECT 1 FROM cst_solicitudes WHERE folio = ? AND departamento_id <> ?', [$folio, $departamentoId])) {
                $folio = self::texto($siglas . '-' . $departamentoId, $n, $anio);
            }
            if ($propia) {
                $pdo->commit();
            }
        } catch (\Throwable $e) {
            if ($propia && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['folio' => $folio, 'anio' => $anio, 'numero' => $n];
    }
}
