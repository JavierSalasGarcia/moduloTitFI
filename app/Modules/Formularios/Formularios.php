<?php
declare(strict_types=1);

namespace App\Modules\Formularios;

use App\Core\Auth;
use App\Core\Database;

/**
 * Formularios temporales (tablas frm_*): un enlace público, sin cuenta en Traza FI, para recabar datos de una sola
 * vez (por ejemplo, el registro de profesores para la clasificación de El Universal). Los campos se definen en JSON:
 * texto, email, select, fecha, confirmar (casilla; «mostrar» la lista en los registros), evidencia (un .zip o un enlace
 * https, uno u otro) y fijo («valor» que se guarda sin mostrarse); «mayusculas» convierte el texto a mayúsculas,
 * «ayuda» es el texto de ejemplo y «de_cuenta» lo llena con el nombre o correo de quien tiene sesión.
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

    /** Tamaño máximo del .zip de un campo evidencia: el del campo, sin pasar el límite de PHP del servidor. */
    public static function maxBytes(array $c): int
    {
        $ini = static function (string $v): int {
            $n = (int) $v;
            return match (strtolower(substr(trim($v), -1))) { 'g' => $n << 30, 'm' => $n << 20, 'k' => $n << 10, default => $n };
        };
        $limites = array_filter([(int) ($c['max_mb'] ?? 25) << 20, $ini((string) ini_get('upload_max_filesize')), $ini((string) ini_get('post_max_size'))]);
        return min($limites);
    }

    /** Nombre del campo de archivo en el formulario para una evidencia. */
    public static function campoArchivo(array $c): string
    {
        return 'ev_' . $c['k'];
    }

    /**
     * Valida lo capturado. Con $evidencias = false se omiten los campos evidencia (al corregir se conservan los del
     * registro). Los .zip solo se revisan aquí; se guardan con guardarEvidencias(). @return array{0:array,1:string[]}
     */
    public static function validar(array $f, array $entrada, bool $evidencias = true): array
    {
        $datos = [];
        $errores = [];
        foreach (self::campos($f) as $c) {
            $k = (string) $c['k'];
            $v = $entrada[$k] ?? '';
            $v = is_string($v) ? trim(preg_replace('/\s+/u', ' ', $v) ?? '') : '';
            $tipo = (string) ($c['tipo'] ?? 'texto');
            if ($tipo === 'evidencia') {
                if ($evidencias) {
                    [$ev, $error] = self::validarEvidencia($c, $entrada);
                    $datos += $ev;
                    if ($error !== '') {
                        $errores[] = $error;
                    }
                }
                continue;
            }
            if ($tipo === 'fijo') {
                $datos[$k] = (string) ($c['valor'] ?? '');
                continue;
            }
            if ($tipo === 'fecha') {
                $t = \DateTime::createFromFormat('!Y-m-d', $v);
                if ($v !== '' && (!$t || $t->format('Y-m-d') !== $v || $v < (string) ($c['min'] ?? '0000') || $v > (string) ($c['max_fecha'] ?? '9999'))) {
                    $errores[] = 'Revisa la fecha de «' . $c['label'] . '».';
                    $v = '';
                }
            } elseif ($tipo === 'confirmar') {
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

    /** @return array{0:array,1:string} [datos de la evidencia, error] */
    private static function validarEvidencia(array $c, array $entrada): array
    {
        $k = (string) $c['k'];
        $modo = (string) ($entrada[$k . '_modo'] ?? '');
        if ($modo === 'enlace') {
            $url = trim((string) ($entrada[$k . '_enlace'] ?? ''));
            if (!preg_match('#^https://[^\s/]+\.[^\s]+$#iD', $url) || !filter_var($url, FILTER_VALIDATE_URL) || strlen($url) > 500) {
                return [[$k . '_modo' => 'enlace', $k => ''], 'Escribe un enlace válido que empiece con https:// en «' . $c['label'] . '».'];
            }
            return [[$k . '_modo' => 'enlace', $k => $url], ''];
        }
        if ($modo === 'archivo') {
            $file = $_FILES[self::campoArchivo($c)] ?? null;
            $error = is_array($file) && !is_array($file['error'] ?? null) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
            $mb = round(self::maxBytes($c) / 1048576);
            return [[$k . '_modo' => 'archivo', $k => ''], match (true) {
                $error === UPLOAD_ERR_NO_FILE => 'Adjunta el archivo .zip de «' . $c['label'] . '».',
                in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true), $error === UPLOAD_ERR_OK && (int) $file['size'] > self::maxBytes($c)
                    => 'El archivo .zip pasa de ' . $mb . ' MB: reduce su tamaño o comparte un enlace a una carpeta.',
                $error !== UPLOAD_ERR_OK => 'No se pudo recibir el archivo (código ' . $error . '). Intenta de nuevo o comparte un enlace.',
                strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION)) !== 'zip' => 'La evidencia debe ser un archivo .zip.',
                default => '',
            }];
        }
        return [[$k . '_modo' => '', $k => ''], !empty($c['req']) ? 'Elige cómo entregas la evidencia: archivo .zip o enlace.' : ''];
    }

    /** Guarda en storage/ los .zip de los campos evidencia ya validados y deja su ruta en los datos. */
    public static function guardarEvidencias(array $f, array $datos): array
    {
        foreach (self::campos($f) as $c) {
            $k = (string) $c['k'];
            if (($c['tipo'] ?? '') === 'evidencia' && ($datos[$k . '_modo'] ?? '') === 'archivo') {
                $file = $_FILES[self::campoArchivo($c)];
                $datos[$k] = guardar_archivo_datos($file, 'formularios/' . $f['slug'], ['zip'], self::maxBytes($c));
                $datos[$k . '_nombre'] = mb_substr(basename((string) $file['name']), 0, 160);
            }
        }
        return $datos;
    }

    /** Borra de storage/ los .zip de evidencia de un registro. */
    public static function borrarEvidencias(array $f, array $datos): void
    {
        $base = rtrim((string) \App\Core\App::config('archivos')['storage'], '/\\');
        foreach (self::campos($f) as $c) {
            $ruta = (string) ($datos[$c['k']] ?? '');
            if (($c['tipo'] ?? '') === 'evidencia' && ($datos[$c['k'] . '_modo'] ?? '') === 'archivo' && str_starts_with($ruta, 'formularios/') && !str_contains($ruta, '..')) {
                @unlink($base . '/' . $ruta);
            }
        }
    }

    /** Evidencia de un registro para mostrarla: ['modo' => archivo|enlace|'', 'texto' => …, 'url' => …]. */
    public static function evidencia(array $f, array $c, array $r): array
    {
        $k = (string) $c['k'];
        $modo = (string) ($r['datos'][$k . '_modo'] ?? '');
        if ($modo === 'enlace') {
            return ['modo' => 'enlace', 'texto' => (string) $r['datos'][$k], 'url' => (string) $r['datos'][$k]];
        }
        if ($modo === 'archivo' && ($r['datos'][$k] ?? '') !== '') {
            return ['modo' => 'archivo', 'texto' => (string) ($r['datos'][$k . '_nombre'] ?? 'evidencia.zip'),
                'url' => url_archivo('formularios/' . (int) $f['id'] . '/respuestas/' . (int) $r['id'] . '/evidencia/' . $k)];
        }
        return ['modo' => '', 'texto' => '', 'url' => ''];
    }

    public static function guardar(array $f, array $datos): int
    {
        $email = strtolower((string) ($datos['email'] ?? ''));
        if (empty($f['varios_por_correo']) && Database::valor('SELECT id FROM frm_respuestas WHERE formulario_id = ? AND email = ?', [(int) $f['id'], $email])) {
            throw new \InvalidArgumentException('Ya hay un registro con el correo ' . $email . '. Si necesitas corregirlo, escribe a la Facultad.');
        }
        return Database::ejecutar('INSERT INTO frm_respuestas (formulario_id, email, datos, usuario_id) VALUES (?, ?, ?, ?)', [(int) $f['id'], $email, json_encode($datos, JSON_UNESCAPED_UNICODE), Auth::id() ?: null]);
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
        $formato = json_decode((string) ($f['excel'] ?? ''), true);
        if (is_array($formato) && $formato) {
            return self::excelConFormato($f, $formato);
        }
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

    /** Texto de una celda: valor del campo (la evidencia como enlace completo; un .zip, con su enlace de descarga). */
    private static function celda(array $f, array $porClave, array $r, string $k): string
    {
        $c = $porClave[$k] ?? ['k' => $k];
        if (($c['tipo'] ?? '') === 'evidencia') {
            $ev = self::evidencia($f, $c, $r);
            return $ev['modo'] === 'archivo' ? rtrim((string) \App\Core\App::config('app.url_publica', ''), '/') . $ev['url'] : $ev['texto'];
        }
        return self::valor($c, (string) ($r['datos'][$k] ?? ''));
    }

    /**
     * Libro con el formato que pide quien solicita la información: una hoja por elemento del formato (registros
     * filtrados por campo), con su título, encabezados y una sección por cada opción del campo de secciones.
     */
    private static function excelConFormato(array $f, array $formato): string
    {
        $porClave = array_column(self::campos($f), null, 'k');
        $respuestas = self::respuestas($f);
        $x = new XlsxEscritor();
        foreach ($formato as $h) {
            $filas = [[(string) $h['titulo']], array_map(static fn(array $col): string => (string) $col[0], $h['columnas'])];
            $negritas = [0, 1];
            $lista = array_values(array_filter($respuestas, static function (array $r) use ($h): bool {
                foreach ((array) ($h['filtro'] ?? []) as $k => $vals) {
                    if (!in_array((string) ($r['datos'][$k] ?? ''), (array) $vals, true)) {
                        return false;
                    }
                }
                return true;
            }));
            $secciones = $h['secciones']['titulos'] ?? ['' => ''];
            $campoSeccion = (string) ($h['secciones']['campo'] ?? '');
            foreach ($secciones as $clave => $tituloSeccion) {
                if ($campoSeccion !== '') {
                    $negritas[] = count($filas);
                    $filas[] = [(string) $tituloSeccion];
                }
                foreach ($lista as $r) {
                    if ($campoSeccion !== '' && (string) ($r['datos'][$campoSeccion] ?? '') !== (string) $clave) {
                        continue;
                    }
                    $filas[] = array_map(static fn(array $col): string => implode(' · ', array_filter(array_map(
                        static fn(string $k): string => self::celda($f, $porClave, $r, $k), (array) $col[1]), 'strlen')), $h['columnas']);
                }
            }
            $x->hoja((string) $h['hoja'], $filas, $negritas);
        }
        return $x->generar();
    }
}
