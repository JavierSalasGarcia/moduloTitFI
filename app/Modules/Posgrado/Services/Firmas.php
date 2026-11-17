<?php
declare(strict_types=1);

namespace App\Modules\Posgrado\Services;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Database;
use App\Modules\Perfil\FirmaUaemex;
use App\Modules\Posgrado\Catalogo;
use App\Modules\Posgrado\Configuracion;
use App\Modules\Posgrado\Flujo;
use App\Modules\Posgrado\PosgradoRepository;
use App\Modules\Titulacion\Services\SelloPdf;
use RuntimeException;
use Throwable;

/**
 * Firmas en línea del trámite de evaluación de grado (como la titulación de licenciatura, Titulacion\Services\FirmasExternas).
 *
 * Cada documento firmable se genera UNA vez (Formatos::generarConCajas), se guarda en storage/posgrado/<id>/ y se registra
 * en pos_generados; por cada firmante se crea un renglón en pos_firmas con la «caja» donde va su sello. Director y
 * sinodales reciben por correo un enlace personal (token aleatorio de 256 bits; en la base solo su SHA-256) y firman sin
 * cuenta; quien tenga sesión con ese mismo correo también firma desde «Posgrado: firmas». La Coordinación de Estudios
 * Avanzados y el egresado firman con su sesión. Cada firma estampa su sello (SelloPdf, con la política vigente de rótulo)
 * sobre el archivo vigente y deja un archivo nuevo con su huella.
 *
 * Si los datos que alimentan un documento cambian antes de que firmen todos, el documento se regenera y sus firmas se
 * cancelan; firmado por todos, esos datos quedan bloqueados para el egresado.
 */
final class Firmas
{
    /** Documentos que dependen de lo que captura el egresado (se regeneran si cambian sus datos). */
    public const DEL_EGRESADO = ['voto', 'datos_interesado', 'solicitud', 'visto_bueno'];

    /** Datos (de Formatos::datos) que alimentan cada documento del egresado; los correos no van en los documentos. */
    private const CAMPOS = [
        'voto' => ['nombre', 'cuenta', 'programa', 'fecha_previo', 'modalidad_nombre', 'titulo', 'director'],
        'datos_interesado' => ['nombre', 'cuenta', 'acentos', 'plan', 'programa', 'telefono', 'correo', 'ocupacion'],
        'solicitud' => ['nombre', 'cuenta', 'programa', 'modalidad_nombre', 'titulo', 'propuesta', 'miembros', 'director'],
        'visto_bueno' => ['programa', 'nombre', 'titulo', 'propuesta', 'miembros'],
    ];

    // ------------------------------------------------------------------ utilidades

    public static function nombreDocumento(string $doc): string
    {
        return Catalogo::firmables()[$doc]['nombre'] ?? $doc;
    }

    public static function etiquetaRol(array $f): string
    {
        return match ((string) $f['rol']) {
            'director' => 'Director(a) de tesis',
            'sinodal' => ((int) $f['orden'] < 5 ? 'Propietario ' . ((int) $f['orden'] + 1) : 'Suplente ' . ((int) $f['orden'] - 4)) . ' del sínodo',
            'coordinacion' => 'Coordinación de Estudios Avanzados',
            'egresado' => 'Egresado(a)',
            default => 'Firmante',
        };
    }

    private static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function enlace(string $token): string
    {
        return Aviso::enlace('posgrado/firma/t/' . $token);
    }

    private static function vence(): string
    {
        return date('Y-m-d 23:59:59', strtotime('+' . Configuracion::diasToken() . ' days'));
    }

    /** SHA-256 de los datos que alimentan un documento del egresado. */
    public static function huellaDatos(string $doc, array $t, array $hitos): ?string
    {
        if (!isset(self::CAMPOS[$doc])) {
            return null;
        }
        $x = Formatos::datos($t, $hitos);
        $sub = array_intersect_key($x, array_flip(self::CAMPOS[$doc]));
        ksort($sub);
        return hash('sha256', json_encode($sub, JSON_UNESCAPED_UNICODE));
    }

    private static function decodificar(?string $json): array
    {
        $d = $json !== null && $json !== '' ? json_decode($json, true) : null;
        return is_array($d) ? $d : [];
    }

    /** Fecha + 1 mes ≤ vigencia: la regla de la vigencia de la firma electrónica UAEMéx frente a la fecha del examen. */
    public static function vigenciaAlcanza(?string $vigencia, string $fechaExamen): bool
    {
        return $vigencia !== null && FirmaUaemex::fechaValida($vigencia) && FirmaUaemex::fechaValida($fechaExamen)
            && FirmaUaemex::masUnMes($fechaExamen) <= $vigencia;
    }

    // ------------------------------------------------------------------ consultas

    public static function una(int $id): ?array
    {
        return Database::una('SELECT * FROM pos_firmas WHERE id = ?', [$id]);
    }

