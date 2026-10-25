<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\App;
use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;

/**
 * Verificación pública de los documentos que genera la plataforma. Cada PDF lleva, junto al ID del proceso, un QR
 * con un código propio que lleva a fingenieria.mx/verificar/{código}; ahí se muestran los datos del documento, sus
 * firmas y si sigue vigente. El código se registra cuando el PDF se guarda en el expediente: un PDF que no se
 * guardó (vista previa) no se puede verificar.
 */
final class Verificacion
{
    /** sha256 del PDF generado => [código, folio, contenido], mientras se guarda en el expediente. */
    private static array $generados = [];

    public static function nuevoCodigo(): string
    {
        $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $c = '';
        foreach (str_split(random_bytes(16)) as $b) {
            $c .= $alfabeto[ord($b) % 32];
        }
        return $c;
    }

    public static function url(string $codigo): string
    {
        return rtrim((string) App::config('app.url_publica', 'https://fingenieria.mx'), '/') . '/verificar/' . $codigo;
    }

    /** Hoja avisa que generó un PDF con su código y el contenido impreso. */
    public static function generado(string $bytes, string $codigo, string $folio, array $contenido): void
    {
        self::$generados[hash('sha256', $bytes)] = [$codigo, $folio, $contenido];
    }

    /**
     * El PDF generado ($bytes, antes de sellos) quedó guardado en $ruta (ya sellado o no): se registra su código.
     * Se llama donde la plataforma escribe en el expediente un PDF que ella misma generó.
     */
    public static function guardado(string $bytes, string $ruta, ?int $revisionId = null, ?int $asignacionId = null, ?string $clave = null): void
    {
        $g = self::$generados[hash('sha256', $bytes)] ?? null;
        if ($g === null) {
            return;
        }
        [$codigo, $folio, $contenido] = $g;
        $tid = Database::valor('SELECT id FROM tit_tramites WHERE folio = ?', [strtok($folio, ' ')]);
        if (!$tid) {
            return;
        }
        Database::ejecutar(
            'INSERT INTO tit_verificaciones (codigo, tramite_id, ruta, revision_id, revision_asignacion_id, clave, contenido) VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE ruta = VALUES(ruta), contenido = VALUES(contenido)',
            [$codigo, (int) $tid, $ruta, $revisionId, $asignacionId, $clave, json_encode($contenido, JSON_UNESCAPED_UNICODE)]
        );
    }

    /** El documento quedó registrado en el expediente. */
    public static function documento(?string $ruta, int $docId): void
    {
        if ($ruta !== null && $ruta !== '') {
            Database::ejecutar('UPDATE tit_verificaciones SET documento_id = ? WHERE ruta = ? AND documento_id IS NULL', [$docId, $ruta]);
        }
    }

    // ------------------------------------------------------------------ consulta pública

