<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use App\Core\Auth;
use App\Core\Database;

/**
 * Lleva a la base lo que se leyó de un acta de Word o de una hoja de Excel. Un caso que ya existe (misma clave) no se duplica ni
 * se pisa: solo se completa lo que le falta (solicitud, antecedentes, acuerdos, observación). Así se puede importar primero el
 * acta del Consejo Académico y después la del de Gobierno a la misma reunión.
 */
final class Importador
{
    /** Crea la reunión con los datos del encabezado del acta. */
    public static function crearReunion(array $acta, string $etiqueta): int
    {
        $c = $acta['consejo'];
        $etiqueta = mb_substr(trim($etiqueta), 0, 80);
        if ($etiqueta === '') {
            $etiqueta = $acta['fecha'] ? ucfirst(Texto::MESES[(int) substr($acta['fecha'], 5, 2) - 1]) . ' ' . substr($acta['fecha'], 0, 4) : 'Reunión ' . date('Y-m-d');
        }
        $id = Database::ejecutar(
            "INSERT INTO consejos_reuniones (etiqueta, tipo_sesion, presidente, secretario, creado_por, estado) VALUES (?, ?, ?, ?, ?, 'preparacion')",
            [$etiqueta, $acta['sesion'], mb_substr($acta['presidente'], 0, 160), mb_substr($acta['secretario'], 0, 160), Auth::id()]
        );
        self::completarReunion($id, $acta);
        return $id;
    }

    /** Llena los datos del Consejo del acta que la reunión aún no tiene. */
    public static function completarReunion(int $id, array $acta): void
    {
        $c = $acta['consejo'];
        if (!in_array($c, ['academico', 'gobierno'], true)) {
            return;
        }
        $r = Database::una('SELECT * FROM consejos_reuniones WHERE id = ?', [$id]) ?: [];
        $nuevo = [
            $c . '_fecha' => $acta['fecha'], $c . '_hora' => $acta['hora'] ? date('H:i', strtotime($acta['hora']) + ($acta['convocatoria'] === 'primera' ? 1800 : 0)) . ':00' : null, $c . '_lugar' => mb_substr($acta['lugar'], 0, 160),
            $c . '_convocatoria' => $acta['sesion'] === 'extraordinaria' ? 'unica' : 'segunda', 'consejeros_' . $c => implode("\n", $acta['consejeros']),
            'actas_anteriores_' . $c => implode("\n", $acta['actas_anteriores']),
            'presidente' => mb_substr($acta['presidente'], 0, 160), 'secretario' => mb_substr($acta['secretario'], 0, 160),
        ];
        $set = [];
        $params = [];
        foreach ($nuevo as $col => $v) {
            $vacio = !isset($r[$col]) || $r[$col] === '' || $r[$col] === null;
            if ($vacio && $v !== null && $v !== '') {
                $set[] = "{$col} = ?";
                $params[] = $v;
            }
        }
        if ($set) {
            Database::ejecutar('UPDATE consejos_reuniones SET ' . implode(', ', $set) . ' WHERE id = ?', [...$params, $id]);
        }
    }

