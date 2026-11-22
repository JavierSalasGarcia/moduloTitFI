<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

use App\Core\App;
use App\Core\Audit;
use App\Core\Cifrado;
use App\Core\Database;
use App\Core\Identidad;
use App\Modules\Titulacion\Services\SelloPdf;
use RuntimeException;

/**
 * Constancias solicitadas en este módulo (tabla cst_solicitudes): datos, cifrado en reposo, PDF, envío a firma,
 * devolución, firma y vinculación de cuentas.
 *
 * Cifrado (docs/CIFRADO_DE_DATOS.md): dirigido_a, cuerpo, persona_nombre, persona_email y observacion se guardan con
 * Cifrado::cifrar ligado a «cst_solicitudes.<columna>#<id>» (la observación del historial a «cst_historial.nota#<id>»).
 * El correo de la persona se busca por su índice ciego (persona_huella): «i:» + Cifrado::indiceCiego cuando hay llave;
 * sin llave, «h:» + SHA-256 del correo con un prefijo propio (se aceptan ambas formas al buscar). En claro quedan solo
 * el nombre y el cargo del firmante, el asunto, el folio y las fechas, que se usan para listar.
 * Archivos en storage/constancias/<id>/: la versión sin firma vigente y la firmada (con su huella SHA-256).
 */
final class Solicitudes
{
    public const ESTADOS = ['borrador' => 'Borrador', 'en_firma' => 'En firma', 'devuelta' => 'Devuelta para corregir', 'firmada' => 'Firmada', 'cancelada' => 'Cancelada'];
    private const CIFRADAS = ['dirigido_a', 'cuerpo', 'persona_nombre', 'persona_email', 'observacion'];

    // ------------------------------------------------------------------ utilidades

    private static function contexto(string $columna, int $id): string
    {
        return 'cst_solicitudes.' . $columna . '#' . $id;
    }

    private static function abrir(?string $valor, string $contexto): string
    {
        try {
            return (string) Cifrado::descifrar($valor, $contexto);
        } catch (\Throwable $e) {
            error_log('Constancias: no se pudo descifrar ' . $contexto . ': ' . $e->getMessage());
            return '';
        }
    }

    /** Índice ciego del correo de la persona. */
    public static function huella(string $email): ?string
    {
        $email = Identidad::normalizar($email);
        if ($email === '') {
            return null;
        }
        if (Cifrado::disponible()) {
            return 'i:' . Cifrado::indiceCiego($email, 'cst_solicitudes.persona_email');
        }
        return 'h:' . hash('sha256', 'trazafi-constancias|' . $email);
    }

    /** Todas las formas de huella de unos correos (con y sin llave), para buscar. @return string[] */
    public static function huellas(array $correos): array
    {
        $r = [];
        foreach ($correos as $c) {
            $c = Identidad::normalizar((string) $c);
            if ($c === '') {
                continue;
            }
            $r[] = 'h:' . hash('sha256', 'trazafi-constancias|' . $c);
            if (Cifrado::disponible()) {
                $r[] = 'i:' . Cifrado::indiceCiego($c, 'cst_solicitudes.persona_email');
            }
        }
        return array_values(array_unique($r));
    }

