<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

use App\Core\Database;
use App\Core\Persona;
use App\Modules\Titulacion\Services\Oficios;
use InvalidArgumentException;

/**
 * Plantillas para «Solicitar una constancia» (tabla cst_plantillas). Las crean y editan quienes pueden editar los textos
 * de constancias (Titulacion\Services\Constancias::puedeEditarTextos) y las usa todo el personal al solicitar.
 *
 * Una plantilla tiene asunto, destinatario y cuerpo (formato de Services\Cuerpo) con marcadores {clave}. Cada marcador
 * que no es automático se define en «campos»: etiqueta, tipo (texto, fecha, número, texto largo o tabla con columnas),
 * si es obligatorio y una ayuda. Al solicitar, los valores capturados se sustituyen (llenar()) y el solicitante puede
 * corregir el texto resultante. Los automáticos ({fecha_letras}, {firmante}, {cargo_firmante}, {suscribe}) quedan en el
 * texto y se sustituyen al generar el PDF con quien firma y la fecha de expedición (automaticos()).
 */
final class Plantillas
{
    public const TIPOS = ['texto' => 'Texto corto', 'fecha' => 'Fecha', 'numero' => 'Número', 'texto_largo' => 'Texto largo', 'tabla' => 'Tabla'];
    public const AUTOMATICOS = [
        'fecha_letras' => 'Fecha de expedición en letras («a los dieciocho días del mes de…»)',
        'firmante' => 'Grado y nombre de quien firma',
        'cargo_firmante' => 'Cargo de quien firma',
        'suscribe' => '«El que suscribe» o «La que suscribe», según quien firma',
    ];
    public const MAX_CAMPOS = 30;
    public const MAX_RENGLONES = 100;
    /** Todo lo que está entre llaves se considera marcador (para avisar de los mal escritos). */
    private const PATRON = '/\{([^{}\n]{1,60})\}/u';
    private const CLAVE = '/^[a-z0-9_]{2,40}$/D';

    // ------------------------------------------------------------------ lectura

    /** @return array<int,array> */
    public static function listar(bool $soloActivas): array
    {
        $filas = Database::todas(
            'SELECT p.*, u.nombre AS actualizada_por_nombre FROM cst_plantillas p LEFT JOIN usuarios u ON u.id = COALESCE(p.actualizada_por, p.creada_por)'
            . ($soloActivas ? ' WHERE p.activa = 1' : '') . ' ORDER BY p.activa DESC, p.nombre'
        );
        return array_map([self::class, 'decodificar'], $filas);
    }

    public static function obtener(int $id, bool $soloActiva = false): ?array
    {
        $p = Database::una('SELECT * FROM cst_plantillas WHERE id = ?' . ($soloActiva ? ' AND activa = 1' : ''), [$id]);
        return $p ? self::decodificar($p) : null;
    }

    private static function decodificar(array $p): array
    {
        $p['id'] = (int) $p['id'];
        $p['activa'] = (int) $p['activa'];
        $p['campos'] = self::sanearCampos(json_decode((string) ($p['campos'] ?? '[]'), true) ?: []);
        return $p;
    }

    /** Estructura segura de los campos guardados. */
    private static function sanearCampos(array $campos): array
    {
        $r = [];
        foreach (array_slice($campos, 0, self::MAX_CAMPOS) as $c) {
            $clave = (string) ($c['clave'] ?? '');
            if (!preg_match(self::CLAVE, $clave) || isset(self::AUTOMATICOS[$clave])) {
                continue;
            }
            $tipo = isset(self::TIPOS[$c['tipo'] ?? '']) ? (string) $c['tipo'] : 'texto';
            $r[] = [
                'clave' => $clave,
                'etiqueta' => mb_substr((string) ($c['etiqueta'] ?? ''), 0, 120) ?: self::etiquetaPorOmision($clave),
                'tipo' => $tipo,
                'obligatorio' => !empty($c['obligatorio']),
                'ayuda' => mb_substr((string) ($c['ayuda'] ?? ''), 0, 200),
                'columnas' => $tipo === 'tabla' ? array_values(array_slice(array_map(static fn($x): string => mb_substr((string) $x, 0, 60), (array) ($c['columnas'] ?? [])), 0, Cuerpo::MAX_COLUMNAS)) : [],
            ];
        }
        return $r;
    }

