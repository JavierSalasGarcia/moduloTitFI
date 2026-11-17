<?php
declare(strict_types=1);

namespace App\Modules\Posgrado\Services;

use App\Core\App;
use App\Core\Audit;
use App\Core\Database;
use App\Modules\Posgrado\Catalogo;
use App\Modules\Posgrado\Flujo;
use App\Modules\Posgrado\PosgradoRepository;
use RuntimeException;
use Throwable;

/**
 * El egresado deja un trámite de evaluación de grado que inició por error (por ejemplo, con la modalidad equivocada).
 * Misma regla que en licenciatura (Titulacion\Services\Abandono):
 * - Si NADIE más intervino, el trámite se BORRA (registros, firmas pendientes cuyos enlaces dejan de servir y la
 *   carpeta storage/posgrado/<id>/); solo queda la auditoría con folio y modalidad.
 * - Si otra persona intervino, pasa a «abandonado» (en pausa) sin borrar nada, y puede retomarse donde se quedó.
 * Una firma PENDIENTE no es intervención; una firma HECHA por el director, el sínodo o la Coordinación sí.
 */
final class Abandono
{
    /** Hitos que registra Apoyo a Grado, en palabras del egresado. */
    private const HITOS = [
        'cotejo' => 'el cotejo de tus originales',
        'externos' => 'el alta de tus sinodales externos',
        'programacion' => 'la programación de tu examen',
        'acuse_cierre' => 'el cierre de las invitaciones al sínodo',
        'resultado' => 'el resultado de tu examen',
        'expediente' => 'la integración de tu expediente',
    ];

    private static function el(?string $dt): string
    {
        $t = $dt ? strtotime($dt) : false;
        return $t ? ' el ' . date('j/n/Y', $t) : '';
    }

    public static function nombreModalidad(array $t): string
    {
        $m = (string) ($t['modalidad'] ?? '');
        return $m !== '' ? (Catalogo::modalidades()[$m] ?? $m) : 'modalidad sin elegir';
    }

    /** Trámites abiertos del egresado, el más reciente primero. */
    public static function activos(int $alumnoId): array
    {
        return Database::todas("SELECT id, folio, nivel, modalidad, titulo, momento, estado, creado_en FROM pos_tramites WHERE alumno_id = ? AND estado = 'abierto' ORDER BY creado_en DESC, id DESC", [$alumnoId]);
    }

    public static function impedimento(array $t): ?string
    {
        return match ((string) $t['estado']) {
            'abierto' => null,
            'concluido' => 'Este trámite ya concluyó: no se puede borrar ni dejar.',
            'abandonado' => 'Este trámite ya está en pausa.',
            default => 'Este trámite ya no está activo.',
        };
    }