    /**
     * @param array[] $casos
     * @param string $origen 'academico' o 'gobierno' (acta de Word) o '' (Excel)
     * @return array{nuevos:int,completados:int,sinCambio:int}
     */
    public static function casos(int $reunionId, array $casos, string $origen = ''): array
    {
        $nuevos = $completados = $sinCambio = 0;
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            foreach ($casos as $c) {
                $sol = Html::limpiar((string) $c['solicitud_html']);
                $ant = Html::limpiar((string) ($c['antecedentes_html'] ?? ''));
                $ac = trim((string) ($c['acuerdo_academico'] ?? ''));
                $go = $origen === 'academico' ? '' : trim((string) ($c['acuerdo_gobierno'] ?? ''));
                $obs = trim((string) ($c['observacion'] ?? ''));
                $autor = (string) (Auth::usuario()['nombre'] ?? '');
                $ex = Database::una('SELECT * FROM consejos_asuntos WHERE reunion_id = ? AND grupo = ? AND numero = ?', [$reunionId, $c['grupo'], (int) $c['numero']]);
                if (!$ex) {
                    Database::ejecutar(
                        'INSERT INTO consejos_asuntos (reunion_id, grupo, numero, origen, solicitud_html, antecedentes_html, texto_busqueda, aval_academico, acuerdo_academico, aprob_gobierno, acuerdo_gobierno, observacion, observacion_autor, creado_por)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                        [$reunionId, $c['grupo'], (int) $c['numero'], ($ac === '' && $go !== '') ? 'gobierno' : 'academico', $sol, $ant, trim(Html::texto($sol) . "\n" . Html::texto($ant)), self::aval($ac), $ac, self::aprob($go), $go,
                            $obs !== '' ? $obs : null, $obs !== '' ? $autor : null, Auth::id()]
                    );
                    $nuevos++;
                    continue;
                }
                $cambios = [];
                if (trim((string) $ex['solicitud_html']) === '' && $sol !== '') {
                    $cambios['solicitud_html'] = $sol;
                }
                if (trim((string) $ex['antecedentes_html']) === '' && $ant !== '') {
                    $cambios['antecedentes_html'] = $ant;
                }
                if (trim((string) $ex['acuerdo_academico']) === '' && $ac !== '') {
                    $cambios['acuerdo_academico'] = $ac;
                    $cambios['aval_academico'] = self::aval($ac);
                }
                if (trim((string) $ex['acuerdo_gobierno']) === '' && $go !== '') {
                    $cambios['acuerdo_gobierno'] = $go;
                    $cambios['aprob_gobierno'] = self::aprob($go);
                }
                if (trim((string) $ex['observacion']) === '' && $obs !== '') {
                    $cambios['observacion'] = $obs;
                    $cambios['observacion_autor'] = $autor;
                }
                if (!$cambios) {
                    $sinCambio++;
                    continue;
                }
                if (isset($cambios['solicitud_html']) || isset($cambios['antecedentes_html'])) {
                    $cambios['texto_busqueda'] = trim(Html::texto($cambios['solicitud_html'] ?? (string) $ex['solicitud_html']) . "\n" . Html::texto($cambios['antecedentes_html'] ?? (string) $ex['antecedentes_html']));
                }
                $set = implode(', ', array_map(static fn($k) => $k . ' = ?', array_keys($cambios)));
                $extra = isset($cambios['acuerdo_academico']) || isset($cambios['acuerdo_gobierno']) ? ', version_acuerdo = version_acuerdo + 1' : '';
                Database::ejecutar("UPDATE consejos_asuntos SET {$set}{$extra} WHERE id = ?", [...array_values($cambios), (int) $ex['id']]);
                $completados++;
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return ['nuevos' => $nuevos, 'completados' => $completados, 'sinCambio' => $sinCambio];
    }

    /** Si el acuerdo del Consejo Académico avala o no, según cómo empieza. */
    public static function aval(string $acuerdo): string
    {
        if (trim($acuerdo) === '') {
            return 'pendiente';
        }
        if (preg_match('/^(no se (otorga|avala|aprueba|puede)|no es )/iu', $acuerdo)) {
            return 'no_avala';
        }
        return preg_match('/aval|aprueb|otorga/iu', $acuerdo) ? 'avala' : 'pendiente';
    }

    public static function aprob(string $acuerdo): string
    {
        if (trim($acuerdo) === '') {
            return 'pendiente';
        }
        if (preg_match('/^(no se aprueba|no es competencia|no se avala)/iu', $acuerdo)) {
            return 'no_aprueba';
        }
        return preg_match('/aprueb|avala/iu', $acuerdo) ? 'aprueba' : 'pendiente';
    }
}
