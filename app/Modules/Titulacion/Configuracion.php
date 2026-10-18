<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\App;
use App\Core\Database;
use InvalidArgumentException;
use Throwable;

/**
 * Capa de configuración editable por la administración: formatos (plantilla y coordenadas),
 * requisitos documentales (activo, entrega física, cotejo) y documentos añadidos. Se superpone
 * al catálogo base; sin base de datos disponible el catálogo funciona tal como está en código.
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
        $vacio = ['formatos' => [], 'docs' => [], 'extras' => []];
        if (!class_exists(Database::class)) {
            return self::$cache = $vacio;
        }
        try {
            foreach (Database::todas('SELECT * FROM tit_formatos_cfg') as $f) {
                $vacio['formatos'][$f['clave']] = $f;
            }
            foreach (Database::todas('SELECT * FROM tit_docs_cfg') as $d) {
                $vacio['docs'][$d['modalidad'] . '|' . $d['clave']] = $d;
            }
            $vacio['extras'] = Database::todas('SELECT * FROM tit_docs_extra ORDER BY id');
        } catch (Throwable $e) {
            error_log('Titulación: configuración no disponible: ' . $e->getMessage());
            $vacio = ['formatos' => [], 'docs' => [], 'extras' => []];
        }
        return self::$cache = $vacio;
    }

    // ------------------------------------------------------------------ formatos

    public static function formatoCfg(string $clave): ?array
    {
        return self::datos()['formatos'][$clave] ?? null;
    }

    /** @return array<string,array<string,mixed>> */
    public static function formatosCfg(): array
    {
        return self::datos()['formatos'];
    }

    public static function aplicarFormatos(array $base): array
    {
        $cfg = self::datos()['formatos'];
        foreach ($base as $clave => &$f) {
            $f += ['activo' => true, 'personalizado' => false, 'etapa' => null, 'modalidades' => null];
            if (isset($cfg[$clave])) {
                $f['activo'] = (bool) $cfg[$clave]['activo'];
                $f['nombre'] = $cfg[$clave]['nombre'] ?: $f['nombre'];
                $f['firma'] = $cfg[$clave]['firma'] ?: $f['firma'];
            }
        }
        unset($f);
        foreach ($cfg as $clave => $c) {
            if ((int) $c['personalizado'] === 1 && !isset($base[$clave])) {
                $base[$clave] = [
                    'tipo' => 'pdf', 'nombre' => (string) $c['nombre'], 'firma' => (string) ($c['firma'] ?: 'el pasante'),
                    'activo' => (bool) $c['activo'], 'personalizado' => true,
                    'etapa' => $c['etapa'], 'modalidades' => $c['modalidades'] ?: '*',
                ];
            }
        }
        return $base;
    }

    private static function dirStorage(): string
    {
        return rtrim((string) App::config('archivos.storage'), '/\\');
    }

    /**
     * Especificación y plantilla vigentes de un formato PDF: lo guardado por la administración
     * o, si no hay cambios, los valores originales que acompañan al módulo.
     *
     * @return array{spec: array, plantilla: string}|null
     */
    public static function resolverPdf(string $clave): ?array
    {
        $cfg = self::formatoCfg($clave);
        $original = \App\Modules\Titulacion\Services\PdfFormato::specs()[$clave] ?? null;
        $spec = null;
        if ($cfg && !empty($cfg['spec'])) {
            $json = json_decode((string) $cfg['spec'], true);
            $spec = is_array($json) ? $json : null;
        }
        $spec ??= $original;
        if ($spec === null) {
            return null;
        }
        if ($cfg && !empty($cfg['plantilla_ruta'])) {
            $ruta = self::dirStorage() . '/' . $cfg['plantilla_ruta'];
        } else {
            $ruta = dirname(__FILE__) . '/plantillas/' . ($original['plantilla'] ?? $spec['plantilla'] ?? '');
        }
        return is_file($ruta) ? ['spec' => $spec, 'plantilla' => $ruta] : null;
    }

    /** Hoja membretada del protocolo: la reemplazada por la administración o la que trae el módulo. */
    public static function rutaProtocolo(): string
    {
        $cfg = self::formatoCfg('protocolo');
        if ($cfg && !empty($cfg['plantilla_ruta']) && is_file(self::dirStorage() . '/' . $cfg['plantilla_ruta'])) {
            return self::dirStorage() . '/' . $cfg['plantilla_ruta'];
        }
        return __DIR__ . '/plantillas/docx/Membretada.docx';
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
            if (!empty($e['formatos'])) {
                $e['formatos'] = array_values(array_filter($e['formatos'], static fn(string $f): bool => ($formatos[$f]['activo'] ?? true)));
            }
            foreach ($formatos as $clave => $f) {
                if (!empty($f['personalizado']) && $f['activo'] && $f['etapa'] === $e['clave']
                    && ($f['modalidades'] === '*' || in_array($modalidad, explode(',', (string) $f['modalidades']), true))) {
                    $e['formatos'][] = $clave;
                }
            }
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
                if (!empty($d['formato']) && !($formatos[$d['formato']]['activo'] ?? true)) {
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

    // ------------------------------------------------------------------ validación

    /**
     * Valida y normaliza una especificación enviada desde el visor.
     *
     * @param array<string,array> $variables Formatos::variables()
     */
    public static function validarSpec(array $in, array $variables, array $base): array
    {
        $paginas = max(1, (int) ($base['paginas'] ?? 1));
        $num = static function (mixed $v, float $min, float $max, string $que): float {
            if (!is_numeric($v) || (float) $v < $min || (float) $v > $max) {
                throw new InvalidArgumentException('Valor fuera de rango en ' . $que . '.');
            }
            return round((float) $v, 1);
        };
        $ancho = (float) $base['ancho'];
        $alto = (float) $base['alto'];
        $campos = [];
        if (!isset($in['campos']) || !is_array($in['campos']) || count($in['campos']) > 120) {
            throw new InvalidArgumentException('La lista de campos no es válida (máximo 120).');
        }
        foreach ($in['campos'] as $c) {
            $k = (string) ($c['k'] ?? '');
            if (!isset($variables[$k]) || !is_array($c)) {
                throw new InvalidArgumentException('Campo desconocido: ' . $k . '.');
            }
            $tipo = (string) ($c['tipo'] ?? '');
            $p = (int) $num($c['p'] ?? 0, 0, $paginas - 1, 'la página de ' . $k);
            $var = $variables[$k];
            if ($tipo === 'marca') {
                if ($var['tipo'] !== 'marca') {
                    throw new InvalidArgumentException('«' . $k . '» no es un campo de casillas.');
                }
                $m = [];
                foreach ((array) ($c['m'] ?? []) as $opcion => $xy) {
                    if (!isset($var['opciones'][$opcion]) || !is_array($xy) || count($xy) < 2) {
                        continue;
                    }
                    $m[(string) $opcion] = [$num($xy[0], 0, $ancho, $k), $num($xy[1], 0, $alto, $k)];
                }
                $campos[] = ['k' => $k, 'tipo' => 'marca', 'p' => $p, 'm' => $m];
            } elseif ($tipo === 'parrafo') {
                $cajas = [];
                foreach (array_slice((array) ($c['cajas'] ?? []), 0, 12) as $b) {
                    $cajas[] = [$num($b[0] ?? null, 0, $ancho, $k), $num($b[1] ?? null, 0, $alto, $k), $num($b[2] ?? null, 4, $ancho, $k), $num($b[3] ?? null, 4, 200, $k)];
                }
                if (!$cajas) {
                    throw new InvalidArgumentException('El párrafo «' . $k . '» necesita al menos un renglón.');
                }
                $campos[] = ['k' => $k, 'tipo' => 'parrafo', 'p' => $p, 'cajas' => $cajas, 's' => $num($c['s'] ?? 10, 5, 24, 'el tamaño de ' . $k)];
            } elseif ($tipo === 'texto') {
                if ($var['tipo'] === 'marca') {
                    throw new InvalidArgumentException('«' . $k . '» se coloca con casillas.');
                }
                $a = in_array($c['a'] ?? 'L', ['L', 'C', 'R'], true) ? $c['a'] : 'L';
                $campos[] = ['k' => $k, 'tipo' => 'texto', 'p' => $p, 'x' => $num($c['x'] ?? null, 0, $ancho, $k), 'y' => $num($c['y'] ?? null, 0, $alto, $k),
                    'w' => $num($c['w'] ?? null, 4, $ancho, $k), 'h' => $num($c['h'] ?? null, 4, 200, $k), 's' => $num($c['s'] ?? 10, 5, 24, 'el tamaño de ' . $k), 'a' => $a];
            } else {
                throw new InvalidArgumentException('Tipo de campo no válido en ' . $k . '.');
            }
        }
        $sub = [];
        foreach (array_slice((array) ($in['subdirector'] ?? []), 0, 8) as $b) {
            $sub[] = [
                'p' => (int) $num($b['p'] ?? 0, 0, $paginas - 1, 'el nombre del subdirector'),
                'x' => $num($b['x'] ?? null, 0, $ancho, 'subdirector'), 'y' => $num($b['y'] ?? null, 0, $alto, 'subdirector'),
                'w' => $num($b['w'] ?? null, 10, $ancho, 'subdirector'), 'h' => $num($b['h'] ?? null, 4, 60, 'subdirector'),
                's' => $num($b['s'] ?? 11, 5, 24, 'subdirector'), 'negrita' => !empty($b['negrita']),
                'fondo' => array_map(static fn($v): int => max(0, min(255, (int) $v)), array_slice((array) ($b['fondo'] ?? [255, 255, 255]), 0, 3)) + [255, 255, 255],
            ];
        }
        return ['plantilla' => $base['plantilla'], 'ancho' => $ancho, 'alto' => $alto, 'campos' => $campos,
            'subdirector' => $sub, 'subdirector_impreso' => (string) ($base['subdirector_impreso'] ?? 'DR. JAVIER SALAS GARCÍA')];
    }
}