    /**
     * @return ?array{nombre:string,folio:string,emitido:string,vigente:bool,estado:string,aviso:?string,firmas:array,contenido:array}
     */
    public static function consultar(string $codigo): ?array
    {
        $codigo = strtoupper(trim($codigo));
        $v = preg_match('/^[A-Z2-9]{16}$/', $codigo) ? Database::una('SELECT * FROM tit_verificaciones WHERE codigo = ?', [$codigo]) : null;
        $t = $v ? Database::una('SELECT id, folio, estado, modalidad, variante, datos FROM tit_tramites WHERE id = ?', [(int) $v['tramite_id']]) : null;
        if (!$t) {
            return null;
        }
        $datos = json_decode((string) $t['datos'], true);
        $alumno = (string) ($datos['datos']['nombre'] ?? '');
        $contenido = json_decode((string) $v['contenido'], true) ?: [];
        $r = [
            'nombre' => self::tituloContenido($contenido) ?? 'Documento', 'folio' => (string) $t['folio'], 'emitido' => (string) $v['creado_en'],
            'vigente' => true, 'estado' => 'Vigente', 'aviso' => null, 'firmas' => [], 'contenido' => $contenido,
        ];
        $doc = $v['documento_id'] ? TitulacionRepository::documento((int) $v['documento_id']) : null;
        if ($doc) {
            $r['nombre'] = self::nombreDocumento($doc, $t) ?? $r['nombre'];
            $cajas = json_decode((string) ($doc['cajas'] ?? ''), true);
            if ($doc['origen'] === 'alumno' && (!is_array($cajas) || isset($cajas['pasante']))) {
                $r['firmas'][] = ['quien' => $alumno, 'como' => 'Pasante', 'en' => (string) $doc['creado_en']];
            }
            self::firmasDocumento((int) $doc['id'], $r['firmas']);
            if ($doc['estado'] === 'reemplazado') {
                $nuevo = Database::valor('SELECT MIN(creado_en) FROM tit_documentos WHERE tramite_id = ? AND etapa = ? AND clave = ? AND id > ?',
                    [(int) $doc['tramite_id'], $doc['etapa'], $doc['clave'], (int) $doc['id']]);
                $r['vigente'] = false;
                $r['estado'] = 'No vigente';
                $r['aviso'] = 'Este documento fue sustituido por una versión posterior' . ($nuevo ? ' el ' . self::fechaHora((string) $nuevo) : '') . '. Solicite la versión vigente.';
            } elseif ($doc['estado'] === 'rechazado') {
                $r['vigente'] = false;
                $r['estado'] = 'No vigente';
                $r['aviso'] = 'Este documento fue devuelto con observaciones' . ($doc['revisado_en'] ? ' el ' . self::fechaHora((string) $doc['revisado_en']) : '') . ' y no tiene validez.';
            } elseif ($doc['estado'] === 'no_aplica') {
                $r['vigente'] = false;
                $r['estado'] = 'Anulado';
                $r['aviso'] = 'Este documento fue anulado y no tiene validez.';
            } elseif ($doc['estado'] === 'en_revision') {
                $r['estado'] = 'En trámite';
                $r['aviso'] = 'Documento registrado en el expediente, pendiente de validación por el Departamento de Evaluación Profesional y Grado.';
            }
        } elseif ($v['revision_asignacion_id']) {
            $a = Database::una('SELECT a.*, m.nombre FROM tit_revision_asignaciones a JOIN tit_comision_miembros m ON m.id = a.miembro_id WHERE a.id = ?', [(int) $v['revision_asignacion_id']]);
            $r['nombre'] = 'Revisión de protocolo del comité';
            if ($a && $a['firmada_en']) {
                $r['firmas'][] = ['quien' => (string) $a['nombre'], 'como' => 'Revisor(a) del protocolo', 'en' => (string) $a['firmada_en']];
            }
            if (!$a || $a['estado'] === 'cancelada') {
                $r['vigente'] = false;
                $r['estado'] = 'No vigente';
                $r['aviso'] = 'Esta revisión fue cancelada.';
            }
        } elseif ($v['revision_id']) {
            $rv = Database::una('SELECT r.*, u.nombre AS dictaminador FROM tit_revisiones r LEFT JOIN usuarios u ON u.id = r.dictaminado_por WHERE r.id = ?', [(int) $v['revision_id']]);
            $r['nombre'] = 'Dictamen de revisión de protocolo' . ($rv ? ' · ' . ucfirst(Mensajes::ordinal((int) $rv['numero'])) . ' vuelta' : '');
            if ($rv && $rv['dictaminado_en']) {
                $r['firmas'][] = ['quien' => (string) $rv['dictaminador'], 'como' => 'Responsable del comité de revisión', 'en' => (string) $rv['dictaminado_en']];
            }
            foreach (Database::todas("SELECT rol_firma, estado, firmado_en, firmante_id FROM tit_firmas_subdireccion WHERE revision_id = ? AND tipo = 'dictamen' AND estado <> 'cancelado' ORDER BY id", [(int) $v['revision_id']]) as $f) {
                $r['firmas'][] = self::firmaAutoridad($f);
            }
            if (!$rv || $rv['estado'] === 'cancelada') {
                $r['vigente'] = false;
                $r['estado'] = 'No vigente';
                $r['aviso'] = 'Esta vuelta de revisión fue cancelada.';
            }
        } elseif ($v['clave']) {
            // Formato llenado que el pasante descargó para entregarlo impreso: vale la última descarga.
            $r['nombre'] = Catalogo::formatos()[$v['clave']]['nombre'] ?? $r['nombre'];
            $r['aviso'] = 'Formato llenado por la plataforma con los datos del expediente. Las firmas autógrafas que lleve el impreso no las registra la plataforma.';
            $nuevo = Database::valor('SELECT MIN(creado_en) FROM tit_verificaciones WHERE tramite_id = ? AND clave = ? AND id > ?', [(int) $v['tramite_id'], $v['clave'], (int) $v['id']]);
            if ($nuevo) {
                $r['vigente'] = false;
                $r['estado'] = 'No vigente';
                $r['aviso'] = 'Este formato fue sustituido por una versión generada el ' . self::fechaHora((string) $nuevo) . '. Solicite la versión vigente.';
            }
        } else {
            $r['estado'] = 'Sin registrar';
            $r['vigente'] = false;
            $r['aviso'] = 'El documento no quedó registrado en el expediente.';
        }
        if ($r['vigente'] && $t['estado'] === 'cancelado') {
            $r['vigente'] = false;
            $r['estado'] = 'No vigente';
            $r['aviso'] = 'El proceso de titulación al que pertenece este documento fue cancelado.';
        }
        return $r;
    }

