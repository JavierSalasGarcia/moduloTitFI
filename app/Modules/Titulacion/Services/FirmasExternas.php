<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;
use RuntimeException;

/**
 * Firma electrónica de asesores, coasesores y revisores desde el enlace personal que reciben por correo (o desde
 * «Mis pendientes» con sesión): protocolo, solicitudes con Vo. Bo. del asesor y voto aprobatorio.
 */
final class FirmasExternas
{
    private const ROLES = ['asesor' => 'Asesor', 'coasesor' => 'Coasesor', 'revisor' => 'Revisor'];

    /** Qué se firma, según la clave del documento del expediente. */
    public const DOCUMENTOS = [
        'protocolo_firmado' => ['el protocolo', 'Firma de protocolo'],
        'revision_firmada' => ['la solicitud de revisión del trabajo escrito', 'Vo. Bo. de la solicitud de revisión'],
        'sustentacion_firmada' => ['la solicitud de sustentación del trabajo escrito', 'Vo. Bo. de la solicitud de sustentación'],
        'voto_aprobatorio' => ['el voto aprobatorio del trabajo escrito', 'Firma del voto aprobatorio'],
    ];

    public static function queSeFirma(string $clave): array
    {
        return self::DOCUMENTOS[$clave] ?? ['el documento', 'Firma de documento'];
    }

    public static function etiquetaRol(string $rol): string
    {
        return self::ROLES[preg_replace('/_\d+$/', '', $rol)] ?? 'Firmante';
    }

