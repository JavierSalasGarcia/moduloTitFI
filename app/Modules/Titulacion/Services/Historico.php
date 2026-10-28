<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Core\Persona;
use App\Core\Profesores;
use App\Modules\Titulacion\Catalogo;

/**
 * Historial del libro de actas: los registros anteriores a la plataforma (cargados una sola vez por la migración desde
 * el CONTROL DE LIBROS DE LICENCIATURAS, más los que el personal agregue a mano) se completan en el visor y, cuando
 * un registro con trabajo escrito está completo, genera las constancias del director, codirector y sinodales mediante
 * un trámite histórico (concluido, sin cuenta de alumno) para usar la misma cola de firmas, búsqueda y QR.
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

    public static function conTrabajo(?string $modalidad): bool
    {
        return $modalidad !== null && $modalidad !== '' && (Catalogo::modalidad($modalidad)['tipo'] ?? '') === 'con_trabajo';
    }

    /** Profesores del registro que reciben constancia: rol => nombre (director, codirector y los tres sinodales del acta; no suplentes). @return array<string,string> */
    public static function profesores(array $h): array
    {
        $p = [];
        foreach (['director', 'codirector', 'presidente', 'secretario', 'vocal'] as $rol) {
            if (trim((string) ($h[$rol] ?? '')) !== '') {
                $p[$rol] = trim((string) $h[$rol]);
            }
        }
        return $p;
    }

    /**
     * ¿El registro puede dar constancias a profesores? Solo si se tituló con trabajo escrito: quien lo hizo por EGEL, aprovechamiento o
     * créditos no genera constancias. Sin la opción capturada se considera con trabajo si ya tiene título o profesores (o si lo creó el personal).
     */
    public static function aplica(array $h): bool
    {
        $m = (string) ($h['modalidad'] ?? '');
        if ($m !== '') {
            return self::conTrabajo($m);
        }
        return ($h['hoja'] ?? '') === 'Plataforma' || trim((string) ($h['titulo'] ?? '')) !== '' || self::profesores($h) !== [];
    }

    /**
     * Lo único que hace falta para las constancias: fecha del examen, nombre del alumno, título del trabajo y al menos un profesor
     * (el correo y demás datos no se piden; el correo se busca solo en el directorio por el nombre). @return string[]
     */
    public static function faltantes(array $h): array
    {
        $f = [];
        foreach (['alumno', 'fecha_examen', 'titulo'] as $k) {
            if (trim((string) ($h[$k] ?? '')) === '') {
                $f[] = $k;
            }
        }
        if (!self::profesores($h)) {
            $f[] = 'director';
        }
        return $f;
    }

    // ------------------------------------------------------------------ utilidades

    private static function norma(string $s): string
    {
        $s = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $s) ?? ''));
        return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);
    }

    private static function fecha(mixed $v): ?string
    {
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

    // ------------------------------------------------------------------ visor

    /** @param array{q?:string,carrera?:string,faltantes?:string,estado?:string} $f */
    /** Fechas del registro que no pueden ser correctas: anteriores a 2000 o posteriores a hoy (errores de captura del libro). @return string[] */
    public static function fueraDeRango(array $h): array
    {
        $hoy = date('Y-m-d');
        return array_values(array_filter(['fecha_examen', 'fecha_aut_egel', 'fecha_afinidad'],
            static fn(string $k): bool => !empty($h[$k]) && ((string) $h[$k] < '2000-01-01' || (string) $h[$k] > $hoy)));
    }

    public static function listar(array $f, int $limite = 200, int $desde = 0): array
    {
        // Solo se muestran los registros que pueden dar constancias: se excluyen EGEL, aprovechamiento y créditos.
        $sin = array_keys(array_filter(Catalogo::modalidades(), static fn(array $m): bool => $m['tipo'] !== 'con_trabajo'));
        $donde = [
            '(modalidad IS NULL OR modalidad NOT IN (' . implode(',', array_fill(0, count($sin), '?')) . '))',
            "(COALESCE(modalidad, '') <> '' OR hoja = 'Plataforma' OR titulo <> '' OR director <> '' OR codirector <> '' OR presidente <> '' OR secretario <> '' OR vocal <> '')",
        ];
        $p = $sin;
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
            $h['fuera'] = self::fueraDeRango($h);
            $h['aplica'] = self::aplica($h);
            if ($campo === 'fuera_rango' && !$h['fuera']) {
                continue;
            }
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

    /** Registro nuevo capturado por el personal (los del libro ya se cargaron). @return int id */
    public static function crear(string $carrera, string $alumno, ?int $acta, int $usuarioId): int
    {
        if (!isset(Catalogo::carreras()[$carrera]) || mb_strlen(trim($alumno)) < 5) {
            throw new \InvalidArgumentException('Indica la licenciatura y el nombre completo del alumno.');
        }
        if ($acta !== null && Database::valor('SELECT id FROM tit_historico WHERE carrera = ? AND acta = ?', [$carrera, $acta])) {
            throw new \InvalidArgumentException('El acta ' . $acta . ' ya está en el historial de la licenciatura.');
        }
        $fila = (int) Database::valor("SELECT COALESCE(MAX(fila), 0) + 1 FROM tit_historico WHERE hoja = 'Plataforma'");
        return Database::ejecutar(
            "INSERT INTO tit_historico (hoja, fila, carrera, acta, alumno, actualizado_por, actualizado_en) VALUES ('Plataforma', ?, ?, ?, ?, ?, NOW())",
            [$fila, $carrera, $acta, mb_substr(trim(preg_replace('/\s+/u', ' ', $alumno) ?? ''), 0, 160), $usuarioId]
        );
    }

    /** Elimina un registro mientras no tenga constancias generadas. */
    public static function eliminar(int $id): array
    {
        $h = Database::una('SELECT * FROM tit_historico WHERE id = ?', [$id]);
        if (!$h) {
            throw new \InvalidArgumentException('Registro inexistente.');
        }
        if ($h['tramite_id']) {
            throw new \InvalidArgumentException('Este registro ya tiene constancias generadas; no se puede eliminar.');
        }
        Database::ejecutar('DELETE FROM tit_historico WHERE id = ? AND tramite_id IS NULL', [$id]);
        return $h;
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
        if ($campo === 'titulo') {
            Database::ejecutar("UPDATE tit_historico SET titulo_origen = 'manual' WHERE id = ?", [$id]);   // lo confirmó o escribió una persona
        }
        $fila = Database::una('SELECT * FROM tit_historico WHERE id = ?', [$id]) ?? throw new \InvalidArgumentException('Registro inexistente.');
        $fila['faltan'] = self::faltantes($fila);
        $fila['fuera'] = self::fueraDeRango($fila);
        $fila['aplica'] = self::aplica($fila);
        $fila['regeneradas'] = 0;
        // Con constancias ya generadas y el registro completo, la corrección se aplica a esas constancias (mismo archivo).
        if ($fila['tramite_id'] && !$fila['faltan'] && $fila['aplica']) {
            $fila['regeneradas'] = self::generar($id, $usuarioId);
        }
        return $fila;
    }

    // ------------------------------------------------------------------ títulos

    /**
     * Completa el título de los registros que no lo tienen con el de un trámite de la plataforma del mismo alumno (cuenta y licenciatura).
     * El título del protocolo suele cambiar al del trabajo definitivo, así que se prefiere el de la solicitud de revisión del trabajo
     * escrito («revision»); si solo existe el del protocolo se usa marcado como «protocolo» para que se confirme. Nunca pisa un título
     * escrito ni toca registros con constancias ya generadas. @return array{revision:int,protocolo:int,sin_tramite:int}
     */
    public static function buscarTitulos(): array
    {
        $porCuenta = [];
        foreach (Database::todas("SELECT id, titulo, datos, estado FROM tit_tramites WHERE historico = 0 AND tipo = 'con_trabajo' ORDER BY id") as $t) {
            $d = json_decode((string) $t['datos'], true) ?: [];
            $cuenta = trim((string) ($d['datos']['cuenta'] ?? ''));
            if ($cuenta === '') {
                continue;
            }
            $porCuenta[$cuenta][] = [
                'carrera' => (string) ($d['datos']['carrera'] ?? ''),
                'revision' => trim((string) ($d['revision']['titulo'] ?? '')),
                'protocolo' => trim((string) ($d['protocolo']['titulo'] ?? '')),
                'ultimo' => trim((string) $t['titulo']),
            ];
        }
        $r = ['revision' => 0, 'protocolo' => 0, 'sin_tramite' => 0];
        foreach (Database::todas("SELECT id, cuenta, carrera, modalidad FROM tit_historico WHERE titulo = '' AND generado_en IS NULL AND cuenta <> ''") as $h) {
            if ($h['modalidad'] !== null && $h['modalidad'] !== '' && !self::conTrabajo($h['modalidad'])) {
                continue;
            }
            $mejor = null;
            foreach (array_reverse($porCuenta[trim((string) $h['cuenta'])] ?? []) as $t) {
                if (($t['carrera'] === '' || $t['carrera'] === $h['carrera']) && ($t['revision'] !== '' || $t['protocolo'] !== '' || $t['ultimo'] !== '')) {
                    $mejor = $t;
                    break;
                }
            }
            if (!$mejor) {
                $r['sin_tramite']++;
                continue;
            }
            $definitivo = $mejor['revision'] !== '' ? $mejor['revision'] : '';
            $titulo = $definitivo !== '' ? $definitivo : ($mejor['protocolo'] !== '' ? $mejor['protocolo'] : $mejor['ultimo']);
            $origen = $definitivo !== '' ? 'revision' : 'protocolo';
            $protocolo = $mejor['protocolo'] !== '' && $mejor['protocolo'] !== $titulo ? $mejor['protocolo'] : '';
            Database::ejecutar(
                "UPDATE tit_historico SET titulo = ?, titulo_origen = ?, titulo_protocolo = ? WHERE id = ? AND titulo = ''",
                [mb_substr($titulo, 0, 500), $origen, mb_substr($protocolo, 0, 500), (int) $h['id']]
            );
            $r[$origen]++;
        }
        return $r;
    }

    // ------------------------------------------------------------------ constancias

    /** La persona de una constancia: si el libro no trae su correo se busca por nombre en el directorio de profesores y en las cuentas. */
    public static function persona(string $nombre, string $email): array
    {
        $n = self::norma($nombre);
        $genero = preg_match('/^(DRA|MTRA|LIC\.?\s*A)\b/', $n) ? 'femenino' : (preg_match('/^(DR|MTRO)\b/', $n) ? 'masculino' : null);
        $email = trim($email);
        $conocido = $email === '' ? Profesores::porNombre($nombre) : null;
        if ($conocido) {
            $email = $conocido['email'];
        }
        $p = Persona::completar(['nombre' => $nombre, 'email' => $email], $email);
        $p['genero'] ??= $genero ?? ($conocido['genero'] ?? null);
        return $p;
    }

    /**
     * Genera (o completa) las constancias de un registro con trabajo escrito: director de tesis, codirector de tesis y, como revisor del
     * trabajo escrito, cada sinodal del acta (presidente, secretario o vocal; su función no importa). Devuelve cuántas se generaron.
     */
    public static function generar(int $id, int $usuarioId): int
    {
        $h = Database::una('SELECT * FROM tit_historico WHERE id = ?', [$id]);
        if (!$h) {
            throw new \InvalidArgumentException('Registro inexistente.');
        }
        if (!self::aplica($h)) {
            throw new \InvalidArgumentException('Quien se tituló por EGEL, aprovechamiento o créditos no genera constancias para los profesores.');
        }
        if ($faltan = self::faltantes($h)) {
            throw new \InvalidArgumentException('Faltan datos: ' . implode(', ', array_map(static fn(string $k): string => self::CAMPOS[$k][0], $faltan)) . '.');
        }
        $tramite = self::tramite($h, $usuarioId);
        $fecha = (string) $h['fecha_examen'];
        $detalle = ['desde' => $fecha, 'hasta' => $fecha, 'acta' => (string) ($h['acta'] ?? '')];
        $n = 0;
        $emitidas = [];
        foreach (['director' => 'asesor_trabajo', 'codirector' => 'coasesor_trabajo', 'presidente' => 'sinodal', 'secretario' => 'sinodal', 'vocal' => 'sinodal'] as $rol => $tipo) {
            if (trim((string) $h[$rol]) !== '') {
                $persona = self::persona($h[$rol], (string) ($h[$rol . '_email'] ?? ''));
                // Si el libro repite a alguien en dos lugares del jurado, recibe una sola constancia de revisor del trabajo escrito.
                $quien = $tipo . '|' . (strtolower(trim((string) ($persona['email'] ?? ''))) ?: self::norma((string) $h[$rol]));
                if (isset($emitidas[$quien])) {
                    foreach (Database::todas('SELECT * FROM tit_constancias WHERE tramite_id = ? AND rol = ?', [(int) $tramite['id'], $rol]) as $c) {
                        Constancias::anular($c);
                    }
                    continue;
                }
                $emitidas[$quien] = true;
                $n += (int) Constancias::emitir($tramite, $tipo, $persona, $detalle, $usuarioId, $rol);
            } else {
                // Se quitó a esa persona del registro: su constancia ya no corresponde.
                foreach (Database::todas('SELECT * FROM tit_constancias WHERE tramite_id = ? AND rol = ?', [(int) $tramite['id'], $rol]) as $c) {
                    Constancias::anular($c);
                }
            }
        }
        Database::ejecutar('UPDATE tit_historico SET generado_en = NOW() WHERE id = ?', [$id]);
        return $n;
    }

    /** Trámite histórico del registro (se crea la primera vez y se actualiza con lo corregido). */
    private static function tramite(array $h, int $usuarioId): array
    {
        $carrera = (string) $h['carrera'];
        $modalidad = (string) ($h['modalidad'] ?: 'tesis');   // el trámite la exige; las constancias nuevas no la imprimen
        $acta = (int) ($h['acta'] ?? 0);
        $datos = [
            'datos' => ['nombre' => $h['alumno'], 'cuenta' => $h['cuenta'], 'carrera' => $carrera, 'sistema' => 'fi'],
            'protocolo' => ['titulo' => $h['titulo'], 'asesor' => $h['director'], 'asesor_correo' => $h['director_email'], 'coasesor' => $h['codirector'], 'coasesor_correo' => $h['codirector_email']],
            'historico' => ['id' => (int) $h['id'], 'acta' => $acta, 'hoja' => $h['hoja'], 'fila' => (int) $h['fila']],
        ];
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
        $tid = (int) ($h['tramite_id'] ?? 0);
        if ($tid > 0) {
            Database::ejecutar('UPDATE tit_tramites SET modalidad = ?, titulo = ?, datos = ? WHERE id = ?', [$modalidad, $h['titulo'], $json, $tid]);
        } else {
            $siglas = Catalogo::carreras()[$carrera]['siglas'][0] ?? 'FI';
            $folio = 'HIS-' . $siglas . '-' . ($acta > 0 ? $acta : 'S' . $h['id']);
            if (Database::valor('SELECT id FROM tit_tramites WHERE folio = ?', [$folio])) {
                $folio .= '-' . $h['id'];
            }
            $tid = Database::ejecutar(
                "INSERT INTO tit_tramites (folio, nivel, alumno_id, tipo, modalidad, estado, etapa_actual, titulo, datos, resultado, responsable_id, historico)
                 VALUES (?, 'licenciatura', NULL, 'con_trabajo', ?, 'concluido', 'resultado', ?, ?, 'aprobado', ?, 1)",
                [mb_substr($folio, 0, 20), $modalidad, $h['titulo'], $json, $usuarioId]
            );
            Database::ejecutar('UPDATE tit_historico SET tramite_id = ? WHERE id = ?', [$tid, (int) $h['id']]);
            if ($acta > 0) {
                Database::ejecutar("INSERT IGNORE INTO tit_actas (tramite_id, carrera, tipo, numero, fecha) VALUES (?, ?, 'evaluacion', ?, ?)", [$tid, $carrera, $acta, $h['fecha_examen']]);
            }
        }
        return \App\Modules\Titulacion\TitulacionRepository::obtener($tid) ?? throw new \RuntimeException('No se pudo crear el trámite histórico.');
    }
}
