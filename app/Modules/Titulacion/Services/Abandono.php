<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\App;
use App\Core\Audit;
use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\RevisionRepository;
use App\Modules\Titulacion\TitulacionRepository;
use RuntimeException;
use Throwable;

/**
 * El pasante deja un trámite de licenciatura que inició por error (por ejemplo, eligió otra modalidad).
 *
 * - Si NADIE más intervino (solo él capturó, subió PDF que nadie revisó o firmó lo suyo), el trámite se BORRA: sus
 *   registros, sus solicitudes de firma pendientes (cuyos enlaces dejan de servir) y su carpeta storage/titulacion/<id>/.
 *   Solo queda el registro de auditoría con folio y modalidad.
 * - Si otra persona intervino, el trámite pasa a «abandonado» (en pausa): no se borra nada; lo que generaron otros
 *   (constancias, oficios, dictámenes) sigue donde aparece hoy, y el pasante puede retomarlo donde se quedó.
 *
 * Una solicitud de firma PENDIENTE no es intervención: solo cuenta lo que otra persona ya hizo.
 */
final class Abandono
{
    /** Estados que el pasante puede dejar. Un trámite concluido (o cancelado con la versión anterior) no se toca. */
    private const DEJABLES = ['abierto'];

    /** Enlaces de descarga ligados al trámite cuyo primer parámetro es su id (Archivos::ACCIONES). */
    private const TOKENS_DEL_TRAMITE = ['tramite_doc', 'tramite_dictamen', 'ruta_doc', 'ruta_dictamen', 'expediente'];

    private static function fecha(?string $dt): string
    {
        $t = $dt ? strtotime($dt) : false;
        return $t ? date('j/n/Y', $t) : '';
    }

    private static function el(?string $dt): string
    {
        $f = self::fecha($dt);
        return $f !== '' ? ' el ' . $f : '';
    }

    public static function nombreModalidad(array $t): string
    {
        return (string) (Catalogo::modalidad((string) $t['modalidad'])['nombre'] ?? $t['modalidad']);
    }

    private static function nombreDocumento(array $t, string $etapa, string $clave): string
    {
        $e = Catalogo::etapa((string) $t['modalidad'], ($t['variante'] ?? null) ?: null, $etapa);
        $def = $e ? Catalogo::documentoDeEtapa($e, $clave) : null;
        return (string) ($def['nombre'] ?? str_replace('_', ' ', $clave));
    }

    private static function nombreEtapa(array $t, string $etapa): string
    {
        return (string) (Catalogo::etapa((string) $t['modalidad'], ($t['variante'] ?? null) ?: null, $etapa)['titulo'] ?? str_replace('_', ' ', $etapa));
    }

    // ------------------------------------------------------------------ consultas

    /** Trámites de licenciatura abiertos (activos) del pasante, el más reciente primero. */
    public static function activos(int $alumnoId): array
    {
        return Database::todas(
            "SELECT * FROM tit_tramites WHERE alumno_id = ? AND nivel = 'licenciatura' AND estado = 'abierto' AND historico = 0 ORDER BY creado_en DESC, id DESC",
            [$alumnoId]
        );
    }

    /** ¿Por qué no se puede dejar este trámite? null si sí se puede. */
    public static function impedimento(array $t): ?string
    {
        if (!empty($t['historico'])) {
            return 'Este trámite se importó del registro anterior; no se puede dejar.';
        }
        return match ((string) $t['estado']) {
            'abierto' => null,
            'concluido' => 'Este trámite ya concluyó: no se puede borrar ni dejar.',
            'abandonado' => 'Este trámite ya está en pausa.',
            default => 'Este trámite ya no está activo.',
        };
    }

