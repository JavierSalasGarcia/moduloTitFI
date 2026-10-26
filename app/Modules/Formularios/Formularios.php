<?php
declare(strict_types=1);

namespace App\Modules\Formularios;

use App\Core\Auth;
use App\Core\Database;

/**
 * Formularios temporales (tablas frm_*): un enlace público, sin cuenta en Traza FI, para recabar datos de una sola
 * vez (por ejemplo, el registro de profesores para la clasificación de El Universal). Los campos se definen en JSON:
 * texto, email, select y confirmar (casilla obligatoria); «mayusculas» convierte el texto a mayúsculas.
 */
final class Formularios
{
    public static function porSlug(string $slug): ?array
    {
        return preg_match('/^[a-z0-9-]{3,80}$/D', $slug) ? Database::una('SELECT * FROM frm_formularios WHERE slug = ?', [$slug]) : null;
    }

    public static function porId(int $id): ?array
    {
        return Database::una('SELECT * FROM frm_formularios WHERE id = ?', [$id]);
    }

    /** @return array<int,array> */
    public static function campos(array $f): array
    {
        $c = json_decode((string) $f['campos'], true);
        return is_array($c) ? $c : [];
    }

    public static function abierto(array $f): bool
    {
        return $f['estado'] === 'abierto' && ($f['cierra_en'] === null || strtotime((string) $f['cierra_en']) > time());
    }

    /** Autoridades (tomadas de Organización) que pueden consultar y corregir un formulario. */
    public const AUTORIDADES = ['subdirector' => 'Subdirector(a) Académico(a)', 'coordinadores_docencia' => 'Coordinadores de Docencia de las licenciaturas'];

    /** @return string[] claves de AUTORIDADES con acceso al formulario */
    public static function autoridades(array $f): array
    {
        return array_values(array_intersect(array_map('trim', explode(',', (string) ($f['acceso'] ?? ''))), array_keys(self::AUTORIDADES)));
    }

    private static function esAutoridad(string $clave, string $email): bool
    {
        try {
            if ($clave === 'subdirector') {
                return strtolower((string) (\App\Modules\Titulacion\Services\Autoridades::subdirector()['email'] ?? '')) === $email;
            }
            if ($clave === 'coordinadores_docencia') {
                foreach (array_keys(\App\Modules\Titulacion\Catalogo::carreras()) as $carrera) {
                    if (strtolower((string) (\App\Modules\Titulacion\Services\Autoridades::coordinadorDocencia($carrera)['email'] ?? '')) === $email) {
                        return true;
                    }
                }
            }
        } catch (\Throwable) {
        }
        return false;
    }

    /**
     * Consultan, descargan y corrigen: la administración, las autoridades con acceso (Subdirección, Coordinadores de
     * Docencia, según el formulario) y las personas responsables por correo. Eliminar y ajustar: solo administración.
     */
    public static function puedeVer(array $f): bool
    {
        if (Auth::tieneRol('admin')) {
            return true;
        }
        $email = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        if ($email === '') {
            return false;
        }
        foreach (self::autoridades($f) as $a) {
            if (self::esAutoridad($a, $email)) {
                return true;
            }
        }
        $resp = array_filter(array_map('trim', preg_split('/[\s,;]+/', strtolower((string) $f['responsables'])) ?: []));
        return in_array($email, $resp, true);
    }

    public static function puedeAdministrar(): bool
    {
        return Auth::tieneRol('admin');
    }

    public static function puedeAlguno(): bool
    {
        if (Auth::tieneRol('admin')) {
            return true;
        }
        foreach (Database::todas("SELECT * FROM frm_formularios WHERE estado <> 'borrador' OR (responsables IS NOT NULL AND responsables <> '')") as $f) {
            if (self::puedeVer($f)) {
                return true;
            }
        }
        return false;
    }

    private static function mayus(string $s): string
    {
        return mb_strtoupper($s, 'UTF-8');
    }

