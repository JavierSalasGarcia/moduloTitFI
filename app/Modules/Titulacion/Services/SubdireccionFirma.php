<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Configuracion;
use App\Modules\Titulacion\TitulacionRepository;
use RuntimeException;
use Throwable;

/**
 * Firma de la Subdirección Académica sobre los formatos que llevan su nombre con espacio para firma.
 * Puede firmar el Subdirector, su asistente (por delegación) o el piloto automático. El sello lleva
 * siempre el nombre y el cargo del Subdirector; cuando firma otra capacidad se añade una leyenda
 * discreta y la bitácora interna conserva quién firmó realmente, cuándo y la huella del documento.
 */
final class SubdireccionFirma
{
    public const LEYENDAS = ['personal' => '', 'delegada' => 'Por delegación', 'automatica' => 'Firma automática programada'];

    // ----------------------------------------------------------------- quién es quién

    public static function correoSubdirector(): string
    {
        return strtolower(trim((string) \ajuste('titulacion_subdirector_email', '')));
    }

    public static function esSubdirector(): bool
    {
        $c = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        return $c !== '' && $c === self::correoSubdirector();
    }

    public static function esAsistente(): bool
    {
        $c = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        if ($c === '') {
            return false;
        }
        try {
            return (bool) Database::valor('SELECT 1 FROM tit_asistentes WHERE email = ? AND activo = 1', [$c]);
        } catch (Throwable) {
            return false;
        }
    }

    public static function puedeFirmar(): bool
    {
        return self::esSubdirector() || self::esAsistente();
    }

    // ----------------------------------------------------------------- formatos que se firman

    /** @return array<string,array> formatos cuyo diseño incluye el bloque de firma del Subdirector */
    public static function formatosFirmables(): array
    {
        $salida = [];
        foreach (Catalogo::formatos() as $clave => $def) {
            if (($def['tipo'] ?? '') === 'docx') {
                continue;
            }
            $r = Configuracion::resolverPdf($clave);
            if ($r !== null && self::cajaFirma($r['spec']) !== null) {
                $salida[$clave] = $def;
            }
        }
        return $salida;
    }

    /** Última caja del nombre impreso del Subdirector: es la del bloque de firma. */
    private static function cajaFirma(array $spec): ?array
    {
        $cajas = $spec['subdirector'] ?? [];
        return $cajas ? end($cajas) : null;
    }

    public static function firmable(string $formato): bool
    {
        return isset(self::formatosFirmables()[$formato]);
    }

    // ----------------------------------------------------------------- cola

    /** Registra (una sola vez) la solicitud de firma de un formato del trámite. @return array la fila vigente */
    public static function solicitar(array $tramite, string $formato): array
    {
        $tid = (int) $tramite['id'];
        $fila = Database::una("SELECT * FROM tit_firmas_subdireccion WHERE tramite_id = ? AND formato = ? AND estado <> 'cancelado' ORDER BY id DESC LIMIT 1", [$tid, $formato]);
        if ($fila) {
            return $fila;
        }
        $id = Database::ejecutar(
            "INSERT INTO tit_firmas_subdireccion (tramite_id, tipo, formato, descripcion, creado_en) VALUES (?, 'documento', ?, ?, ?)",
            [$tid, $formato, Catalogo::formatos()[$formato]['nombre'] . ' · ' . $tramite['folio'], date('Y-m-d H:i:s')]
        );
        TitulacionRepository::evento($tid, Auth::id(), 'firma', 'Formato en espera de la firma de la Subdirección: ' . Catalogo::formatos()[$formato]['nombre'], true);
        return Database::una('SELECT * FROM tit_firmas_subdireccion WHERE id = ?', [$id]) ?? [];
    }

    public static function firmado(int $tramiteId, string $formato): ?array
    {
        return Database::una("SELECT * FROM tit_firmas_subdireccion WHERE tramite_id = ? AND formato = ? AND estado = 'firmado' ORDER BY id DESC LIMIT 1", [$tramiteId, $formato]);
    }