    /** UPDATE que devuelve las filas afectadas (Database::ejecutar devuelve el último id insertado). */
    private static function afectadas(string $sql, array $params): int
    {
        $st = Database::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function codigo(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $c = '';
        foreach (str_split(random_bytes(20)) as $b) {
            $c .= $alfabeto[ord($b) % 32];
        }
        return $c;
    }

    public static function base(): string
    {
        return rtrim((string) App::config('archivos.storage'), '/\\');
    }

    public static function absoluta(string $relativa): string
    {
        return self::base() . '/' . ltrim($relativa, '/');
    }

    private static function nuevaRuta(int $id, string $tipo): string
    {
        return 'constancias/' . $id . '/' . $tipo . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.pdf';
    }

    // ------------------------------------------------------------------ lectura

    /** Una constancia con sus datos descifrados, el solicitante y el departamento. */
    public static function obtener(int $id): ?array
    {
        $s = Database::una(
            'SELECT s.*, u.nombre AS solicitante_nombre, u.email AS solicitante_email, d.nombre AS departamento, d.clave AS depto_clave, f.email AS firmante_email, f.genero AS firmante_genero, pl.nombre AS plantilla_nombre
               FROM cst_solicitudes s JOIN usuarios u ON u.id = s.solicitante_id JOIN usuarios f ON f.id = s.firmante_id
               JOIN departamentos d ON d.id = s.departamento_id LEFT JOIN cst_plantillas pl ON pl.id = s.plantilla_id WHERE s.id = ?',
            [$id]
        );
        return $s ? self::descifrarFila($s) : null;
    }

    public static function descifrarFila(array $s): array
    {
        $id = (int) $s['id'];
        foreach (self::CIFRADAS as $c) {
            if (array_key_exists($c, $s)) {
                $s[$c] = self::abrir($s[$c], self::contexto($c, $id));
            }
        }
        if (array_key_exists('cuerpo', $s)) {
            $cuerpo = json_decode((string) $s['cuerpo'], true);
            $s['bloques'] = Cuerpo::sanear((array) ($cuerpo['bloques'] ?? []));
            $s['texto'] = (string) ($cuerpo['texto'] ?? '');
            $s['avisos'] = array_values(array_map('strval', (array) ($cuerpo['avisos'] ?? [])));
            $s['archivo'] = (string) ($cuerpo['archivo'] ?? '');
            if (array_key_exists('firmante_genero', $s)) {
                // Marcadores automáticos de las plantillas ({fecha_letras}, {firmante}, {cargo_firmante}, {suscribe}): se llenan con quien
                // firma y la fecha de expedición (la del envío a firma; antes de enviarla, la de hoy). El texto editable los conserva.
                $mapa = Plantillas::automaticos(['nombre' => $s['firmante_nombre'], 'cargo' => $s['firmante_cargo'], 'genero' => $s['firmante_genero']],
                    !empty($s['enviada_en']) ? substr((string) $s['enviada_en'], 0, 10) : date('Y-m-d'));
                $s['bloques'] = Plantillas::sustituirBloques($s['bloques'], $mapa);
            }
        }
        $s['caja'] = isset($s['caja']) && is_string($s['caja']) ? (json_decode($s['caja'], true) ?: null) : ($s['caja'] ?? null);
        return $s;
    }

    /** Lo que ha solicitado una persona (sin las canceladas). */
    public static function delSolicitante(int $usuarioId): array
    {
        return array_map([self::class, 'descifrarFila'], Database::todas(
            "SELECT s.id, s.estado, s.asunto, s.folio, s.firmante_nombre, s.firmante_cargo, s.persona_nombre, s.creado_en, s.enviada_en, s.firmada_en, s.devuelta_en, d.nombre AS departamento
               FROM cst_solicitudes s JOIN departamentos d ON d.id = s.departamento_id
              WHERE s.solicitante_id = ? AND s.estado <> 'cancelada' ORDER BY s.actualizado_en DESC LIMIT 200",
            [$usuarioId]
        ));
    }

    /** Bandeja del firmante: por firmar y firmadas recientemente. */
    public static function delFirmante(int $usuarioId): array
    {
        return array_map([self::class, 'descifrarFila'], Database::todas(
            "SELECT s.id, s.estado, s.asunto, s.folio, s.persona_nombre, s.enviada_en, s.firmada_en, u.nombre AS solicitante_nombre, d.nombre AS departamento
               FROM cst_solicitudes s JOIN usuarios u ON u.id = s.solicitante_id JOIN departamentos d ON d.id = s.departamento_id
              WHERE s.firmante_id = ? AND (s.estado = 'en_firma' OR (s.estado = 'firmada' AND s.firmada_en >= DATE_SUB(NOW(), INTERVAL 120 DAY)))
              ORDER BY s.estado = 'en_firma' DESC, COALESCE(s.firmada_en, s.enviada_en) DESC LIMIT 200",
            [$usuarioId]
        ));
    }

    public static function historial(int $id): array
    {
        $r = Database::todas('SELECT h.*, u.nombre FROM cst_historial h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.solicitud_id = ? ORDER BY h.id', [$id]);
        foreach ($r as &$h) {
            $h['nota'] = self::abrir($h['nota'], 'cst_historial.nota#' . $id);
        }
        return $r;
    }

    private static function anotar(int $id, ?int $usuarioId, string $accion, string $nota = ''): void
    {
        Database::ejecutar('INSERT INTO cst_historial (solicitud_id, usuario_id, accion, nota) VALUES (?, ?, ?, ?)',
            [$id, $usuarioId ?: null, $accion, $nota !== '' ? Cifrado::cifrar($nota, 'cst_historial.nota#' . $id) : null]);
    }

    // ------------------------------------------------------------------ escritura

    /**
     * Guarda una constancia nueva (borrador) o los cambios de una en borrador o devuelta, y regenera su vista previa.
     * @param array{dirigido_a:string,asunto:string,bloques:array,texto:string,avisos:array,archivo:string,origen:string,persona_nombre:string,persona_email:string,firmante:array,plantilla_id?:?int} $d (plantilla_id solo cuenta al crearla)
     */
    public static function guardar(?int $id, array $d, int $usuarioId): int
    {
        $f = $d['firmante'];
        $personaEmail = Identidad::normalizar($d['persona_email']);
        $persona = $personaEmail !== '' ? Identidad::resolver($personaEmail) : ['usuario_id' => null, 'profesor_id' => null];
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($id === null) {
                $id = Database::ejecutar(
                    'INSERT INTO cst_solicitudes (estado, solicitante_id, firmante_id, firmante_nombre, firmante_cargo, departamento_id, asunto, codigo, plantilla_id) VALUES (\'borrador\', ?, ?, ?, ?, ?, ?, ?, ?)',
                    [$usuarioId, $f['usuario_id'], mb_substr($f['nombre'], 0, 160), mb_substr($f['cargo'], 0, 255), $f['departamento_id'], mb_substr($d['asunto'], 0, 160), self::codigo(), ($d['plantilla_id'] ?? null) ?: null]
                );
                if ($id <= 0) {
                    throw new RuntimeException('No se obtuvo el identificador de la constancia.');
                }
                self::anotar($id, $usuarioId, 'creada');
            } else {
                Database::ejecutar('UPDATE cst_solicitudes SET firmante_id = ?, firmante_nombre = ?, firmante_cargo = ?, departamento_id = ?, asunto = ? WHERE id = ?',
                    [$f['usuario_id'], mb_substr($f['nombre'], 0, 160), mb_substr($f['cargo'], 0, 255), $f['departamento_id'], mb_substr($d['asunto'], 0, 160), $id]);
                self::anotar($id, $usuarioId, 'editada');
            }
            $cuerpo = json_encode(['bloques' => $d['bloques'], 'texto' => $d['texto'], 'avisos' => $d['avisos'], 'archivo' => $d['archivo']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            Database::ejecutar(
                'UPDATE cst_solicitudes SET dirigido_a = ?, cuerpo = ?, origen = ?, persona_nombre = ?, persona_email = ?, persona_huella = ?, persona_usuario_id = ?, persona_profesor_id = ? WHERE id = ?',
                [
                    Cifrado::cifrar($d['dirigido_a'], self::contexto('dirigido_a', $id)), Cifrado::cifrar($cuerpo, self::contexto('cuerpo', $id)), $d['origen'],
                    Cifrado::cifrar($d['persona_nombre'] !== '' ? $d['persona_nombre'] : null, self::contexto('persona_nombre', $id)),
                    Cifrado::cifrar($personaEmail !== '' ? $personaEmail : null, self::contexto('persona_email', $id)),
                    self::huella($personaEmail), $persona['usuario_id'], $persona['profesor_id'], $id,
                ]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        self::regenerar((int) $id, true);
        return (int) $id;
    }

    /** Genera el PDF sin firma (vista previa o el que se envía a firma) y lo deja como versión vigente. */
    private static function regenerar(int $id, bool $vistaPrevia): void
    {
        $s = self::obtener($id);
        if (!$s) {
            throw new RuntimeException('No se encontró la constancia.');
        }
        $folio = (string) ($s['folio'] ?? '') !== '' && (int) $s['folio_departamento_id'] === (int) $s['departamento_id']
            ? (string) $s['folio'] : Folio::provisional((int) $s['departamento_id']);
        [$bytes, $caja] = Pdf::generar([
            'asunto' => $s['asunto'], 'dirigido_a' => $s['dirigido_a'], 'bloques' => $s['bloques'], 'firmante_nombre' => $s['firmante_nombre'],
            'firmante_cargo' => $s['firmante_cargo'], 'folio' => $folio, 'codigo' => $s['codigo'],
            'fecha' => $vistaPrevia ? date('Y-m-d') : substr((string) ($s['enviada_en'] ?: date('Y-m-d')), 0, 10),
        ], $vistaPrevia);
        $relativa = self::nuevaRuta($id, $vistaPrevia ? 'vista-previa' : 'para-firma');
        $abs = self::absoluta($relativa);
        if (!is_dir(dirname($abs)) && !mkdir(dirname($abs), 0750, true) && !is_dir(dirname($abs))) {
            throw new RuntimeException('No se pudo crear la carpeta de la constancia.');
        }
        if (file_put_contents($abs, $bytes) === false) {
            throw new RuntimeException('No se pudo guardar el PDF.');
        }
        Database::ejecutar('UPDATE cst_solicitudes SET ruta = ?, huella = ?, caja = ? WHERE id = ?', [$relativa, hash('sha256', $bytes), json_encode($caja), $id]);
        if (!empty($s['ruta']) && $s['ruta'] !== $relativa) {
            @unlink(self::absoluta((string) $s['ruta']));
        }
    }

    /** Envía (o reenvía, tras una devolución) la constancia a firma: aparta el folio, genera el PDF y avisa al firmante. */
    public static function enviar(int $id, int $usuarioId): array
    {
        $s = self::obtener($id);
        if (!$s || !in_array($s['estado'], ['borrador', 'devuelta'], true)) {
            throw new RuntimeException('Esta constancia ya no se puede enviar a firma.');
        }
        if (!$s['bloques']) {
            throw new RuntimeException('La constancia no tiene texto.');
        }
        $reenvio = $s['estado'] === 'devuelta';
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ((string) ($s['folio'] ?? '') === '' || (int) $s['folio_departamento_id'] !== (int) $s['departamento_id']) {
                $f = Folio::apartar((int) $s['departamento_id']);
                Database::ejecutar('UPDATE cst_solicitudes SET folio = ?, folio_anio = ?, folio_numero = ?, folio_departamento_id = ? WHERE id = ?',
                    [$f['folio'], $f['anio'], $f['numero'], (int) $s['departamento_id'], $id]);
            }
            Database::ejecutar("UPDATE cst_solicitudes SET estado = 'en_firma', enviada_en = NOW() WHERE id = ? AND estado IN ('borrador','devuelta')", [$id]);
            self::anotar($id, $usuarioId, $reenvio ? 'reenviada' : 'enviada');
            self::regenerar($id, false);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $s = self::obtener($id) ?? $s;
        Audit::record('constancias::enviar', 'correcto', 'Constancia ' . $id . ' · ' . $s['folio'] . ($reenvio ? ' (reenvío)' : ''));
        Aviso::enviar((string) $s['firmante_email'], (string) $s['firmante_nombre'], 'Constancia por firmar · ' . $s['folio'],
            $s['solicitante_nombre'] . ($reenvio ? ' corrigió y volvió a enviar' : ' te envió') . ' una constancia para tu firma'
            . ($s['persona_nombre'] !== '' ? ', extendida a ' . $s['persona_nombre'] : '') . ".\nFolio: " . $s['folio']
            . "\n\nRevísala y fírmala en Traza FI (necesitas iniciar sesión). Si algo no está bien, puedes devolverla con una observación.",
            'constancias/firmar/' . $id, 'Revisar y firmar');
        return $s;
    }

    public static function cancelar(int $id, int $usuarioId): void
    {
        $n = self::afectadas("UPDATE cst_solicitudes SET estado = 'cancelada' WHERE id = ? AND estado IN ('borrador','devuelta')", [$id]);
        if ($n === 0) {
            throw new RuntimeException('Solo se puede descartar una constancia que no está en firma.');
        }
        self::anotar($id, $usuarioId, 'cancelada');
        Audit::record('constancias::cancelar', 'correcto', 'Constancia ' . $id);
    }

    public static function devolver(int $id, int $firmanteId, string $observacion): void
    {
        $s = self::obtener($id);
        if (!$s || $s['estado'] !== 'en_firma' || (int) $s['firmante_id'] !== $firmanteId) {
            throw new RuntimeException('Esta constancia ya no está esperando tu firma.');
        }
        Database::ejecutar("UPDATE cst_solicitudes SET estado = 'devuelta', devuelta_en = NOW(), observacion = ? WHERE id = ? AND estado = 'en_firma'",
            [Cifrado::cifrar($observacion, self::contexto('observacion', $id)), $id]);
        self::anotar($id, $firmanteId, 'devuelta', $observacion);
        Audit::record('constancias::devolver', 'correcto', 'Constancia ' . $id . ' · ' . $s['folio']);
        Aviso::enviar((string) $s['solicitante_email'], (string) $s['solicitante_nombre'], 'Constancia devuelta para corregir · ' . $s['folio'],
            $s['firmante_nombre'] . " devolvió la constancia con esta observación:\n\n«" . $observacion . "»\n\nCorrígela y vuelve a enviarla a firma.",
            'constancias/solicitud/' . $id, 'Corregir la constancia');
    }

    /** Firma con sesión: estampa el sello (con el rótulo vigente) en la caja de firma y guarda el PDF firmado con su huella. */
    public static function firmar(int $id, int $firmanteId): array
    {
        $lock = 'cst_firma_' . $id;
        Database::valor('SELECT GET_LOCK(?, 15)', [$lock]);
        try {
            $s = self::obtener($id);
            if (!$s || $s['estado'] !== 'en_firma' || (int) $s['firmante_id'] !== $firmanteId) {
                throw new RuntimeException('Esta constancia ya no está esperando tu firma.');
            }
            $origen = self::absoluta((string) $s['ruta']);
            $caja = $s['caja'];
            if ((string) $s['ruta'] === '' || !is_file($origen) || !is_array($caja) || !hash_equals((string) $s['huella'], (string) hash_file('sha256', $origen))) {
                throw new RuntimeException('No se encontró el documento a firmar o cambió desde que se envió.');
            }
            $relativa = self::nuevaRuta($id, 'firmada');
            $destino = self::absoluta($relativa);
            $ahora = date('Y-m-d H:i:s');
            // Cabecera corta para que quepa en el sello; SelloPdf la rotula «CONFORMIDAD» mientras no se apruebe la firma electrónica.
            $cabecera = 'FIRMA ELECTRÓNICA · CONSTANCIA ' . Folio::siglas((int) $s['departamento_id']);
            SelloPdf::estampar($origen, $destino, (int) $caja['p'], (float) $caja['x'], (float) $caja['y'], $cabecera,
                [mb_strtoupper((string) $s['firmante_nombre']), 'Firmado el ' . date('d/m/Y H:i', strtotime($ahora)) . ' · ' . $s['folio']]);
            $hash = (string) hash_file('sha256', $destino);
            $n = self::afectadas(
                "UPDATE cst_solicitudes SET estado = 'firmada', firmada_en = ?, ruta_firmado = ?, huella_firmado = ?, firmada_ip = ?, firmada_agente = ? WHERE id = ? AND estado = 'en_firma'",
                [$ahora, $relativa, $hash, mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $id]
            );
            if ($n === 0) {
                @unlink($destino);
                throw new RuntimeException('Esta constancia ya no está esperando tu firma.');
            }
            self::anotar($id, $firmanteId, 'firmada');
        } finally {
            Database::valor('SELECT RELEASE_LOCK(?)', [$lock]);
        }
        Audit::record('constancias::firmar', 'correcto', 'Constancia ' . $id . ' · ' . $s['folio'] . ' · ' . substr($hash, 0, 16));
        $texto = 'La constancia ' . $s['folio'] . ($s['persona_nombre'] !== '' ? ', extendida a ' . $s['persona_nombre'] . ',' : '') . ' ya está firmada por '
            . $s['firmante_nombre'] . ".\nDescárgala en Traza FI, en «Constancias → Constancias firmadas» (necesitas iniciar sesión).";
        Aviso::enviar((string) $s['solicitante_email'], (string) $s['solicitante_nombre'], 'Constancia firmada · ' . $s['folio'], $texto, 'constancias/solicitud/' . $id, 'Descargar la constancia');
        $persona = Identidad::normalizar((string) $s['persona_email']);
        if ($persona !== '' && $persona !== Identidad::normalizar((string) $s['solicitante_email'])) {
            Aviso::enviar($persona, (string) $s['persona_nombre'], 'Tienes una constancia firmada · ' . $s['folio'],
                'Se te extendió la constancia ' . $s['folio'] . ', firmada por ' . $s['firmante_nombre'] . ', ' . $s['firmante_cargo']
                . ".\nLa encuentras en Traza FI, en «Constancias → Constancias firmadas». Si aún no tienes cuenta, regístrate con este correo y aparecerá ahí.",
                'constancias', 'Ver mis constancias');
        }
        return self::obtener($id) ?? $s;
    }

    /**
     * Proveedor de vinculación (manifiesto): las constancias extendidas a un correo verificado de la cuenta, o a su ficha
     * de profesor, pasan a la cuenta. Nunca por nombre.
     */
    public static function vincular(int $usuarioId, array $correos, ?int $profesorId): int
    {
        $cond = [];
        $par = [];
        $huellas = self::huellas($correos);
        if ($huellas) {
            $cond[] = 'persona_huella IN (' . implode(',', array_fill(0, count($huellas), '?')) . ')';
            $par = $huellas;
        }
        if ($profesorId) {
            $cond[] = 'persona_profesor_id = ?';
            $par[] = $profesorId;
        }
        if (!$cond) {
            return 0;
        }
        return self::afectadas('UPDATE cst_solicitudes SET persona_usuario_id = ? WHERE persona_usuario_id IS NULL AND (' . implode(' OR ', $cond) . ')', [$usuarioId, ...$par]);
    }

    // ------------------------------------------------------------------ verificación pública

    /** @return ?array{folio:string,estado:string,vigente:bool,fecha:?string,firmante:string,cargo:string,departamento:string,persona:string,asunto:string,huella:?string} */
    public static function verificar(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        if (!preg_match('/^[A-Z2-9]{20}$/', $codigo)) {
            return null;
        }
        $s = Database::una('SELECT s.*, d.nombre AS departamento FROM cst_solicitudes s JOIN departamentos d ON d.id = s.departamento_id WHERE s.codigo = ?', [$codigo]);
        if (!$s) {
            return null;
        }
        $id = (int) $s['id'];
        $estado = match ((string) $s['estado']) {
            'firmada' => 'Firmada',
            'en_firma' => 'En firma: aún no tiene validez',
            'cancelada' => 'Cancelada: no tiene validez',
            default => 'No emitida: no tiene validez',
        };
        return [
            'folio' => (string) ($s['folio'] ?? ''), 'estado' => $estado, 'vigente' => $s['estado'] === 'firmada',
            'fecha' => $s['firmada_en'] ?: null, 'firmante' => (string) $s['firmante_nombre'], 'cargo' => (string) $s['firmante_cargo'],
            'departamento' => (string) $s['departamento'], 'persona' => self::abrir($s['persona_nombre'], self::contexto('persona_nombre', $id)),
            'asunto' => (string) $s['asunto'], 'huella' => $s['estado'] === 'firmada' ? (string) $s['huella_firmado'] : null,
        ];
    }
}
