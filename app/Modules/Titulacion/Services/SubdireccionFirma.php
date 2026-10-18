<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Configuracion;
use App\Modules\Titulacion\Flujo;
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

    // ----------------------------------------------------------------- qué se firma

    /** @return array<string,string> tipos de documento que firma la Subdirección: formatos (por clave de formato) y oficios */
    public static function formatosFirmables(): array
    {
        static $memo = null;
        if ($memo !== null) {
            return $memo;
        }
        $salida = [];
        foreach (Catalogo::formatos() as $clave => $def) {
            if (($def['tipo'] ?? '') === 'docx' || !empty($def['interno'])) {
                continue;
            }
            $r = Configuracion::resolverPdf($clave);
            if ($r !== null && self::cajaFormato($r['spec']) !== null) {
                $salida[$clave] = ['nombre' => $def['nombre']];
            }
        }
        foreach (Oficios::TIPOS as $clave => $nombre) {
            $salida[$clave] = ['nombre' => $nombre];
        }
        return $memo = $salida;
    }

    /** Última caja del nombre impreso del Subdirector: es la del bloque de firma. */
    private static function cajaFormato(array $spec): ?array
    {
        $cajas = $spec['subdirector'] ?? [];
        return $cajas ? end($cajas) : null;
    }

    /** Tipo de firma de Subdirección que requiere un documento del catálogo, o null si no la requiere. */
    public static function tipoDeDocumento(array $def): ?string
    {
        if (!empty($def['oficio']) && isset(Oficios::TIPOS[$def['clave']])) {
            return $def['clave'];
        }
        if (!empty($def['generado']) && !empty($def['formato']) && isset(self::formatosFirmables()[$def['formato']]) && !isset(Oficios::TIPOS[$def['formato']])) {
            return (string) $def['formato'];
        }
        return null;
    }

    /** Caja de firma {p,x,y,w} (puntos) del bloque de la Subdirección en un formato. */
    public static function cajaDeFormato(string $formato): ?array
    {
        $r = Configuracion::resolverPdf($formato);
        return $r ? self::cajaFormato($r['spec']) : null;
    }

    // ----------------------------------------------------------------- cola

    /** Pone un documento ya generado en la cola de la Subdirección (sustituye una solicitud pendiente anterior del mismo tipo). */
    public static function solicitar(array $tramite, int $documentoId, string $tipo, string $descripcion, ?array $caja): void
    {
        $tid = (int) $tramite['id'];
        Database::ejecutar("UPDATE tit_firmas_subdireccion SET estado = 'cancelado' WHERE tramite_id = ? AND formato = ? AND estado = 'pendiente'", [$tid, $tipo]);
        Database::ejecutar(
            "INSERT INTO tit_firmas_subdireccion (tramite_id, tipo, documento_id, formato, descripcion, caja, creado_en) VALUES (?, 'documento', ?, ?, ?, ?, ?)",
            [$tid, $documentoId, $tipo, $descripcion . ' · ' . $tramite['folio'], $caja ? json_encode($caja) : null, date('Y-m-d H:i:s')]
        );
        TitulacionRepository::evento($tid, Auth::id(), 'firma', 'En espera de la firma de la Subdirección: ' . $descripcion, true);
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
        $doc = $s['documento_id'] ? TitulacionRepository::documento((int) $s['documento_id']) : null;
        $origen = $doc && $doc['estado'] !== 'reemplazado' && !empty($doc['ruta']) ? FirmaElectronica::almacen() . '/' . $doc['ruta'] : null;
        $t = TitulacionRepository::obtener((int) $s['tramite_id']);
        $caja = $s['caja'] ? json_decode((string) $s['caja'], true) : null;
        if (!$doc || !$t || !$origen || !is_file($origen) || !is_array($caja)) {
            Database::ejecutar("UPDATE tit_firmas_subdireccion SET estado = 'cancelado' WHERE id = ?", [$solicitudId]);
            throw new RuntimeException('El documento ya no está vigente.');
        }
        $relativa = 'titulacion/' . (int) $t['id'] . '/subdireccion/' . $s['formato'] . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $destino = FirmaElectronica::almacen() . '/' . $relativa;
        self::estampar($origen, $destino, $caja, $capacidad, (string) $t['folio']);
        $hash = hash_file('sha256', $destino) ?: '';
        $ahora = date('Y-m-d H:i:s');
        $st = Database::pdo()->prepare(
            "UPDATE tit_firmas_subdireccion SET estado = 'firmado', firmado_en = ?, capacidad = ?, firmante_id = ?, piloto_id = ?, ruta_firmado = ?, hash_firmado = ?
             WHERE id = ? AND estado = 'pendiente'"
        );
        $st->execute([$ahora, $capacidad, $usuarioId, $pilotoId, $relativa, $hash, $solicitudId]);
        if ($st->rowCount() === 0) {
            @unlink($destino);
            return;
        }
        Database::ejecutar('UPDATE tit_documentos SET subdir_en = ?, subdir_capacidad = ?, subdir_ruta = ? WHERE id = ?', [$ahora, $capacidad, $relativa, (int) $doc['id']]);
        $autor = $usuarioId ?? (int) Database::valor('SELECT creado_por FROM tit_piloto WHERE id = ?', [(int) $pilotoId]);
        TitulacionRepository::evento((int) $t['id'], $autor, 'firma', 'Firmado por la Subdirección: ' . $s['descripcion'], true);
        Audit::record('titulacion::subdireccion', 'firma', $capacidad . ' · ' . $s['formato'] . ' · ' . $t['folio'] . ' · ' . substr($hash, 0, 12));
        self::sincronizar((int) $t['id']);
    }

    /** Recalcula etapa y estado del trámite tras una firma. */
    private static function sincronizar(int $id): void
    {
        $t = TitulacionRepository::obtener($id);
        if (!$t || $t['estado'] === 'cancelado') {
            return;
        }
        $flujo = Flujo::evaluar($t, TitulacionRepository::documentosVigentes($id), TitulacionRepository::hitos($id));
        $etapas = $flujo['etapas'];
        $actual = $flujo['actual'] ?? ($etapas ? $etapas[array_key_last($etapas)]['clave'] : 'datos');
        $resultado = $flujo['completo'] ? (TitulacionRepository::hitos($id)['resultado']['datos']['resultado'] ?? null) : null;
        TitulacionRepository::actualizarAvance($id, $actual, $flujo['completo'] ? 'concluido' : 'abierto', $resultado);
    }

    /** Sello visible sobre el nombre impreso: nombre y cargo del Subdirector, folio y huella; leyenda solo si no firmó él. */
    private static function estampar(string $origenAbs, string $salidaAbs, array $caja, string $capacidad, string $folio): void
    {
        $mm = 25.4 / 72;
        $huella = substr(hash_file('sha256', $origenAbs) ?: '', 0, 8);
        SelloPdf::estampar(
            $origenAbs, $salidaAbs, (int) ($caja['p'] ?? 0),
            ((float) $caja['x'] + (float) $caja['w'] / 2) * $mm, (float) $caja['y'] * $mm - 1.5,
            'FIRMA ELECTRÓNICA · SUBDIRECCIÓN ACADÉMICA',
            [mb_strtoupper(Formatos::subdirector()), 'Firmado el ' . date('d/m/Y H:i') . ' · ' . $folio . ' ' . $huella],
            self::LEYENDAS[$capacidad] ?? ''
        );
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