    /**
     * Acciones de otras personas en el trámite, en lenguaje llano para el pasante. Vacío: solo él actuó y se puede borrar.
     * @return string[]
     */
    public static function huboIntervencion(array $t): array
    {
        $tid = (int) $t['id'];
        $yo = (int) $t['alumno_id'];
        $m = [];

        // Documentos que otra persona validó, devolvió, cotejó o emitió. Las constancias y los oficios con número se
        // resumen más abajo (no se repiten aquí).
        foreach (Database::todas(
            'SELECT etapa, clave, origen, estado, revisado_por, revisado_en, cotejo_por, cotejo_en, subido_por, creado_en FROM tit_documentos
             WHERE tramite_id = ? AND ((revisado_por IS NOT NULL AND revisado_por <> ?) OR subido_por <> ? OR cotejo_por IS NOT NULL) ORDER BY id',
            [$tid, $yo, $yo]
        ) as $d) {
            $clave = (string) $d['clave'];
            if (str_starts_with($clave, 'constancia') || isset(Oficios::TIPOS[$clave])) {
                continue;
            }
            $nombre = '«' . self::nombreDocumento($t, (string) $d['etapa'], $clave) . '»';
            if ($d['cotejo_por'] !== null) {
                $m[] = 'Titulación cotejó con tu original ' . $nombre . self::el($d['cotejo_en']) . '.';
            }
            if ((int) $d['subido_por'] !== $yo) {
                $m[] = 'Se emitió ' . $nombre . ' en tu trámite' . self::el($d['creado_en']) . '.';
            } elseif ($d['revisado_por'] !== null && (int) $d['revisado_por'] !== $yo) {
                $m[] = match ((string) $d['estado']) {
                    'aprobado' => 'Se validó ' . $nombre . self::el($d['revisado_en']) . '.',
                    'rechazado' => 'Te devolvieron ' . $nombre . ' con observaciones' . self::el($d['revisado_en']) . '.',
                    default => 'Se revisó ' . $nombre . self::el($d['revisado_en']) . '.',
                };
            }
        }
        // Firmas ya hechas por autoridades (Jefatura, Subdirección, Dirección); las de constancias se resumen abajo.
        foreach (Database::todas("SELECT rol_firma, descripcion, firmado_en FROM tit_firmas_subdireccion WHERE tramite_id = ? AND estado = 'firmado' AND formato <> 'constancia' ORDER BY firmado_en", [$tid]) as $f) {
            $desc = trim((string) preg_replace('/\s·\s[A-Z]+-\d{4}-\d+$/', '', (string) $f['descripcion']));
            $m[] = ucfirst(SubdireccionFirma::CARGOS[$f['rol_firma']] ?? 'Una autoridad') . ' firmó «' . $desc . '»' . self::el($f['firmado_en']) . '.';
        }
        // Firmas ya hechas por asesor, coasesor o revisores.
        foreach (Database::todas(
            "SELECT f.rol, f.nombre, f.genero, f.firmada_en, d.clave FROM tit_firmas_externas f JOIN tit_documentos d ON d.id = f.documento_id
             WHERE f.tramite_id = ? AND f.estado = 'firmada' ORDER BY f.firmada_en",
            [$tid]
        ) as $f) {
            $rol = mb_strtolower(FirmasExternas::etiquetaRol((string) $f['rol'])) . ($f['genero'] === 'femenino' ? 'a' : '');
            $m[] = 'Tu ' . $rol . ' ' . $f['nombre'] . ' firmó ' . FirmasExternas::queSeFirma((string) $f['clave'])[0] . self::el($f['firmada_en']) . '.';
        }
        // Protocolo: turnado al comité, revisores asignados, evaluaciones firmadas, dictamen.
        foreach (Database::todas(
            "SELECT r.id, r.numero, r.estado, r.enviada_por, r.enviada_en, r.evaluacion_en, r.dictaminado_en,
                    (SELECT MAX(a.firmada_en) FROM tit_revision_asignaciones a WHERE a.revision_id = r.id AND a.estado = 'firmada') AS ultima_firma,
                    (SELECT MIN(a.asignada_en) FROM tit_revision_asignaciones a WHERE a.revision_id = r.id) AS asignada
             FROM tit_revisiones r WHERE r.tramite_id = ? ORDER BY r.numero",
            [$tid]
        ) as $r) {
            $vuelta = (int) $r['numero'] > 1 ? ' (' . Mensajes::ordinal((int) $r['numero']) . ' vuelta)' : '';
            if ($r['estado'] === 'dictaminada') {
                $m[] = 'El comité evaluó tu protocolo' . $vuelta . self::el($r['dictaminado_en']) . '.';
            } elseif ($r['ultima_firma'] !== null) {
                $m[] = 'Revisores del comité ya firmaron la evaluación de tu protocolo' . $vuelta . self::el($r['ultima_firma']) . '.';
            } elseif ($r['asignada'] !== null) {
                $m[] = 'El comité asignó revisores a tu protocolo' . $vuelta . self::el($r['asignada']) . '.';
            } elseif ($r['enviada_por'] !== null || !in_array($r['estado'], ['recibida', 'cancelada'], true)) {
                $m[] = 'Titulación turnó tu protocolo al comité' . $vuelta . self::el($r['enviada_en']) . '.';
            }
        }
        // Fechas, resultados y demás hitos que registró el personal.
        foreach (Database::todas('SELECT etapa, completado_en FROM tit_hitos WHERE tramite_id = ? AND completado_por <> ? ORDER BY completado_en', [$tid, $yo]) as $h) {
            $m[] = 'Se dio por atendida la etapa «' . self::nombreEtapa($t, (string) $h['etapa']) . '»' . self::el($h['completado_en']) . '.';
        }
        // Sínodo o jurado ratificado por el Coordinador de Docencia y acuses de los sinodales.
        foreach (Database::todas("SELECT tipo, firmada_en FROM tit_asignaciones_jurado WHERE tramite_id = ? AND estado = 'firmada' ORDER BY firmada_en", [$tid]) as $a) {
            $m[] = 'El Coordinador de Docencia ratificó el sínodo de tu ' . (Oficios::EXAMEN[$a['tipo']] ?? 'examen') . self::el($a['firmada_en']) . '.';
        }
        if (($n = (int) Database::valor("SELECT COUNT(*) FROM tit_sinodales WHERE tramite_id = ? AND estado = 'acuse'", [$tid])) > 0) {
            $m[] = 'Sinodales ya firmaron de recibido el oficio de tu examen.';
        }
        // Oficios con número (los de las constancias se resumen con ellas), constancias y actas emitidos.
        $deConstancias = array_map(static fn(array $x): string => (string) $x[0], Constancias::TIPOS);
        foreach (Database::todas('SELECT texto, asunto, creado_en FROM tit_oficios WHERE tramite_id = ? ORDER BY id', [$tid]) as $o) {
            if (!in_array((string) $o['asunto'], $deConstancias, true)) {
                $m[] = 'Se emitió el oficio ' . $o['texto'] . ' («' . $o['asunto'] . '»)' . self::el($o['creado_en']) . '.';
            }
        }
        foreach (Database::todas('SELECT tipo, MIN(creado_en) AS creado_en FROM tit_constancias WHERE tramite_id = ? GROUP BY tipo ORDER BY MIN(id)', [$tid]) as $c) {
            $m[] = 'Se emitieron constancias de «' . Constancias::titulo((string) $c['tipo']) . '» para quienes participaron' . self::el($c['creado_en']) . '.';
        }
        foreach (Database::todas('SELECT tipo, creado_en FROM tit_actas WHERE tramite_id = ? ORDER BY id', [$tid]) as $a) {
            $m[] = 'Se asignó número de acta de ' . ($a['tipo'] === 'aplazamiento' ? 'aplazamiento' : 'evaluación') . self::el($a['creado_en']) . '.';
        }
        if (Database::valor('SELECT 1 FROM tit_expediente_final WHERE tramite_id = ?', [$tid])) {
            $m[] = 'Titulación integró tu expediente final.';
        }
        if ($m === [] && !empty($t['responsable_id']) && (int) $t['responsable_id'] !== $yo) {
            $m[] = 'Personal de Titulación ya tomó tu trámite para atenderlo.';
        }
        if ($m === [] && ($ultimo = Database::valor('SELECT MAX(creado_en) FROM tit_eventos WHERE tramite_id = ? AND usuario_id <> ?', [$tid, $yo]))) {
            $m[] = 'Otras personas registraron movimientos en tu trámite (el último' . self::el((string) $ultimo) . ').';
        }
        return array_values(array_unique($m));
    }