    public static function etiquetaPorOmision(string $clave): string
    {
        $t = trim(str_replace('_', ' ', $clave));
        return $t === '' ? $clave : mb_strtoupper(mb_substr($t, 0, 1)) . mb_substr($t, 1);
    }

    /** Marcadores (lo que está entre llaves) en el orden en que aparecen, sin repetir. @return string[] */
    public static function marcadores(string ...$textos): array
    {
        $r = [];
        foreach ($textos as $t) {
            if (preg_match_all(self::PATRON, $t, $m)) {
                foreach ($m[1] as $x) {
                    $r[$x] = true;
                }
            }
        }
        return array_keys($r);
    }

    /** Marcadores que se piden al solicitar (los no automáticos y con nombre válido). @return string[] */
    public static function pedidos(string ...$textos): array
    {
        return array_values(array_filter(self::marcadores(...$textos), static fn(string $m): bool => (bool) preg_match(self::CLAVE, $m) && !isset(self::AUTOMATICOS[$m])));
    }

    // ------------------------------------------------------------------ edición

    /**
     * Lee y valida el formulario del editor. Los campos se definen con def[clave][etiqueta|tipo|obligatorio|ayuda|columnas].
     * Con $estricto = false (vista previa o «actualizar marcadores») no se valida el nombre único.
     * @return array{nombre:string,descripcion:string,asunto:string,dirigido_a:string,cuerpo:string,campos:array}
     */
    public static function leerFormulario(array $post): array
    {
        $linea = static fn(string $v, int $max): string => mb_substr(trim(preg_replace('/\s+/u', ' ', preg_replace('/[\x00-\x1F\x7F]/u', ' ', $v) ?? '') ?? ''), 0, $max);
        $bloque = static fn(string $v, int $max): string => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', str_replace(["\r\n", "\r"], "\n", $v)) ?? ''), 0, $max);
        $d = [
            'nombre' => $linea((string) ($post['nombre'] ?? ''), 120),
            'descripcion' => $linea((string) ($post['descripcion'] ?? ''), 255),
            'asunto' => $linea((string) ($post['asunto'] ?? ''), 160),
            'dirigido_a' => $bloque((string) ($post['dirigido_a'] ?? ''), 600),
            'cuerpo' => $bloque((string) ($post['cuerpo'] ?? ''), Cuerpo::MAX_TEXTO),
        ];
        if ($d['asunto'] === '') {
            $d['asunto'] = 'CONSTANCIA';
        }
        if ($d['dirigido_a'] === '') {
            $d['dirigido_a'] = 'A QUIEN CORRESPONDA';
        }
        $def = is_array($post['def'] ?? null) ? $post['def'] : [];
        $campos = [];
        foreach (self::pedidos($d['asunto'], $d['dirigido_a'], $d['cuerpo']) as $clave) {
            $x = is_array($def[$clave] ?? null) ? $def[$clave] : [];
            $tipo = isset(self::TIPOS[$x['tipo'] ?? '']) ? (string) $x['tipo'] : 'texto';
            $columnas = array_values(array_filter(array_map(static fn(string $c): string => $linea($c, 60), explode('|', (string) ($x['columnas'] ?? ''))), static fn(string $c): bool => $c !== ''));
            $campos[] = [
                'clave' => $clave, 'etiqueta' => $linea((string) ($x['etiqueta'] ?? ''), 120) ?: self::etiquetaPorOmision($clave), 'tipo' => $tipo,
                // Recién detectado (sin definición previa) se marca obligatorio por omisión.
                'obligatorio' => $x ? !empty($x['obligatorio']) : true, 'ayuda' => $linea((string) ($x['ayuda'] ?? ''), 200),
                'columnas' => $tipo === 'tabla' ? array_slice($columnas, 0, Cuerpo::MAX_COLUMNAS + 1) : [],
            ];
        }
        $d['campos'] = $campos;
        return $d;
    }

