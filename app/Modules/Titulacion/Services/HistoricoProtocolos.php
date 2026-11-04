<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;

/**
 * Protocolos revisados fuera del sistema (en papel): se capturan aquí mientras el personal se adapta a usar el flujo de la plataforma
 * y, ya completos, generan la constancia de «revisor del protocolo» de cada revisor por medio de un trámite histórico.
 */
final class HistoricoProtocolos
{
    private const REVISORES = [1, 2, 3];

    private static function limpio(mixed $v, int $max): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) $v)), 0, $max);
    }

    private static function fecha(mixed $v): ?string
    {
        $v = trim((string) $v);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && checkdate((int) substr($v, 5, 2), (int) substr($v, 8, 2), (int) substr($v, 0, 4)) ? $v : null;
    }

    /** @return int id */
    public static function crear(array $in, int $usuarioId): int
    {
        $carrera = (string) ($in['carrera'] ?? '');
        if (!isset(Catalogo::carreras()[$carrera]) || mb_strlen(self::limpio($in['alumno'] ?? '', 160)) < 5) {
            throw new \InvalidArgumentException('Indica la licenciatura y el nombre completo del alumno.');
        }
        $aprob = self::fecha($in['fecha_aprobacion'] ?? '');
        if ($aprob === null || $aprob < '1990-01-01' || $aprob > date('Y-m-d')) {
            throw new \InvalidArgumentException('La fecha de aprobación del protocolo es obligatoria y no puede ser futura.');
        }
        $inicio = self::fecha($in['fecha_inicio'] ?? '');
        if ($inicio !== null && $inicio > $aprob) {
            throw new \InvalidArgumentException('La fecha en que se turnó el protocolo no puede ser posterior a su aprobación.');
        }
        if (self::limpio($in['titulo'] ?? '', 500) === '') {
            throw new \InvalidArgumentException('Escribe el título del protocolo.');
        }
        $cols = ['carrera' => $carrera, 'alumno' => self::limpio($in['alumno'], 160), 'cuenta' => self::limpio($in['cuenta'] ?? '', 12),
            'titulo' => self::limpio($in['titulo'], 500), 'director' => self::limpio($in['director'] ?? '', 160), 'fecha_inicio' => $inicio, 'fecha_aprobacion' => $aprob];
        $hay = 0;
        foreach (self::REVISORES as $n) {
            $cols["revisor$n"] = self::limpio($in["revisor$n"] ?? '', 160);
            $correo = strtolower(self::limpio($in["revisor{$n}_email"] ?? '', 120));
            if ($correo !== '' && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("El correo del revisor $n no es válido.");
            }
            $cols["revisor{$n}_email"] = $correo;
            $hay += $cols["revisor$n"] !== '' ? 1 : 0;
        }
        if ($hay === 0) {
            throw new \InvalidArgumentException('Captura al menos un revisor del protocolo.');
        }
        $cols['creado_por'] = $usuarioId;
        return Database::ejecutar('INSERT INTO tit_historico_protocolos (`' . implode('`,`', array_keys($cols)) . '`) VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($cols));
    }

    /** Todos los revisores del registro: las tres columnas y, si hay más, los de revisores_mas. @return list<array{nombre:string,email:string}> */
    public static function revisores(array $h): array
    {
        $r = [];
        foreach (self::REVISORES as $i) {
            if (trim((string) $h["revisor$i"]) !== '') {
                $r[] = ['nombre' => trim((string) $h["revisor$i"]), 'email' => (string) $h["revisor{$i}_email"]];
            }
        }
        foreach (json_decode((string) ($h['revisores_mas'] ?? ''), true) ?: [] as $x) {
            if (trim((string) ($x['nombre'] ?? '')) !== '') {
                $r[] = ['nombre' => trim((string) $x['nombre']), 'email' => (string) ($x['email'] ?? '')];
            }
        }
        return $r;
    }

    /** @param array{q?:string,carrera?:string,estado?:string,desde?:string,hasta?:string} $f @return array{total:int,filas:array} */
    public static function listar(array $f = [], int $limite = 50, int $desde = 0): array
    {
        $donde = ['1 = 1'];
        $p = [];
        if (($f['carrera'] ?? '') !== '') {
            $donde[] = 'carrera = ?';
            $p[] = $f['carrera'];
        }
        if (($f['q'] ?? '') !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $f['q']) . '%';
            $donde[] = '(alumno LIKE ? OR cuenta LIKE ? OR titulo LIKE ? OR revisor1 LIKE ? OR revisor2 LIKE ? OR revisor3 LIKE ? OR revisores_mas LIKE ?)';
            array_push($p, $like, $like, $like, $like, $like, $like, $like);
        }
        if (($f['estado'] ?? '') === 'generadas') {
            $donde[] = 'tramite_id IS NOT NULL';
        } elseif (($f['estado'] ?? '') === 'pendientes') {
            $donde[] = 'tramite_id IS NULL';
        }
        foreach (['desde' => '>=', 'hasta' => '<='] as $k => $op) {
            if (self::fecha($f[$k] ?? '') !== null) {
                $donde[] = "fecha_aprobacion $op ?";
                $p[] = $f[$k];
            }
        }
        $w = implode(' AND ', $donde);
        return [
            'total' => (int) Database::valor("SELECT COUNT(*) FROM tit_historico_protocolos WHERE $w", $p),
            'filas' => Database::todas("SELECT * FROM tit_historico_protocolos WHERE $w ORDER BY fecha_aprobacion DESC, id DESC LIMIT " . (int) $limite . ' OFFSET ' . (int) $desde, $p),
            'ids_pendientes' => array_map('intval', array_column(Database::todas("SELECT id FROM tit_historico_protocolos WHERE tramite_id IS NULL AND $w ORDER BY id LIMIT 5000", $p), 'id')),
        ];
    }

    public static function eliminar(int $id): void
    {
        $h = Database::una('SELECT * FROM tit_historico_protocolos WHERE id = ?', [$id]);
        if (!$h) {
            throw new \InvalidArgumentException('Registro inexistente.');
        }
        if ($h['tramite_id']) {
            throw new \InvalidArgumentException('Este registro ya tiene constancias generadas; no se puede eliminar.');
        }
        Database::ejecutar('DELETE FROM tit_historico_protocolos WHERE id = ? AND tramite_id IS NULL', [$id]);
    }

    /** Genera las constancias de revisor del protocolo. @return int constancias generadas */
    public static function generar(int $id, int $usuarioId): int
    {
        $h = Database::una('SELECT * FROM tit_historico_protocolos WHERE id = ?', [$id]) ?? throw new \InvalidArgumentException('Registro inexistente.');
        $tramite = self::tramite($h, $usuarioId);
        $detalle = ['desde' => (string) ($h['fecha_inicio'] ?: $h['fecha_aprobacion']), 'hasta' => (string) $h['fecha_aprobacion'], 'vueltas' => ''];
        $n = 0;
        $vistos = [];
        foreach (self::revisores($h) as $k => $r) {
            $i = $k + 1;
            $persona = Historico::persona($r['nombre'], $r['email']);
            $quien = strtolower(trim((string) ($persona['email'] ?? ''))) ?: mb_strtoupper($r['nombre']);
            if (isset($vistos[$quien])) {
                continue;   // repetido: una sola constancia por persona
            }
            $vistos[$quien] = true;
            $n += (int) Constancias::emitir($tramite, 'revisor_protocolo', $persona, $detalle, $usuarioId, 'revisor_' . $i);
        }
        Database::ejecutar('UPDATE tit_historico_protocolos SET generado_en = NOW() WHERE id = ?', [$id]);
        return $n;
    }

    private static function tramite(array $h, int $usuarioId): array
    {
        $carrera = (string) $h['carrera'];
        $datos = [
            'datos' => ['nombre' => $h['alumno'], 'cuenta' => $h['cuenta'], 'carrera' => $carrera, 'sistema' => 'fi'],
            'protocolo' => ['titulo' => $h['titulo'], 'asesor' => $h['director']],
            'historico' => ['protocolo_id' => (int) $h['id']],
        ];
        $json = json_encode($datos, JSON_UNESCAPED_UNICODE);
        $tid = (int) ($h['tramite_id'] ?? 0);
        if ($tid > 0) {
            Database::ejecutar('UPDATE tit_tramites SET titulo = ?, datos = ? WHERE id = ?', [$h['titulo'], $json, $tid]);
        } else {
            $siglas = Catalogo::carreras()[$carrera]['siglas'][0] ?? 'FI';
            $tid = Database::ejecutar(
                "INSERT INTO tit_tramites (folio, nivel, alumno_id, tipo, modalidad, estado, etapa_actual, titulo, datos, resultado, responsable_id, historico)
                 VALUES (?, 'licenciatura', NULL, 'con_trabajo', 'tesis', 'concluido', 'resultado', ?, ?, 'aprobado', ?, 1)",
                [mb_substr('HIS-P-' . $siglas . '-' . $h['id'], 0, 20), $h['titulo'], $json, $usuarioId]
            );
            Database::ejecutar('UPDATE tit_historico_protocolos SET tramite_id = ? WHERE id = ?', [$tid, (int) $h['id']]);
        }
        return TitulacionRepository::obtener($tid) ?? throw new \RuntimeException('No se pudo crear el trámite histórico.');
    }
}
