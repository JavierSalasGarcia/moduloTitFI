<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;

/**
 * Expediente de titulación con trabajo escrito, en tres etapas: revisión del protocolo, examen previo y examen definitivo.
 * Un expediente es un protocolo (tit_historico_protocolos) y/o un registro de examen (tit_historico), ligados uno a uno por
 * tit_historico_protocolos.historico_id. Las capturas buscan primero y completan lo que ya existe en lugar de crear otro registro.
 */
final class Expedientes
{
    private static function norma(string $s): string
    {
        $s = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $s)));
        return strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U']);
    }

    /** Condición SQL (alumno) para un nombre: todas sus palabras. @return array{0:string,1:array} */
    private static function porNombre(string $nombre, string $col = 'alumno'): array
    {
        $pal = array_values(array_filter(explode(' ', self::norma($nombre)), static fn(string $w): bool => mb_strlen($w) >= 3));
        if (!$pal) {
            return ['0 = 1', []];
        }
        return [implode(' AND ', array_fill(0, count($pal), "$col LIKE ?")), array_map(static fn(string $w): string => '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $w) . '%', $pal)];
    }

    /**
     * Expedientes de una licenciatura que coinciden por cuenta o por nombre.
     * @return list<array{protocolo:?array,historico:?array,probable:bool}>
     */
    public static function buscar(string $carrera, string $cuenta, string $nombre): array
    {
        $cuenta = trim($cuenta);
        [$cn, $pn] = self::porNombre($nombre);
        $cond = [];
        $par = [];
        if ($cuenta !== '') {
            $cond[] = 'cuenta = ?';
            $par[] = $cuenta;
        }
        if (trim($nombre) !== '') {
            $cond[] = "($cn)";
            array_push($par, ...$pn);
        }
        if (!$cond) {
            return [];
        }
        $w = 'carrera = ? AND (' . implode(' OR ', $cond) . ')';
        $prot = Database::todas("SELECT * FROM tit_historico_protocolos WHERE $w ORDER BY fecha_aprobacion DESC, id DESC LIMIT 40", [$carrera, ...$par]);
        $hist = Database::todas("SELECT * FROM tit_historico WHERE $w ORDER BY fecha_examen DESC, id DESC LIMIT 40", [$carrera, ...$par]);
        $porId = [];
        foreach ($hist as $h) {
            $porId[(int) $h['id']] = $h;
        }
        $res = [];
        foreach ($prot as $p) {
            $hid = (int) ($p['historico_id'] ?? 0);
            if ($hid > 0) {
                $h = $porId[$hid] ?? Database::una('SELECT * FROM tit_historico WHERE id = ?', [$hid]);
                unset($porId[$hid]);
                $res[] = ['protocolo' => $p, 'historico' => $h ?: null, 'probable' => false];
            } else {
                $res[] = ['protocolo' => $p, 'historico' => null, 'probable' => false];
            }
        }
        // Un protocolo sin examen ligado y un examen sin protocolo con la misma cuenta son, casi seguro, el mismo expediente.
        foreach ($res as &$e) {
            if ($e['historico'] === null && $e['protocolo']['cuenta'] !== '') {
                foreach ($porId as $id => $h) {
                    if ($h['cuenta'] === $e['protocolo']['cuenta'] && !self::protocoloDe((int) $id)) {
                        $e['historico'] = $h;
                        $e['probable'] = true;
                        unset($porId[$id]);
                        break;
                    }
                }
            }
        }
        unset($e);
        foreach ($porId as $h) {
            $res[] = ['protocolo' => null, 'historico' => $h, 'probable' => false];
        }
        return $res;
    }

    private static function protocoloDe(int $historicoId): ?array
    {
        return Database::una('SELECT * FROM tit_historico_protocolos WHERE historico_id = ?', [$historicoId]);
    }

    /** Liga un protocolo con su examen (uno a uno) y copia entre ambos las fechas de las etapas. */
    public static function ligar(int $protocoloId, int $historicoId, int $usuarioId): void
    {
        $p = Database::una('SELECT * FROM tit_historico_protocolos WHERE id = ?', [$protocoloId]) ?? throw new \InvalidArgumentException('Protocolo inexistente.');
        $h = Database::una('SELECT * FROM tit_historico WHERE id = ?', [$historicoId]) ?? throw new \InvalidArgumentException('Registro de examen inexistente.');
        if ($p['carrera'] !== $h['carrera']) {
            throw new \InvalidArgumentException('El protocolo y el examen son de licenciaturas distintas.');
        }
        if ($p['historico_id'] && (int) $p['historico_id'] !== $historicoId) {
            throw new \InvalidArgumentException('El protocolo ya está ligado a otro examen.');
        }
        if (self::protocoloDe($historicoId) && (int) self::protocoloDe($historicoId)['id'] !== $protocoloId) {
            throw new \InvalidArgumentException('Ese examen ya tiene otro protocolo ligado.');
        }
        $fp = (string) ($h['fecha_protocolo'] ?: $p['fecha_aprobacion']);
        $fv = (string) ($h['fecha_previo'] ?: $p['fecha_previo']);
        Historico::cronologia(['fecha_protocolo' => $fp, 'fecha_previo' => $fv, 'fecha_examen' => (string) $h['fecha_examen']]);
        Database::ejecutar('UPDATE tit_historico_protocolos SET historico_id = ?, fecha_previo = COALESCE(fecha_previo, ?), acta_previo = COALESCE(acta_previo, ?) WHERE id = ?',
            [$historicoId, $h['fecha_previo'], $h['acta_previo'], $protocoloId]);
        Database::ejecutar('UPDATE tit_historico SET fecha_protocolo = COALESCE(fecha_protocolo, ?), fecha_previo = COALESCE(fecha_previo, ?), acta_previo = COALESCE(acta_previo, ?),
                actualizado_por = ?, actualizado_en = NOW() WHERE id = ?', [$p['fecha_aprobacion'], $p['fecha_previo'], $p['acta_previo'], $usuarioId, $historicoId]);
    }

    /**
     * Hay ya un expediente de esa persona? Por cuenta es definitivo; por nombre (sin cuenta de alguno) se pide confirmar que no es un homónimo.
     * @return ?string mensaje si hay duplicado
     */
    public static function duplicado(string $carrera, string $cuenta, string $alumno, string $tabla, bool $homonimo = false, int $excluir = 0): ?string
    {
        $tabla = $tabla === 'protocolo' ? 'tit_historico_protocolos' : 'tit_historico';
        $cuenta = trim($cuenta);
        if ($cuenta !== '' && ($otro = Database::una("SELECT id, alumno FROM $tabla WHERE carrera = ? AND cuenta = ? AND id <> ? LIMIT 1", [$carrera, $cuenta, $excluir]))) {
            return 'Ya existe un registro de ' . $otro['alumno'] . ' con la cuenta ' . $cuenta . ' en esta licenciatura. Búscalo arriba y complétalo en lugar de crear otro.';
        }
        if (!$homonimo && ($otro = Database::una("SELECT id, alumno FROM $tabla WHERE carrera = ? AND alumno = ? AND id <> ? LIMIT 1", [$carrera, self::limpioNombre($alumno), $excluir]))) {
            return 'Ya existe un registro llamado «' . $otro['alumno'] . '» en esta licenciatura. Si es la misma persona, búscalo y complétalo; si es otra con el mismo nombre, marca «Es otra persona».';
        }
        return null;
    }

    private static function limpioNombre(string $s): string
    {
        return mb_substr(trim((string) preg_replace('/\s+/u', ' ', $s)), 0, 160);
    }

    /** Protocolo nuevo; si se indica un examen existente, queda ligado. @return int id del protocolo */
    public static function registrarProtocolo(array $in, int $usuarioId): int
    {
        $carrera = (string) ($in['carrera'] ?? '');
        $hid = (int) ($in['historico_id'] ?? 0);
        if ($msg = self::duplicado($carrera, (string) ($in['cuenta'] ?? ''), (string) ($in['alumno'] ?? ''), 'protocolo', !empty($in['homonimo']))) {
            throw new \InvalidArgumentException($msg);
        }
        $h = $hid > 0 ? Database::una('SELECT * FROM tit_historico WHERE id = ?', [$hid]) : null;
        if ($hid > 0 && !$h) {
            throw new \InvalidArgumentException('Registro de examen inexistente.');
        }
        if ($h) {
            Historico::cronologia(['fecha_protocolo' => (string) ($in['fecha_aprobacion'] ?? ''), 'fecha_previo' => (string) $h['fecha_previo'], 'fecha_examen' => (string) $h['fecha_examen']]);
        }
        $id = HistoricoProtocolos::crear($in, $usuarioId);
        if ($h) {
            self::ligar($id, $hid, $usuarioId);
        }
        return $id;
    }

    /**
     * Examen previo: se escribe en el protocolo y en el examen del expediente. Sin ninguno de los dos, se abre un registro de examen
     * (todavía sin fecha de examen) para guardarlo. @return array{protocolo:?int,historico:?int}
     */
    public static function registrarPrevio(array $in, int $usuarioId): array
    {
        $fecha = trim((string) ($in['fecha_previo'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha < '1988-01-01' || $fecha > date('Y-m-d')) {
            throw new \InvalidArgumentException('Indica la fecha del examen previo (no puede ser futura).');
        }
        $acta = trim((string) ($in['acta_previo'] ?? ''));
        if ($acta !== '' && !ctype_digit($acta)) {
            throw new \InvalidArgumentException('El acta del examen previo debe ser un número.');
        }
        $pid = (int) ($in['protocolo_id'] ?? 0);
        $hid = (int) ($in['historico_id'] ?? 0);
        $p = $pid > 0 ? Database::una('SELECT * FROM tit_historico_protocolos WHERE id = ?', [$pid]) : null;
        $h = $hid > 0 ? Database::una('SELECT * FROM tit_historico WHERE id = ?', [$hid]) : null;
        if (!$p && !$h) {
            if ($msg = self::duplicado((string) ($in['carrera'] ?? ''), (string) ($in['cuenta'] ?? ''), (string) ($in['alumno'] ?? ''), 'historico', !empty($in['homonimo']))) {
                throw new \InvalidArgumentException($msg);
            }
            $hid = Historico::crear((string) ($in['carrera'] ?? ''), (string) ($in['alumno'] ?? ''), null, $usuarioId);
            if (trim((string) ($in['cuenta'] ?? '')) !== '') {
                Historico::guardarCampo($hid, 'cuenta', (string) $in['cuenta'], $usuarioId);
            }
            $h = Database::una('SELECT * FROM tit_historico WHERE id = ?', [$hid]);
        }
        Historico::cronologia(['fecha_protocolo' => (string) ($h['fecha_protocolo'] ?? $p['fecha_aprobacion'] ?? ''), 'fecha_previo' => $fecha, 'fecha_examen' => (string) ($h['fecha_examen'] ?? '')]);
        if ($h) {
            Historico::guardarCampo((int) $h['id'], 'fecha_previo', $fecha, $usuarioId);
            Historico::guardarCampo((int) $h['id'], 'acta_previo', $acta, $usuarioId);
        }
        if ($p) {
            Database::ejecutar('UPDATE tit_historico_protocolos SET fecha_previo = ?, acta_previo = ? WHERE id = ?', [$fecha, $acta === '' ? null : (int) $acta, (int) $p['id']]);
        }
        return ['protocolo' => $p ? (int) $p['id'] : null, 'historico' => $h ? (int) $h['id'] : null];
    }

    /**
     * Examen definitivo: completa el registro de examen indicado o crea uno nuevo (con búsqueda de duplicados); si viene de un protocolo
     * lo liga y hereda sus fechas. @return int id del registro de examen
     */
    public static function registrarExamen(array $in, int $usuarioId): int
    {
        $hid = (int) ($in['historico_id'] ?? 0);
        $pid = (int) ($in['protocolo_id'] ?? 0);
        $p = $pid > 0 ? Database::una('SELECT * FROM tit_historico_protocolos WHERE id = ?', [$pid]) : null;
        if ($pid > 0 && !$p) {
            throw new \InvalidArgumentException('Protocolo inexistente.');
        }
        if ($p) {   // los datos de la persona y las etapas previas salen del protocolo
            $in = ['carrera' => $p['carrera'], 'alumno' => $p['alumno'], 'cuenta' => $p['cuenta'], 'titulo' => $in['titulo'] ?? '' ?: $p['titulo'],
                'fecha_protocolo' => $p['fecha_aprobacion'], 'fecha_previo' => $p['fecha_previo'], 'acta_previo' => $p['acta_previo']] + $in;
            $in['titulo'] = trim((string) ($in['titulo'] ?? '')) !== '' ? $in['titulo'] : $p['titulo'];
        }
        if ($hid > 0) {
            if (!Database::una('SELECT id FROM tit_historico WHERE id = ?', [$hid])) {
                throw new \InvalidArgumentException('Registro de examen inexistente.');
            }
            $campos = ['cuenta', 'modalidad', 'fecha_examen', 'fecha_protocolo', 'fecha_previo', 'acta_previo', 'titulo', 'director', 'director_email', 'codirector', 'codirector_email',
                'presidente', 'presidente_email', 'secretario', 'secretario_email', 'vocal', 'vocal_email', 'acta'];
            foreach ($campos as $c) {
                if (trim((string) ($in[$c] ?? '')) !== '') {
                    Historico::guardarCampo($hid, $c, (string) $in[$c], $usuarioId);
                }
            }
        } else {
            if ($msg = self::duplicado((string) ($in['carrera'] ?? ''), (string) ($in['cuenta'] ?? ''), (string) ($in['alumno'] ?? ''), 'historico', !empty($in['homonimo']))) {
                throw new \InvalidArgumentException($msg);
            }
            Historico::cronologia(['fecha_protocolo' => (string) ($in['fecha_protocolo'] ?? ''), 'fecha_previo' => (string) ($in['fecha_previo'] ?? ''), 'fecha_examen' => (string) ($in['fecha_examen'] ?? '')]);
            $hid = Historico::capturar($in, $usuarioId);
        }
        if ($p) {
            self::ligar((int) $p['id'], $hid, $usuarioId);
        }
        return $hid;
    }

    /** Las tres etapas de un expediente con su fecha (o null si falta). @return array{protocolo:?string,previo:?string,examen:?string} */
    public static function etapas(array $e): array
    {
        $p = $e['protocolo'];
        $h = $e['historico'];
        return [
            'protocolo' => ($h['fecha_protocolo'] ?? null) ?: ($p['fecha_aprobacion'] ?? null) ?: null,
            'previo' => ($h['fecha_previo'] ?? null) ?: ($p['fecha_previo'] ?? null) ?: null,
            'examen' => ($h['fecha_examen'] ?? null) ?: null,
        ];
    }
}
