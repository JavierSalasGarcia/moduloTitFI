<?php
declare(strict_types=1);

namespace App\Modules\Oficios\Services;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Cifrado;
use App\Core\Database;
use App\Core\Persona;
use App\Modules\Consejos\Services\Html;
use App\Modules\Titulacion\Services\Autoridades;
use App\Modules\Titulacion\Services\NumeroOficio;
use App\Modules\Titulacion\Services\Verificacion;
use RuntimeException;

/**
 * Oficios de la Subdirección Académica (tablas ofs_*): borrador → en firma → firmado. El número sale del contador único de la
 * Subdirección al enviarlo a firma; el titular firma con su e.firma del SAT en el navegador (la llave nunca llega aquí).
 */
final class OficiosSub
{
    public const SERIE = 'subdireccion';
    public const ESTADOS = ['borrador' => 'Borrador', 'en_firma' => 'En firma', 'firmado' => 'Firmado', 'cancelado' => 'Cancelado'];
    private const AJUSTE_RFC = 'oficios_rfc_titular';

    // ------------------------------------------------------------------ lectura

    public static function lista(string $estado = '', string $q = ''): array
    {
        $donde = ['1 = 1'];
        $p = [];
        if (isset(self::ESTADOS[$estado])) {
            $donde[] = 'o.estado = ?';
            $p[] = $estado;
        }
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $donde[] = '(o.numero_texto LIKE ? OR o.asunto LIKE ? OR o.destinatarios LIKE ?)';
            array_push($p, $like, $like, $like);
        }
        return Database::todas(
            'SELECT o.id, o.estado, o.numero_texto, o.fecha, o.asunto, o.destinatarios, o.firmado_en, o.actualizado_en, u.nombre AS autor
               FROM ofs_oficios o JOIN usuarios u ON u.id = o.creado_por
              WHERE ' . implode(' AND ', $donde) . ' ORDER BY o.actualizado_en DESC, o.id DESC LIMIT 300',
            $p
        );
    }

    public static function obtener(int $id): ?array
    {
        $o = Database::una('SELECT o.*, u.nombre AS autor FROM ofs_oficios o JOIN usuarios u ON u.id = o.creado_por WHERE o.id = ?', [$id]);
        if ($o) {
            $o['dest'] = json_decode((string) $o['destinatarios'], true) ?: [];
        }
        return $o;
    }

    public static function porCodigo(string $codigo): ?array
    {
        return preg_match('/^[A-Z0-9]{8,32}$/', $codigo) ? Database::una("SELECT * FROM ofs_oficios WHERE codigo = ? AND estado = 'firmado'", [$codigo]) : null;
    }

    public static function historial(int $id): array
    {
        return Database::todas('SELECT h.*, u.nombre FROM ofs_historial h LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.oficio_id = ? ORDER BY h.id', [$id]);
    }

    public static function anotar(int $id, string $accion, ?string $nota = null): void
    {
        Database::ejecutar('INSERT INTO ofs_historial (oficio_id, accion, usuario_id, nota) VALUES (?, ?, ?, ?)', [$id, $accion, Auth::id(), $nota !== null ? mb_substr($nota, 0, 500) : null]);
    }

    /** Oficios que esperan la firma del titular (proveedor `por_firmar`). */
    public static function enFirma(): array
    {
        return Database::todas("SELECT id, numero_texto, asunto, destinatarios, enviado_en FROM ofs_oficios WHERE estado = 'en_firma' ORDER BY numero, id");
    }

    // ------------------------------------------------------------------ captura

    /** Limpia el HTML del lienzo: solo párrafos, saltos, negritas, cursivas y tablas. */
    public static function limpiarCuerpo(string $html): string
    {
        $h = Html::limpiar($html);
        $h = preg_replace('~</?u>~i', '', $h) ?? $h;
        return trim($h);
    }

    /**
     * @param array<int,int> $destinatarioIds
     * @return int id del oficio
     */
    public static function guardar(?int $id, string $asunto, string $cuerpoHtml, array $destinatarioIds): int
    {
        $asunto = mb_substr(trim(preg_replace('/\s+/u', ' ', $asunto) ?? ''), 0, 250);
        if ($asunto === '') {
            throw new RuntimeException('Escribe el asunto del oficio.');
        }
        $cuerpo = self::limpiarCuerpo($cuerpoHtml);
        if (trim(Html::texto($cuerpo)) === '') {
            throw new RuntimeException('Escribe el contenido del oficio.');
        }
        $dest = Destinatarios::instantaneas($destinatarioIds);
        if (!$dest) {
            throw new RuntimeException('Elige al menos un destinatario.');
        }
        $json = json_encode($dest, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if ($id === null) {
            $id = Database::ejecutar('INSERT INTO ofs_oficios (asunto, destinatarios, cuerpo_html, creado_por) VALUES (?, ?, ?, ?)', [$asunto, $json, $cuerpo, (int) Auth::id()]);
            self::anotar($id, 'creado');
        } else {
            $o = self::obtener($id);
            if (!$o || !in_array($o['estado'], ['borrador', 'en_firma'], true)) {
                throw new RuntimeException('Este oficio ya no se puede modificar.');
            }
            Database::ejecutar('UPDATE ofs_oficios SET asunto = ?, destinatarios = ?, cuerpo_html = ?, observacion = NULL WHERE id = ?', [$asunto, $json, $cuerpo, $id]);
            // Si ya estaba en firma y lo edita otra persona, vuelve a borrador para que el titular revise lo nuevo.
            // Si lo corrige el propio titular (quien firma), sigue en firma con su mismo número.
            if ($o['estado'] === 'en_firma' && !\App\Modules\Oficios\OficiosAccess::puedeFirmar()) {
                Database::ejecutar("UPDATE ofs_oficios SET estado = 'borrador', pdf_preparado = NULL WHERE id = ?", [$id]);
            } elseif ($o['estado'] === 'en_firma') {
                Database::ejecutar('UPDATE ofs_oficios SET pdf_preparado = NULL WHERE id = ?', [$id]);
            }
            self::anotar($id, 'editado');
        }
        return $id;
    }

    // ------------------------------------------------------------------ flujo

    /** Reserva el número (solo la primera vez) y deja el oficio en firma. */
    public static function enviarAFirma(int $id): array
    {
        $o = self::obtener($id);
        if (!$o || $o['estado'] !== 'borrador') {
            throw new RuntimeException('Solo se envían a firma los oficios en borrador.');
        }
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            if ($o['numero'] === null) {
                $anio = (int) date('Y');
                [$n, $texto] = NumeroOficio::reservar(self::SERIE, $anio);
                Database::ejecutar('UPDATE ofs_oficios SET anio = ?, numero = ?, numero_texto = ? WHERE id = ?', [$anio, $n, $texto, $id]);
            }
            Database::ejecutar(
                "UPDATE ofs_oficios SET estado = 'en_firma', enviado_en = NOW(), fecha = CURDATE(), codigo = COALESCE(codigo, ?), observacion = NULL WHERE id = ?",
                [Verificacion::nuevoCodigo(), $id]
            );
            self::anotar($id, 'enviado');
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
        return self::obtener($id) ?? [];
    }

    public static function devolver(int $id, string $nota): void
    {
        $o = self::obtener($id);
        if (!$o || $o['estado'] !== 'en_firma') {
            throw new RuntimeException('Este oficio no está esperando firma.');
        }
        Database::ejecutar("UPDATE ofs_oficios SET estado = 'borrador', observacion = ?, pdf_preparado = NULL WHERE id = ?", [mb_substr(trim($nota), 0, 500), $id]);
        self::anotar($id, 'devuelto', $nota);
    }

    /** Cancela; si ya tenía número, el número queda registrado como cancelado y no se reutiliza. */
    public static function cancelar(int $id, string $motivo): void
    {
        $o = self::obtener($id);
        if (!$o || !in_array($o['estado'], ['borrador', 'en_firma'], true)) {
            throw new RuntimeException('Este oficio ya no se puede cancelar.');
        }
        Database::ejecutar("UPDATE ofs_oficios SET estado = 'cancelado', motivo_cancelacion = ?, pdf_preparado = NULL WHERE id = ?", [mb_substr(trim($motivo), 0, 255), $id]);
        self::anotar($id, 'cancelado', $motivo);
    }

    // ------------------------------------------------------------------ PDF

    /** Datos del titular para la firma: nombre en mayúsculas y cargo en mayúsculas «DE LA FACULTAD DE INGENIERÍA». */
    public static function firmante(): array
    {
        $t = Autoridades::subdirector() ?? [];
        $nombre = $t ? Persona::tratamiento($t, true) : 'SUBDIRECTOR ACADÉMICO';
        $cargo = $t ? Persona::f($t, 'Subdirector Académico', 'Subdirectora Académica') : 'Subdirector Académico';
        return ['nombre' => $nombre, 'cargo' => [mb_strtoupper($cargo), 'DE LA FACULTAD DE INGENIERÍA']];
    }

    public static function urlVerificacion(string $codigo): string
    {
        return rtrim((string) App::config('app.url_publica', 'https://fingenieria.mx'), '/') . '/oficios/verificar/' . $codigo;
    }

    /** PDF del oficio. $sello: líneas visibles de la e.firma (solo al preparar la firma). */
    public static function pdf(array $o, bool $borrador, ?array $sello = null): string
    {
        $codigo = (string) ($o['codigo'] ?: 'SINCODIGO');
        return OficioPdf::generar([
            'numero_texto' => $o['numero_texto'], 'fecha' => (string) ($o['fecha'] ?: date('Y-m-d')), 'asunto' => $o['asunto'], 'destinatarios' => $o['dest'],
            'cuerpo_html' => $o['cuerpo_html'], 'codigo' => $codigo, 'url' => self::urlVerificacion($codigo), 'borrador' => $borrador,
            'firmante' => self::firmante(), 'sello' => $sello,
        ]);
    }

    // ------------------------------------------------------------------ configuración (RFC del titular y certificados del SAT)

    /** RFC registrado del titular (descifrado); nunca se muestra en pantalla ni en el PDF. */
    public static function rfcTitular(): string
    {
        $v = Database::valor('SELECT valor FROM ajustes WHERE clave = ?', [self::AJUSTE_RFC]);
        if ($v === false || $v === null || $v === '') {
            return '';
        }
        return strtoupper(trim((string) (Cifrado::descifrar((string) $v, 'ajustes.' . self::AJUSTE_RFC) ?? '')));
    }

    public static function guardarRfcTitular(string $rfc): void
    {
        $rfc = strtoupper(trim($rfc));
        if (!preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/u', $rfc)) {
            throw new RuntimeException('El RFC no tiene un formato válido (por ejemplo, ABCD800101XYZ).');
        }
        $cif = Cifrado::cifrar($rfc, 'ajustes.' . self::AJUSTE_RFC);
        Database::ejecutar(
            'INSERT INTO ajustes (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [self::AJUSTE_RFC, $cif, 'RFC del titular de la Subdirección Académica (cifrado): sirve para comprobar que la e.firma con la que se firma es la suya. Nunca se muestra.']
        );
        Audit::record('oficios_config', 'correcto', 'Registra el RFC del titular');
    }

    /** @return string[] certificados PEM del SAT cargados */
    public static function certificadosPem(): array
    {
        return array_column(Database::todas('SELECT pem FROM ofs_confianza ORDER BY id'), 'pem');
    }

    public static function agregarCertificado(string $contenido, string $nombreArchivo): void
    {
        $pem = str_contains($contenido, '-----BEGIN CERTIFICATE-----') ? $contenido : Cms::pem($contenido);
        if (preg_match('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', $pem, $m)) {
            $pem = $m[0] . "\n";
        }
        $x = @openssl_x509_parse($pem, true);
        if (!is_array($x)) {
            throw new RuntimeException('El archivo no es un certificado (.cer, .crt o .pem).');
        }
        $der = base64_decode((string) preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', $pem), true);
        $huella = hash('sha256', (string) $der);
        $nombre = trim((string) ((is_array($x['subject']['CN'] ?? null) ? $x['subject']['CN'][0] : ($x['subject']['CN'] ?? '')) ?: ($x['subject']['O'] ?? $nombreArchivo)));
        Database::ejecutar('INSERT IGNORE INTO ofs_confianza (nombre, pem, huella, subido_por) VALUES (?, ?, ?, ?)', [mb_substr($nombre, 0, 190), $pem, $huella, Auth::id()]);
        Audit::record('oficios_config', 'correcto', 'Carga un certificado de la cadena del SAT');
    }
}