    /**
     * Valida lo capturado. @return array{0:array,1:string[]} [datos limpios, errores]
     */
    public static function validar(array $f, array $entrada): array
    {
        $datos = [];
        $errores = [];
        foreach (self::campos($f) as $c) {
            $k = (string) $c['k'];
            $v = $entrada[$k] ?? '';
            $v = is_string($v) ? trim(preg_replace('/\s+/u', ' ', $v) ?? '') : '';
            $tipo = (string) ($c['tipo'] ?? 'texto');
            if ($tipo === 'confirmar') {
                $v = $v !== '' ? 'SÍ' : '';
            } elseif ($tipo === 'select') {
                $v = array_key_exists($v, $c['opciones'] ?? []) ? $v : '';
            } elseif ($tipo === 'email') {
                $v = strtolower($v);
                if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) {
                    $errores[] = 'Escribe un correo válido en «' . $c['label'] . '».';
                }
            } else {
                $v = mb_substr($v, 0, (int) ($c['max'] ?? 200));
                if (!empty($c['mayusculas'])) {
                    $v = self::mayus($v);
                }
            }
            if (!empty($c['req']) && $v === '') {
                $errores[] = $tipo === 'confirmar' ? 'Confirma: «' . $c['label'] . '»' : 'Falta «' . $c['label'] . '».';
            }
            $datos[$k] = $v;
        }
        return [$datos, $errores];
    }

    public static function guardar(array $f, array $datos): int
    {
        $email = strtolower((string) ($datos['email'] ?? ''));
        if (Database::valor('SELECT id FROM frm_respuestas WHERE formulario_id = ? AND email = ?', [(int) $f['id'], $email])) {
            throw new \InvalidArgumentException('Ya hay un registro con el correo ' . $email . '. Si necesitas corregirlo, escribe a la Facultad.');
        }
        return Database::ejecutar('INSERT INTO frm_respuestas (formulario_id, email, datos) VALUES (?, ?, ?)', [(int) $f['id'], $email, json_encode($datos, JSON_UNESCAPED_UNICODE)]);
    }

    public static function respuestas(array $f): array
    {
        $r = Database::todas('SELECT * FROM frm_respuestas WHERE formulario_id = ? ORDER BY id', [(int) $f['id']]);
        foreach ($r as &$x) {
            $x['datos'] = json_decode((string) $x['datos'], true) ?: [];
        }
        return $r;
    }

    /** Texto visible de un valor (la etiqueta de la opción en los select). */
    public static function valor(array $c, string $v): string
    {
        return ($c['tipo'] ?? '') === 'select' ? (string) ($c['opciones'][$v] ?? $v) : $v;
    }

    /**
     * Libro de Excel con una hoja por grupo (agrupar_por), con las columnas del formulario (sin las confirmaciones).
     */
    public static function excel(array $f): string
    {
        $campos = array_values(array_filter(self::campos($f), static fn(array $c): bool => ($c['tipo'] ?? '') !== 'confirmar' && $c['k'] !== $f['agrupar_por']));
        $grupo = null;
        foreach (self::campos($f) as $c) {
            if ($c['k'] === $f['agrupar_por']) {
                $grupo = $c;
            }
        }
        $hojas = [];
        if ($grupo !== null) {
            foreach ($grupo['opciones'] ?? [] as $clave => $nombre) {
                $hojas[$clave] = ['nombre' => $nombre, 'filas' => []];
            }
        } else {
            $hojas[''] = ['nombre' => $f['titulo'], 'filas' => []];
        }
        foreach (self::respuestas($f) as $r) {
            $g = $grupo !== null ? (string) ($r['datos'][$grupo['k']] ?? '') : '';
            $hojas[$g] ??= ['nombre' => $g !== '' ? $g : 'Sin grupo', 'filas' => []];
            $fila = [count($hojas[$g]['filas']) + 1];
            foreach ($campos as $c) {
                $fila[] = self::valor($c, (string) ($r['datos'][$c['k']] ?? ''));
            }
            $fila[] = (string) $r['creado_en'];
            $hojas[$g]['filas'][] = $fila;
        }
        $encabezado = array_merge(['#'], array_map(static fn(array $c): string => (string) $c['label'], $campos), ['Registrado el']);
        $x = new XlsxEscritor();
        foreach ($hojas as $h) {
            // El nombre de la hoja admite 31 caracteres: sin «LICENCIATURA EN»; el título completo va en la primera fila.
            $x->hoja(trim((string) preg_replace('/^LICENCIATURA EN\s+/u', '', (string) $h['nombre'])), array_merge([[$h['nombre']], $encabezado], $h['filas']));
        }
        return $x->generar();
    }
}
