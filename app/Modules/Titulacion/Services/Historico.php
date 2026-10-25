<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Core\Persona;
use App\Modules\Titulacion\Catalogo;

/**
 * Historial del libro de actas (CONTROL DE LIBROS DE LICENCIATURAS): se importa del Excel, se corrige en el visor y,
 * cuando un registro con trabajo escrito está completo, genera las constancias del director, codirector y sinodales
 * mediante un trámite histórico (concluido, sin cuenta de alumno) para usar la misma cola de firmas, búsqueda y QR.
 */
final class Historico
{
    /** Columnas editables del visor => [etiqueta, tipo]. */
    public const CAMPOS = [
        'acta' => ['Acta', 'numero'], 'alumno' => ['Alumno', 'texto'], 'cuenta' => ['Cuenta', 'texto'], 'carrera' => ['Licenciatura', 'carrera'],
        'modalidad' => ['Modalidad', 'modalidad'], 'fecha_examen' => ['Fecha de evaluación', 'fecha'], 'titulo' => ['Título del trabajo', 'texto'],
        'testimonio' => ['Testimonio EGEL', 'testimonio'], 'vez' => ['Vez', 'vez'], 'fecha_aut_egel' => ['Aut. EGEL', 'fecha'], 'fecha_afinidad' => ['Afinidad CEA', 'fecha'],
        'director' => ['Director', 'texto'], 'director_email' => ['Correo del director', 'email'],
        'codirector' => ['Codirector', 'texto'], 'codirector_email' => ['Correo del codirector', 'email'],
        'presidente' => ['Presidente', 'texto'], 'presidente_email' => ['Correo del presidente', 'email'],
        'secretario' => ['Secretario', 'texto'], 'secretario_email' => ['Correo del secretario', 'email'],
        'vocal' => ['Vocal', 'texto'], 'vocal_email' => ['Correo del vocal', 'email'],
    ];

    /** Claves de modalidad del libro => modalidad del catálogo (EAP e INA se interpretan; se corrigen en el visor). */
    public const CODIGOS = [
        'APRO' => 'aprovechamiento', 'CEA' => 'creditos', 'EAP' => 'autoempleo', 'ENS' => 'ensayo', 'EXA' => 'egel', 'INA' => 'articulo',
        'MEL' => 'memoria', 'RAC' => 'aplicacion', 'RRI' => 'residencia', 'TES' => 'tesis',
    ];

    private const PALABRAS = [
        'EXAMEN GENERAL' => 'egel', 'EGEL' => 'egel', 'APROVECHAMIENTO' => 'aprovechamiento', 'APRO' => 'aprovechamiento', 'CREDITOS' => 'creditos', 'CEA' => 'creditos',
        'TESINA' => 'tesina', 'TESIS' => 'tesis', 'MEMORIA' => 'memoria', 'MEL' => 'memoria', 'APLICACION' => 'aplicacion', 'RAC' => 'aplicacion',
        'RESIDENCIA' => 'residencia', 'RRI' => 'residencia', 'AUTOEMPLEO' => 'autoempleo', 'ENSAYO' => 'ensayo', 'ARTICULO' => 'articulo',
    ];

    public static function conTrabajo(?string $modalidad): bool
    {
        return $modalidad !== null && $modalidad !== '' && (Catalogo::modalidad($modalidad)['tipo'] ?? '') === 'con_trabajo';
    }

    /** Campos que faltan para generar las constancias del registro. @return string[] */
    public static function faltantes(array $h): array
    {
        $req = ['acta', 'alumno', 'cuenta', 'carrera', 'modalidad', 'fecha_examen'];
        if (self::conTrabajo($h['modalidad'] ?? null)) {
            array_push($req, 'titulo', 'director', 'director_email', 'presidente', 'presidente_email', 'secretario', 'secretario_email', 'vocal', 'vocal_email');
            if (trim((string) $h['codirector']) !== '') {
                $req[] = 'codirector_email';
            }
        } elseif (($h['modalidad'] ?? '') === 'egel') {
            $req[] = 'testimonio';
        }
        $f = [];
        foreach ($req as $k) {
            $v = trim((string) ($h[$k] ?? ''));
            if ($v === '' || (str_ends_with($k, '_email') && !filter_var($v, FILTER_VALIDATE_EMAIL))) {
                $f[] = $k;
            }
        }
        return $f;
    }

