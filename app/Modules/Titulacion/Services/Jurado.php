<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Core\Persona;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;
use RuntimeException;

/**
 * Asignación de sinodales: el pasante elige fecha y hora y propone su sínodo; el Coordinador de Docencia de la
 * licenciatura lo ratifica o modifica y firma desde el enlace de su correo; se generan los oficios individuales
 * (los firma la Subdirección) y cada profesor firma de acuse de recibido desde su propio enlace.
 */
final class Jurado
{
    private const DOC_ASIGNACION = ['previo' => 'asignacion_revisores', 'definitivo' => 'asignacion_jurado', 'protesta' => 'asignacion_jurado'];

    private static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function vence(string $fecha): string
    {
        $gracia = max(1, (int) \ajuste('titulacion_token_gracia', '14'));
        $base = max(strtotime($fecha) ?: 0, time());
        return date('Y-m-d', $base + $gracia * 86400);
    }

    public static function coordinador(string $carrera): ?array
    {
        return Autoridades::coordinadorDocencia($carrera);
    }

    public static function vigente(int $tramiteId, string $etapa): ?array
    {
        return Database::una("SELECT * FROM tit_asignaciones_jurado WHERE tramite_id = ? AND etapa = ? AND estado <> 'cancelada' ORDER BY id DESC LIMIT 1", [$tramiteId, $etapa]);
    }

    public static function sinodales(int $asigId): array
    {
        return Database::todas('SELECT * FROM tit_sinodales WHERE asignacion_id = ? AND estado <> \'cancelado\' ORDER BY orden', [$asigId]);
    }

    // ----------------------------------------------------------------- solicitud al Coordinador

