<?php

declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use App\Core\Database;

/**
 * Solicitudes a los HH. Consejos que redactan los profesores y coordinadores (docs/CONSEJOS.md): borrador → enviada a la
 * coordinación → Vo. Bo. o enterado → incorporada como caso de la siguiente reunión. Por acuerdo del H. Consejo de Gobierno
 * (AG28, 19 de noviembre de 2026) toda solicitud lleva el Vo. Bo. o de enterado de la Coordinación de Docencia respectiva o
 * de la de Estudios Avanzados.
 */
final class Solicitudes
{
    public const ACUERDO_FECHA = '19 de noviembre de 2026';
    public const ACUERDO_CLAVE = 'AG28';
    public const ESTADOS = [
        'borrador' => 'Borrador', 'enviada' => 'Con la coordinación', 'visto_bueno' => 'Con Vo. Bo. · espera reunión',
        'devuelta' => 'Devuelta', 'incorporada' => 'En el acta', 'cancelada' => 'Cancelada',
    ];

    /** Fundamento que lleva todo oficio de solicitud. */
    public static function fundamento(): string
    {
        return 'Con fundamento en el acuerdo del H. Consejo de Gobierno del ' . self::ACUERDO_FECHA . ' (caso ' . self::ACUERDO_CLAVE
            . '), todas las solicitudes a los H.H. Consejos Académico y de Gobierno deben contar con el Vo. Bo. o de enterado de la Coordinación de Docencia respectiva o de la Coordinación de Estudios Avanzados, según sea el caso.';
    }

    /**
     * Coordinaciones que pueden dar el Vo. Bo.: las de Docencia de cada licenciatura y la de Estudios Avanzados / posgrado,
     * con quien las ocupa hoy.
     * @return array<string,array{clave:string,usuario_id:int,nombre:string,coordinacion:string}>
     */
    public static function coordinaciones(): array
    {
        $r = [];
        try {
            foreach (\App\Modules\Constancias\Services\Firmantes::todos() as $f) {
                $dep = (string) ($f['departamento'] ?? '');
                $esDocencia = (bool) preg_match('/^Coordinaci[oó]n de Docencia/iu', $dep);
                $esPosgrado = (bool) preg_match('/Estudios Avanzados|^Coordinaci[oó]n (de la|del) (Maestr|Doctor|Especial)/iu', $dep);
                if (!$esDocencia && !$esPosgrado) {
                    continue;
                }
                $etiqueta = $esDocencia ? $dep : ($dep !== '' ? $dep : 'Coordinación de Estudios Avanzados');
                $r[(string) $f['clave']] = ['clave' => (string) $f['clave'], 'usuario_id' => (int) $f['usuario_id'], 'nombre' => (string) $f['nombre'], 'coordinacion' => $etiqueta];
            }
        } catch (\Throwable) {
            // Sin el directorio de firmantes no hay coordinaciones que ofrecer.
        }
        uasort($r, static fn($a, $b) => [$a['coordinacion'], $a['nombre']] <=> [$b['coordinacion'], $b['nombre']]);
        return $r;
    }

    public static function folio(int $id): string
    {
        return sprintf('SC-%s-%04d', date('Y'), $id);
    }

    /** ¿Esta persona es la coordinadora o coordinador a quien se dirige la solicitud? */
    public static function esCoordinadorDe(array $s, ?int $uid): bool
    {
        return $uid !== null && $uid === (int) $s['coordinador_id'];
    }

    public static function nombreCompleto(int $uid): string
    {
        $u = Database::una('SELECT nombre, grado FROM usuarios WHERE id = ?', [$uid]);
        return $u ? self::conGrado($u) : '';
    }

    /** «Dr. Nombre»: el grado solo se antepone si el nombre registrado no lo trae ya. */
    public static function conGrado(array $u): string
    {
        $nombre = trim((string) $u['nombre']);
        $grado = trim((string) ($u['grado'] ?? ''));
        return $grado !== '' && !str_starts_with(mb_strtolower($nombre), mb_strtolower(rtrim($grado, '.'))) ? $grado . ' ' . $nombre : $nombre;
    }

    /** Reunión a la que llegan las solicitudes con Vo. Bo.: la próxima que aún no empieza. */
    public static function siguienteReunion(): ?int
    {
        $id = Database::valor("SELECT id FROM consejos_reuniones WHERE estado = 'preparacion' ORDER BY (academico_fecha IS NULL), academico_fecha, id LIMIT 1");
        return $id ? (int) $id : null;
    }

