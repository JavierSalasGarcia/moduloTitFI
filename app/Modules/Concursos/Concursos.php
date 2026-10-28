<?php
declare(strict_types=1);

namespace App\Modules\Concursos;

use App\Core\Auth;
use App\Core\Database;

/**
 * Concursos temporales (tablas con_*): convocatoria pública, envío de obras por usuarios registrados, prefiltro,
 * votación anónima con recibo y resultados. Las fases salen de las fechas del concurso.
 *
 * Voto anónimo: con_votantes guarda solo QUE una persona votó; con_votos guarda la obra votada con un recibo
 * aleatorio, sin usuario, hora ni consecutivo. El recibo se muestra una vez al votante para que compruebe su voto.
 */
final class Concursos
{
    public const FASES = [
        'proximo' => 'Próximamente', 'recepcion' => 'Recepción de obras', 'revision' => 'Prefiltro',
        'votacion' => 'Votación', 'computo' => 'Cómputo de resultados', 'resultados' => 'Resultados', 'cancelado' => 'Cancelado',
    ];

    private const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    // ------------------------------------------------------------------ permisos

    /** Crean, configuran y publican concursos: administración y Subdirección Académica. */
    public static function puedeGestionar(): bool
    {
        return Auth::tieneRol('admin') || self::esSubdirector();
    }

    /** El prefiltro lo hace la Subdirección Académica (la administración también puede verlo). No se publica en las bases. */
    public static function puedePrefiltrar(): bool
    {
        return self::puedeGestionar();
    }

    private static function esSubdirector(): bool
    {
        try {
            return \App\Modules\Titulacion\Services\SubdireccionFirma::esSubdirector();
        } catch (\Throwable) {
            return false;
        }
    }

    // ------------------------------------------------------------------ consulta

    public static function porSlug(string $slug): ?array
    {
        return preg_match('/^[a-z0-9-]{3,80}$/D', $slug) ? Database::una('SELECT * FROM con_concursos WHERE slug = ?', [$slug]) : null;
    }

    public static function porId(int $id): ?array
    {
        return Database::una('SELECT * FROM con_concursos WHERE id = ?', [$id]);
    }

    public static function fase(array $c, ?int $ahora = null): string
    {
        $t = $ahora ?? time();
        return match (true) {
            $c['estado'] === 'cancelado' => 'cancelado',
            $t < strtotime($c['publicacion']) => 'proximo',
            // La recepción puede seguir abierta durante la votación (ver recepcionAbierta): la votación manda en la fase.
            $t >= strtotime($c['votacion_inicio']) && $t <= strtotime($c['votacion_fin']) => 'votacion',
            $t < strtotime($c['votacion_inicio']) && $t <= strtotime($c['recepcion_fin']) => 'recepcion',
            $t < strtotime($c['votacion_inicio']) => 'revision',
            $t < strtotime($c['premiacion']) => 'computo',
            default => 'resultados',
        };
    }

    /** Se pueden enviar obras desde la publicación hasta el fin de la recepción, aunque la votación ya haya comenzado. */
    public static function recepcionAbierta(array $c, ?int $ahora = null): bool
    {
        $t = $ahora ?? time();
        return $c['estado'] === 'publicado' && $t >= strtotime($c['publicacion']) && $t <= strtotime($c['recepcion_fin']);
    }

    /** Cómo se llama lo que se sube en este concurso (para los botones y textos). */
    public static function sustantivo(array $c): string
    {
        return stripos($c['slug'] . ' ' . $c['titulo'], 'calaverita') !== false ? 'calaverita' : 'obra';
    }

    /** Visible para el público: publicado (los borradores solo los ve quien gestiona). */
    public static function visible(array $c): bool
    {
        return $c['estado'] === 'publicado' || ($c['estado'] !== 'cancelado' && self::puedeGestionar());
    }

