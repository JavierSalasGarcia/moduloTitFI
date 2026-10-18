<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Audit;
use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;
use RuntimeException;

/** Firma electrónica de asesores desde el enlace personal que reciben por correo. */
final class FirmasExternas
{
    private const ROLES = ['asesor' => 'Asesor', 'coasesor' => 'Coasesor'];

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

    /** Pide la firma de asesor (y coasesor, si lo hay) sobre el protocolo ya firmado por el pasante. */
    public static function solicitarProtocolo(array $tramite, int $docId, array $cajas): void
    {
        $r = $tramite['datos']['protocolo'] ?? [];
        Database::ejecutar("UPDATE tit_firmas_externas SET estado = 'cancelada' WHERE tramite_id = ? AND estado = 'pendiente'", [(int) $tramite['id']]);
        foreach (['asesor', 'coasesor'] as $rol) {
            $email = strtolower(trim((string) ($r[$rol . '_correo'] ?? '')));
            $nombre = trim((string) ($r[$rol] ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || ($rol === 'coasesor' && ($nombre === '' || mb_strtolower($nombre) === 'no aplica'))) {
                continue;
            }
            $token = self::token();
            Database::ejecutar(
                'INSERT INTO tit_firmas_externas (tramite_id, documento_id, rol, nombre, email, token_hash, vence, caja) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [(int) $tramite['id'], $docId, $rol, mb_substr($nombre, 0, 160), $email, hash('sha256', $token), self::vence(), json_encode($cajas['asesor'])]
            );
            self::correo($tramite, $nombre, $email, $token, false);
        }
        Database::ejecutar('UPDATE tit_documentos SET pendiente_firmas = 1 WHERE id = ?', [$docId]);
        TitulacionRepository::evento((int) $tramite['id'], (int) $tramite['alumno_id'], 'firma', 'Protocolo firmado por el pasante; se pidió la firma de su asesor por correo.', true);
    }

    private static function correo(array $tramite, string $nombre, string $email, string $token, bool $recordatorio): void
    {
        $alumno = (string) ($tramite['datos']['datos']['nombre'] ?? '');
        $titulo = (string) ($tramite['datos']['protocolo']['titulo'] ?? '');
        Correo::enviar(
            $email, $nombre,
            ($recordatorio ? 'Recordatorio · ' : '') . 'Firma de protocolo · ' . $tramite['folio'],
            ($recordatorio ? 'Recordatorio: ' : '') . "$alumno, pasante de la Facultad de Ingeniería, te pide firmar como asesor su protocolo «{$titulo}».\n\nQué debes hacer: abre el enlace, lee el protocolo y pulsa «Firmar». No necesitas descargar ni subir nada. Para firmar se requiere una cuenta verificada con este mismo correo ($email); si aún no tienes cuenta, el enlace te lleva a crearla.\n\nEl enlace es personal; no lo compartas.",
            'Revisar y firmar el protocolo', self::url($token), $recordatorio ? 'recordatorio_firma' : 'firma_asesor', (int) $tramite['id']
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
            self::correo($t, (string) $f['nombre'], (string) $f['email'], $token, true);
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
        // El coasesor firma debajo del asesor, a su misma altura: se corre a la izquierda para no encimarse.
        $centro = $f['rol'] === 'coasesor' ? $x - 70 * $mm : $x * $mm;
        SelloPdf::estampar(
            $origen, FirmaElectronica::almacen() . '/' . $relativa, (int) $caja['p'], $centro, (float) $caja['y'] * $mm - 1.0,
            'FIRMA ELECTRÓNICA · ' . mb_strtoupper(self::ROLES[$f['rol']]),
            [mb_strtoupper((string) $f['nombre']), 'Firmado el ' . date('d/m/Y H:i') . ' · ' . $t['folio']]
        );
        $hash = hash_file('sha256', FirmaElectronica::almacen() . '/' . $relativa) ?: '';
        Database::ejecutar('UPDATE tit_documentos SET ruta = ? WHERE id = ?', [$relativa, (int) $doc['id']]);
        Database::ejecutar("UPDATE tit_firmas_externas SET estado = 'firmada', firmada_en = ?, firmante_id = ?, hash_firmado = ? WHERE id = ?", [date('Y-m-d H:i:s'), (int) $usuario['id'], $hash, (int) $f['id']]);
        TitulacionRepository::evento((int) $t['id'], (int) $usuario['id'], 'firma', 'Protocolo firmado por ' . strtolower(self::ROLES[$f['rol']]) . ': ' . $f['nombre'], true);
        Audit::record('titulacion::firma_externa', 'correcto', $f['rol'] . ' · ' . $t['folio'] . ' · ' . substr($hash, 0, 12));
        $quedan = (int) Database::valor("SELECT COUNT(*) FROM tit_firmas_externas WHERE documento_id = ? AND estado = 'pendiente'", [(int) $doc['id']]);
        if ($quedan === 0) {
            Database::ejecutar('UPDATE tit_documentos SET pendiente_firmas = 0 WHERE id = ?', [(int) $doc['id']]);
            RevisionProtocolo::nuevaRonda($t, (int) $doc['id']);
        }
    }
}