    /** Convierte la solicitud con Vo. Bo. en un caso de la reunión; el acta lo recoge como cualquier otro. @return int id del caso */
    public static function incorporar(array $s, int $reunionId): int
    {
        $numero = (int) Database::valor('SELECT COALESCE(MAX(numero), 0) + 1 FROM consejos_asuntos WHERE reunion_id = ? AND grupo = ?', [$reunionId, $s['grupo']]);
        $quien = self::nombreCompleto((int) $s['vobo_por']);
        $nota = '<p><i>' . ($s['vobo_tipo'] === 'enterado' ? 'De enterado' : 'Vo. Bo.') . ': ' . htmlspecialchars($quien, ENT_QUOTES, 'UTF-8') . ', ' . htmlspecialchars((string) $s['coordinacion'], ENT_QUOTES, 'UTF-8') . '.</i></p>';
        $ant = (string) $s['antecedentes_html'] . $nota;
        $aid = Database::ejecutar(
            "INSERT INTO consejos_asuntos (reunion_id, grupo, numero, origen, solicitud_id, solicitud_html, antecedentes_html, texto_busqueda, creado_por) VALUES (?, ?, ?, 'academico', ?, ?, ?, ?, ?)",
            [$reunionId, $s['grupo'], $numero, (int) $s['id'], $s['solicitud_html'], $ant, trim(Html::texto((string) $s['solicitud_html']) . "\n" . Html::texto($ant)), (int) $s['solicitante_id']]
        );
        $ids = array_filter(array_map('intval', explode(',', (string) $s['profesores'])));
        if ($s['grupo'] === 'profesor') {
            $ids[] = (int) $s['solicitante_id'];
        }
        foreach (array_unique($ids) as $uid) {
            Database::ejecutar('INSERT IGNORE INTO consejos_asunto_profesores (asunto_id, usuario_id) SELECT ?, id FROM usuarios WHERE id = ? AND activo = 1', [$aid, $uid]);
        }
        Database::ejecutar("UPDATE consejos_solicitudes SET estado = 'incorporada', reunion_id = ?, asunto_id = ? WHERE id = ?", [$reunionId, $aid, (int) $s['id']]);
        $r = Database::una('SELECT etiqueta FROM consejos_reuniones WHERE id = ?', [$reunionId]);
        self::avisar((int) $s['solicitante_id'], 'Tu solicitud ' . $s['folio'] . ' está en el acta', 'Con el Vo. Bo. de ' . $s['coordinacion'] . ', tu solicitud ' . $s['folio'] . ' quedó como caso ' . Texto::clave((string) $s['grupo'], $numero) . ' de la reunión ' . ($r['etiqueta'] ?? '') . '.', 'consejos/solicitudes/' . (int) $s['id'], 'Ver mi solicitud');
        return $aid;
    }

    /** Lleva a la reunión las solicitudes con Vo. Bo. que esperaban una. @return int cuántas */
    public static function incorporarPendientes(int $reunionId): int
    {
        $n = 0;
        foreach (Database::todas("SELECT * FROM consejos_solicitudes WHERE estado = 'visto_bueno' ORDER BY vobo_en, id") as $s) {
            self::incorporar($s, $reunionId);
            $n++;
        }
        return $n;
    }

    /**
     * Quienes reciben el aviso de cada solicitud que llega a la cartera del Consejo: el Subdirector Académico (secretario de los
     * Consejos) y su asistente.
     * @return list<array{id:int,nombre:string}>
     */
    public static function destinatariosCartera(): array
    {
        $r = [];
        try {
            $correos = [];
            $sub = \App\Modules\Titulacion\Services\Autoridades::subdirector();
            if (!empty($sub['email'])) {
                $correos[] = strtolower((string) $sub['email']);
            }
            foreach (Database::todas('SELECT email FROM tit_asistentes WHERE activo = 1') as $a) {
                $correos[] = strtolower((string) $a['email']);
            }
            foreach (array_unique($correos) as $c) {
                $u = Database::una('SELECT id, nombre FROM usuarios WHERE LOWER(email) = ? AND activo = 1', [$c]);
                if ($u) {
                    $r[(int) $u['id']] = ['id' => (int) $u['id'], 'nombre' => (string) $u['nombre']];
                }
            }
        } catch (\Throwable) {
            // sin directorio de autoridades no hay a quién avisar
        }
        return array_values($r);
    }

    /** Observaciones y respuestas de la solicitud, en orden. */
    public static function historial(int $id): array
    {
        return Database::todas('SELECT o.*, u.nombre FROM consejos_solicitud_obs o JOIN usuarios u ON u.id = o.autor_id WHERE o.solicitud_id = ? ORDER BY o.id', [$id]);
    }

    /** Ronda de observaciones en curso (0 si nunca se ha devuelto). */
    public static function ronda(int $id): int
    {
        return (int) Database::valor("SELECT COALESCE(MAX(ronda), 0) FROM consejos_solicitud_obs WHERE solicitud_id = ? AND tipo = 'observacion'", [$id]);
    }

    /** Aviso en la bandeja y por correo; un fallo nunca interrumpe la acción. */
    public static function avisar(int $uid, string $asunto, string $texto, string $ruta, string $boton): void
    {
        try {
            $u = Database::una('SELECT nombre, email FROM usuarios WHERE id = ? AND activo = 1', [$uid]);
            if ($u && class_exists(\App\Modules\Constancias\Services\Aviso::class)) {
                \App\Modules\Constancias\Services\Aviso::enviar((string) $u['email'], (string) $u['nombre'], $asunto, $texto, $ruta, $boton);
            }
        } catch (\Throwable) {
            // opcional
        }
    }
}