    /**
     * Examen con fecha que todavía no pasa (solicitado o con sínodo ya ratificado).
     * @return array{texto:string,convocado:bool}|null
     */
    public static function examenProgramado(array $t): ?array
    {
        $a = Database::una(
            "SELECT tipo, fecha, hora, estado FROM tit_asignaciones_jurado WHERE tramite_id = ? AND estado IN ('solicitada','firmada') AND fecha >= CURDATE() ORDER BY fecha, id LIMIT 1",
            [(int) $t['id']]
        );
        if ($a === null) {
            return null;
        }
        return ['texto' => 'tu ' . (Oficios::EXAMEN[$a['tipo']] ?? 'examen') . ' del ' . Oficios::fecha((string) $a['fecha']) . ' a las ' . $a['hora'] . ' h', 'convocado' => $a['estado'] === 'firmada'];
    }

    /** Lo que el pasante perdería al borrar (para decírselo antes de confirmar). @return string[] */
    public static function seBorra(array $t): array
    {
        $tid = (int) $t['id'];
        $r = ['Los datos que capturaste.'];
        $docs = (int) Database::valor("SELECT COUNT(*) FROM tit_documentos WHERE tramite_id = ? AND ruta IS NOT NULL", [$tid]);
        if ($docs > 0) {
            $r[] = $docs === 1 ? 'El PDF que subiste o generaste.' : 'Los PDF que subiste o generaste.';
        }
        if ((int) Database::valor("SELECT COUNT(*) FROM tit_firmas_externas WHERE tramite_id = ? AND estado = 'pendiente'", [$tid]) > 0) {
            $r[] = 'Las solicitudes de firma que enviamos a tu asesor: sus enlaces dejarán de servir.';
        }
        if (Database::valor("SELECT 1 FROM tit_revisiones WHERE tramite_id = ? AND estado = 'recibida'", [$tid])) {
            $r[] = 'El protocolo que enviaste y que aún no se turnaba al comité.';
        }
        if (Database::valor("SELECT 1 FROM tit_asignaciones_jurado WHERE tramite_id = ? AND estado = 'solicitada'", [$tid])) {
            $r[] = 'Tu solicitud de fecha de examen: el espacio apartado se libera.';
        }
        return $r;
    }