    /** Firmas registradas en la plataforma para el documento (asesor, revisores, Coordinación, Jefatura, Subdirección, acuses). */
    private static function firmasDocumento(int $docId, array &$firmas): void
    {
        foreach (Database::todas("SELECT nombre, rol, estado, firmada_en FROM tit_firmas_externas WHERE documento_id = ? AND estado <> 'cancelada' ORDER BY id", [$docId]) as $f) {
            $firmas[] = ['quien' => (string) $f['nombre'], 'como' => FirmasExternas::etiquetaRol((string) $f['rol']), 'en' => $f['estado'] === 'firmada' ? (string) $f['firmada_en'] : null];
        }
        foreach (Database::todas("SELECT coord_nombre, firmada_en FROM tit_asignaciones_jurado WHERE documento_id = ? AND firmada_en IS NOT NULL", [$docId]) as $a) {
            $firmas[] = ['quien' => (string) $a['coord_nombre'], 'como' => 'Coordinación de Docencia', 'en' => (string) $a['firmada_en']];
        }
        foreach (Database::todas("SELECT rol_firma, estado, firmado_en, firmante_id FROM tit_firmas_subdireccion WHERE documento_id = ? AND estado <> 'cancelado' ORDER BY id", [$docId]) as $f) {
            $firmas[] = self::firmaAutoridad($f);
        }
        foreach (Database::todas("SELECT nombre, acuse_en FROM tit_sinodales WHERE oficio_doc_id = ? AND estado = 'acuse'", [$docId]) as $s) {
            $firmas[] = ['quien' => (string) $s['nombre'], 'como' => 'Acuse de recibido', 'en' => (string) $s['acuse_en']];
        }
    }

    private static function firmaAutoridad(array $f): array
    {
        $como = ['subdirector' => 'Subdirección Académica', 'director' => 'Dirección', 'jefe_titulacion' => 'Departamento de Evaluación Profesional y Grado'][$f['rol_firma']] ?? 'Autoridad';
        $quien = $f['firmante_id'] ? (string) Database::valor('SELECT nombre FROM usuarios WHERE id = ?', [(int) $f['firmante_id']]) : '';
        return ['quien' => $quien, 'como' => $como, 'en' => $f['estado'] === 'firmado' ? (string) $f['firmado_en'] : null];
    }

    private static function nombreDocumento(array $doc, array $t): ?string
    {
        $clave = (string) $doc['clave'];
        if (str_starts_with($clave, 'constancia_') && ($tipo = Database::valor('SELECT tipo FROM tit_constancias WHERE documento_id = ?', [(int) $doc['id']]))) {
            return Constancias::titulo((string) $tipo);
        }
        if (str_starts_with($clave, 'oficio_sinodal_')) {
            return Oficios::TIPOS['oficio_sinodal'] ?? null;
        }
        if (isset(Oficios::TIPOS[$clave])) {
            return Oficios::TIPOS[$clave];
        }
        foreach (Catalogo::etapas((string) $t['modalidad'], $t['variante'] ?: null) as $e) {
            if (($e['clave'] ?? '') !== $doc['etapa']) {
                continue;
            }
            foreach (array_merge($e['documentos'] ?? [], [$e['personal']['entrega'] ?? []], $e['personal']['extras'] ?? []) as $d) {
                if (($d['clave'] ?? '') === $clave) {
                    return trim((string) preg_replace('/\s*\([^)]*\)\s*$/u', '', (string) $d['nombre']));
                }
            }
        }
        return null;
    }

    private static function tituloContenido(array $contenido): ?string
    {
        foreach ($contenido as $c) {
            if (($c[0] ?? '') === 't') {
                return mb_convert_case(mb_strtolower((string) $c[1]), MB_CASE_TITLE);
            }
        }
        return null;
    }

    public static function fechaHora(string $f): string
    {
        $t = strtotime($f);
        return $t ? date('d/m/Y H:i', $t) . ' h' : $f;
    }
}