    public static function solicitar(array $tramite, string $etapa, string $tipo, array $datos, int $usuarioId): void
    {
        $carrera = (string) ($tramite['datos']['datos']['carrera'] ?? '');
        $c = self::coordinador($carrera);
        if (!$c) {
            throw new RuntimeException('No hay Coordinador de Docencia vigente para esta licenciatura en Organización (cargo «coordinador» del programa). Pide al administrador registrarlo allí.');
        }
        Database::ejecutar("UPDATE tit_asignaciones_jurado SET estado = 'cancelada' WHERE tramite_id = ? AND etapa = ? AND estado = 'solicitada'", [(int) $tramite['id'], $etapa]);
        $token = self::token();
        Database::ejecutar(
            'INSERT INTO tit_asignaciones_jurado (tramite_id, tipo, etapa, carrera, fecha, hora, lugar, reserva_id, propuesta, coord_nombre, coord_genero, coord_email, token_hash, vence, creado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            [(int) $tramite['id'], $tipo, $etapa, $carrera, $datos['fecha'], $datos['hora'], mb_substr((string) $datos['lugar'], 0, 200), $datos['reserva_id'] ?? null,
                isset($datos['propuesta']) ? json_encode($datos['propuesta'], JSON_UNESCAPED_UNICODE) : null,
                Persona::tratamiento($c), Persona::genero($c), strtolower($c['email']), hash('sha256', $token), self::vence((string) $datos['fecha']), $usuarioId]
        );
        $a = Database::una('SELECT * FROM tit_asignaciones_jurado WHERE token_hash = ?', [hash('sha256', $token)]);
        self::correoCoordinador($tramite, $a, $token, false);
        TitulacionRepository::evento((int) $tramite['id'], $usuarioId, 'jurado', 'Se pidió al Coordinador de Docencia ratificar o modificar el sínodo del ' . (Oficios::EXAMEN[$tipo] ?? 'examen') . ' del ' . Oficios::fecha($datos['fecha']) . '.', true);
    }

    public static function urlCoordinador(string $token): string
    {
        return Correo::enlace('titulacion/jurado/t/' . $token);
    }

    private static function correoCoordinador(array $t, array $a, string $token, bool $recordatorio): void
    {
        $alumno = (string) ($t['datos']['datos']['nombre'] ?? '');
        $examen = Oficios::EXAMEN[$a['tipo']] ?? 'examen';
        Correo::enviar(
            $a['coord_email'], ['nombre' => $a['coord_nombre'], 'genero' => $a['coord_genero'] ?? null],
            ($recordatorio ? 'Recordatorio · ' : '') . 'Asignar sinodales · ' . $t['folio'],
            ($recordatorio ? 'Recordatorio: ' : '') . "$alumno solicitó su $examen para el " . Oficios::fecha($a['fecha']) . ' a las ' . $a['hora'] . ' horas, en ' . $a['lugar'] . ", y propuso a los integrantes de su sínodo.\n\nQué debe hacer: abra el enlace, revise el sínodo propuesto, ratifíquelo o cambie a quien considere (puede buscar por nombre o por correo) y pulse «Firmar asignación». Al firmar se generan los oficios para cada profesor. No necesita descargar ni subir nada. Para firmar se requiere una cuenta verificada con este mismo correo ({$a['coord_email']}).\n\nEl enlace es personal; no lo comparta.",
            'Revisar y firmar el sínodo', self::urlCoordinador($token), $recordatorio ? 'recordatorio_jurado' : 'solicitud_jurado', (int) $t['id']
        );
    }

    public static function reenviarCoordinador(int $asigId): bool
    {
        $a = Database::una("SELECT * FROM tit_asignaciones_jurado WHERE id = ? AND estado = 'solicitada'", [$asigId]);
        $t = $a ? TitulacionRepository::obtener((int) $a['tramite_id']) : null;
        if (!$a || !$t) {
            return false;
        }
        $token = self::token();
        Database::ejecutar('UPDATE tit_asignaciones_jurado SET token_hash = ?, vence = ? WHERE id = ?', [hash('sha256', $token), self::vence((string) $a['fecha']), $asigId]);
        self::correoCoordinador($t, $a, $token, true);
        return true;
    }

    public static function porToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,50}$/D', $token)) {
            return null;
        }
        $a = Database::una('SELECT * FROM tit_asignaciones_jurado WHERE token_hash = ?', [hash('sha256', $token)]);
        return $a && $a['estado'] !== 'cancelada' && $a['vence'] >= date('Y-m-d') ? $a : null;
    }

    // ----------------------------------------------------------------- firma del Coordinador

    /** Sínodo que propuso el pasante (vacío en solicitudes anteriores a la propuesta). */
    public static function propuesta(array $a): array
    {
        $p = json_decode((string) ($a['propuesta'] ?? ''), true);
        return is_array($p) ? array_values($p) : [];
    }

    /** @return array{0:int,1:int} propietarios y suplentes de esta asignación */
    public static function integrantes(array $a): array
    {
        $p = self::propuesta($a);
        if ($p) {
            $prop = count(array_filter($p, static fn(array $x): bool => ($x['calidad'] ?? '') === 'propietario'));
            return [$prop, count($p) - $prop];
        }
        $c = AgendaExamen::config();
        return [$c['propietarios'], $c['suplentes']];
    }

    /** El Coordinador ratifica o modifica el sínodo propuesto y firma. @param array<int,array{nombre:string,email:string}> $elegidos */
    public static function firmarAsignacion(array $a, array $usuario, array $elegidos): void
    {
        if ($a['estado'] !== 'solicitada') {
            throw new RuntimeException('Esta asignación ya se firmó.');
        }
        if (strtolower((string) $usuario['email']) !== strtolower((string) $a['coord_email'])) {
            throw new RuntimeException('Tu sesión no corresponde al correo del Coordinador.');
        }
        [$prop, $sup] = self::integrantes($a);
        $sin = AgendaExamen::sinodo($elegidos, $prop, $sup);
        $propuestos = array_column(self::propuesta($a), 'email');
        $cambios = array_diff(array_column($sin, 'email'), $propuestos);
        $t = TitulacionRepository::obtener((int) $a['tramite_id']);
        if (!$t || $t['estado'] !== 'abierto') {
            throw new RuntimeException('El trámite ya no está abierto.');
        }
        // Formato firmado por el Coordinador.
        [$bytes, $caja] = Oficios::asignacion($t, $a, $sin);
        $dir = FirmaElectronica::almacen() . '/titulacion/' . (int) $t['id'];
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $origen = $dir . '/_asig_' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($origen, $bytes);
        $relativa = 'titulacion/' . (int) $t['id'] . '/asignacion_' . $a['tipo'] . '_' . bin2hex(random_bytes(8)) . '.pdf';
        $mm = 25.4 / 72;
        try {
            SelloPdf::estampar(
                $origen, FirmaElectronica::almacen() . '/' . $relativa, (int) $caja['p'], ((float) $caja['x'] + (float) $caja['w'] / 2) * $mm, (float) $caja['y'] * $mm - 1.0,
                'FIRMA ELECTRÓNICA · COORDINACIÓN DE DOCENCIA',
                [mb_strtoupper((string) $a['coord_nombre']), 'Firmado el ' . date('d/m/Y H:i') . ' · ' . $t['folio'] . ' ' . substr(hash('sha256', $bytes), 0, 8)]
            );
        } finally {
            @unlink($origen);
        }
        Verificacion::guardado($bytes, $relativa);
        $docId = TitulacionRepository::registrarDocumento((int) $t['id'], (string) $a['etapa'], self::DOC_ASIGNACION[$a['tipo']], 'personal', 'aprobado', $relativa, 'asignacion_' . $a['tipo'] . '_' . $t['folio'] . '.pdf', (int) $usuario['id']);
        $st = Database::pdo()->prepare("UPDATE tit_asignaciones_jurado SET estado = 'firmada', firmada_en = ?, firmante_id = ?, documento_id = ? WHERE id = ? AND estado = 'solicitada'");
        $st->execute([date('Y-m-d H:i:s'), (int) $usuario['id'], $docId, (int) $a['id']]);
        if ($st->rowCount() === 0) {
            return;
        }
        // Oficios individuales: pasan a la firma de la Subdirección.
        foreach ($sin as $s) {
            $s['id'] = Database::ejecutar(
                'INSERT INTO tit_sinodales (asignacion_id, tramite_id, calidad, orden, nombre, grado, genero, email) VALUES (?,?,?,?,?,?,?,?)',
                [(int) $a['id'], (int) $t['id'], $s['calidad'], $s['orden'], $s['nombre'], $s['grado'], $s['genero'], $s['email']]
            );
            [$ruta, $cajaOf] = DocumentosGenerados::guardarOficio($t, 'oficio_sinodal', [], ['sinodal' => $s, 'asig' => $a]);
            $oficioId = TitulacionRepository::registrarDocumento((int) $t['id'], (string) $a['etapa'], 'oficio_sinodal_' . $s['orden'], 'personal', 'aprobado', $ruta, 'oficio_sinodal_' . $s['orden'] . '_' . $t['folio'] . '.pdf', (int) $usuario['id']);
            Database::ejecutar('UPDATE tit_sinodales SET oficio_doc_id = ? WHERE id = ?', [$oficioId, (int) $s['id']]);
            SubdireccionFirma::solicitar($t, $oficioId, 'oficio_sinodal', 'Oficio a sinodal: ' . $s['nombre'], $cajaOf);
        }
        TitulacionRepository::evento((int) $t['id'], (int) $usuario['id'], 'jurado', 'El Coordinador de Docencia ' . ($propuestos && !$cambios ? 'ratificó' : 'aprobó, con cambios,') . ' el sínodo del ' . (Oficios::EXAMEN[$a['tipo']] ?? 'examen') . '; los oficios pasan a la firma de la Subdirección.', true);
        Audit::record('titulacion::jurado', 'firma', $a['tipo'] . ' · ' . $t['folio']);
        SubdireccionFirma::sincronizar((int) $t['id']);
    }

    // ----------------------------------------------------------------- oficios a sinodales

    /** La Subdirección firmó el oficio: se avisa al profesor con su enlace personal. */
    public static function oficioFirmado(int $oficioDocId): void
    {
        $s = Database::una("SELECT * FROM tit_sinodales WHERE oficio_doc_id = ? AND estado = 'esperando_firma'", [$oficioDocId]);
        if ($s) {
            self::enviarASinodal($s, false);
        }
    }

    private static function enviarASinodal(array $s, bool $recordatorio): void
    {
        $a = Database::una('SELECT * FROM tit_asignaciones_jurado WHERE id = ?', [(int) $s['asignacion_id']]);
        $t = $a ? TitulacionRepository::obtener((int) $a['tramite_id']) : null;
        if (!$a || !$t) {
            return;
        }
        $token = self::token();
        Database::ejecutar("UPDATE tit_sinodales SET token_hash = ?, vence = ?, estado = IF(estado = 'esperando_firma', 'pendiente', estado), enviado_en = ? WHERE id = ?", [hash('sha256', $token), self::vence((string) $a['fecha']), date('Y-m-d H:i:s'), (int) $s['id']]);
        $examen = Oficios::EXAMEN[$a['tipo']] ?? 'examen';
        Correo::enviar(
            $s['email'], ['nombre' => $s['nombre'], 'grado' => $s['grado'] ?? null, 'genero' => $s['genero'] ?? null],
            ($recordatorio ? 'Recordatorio · ' : '') . 'Oficio de citación · ' . $t['folio'],
            ($recordatorio ? 'Recordatorio: ' : '') . "Fuiste " . Persona::f($s, 'designado', 'designada') . " como sinodal " . ($s['calidad'] === 'suplente' ? 'suplente' : Persona::f($s, 'propietario', 'propietaria')) . " para el $examen de " . ($t['datos']['datos']['nombre'] ?? '') . ', el ' . Oficios::fecha($a['fecha']) . ' a las ' . $a['hora'] . ' horas, en ' . $a['lugar'] . ".\n\nQué debes hacer: abre el enlace, lee tu oficio y descarga el trabajo escrito; después pulsa «Firmar de recibido». Con eso la fecha se agrega a tu calendario de Traza FI. No necesitas descargar ni subir nada más. Para firmar se requiere una cuenta verificada con este mismo correo ({$s['email']}).\n\nEl enlace es personal; no lo compartas.",
            'Ver mi oficio y firmar de recibido', Correo::enlace('titulacion/sinodal/t/' . $token), $recordatorio ? 'recordatorio_sinodal' : 'oficio_sinodal', (int) $t['id']
        );
    }

    /** El pasante pide reenviar el correo a un sinodal que aún no firma (máximo tres veces por hora, lo valida el controlador). */
    public static function recordar(int $sinodalId, int $tramiteId): bool
    {
        $s = Database::una("SELECT * FROM tit_sinodales WHERE id = ? AND tramite_id = ? AND estado = 'pendiente'", [$sinodalId, $tramiteId]);
        if (!$s) {
            return false;
        }
        self::enviarASinodal($s, true);
        return true;
    }

    public static function sinodalPorToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,50}$/D', $token)) {
            return null;
        }
        $s = Database::una('SELECT * FROM tit_sinodales WHERE token_hash = ?', [hash('sha256', $token)]);
        return $s && $s['estado'] !== 'cancelado' && $s['vence'] >= date('Y-m-d') ? $s : null;
    }

    /** Documento del trabajo escrito que el sinodal debe revisar. */
    public static function trabajo(array $asig): ?array
    {
        $par = ['previo' => ['revision', 'trabajo_previo'], 'definitivo' => ['final', 'trabajo_final']][$asig['tipo']] ?? null;
        if ($par === null) {
            return null;
        }
        $d = TitulacionRepository::documentosVigentes((int) $asig['tramite_id'])[$par[0] . '|' . $par[1]] ?? null;
        return $d && !empty($d['ruta']) ? $d : null;
    }

    public static function acuse(array $s, array $usuario): void
    {
        if ($s['estado'] !== 'pendiente') {
            throw new RuntimeException('Este oficio ya se firmó de recibido.');
        }
        if (strtolower((string) $usuario['email']) !== strtolower((string) $s['email'])) {
            throw new RuntimeException('Tu sesión no corresponde al correo del oficio.');
        }
        $doc = TitulacionRepository::documento((int) $s['oficio_doc_id']);
        $firma = Database::una('SELECT caja FROM tit_firmas_subdireccion WHERE documento_id = ? ORDER BY id DESC LIMIT 1', [(int) $s['oficio_doc_id']]);
        $t = TitulacionRepository::obtener((int) $s['tramite_id']);
        $caja = $firma && $firma['caja'] ? json_decode((string) $firma['caja'], true) : null;
        $origenRel = $doc ? ($doc['subdir_ruta'] ?: $doc['ruta']) : null;
        if (!$doc || !$t || !$origenRel || !is_array($caja)) {
            throw new RuntimeException('El oficio ya no está vigente.');
        }
        $relativa = 'titulacion/' . (int) $t['id'] . '/acuse_' . bin2hex(random_bytes(8)) . '.pdf';
        $mm = 25.4 / 72;
        SelloPdf::estampar(
            FirmaElectronica::almacen() . '/' . $origenRel, FirmaElectronica::almacen() . '/' . $relativa, (int) $caja['p'], 38.0, (float) $caja['y'] * $mm - 1.0,
            'ACUSE DE RECIBIDO · SINODAL',
            // Vale la fecha en que se le notificó, no la del clic: el plazo del pasante no depende de cuándo abra el correo.
            [mb_strtoupper((string) $s['nombre']), 'Notificado el ' . date('d/m/Y', strtotime((string) ($s['enviado_en'] ?: 'now'))) . ' · ' . $t['folio']]
        );
        $hash = hash_file('sha256', FirmaElectronica::almacen() . '/' . $relativa) ?: '';
        Database::ejecutar("UPDATE tit_sinodales SET estado = 'acuse', acuse_en = ?, firmante_id = ?, ruta_acuse = ?, hash_acuse = ? WHERE id = ?", [date('Y-m-d H:i:s'), (int) $usuario['id'], $relativa, $hash, (int) $s['id']]);
        Database::ejecutar('UPDATE tit_documentos SET subdir_ruta = ? WHERE id = ?', [$relativa, (int) $doc['id']]);
        TitulacionRepository::evento((int) $t['id'], (int) $usuario['id'], 'jurado', 'Acuse de recibido del oficio por ' . $s['nombre'] . '.', true);
        Audit::record('titulacion::acuse_sinodal', 'correcto', $t['folio'] . ' · ' . substr($hash, 0, 12));
    }

    /** Exámenes con acuse firmado para el calendario de una persona. @return array<int,array<string,mixed>> */
    public static function eventosDe(string $email, string $desde, string $hasta): array
    {
        return Database::todas(
            "SELECT a.fecha, a.hora, a.lugar, a.tipo, t.folio, t.datos, s.calidad
             FROM tit_sinodales s JOIN tit_asignaciones_jurado a ON a.id = s.asignacion_id JOIN tit_tramites t ON t.id = a.tramite_id
             WHERE s.email = ? AND s.estado = 'acuse' AND a.estado = 'firmada' AND a.fecha BETWEEN ? AND ? ORDER BY a.fecha, a.hora",
            [strtolower($email), $desde, $hasta]
        );
    }
}