    /** Lo que se conserva si queda en pausa. @return string[] */
    public static function seConserva(array $t): array
    {
        $tid = (int) $t['id'];
        $r = ['Tus datos, tus documentos y todo tu avance, tal como están hoy.'];
        if ((int) Database::valor('SELECT COUNT(*) FROM tit_constancias WHERE tramite_id = ?', [$tid]) > 0) {
            $r[] = 'Las constancias emitidas a quienes participaron (por ejemplo, a quienes evaluaron tu protocolo); siguen siendo válidas para ellos.';
        }
        if ((int) Database::valor('SELECT COUNT(*) FROM tit_oficios WHERE tramite_id = ?', [$tid]) > 0) {
            $r[] = 'Los oficios ya emitidos, con su número.';
        }
        if (Database::valor("SELECT 1 FROM tit_revisiones WHERE tramite_id = ? AND estado = 'dictaminada'", [$tid])) {
            $r[] = 'El dictamen del comité sobre tu protocolo.';
        }
        if ((int) Database::valor("SELECT COUNT(*) FROM tit_firmas_externas WHERE tramite_id = ? AND estado = 'pendiente'", [$tid]) > 0
            || (int) Database::valor("SELECT COUNT(*) FROM tit_firmas_subdireccion WHERE tramite_id = ? AND estado = 'pendiente' AND formato <> 'constancia'", [$tid]) > 0) {
            $r[] = 'Las firmas que estaban pendientes quedan en pausa: sus enlaces no permitirán firmar hasta que lo retomes.';
        }
        return $r;
    }

    /**
     * Qué pasaría al dejarlo hoy.
     * @return array{accion:string,motivos:string[],examen:?array,borra:string[],conserva:string[],impedimento:?string}
     */
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

    // ------------------------------------------------------------------ acciones

