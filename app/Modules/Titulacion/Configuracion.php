<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Database;
use Throwable;

/**
 * Capa de configuración editable por la administración: requisitos documentales (activo, entrega física,
 * cotejo) y documentos añadidos. Se superpone al catálogo base; sin base de datos disponible el catálogo
 * funciona tal como está en código. Los formatos los genera la plataforma (Services\FormatosOficiales).
 */
final class Configuracion
{
    private static ?array $cache = null;

    public static function reiniciar(): void
    {
        self::$cache = null;
    }

    private static function datos(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $vacio = ['docs' => [], 'extras' => []];
        if (!class_exists(Database::class)) {
            return self::$cache = $vacio;
        }
        try {
            foreach (Database::todas('SELECT * FROM tit_docs_cfg') as $d) {
                $vacio['docs'][$d['modalidad'] . '|' . $d['clave']] = $d;
            }
            $vacio['extras'] = Database::todas('SELECT * FROM tit_docs_extra ORDER BY id');
        } catch (Throwable $e) {
            error_log('Titulación: configuración no disponible: ' . $e->getMessage());
            $vacio = ['docs' => [], 'extras' => []];
        }
        return self::$cache = $vacio;
    }

    // ----------------------------------------------------------------- documentos

    private static function resolver(string $modalidad, string $clave, string $campo, mixed $porDefecto): mixed
    {
        $d = self::datos()['docs'];
        foreach ([$modalidad . '|' . $clave, '*|' . $clave] as $k) {
            if (isset($d[$k]) && $d[$k][$campo] !== null) {
                return $d[$k][$campo];
            }
        }
        return $porDefecto;
    }

    /** Valores heredados al editar un ámbito concreto: lo de «todas las opciones» o el catálogo. */
    public static function heredado(string $modalidad, string $clave, string $campo, mixed $porDefecto): mixed
    {
        $fila = self::datos()['docs']['*|' . $clave] ?? null;
        return $modalidad !== '*' && $fila && $fila[$campo] !== null ? $fila[$campo] : $porDefecto;
    }

    public static function documento(array $d, string $modalidad): array
    {
        $c = $d['clave'];
        $d['activo'] = (bool) self::resolver($modalidad, $c, 'activo', true);
        $d['fisico'] = (bool) self::resolver($modalidad, $c, 'fisico', $d['fisico'] ?? false);
        $d['cotejo'] = (bool) self::resolver($modalidad, $c, 'cotejo', $d['cotejo'] ?? false);
        $d['firma_cotejo'] = (string) self::resolver($modalidad, $c, 'firma_cotejo', $d['firma_cotejo'] ?? 'sello');
        $d['nombre'] = (string) (self::resolver($modalidad, $c, 'nombre', null) ?: $d['nombre']);
        $nota = self::resolver($modalidad, $c, 'nota', null);
        $d['nota'] = $nota !== null && $nota !== '' ? (string) $nota : (string) ($d['nota'] ?? '');
        return $d;
    }

    /** @return array<int,array<string,mixed>> */
    public static function extras(string $modalidad): array
    {
        return array_values(array_filter(
            self::datos()['extras'],
            static fn(array $x): bool => $x['modalidad'] === '*' || $x['modalidad'] === $modalidad
        ));
    }

    public static function todosLosExtras(): array
    {
        return self::datos()['extras'];
    }

    public static function aplicarEtapas(array $etapas, string $modalidad): array
    {
        $formatos = Catalogo::formatos();
        $extras = self::extras($modalidad);
        foreach ($etapas as &$e) {
            $docs = [];
            foreach ($e['documentos'] ?? [] as $d) {
                $docs[] = $d;
            }
            foreach ($extras as $x) {
                if ($x['etapa'] !== $e['clave']) {
                    continue;
                }
                if ((int) $x['solo_fisico'] === 1) {
                    $d = self::documento(['clave' => $x['clave'], 'nombre' => $x['nombre'], 'nota' => (string) $x['nota'], 'fisico' => true], $modalidad);
                    if ($d['activo']) {
                        $e['presencial'][] = $d['nombre'] . ' *' . ($d['nota'] !== '' ? ' (' . $d['nota'] . ')' : '');
                    }
                    continue;
                }
                $docs[] = [
                    'clave' => $x['clave'], 'nombre' => $x['nombre'], 'nota' => (string) $x['nota'],
                    'condicion' => $x['condicion'] ?: null, 'formato' => $x['formato'] ?: null, 'extra_id' => (int) $x['id'],
                ];
            }
            $resultado = [];
            foreach ($docs as $d) {
                $d = self::documento($d, $modalidad);
                if (!$d['activo']) {
                    continue;
                }
                if (!empty($d['formato']) && !isset($formatos[$d['formato']])) {
                    $d['formato'] = null;
                }
                $resultado[] = $d;
            }
            if (isset($e['documentos']) || $resultado) {
                $e['documentos'] = $resultado;
            }
        }
        unset($e);
        return $etapas;
    }

    // ------------------------------------------------------------------- escritura

    public static function bitacora(int $usuarioId, string $accion, string $detalle): void
    {
        Database::ejecutar('INSERT INTO tit_config_bitacora (usuario_id, accion, detalle) VALUES (?, ?, ?)', [$usuarioId, $accion, mb_substr($detalle, 0, 500)]);
    }

    /**
     * Guarda los ajustes de un documento para un ámbito. Los valores iguales a los heredados se
     * guardan como NULL para que un cambio posterior en «todas las opciones» siga aplicando.
     */
    public static function guardarDocumento(string $modalidad, string $clave, array $nuevo, array $base, int $usuarioId): void
    {
        $columnas = ['activo', 'fisico', 'cotejo', 'firma_cotejo', 'nombre', 'nota'];
        $valores = [];
        foreach ($columnas as $c) {
            $her = self::heredado($modalidad, $clave, $c, $base[$c] ?? null);
            if (in_array($c, ['nombre', 'nota'], true)) {
                $v = trim((string) ($nuevo[$c] ?? ''));
                $valores[$c] = ($v === '' || $v === (string) ($base[$c] ?? '')) ? null : $v;
                continue;
            }
            $v = $c === 'firma_cotejo' ? (string) $nuevo[$c] : (int) (bool) $nuevo[$c];
            $hv = $c === 'firma_cotejo' ? (string) $her : (int) (bool) $her;
            $valores[$c] = $v === $hv ? null : $v;
        }
        if (array_filter($valores, static fn($v): bool => $v !== null) === []) {
            Database::ejecutar('DELETE FROM tit_docs_cfg WHERE modalidad = ? AND clave = ?', [$modalidad, $clave]);
        } else {
            Database::ejecutar(
                'INSERT INTO tit_docs_cfg (modalidad, clave, activo, fisico, cotejo, firma_cotejo, nombre, nota, actualizado_por)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE activo = VALUES(activo), fisico = VALUES(fisico), cotejo = VALUES(cotejo),
                    firma_cotejo = VALUES(firma_cotejo), nombre = VALUES(nombre), nota = VALUES(nota), actualizado_por = VALUES(actualizado_por)',
                [$modalidad, $clave, $valores['activo'], $valores['fisico'], $valores['cotejo'], $valores['firma_cotejo'], $valores['nombre'], $valores['nota'], $usuarioId]
            );
        }
        self::reiniciar();
    }
}
