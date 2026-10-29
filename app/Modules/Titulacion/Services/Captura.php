<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;

/**
 * Captura transitoria: fichas de los trámites que se atienden en papel mientras la plataforma no se inaugura. Los examinados se
 * pasan al libro de actas (tit_historico) para sus constancias; al inaugurar, cada ficha se vincula con el trámite en línea del
 * alumno por número de cuenta y la captura queda en solo lectura (ajuste `titulacion_captura_cerrada`).
 */
final class Captura
{
    public const ESTADOS = [
        'protocolo' => 'Protocolo en revisión', 'tema_aprobado' => 'Tema aprobado', 'prorroga' => 'Prórroga',
        'afinidad' => 'Afinidad CEA', 'revision' => 'Trabajo escrito en revisión', 'examinado' => 'Examinado',
    ];

    /** Columnas editables => [etiqueta, tipo]. */
    public const CAMPOS = [
        'estado' => ['Estado', 'estado'], 'carrera' => ['Licenciatura', 'carrera'], 'sistema' => ['Plantel', 'sistema'],
        'alumno' => ['Alumno', 'texto'], 'cuenta' => ['Número de cuenta', 'cuenta'], 'modalidad' => ['Modalidad', 'modalidad'],
        'clave_registro' => ['Clave de registro', 'texto'], 'titulo' => ['Título del trabajo', 'largo'],
        'asesor' => ['Asesor o director', 'texto'], 'coasesor' => ['Coasesor', 'texto'],
        'revisores_protocolo' => ['Revisores del protocolo', 'lista'], 'revisores_trabajo' => ['Revisores del trabajo escrito', 'lista'],
        'programa_cea' => ['Posgrado afín (CEA)', 'largo'],
        'fecha_tema' => ['Aprobación del tema', 'fecha'], 'fecha_prorroga' => ['Vence la prórroga', 'fecha'], 'fecha_afinidad' => ['Dictamen de afinidad', 'fecha'],
        'fecha_dictamen' => ['Dictamen del trabajo escrito', 'fecha'], 'fecha_examen' => ['Fecha del examen', 'fecha'],
        'revisar' => ['Por confirmar', 'bandera'], 'observaciones' => ['Observaciones', 'nota'], 'fuente' => ['Documento de origen', 'texto'],
    ];

    public static function cerrada(): bool
    {
        return \ajuste('titulacion_captura_cerrada', '0') === '1';
    }

    public static function cerrar(bool $cerrar): void
    {
        Database::ejecutar(
            "INSERT INTO ajustes (clave, valor, descripcion) VALUES ('titulacion_captura_cerrada', ?, 'Captura transitoria de Titulación en solo lectura (1 al inaugurar la plataforma)')
             ON DUPLICATE KEY UPDATE valor = VALUES(valor)",
            [$cerrar ? '1' : '0']
        );
    }

    /**
     * Lo que le falta a la ficha para estar completa: nombre, cuenta, licenciatura y modalidad; con trabajo escrito, título, asesor y
     * la fecha de aprobación, dictamen o examen; por créditos, el posgrado y la fecha de afinidad; examinado, la fecha del examen. Una ficha
     * marcada «por confirmar» cuenta como incompleta. @return string[]
     */
    public static function faltantes(array $c): array
    {
        $f = [];
        foreach (['alumno', 'carrera', 'modalidad'] as $k) {
            if (trim((string) ($c[$k] ?? '')) === '') {
                $f[] = $k;
            }
        }
        if (!preg_match('/^\d{6,10}$/', (string) ($c['cuenta'] ?? ''))) {
            $f[] = 'cuenta';
        }
        $m = (string) ($c['modalidad'] ?? '');
        if ($m === 'creditos') {
            foreach (['programa_cea', 'fecha_afinidad'] as $k) {
                if (trim((string) ($c[$k] ?? '')) === '') {
                    $f[] = $k;
                }
            }
        } elseif ($m === '' || (Catalogo::modalidad($m)['tipo'] ?? '') === 'con_trabajo') {
            foreach (['titulo', 'asesor'] as $k) {
                if (trim((string) ($c[$k] ?? '')) === '') {
                    $f[] = $k;
                }
            }
            if (empty($c['fecha_tema']) && empty($c['fecha_dictamen']) && empty($c['fecha_examen']) && ($c['estado'] ?? '') !== 'protocolo') {
                $f[] = 'fecha_tema';
            }
        }
        if (($c['estado'] ?? '') === 'examinado' && empty($c['fecha_examen'])) {
            $f[] = 'fecha_examen';
        }
        if (!empty($c['revisar'])) {
            $f[] = 'revisar';
        }
        return $f;
    }