    /** @return string[] acciones de otras personas, en lenguaje llano; vacío si solo actuó el egresado */
    public static function huboIntervencion(array $t): array
    {
        $tid = (int) $t['id'];
        $yo = (int) $t['alumno_id'];
        $m = [];
        foreach (Database::todas(
            'SELECT clave, tipo, estado, revisado_en, creado_en, subido_por FROM pos_documentos WHERE tramite_id = ? AND ((revisado_por IS NOT NULL AND revisado_por <> ?) OR subido_por <> ?) ORDER BY id',
            [$tid, $yo, $yo]
        ) as $d) {
            $n = '«' . (Catalogo::requisitos()[$d['clave']]['nombre'] ?? $d['clave']) . '»';
            $m[] = match (true) {
                $d['tipo'] === 'fisico' => 'Apoyo a Grado registró que entregaste en físico ' . $n . self::el($d['creado_en']) . '.',
                $d['estado'] === 'validado' => 'Apoyo a Grado validó ' . $n . self::el($d['revisado_en']) . '.',
                $d['estado'] === 'devuelto' => 'Apoyo a Grado te devolvió ' . $n . ' con una observación' . self::el($d['revisado_en']) . '.',
                default => 'Apoyo a Grado revisó ' . $n . self::el($d['revisado_en'] ?? $d['creado_en']) . '.',
            };
        }
        foreach (Database::todas(
            "SELECT documento, rol, orden, nombre, firmada_en FROM pos_firmas WHERE tramite_id = ? AND estado = 'firmada' AND rol <> 'egresado' ORDER BY firmada_en",
            [$tid]
        ) as $f) {
            $quien = match ((string) $f['rol']) {
                'director' => 'Tu director(a) ' . $f['nombre'],
                'coordinacion' => 'La Coordinación de Estudios Avanzados',
                default => 'Tu sinodal ' . $f['nombre'],
            };
            $m[] = $quien . ' firmó ' . (Catalogo::firmables()[$f['documento']]['que'] ?? 'un documento') . self::el($f['firmada_en']) . '.';
        }
        foreach (Database::todas('SELECT clave, completado_en FROM pos_hitos WHERE tramite_id = ? AND completado_por <> ? ORDER BY completado_en', [$tid, $yo]) as $h) {
            $m[] = 'Apoyo a Grado registró ' . (self::HITOS[$h['clave']] ?? '«' . $h['clave'] . '»') . self::el($h['completado_en']) . '.';
        }
        foreach (Database::todas('SELECT clave, nombre, generado_en FROM pos_generados WHERE tramite_id = ? AND generado_por <> ? ORDER BY id', [$tid, $yo]) as $g) {
            $m[] = 'Apoyo a Grado emitió «' . $g['nombre'] . '»' . self::el($g['generado_en']) . '.';
        }
        if ($m === [] && !empty($t['responsable_id']) && (int) $t['responsable_id'] !== $yo) {
            $m[] = 'Apoyo a Grado ya tomó tu trámite para atenderlo.';
        }
        if ($m === [] && ($ultimo = Database::valor('SELECT MAX(creado_en) FROM pos_eventos WHERE tramite_id = ? AND usuario_id <> ?', [$tid, $yo]))) {
            $m[] = 'Otras personas registraron movimientos en tu trámite (el último' . self::el((string) $ultimo) . ').';
        }
        return array_values(array_unique($m));
    }

    /** @return array{texto:string,convocado:bool}|null examen con fecha que todavía no pasa */
    public static function examenProgramado(array $t): ?array
    {
        $hitos = PosgradoRepository::hitos((int) $t['id']);
        $p = $hitos['programacion']['datos'] ?? null;
        if (!is_array($p) || isset($hitos['resultado']) || (string) ($p['fecha'] ?? '') < date('Y-m-d')) {
            return null;
        }
        return ['texto' => 'tu examen de grado del ' . Flujo::fechaLarga((string) $p['fecha']) . ' a las ' . ($p['hora'] ?? '') . ' h', 'convocado' => true];
    }

    /** @return string[] */
    public static function seBorra(array $t): array
    {
        $tid = (int) $t['id'];
        $r = ['Los datos que capturaste.'];
        if ((int) Database::valor('SELECT COUNT(*) FROM pos_documentos WHERE tramite_id = ? AND ruta IS NOT NULL', [$tid]) + (int) Database::valor('SELECT COUNT(*) FROM pos_generados WHERE tramite_id = ?', [$tid]) > 0) {
            $r[] = 'Los PDF que subiste y los formatos que se generaron con tus datos.';
        }
        if ((int) Database::valor("SELECT COUNT(*) FROM pos_firmas WHERE tramite_id = ? AND estado = 'pendiente' AND rol <> 'egresado'", [$tid]) > 0) {
            $r[] = 'Las solicitudes de firma que enviamos (por ejemplo, a tu director): sus enlaces dejarán de servir.';
        }
        return $r;
    }