    public static function pendientes(): array
    {
        return Database::todas(
            "SELECT f.*, t.folio, t.modalidad, u.nombre AS alumno_nombre
             FROM tit_firmas_subdireccion f JOIN tit_tramites t ON t.id = f.tramite_id JOIN usuarios u ON u.id = t.alumno_id
             WHERE f.estado = 'pendiente' ORDER BY f.creado_en"
        );
    }

    public static function bitacora(int $limite = 100): array
    {
        return Database::todas(
            "SELECT f.*, t.folio, u.nombre AS firmante_nombre, u.email AS firmante_email
             FROM tit_firmas_subdireccion f JOIN tit_tramites t ON t.id = f.tramite_id LEFT JOIN usuarios u ON u.id = f.firmante_id
             WHERE f.estado = 'firmado' ORDER BY f.firmado_en DESC LIMIT " . (int) $limite
        );
    }

    // ----------------------------------------------------------------- firma

    /** @param 'personal'|'delegada'|'automatica' $capacidad */
    public static function firmar(int $solicitudId, string $capacidad, ?int $usuarioId, ?int $pilotoId = null): void
    {
        $s = Database::una("SELECT * FROM tit_firmas_subdireccion WHERE id = ? AND estado = 'pendiente'", [$solicitudId]);
        if (!$s) {
            throw new RuntimeException('La solicitud ya no está pendiente.');
        }
        $t = TitulacionRepository::obtener((int) $s['tramite_id']);
        if (!$t) {
            throw new RuntimeException('El trámite ya no existe.');
        }
        $formato = (string) $s['formato'];
        $r = Configuracion::resolverPdf($formato);
        $caja = $r ? self::cajaFirma($r['spec']) : null;
        if (!$r || !$caja) {
            throw new RuntimeException('El formato no tiene bloque de firma de la Subdirección.');
        }
        [$bytes] = Formatos::generar($formato, $t);
        $dir = FirmaElectronica::almacen() . '/titulacion/' . (int) $t['id'] . '/subdireccion';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $origen = $dir . '/_origen_' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($origen, $bytes);
        $relativa = 'titulacion/' . (int) $t['id'] . '/subdireccion/' . $formato . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $destino = FirmaElectronica::almacen() . '/' . $relativa;
        try {
            self::estampar($origen, $destino, $caja, $capacidad, (string) $t['folio']);
        } finally {
            @unlink($origen);
        }
        $hash = hash_file('sha256', $destino) ?: '';
        $st = Database::pdo()->prepare(
            "UPDATE tit_firmas_subdireccion SET estado = 'firmado', firmado_en = ?, capacidad = ?, firmante_id = ?, piloto_id = ?, ruta_firmado = ?, hash_firmado = ?
             WHERE id = ? AND estado = 'pendiente'"
        );
        $st->execute([date('Y-m-d H:i:s'), $capacidad, $usuarioId, $pilotoId, $relativa, $hash, $solicitudId]);
        if ($st->rowCount() === 0) {
            @unlink($destino);
            return;
        }
        TitulacionRepository::evento((int) $t['id'], (int) ($usuarioId ?? 0), 'firma', 'Firmado por la Subdirección: ' . Catalogo::formatos()[$formato]['nombre'], true);
        Audit::record('titulacion::subdireccion', 'firma', $capacidad . ' · ' . $formato . ' · ' . $t['folio'] . ' · ' . substr($hash, 0, 12));
    }

