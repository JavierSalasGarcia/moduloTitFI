<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
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
        return Autoridades::subdirector()['email'] ?? '';
    }

    public static function esSubdirector(): bool
    {
        $c = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        return $c !== '' && $c === self::correoSubdirector();
    }

    public static function esDirector(): bool
    {
        $c = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        $d = Autoridades::director()['email'] ?? '';
        return $c !== '' && $c === $d;
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

    public static function esJefeTitulacion(): bool
    {
        $c = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        $j = Autoridades::jefeTitulacion()['email'] ?? '';
        return $c !== '' && $c === $j;
    }

    public static function puedeFirmar(): bool
    {
        return self::esSubdirector() || self::esAsistente() || self::esDirector() || self::esJefeTitulacion();
    }

    /** Cargos cuyas firmas ve quien tiene la sesión (el asistente firma por el Subdirector). @return string[] */
    private static function rolesSesion(): array
    {
        $roles = [];
        if (self::esSubdirector() || self::esAsistente()) {
            $roles[] = 'subdirector';
        }
        if (self::esDirector()) {
            $roles[] = 'director';
        }
        if (self::esJefeTitulacion()) {
            $roles[] = 'jefe_titulacion';
        }
        return $roles;
    }

    /** Capacidad con que firma la sesión una solicitud: el asistente firma por delegación al Subdirector. */
    public static function capacidadPara(array $solicitud): string
    {
        return ($solicitud['rol_firma'] ?? 'subdirector') === 'subdirector' && !self::esSubdirector() ? 'delegada' : 'personal';
    }

    // ----------------------------------------------------------------- qué se firma

    /** @return array<string,array{nombre:string}> tipos de documento que firma la Subdirección: formatos y oficios */
    public static function formatosFirmables(): array
    {
        $salida = [];
        foreach (FormatosOficiales::CON_SUBDIRECCION as $clave) {
            $salida[$clave] = ['nombre' => Catalogo::formatos()[$clave]['nombre'] ?? $clave];
        }
        foreach (Oficios::TIPOS as $clave => $nombre) {
            if (!in_array($clave, Oficios::DIRECCION, true)) {
                $salida[$clave] = ['nombre' => $nombre];
            }
        }
        return $salida;
    }

    /** Tipo de firma de Subdirección que requiere un documento del catálogo, o null si no la requiere. */
    public static function tipoDeDocumento(array $def): ?string
    {
        if (!empty($def['oficio']) && isset(Oficios::TIPOS[$def['clave']])) {
            return $def['clave'];
        }
        if (!empty($def['asignacion'])) {
            return null;
        }
        if (!empty($def['generado']) && !empty($def['formato']) && isset(self::formatosFirmables()[$def['formato']]) && !isset(Oficios::TIPOS[$def['formato']])) {
            return (string) $def['formato'];
        }
        return null;
    }

    // ----------------------------------------------------------------- cola

    /** Tipos con un documento por persona en el mismo trámite: no se sustituyen entre sí. */
    private const VARIOS = ['oficio_sinodal', 'constancia'];

    public const CARGOS = ['subdirector' => 'la Subdirección Académica', 'director' => 'la Dirección', 'jefe_titulacion' => 'la Jefatura de Evaluación Profesional'];

    /**
     * Pone un documento ya generado en la cola de firmas (sustituye una solicitud pendiente anterior del mismo tipo y cargo).
     * Sin $rol firma la Dirección sus oficios y la Subdirección el resto.
     */
    public static function solicitar(array $tramite, int $documentoId, string $tipo, string $descripcion, ?array $caja, ?string $rol = null): void
    {
        $tid = (int) $tramite['id'];
        $rol ??= in_array($tipo, Oficios::DIRECCION, true) ? 'director' : 'subdirector';
        if (!in_array($tipo, self::VARIOS, true)) {
            Database::ejecutar("UPDATE tit_firmas_subdireccion SET estado = 'cancelado' WHERE tramite_id = ? AND formato = ? AND rol_firma = ? AND estado = 'pendiente'", [$tid, $tipo, $rol]);
        }
        Database::ejecutar(
            "INSERT INTO tit_firmas_subdireccion (tramite_id, tipo, documento_id, formato, rol_firma, descripcion, caja, creado_en) VALUES (?, 'documento', ?, ?, ?, ?, ?, ?)",
            [$tid, $documentoId, $tipo, $rol, $descripcion . ' · ' . $tramite['folio'], $caja ? json_encode($caja) : null, date('Y-m-d H:i:s')]
        );
        if ($tipo !== 'constancia') {
            TitulacionRepository::evento($tid, Auth::id() ?: (int) $tramite['alumno_id'], 'firma', 'En espera de la firma de ' . self::CARGOS[$rol] . ': ' . $descripcion, true);
        }
    }

    /** La Subdirección firma después del Vo. Bo.: mientras haya uno pendiente sobre el mismo documento, su solicitud espera. */
    private const SIN_VOBO = "NOT EXISTS (SELECT 1 FROM tit_firmas_subdireccion v WHERE v.documento_id = f.documento_id AND v.rol_firma = 'jefe_titulacion' AND v.estado = 'pendiente' AND v.id <> f.id)";

    /** Lo que le toca firmar a quien tiene la sesión, según su cargo (el asistente ve lo del Subdirector). */
    public static function pendientes(): array
    {
        $roles = self::rolesSesion();
        if (!$roles) {
            return [];
        }
        $marcas = implode(',', array_fill(0, count($roles), '?'));
        return Database::todas(
            "SELECT f.*, t.folio, t.modalidad, u.nombre AS alumno_nombre
             FROM tit_firmas_subdireccion f JOIN tit_tramites t ON t.id = f.tramite_id JOIN usuarios u ON u.id = t.alumno_id
             WHERE f.estado = 'pendiente' AND f.rol_firma IN ($marcas) AND " . self::SIN_VOBO . ' ORDER BY f.creado_en',
            $roles
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
        $s = Database::una("SELECT * FROM tit_firmas_subdireccion f WHERE f.id = ? AND f.estado = 'pendiente' AND " . self::SIN_VOBO, [$solicitudId]);
        if (!$s) {
            throw new RuntimeException('La solicitud ya no está pendiente o aún espera el Vo. Bo.');
        }
        $rol = (string) ($s['rol_firma'] ?? 'subdirector');
        $doc = $s['documento_id'] ? TitulacionRepository::documento((int) $s['documento_id']) : null;
        $origen = $doc && $doc['estado'] !== 'reemplazado' && !empty($doc['ruta']) ? FirmaElectronica::almacen() . '/' . $doc['ruta'] : null;
        $t = TitulacionRepository::obtener((int) $s['tramite_id']);
        $caja = $s['caja'] ? json_decode((string) $s['caja'], true) : null;
        if (!$doc || !$t || !$origen || !is_file($origen) || !is_array($caja)) {
            Database::ejecutar("UPDATE tit_firmas_subdireccion SET estado = 'cancelado' WHERE id = ?", [$solicitudId]);
            throw new RuntimeException('El documento ya no está vigente.');
        }
        $relativa = 'titulacion/' . (int) $t['id'] . '/subdireccion/' . $s['formato'] . ($rol === 'jefe_titulacion' ? '_vobo' : '') . '_' . bin2hex(random_bytes(6)) . '.pdf';
        $destino = FirmaElectronica::almacen() . '/' . $relativa;
        self::estampar($origen, $destino, $caja, $capacidad, (string) $t['folio'], $rol, (string) $s['formato']);
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
        if ($rol === 'jefe_titulacion' && $s['formato'] === 'oficio_aprobacion_tema') {
            // El Vo. Bo. no concluye el documento: la firma de la Subdirección se estampa sobre esta versión.
            Database::ejecutar('UPDATE tit_documentos SET ruta = ? WHERE id = ?', [$relativa, (int) $doc['id']]);
        } else {
            Database::ejecutar('UPDATE tit_documentos SET subdir_en = ?, subdir_capacidad = ?, subdir_ruta = ? WHERE id = ?', [$ahora, $capacidad, $relativa, (int) $doc['id']]);
        }
        $autor = $usuarioId ?? (int) Database::valor('SELECT creado_por FROM tit_piloto WHERE id = ?', [(int) $pilotoId]);
        if ($s['formato'] !== 'constancia') {
            TitulacionRepository::evento((int) $t['id'], $autor, 'firma', 'Firmado por ' . self::CARGOS[$rol] . ': ' . $s['descripcion'], true);
        }
        Audit::record('titulacion::subdireccion', 'firma', $capacidad . ' · ' . $s['formato'] . ' · ' . $t['folio'] . ' · ' . substr($hash, 0, 12));
        self::sincronizar((int) $t['id']);
        if ($s['formato'] === 'oficio_sinodal') {
            Jurado::oficioFirmado((int) $doc['id']);
        }
        if ($s['formato'] === 'constancia') {
            Constancias::firmada((int) $doc['id']);
        }
        if ($s['formato'] === 'oficio_aprobacion_tema' && $rol === 'subdirector') {
            AprobacionTema::oficioFirmado($t);
        }
    }

    /** Recalcula etapa y estado del trámite tras una firma. */
    public static function sincronizar(int $id): void
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

    /** Sello visible sobre el nombre impreso: nombre y cargo de quien firma, folio y huella; leyenda solo si no firmó en persona. */
    private static function estampar(string $origenAbs, string $salidaAbs, array $caja, string $capacidad, string $folio, string $rol, string $formato = ''): void
    {
        $mm = 25.4 / 72;
        $huella = substr(hash_file('sha256', $origenAbs) ?: '', 0, 8);
        $jefe = Autoridades::jefeTitulacion();
        [$etiqueta, $nombre] = match ($rol) {
            'director' => ['FIRMA ELECTRÓNICA · DIRECCIÓN', Oficios::director()],
            'jefe_titulacion' => [$formato === 'oficio_aprobacion_tema' ? 'VO. BO. · EVAL. PROFESIONAL Y GRADO' : 'FIRMA ELECTRÓNICA · EVAL. PROFESIONAL', $jefe ? mb_strtoupper(\App\Core\Persona::tratamiento($jefe)) : 'JEFATURA DE EVALUACIÓN PROFESIONAL'],
            default => ['FIRMA ELECTRÓNICA · SUBDIRECCIÓN ACADÉMICA', mb_strtoupper(Formatos::subdirector())],
        };
        SelloPdf::estampar(
            $origenAbs, $salidaAbs, (int) ($caja['p'] ?? 0),
            ((float) $caja['x'] + (float) $caja['w'] / 2) * $mm, (float) $caja['y'] * $mm - 1.5,
            $etiqueta, [$nombre, 'Firmado el ' . date('d/m/Y H:i') . ' · ' . $folio . ' ' . $huella],
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
            "SELECT f.id FROM tit_firmas_subdireccion f WHERE f.estado = 'pendiente' AND f.rol_firma = 'subdirector' AND f.formato IN ($marcas)
               AND f.creado_en <= ? AND f.creado_en >= ? AND " . self::SIN_VOBO . ' ORDER BY f.creado_en LIMIT 25',
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