    /** @return array{actuales:array,pasados:array} */
    public static function listar(): array
    {
        $r = ['actuales' => [], 'pasados' => []];
        foreach (Database::todas("SELECT * FROM con_concursos WHERE estado <> 'cancelado' ORDER BY premiacion DESC") as $c) {
            if (!self::visible($c)) {
                continue;
            }
            $c['fase'] = self::fase($c);
            $r[$c['fase'] === 'resultados' ? 'pasados' : 'actuales'][] = $c;
        }
        return $r;
    }

    public static function fechaHora(string $f): string
    {
        $t = strtotime($f);
        return (int) date('j', $t) . ' de ' . self::MESES[(int) date('n', $t)] . ' de ' . date('Y', $t) . ' a las ' . date('H:i', $t) . ' h';
    }

    public static function monto(?string $m): string
    {
        return $m === null || (float) $m <= 0 ? 'por anunciar' : '$' . number_format((float) $m, 2) . ' MXN';
    }

    /** Texto escapado donde las direcciones http(s) se vuelven enlaces. */
    private static function conEnlaces(string $t): string
    {
        return preg_replace_callback('~https?://[^\s<>]+~iu', static function (array $m): string {
            $u = rtrim($m[0], '.,;:)');
            return '<a href="' . $u . '" target="_blank" rel="noopener noreferrer">' . $u . '</a>' . substr($m[0], strlen($u));
        }, e($t)) ?? e($t);
    }

    /** Bases con sus datos (fechas, premios, límites) y un formato mínimo: «## título», «- viñeta», párrafos y enlaces. */
    public static function basesHtml(array $c): string
    {
        $texto = strtr((string) $c['bases'], [
            '{tematica}' => $c['tematica'], '{max_por_persona}' => (string) $c['max_por_persona'], '{max_caracteres}' => number_format((int) $c['max_caracteres']),
            '{recepcion_fin}' => self::fechaHora($c['recepcion_fin']), '{votacion_inicio}' => self::fechaHora($c['votacion_inicio']),
            '{votacion_fin}' => self::fechaHora($c['votacion_fin']), '{premiacion}' => self::fechaHora($c['premiacion']),
            '{premio_1}' => self::monto($c['premio_1']), '{premio_2}' => self::monto($c['premio_2']), '{premio_3}' => self::monto($c['premio_3']),
        ]);
        $html = '';
        $lista = false;
        foreach (preg_split('/\R/u', $texto) ?: [] as $l) {
            $l = trim($l);
            if (str_starts_with($l, '- ')) {
                $html .= ($lista ? '' : '<ul>') . '<li>' . self::conEnlaces(substr($l, 2)) . '</li>';
                $lista = true;
                continue;
            }
            if ($lista) {
                $html .= '</ul>';
                $lista = false;
            }
            if ($l === '') {
                continue;
            }
            $html .= str_starts_with($l, '## ') ? '<h3>' . e(substr($l, 3)) . '</h3>' : '<p>' . self::conEnlaces($l) . '</p>';
        }
        return $html . ($lista ? '</ul>' : '');
    }

    // ------------------------------------------------------------------ obras

    public static function misObras(int $concursoId, int $usuarioId): array
    {
        return Database::todas("SELECT * FROM con_participaciones WHERE concurso_id = ? AND usuario_id = ? AND estado <> 'retirada' ORDER BY numero", [$concursoId, $usuarioId]);
    }