    /** «tu examen…» → «el examen…» para la bitácora y los avisos al personal. */
    private static function paraPersonal(string $texto): string
    {
        return (string) preg_replace('/^tu /u', 'el ', $texto);
    }

    /** Borra o deja en pausa según la regla. @return string 'borrado' o 'abandonado' */
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

    /** Borra definitivamente un trámite en el que solo actuó el pasante. */
    public static function borrar(array $t, int $usuarioId): void
    {
        $tid = (int) $t['id'];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $fila = Database::una('SELECT * FROM tit_tramites WHERE id = ? FOR UPDATE', [$tid]);
            if ($fila === null) {
                throw new RuntimeException('El trámite ya no existe.');
            }
            $fila['datos'] = $t['datos'] ?? [];
            if (($imp = self::impedimento($fila)) !== null) {
                throw new RuntimeException($imp);
            }
            if (self::huboIntervencion($fila) !== []) {
                throw new RuntimeException('Mientras confirmabas, otra persona intervino en tu trámite: ya no se puede borrar, solo dejar en pausa.');
            }
            // El espacio apartado para un examen se libera (la reserva vive en el módulo de Espacios).
            Database::ejecutar("UPDATE esp_reservas SET estado = 'cancelada', cancelada_por = ?, cancelada_en = NOW() WHERE tramite_id = ? AND origen = 'titulacion' AND estado = 'activa'", [$usuarioId, $tid]);
            // Enlaces de descarga y de firma ligados al trámite.
            $marcas = implode(',', array_fill(0, count(self::TOKENS_DEL_TRAMITE), '?'));
            Database::ejecutar("DELETE FROM tit_archivo_tokens WHERE accion IN ($marcas) AND (params = ? OR params LIKE ?)", [...self::TOKENS_DEL_TRAMITE, (string) $tid, $tid . ',%']);
            $firmas = array_map('strval', array_column(Database::todas('SELECT id FROM tit_firmas_externas WHERE tramite_id = ?', [$tid]), 'id'));
            if ($firmas) {
                Database::ejecutar("DELETE FROM tit_archivo_tokens WHERE accion = 'firma_mia' AND params IN (" . implode(',', array_fill(0, count($firmas), '?')) . ')', $firmas);
            }
            Database::ejecutar('DELETE FROM tit_verificaciones WHERE tramite_id = ?', [$tid]);
            Database::ejecutar('DELETE FROM tit_correos WHERE tramite_id = ?', [$tid]);
            Database::ejecutar("DELETE FROM notificaciones WHERE tramite_id = ? AND origen = 'titulacion'", [$tid]);
            // El resto (documentos, eventos, firmas pendientes, hitos, rondas «recibidas», solicitudes de examen) se va en cascada.
            Database::ejecutar('DELETE FROM tit_tramites WHERE id = ?', [$tid]);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        self::borrarCarpeta('titulacion', $tid);
        Audit::record('titulacion::tramite_borrado', 'correcto', 'Folio ' . $t['folio'] . ' · modalidad ' . $t['modalidad'] . ' · sin intervención de otras personas');
    }