    /** @return string[] */
    public static function seConserva(array $t): array
    {
        $tid = (int) $t['id'];
        $r = ['Tus datos, tus documentos y todo tu avance, tal como están hoy.'];
        if ((int) Database::valor("SELECT COUNT(*) FROM pos_generados WHERE tramite_id = ? AND vigente = 1 AND clave IN ('constancias_revisores','constancias_direccion')", [$tid]) > 0) {
            $r[] = 'Las constancias emitidas a quienes participaron; siguen siendo válidas para ellos.';
        }
        if ((int) Database::valor("SELECT COUNT(*) FROM pos_firmas WHERE tramite_id = ? AND estado = 'firmada' AND rol <> 'egresado'", [$tid]) > 0) {
            $r[] = 'Las firmas que ya hicieron tu director, tu sínodo o la Coordinación.';
        }
        if ((int) Database::valor("SELECT COUNT(*) FROM pos_firmas WHERE tramite_id = ? AND estado = 'pendiente'", [$tid]) > 0) {
            $r[] = 'Las firmas que estaban pendientes quedan en pausa: sus enlaces no permitirán firmar hasta que lo retomes.';
        }
        return $r;
    }

    /** @return array{accion:string,motivos:string[],examen:?array,borra:string[],conserva:string[],impedimento:?string} */
    public static function plan(array $t): array
    {
        $motivos = self::huboIntervencion($t);
        return [
            'accion' => $motivos === [] ? 'borrar' : 'abandonar',
            'motivos' => $motivos,
            'examen' => self::examenProgramado($t),
            'borra' => $motivos === [] ? self::seBorra($t) : [],
            'conserva' => $motivos === [] ? [] : self::seConserva($t),
            'impedimento' => self::impedimento($t),
        ];
    }

    /** «tu examen…» → «el examen…» para la bitácora y los avisos al personal. */
    private static function paraPersonal(string $texto): string
    {
        return (string) preg_replace('/^tu /u', 'el ', $texto);
    }

    /** @return string 'borrado' o 'abandonado' */
    public static function dejar(array $t, int $usuarioId, string $motivo = ''): string
    {
        if (($imp = self::impedimento($t)) !== null) {
            throw new RuntimeException($imp);
        }
        if (self::huboIntervencion($t) === []) {
            self::borrar($t, $usuarioId);
            return 'borrado';
        }
        self::abandonar($t, $usuarioId, $motivo);
        return 'abandonado';
    }