    /** @param array{q?:string,carrera?:string,estado?:string,faltantes?:string} $f @return array{filas:array,total:int,incompletas:int} */
    public static function listar(array $f): array
    {
        $donde = ['1 = 1'];
        $p = [];
        if (($f['carrera'] ?? '') !== '') {
            $donde[] = 'carrera = ?';
            $p[] = $f['carrera'];
        }
        if (($f['estado'] ?? '') !== '') {
            $donde[] = 'estado = ?';
            $p[] = $f['estado'];
        }
        if (($f['q'] ?? '') !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $f['q']) . '%';
            $donde[] = '(alumno LIKE ? OR cuenta LIKE ? OR titulo LIKE ? OR asesor LIKE ? OR coasesor LIKE ? OR revisores_protocolo LIKE ? OR revisores_trabajo LIKE ? OR clave_registro LIKE ?)';
            array_push($p, $like, $like, $like, $like, $like, $like, $like, $like);
        }
        $salida = [];
        $incompletas = 0;
        foreach (Database::todas('SELECT * FROM tit_captura WHERE ' . implode(' AND ', $donde) . ' ORDER BY id DESC', $p) as $c) {
            $c['faltan'] = self::faltantes($c);
            $incompletas += $c['faltan'] ? 1 : 0;
            if (($f['faltantes'] ?? '') === 'incompletos' && !$c['faltan'] || ($f['faltantes'] ?? '') === 'completos' && $c['faltan']) {
                continue;
            }
            $salida[] = $c;
        }
        return ['filas' => $salida, 'total' => count($salida), 'incompletas' => $incompletas];
    }

    /** Resumen para el encabezado: total de fichas e incompletas, en toda la captura. @return array{total:int,incompletas:int} */
    public static function resumen(): array
    {
        $t = $i = 0;
        foreach (Database::todas('SELECT * FROM tit_captura') as $c) {
            $t++;
            $i += self::faltantes($c) ? 1 : 0;
        }
        return ['total' => $t, 'incompletas' => $i];
    }

    public static function obtener(int $id): ?array
    {
        return Database::una('SELECT * FROM tit_captura WHERE id = ?', [$id]);
    }

    private static function fecha(string $v): ?string
    {
        if ($v === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if (!$d || $d->format('Y-m-d') !== $v || $v < '1990-01-01' || $v > '2040-12-31') {
            throw new \InvalidArgumentException('Fecha no válida: ' . $v . '.');
        }
        return $v;
    }

    /** Normaliza lo enviado por el formulario. @return array<string,mixed> */
    private static function limpiar(array $in): array
    {
        $d = [];
        foreach (self::CAMPOS as $k => [$etiqueta, $tipo]) {
            $v = trim(str_replace("\r\n", "\n", (string) ($in[$k] ?? '')));
            $d[$k] = match ($tipo) {
                'estado' => isset(self::ESTADOS[$v]) ? $v : throw new \InvalidArgumentException('Elige el estado del trámite.'),
                'carrera' => $v === '' || isset(Catalogo::carreras()[$v]) ? $v : throw new \InvalidArgumentException('Licenciatura no válida.'),
                'sistema' => isset(Catalogo::sistemas()[$v]) ? $v : 'fi',
                'modalidad' => $v === '' ? null : (Catalogo::modalidad($v) ? $v : throw new \InvalidArgumentException('Modalidad no válida.')),
                'cuenta' => $v === '' || preg_match('/^\d{6,10}$/', $v) ? $v : throw new \InvalidArgumentException('El número de cuenta lleva solo dígitos (6 a 10).'),
                'fecha' => self::fecha($v),
                'bandera' => $v !== '' && $v !== '0' ? 1 : 0,
                'lista' => mb_substr(implode("\n", array_filter(array_map('trim', explode("\n", $v)))), 0, 600),
                'nota' => $v === '' ? null : mb_substr($v, 0, 4000),
                'largo' => mb_substr(preg_replace('/\s+/u', ' ', $v) ?? '', 0, $k === 'titulo' ? 500 : 300),
                default => mb_substr(preg_replace('/\s+/u', ' ', $v) ?? '', 0, $k === 'fuente' ? 300 : 160),
            };
        }
        if (mb_strlen($d['alumno']) < 5) {
            throw new \InvalidArgumentException('Escribe el nombre completo del alumno.');
        }
        return $d;
    }

    /** Alta o edición de una ficha. @return int id */
    public static function guardar(?int $id, array $in, int $usuarioId): int
    {
        if (self::cerrada()) {
            throw new \InvalidArgumentException('La captura transitoria está cerrada: los trámites se atienden ya en la plataforma.');
        }
        $d = self::limpiar($in);
        if ($d['cuenta'] !== '') {
            $otro = Database::una('SELECT id, alumno FROM tit_captura WHERE cuenta = ? AND id <> ? AND modalidad <=> ?', [$d['cuenta'], (int) $id, $d['modalidad']]);
            if ($otro) {
                throw new \InvalidArgumentException('La cuenta ' . $d['cuenta'] . ' ya tiene una ficha con esa modalidad (' . $otro['alumno'] . '). Edita esa ficha.');
            }
        }
        $cols = array_keys($d);
        if ($id) {
            $set = implode(', ', array_map(static fn(string $c): string => "`$c` = ?", $cols));
            Database::ejecutar("UPDATE tit_captura SET $set, actualizado_por = ?, actualizado_en = NOW() WHERE id = ?", [...array_values($d), $usuarioId, $id]);
            return $id;
        }
        $lista = implode(', ', array_map(static fn(string $c): string => "`$c`", $cols));
        return Database::ejecutar(
            "INSERT INTO tit_captura ($lista, creado_por, actualizado_por, actualizado_en) VALUES (" . implode(', ', array_fill(0, count($cols), '?')) . ', ?, ?, NOW())',
            [...array_values($d), $usuarioId, $usuarioId]
        );
    }

    public static function eliminar(int $id): array
    {
        if (self::cerrada()) {
            throw new \InvalidArgumentException('La captura transitoria está cerrada.');
        }
        $c = self::obtener($id) ?? throw new \InvalidArgumentException('Ficha inexistente.');
        if ($c['tramite_id']) {
            throw new \InvalidArgumentException('La ficha ya está vinculada con un trámite de la plataforma.');
        }
        Database::ejecutar('DELETE FROM tit_captura WHERE id = ?', [$id]);
        return $c;
    }

    private static function norma(string $s): string
    {
        $s = mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $s) ?? ''));
        return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);
    }

    /** ¿Mismas palabras del nombre? El libro escribe «APELLIDOS NOMBRE» y los oficios «NOMBRE APELLIDOS». */
    private static function mismoNombre(string $a, string $b): bool
    {
        $pa = explode(' ', self::norma($a));
        $pb = explode(' ', self::norma($b));
        sort($pa);
        sort($pb);
        return $pa === $pb;
    }

    /**
     * Pasa una ficha examinada al libro de actas: si el alumno ya está en el libro (misma licenciatura y cuenta, o el mismo nombre)
     * se enlaza y solo se llenan sus datos vacíos; si no, se crea su renglón. Ahí se generan las constancias. @return int id del libro
     */
    public static function pasarAlHistorial(int $id, int $usuarioId): int
    {
        $c = self::obtener($id) ?? throw new \InvalidArgumentException('Ficha inexistente.');
        if ($c['historico_id']) {
            return (int) $c['historico_id'];
        }
        if (empty($c['fecha_examen']) || $c['carrera'] === '' || trim($c['alumno']) === '') {
            throw new \InvalidArgumentException('Para pasarla al historial captura la licenciatura, el alumno y la fecha del examen.');
        }
        $hid = null;
        foreach (Database::todas('SELECT id, alumno, cuenta FROM tit_historico WHERE carrera = ? AND (fecha_examen IS NULL OR fecha_examen >= ?)', [$c['carrera'], date('Y-m-d', strtotime($c['fecha_examen'] . ' -1 year'))]) as $h) {
            if (($c['cuenta'] !== '' && $h['cuenta'] === $c['cuenta']) || self::mismoNombre($h['alumno'], $c['alumno'])) {
                $hid = (int) $h['id'];
                break;
            }
        }
        if ($hid === null) {
            $fila = (int) Database::valor("SELECT COALESCE(MAX(fila), 0) + 1 FROM tit_historico WHERE hoja = 'CAPTURA TRANSITORIA'");
            $hid = Database::ejecutar(
                "INSERT INTO tit_historico (hoja, fila, carrera, alumno, cuenta, actualizado_por, actualizado_en) VALUES ('CAPTURA TRANSITORIA', ?, ?, ?, ?, ?, NOW())",
                [$fila, $c['carrera'], $c['alumno'], $c['cuenta'], $usuarioId]
            );
        }
        Database::ejecutar(
            "UPDATE tit_historico SET cuenta = IF(cuenta = '', ?, cuenta), modalidad = IF(modalidad IS NULL OR modalidad = '', ?, modalidad),
                    fecha_examen = IFNULL(fecha_examen, ?), titulo_origen = IF(titulo = '', 'manual', titulo_origen), titulo = IF(titulo = '', ?, titulo),
                    director = IF(director = '', ?, director), codirector = IF(codirector = '', ?, codirector), actualizado_por = ?, actualizado_en = NOW()
             WHERE id = ? AND tramite_id IS NULL",
            [$c['cuenta'], $c['modalidad'], $c['fecha_examen'], $c['titulo'], $c['asesor'], $c['coasesor'], $usuarioId, $hid]
        );
        Database::ejecutar("UPDATE tit_captura SET historico_id = ?, estado = 'examinado', actualizado_por = ?, actualizado_en = NOW() WHERE id = ?", [$hid, $usuarioId, $id]);
        return $hid;
    }

    // ------------------------------------------------------------------ inauguración: vínculo con el trámite en línea

    /** Fichas sin vincular que corresponden al trámite (misma cuenta). */
    public static function paraTramite(array $t): array
    {
        $cuenta = trim((string) ($t['datos']['datos']['cuenta'] ?? ''));
        try {
            $vinculada = Database::todas('SELECT * FROM tit_captura WHERE tramite_id = ?', [(int) $t['id']]);
        } catch (\PDOException) {
            return [];   // migración 035 sin aplicar: el expediente se muestra igual
        }
        if ($vinculada || $cuenta === '') {
            return $vinculada;
        }
        return Database::todas('SELECT * FROM tit_captura WHERE cuenta = ? AND tramite_id IS NULL ORDER BY id DESC', [$cuenta]);
    }

    /**
     * Vincula la ficha con el trámite y precarga lo que el alumno no haya capturado: título, asesor y coasesor del protocolo y el
     * título de la solicitud de revisión. Lo ya hecho en papel queda en la bitácora del expediente para que el personal registre
     * cada etapa con sus fechas. @return string[] campos precargados
     */
    public static function vincular(array $t, int $capturaId, int $usuarioId): array
    {
        $c = self::obtener($capturaId);
        $cuenta = trim((string) ($t['datos']['datos']['cuenta'] ?? ''));
        if (!$c || $c['tramite_id'] || $cuenta === '' || $c['cuenta'] !== $cuenta) {
            throw new \InvalidArgumentException('La ficha no corresponde a este trámite o ya está vinculada.');
        }
        $datos = $t['datos'];
        $hechos = [];
        $poner = static function (string $etapa, string $k, string $v) use (&$datos, &$hechos): void {
            if (trim($v) !== '' && trim((string) ($datos[$etapa][$k] ?? '')) === '') {
                $datos[$etapa][$k] = $v;
                $hechos[] = $etapa . '.' . $k;
            }
        };
        if ($t['tipo'] === 'con_trabajo') {
            $poner('protocolo', 'titulo', (string) $c['titulo']);
            $poner('protocolo', 'asesor', (string) $c['asesor']);
            $poner('protocolo', 'coasesor', (string) $c['coasesor']);
            if (!empty($c['fecha_dictamen'])) {
                $poner('revision', 'titulo', (string) $c['titulo']);
            }
        }
        TitulacionRepository::guardarDatos((int) $t['id'], $datos);
        Database::ejecutar('UPDATE tit_captura SET tramite_id = ?, actualizado_por = ?, actualizado_en = NOW() WHERE id = ?', [(int) $t['id'], $usuarioId, $capturaId]);
        $antes = [];
        foreach (['fecha_tema' => 'tema aprobado', 'fecha_prorroga' => 'prórroga vigente hasta', 'fecha_afinidad' => 'afinidad CEA', 'fecha_dictamen' => 'dictamen del trabajo escrito', 'fecha_examen' => 'examen'] as $k => $et) {
            if (!empty($c[$k])) {
                $antes[] = $et . ' ' . date('d/m/Y', strtotime((string) $c[$k]));
            }
        }
        TitulacionRepository::evento((int) $t['id'], $usuarioId, 'captura', 'Vinculado con la ficha de captura transitoria #' . $capturaId
            . ($antes ? ' (' . implode('; ', $antes) . ')' : '') . '.', false);
        return $hechos;
    }

    /** Exporta la captura completa (con sus faltantes) a CSV para respaldo o revisión fuera de la plataforma. */
    public static function csv(): string
    {
        $fh = fopen('php://temp', 'w+');
        fwrite($fh, "\xEF\xBB\xBF");
        $cols = array_keys(self::CAMPOS);
        fputcsv($fh, ['id', ...array_map(static fn(string $k): string => self::CAMPOS[$k][0], $cols), 'Faltan', 'Libro de actas', 'Trámite']);
        foreach (Database::todas('SELECT * FROM tit_captura ORDER BY id') as $c) {
            $fila = [$c['id']];
            foreach ($cols as $k) {
                $fila[] = (string) ($c[$k] ?? '');
            }
            fputcsv($fh, [...$fila, implode(', ', array_map(static fn(string $k): string => self::CAMPOS[$k][0], self::faltantes($c))), $c['historico_id'] ? 'Sí' : '', (string) ($c['tramite_id'] ?? '')]);
        }
        rewind($fh);
        return (string) stream_get_contents($fh);
    }
}