    /** Valida una plantilla leída con leerFormulario(). Lanza InvalidArgumentException con un mensaje en lenguaje llano. */
    public static function validar(array $d, ?int $id, bool $nombreUnico = true): void
    {
        if ($d['nombre'] === '') {
            throw new InvalidArgumentException('Escribe el nombre de la plantilla.');
        }
        if (trim($d['cuerpo']) === '') {
            throw new InvalidArgumentException('El cuerpo de la plantilla no puede quedar vacío.');
        }
        foreach (['nombre' => 'el nombre', 'descripcion' => 'la descripción', 'asunto' => 'el asunto', 'dirigido_a' => 'el destinatario', 'cuerpo' => 'el cuerpo'] as $k => $donde) {
            if (preg_match('/<\s*\/?\s*[a-z!?][^>]*>/iu', $d[$k])) {
                throw new InvalidArgumentException('No se admite HTML en ' . $donde . '. Usa el formato sencillo: **negritas** y tablas con |.');
            }
        }
        $invalidos = array_values(array_filter(self::marcadores($d['asunto'], $d['dirigido_a'], $d['cuerpo']), static fn(string $m): bool => !preg_match(self::CLAVE, $m)));
        if ($invalidos) {
            throw new InvalidArgumentException('Estos marcadores no tienen un nombre válido: ' . implode(', ', array_map(static fn(string $m): string => '{' . $m . '}', $invalidos))
                . '. Usa de 2 a 40 letras minúsculas sin acentos, números o guion bajo, por ejemplo {nombre_curso}.');
        }
        foreach (array_keys(self::AUTOMATICOS) as $a) {
            if (str_contains($d['asunto'] . "\n" . $d['dirigido_a'], '{' . $a . '}')) {
                throw new InvalidArgumentException('El marcador automático {' . $a . '} solo puede ir en el cuerpo.');
            }
        }
        if (count($d['campos']) > self::MAX_CAMPOS) {
            throw new InvalidArgumentException('La plantilla pide demasiados datos (máximo ' . self::MAX_CAMPOS . ' marcadores).');
        }
        foreach ($d['campos'] as $c) {
            if ($c['tipo'] !== 'tabla') {
                continue;
            }
            if (!$c['columnas']) {
                throw new InvalidArgumentException('Escribe las columnas de la tabla {' . $c['clave'] . '}, separadas por |.');
            }
            if (count($c['columnas']) > Cuerpo::MAX_COLUMNAS) {
                throw new InvalidArgumentException('La tabla {' . $c['clave'] . '} admite como máximo ' . Cuerpo::MAX_COLUMNAS . ' columnas.');
            }
            if (str_contains($d['asunto'] . "\n" . $d['dirigido_a'], '{' . $c['clave'] . '}') || !preg_match('/^[ \t]*\{' . $c['clave'] . '\}[ \t]*$/m', $d['cuerpo'])) {
                throw new InvalidArgumentException('El marcador de tabla {' . $c['clave'] . '} debe ir solo, en su propio renglón del cuerpo.');
            }
        }
        if ($nombreUnico && Database::valor('SELECT id FROM cst_plantillas WHERE nombre = ? AND id <> ?', [$d['nombre'], (int) $id])) {
            throw new InvalidArgumentException('Ya hay una plantilla llamada «' . $d['nombre'] . '». Elige otro nombre.');
        }
    }