    /** Sello visible sobre el nombre impreso: nombre y cargo del Subdirector, folio y huella; leyenda solo si no firmó él. */
    private static function estampar(string $origenAbs, string $salidaAbs, array $caja, string $capacidad, string $folio): void
    {
        $lib = dirname(APP_RAIZ) . '/lib';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $lib . '/fpdf/font/');
        }
        require_once $lib . '/fpdf/fpdf.php';
        require_once $lib . '/fpdi/autoload.php';
        $huella = substr(hash_file('sha256', $origenAbs) ?: '', 0, 8);
        $nombre = mb_strtoupper(Formatos::subdirector());
        $leyenda = self::LEYENDAS[$capacidad] ?? '';
        $mm = 25.4 / 72;
        $pdf = new \setasign\Fpdi\Fpdi();
        $pdf->SetAutoPageBreak(false);
        $total = $pdf->setSourceFile($origenAbs);
        for ($i = 1; $i <= $total; $i++) {
            $tpl = $pdf->importPage($i);
            $dim = $pdf->getTemplateSize($tpl);
            $pdf->AddPage($dim['orientation'], [$dim['width'], $dim['height']]);
            $pdf->useTemplate($tpl);
            if ($i - 1 !== (int) ($caja['p'] ?? 0)) {
                continue;
            }
            $w = 62.0;
            $cx = ((float) $caja['x'] + (float) $caja['w'] / 2) * $mm;
            $x = max(8.0, min($dim['width'] - $w - 8.0, $cx - $w / 2));
            $y = max(8.0, (float) $caja['y'] * $mm - 17.0);
            $pdf->SetDrawColor(44, 82, 52);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($x, $y, $w, 15.0);
            $pdf->SetTextColor(44, 82, 52);
            $pdf->SetFont('Helvetica', 'B', 6.5);
            $pdf->SetXY($x, $y + 1.2);
            $pdf->Cell($w, 3, self::l1('FIRMA ELECTRÓNICA · SUBDIRECCIÓN ACADÉMICA'), 0, 2, 'C');
            $pdf->SetFont('Helvetica', '', 6.5);
            $pdf->SetTextColor(25, 25, 25);
            $pdf->Cell($w, 3, self::l1($nombre), 0, 2, 'C');
            $pdf->Cell($w, 3, self::l1('Firmado el ' . date('d/m/Y H:i') . ' · ' . $folio . ' ' . $huella), 0, 2, 'C');
            if ($leyenda !== '') {
                $pdf->SetFont('Helvetica', 'I', 6);
                $pdf->Cell($w, 3, self::l1($leyenda), 0, 2, 'C');
            }
        }
        $pdf->Output('F', $salidaAbs);
    }

    private static function l1(string $s): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?: $s;
    }

    // ----------------------------------------------------------------- piloto automático

    public static function pilotoVigente(): ?array
    {
        return Database::una('SELECT * FROM tit_piloto WHERE activo = 1 AND inicio <= ? AND fin >= ? ORDER BY id DESC LIMIT 1', [$ahora = date('Y-m-d H:i:s'), $ahora]);
    }

    public static function pilotoUltimo(): ?array
    {
        return Database::una('SELECT * FROM tit_piloto ORDER BY id DESC LIMIT 1');
    }

    /** Firma lo que el piloto vigente cubra y ya cumplió el retraso fijo. @return int firmas aplicadas */
    public static function procesarPiloto(): int
    {
        $p = self::pilotoVigente();
        if (!$p) {
            return 0;
        }
        $formatos = (array) json_decode((string) $p['formatos'], true);
        if (!$formatos) {
            return 0;
        }
        $marcas = implode(',', array_fill(0, count($formatos), '?'));
        $filas = Database::todas(
            "SELECT id FROM tit_firmas_subdireccion WHERE estado = 'pendiente' AND formato IN ($marcas)
               AND creado_en <= ? AND creado_en >= ? ORDER BY creado_en LIMIT 25",
            [...array_values($formatos), date('Y-m-d H:i:s', time() - (int) $p['retraso_min'] * 60), $p['inicio']]
        );
        $hechas = 0;
        foreach ($filas as $f) {
            try {
                self::firmar((int) $f['id'], 'automatica', null, (int) $p['id']);
                $hechas++;
            } catch (Throwable $e) {
                error_log('Titulación: piloto no pudo firmar #' . $f['id'] . ': ' . $e->getMessage());
            }
        }
        return $hechas;
    }
}