    public static function borrar(array $t, int $usuarioId): void
    {
        $tid = (int) $t['id'];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $fila = Database::una('SELECT id, alumno_id, estado, responsable_id FROM pos_tramites WHERE id = ? FOR UPDATE', [$tid]);
            if ($fila === null) {
                throw new RuntimeException('El trámite ya no existe.');
            }
            if (($imp = self::impedimento($fila)) !== null) {
                throw new RuntimeException($imp);
            }
            if (self::huboIntervencion($fila) !== []) {
                throw new RuntimeException('Mientras confirmabas, otra persona intervino en tu trámite: ya no se puede borrar, solo dejar en pausa.');
            }
            // Avisos de la bandeja que llevan a este trámite (Aviso::egresado deja el enlace, sin tramite_id).
            $enlace = Aviso::enlace('posgrado/tramite/' . $tid);
            Database::ejecutar("DELETE FROM notificaciones WHERE origen = 'posgrado' AND (enlace = ? OR enlace LIKE ?)", [$enlace, $enlace . '#%']);
            // Documentos, eventos, firmas (con sus tokens), generados e hitos se van en cascada.
            Database::ejecutar('DELETE FROM pos_tramites WHERE id = ?', [$tid]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        \App\Modules\Titulacion\Services\Abandono::borrarCarpeta('posgrado', $tid);
        Audit::record('posgrado::tramite_borrado', 'correcto', 'Folio ' . $t['folio'] . ' · modalidad ' . ((string) ($t['modalidad'] ?? '') ?: 'sin elegir') . ' · sin intervención de otras personas');
    }

    public static function abandonar(array $t, int $usuarioId, string $motivo = ''): void
    {
        $tid = (int) $t['id'];
        $examen = self::examenProgramado($t);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $fila = Database::una('SELECT estado FROM pos_tramites WHERE id = ? FOR UPDATE', [$tid]);
            if ($fila === null || ($imp = self::impedimento($fila)) !== null) {
                throw new RuntimeException($imp ?? 'El trámite ya no existe.');
            }
            Database::ejecutar("UPDATE pos_tramites SET estado = 'abandonado', abandonado_en = NOW(), abandonos = abandonos + 1 WHERE id = ?", [$tid]);
            PosgradoRepository::evento($tid, $usuarioId, 'abandono', 'El egresado dejó el trámite en pausa' . ($motivo !== '' ? ' (' . $motivo . ')' : '') . '. Se conserva todo; puede retomarlo.' . ($examen ? ' Tenía programado ' . self::paraPersonal($examen['texto']) . '.' : ''));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::record('posgrado::tramite_abandonado', 'correcto', 'Folio ' . $t['folio'] . ' · modalidad ' . ((string) ($t['modalidad'] ?? '') ?: 'sin elegir') . ($examen ? ' · con examen programado' : ''));
        self::avisarPersonal($t, 'Trámite en pausa · ' . $t['folio'],
            'El egresado dejó en pausa su trámite de grado ' . $t['folio'] . ' (' . self::nombreModalidad($t) . ')' . ($motivo !== '' ? ', ' . $motivo : '') . '. '
            . 'No se borró nada y ya no aparece entre los pendientes; las firmas que estaban por hacerse quedan suspendidas hasta que lo retome.'
            . ($examen ? "\n\nAtención: tenía programado " . self::paraPersonal($examen['texto']) . '; el sínodo ya fue convocado. Avisa a sus integrantes.' : ''));
    }

    public static function retomar(array $t, int $usuarioId): void
    {
        $tid = (int) $t['id'];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $fila = Database::una('SELECT estado FROM pos_tramites WHERE id = ? FOR UPDATE', [$tid]);
            if ($fila === null || $fila['estado'] !== 'abandonado') {
                throw new RuntimeException('Este trámite no está en pausa.');
            }
            Database::ejecutar("UPDATE pos_tramites SET estado = 'abierto', abandonado_en = NULL WHERE id = ?", [$tid]);
            PosgradoRepository::evento($tid, $usuarioId, 'retomado', 'El egresado retomó el trámite donde se había quedado.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::record('posgrado::tramite_retomado', 'correcto', 'Folio ' . $t['folio'] . ' · modalidad ' . ((string) ($t['modalidad'] ?? '') ?: 'sin elegir'));
        self::avisarPersonal($t, 'Trámite retomado · ' . $t['folio'],
            'El egresado retomó su trámite de grado ' . $t['folio'] . ' (' . self::nombreModalidad($t) . ') donde se había quedado. Vuelve a aparecer en tu bandeja y sus firmas pendientes vuelven a funcionar si no vencieron.');
    }

    /** Aviso en la bandeja y por correo al personal de Apoyo a Grado. */
    private static function avisarPersonal(array $t, string $asunto, string $texto): void
    {
        try {
            $enlace = Aviso::enlace('posgrado/gestion/' . (int) $t['id']);
            foreach (\App\Modules\Titulacion\RevisionRepository::correosPersonal((array) App::config('posgrado.departamentos', []), (array) App::config('posgrado.gestores', [])) as $email => $nombre) {
                if (class_exists(\App\Modules\Notificaciones\Notificaciones::class)) {
                    \App\Modules\Notificaciones\Notificaciones::crear((string) $email, $asunto, $texto, $enlace, 'posgrado', null);
                }
                Aviso::personal((string) $email, (string) $nombre, $asunto, $texto, $enlace);
            }
        } catch (Throwable $e) {
            error_log('Posgrado: no se avisó al personal del abandono: ' . $e->getMessage());
        }
    }
}