    private static function token(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public static function url(string $token): string
    {
        return Correo::enlace('titulacion/firma/t/' . $token);
    }

    private static function vence(): string
    {
        return date('Y-m-d', strtotime('+' . max(1, (int) \ajuste('titulacion_token_gracia', '14')) . ' days'));
    }

    /** Asesor y, si lo hay, coasesor del protocolo, como firmantes. @return array<int,array<string,mixed>> */
    public static function asesores(array $tramite, bool $conCoasesor = true): array
    {
        $r = $tramite['datos']['protocolo'] ?? [];
        $salida = [];
        foreach ($conCoasesor ? ['asesor', 'coasesor'] : ['asesor'] as $rol) {
            $email = strtolower(trim((string) ($r[$rol . '_correo'] ?? '')));
            $nombre = trim((string) ($r[$rol] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $nombre === '' || mb_strtolower($nombre) === 'no aplica') {
                continue;
            }
            $grado = mb_substr(trim((string) ($r[$rol . '_grado'] ?? '')), 0, 30);
            $salida[] = ['rol' => $rol, 'nombre' => $nombre, 'grado' => $grado !== '' ? $grado : null,
                'genero' => in_array(($r[$rol . '_genero'] ?? ''), ['masculino', 'femenino'], true) ? $r[$rol . '_genero'] : null, 'email' => $email];
        }
        return $salida;
    }

    /**
     * Pide por correo la firma de cada persona sobre un documento ya generado.
     * @param array<int,array{rol:string,nombre:string,grado?:?string,genero?:?string,email:string,caja:?array}> $firmantes
     */
    public static function solicitar(array $tramite, int $docId, array $firmantes): void
    {
        Database::ejecutar("UPDATE tit_firmas_externas SET estado = 'cancelada' WHERE documento_id = ? AND estado = 'pendiente'", [$docId]);
        $doc = TitulacionRepository::documento($docId);
        foreach ($firmantes as $f) {
            if (!filter_var($f['email'], FILTER_VALIDATE_EMAIL) || empty($f['caja'])) {
                continue;
            }
            $token = self::token();
            Database::ejecutar(
                'INSERT INTO tit_firmas_externas (tramite_id, documento_id, rol, nombre, grado, genero, email, token_hash, vence, caja) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $tramite['id'], $docId, $f['rol'], mb_substr($f['nombre'], 0, 160), $f['grado'] ?? null, $f['genero'] ?? null, strtolower($f['email']), hash('sha256', $token), self::vence(), json_encode($f['caja'])]
            );
            self::correo($tramite, $f, strtolower($f['email']), $token, false, (string) $f['rol'], (string) ($doc['clave'] ?? ''));
        }
        Database::ejecutar('UPDATE tit_documentos SET pendiente_firmas = 1 WHERE id = ?', [$docId]);
        TitulacionRepository::evento((int) $tramite['id'], (int) $tramite['alumno_id'], 'firma', 'Se pidió por correo la firma de ' . self::queSeFirma((string) ($doc['clave'] ?? ''))[0] . ' a: ' . implode(', ', array_map(static fn(array $f): string => $f['nombre'], $firmantes)) . '.', true);
    }

    /** Pide la firma de asesor (y coasesor, si lo hay) sobre el protocolo ya firmado por el pasante. */
    public static function solicitarProtocolo(array $tramite, int $docId, array $cajas): void
    {
        $firmantes = array_map(static fn(array $f): array => $f + ['caja' => $cajas['asesor'] ?? null], self::asesores($tramite));
        self::solicitar($tramite, $docId, $firmantes);
    }

    private static function correo(array $tramite, array $persona, string $email, string $token, bool $recordatorio, string $rol, string $docClave): void
    {
        $alumno = (string) ($tramite['datos']['datos']['nombre'] ?? '');
        $titulo = (string) ($tramite['titulo'] ?: ($tramite['datos']['protocolo']['titulo'] ?? ''));
        [$que, $asunto] = self::queSeFirma($docClave);
        $comoRol = \App\Core\Persona::f(\App\Core\Persona::completar($persona, $email), mb_strtolower(self::etiquetaRol($rol)), mb_strtolower(self::etiquetaRol($rol)) . 'a');
        Correo::enviar(
            $email, ['nombre' => $persona['nombre'], 'grado' => $persona['grado'] ?? null, 'genero' => $persona['genero'] ?? null],
            ($recordatorio ? 'Recordatorio · ' : '') . $asunto . ' · ' . $tramite['folio'],
            ($recordatorio ? 'Recordatorio: ' : '') . "Se le pide firmar, como $comoRol, $que de $alumno (trabajo «{$titulo}»).\n\nQué debe hacer: abra el enlace, lea el documento y pulse «Firmar». No necesita descargar ni subir nada. Para firmar se requiere una cuenta verificada con este mismo correo ($email); si aún no tiene cuenta, el enlace le lleva a crearla. También lo encontrará en Traza FI, en «Mis pendientes».\n\nEl enlace es personal; no lo comparta.",
            'Revisar y firmar', self::url($token), $recordatorio ? 'recordatorio_firma' : 'firma_externa', (int) $tramite['id']
        );
    }

    public static function pendientesDe(int $docId): array
    {
        return Database::todas('SELECT * FROM tit_firmas_externas WHERE documento_id = ? AND estado <> \'cancelada\' ORDER BY id', [$docId]);
    }

    public static function porToken(string $token): ?array
    {
        if (!preg_match('/^[A-Za-z0-9_-]{40,50}$/D', $token)) {
            return null;
        }
        $f = Database::una('SELECT * FROM tit_firmas_externas WHERE token_hash = ?', [hash('sha256', $token)]);
        return $f && $f['estado'] !== 'cancelada' && $f['vence'] >= date('Y-m-d') ? $f : null;
    }

    /** Nuevo enlace para quien no ha firmado (el anterior deja de servir). */
    public static function reenviar(int $docId): int
    {
        $n = 0;
        foreach (self::pendientesDe($docId) as $f) {
            if ($f['estado'] !== 'pendiente') {
                continue;
            }
            $t = TitulacionRepository::obtener((int) $f['tramite_id']);
            if (!$t) {
                continue;
            }
            $token = self::token();
            Database::ejecutar('UPDATE tit_firmas_externas SET token_hash = ?, vence = ? WHERE id = ?', [hash('sha256', $token), self::vence(), (int) $f['id']]);
            $doc = TitulacionRepository::documento((int) $f['documento_id']);
            self::correo($t, ['nombre' => $f['nombre'], 'grado' => $f['grado'], 'genero' => $f['genero']], (string) $f['email'], $token, true, (string) $f['rol'], (string) ($doc['clave'] ?? ''));
            $n++;
        }
        return $n;
    }

    /** Estampa el sello del asesor en el PDF del expediente; si ya firmaron todos, lo envía a la comisión. */
    public static function firmar(array $f, array $usuario): void
    {
        if ($f['estado'] !== 'pendiente') {
            throw new RuntimeException('Esta firma ya se registró.');
        }
        if (strtolower((string) $usuario['email']) !== strtolower((string) $f['email'])) {
            throw new RuntimeException('Tu sesión no corresponde al correo al que se envió la solicitud.');
        }
        $doc = TitulacionRepository::documento((int) $f['documento_id']);
        $t = TitulacionRepository::obtener((int) $f['tramite_id']);
        if (!$doc || !$t || $doc['estado'] === 'reemplazado' || empty($doc['ruta'])) {
            throw new RuntimeException('El protocolo ya no está vigente.');
        }
        $origen = FirmaElectronica::almacen() . '/' . $doc['ruta'];
        $caja = json_decode((string) $f['caja'], true) ?: null;
        if (!is_file($origen) || !is_array($caja)) {
            throw new RuntimeException('No se encontró el documento.');
        }
        $relativa = 'titulacion/' . (int) $t['id'] . '/protocolo_' . bin2hex(random_bytes(8)) . '.pdf';
        $mm = 25.4 / 72;
        $x = (float) $caja['x'] + (float) $caja['w'] / 2;
        // En el protocolo el coasesor comparte la caja del asesor: se corre a la izquierda para no encimarse.
        $centro = $f['rol'] === 'coasesor' && $doc['clave'] === 'protocolo_firmado' ? $x - 70 * $mm : $x * $mm;
        SelloPdf::estampar(
            $origen, FirmaElectronica::almacen() . '/' . $relativa, (int) $caja['p'], $centro, (float) $caja['y'] * $mm - 1.0,
            'FIRMA ELECTRÓNICA · ' . mb_strtoupper(self::etiquetaRol((string) $f['rol'])),
            [mb_strtoupper(\App\Core\Persona::tratamiento(\App\Core\Persona::completar(['nombre' => $f['nombre'], 'grado' => $f['grado'], 'genero' => $f['genero']], (string) $f['email']))), 'Firmado el ' . date('d/m/Y H:i') . ' · ' . $t['folio']]
        );
        $hash = hash_file('sha256', FirmaElectronica::almacen() . '/' . $relativa) ?: '';
        Database::ejecutar('UPDATE tit_documentos SET ruta = ? WHERE id = ?', [$relativa, (int) $doc['id']]);
        Database::ejecutar("UPDATE tit_firmas_externas SET estado = 'firmada', firmada_en = ?, firmante_id = ?, hash_firmado = ? WHERE id = ?", [date('Y-m-d H:i:s'), (int) $usuario['id'], $hash, (int) $f['id']]);
        TitulacionRepository::evento((int) $t['id'], (int) $usuario['id'], 'firma', ucfirst(self::queSeFirma((string) $doc['clave'])[0]) . ': firmó ' . mb_strtolower(self::etiquetaRol((string) $f['rol'])) . ' ' . $f['nombre'], true);
        Audit::record('titulacion::firma_externa', 'correcto', $f['rol'] . ' · ' . $t['folio'] . ' · ' . substr($hash, 0, 12));
        $quedan = (int) Database::valor("SELECT COUNT(*) FROM tit_firmas_externas WHERE documento_id = ? AND estado = 'pendiente'", [(int) $doc['id']]);
        if ($quedan === 0) {
            Database::ejecutar('UPDATE tit_documentos SET pendiente_firmas = 0 WHERE id = ?', [(int) $doc['id']]);
            if ($doc['clave'] === 'protocolo_firmado') {
                RevisionProtocolo::nuevaRonda($t, (int) $doc['id']);
            } else {
                if ($doc['clave'] === 'voto_aprobatorio') {
                    Constancias::revisoresTrabajo($t, (int) $doc['id'], (int) $usuario['id']);
                }
                TitulacionRepository::evento((int) $t['id'], (int) $usuario['id'], 'firma', 'Ya firmaron todos ' . self::queSeFirma((string) $doc['clave'])[0] . '; pasa a validación del Departamento de Evaluación Profesional.', true);
                SubdireccionFirma::sincronizar((int) $t['id']);
            }
        }
    }
}