    // ------------------------------------------------------------------ importación

    private static function norma(string $s): string
    {
        $s = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $s) ?? ''));
        return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);
    }

    private static function carreraDeHoja(string $hoja): ?string
    {
        $h = self::norma($hoja);
        return match (true) {
            str_contains($h, 'APLAZ') => null,
            str_contains($h, 'CIVIL') => 'civil', str_contains($h, 'MECANICA') => 'mecanica', str_contains($h, 'COMPU') => 'computacion',
            str_contains($h, 'ELECTRONICA') => 'electronica', str_contains($h, 'ISES') || str_contains($h, 'ENERGETIC') => 'sistemas',
            str_contains($h, 'INTELIGENCIA') || preg_match('/\bIIA\b|\bIA\b/', $h) === 1 => 'ia',
            default => null,
        };
    }

    private static function fecha(mixed $v): ?string
    {
        if (is_float($v) || is_int($v)) {
            return $v > 20000 && $v < 80000 ? Xlsx::fechaSerial((float) $v) : null;
        }
        $v = trim((string) $v);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return $v;
        }
        if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{2,4})$#', $v, $m)) {
            $a = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
            return checkdate((int) $m[2], (int) $m[1], $a) ? sprintf('%04d-%02d-%02d', $a, (int) $m[2], (int) $m[1]) : null;
        }
        return null;
    }

    private static function marcada(mixed $v): bool
    {
        return is_string($v) && preg_match('/^\s*x\s*$/i', $v) === 1;
    }

    private static function modalidadDeTexto(string $v): ?string
    {
        $n = self::norma($v);
        foreach (self::PALABRAS as $p => $m) {
            if (preg_match('/\b' . preg_quote($p, '/') . '\b/u', $n)) {
                return $m;
            }
        }
        return null;
    }

    /** ¿Parece nombre de persona (y no modalidad, testimonio o marca)? */
    private static function esNombre(mixed $v): bool
    {
        if (!is_string($v)) {
            return false;
        }
        $n = self::norma($v);
        return mb_strlen($n) >= 8 && substr_count($n, ' ') >= 1 && preg_match('/^[A-ZÑ .,]+$/u', $n) === 1
            && self::modalidadDeTexto($n) === null && !preg_match('/SATISFACTORIO|SOBRESALIENTE|NO APLICA|PENDIENTE/', $n);
    }

    private static function limpiarNombre(string $n): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace("\u{a0}", ' ', $n)) ?? '');
    }

    /**
     * Importa un libro de actas. Solo agrega filas nuevas (hoja y fila): lo ya importado y corregido no se toca.
     * @return array{nuevos:int,existentes:int,hojas:string[]}
     */
    public static function importar(string $archivo, int $usuarioId): array
    {
        $x = new Xlsx($archivo);
        $nuevos = 0;
        $existentes = 0;
        $hojas = [];
        $correosConocidos = self::correosConocidos();
        foreach ($x->hojas() as $hoja) {
            $carrera = self::carreraDeHoja($hoja);
            if ($carrera === null) {
                continue;
            }
            $mapa = null;
            foreach ($x->filas($hoja, 40) as $num => $fila) {
                if ($mapa === null) {
                    $mapa = self::encabezados($fila);
                    continue;
                }
                $h = self::fila($fila, $mapa, $carrera, $correosConocidos);
                if ($h === null) {
                    continue;
                }
                $h['hoja'] = mb_substr($hoja, 0, 60);
                $h['fila'] = $num;
                $cols = array_keys($h);
                $ok = Database::ejecutar(
                    'INSERT IGNORE INTO tit_historico (' . implode(', ', $cols) . ', actualizado_por, actualizado_en) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ', ?, NOW())',
                    [...array_values($h), $usuarioId]
                );
                $ok > 0 ? $nuevos++ : $existentes++;
            }
            $hojas[] = $hoja;
        }
        return ['nuevos' => $nuevos, 'existentes' => $existentes, 'hojas' => $hojas];
    }

    /** Fila de encabezados (la que dice «Nombre del alumno»): índice de columna por campo. null mientras no aparezca. */
    private static function encabezados(array $fila): ?array
    {
        $n = array_map(static fn($v): string => is_string($v) ? self::norma($v) : '', $fila);
        if (!array_filter($n, static fn(string $v): bool => str_contains($v, 'NOMBRE DEL ALUMNO'))) {
            return null;
        }
        $m = ['codigos' => [], 'sinodo' => []];
        foreach ($n as $i => $v) {
            match (true) {
                $v === '' => null,
                str_contains($v, 'ACTA') => $m['acta'] = $i,
                str_contains($v, 'NOMBRE DEL ALUMNO') => $m['alumno'] = $i,
                str_contains($v, 'CUENTA') => $m['cuenta'] = $i,
                isset(self::CODIGOS[$v]) => $m['codigos'][$i] = $v,
                str_contains($v, 'AUT') && str_contains($v, 'EGEL') => $m['aut_egel'] = $i,
                str_contains($v, 'PRIMERA') => $m['primera'] = $i,
                str_contains($v, 'SEGUNDA') => $m['segunda'] = $i,
                $v === 'SATISFACTORIO' => $m['satisfactorio'] = $i,
                $v === 'SOBRESALIENTE' => $m['sobresaliente'] = $i,
                str_contains($v, 'AFINIDAD') => $m['afinidad'] = $i,
                str_starts_with($v, 'FECHA') => $m['fecha'] = $i,
                str_contains($v, 'DIRECTOR') => $m['director'] = $i,
                str_contains($v, 'PRESIDENTE') => $m['presidente'] = $i,
                str_contains($v, 'SECRETARIO') => $m['secretario'] = $i,
                str_contains($v, 'VOCAL') => $m['vocal'] = $i,
                str_contains($v, 'SINODO') => $m['sinodo'][] = $i,
                str_contains($v, 'CORREO') => $m['correos'] = $i,
                default => null,
            };
        }
        // «Sinodo» sin presidente/secretario/vocal: esa columna y las vacías que le siguen (hasta tres nombres).
        if (!isset($m['presidente']) && $m['sinodo']) {
            $i = $m['sinodo'][0];
            $m['sinodo'] = [$i];
            for ($k = $i + 1; $k <= $i + 2 && ($n[$k] ?? '') === ''; $k++) {
                $m['sinodo'][] = $k;
            }
        } else {
            $m['sinodo'] = [];
        }
        return isset($m['alumno']) ? $m : null;
    }

    private static function fila(array $f, array $m, string $carrera, array $correosConocidos): ?array
    {
        $alumno = self::limpiarNombre((string) ($f[$m['alumno']] ?? ''));
        if (mb_strlen($alumno) < 5) {
            return null;
        }
        $acta = isset($m['acta']) && is_numeric($f[$m['acta']] ?? null) ? (int) $f[$m['acta']] : null;
        $cuenta = preg_replace('/\D/', '', (string) (is_float($f[$m['cuenta'] ?? -1] ?? null) ? (string) (int) $f[$m['cuenta']] : ($f[$m['cuenta'] ?? -1] ?? ''))) ?? '';
        if ($cuenta !== '' && strlen($cuenta) < 7) {
            $cuenta = str_pad($cuenta, 7, '0', STR_PAD_LEFT);
        }
        $h = ['carrera' => $carrera, 'acta' => $acta, 'alumno' => mb_substr($alumno, 0, 160), 'cuenta' => mb_substr($cuenta, 0, 12),
            'modalidad' => null, 'modalidad_origen' => '', 'vez' => '', 'testimonio' => '', 'fecha_aut_egel' => null, 'fecha_examen' => null, 'fecha_afinidad' => null,
            'director' => '', 'director_email' => '', 'presidente' => '', 'presidente_email' => '', 'secretario' => '', 'secretario_email' => '', 'vocal' => '', 'vocal_email' => '', 'correos' => ''];
        foreach ($m['codigos'] as $i => $codigo) {
            if (self::marcada($f[$i] ?? null)) {
                $h['modalidad'] = self::CODIGOS[$codigo];
                $h['modalidad_origen'] = $codigo;
            }
        }
        // Filas recientes: la modalidad o el testimonio vienen escritos en otras columnas.
        $usadas = [];
        foreach ($f as $i => $v) {
            if (!is_string($v) || $i === $m['alumno']) {
                continue;
            }
            if ($h['modalidad'] === null && ($mod = self::modalidadDeTexto($v)) !== null && !self::marcada($v)) {
                $h['modalidad'] = $mod;
                $h['modalidad_origen'] = mb_substr($v, 0, 60);
                $usadas[$i] = true;
            } elseif (preg_match('/SATISFACTORIO|SOBRESALIENTE/u', self::norma($v), $t)) {
                $h['testimonio'] = $t[0] === 'SOBRESALIENTE' ? 'sobresaliente' : 'satisfactorio';
                $usadas[$i] = true;
            }
        }
        if (isset($m['sobresaliente']) && self::marcada($f[$m['sobresaliente']] ?? null)) {
            $h['testimonio'] = 'sobresaliente';
        } elseif (isset($m['satisfactorio']) && self::marcada($f[$m['satisfactorio']] ?? null)) {
            $h['testimonio'] = 'satisfactorio';
        }
        $h['vez'] = isset($m['segunda']) && self::marcada($f[$m['segunda']] ?? null) ? 'segunda' : (isset($m['primera']) && self::marcada($f[$m['primera']] ?? null) ? 'primera' : '');
        $h['fecha_aut_egel'] = isset($m['aut_egel']) ? self::fecha($f[$m['aut_egel']] ?? null) : null;
        $h['fecha_afinidad'] = isset($m['afinidad']) ? self::fecha($f[$m['afinidad']] ?? null) : null;
        $h['fecha_examen'] = isset($m['fecha']) ? self::fecha($f[$m['fecha']] ?? null) : null;
        if ($h['fecha_examen'] === null) {
            foreach ($f as $i => $v) {
                if ($i > ($m['cuenta'] ?? 0) && $i !== ($m['aut_egel'] ?? -1) && ($d = self::fecha($v)) !== null && is_string($v)) {
                    $h['fecha_examen'] = $d;
                    break;
                }
            }
        }
        // Director y sínodo, solo en opciones con trabajo escrito (en las demás la comisión es Director y Subdirector).
        if (self::conTrabajo($h['modalidad'])) {
            $nombre = static fn(?int $i): string => $i !== null && !isset($usadas[$i]) && self::esNombre($f[$i] ?? null) ? self::limpiarNombre((string) $f[$i]) : '';
            $h['director'] = $nombre($m['director'] ?? null);
            $sinodo = isset($m['presidente']) ? [$m['presidente'] ?? null, $m['secretario'] ?? null, $m['vocal'] ?? null] : $m['sinodo'];
            $nombres = [];
            foreach ($sinodo as $i) {
                $v = $i !== null ? (string) ($f[$i] ?? '') : '';
                foreach (preg_split('/\s*[,;\/\n]\s*|\s+-\s+/u', $v) ?: [] as $parte) {
                    if (self::esNombre($parte)) {
                        $nombres[] = self::limpiarNombre($parte);
                    }
                }
            }
            foreach (['presidente', 'secretario', 'vocal'] as $k => $rol) {
                $h[$rol] = mb_substr($nombres[$k] ?? '', 0, 160);
            }
            $correos = isset($m['correos']) ? (string) ($f[$m['correos']] ?? '') : '';
            $h['correos'] = mb_substr($correos, 0, 500);
            preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $correos, $em);
            foreach (['director', 'presidente', 'secretario', 'vocal'] as $rol) {
                $h[$rol . '_email'] = $h[$rol] !== '' ? self::correoDe($h[$rol], array_map('strtolower', $em[0]), $correosConocidos) : '';
            }
        }
        return $h;
    }

    /** Correos de profesores registrados en la plataforma, por nombre normalizado (sin grado ni acentos). */
    private static function correosConocidos(): array
    {
        $r = [];
        foreach (Database::todas("SELECT nombre, email FROM usuarios WHERE activo = 1 AND rol <> 'alumno'") as $u) {
            $r[self::clave((string) $u['nombre'])] = strtolower((string) $u['email']);
        }
        return $r;
    }

    /** Nombre sin grado, acentos ni signos, con las palabras ordenadas (sirve igual «Apellidos Nombre» que «Nombre Apellidos»). */
    public static function clave(string $nombre): string
    {
        $n = self::norma(strtr($nombre, ['ñ' => 'n', 'Ñ' => 'N']));
        $n = preg_replace('/^((DR|DRA|MTRO|MTRA|ING|LIC|ARQ|PHD|M|C|EN|I|A|MC|MI|MA)\.?\s+)+/u', '', $n) ?? $n;
        $p = array_filter(preg_split('/[^A-Z]+/', $n) ?: [], static fn(string $w): bool => strlen($w) > 2 && !in_array($w, ['DEL', 'LAS', 'LOS'], true));
        sort($p);
        return implode(' ', $p);
    }

    /** Correo de la persona: el registrado con su nombre o, si no, el de la columna de correos cuyo usuario contenga su apellido. */
    private static function correoDe(string $nombre, array $correosFila, array $conocidos): string
    {
        $k = self::clave($nombre);
        if (isset($conocidos[$k])) {
            return $conocidos[$k];
        }
        $palabras = explode(' ', $k);
        foreach ($correosFila as $e) {
            $usuario = strtoupper(strstr($e, '@', true) ?: '');
            $coinciden = count(array_filter($palabras, static fn(string $w): bool => strlen($w) > 3 && str_contains($usuario, $w)));
            if ($coinciden >= 1) {
                return $e;
            }
        }
        return '';
    }

    // ------------------------------------------------------------------ visor

    /** @param array{q?:string,carrera?:string,faltantes?:string,estado?:string} $f */
    public static function listar(array $f, int $limite = 200, int $desde = 0): array
    {
        $donde = ['1 = 1'];
        $p = [];
        if (($f['carrera'] ?? '') !== '') {
            $donde[] = 'carrera = ?';
            $p[] = $f['carrera'];
        }
        if (($f['q'] ?? '') !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $f['q']) . '%';
            $donde[] = '(alumno LIKE ? OR cuenta LIKE ? OR CAST(acta AS CHAR) = ? OR director LIKE ? OR presidente LIKE ? OR secretario LIKE ? OR vocal LIKE ? OR titulo LIKE ?)';
            array_push($p, $like, $like, (string) $f['q'], $like, $like, $like, $like, $like);
        }
        if (($f['estado'] ?? '') === 'generadas') {
            $donde[] = 'tramite_id IS NOT NULL';
        } elseif (($f['estado'] ?? '') === 'pendientes') {
            $donde[] = 'tramite_id IS NULL';
        }
        $filas = Database::todas('SELECT * FROM tit_historico WHERE ' . implode(' AND ', $donde) . ' ORDER BY carrera, acta DESC, id DESC', $p);
        $campo = (string) ($f['faltantes'] ?? '');
        $salida = [];
        foreach ($filas as $h) {
            $h['faltan'] = self::faltantes($h);
            if ($campo === 'cualquiera' && !$h['faltan']) {
                continue;
            }
            if ($campo === 'completos' && $h['faltan']) {
                continue;
            }
            if (isset(self::CAMPOS[$campo]) && !in_array($campo, $h['faltan'], true)) {
                continue;
            }
            $salida[] = $h;
        }
        return ['total' => count($salida), 'filas' => array_slice($salida, $desde, $limite)];
    }

    /** Guarda un campo editado en el visor. @return array la fila actualizada con sus faltantes */
    public static function guardarCampo(int $id, string $campo, string $valor, int $usuarioId): array
    {
        if (!isset(self::CAMPOS[$campo])) {
            throw new \InvalidArgumentException('Campo no editable.');
        }
        $valor = trim($valor);
        $tipo = self::CAMPOS[$campo][1];
        $valor = match ($tipo) {
            'numero' => $valor === '' ? null : (ctype_digit($valor) ? (int) $valor : throw new \InvalidArgumentException('El acta debe ser un número.')),
            'fecha' => $valor === '' ? null : (self::fecha($valor) ?? throw new \InvalidArgumentException('Fecha no válida.')),
            'email' => $valor === '' || filter_var($valor, FILTER_VALIDATE_EMAIL) ? strtolower($valor) : throw new \InvalidArgumentException('Correo no válido.'),
            'carrera' => isset(Catalogo::carreras()[$valor]) ? $valor : throw new \InvalidArgumentException('Licenciatura no válida.'),
            'modalidad' => $valor === '' ? null : (Catalogo::modalidad($valor) ? $valor : throw new \InvalidArgumentException('Modalidad no válida.')),
            'testimonio' => in_array($valor, ['', 'satisfactorio', 'sobresaliente'], true) ? $valor : throw new \InvalidArgumentException('Testimonio no válido.'),
            'vez' => in_array($valor, ['', 'primera', 'segunda'], true) ? $valor : throw new \InvalidArgumentException('Valor no válido.'),
            default => mb_substr($valor, 0, $campo === 'titulo' ? 500 : 160),
        };
        if ($campo === 'acta' && $valor !== null) {
            $h = Database::una('SELECT carrera FROM tit_historico WHERE id = ?', [$id]);
            $otro = $h ? Database::valor('SELECT alumno FROM tit_historico WHERE carrera = ? AND acta = ? AND id <> ?', [$h['carrera'], $valor, $id]) : null;
            $plat = $h ? Database::valor("SELECT t.folio FROM tit_actas a JOIN tit_tramites t ON t.id = a.tramite_id WHERE a.carrera = ? AND a.tipo = 'evaluacion' AND a.numero = ? AND t.historico = 0", [$h['carrera'], $valor]) : null;
            if ($otro || $plat) {
                throw new \InvalidArgumentException('El acta ' . $valor . ' ya está registrada para ' . ($otro ?: 'el trámite ' . $plat) . '.');
            }
        }
        Database::ejecutar("UPDATE tit_historico SET `$campo` = ?, actualizado_por = ?, actualizado_en = NOW() WHERE id = ?", [$valor, $usuarioId, $id]);
        $fila = Database::una('SELECT * FROM tit_historico WHERE id = ?', [$id]) ?? throw new \InvalidArgumentException('Registro inexistente.');
        $fila['faltan'] = self::faltantes($fila);
        return $fila;
    }

    // ------------------------------------------------------------------ constancias

    private static function persona(string $nombre, string $email): array
    {
        $n = self::norma($nombre);
        $genero = preg_match('/^(DRA|MTRA|LIC\.?\s*A)\b/', $n) ? 'femenino' : (preg_match('/^(DR|MTRO)\b/', $n) ? 'masculino' : null);
        $p = Persona::completar(['nombre' => $nombre, 'email' => $email], $email);
        $p['genero'] ??= $genero;
        return $p;
    }

    /**
     * Genera (o completa) las constancias de un registro completo con trabajo escrito. Devuelve cuántas se generaron.
     */
    public static function generar(int $id, int $usuarioId): int
    {
        $h = Database::una('SELECT * FROM tit_historico WHERE id = ?', [$id]);
        if (!$h) {
            throw new \InvalidArgumentException('Registro inexistente.');
        }
        if (!self::conTrabajo($h['modalidad'])) {
            throw new \InvalidArgumentException('Las opciones sin trabajo escrito no generan constancias para profesores.');
        }
        if ($faltan = self::faltantes($h)) {
            throw new \InvalidArgumentException('Faltan datos: ' . implode(', ', array_map(static fn(string $k): string => self::CAMPOS[$k][0], $faltan)) . '.');
        }
        $tramite = self::tramite($h, $usuarioId);
        $fecha = (string) $h['fecha_examen'];
        $detalle = ['desde' => $fecha, 'hasta' => $fecha, 'acta' => (string) $h['acta']];
        $n = 0;
        foreach (['asesor_trabajo', 'cumplimiento_director', 'nombramiento_director'] as $tipo) {
            $n += (int) Constancias::emitir($tramite, $tipo, self::persona($h['director'], $h['director_email']), $detalle, $usuarioId);
        }
        if (trim($h['codirector']) !== '') {
            $n += (int) Constancias::emitir($tramite, 'coasesor_trabajo', self::persona($h['codirector'], $h['codirector_email']), $detalle, $usuarioId);
        }
        foreach (['presidente', 'secretario', 'vocal'] as $rol) {
            $n += (int) Constancias::emitir($tramite, 'sinodal', self::persona($h[$rol], $h[$rol . '_email']), $detalle + ['funcion' => $rol], $usuarioId);
        }
        Database::ejecutar('UPDATE tit_historico SET generado_en = NOW() WHERE id = ?', [$id]);
        return $n;
    }

    /** Trámite histórico del registro (se crea la primera vez y se actualiza con lo corregido). */
    private static function tramite(array $h, int $usuarioId): array
    {
        $carrera = (string) $h['carrera'];
        $datos = [
            'datos' => ['nombre' => $h['alumno'], 'cuenta' => $h['cuenta'], 'carrera' => $carrera, 'sistema' => 'fi'],
            'protocolo' => ['titulo' => $h['titulo'], 'asesor' => $h['director'], 'asesor_correo' => $h['director_email'], 'coasesor' => $h['codirector'], 'coasesor_correo' => $h['codirector_email']],
            'historico' => ['id' => (int) $h['id'], 'acta' => (int) $h['acta'], 'hoja' => $h['hoja'], 'fila' => (int) $h['fila']],
        ];
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
        $tid = (int) ($h['tramite_id'] ?? 0);
        if ($tid > 0) {
            Database::ejecutar('UPDATE tit_tramites SET modalidad = ?, titulo = ?, datos = ? WHERE id = ?', [$h['modalidad'], $h['titulo'], $json, $tid]);
        } else {
            $siglas = Catalogo::carreras()[$carrera]['siglas'][0] ?? 'FI';
            $folio = 'HIS-' . $siglas . '-' . $h['acta'];
            if (Database::valor('SELECT id FROM tit_tramites WHERE folio = ?', [$folio])) {
                $folio .= '-' . $h['id'];
            }
            $tid = Database::ejecutar(
                "INSERT INTO tit_tramites (folio, nivel, alumno_id, tipo, modalidad, estado, etapa_actual, titulo, datos, resultado, responsable_id, historico)
                 VALUES (?, 'licenciatura', NULL, 'con_trabajo', ?, 'concluido', 'resultado', ?, ?, 'aprobado', ?, 1)",
                [mb_substr($folio, 0, 20), $h['modalidad'], $h['titulo'], $json, $usuarioId]
            );
            Database::ejecutar('UPDATE tit_historico SET tramite_id = ? WHERE id = ?', [$tid, (int) $h['id']]);
            Database::ejecutar("INSERT IGNORE INTO tit_actas (tramite_id, carrera, tipo, numero, fecha) VALUES (?, ?, 'evaluacion', ?, ?)", [$tid, $carrera, (int) $h['acta'], $h['fecha_examen']]);
        }
        return \App\Modules\Titulacion\TitulacionRepository::obtener($tid) ?? throw new \RuntimeException('No se pudo crear el trámite histórico.');
    }
}