    /** Crea o actualiza una plantilla ya validada y lo anota en la bitácora. */
    public static function guardar(?int $id, array $d, int $usuarioId): int
    {
        $campos = json_encode($d['campos'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ($id === null) {
            $id = Database::ejecutar(
                'INSERT INTO cst_plantillas (nombre, descripcion, asunto, dirigido_a, cuerpo, campos, activa, creada_por, actualizada_por) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)',
                [$d['nombre'], $d['descripcion'], $d['asunto'], $d['dirigido_a'], $d['cuerpo'], $campos, $usuarioId, $usuarioId]
            );
            self::anotar((int) $id, $usuarioId, $d['accion'] ?? 'creada');
            return (int) $id;
        }
        Database::ejecutar('UPDATE cst_plantillas SET nombre = ?, descripcion = ?, asunto = ?, dirigido_a = ?, cuerpo = ?, campos = ?, actualizada_por = ? WHERE id = ?',
            [$d['nombre'], $d['descripcion'], $d['asunto'], $d['dirigido_a'], $d['cuerpo'], $campos, $usuarioId, $id]);
        self::anotar($id, $usuarioId, 'editada');
        return $id;
    }

    /** Copia con el nombre «… (copia)», «… (copia 2)»… */
    public static function duplicar(int $id, int $usuarioId): int
    {
        $p = self::obtener($id);
        if (!$p) {
            throw new InvalidArgumentException('No se encontró la plantilla.');
        }
        $base = mb_substr($p['nombre'], 0, 100) . ' (copia';
        $nombre = $base . ')';
        for ($n = 2; Database::valor('SELECT 1 FROM cst_plantillas WHERE nombre = ?', [$nombre]); $n++) {
            $nombre = $base . ' ' . $n . ')';
        }
        return self::guardar(null, ['nombre' => $nombre, 'descripcion' => $p['descripcion'], 'asunto' => $p['asunto'], 'dirigido_a' => $p['dirigido_a'],
            'cuerpo' => $p['cuerpo'], 'campos' => $p['campos'], 'accion' => 'duplicada'], $usuarioId);
    }

    public static function activar(int $id, bool $activa, int $usuarioId): void
    {
        Database::ejecutar('UPDATE cst_plantillas SET activa = ?, actualizada_por = ? WHERE id = ?', [$activa ? 1 : 0, $usuarioId, $id]);
        self::anotar($id, $usuarioId, $activa ? 'activada' : 'desactivada');
    }

    private static function anotar(int $id, int $usuarioId, string $accion): void
    {
        $p = Database::una('SELECT nombre, descripcion, asunto, dirigido_a, cuerpo, campos, activa FROM cst_plantillas WHERE id = ?', [$id]);
        Database::ejecutar('INSERT INTO cst_plantillas_historial (plantilla_id, usuario_id, accion, version) VALUES (?, ?, ?, ?)',
            [$id, $usuarioId ?: null, $accion, $p ? json_encode($p, JSON_UNESCAPED_UNICODE) : null]);
    }

    /** @return array<int,array{accion:string,creado_en:string,nombre:?string}> */
    public static function historial(int $id, int $limite = 10): array
    {
        return Database::todas('SELECT h.accion, h.creado_en, u.nombre FROM cst_plantillas_historial h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.plantilla_id = ? ORDER BY h.id DESC LIMIT ' . max(1, $limite), [$id]);
    }

    // ------------------------------------------------------------------ uso al solicitar

    /**
     * Lee los valores capturados (campo[clave] o, para tablas, campo[clave][renglón][columna]) y los valida.
     * @return array{valores:array<string,mixed>,errores:array<string,string>}
     */
    public static function leerValores(array $p, array $post): array
    {
        $valores = [];
        $errores = [];
        $limpiar = static fn(string $v): string => str_replace(['|', "\t"], ['/', ' '], preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '');
        foreach ($p['campos'] as $c) {
            $k = $c['clave'];
            $crudo = $post[$k] ?? '';
            if ($c['tipo'] === 'tabla') {
                $filas = [];
                foreach (array_slice(is_array($crudo) ? array_values($crudo) : [], 0, self::MAX_RENGLONES) as $fila) {
                    $celdas = [];
                    foreach (array_keys($c['columnas']) as $j) {
                        $celdas[] = mb_substr(trim(preg_replace('/\s+/u', ' ', $limpiar((string) (is_array($fila) ? ($fila[$j] ?? '') : ''))) ?? ''), 0, 300);
                    }
                    if (implode('', $celdas) !== '') {
                        $filas[] = $celdas;
                    }
                }
                $valores[$k] = $filas;
                if ($c['obligatorio'] && !$filas) {
                    $errores[$k] = 'Llena al menos un renglón.';
                }
                continue;
            }
            $v = is_string($crudo) ? $crudo : '';
            $v = $c['tipo'] === 'texto_largo'
                ? mb_substr(trim(preg_replace("/\n{3,}/", "\n\n", str_replace(["\r\n", "\r"], "\n", $limpiar($v))) ?? ''), 0, 4000)
                : mb_substr(trim(preg_replace('/\s+/u', ' ', $limpiar($v)) ?? ''), 0, 300);
            $valores[$k] = $v;
            if ($v === '') {
                if ($c['obligatorio']) {
                    $errores[$k] = 'Este dato es obligatorio.';
                }
                continue;
            }
            if ($c['tipo'] === 'fecha') {
                $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
                if (!$f || $f->format('Y-m-d') !== $v) {
                    $errores[$k] = 'Escribe una fecha válida.';
                }
            } elseif ($c['tipo'] === 'numero' && !preg_match('/^-?\d{1,12}([.,]\d{1,4})?$/D', $v)) {
                $errores[$k] = 'Escribe solo un número (por ejemplo 20 o 2.5).';
            }
        }
        return ['valores' => $valores, 'errores' => $errores];
    }

    /** Sustituye los valores en el asunto, el destinatario y el cuerpo. @return array{asunto:string,dirigido_a:string,texto:string} */
    public static function llenar(array $p, array $valores, ?string $asunto = null, ?string $dirigido = null): array
    {
        $mapa = [];
        $cuerpo = (string) $p['cuerpo'];
        foreach ($p['campos'] as $c) {
            $k = $c['clave'];
            $v = $valores[$k] ?? '';
            if ($c['tipo'] === 'tabla') {
                $tabla = '';
                if ($v) {
                    $lineas = ['| ' . implode(' | ', $c['columnas']) . ' |', '|' . str_repeat('---|', count($c['columnas']))];
                    foreach ($v as $fila) {
                        $lineas[] = '| ' . implode(' | ', array_map(static fn(string $x): string => $x === '' ? ' ' : $x, $fila)) . ' |';
                    }
                    $tabla = implode("\n", $lineas);
                }
                $cuerpo = preg_replace('/^[ \t]*\{' . $k . '\}[ \t]*$/m', addcslashes($tabla, '\\$'), $cuerpo) ?? $cuerpo;
                continue;
            }
            $mapa['{' . $k . '}'] = $c['tipo'] === 'fecha' && $v !== '' ? Oficios::fecha($v) : (string) $v;
        }
        $texto = strtr($cuerpo, $mapa);
        // Un dato opcional vacío no debe dejar más de una línea en blanco seguida.
        $texto = trim(preg_replace("/\n{3,}/", "\n\n", $texto) ?? $texto);
        return ['asunto' => trim(strtr($asunto ?? (string) $p['asunto'], $mapa)), 'dirigido_a' => trim(strtr($dirigido ?? (string) $p['dirigido_a'], $mapa)), 'texto' => $texto];
    }

    /** Marcadores de la plantilla que siguen en un texto (sin llenar). @return string[] */
    public static function pendientes(array $p, string $texto): array
    {
        $r = [];
        foreach ($p['campos'] as $c) {
            if (str_contains($texto, '{' . $c['clave'] . '}')) {
                $r[] = '{' . $c['clave'] . '}';
            }
        }
        return $r;
    }

    /** Valores de ejemplo para la vista previa de una plantilla. */
    public static function ejemplo(array $p): array
    {
        $v = [];
        foreach ($p['campos'] as $c) {
            $v[$c['clave']] = match ($c['tipo']) {
                'fecha' => date('Y-m-d'),
                'numero' => '20',
                'tabla' => [array_map(static fn(string $col): string => $col . ' (ejemplo 1)', $c['columnas']), array_map(static fn(string $col): string => $col . ' (ejemplo 2)', $c['columnas'])],
                'texto_largo' => '[' . $c['etiqueta'] . ': texto de ejemplo de varias líneas]',
                default => '[' . $c['etiqueta'] . ']',
            };
        }
        return $v;
    }

    // ------------------------------------------------------------------ automáticos (al generar el PDF)

    /** @param array $firmante nombre (con grado), cargo y genero @return array<string,string> */
    public static function automaticos(array $firmante, ?string $fecha): array
    {
        $g = Persona::genero($firmante);
        return [
            '{fecha_letras}' => Oficios::fechaEnLetras($fecha ?: null),
            '{firmante}' => (string) ($firmante['nombre'] ?? ''),
            '{cargo_firmante}' => (string) ($firmante['cargo'] ?? ''),
            '{suscribe}' => $g === 'femenino' ? 'La que suscribe' : ($g === 'masculino' ? 'El que suscribe' : 'Quien suscribe'),
        ];
    }

    /** Sustituye los marcadores automáticos en los bloques del cuerpo (párrafos y celdas). */
    public static function sustituirBloques(array $bloques, array $mapa): array
    {
        foreach ($bloques as &$b) {
            if (($b['t'] ?? '') === 'p') {
                foreach ($b['runs'] as &$r) {
                    $r[0] = strtr((string) $r[0], $mapa);
                }
                unset($r);
            } elseif (($b['t'] ?? '') === 'tabla') {
                foreach ($b['filas'] as &$f) {
                    foreach ($f as &$c) {
                        $c['texto'] = strtr((string) $c['texto'], $mapa);
                    }
                    unset($c);
                }
                unset($f);
            }
        }
        unset($b);
        return $bloques;
    }
}