    /** Firma por token del enlace: solo si no está cancelada ni vencida. */
    public static function porToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $token)) {
            return null;
        }
        $f = Database::una('SELECT * FROM pos_firmas WHERE token_hash = ?', [hash('sha256', $token)]);
        return $f && $f['estado'] !== 'cancelada' && (string) $f['vence'] >= date('Y-m-d H:i:s') ? $f : null;
    }

    /**
     * Firmas vigentes (no canceladas) de un trámite, por documento, con su emisión.
     * @return array<string,array{generado:array,firmas:array<int,array>,completo:bool,pendientes:int}>
     */
    public static function estado(int $tramiteId): array
    {
        $r = [];
        foreach (Database::todas(
            "SELECT f.*, g.clave AS g_clave, g.generado_en AS g_en FROM pos_firmas f JOIN pos_generados g ON g.id = f.generado_id AND g.vigente = 1
              WHERE f.tramite_id = ? AND f.estado <> 'cancelada' ORDER BY f.documento, FIELD(f.rol, 'egresado', 'director', 'coordinacion', 'sinodal'), f.orden, f.id",
            [$tramiteId]
        ) as $f) {
            $f['respuestas'] = self::decodificar($f['respuestas']);
            $doc = (string) $f['documento'];
            $r[$doc] ??= ['generado_id' => (int) $f['generado_id'], 'firmas' => [], 'completo' => true, 'pendientes' => 0];
            $r[$doc]['firmas'][] = $f;
            if ($f['estado'] !== 'firmada') {
                $r[$doc]['completo'] = false;
                $r[$doc]['pendientes']++;
            }
        }
        return $r;
    }

    /** ¿Firmaron todos los de este rol en el documento? (false si no hay firmas de ese rol) */
    public static function rolCompleto(array $estado, string $doc, string $rol): bool
    {
        $f = array_filter($estado[$doc]['firmas'] ?? [], static fn(array $x): bool => $x['rol'] === $rol);
        return $f !== [] && !array_filter($f, static fn(array $x): bool => $x['estado'] !== 'firmada');
    }

    /** Firmas pedidas a estos correos (enviadas): pendientes de trámites abiertos y, si se piden, también las ya hechas. */
    public static function deCorreos(array $correos, bool $soloPendientes = true): array
    {
        $correos = array_values(array_filter($correos));
        if (!$correos) {
            return [];
        }
        return Database::todas(
            "SELECT f.id, f.documento, f.rol, f.orden, f.nombre, f.estado, f.enviada_en, f.firmada_en, f.creado_en, t.folio, t.titulo, u.nombre AS alumno_nombre
               FROM pos_firmas f JOIN pos_tramites t ON t.id = f.tramite_id JOIN usuarios u ON u.id = t.alumno_id JOIN pos_generados g ON g.id = f.generado_id AND g.vigente = 1
              WHERE f.email IN (" . implode(',', array_fill(0, count($correos), '?')) . ") AND f.enviada_en IS NOT NULL
                AND " . ($soloPendientes ? "f.estado = 'pendiente' AND t.estado = 'abierto'" : "(f.estado = 'firmada' OR (f.estado = 'pendiente' AND t.estado = 'abierto'))") . '
              ORDER BY f.estado = \'pendiente\' DESC, f.id DESC LIMIT 100',
            $correos
        );
    }

    public static function hayDeCorreo(array $correos): bool
    {
        return self::deCorreos($correos, false) !== [];
    }

    /** Lo que espera la firma de la Coordinación. */
    public static function pendientesCoordinacion(): array
    {
        return Database::todas(
            "SELECT f.id, f.tramite_id, f.documento, f.creado_en, t.folio, t.nivel, u.nombre AS alumno_nombre, g.nombre AS g_nombre
               FROM pos_firmas f JOIN pos_tramites t ON t.id = f.tramite_id JOIN usuarios u ON u.id = t.alumno_id JOIN pos_generados g ON g.id = f.generado_id AND g.vigente = 1
              WHERE f.rol = 'coordinacion' AND f.estado = 'pendiente' AND t.estado = 'abierto'
              ORDER BY f.id"
        );
    }

    /** Firmadas recientemente por la Coordinación (para la pantalla de firmas). */
    public static function firmadasCoordinacion(int $limite = 20): array
    {
        return Database::todas(
            "SELECT f.id, f.documento, f.firmada_en, f.generado_id, t.folio, u.nombre AS alumno_nombre, s.nombre AS firmo
               FROM pos_firmas f JOIN pos_tramites t ON t.id = f.tramite_id JOIN usuarios u ON u.id = t.alumno_id LEFT JOIN usuarios s ON s.id = f.firmante_id
              WHERE f.rol = 'coordinacion' AND f.estado = 'firmada' ORDER BY f.firmada_en DESC LIMIT " . max(1, $limite)
        );
    }

    /**
     * Sinodales cuya vigencia declarada no llega a un mes después de la fecha del examen.
     * @return array<int,array{nombre:string,vigencia:string,orden:int}>
     */
    public static function vigenciasInsuficientes(array $estado, string $fechaExamen): array
    {
        $r = [];
        foreach ($estado['visto_bueno']['firmas'] ?? [] as $f) {
            $v = (string) ($f['respuestas']['vigencia'] ?? '');
            if ($f['estado'] === 'firmada' && !self::vigenciaAlcanza($v !== '' ? $v : null, $fechaExamen)) {
                $r[] = ['nombre' => (string) $f['nombre'], 'vigencia' => $v, 'orden' => (int) $f['orden']];
            }
        }
        return $r;
    }

    /** Integrantes que contestaron «No» a alguna pregunta del visto bueno. @return array<int,array{nombre:string,preguntas:string[]}> */
    public static function respuestasNo(array $estado): array
    {
        $r = [];
        foreach ($estado['visto_bueno']['firmas'] ?? [] as $f) {
            $no = [];
            foreach (Catalogo::preguntasVistoBueno() as $k => $p) {
                if (($f['respuestas'][$k] ?? '') === 'no') {
                    $no[] = $p;
                }
            }
            if ($f['estado'] === 'firmada' && $no) {
                $r[] = ['nombre' => (string) $f['nombre'], 'preguntas' => $no];
            }
        }
        return $r;
    }

    /** Vigencia que se precarga en la página del sinodal: la que ya declaró en este u otro trámite, o la de su cuenta. */
    public static function vigenciaConocida(array $f, ?int $usuarioSesion = null): ?string
    {
        $r = self::decodificar(is_array($f['respuestas'] ?? null) ? json_encode($f['respuestas']) : ($f['respuestas'] ?? null));
        if (!empty($r['vigencia'])) {
            return (string) $r['vigencia'];
        }
        if ($usuarioSesion) {
            $v = FirmaUaemex::deUsuario($usuarioSesion);
            if ($v !== null) {
                return $v;
            }
        }
        $email = strtolower((string) ($f['email'] ?? ''));
        if ($email === '') {
            return null;
        }
        $previa = Database::valor(
            "SELECT JSON_UNQUOTE(JSON_EXTRACT(respuestas, '$.vigencia')) FROM pos_firmas
              WHERE email = ? AND documento = 'visto_bueno' AND estado = 'firmada' AND JSON_EXTRACT(respuestas, '$.vigencia') IS NOT NULL ORDER BY firmada_en DESC LIMIT 1",
            [$email]
        );
        if (is_string($previa) && FirmaUaemex::fechaValida($previa)) {
            return $previa;
        }
        return FirmaUaemex::cuentaDe($email)['fise_vigencia'] ?? null;
    }

    // ------------------------------------------------------------------ emisión

    /** Quiénes firman cada documento, con nombre y correo tomados del trámite. */
    private static function firmantes(string $doc, array $t): array
    {
        $i = $t['datos']['interesado'] ?? [];
        $x = Formatos::datos($t, []);
        $director = ['rol' => 'director', 'orden' => null, 'nombre' => $x['director'], 'email' => strtolower(trim((string) ($i['director_correo'] ?? ''))), 'caja' => 'director'];
        $coordinacion = ['rol' => 'coordinacion', 'orden' => null, 'nombre' => Configuracion::firmante(), 'email' => null, 'caja' => 'coordinacion'];
        $sinodales = [];
        foreach (array_slice(array_values($t['datos']['sinodo']['miembros'] ?? []), 0, 7) as $n => $m) {
            if (trim((string) ($m['nombre'] ?? '')) !== '') {
                $sinodales[] = ['rol' => 'sinodal', 'orden' => $n, 'nombre' => trim((string) $m['nombre']), 'email' => strtolower(trim((string) ($m['correo'] ?? ''))), 'caja' => 'sinodal_' . $n];
            }
        }
        return match ($doc) {
            'voto', 'solicitud' => [$director],
            'datos_interesado' => [['rol' => 'egresado', 'orden' => null, 'nombre' => trim((string) ($i['nombre'] ?? $t['alumno_nombre'] ?? '')), 'email' => null, 'caja' => 'egresado']],
            'visto_bueno' => $sinodales,
            'acuse' => array_merge([$coordinacion], $sinodales),
            default => [$coordinacion],
        };
    }

    /**
     * Genera el documento una sola vez, lo guarda y pide las firmas. La emisión anterior y sus firmas (pendientes y
     * hechas) quedan canceladas. Los enlaces al director y al sínodo se envían de inmediato, salvo el acuse del sínodo,
     * que sale cuando la Coordinación firma las invitaciones y el acuse.
     * @return int id en pos_generados
     */
    public static function emitir(array $t, string $doc, int $usuarioId, string $motivo = ''): int
    {
        if (!isset(Catalogo::firmables()[$doc])) {
            throw new RuntimeException('Documento desconocido.');
        }
        $hitos = PosgradoRepository::hitos((int) $t['id']);
        [$bytes, , $cajas] = Formatos::generarConCajas($doc, $t, $hitos);
        $anteriores = self::cancelarDocumento((int) $t['id'], $doc, $usuarioId, $motivo, false);
        $gid = Expediente::guardar($t, $doc, self::nombreDocumento($doc), $bytes, $usuarioId, self::huellaDatos($doc, $t, $hitos));
        $nombres = [];
        foreach (self::firmantes($doc, $t) as $p) {
            $caja = $cajas[$p['caja']] ?? null;
            if (!is_array($caja) || empty($caja['sellos'])) {
                continue;
            }
            if ($doc === 'invitaciones' && $p['rol'] === 'coordinacion') {
                $caja['paginas'] = $cajas['paginas'] ?? [];
            }
            $id = Database::ejecutar(
                'INSERT INTO pos_firmas (tramite_id, documento, generado_id, rol, orden, nombre, email, caja) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $t['id'], $doc, $gid, $p['rol'], $p['orden'], mb_substr($p['nombre'] !== '' ? $p['nombre'] : self::etiquetaRol($p), 0, 160), $p['email'] ?: null, json_encode($caja)]
            );
            $nombres[] = $p['nombre'];
            if (in_array($p['rol'], ['director', 'sinodal'], true) && $doc !== 'acuse') {
                self::enviar($id);
            }
        }
        PosgradoRepository::evento((int) $t['id'], $usuarioId, 'firma', ($anteriores ? 'Se regeneró ' : 'Se generó ') . mb_strtolower(self::nombreDocumento($doc)) . ' y se pidió la firma en línea de: ' . implode(', ', array_slice($nombres, 0, 8)) . '.');
        Audit::record('posgrado::emitir_firma', 'correcto', 'Trámite ' . (int) $t['id'] . ' · ' . $doc . ' · generado ' . $gid);
        return $gid;
    }

    /**
     * Retira la emisión vigente de un documento y cancela sus firmas (pendientes y hechas).
     * @return int firmas canceladas
     */
    public static function cancelarDocumento(int $tramiteId, string $doc, int $usuarioId, string $motivo = '', bool $retirar = true): int
    {
        $n = (int) Database::valor(
            "SELECT COUNT(*) FROM pos_firmas f JOIN pos_generados g ON g.id = f.generado_id AND g.vigente = 1 WHERE f.tramite_id = ? AND f.documento = ? AND f.estado <> 'cancelada'",
            [$tramiteId, $doc]
        );
        Database::ejecutar(
            "UPDATE pos_firmas SET estado = 'cancelada', cancelada_en = NOW(), token_hash = NULL WHERE tramite_id = ? AND documento = ? AND estado <> 'cancelada'",
            [$tramiteId, $doc]
        );
        if ($retirar) {
            PosgradoRepository::retirarGenerado($tramiteId, $doc);
        }
        if ($n > 0) {
            PosgradoRepository::evento($tramiteId, $usuarioId, 'firma', 'Se cancelaron las firmas de ' . mb_strtolower(self::nombreDocumento($doc)) . ($motivo !== '' ? ': ' . $motivo : '.'), true);
        }
        return $n;
    }

    // ------------------------------------------------------------------ correos

    /** Envía (o reenvía) el enlace personal: token nuevo, el anterior deja de servir. */
    public static function enviar(int $firmaId, bool $recordatorio = false): bool
    {
        $f = self::una($firmaId);
        if (!$f || !filter_var((string) $f['email'], FILTER_VALIDATE_EMAIL) || !in_array($f['rol'], ['director', 'sinodal'], true)) {
            return false;
        }
        $t = PosgradoRepository::obtener((int) $f['tramite_id']);
        if (!$t) {
            return false;
        }
        $token = self::token();
        Database::ejecutar('UPDATE pos_firmas SET token_hash = ?, vence = ?, enviada_en = NOW() WHERE id = ?', [hash('sha256', $token), self::vence(), $firmaId]);
        $i = $t['datos']['interesado'] ?? [];
        $alumno = trim((string) ($i['nombre'] ?? $t['alumno_nombre'] ?? ''));
        $titulo = trim((string) ($i['titulo'] ?? ''));
        $programa = trim((string) ($i['programa'] ?? ''));
        $comoQuien = mb_strtolower(self::etiquetaRol($f));
        $vence = date('d/m/Y', strtotime((string) self::vence()));
        if ($f['documento'] === 'acuse') {
            $asunto = 'Invitación al sínodo de evaluación de grado · ' . $alumno;
            $texto = "La Coordinación de Estudios Avanzados de la Facultad de Ingeniería le invita a integrar, como $comoQuien, el sínodo de la evaluación de grado de $alumno ($programa), trabajo «{$titulo}».\n\n"
                . "En el enlace encontrará su oficio de invitación firmado. Léalo y pulse «Acusar de recibido»: así queda su acuse en línea. No necesita cuenta en Traza FI ni imprimir nada.";
            $boton = 'Ver mi invitación y acusar de recibido';
        } elseif ($f['documento'] === 'visto_bueno') {
            $asunto = 'Visto bueno para evaluación de grado · ' . $alumno;
            $texto = "Se le pide, como $comoQuien propuesto, su visto bueno para la evaluación de grado de $alumno ($programa), trabajo «{$titulo}».\n\n"
                . "En el enlace contestará tres preguntas (tesis concluida, tesis entregada a usted y revisión en el periodo solicitado), declarará la fecha de vigencia de su firma electrónica UAEMéx (la consulta en " . FirmaUaemex::URL . ") y firmará en línea. No necesita cuenta en Traza FI ni imprimir nada.";
            $boton = 'Revisar y firmar';
        } else {
            $que = Catalogo::firmables()[$f['documento']]['que'] ?? 'el documento';
            $asunto = Catalogo::firmables()[$f['documento']]['nombre'] . ' · ' . $alumno;
            $texto = "Se le pide firmar en línea, como director(a) de tesis, $que de $alumno ($programa), trabajo «{$titulo}».\n\n"
                . 'Abra el enlace, lea el documento y pulse «Firmar». No necesita cuenta en Traza FI ni imprimir nada.';
            $boton = 'Revisar y firmar';
        }
        $texto .= "\n\nEl enlace es personal y vence el $vence; no lo comparta. Si tiene cuenta en Traza FI con este mismo correo, también lo encontrará en «Posgrado: firmas».";
        Aviso::firmante((string) $f['email'], (string) $f['nombre'], ($recordatorio ? 'Recordatorio · ' : '') . $asunto, $texto, self::enlace($token), $boton);
        Audit::record('posgrado::firma_enviada', 'correcto', 'Firma ' . $firmaId . ($recordatorio ? ' (reenvío)' : ''));
        return true;
    }

    /** Reenvío: solo a quien no ha firmado, o al sinodal que debe actualizar la vigencia de su firma UAEMéx. */
    public static function reenviar(array $f, array $t, int $usuarioId): void
    {
        if ($f['estado'] === 'cancelada' || !in_array($f['rol'], ['director', 'sinodal'], true)) {
            throw new RuntimeException('Ese enlace ya no se puede reenviar.');
        }
        $respuestas = self::decodificar($f['respuestas']);
        $fecha = (string) ($t['datos']['sinodo']['fecha'] ?? '');
        $corregir = $f['estado'] === 'firmada' && $f['documento'] === 'visto_bueno' && $fecha !== '' && !self::vigenciaAlcanza($respuestas['vigencia'] ?? null, $fecha);
        if ($f['estado'] === 'firmada' && !$corregir) {
            throw new RuntimeException($f['nombre'] . ' ya firmó; no hace falta reenviar.');
        }
        if ($f['documento'] === 'acuse' && $f['enviada_en'] === null) {
            throw new RuntimeException('La invitación se enviará cuando la Coordinación la firme.');
        }
        if (!self::enviar((int) $f['id'], true)) {
            throw new RuntimeException('No hay un correo válido para ' . $f['nombre'] . '.');
        }
        PosgradoRepository::evento((int) $t['id'], $usuarioId, 'firma', 'Se reenvió a ' . $f['nombre'] . ' el enlace de ' . mb_strtolower(self::nombreDocumento((string) $f['documento'])) . '; el anterior dejó de servir.');
        Audit::record('posgrado::firma_reenvio', 'correcto', 'Firma ' . (int) $f['id']);
    }

    /** Corrige el correo de un firmante que no ha firmado (y en los datos del trámite); si ya se envió, sale un enlace nuevo. */
    public static function corregirCorreo(array $f, array $t, string $email, int $usuarioId): void
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
            throw new RuntimeException('Escribe un correo válido.');
        }
        if ($f['estado'] !== 'pendiente' || !in_array($f['rol'], ['director', 'sinodal'], true)) {
            throw new RuntimeException('Solo se corrige el correo de quien aún no firma.');
        }
        $datos = $t['datos'];
        if ($f['rol'] === 'director') {
            $datos['interesado']['director_correo'] = $email;
        } elseif (isset($datos['sinodo']['miembros'][(int) $f['orden']])) {
            $datos['sinodo']['miembros'][(int) $f['orden']]['correo'] = $email;
        }
        PosgradoRepository::guardarDatos((int) $t['id'], $datos);
        // El mismo firmante en otros documentos aún sin firmar recibe el correo corregido.
        $mismos = Database::todas(
            "SELECT id, enviada_en FROM pos_firmas WHERE tramite_id = ? AND rol = ? AND estado = 'pendiente' AND " . ($f['rol'] === 'sinodal' ? 'orden = ?' : '1 = ?'),
            [(int) $t['id'], $f['rol'], $f['rol'] === 'sinodal' ? (int) $f['orden'] : 1]
        );
        foreach ($mismos as $m) {
            Database::ejecutar('UPDATE pos_firmas SET email = ?, token_hash = NULL WHERE id = ?', [$email, (int) $m['id']]);
            if ($m['enviada_en'] !== null) {
                self::enviar((int) $m['id']);
            }
        }
        PosgradoRepository::evento((int) $t['id'], $usuarioId, 'firma', 'Se corrigió el correo de ' . $f['nombre'] . ' y se le envió un enlace nuevo.');
        Audit::record('posgrado::firma_correo', 'correcto', 'Firma ' . (int) $f['id']);
    }

    // ------------------------------------------------------------------ datos del egresado

    /**
     * Documentos firmados por todos cuyos datos cambiarían con $tNuevo: esos datos están bloqueados.
     * @return string[] nombres de documento
     */
    public static function bloqueos(array $tNuevo, array $hitos): array
    {
        $estado = self::estado((int) $tNuevo['id']);
        $vig = PosgradoRepository::generadosVigentes((int) $tNuevo['id']);
        $r = [];
        foreach (self::DEL_EGRESADO as $doc) {
            if (!empty($estado[$doc]['completo']) && isset($vig[$doc]) && (string) $vig[$doc]['huella_datos'] !== (string) self::huellaDatos($doc, $tNuevo, $hitos)) {
                $r[] = self::nombreDocumento($doc);
            }
        }
        return $r;
    }

    /**
     * Tras guardar datos del egresado: los documentos en firma cuyos datos cambiaron se regeneran (sus firmas se cancelan
     * y salen enlaces nuevos); si solo cambió el correo de un firmante que no ha firmado, se le envía el enlace al nuevo.
     * @return string[] documentos regenerados
     */
    public static function sincronizarDatos(int $tramiteId, int $usuarioId): array
    {
        $t = PosgradoRepository::obtener($tramiteId);
        if (!$t) {
            return [];
        }
        $hitos = PosgradoRepository::hitos($tramiteId);
        $estado = self::estado($tramiteId);
        $vig = PosgradoRepository::generadosVigentes($tramiteId);
        $regenerados = [];
        foreach (self::DEL_EGRESADO as $doc) {
            if (!isset($vig[$doc], $estado[$doc]) || $estado[$doc]['completo']) {
                continue;
            }
            if ((string) $vig[$doc]['huella_datos'] !== (string) self::huellaDatos($doc, $t, $hitos)) {
                if (Formatos::impedimento($doc, $t, $hitos) !== null) {
                    self::cancelarDocumento($tramiteId, $doc, $usuarioId, 'cambiaron los datos y el formato quedó incompleto');
                } else {
                    self::emitir($t, $doc, $usuarioId, 'cambiaron los datos');
                    PosgradoRepository::evento($tramiteId, $usuarioId, 'firma', 'Cambiaron los datos de ' . mb_strtolower(self::nombreDocumento($doc)) . ': se regeneró y sus firmas anteriores se cancelaron.');
                }
                $regenerados[] = self::nombreDocumento($doc);
                continue;
            }
            // Mismo documento: ¿cambió el correo de alguien que no ha firmado?
            $i = $t['datos']['interesado'] ?? [];
            foreach ($estado[$doc]['firmas'] as $f) {
                if ($f['estado'] !== 'pendiente' || !in_array($f['rol'], ['director', 'sinodal'], true)) {
                    continue;
                }
                $actual = $f['rol'] === 'director' ? (string) ($i['director_correo'] ?? '') : (string) ($t['datos']['sinodo']['miembros'][(int) $f['orden']]['correo'] ?? '');
                $actual = strtolower(trim($actual));
                if ($actual !== '' && $actual !== strtolower((string) $f['email'])) {
                    Database::ejecutar('UPDATE pos_firmas SET email = ?, token_hash = NULL WHERE id = ?', [$actual, (int) $f['id']]);
                    self::enviar((int) $f['id']);
                    PosgradoRepository::evento($tramiteId, $usuarioId, 'firma', 'Cambió el correo de ' . $f['nombre'] . ': se le envió un enlace nuevo de ' . mb_strtolower(self::nombreDocumento($doc)) . '.');
                }
            }
        }
        return $regenerados;
    }

    // ------------------------------------------------------------------ firma

    /**
     * Estampa el sello del firmante en su caja sobre el archivo vigente y registra la firma.
     * @param array{usuario_id:?int,respuestas?:?array} $ctx
     */
    public static function firmar(array $f, array $ctx): array
    {
        $lock = 'pos_firma_' . (int) $f['generado_id'];
        Database::valor('SELECT GET_LOCK(?, 15)', [$lock]);
        try {
            return self::firmarBloqueado((int) $f['id'], $ctx);
        } finally {
            Database::valor('SELECT RELEASE_LOCK(?)', [$lock]);
        }
    }

    private static function firmarBloqueado(int $firmaId, array $ctx): array
    {
        $f = self::una($firmaId);
        if (!$f || $f['estado'] === 'cancelada') {
            throw new RuntimeException('Esta solicitud de firma ya no está vigente.');
        }
        if ($f['estado'] === 'firmada') {
            throw new RuntimeException('Este documento ya tiene tu firma.');
        }
        if (in_array($f['rol'], ['director', 'sinodal'], true) && $f['enviada_en'] === null) {
            throw new RuntimeException('Esta solicitud aún no se ha enviado.');
        }
        $g = PosgradoRepository::generado((int) $f['generado_id']);
        $t = PosgradoRepository::obtener((int) $f['tramite_id']);
        if (!$g || (int) $g['vigente'] !== 1 || !$t || $t['estado'] !== 'abierto') {
            throw new RuntimeException('El documento ya no está vigente.');
        }
        $caja = self::decodificar($f['caja']);
        $respuestas = null;
        if ($f['documento'] === 'visto_bueno') {
            $respuestas = $ctx['respuestas'] ?? null;
            foreach (array_keys(Catalogo::preguntasVistoBueno()) as $k) {
                if (!in_array($respuestas[$k] ?? '', ['si', 'no'], true)) {
                    throw new RuntimeException('Contesta Sí o No a las tres preguntas.');
                }
            }
            if (!FirmaUaemex::fechaValida((string) ($respuestas['vigencia'] ?? ''))) {
                throw new RuntimeException('Escribe la fecha de vigencia de tu firma electrónica UAEMéx.');
            }
            $respuestas = array_intersect_key($respuestas, array_flip(['concluida', 'entregada', 'periodo', 'vigencia']));
        }
        $origen = Expediente::absoluta((string) $g['ruta']);
        if (!is_file($origen) || empty($caja['sellos'])) {
            throw new RuntimeException('No se encontró el documento.');
        }
        $relativa = Expediente::nuevaRuta((int) $t['id'], (string) $f['documento']);
        $destino = Expediente::absoluta($relativa);
        $temporales = [];
        $actual = $origen;
        if ($respuestas !== null && !empty($caja['marcas'])) {
            $tmp = $destino . '.m.pdf';
            self::marcar($actual, $tmp, $caja['marcas'], $respuestas);
            $temporales[] = $tmp;
            $actual = $tmp;
        }
        $ahora = date('Y-m-d H:i:s');
        [$cabecera, $lineas] = self::textoSello($f, $t, $ahora);
        foreach (array_values($caja['sellos']) as $n => $s) {
            $salida = $n === count($caja['sellos']) - 1 ? $destino : $destino . '.' . $n . '.pdf';
            SelloPdf::estampar($actual, $salida, (int) $s['p'], (float) $s['x'], (float) $s['y'], $cabecera, $lineas);
            if ($salida !== $destino) {
                $temporales[] = $salida;
            }
            $actual = $salida;
        }
        foreach ($temporales as $tmp) {
            @unlink($tmp);
        }
        $hash = (string) hash_file('sha256', $destino);
        PosgradoRepository::actualizarArchivoGenerado((int) $g['id'], $relativa, $hash);
        Database::ejecutar(
            "UPDATE pos_firmas SET estado = 'firmada', firmada_en = ?, firmante_id = ?, ip = ?, agente = ?, hash_firmado = ?, respuestas = ?,
                    vence = IF(documento = 'visto_bueno', GREATEST(COALESCE(vence, ?), ?), vence) WHERE id = ?",
            [$ahora, $ctx['usuario_id'] ?? null, mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45), mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $hash,
                $respuestas !== null ? json_encode($respuestas) : null, $ahora, self::venceVistoBueno($t), (int) $f['id']]
        );
        $nombreDoc = self::nombreDocumento((string) $f['documento']);
        $accion = $f['documento'] === 'acuse' && $f['rol'] === 'sinodal' ? 'acusó de recibido' : 'firmó';
        PosgradoRepository::evento((int) $t['id'], (int) ($ctx['usuario_id'] ?? 0) ?: (int) $t['alumno_id'], 'firma',
            $nombreDoc . ': ' . $accion . ' ' . $f['nombre'] . ' (' . mb_strtolower(self::etiquetaRol($f)) . ')' . ($ctx['usuario_id'] ?? null ? ' con su sesión' : ' desde su enlace personal') . '.');
        Audit::record('posgrado::firma', 'correcto', 'Firma ' . (int) $f['id'] . ' · ' . $f['documento'] . ' · ' . $f['rol'] . ' · ' . $t['folio'] . ' · ' . substr($hash, 0, 16));

        if ($respuestas !== null) {
            self::actualizarCuenta((string) $f['email'], (string) $respuestas['vigencia'], 'Visto bueno ' . $t['folio']);
            if (array_filter(array_intersect_key($respuestas, Catalogo::preguntasVistoBueno()), static fn($v): bool => $v === 'no')) {
                PosgradoRepository::evento((int) $t['id'], (int) $t['alumno_id'], 'atencion', 'Visto bueno: ' . $f['nombre'] . ' contestó «No» a alguna pregunta; Apoyo a Grado revisará la propuesta.');
            }
        }
        self::alCompletar((int) $t['id'], (string) $f['documento']);
        return self::una((int) $f['id']) ?? $f;
    }

    /** Un sinodal que firmó el visto bueno conserva su enlace hasta el examen (para corregir su vigencia). */
    private static function venceVistoBueno(array $t): string
    {
        $fecha = (string) ($t['datos']['sinodo']['fecha'] ?? '');
        return FirmaUaemex::fechaValida($fecha) ? $fecha . ' 23:59:59' : date('Y-m-d H:i:s');
    }

    /** Cabecera y renglones del sello. @return array{0:string,1:string[]} */
    private static function textoSello(array $f, array $t, string $ahora): array
    {
        $cuando = 'Firmado el ' . date('d/m/Y H:i', strtotime($ahora)) . ' · ' . $t['folio'];
        return match ((string) $f['rol']) {
            'coordinacion' => ['FIRMA ELECTRÓNICA · COORD. DE ESTUDIOS AVANZADOS', [mb_strtoupper(Configuracion::firmante()), Configuracion::cargo(), $cuando]],
            'director' => ['FIRMA ELECTRÓNICA · DIRECTOR(A) DE TESIS', [mb_strtoupper((string) $f['nombre']), $cuando]],
            'egresado' => ['FIRMA ELECTRÓNICA · INTERESADO(A)', [mb_strtoupper((string) $f['nombre']), $cuando]],
            default => $f['documento'] === 'acuse'
                ? ['FIRMA ELECTRÓNICA · ACUSE DE RECIBIDO', [mb_strtoupper((string) $f['nombre']), 'Recibido el ' . date('d/m/Y H:i', strtotime($ahora)) . ' · ' . $t['folio']]]
                : ['FIRMA ELECTRÓNICA · VISTO BUENO DEL SÍNODO', [mb_strtoupper((string) $f['nombre']), $cuando]],
        };
    }

    /** Marca con una X las respuestas Sí/No del integrante en su renglón del visto bueno. */
    private static function marcar(string $origen, string $salida, array $marcas, array $respuestas): void
    {
        $lib = dirname(APP_RAIZ) . '/lib';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $lib . '/fpdf/font/');
        }
        require_once $lib . '/fpdf/fpdf.php';
        require_once $lib . '/fpdi/autoload.php';
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->SetAutoPageBreak(false);
        $total = $pdf->setSourceFile($origen);
        $claves = array_keys(Catalogo::preguntasVistoBueno());
        for ($i = 0; $i < $total; $i++) {
            $tpl = $pdf->importPage($i + 1);
            $dim = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
            $pdf->useTemplate($tpl);
            foreach ($marcas as $m) {
                if ((int) $m['p'] !== $i) {
                    continue;
                }
                $pdf->SetFont('Helvetica', 'B', 12);
                $pdf->SetTextColor(44, 82, 52);
                foreach ($claves as $k => $clave) {
                    $x = ($respuestas[$clave] ?? '') === 'si' ? ($m['si'][$k] ?? null) : ($m['no'][$k] ?? null);
                    if ($x === null) {
                        continue;
                    }
                    $pdf->SetXY((float) $x - 4, (float) $m['y'] - 3);
                    $pdf->Cell(8, 6, 'X', 0, 0, 'C');
                }
            }
        }
        $pdf->Output('F', $salida);
    }

    /** Si existe una cuenta activa con ese correo, su vigencia de la firma UAEMéx queda como la declarada. */
    private static function actualizarCuenta(string $email, string $vigencia, string $origen): void
    {
        try {
            $cuenta = AccessControl::internal(static fn() => FirmaUaemex::cuentaDe($email));
            if ($cuenta !== null && $cuenta['fise_vigencia'] !== $vigencia) {
                FirmaUaemex::guardar($cuenta['id'], $vigencia, $origen, true);
            }
        } catch (Throwable $e) {
            error_log('Posgrado: no se actualizó la vigencia de la cuenta: ' . $e->getMessage());
        }
    }

    /**
     * Corrige la vigencia declarada en un visto bueno ya firmado (sin volver a firmar). El renglón del PDF no cambia:
     * la vigencia vive en el registro de la firma y la plataforma la reevalúa contra la fecha del examen.
     */
    public static function corregirVigencia(array $f, string $vigencia, ?int $usuarioId): void
    {
        if ($f['documento'] !== 'visto_bueno' || $f['estado'] !== 'firmada') {
            throw new RuntimeException('Primero firma el visto bueno.');
        }
        if (!FirmaUaemex::fechaValida($vigencia)) {
            throw new RuntimeException('Escribe una fecha válida.');
        }
        $r = self::decodificar($f['respuestas']);
        $anterior = (string) ($r['vigencia'] ?? '');
        $r['vigencia'] = $vigencia;
        Database::ejecutar('UPDATE pos_firmas SET respuestas = ? WHERE id = ?', [json_encode($r), (int) $f['id']]);
        $t = PosgradoRepository::obtener((int) $f['tramite_id']);
        PosgradoRepository::evento((int) $f['tramite_id'], $usuarioId ?: (int) ($t['alumno_id'] ?? 0), 'firma',
            $f['nombre'] . ' actualizó la vigencia de su firma electrónica UAEMéx: ' . FirmaUaemex::fechaLarga($vigencia) . '.');
        Audit::record('posgrado::fise_vigencia', 'correcto', 'Firma ' . (int) $f['id'] . ' · ' . ($anterior ?: '—') . ' → ' . $vigencia);
        self::actualizarCuenta((string) $f['email'], $vigencia, 'Visto bueno (corrección)');
    }

    /** Al quedar un documento firmado por todos: bitácora, aviso al egresado y lo que sigue (acuse del sínodo). */
    private static function alCompletar(int $tramiteId, string $doc): void
    {
        $estado = self::estado($tramiteId);
        $t = PosgradoRepository::obtener($tramiteId);
        if (!$t) {
            return;
        }
        if ($doc === 'invitaciones' || $doc === 'acuse') {
            self::enviarAcuses($t, $estado);
        }
        if (empty($estado[$doc]['completo'])) {
            return;
        }
        $nombre = self::nombreDocumento($doc);
        PosgradoRepository::evento($tramiteId, (int) $t['alumno_id'], 'firma', $nombre . ': firmado por todos.');
        $texto = match ($doc) {
            'voto' => 'Tu director firmó tu voto aprobatorio: el requisito quedó cumplido.',
            'datos_interesado' => '',
            'solicitud', 'visto_bueno' => 'Ya firmaron todos ' . mb_strtolower($nombre) . '.',
            'acuse' => 'Todo tu sínodo acusó de recibido su invitación.',
            default => 'La Coordinación de Estudios Avanzados firmó «' . $nombre . '». Ya puedes consultarlo en tu trámite.',
        };
        if ($texto !== '') {
            Aviso::egresado($t, $nombre . ' firmado', $texto . ' Trámite ' . $t['folio'] . '.', in_array($doc, ['voto', 'cotejo', 'acuse'], true));
        }
    }

    /** Cuando la Coordinación firmó las invitaciones y el acuse, sale a cada integrante su invitación con enlace de acuse. */
    private static function enviarAcuses(array $t, array $estado): void
    {
        if (empty($estado['invitaciones']['completo']) || !self::rolCompleto($estado, 'acuse', 'coordinacion')) {
            return;
        }
        $enviados = [];
        foreach ($estado['acuse']['firmas'] ?? [] as $f) {
            if ($f['rol'] === 'sinodal' && $f['estado'] === 'pendiente' && $f['enviada_en'] === null && self::enviar((int) $f['id'])) {
                $enviados[] = $f['nombre'];
            }
        }
        if ($enviados) {
            PosgradoRepository::evento((int) $t['id'], (int) $t['alumno_id'], 'firma', 'Se envió a cada integrante del sínodo su invitación con el enlace para acusar de recibido.');
            Aviso::egresado($t, 'Tu sínodo recibió sus invitaciones', 'La Coordinación firmó tus oficios de invitación y cada integrante de tu sínodo los recibió por correo para acusar de recibido en línea. Entrégales tu trabajo final. Trámite ' . $t['folio'] . '.');
        }
    }

    /** Página del oficio de invitación de un integrante (solo esa hoja), ya firmada por la Coordinación. */
    public static function paginaInvitacion(array $f): ?string
    {
        if ($f['rol'] !== 'sinodal' || $f['documento'] !== 'acuse') {
            return null;
        }
        $fila = Database::una(
            "SELECT g.ruta, f.caja FROM pos_generados g JOIN pos_firmas f ON f.generado_id = g.id AND f.rol = 'coordinacion' AND f.estado = 'firmada'
              WHERE g.tramite_id = ? AND g.clave = 'invitaciones' AND g.vigente = 1 LIMIT 1",
            [(int) $f['tramite_id']]
        );
        $pagina = $fila ? (self::decodificar($fila['caja'])['paginas'][(int) $f['orden']] ?? null) : null;
        $ruta = $fila ? Expediente::absoluta((string) $fila['ruta']) : '';
        if ($pagina === null || !is_file($ruta)) {
            return null;
        }
        $lib = dirname(APP_RAIZ) . '/lib';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $lib . '/fpdf/font/');
        }
        require_once $lib . '/fpdf/fpdf.php';
        require_once $lib . '/fpdi/autoload.php';
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->setSourceFile($ruta);
        $tpl = $pdf->importPage((int) $pagina + 1);
        $dim = $pdf->getTemplateSize($tpl);
        $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
        $pdf->useTemplate($tpl);
        return $pdf->Output('S');
    }

    /** Texto de una línea para mostrar dónde va la espera: «Esperando la firma de X (enviada el …)». */
    public static function espera(array $f): string
    {
        $env = $f['enviada_en'] ? ' (enviada el ' . date('d/m/Y', strtotime((string) $f['enviada_en'])) . ')' : '';
        return $f['rol'] === 'coordinacion' ? 'En firma de la Coordinación de Estudios Avanzados' : 'Esperando la firma de ' . $f['nombre'] . $env;
    }

    /** Fecha del examen contra la que se evalúa la vigencia: la programada o, si aún no, la propuesta. */
    public static function fechaExamen(array $t, array $hitos): string
    {
        return (string) ($hitos['programacion']['datos']['fecha'] ?? $t['datos']['sinodo']['fecha'] ?? '');
    }

    /** Resumen legible de la regla: «Firma UAEMéx de X vence el …: debe renovarla». @return string[] */
    public static function avisosVigencia(array $estado, string $fechaExamen): array
    {
        if (!FirmaUaemex::fechaValida($fechaExamen)) {
            return [];
        }
        return array_map(static fn(array $v): string => 'Firma UAEMéx de ' . $v['nombre'] . ' vence el ' . FirmaUaemex::fechaLarga($v['vigencia'])
            . ': debe renovarla (debe estar vigente al menos hasta el ' . FirmaUaemex::fechaLarga(FirmaUaemex::masUnMes($fechaExamen)) . ').', self::vigenciasInsuficientes($estado, $fechaExamen));
    }

    /** ¿Flujo::fechaLarga disponible para las vistas? (atajo) */
    public static function fecha(string $iso): string
    {
        return Flujo::fechaLarga($iso);
    }
}