    /** Deja el trámite en pausa: no se borra nada. */
    public static function abandonar(array $t, int $usuarioId, string $motivo = ''): void
    {
        $tid = (int) $t['id'];
        $examen = self::examenProgramado($t);
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $fila = Database::una('SELECT estado, historico FROM tit_tramites WHERE id = ? FOR UPDATE', [$tid]);
            if ($fila === null || ($imp = self::impedimento($fila)) !== null) {
                throw new RuntimeException($imp ?? 'El trámite ya no existe.');
            }
            Database::ejecutar("UPDATE tit_tramites SET estado = 'abandonado', abandonado_en = NOW(), abandonos = abandonos + 1 WHERE id = ?", [$tid]);
            TitulacionRepository::evento($tid, $usuarioId, 'abandono', 'El pasante dejó el trámite en pausa' . ($motivo !== '' ? ' (' . $motivo . ')' : '') . '. Se conserva todo; puede retomarlo.' . ($examen ? ' Tenía programado ' . self::paraPersonal($examen['texto']) . '.' : ''));
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::record('titulacion::tramite_abandonado', 'correcto', 'Folio ' . $t['folio'] . ' · modalidad ' . $t['modalidad'] . ($examen ? ' · con examen programado' : ''));
        self::avisarPersonal($t, 'Trámite en pausa · ' . $t['folio'],
            'El pasante dejó en pausa su trámite ' . $t['folio'] . ' (' . self::nombreModalidad($t) . ')' . ($motivo !== '' ? ', ' . $motivo : '') . '. '
            . 'No se borró nada y ya no aparece entre los pendientes; las firmas que estaban por hacerse quedan suspendidas hasta que lo retome.'
            . ($examen ? "\n\nAtención: tenía programado " . self::paraPersonal($examen['texto']) . ($examen['convocado'] ? '; el sínodo ya fue convocado. Avisa a sus integrantes y decide si se libera el espacio.' : '; el espacio sigue apartado y el Coordinador de Docencia tiene la propuesta de sínodo.') : ''));
    }

    /** Vuelve a «abierto» exactamente donde se quedó. */
    public static function retomar(array $t, int $usuarioId): void
    {
        $tid = (int) $t['id'];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $fila = Database::una('SELECT estado FROM tit_tramites WHERE id = ? FOR UPDATE', [$tid]);
            if ($fila === null || $fila['estado'] !== 'abandonado') {
                throw new RuntimeException('Este trámite no está en pausa.');
            }
            Database::ejecutar("UPDATE tit_tramites SET estado = 'abierto', abandonado_en = NULL WHERE id = ?", [$tid]);
            TitulacionRepository::evento($tid, $usuarioId, 'retomado', 'El pasante retomó el trámite donde se había quedado.');
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        Audit::record('titulacion::tramite_retomado', 'correcto', 'Folio ' . $t['folio'] . ' · modalidad ' . $t['modalidad']);
        self::avisarPersonal($t, 'Trámite retomado · ' . $t['folio'],
            'El pasante retomó su trámite ' . $t['folio'] . ' (' . self::nombreModalidad($t) . ') donde se había quedado. Vuelve a aparecer en tu bandeja y sus firmas pendientes vuelven a funcionar si no vencieron.');
    }

    /** Aviso en la bandeja y por correo al personal de Titulación (Departamento de Evaluación Profesional). */
    private static function avisarPersonal(array $t, string $asunto, string $texto): void
    {
        try {
            foreach (RevisionRepository::correosPersonal((array) App::config('titulacion.departamentos', []), (array) App::config('titulacion.gestores', [])) as $email => $nombre) {
                Correo::enviar((string) $email, (string) $nombre, $asunto, $texto, 'Abrir el trámite', Correo::enlace('titulacion/gestion/' . (int) $t['id']), 'abandono', (int) $t['id']);
            }
        } catch (Throwable $e) {
            error_log('Titulación: no se avisó al personal del abandono: ' . $e->getMessage());
        }
    }

    /**
     * Borra storage/<modulo>/<id>/ completo. Solo actúa si la carpeta real está exactamente ahí (realpath), y no sigue
     * enlaces simbólicos: un enlace se elimina como tal, nunca lo que apunta.
     */
    public static function borrarCarpeta(string $modulo, int $id): void
    {
        if (!in_array($modulo, ['titulacion', 'posgrado'], true) || $id <= 0) {
            return;
        }
        $base = realpath((string) App::config('archivos.storage'));
        if ($base === false) {
            return;
        }
        $esperada = $base . DIRECTORY_SEPARATOR . $modulo . DIRECTORY_SEPARATOR . $id;
        $dir = realpath($esperada);
        if ($dir === false || $dir !== $esperada || !is_dir($dir) || is_link($esperada)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $ruta = $f->getPathname();
            if (!str_starts_with($ruta, $dir . DIRECTORY_SEPARATOR)) {
                continue;
            }
            if ($f->isLink() || $f->isFile()) {
                @unlink($ruta);
            } elseif ($f->isDir()) {
                @rmdir($ruta);
            }
        }
        @rmdir($dir);
    }
}