    /**
     * Envía o corrige una obra. Solo durante la recepción; una obra ya revisada no se modifica.
     * @return int número de la obra
     */
    public static function enviar(array $c, int $usuarioId, string $titulo, string $texto, ?int $obraId = null): int
    {
        if (!self::recepcionAbierta($c)) {
            throw new \InvalidArgumentException('La recepción de obras no está abierta.');
        }
        $titulo = trim(preg_replace('/\s+/u', ' ', $titulo) ?? '');
        $texto = trim(str_replace("\r\n", "\n", $texto));
        if (mb_strlen($titulo) < 3 || mb_strlen($titulo) > 120) {
            throw new \InvalidArgumentException('Escribe un título de 3 a 120 caracteres.');
        }
        if (mb_strlen($texto) < 80 || mb_strlen($texto) > (int) $c['max_caracteres']) {
            throw new \InvalidArgumentException('La obra debe tener entre 80 y ' . number_format((int) $c['max_caracteres']) . ' caracteres.');
        }
        if ($obraId !== null) {
            $o = Database::una('SELECT * FROM con_participaciones WHERE id = ? AND concurso_id = ? AND usuario_id = ?', [$obraId, (int) $c['id'], $usuarioId]);
            if (!$o || $o['estado'] !== 'enviada') {
                throw new \InvalidArgumentException('Esa obra ya fue revisada y no se puede modificar.');
            }
            Database::ejecutar('UPDATE con_participaciones SET titulo = ?, texto = ?, actualizado_en = NOW() WHERE id = ?', [$titulo, $texto, $obraId]);
            return (int) $o['numero'];
        }
        $vigentes = (int) Database::valor("SELECT COUNT(*) FROM con_participaciones WHERE concurso_id = ? AND usuario_id = ? AND estado IN ('enviada', 'aprobada')", [(int) $c['id'], $usuarioId]);
        if ($vigentes >= (int) $c['max_por_persona']) {
            throw new \InvalidArgumentException('Ya enviaste ' . $vigentes . ' obra(s), el máximo por persona.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $numero = (int) Database::valor('SELECT COALESCE(MAX(numero), 0) + 1 FROM con_participaciones WHERE concurso_id = ? FOR UPDATE', [(int) $c['id']]);
            Database::ejecutar('INSERT INTO con_participaciones (concurso_id, numero, usuario_id, titulo, texto) VALUES (?, ?, ?, ?, ?)', [(int) $c['id'], $numero, $usuarioId, $titulo, $texto]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $numero;
    }

    public static function retirar(array $c, int $usuarioId, int $obraId): void
    {
        if (!self::recepcionAbierta($c)) {
            throw new \InvalidArgumentException('La recepción ya cerró.');
        }
        $st = Database::pdo()->prepare("UPDATE con_participaciones SET estado = 'retirada', actualizado_en = NOW() WHERE id = ? AND concurso_id = ? AND usuario_id = ? AND estado = 'enviada'");
        $st->execute([$obraId, (int) $c['id'], $usuarioId]);
        if ($st->rowCount() === 0) {
            throw new \InvalidArgumentException('Esa obra ya fue revisada y no se puede retirar.');
        }
    }

    /** Dictamen del prefiltro sobre una obra; se avisa al autor sin revelar quién revisó. */
    public static function dictaminar(array $c, int $obraId, bool $aprobada, string $motivo, int $revisorId): void
    {
        $fase = self::fase($c);
        // Con la votación en curso solo se pueden dictaminar las obras nuevas que llegan mientras siga abierta la recepción;
        // de las ya aprobadas solo se puede retirar la aprobación.
        $nuevaEnVotacion = $fase === 'votacion' && self::recepcionAbierta($c);
        if (!in_array($fase, ['recepcion', 'revision'], true) && !$nuevaEnVotacion && !($aprobada === false && $fase === 'votacion')) {
            throw new \InvalidArgumentException('El prefiltro ya cerró: la votación comenzó.');
        }
        $motivo = mb_substr(trim($motivo), 0, 600);
        if (!$aprobada && mb_strlen($motivo) < 10) {
            throw new \InvalidArgumentException('Explica brevemente por qué no pasa (se le envía al autor).');
        }
        $o = Database::una("SELECT p.*, u.email, u.nombre FROM con_participaciones p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ? AND p.concurso_id = ? AND p.estado <> 'retirada'", [$obraId, (int) $c['id']]);
        if (!$o) {
            throw new \InvalidArgumentException('Obra inexistente.');
        }
        if ($fase === 'votacion' && $aprobada && $o['estado'] !== 'enviada') {
            throw new \InvalidArgumentException('La votación ya comenzó: solo se aprueban obras que aún no se revisaban.');
        }
        Database::ejecutar('UPDATE con_participaciones SET estado = ?, motivo = ?, revisado_por = ?, revisado_en = NOW() WHERE id = ?',
            [$aprobada ? 'aprobada' : 'rechazada', $motivo !== '' ? $motivo : null, $revisorId, $obraId]);
        $cuerpo = $aprobada
            ? 'Tu obra «' . $o['titulo'] . '» pasó el prefiltro y participará en la votación del ' . self::fechaHora($c['votacion_inicio']) . ' al ' . self::fechaHora($c['votacion_fin']) . ' como la número ' . $o['numero'] . '.'
            : 'Tu obra «' . $o['titulo'] . '» no pasó el prefiltro: ' . $motivo . (self::recepcionAbierta($c) ? ' Puedes enviar otra mientras siga abierta la recepción.' : '');
        try {
            \App\Modules\Notificaciones\Notificaciones::crear((string) $o['email'], $c['titulo'] . ': ' . ($aprobada ? 'obra aprobada' : 'obra no aprobada'), $cuerpo, 'concursos/' . $c['slug'], 'concursos');
        } catch (\Throwable) {
        }
    }

    // ------------------------------------------------------------------ votación

    public static function yaVoto(int $concursoId, int $usuarioId): bool
    {
        return (bool) Database::valor('SELECT 1 FROM con_votantes WHERE concurso_id = ? AND usuario_id = ?', [$concursoId, $usuarioId]);
    }

    /** Números de las obras que la persona marcó como favoritas. */
    public static function favoritos(int $concursoId, int $usuarioId): array
    {
        if ($usuarioId <= 0) {
            return [];
        }
        return array_map('intval', array_column(Database::todas('SELECT numero FROM con_favoritos WHERE concurso_id = ? AND usuario_id = ? ORDER BY creado_en', [$concursoId, $usuarioId]), 'numero'));
    }

    /** Agrega o quita una obra de las favoritas (solo obras que participan en la votación). @return bool true si queda como favorita */
    public static function alternarFavorito(array $c, int $usuarioId, int $numero): bool
    {
        if (self::fase($c) !== 'votacion' || $c['estado'] !== 'publicado') {
            throw new \InvalidArgumentException('Las favoritas se marcan mientras la votación está abierta.');
        }
        if (self::yaVoto((int) $c['id'], $usuarioId)) {
            throw new \InvalidArgumentException('Ya votaste; tus favoritas ya no se modifican.');
        }
        $o = Database::una("SELECT usuario_id FROM con_participaciones WHERE concurso_id = ? AND numero = ? AND estado = 'aprobada'", [(int) $c['id'], $numero]);
        if (!$o) {
            throw new \InvalidArgumentException('Esa obra no participa en la votación.');
        }
        if ((int) $o['usuario_id'] === $usuarioId) {
            throw new \InvalidArgumentException('Tu propia obra no se marca como favorita.');
        }
        $st = Database::pdo()->prepare('DELETE FROM con_favoritos WHERE concurso_id = ? AND usuario_id = ? AND numero = ?');
        $st->execute([(int) $c['id'], $usuarioId, $numero]);
        if ($st->rowCount() > 0) {
            return false;
        }
        Database::ejecutar('INSERT IGNORE INTO con_favoritos (concurso_id, usuario_id, numero) VALUES (?, ?, ?)', [(int) $c['id'], $usuarioId, $numero]);
        return true;
    }

    /** Registra el voto anónimo y devuelve el recibo. */
    public static function votar(array $c, int $usuarioId, int $numero): string
    {
        if (self::fase($c) !== 'votacion' || $c['estado'] !== 'publicado') {
            throw new \InvalidArgumentException('La votación no está abierta.');
        }
        $o = Database::una("SELECT id, usuario_id FROM con_participaciones WHERE concurso_id = ? AND numero = ? AND estado = 'aprobada'", [(int) $c['id'], $numero]);
        if (!$o) {
            throw new \InvalidArgumentException('Esa obra no participa en la votación.');
        }
        if ((int) $o['usuario_id'] === $usuarioId) {
            throw new \InvalidArgumentException('No puedes votar por una obra propia.');
        }
        if (!in_array($numero, self::favoritos((int) $c['id'], $usuarioId), true)) {
            throw new \InvalidArgumentException('Marca tus calaveritas favoritas con la estrella y elige la mejor entre ellas.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare('INSERT IGNORE INTO con_votantes (concurso_id, usuario_id) VALUES (?, ?)');
            $st->execute([(int) $c['id'], $usuarioId]);
            if ($st->rowCount() === 0) {
                throw new \InvalidArgumentException('Ya votaste en este concurso.');
            }
            do {
                $recibo = 'CV-' . implode('-', str_split(strtoupper(bin2hex(random_bytes(5))), 5));
                $ok = $pdo->prepare('INSERT IGNORE INTO con_votos (recibo, concurso_id, participacion_id) VALUES (?, ?, ?)');
                $ok->execute([$recibo, (int) $c['id'], (int) $o['id']]);
            } while ($ok->rowCount() === 0);
            $pdo->prepare('DELETE FROM con_favoritos WHERE concurso_id = ? AND usuario_id = ?')->execute([(int) $c['id'], $usuarioId]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return $recibo;
    }

    /** Comprobación de un recibo: número y título de la obra votada. */
    public static function verificar(array $c, string $recibo): ?array
    {
        $recibo = strtoupper(trim($recibo));
        if (!preg_match('/^CV-[0-9A-F]{5}-[0-9A-F]{5}$/D', $recibo)) {
            return null;
        }
        return Database::una('SELECT v.recibo, p.numero, p.titulo FROM con_votos v JOIN con_participaciones p ON p.id = v.participacion_id WHERE v.recibo = ? AND v.concurso_id = ?', [$recibo, (int) $c['id']]);
    }

    /** Todos los recibos con la obra votada (se publica al cerrar la votación), en orden de recibo. */
    public static function recibos(array $c): array
    {
        return Database::todas('SELECT v.recibo, p.numero FROM con_votos v JOIN con_participaciones p ON p.id = v.participacion_id WHERE v.concurso_id = ? ORDER BY v.recibo', [(int) $c['id']]);
    }

    // ------------------------------------------------------------------ resultados

    /** Obras aprobadas con sus votos, ordenadas (votos, desempate del prefiltro, número). */
    public static function conteo(array $c): array
    {
        $filas = Database::todas(
            "SELECT p.id, p.numero, p.titulo, p.texto, p.desempate, p.usuario_id, u.nombre AS autor, COUNT(v.recibo) AS votos
             FROM con_participaciones p JOIN usuarios u ON u.id = p.usuario_id LEFT JOIN con_votos v ON v.participacion_id = p.id
             WHERE p.concurso_id = ? AND p.estado = 'aprobada' GROUP BY p.id ORDER BY votos DESC, p.desempate IS NULL, p.desempate, p.numero",
            [(int) $c['id']]
        );
        foreach ($filas as $i => &$f) {
            $f['votos'] = (int) $f['votos'];
            $f['lugar'] = $i + 1;
        }
        return $filas;
    }

    /** Grupos empatados que afectan los tres primeros lugares (para que el prefiltro decida el orden). */
    public static function empates(array $conteo): array
    {
        $grupos = [];
        foreach ($conteo as $f) {
            $grupos[$f['votos']][] = $f;
        }
        $r = [];
        foreach ($grupos as $votos => $g) {
            if (count($g) > 1 && $votos > 0 && min(array_column($g, 'lugar')) <= 3) {
                $r[] = $g;
            }
        }
        return $r;
    }

    /** @param array<int,int> $orden participacion_id => posición dentro del empate */
    public static function desempatar(array $c, array $orden): void
    {
        foreach ($orden as $id => $pos) {
            Database::ejecutar('UPDATE con_participaciones SET desempate = ? WHERE id = ? AND concurso_id = ?', [max(1, min(99, (int) $pos)), (int) $id, (int) $c['id']]);
        }
    }

    // ------------------------------------------------------------------ gestión

    public const CAMPOS = ['titulo', 'slug', 'resumen', 'tematica', 'bases', 'estado', 'publicacion', 'recepcion_fin', 'votacion_inicio', 'votacion_fin', 'premiacion',
        'premio_1', 'premio_2', 'premio_3', 'max_por_persona', 'max_caracteres'];

    /** Valida y guarda un concurso. @return int id */
    public static function guardar(?int $id, array $in, int $usuarioId): int
    {
        $d = [];
        foreach (self::CAMPOS as $k) {
            $d[$k] = trim(str_replace("\r\n", "\n", (string) ($in[$k] ?? '')));
        }
        $d['slug'] = strtolower($d['slug']);
        if (mb_strlen($d['titulo']) < 5 || !preg_match('/^[a-z0-9-]{3,80}$/D', $d['slug']) || mb_strlen($d['bases']) < 50) {
            throw new \InvalidArgumentException('Revisa el título, la dirección (minúsculas, números y guiones) y las bases.');
        }
        if (!in_array($d['estado'], ['borrador', 'publicado', 'cancelado'], true)) {
            throw new \InvalidArgumentException('Estado no válido.');
        }
        foreach (['publicacion', 'recepcion_fin', 'votacion_inicio', 'votacion_fin', 'premiacion'] as $k) {
            $t = strtotime(str_replace('T', ' ', $d[$k]));
            if ($t === false) {
                throw new \InvalidArgumentException('Revisa las fechas.');
            }
            $d[$k] = date('Y-m-d H:i:s', $t);
        }
        if (!($d['publicacion'] < $d['recepcion_fin'] && $d['recepcion_fin'] < $d['votacion_fin'] && $d['votacion_inicio'] < $d['votacion_fin'] && $d['votacion_fin'] <= $d['premiacion'])) {
            throw new \InvalidArgumentException('Las fechas deben ir en orden: publicación, fin de recepción (puede ser después de iniciar la votación), inicio y fin de votación, premiación; la recepción debe cerrar antes de que termine la votación.');
        }
        foreach (['premio_1', 'premio_2', 'premio_3'] as $k) {
            $d[$k] = $d[$k] === '' ? null : (is_numeric(str_replace([',', '$'], '', $d[$k])) ? (float) str_replace([',', '$'], '', $d[$k]) : throw new \InvalidArgumentException('Los premios deben ser montos.'));
        }
        $d['max_por_persona'] = max(1, min(10, (int) $d['max_por_persona']));
        $d['max_caracteres'] = max(200, min(10000, (int) $d['max_caracteres']));
        $otro = Database::valor('SELECT id FROM con_concursos WHERE slug = ? AND id <> ?', [$d['slug'], (int) $id]);
        if ($otro) {
            throw new \InvalidArgumentException('Ya existe un concurso con esa dirección.');
        }
        $cols = array_keys($d);
        if ($id) {
            Database::ejecutar('UPDATE con_concursos SET ' . implode(', ', array_map(static fn(string $k): string => "$k = ?", $cols)) . ' WHERE id = ?', [...array_values($d), $id]);
            return $id;
        }
        return Database::ejecutar('INSERT INTO con_concursos (' . implode(', ', $cols) . ', creado_por) VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ', ?)', [...array_values($d), $usuarioId]);
    }
}
