<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Core\Persona;
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
        $fila = Database::una('SELECT * FROM tit_historico WHERE id = ?', [$id]) ?? throw new \InvalidArgumentException('Registro inexistente.');
        $fila['faltan'] = self::faltantes($fila);
        $fila['regeneradas'] = 0;
        // Con constancias ya generadas y el registro completo, la corrección se aplica a esas constancias (mismo archivo).
        if ($fila['tramite_id'] && !$fila['faltan'] && self::conTrabajo($fila['modalidad'])) {
            $fila['regeneradas'] = self::generar($id, $usuarioId);
        }
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
            $n += (int) Constancias::emitir($tramite, $tipo, self::persona($h['director'], $h['director_email']), $detalle, $usuarioId, 'director');
        }
        if (trim($h['codirector']) !== '') {
            $n += (int) Constancias::emitir($tramite, 'coasesor_trabajo', self::persona($h['codirector'], $h['codirector_email']), $detalle, $usuarioId, 'codirector');
        }
        foreach (['presidente', 'secretario', 'vocal'] as $rol) {
            $n += (int) Constancias::emitir($tramite, 'sinodal', self::persona($h[$rol], $h[$rol . '_email']), $detalle + ['funcion' => $rol], $usuarioId, $rol);
        }
        if (trim($h['codirector']) === '') {
            foreach (Database::todas("SELECT * FROM tit_constancias WHERE tramite_id = ? AND rol = 'codirector'", [(int) $tramite['id']]) as $c) {
                Constancias::anular($c);   // se quitó al codirector: su constancia ya no corresponde
            }
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
