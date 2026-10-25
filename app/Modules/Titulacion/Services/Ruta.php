<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Modules\Titulacion\RevisionRepository;

/**
 * Ruta del trámite: los pasos del proceso (sitio de Titulación y reglas del módulo) con su estado —hecho,
 * actual o pendiente— y los documentos de cada paso para descargarlos (auditoría).
 */
final class Ruta
{
    /**
     * @param array $flujo Flujo::evaluar() con 'hitos'; $docs documentos vigentes
     * @return array<int,array{etapa:string,titulo:string,estado:string,nota:?string,archivos:array<int,array{nombre:string,url:string}>}>
     */
    public static function pasos(array $t, array $flujo, array $docs): array
    {
        $tid = (int) $t['id'];
        $base = 'titulacion/ruta/' . $tid;
        $pasos = [];
        $archivo = static function (?array $fila, string $nombre) use ($base): array {
            return $fila && !empty($fila['ruta']) ? [['nombre' => $nombre, 'url' => $base . '/documento/' . (int) $fila['id']]] : [];
        };
        $add = static function (array $e, string $titulo, bool $hecho, array $archivos = [], ?string $nota = null) use (&$pasos): void {
            $pasos[] = ['etapa' => $e['titulo'], 'titulo' => $titulo, 'hecho' => $hecho || $e['situacion'] === 'completa', 'nota' => $nota, 'archivos' => $archivos];
        };
        foreach ($flujo['etapas'] as $e) {
            if (!empty($e['campos'])) {
                $add($e, $e['clave'] === 'datos' ? 'Captura de tus datos' : 'Captura: ' . $e['titulo'], (bool) $e['captura_ok']);
            }
            if ($e['tipo'] === 'confirmacion') {
                $add($e, $e['confirmar'] ?? $e['titulo'], !empty($e['confirmada']));
            }
            foreach ($e['docs_estado'] ?? [] as $clave => $info) {
                $d = $info['def'];
                $fila = $info['fila'];
                $nota = match ($info['estado']) {
                    'firmas' => 'esperando firmas', 'en_revision' => 'en validación', 'rechazado' => 'con observaciones', 'no_aplica' => 'no aplica', default => null,
                };
                $add($e, $d['nombre'], (bool) $info['ok'], $archivo($fila, $d['nombre']), $nota);
                if ($clave === 'protocolo_firmado') {
                    foreach (array_reverse(RevisionRepository::rondasDeTramite($tid)) as $r) {
                        if ($r['estado'] === 'cancelada') {
                            continue;
                        }
                        $add($e, 'Revisión del comité · ' . Mensajes::ordinal((int) $r['numero']) . ' vuelta', $r['estado'] === 'dictaminada',
                            $r['dictamen_ruta'] ? [['nombre' => 'Dictamen firmado', 'url' => $base . '/dictamen/' . (int) $r['id']]] : [],
                            $r['estado'] === 'dictaminada' ? ($r['dictamen'] === 'aprobado' ? 'aprobado' : 'con observaciones') : 'en curso');
                    }
                }
            }
            if (!empty($e['agenda'])) {
                $h = $e['hito']['datos'] ?? null;
                $add($e, 'Fecha, hora y sínodo solicitados por el pasante', $h !== null, [], $h ? Oficios::fecha((string) $h['fecha']) . ', ' . $h['hora'] . ' h, ' . $h['lugar'] : null);
            }
            if (isset($e['personal'])) {
                $ent = $e['personal']['entrega'] ?? null;
                if ($ent === null) {
                    $add($e, $e['personal']['titulo'], $e['hito'] !== null);
                } else {
                    $fila = $docs[$e['clave'] . '|' . $ent['clave']] ?? null;
                    $nombre = $ent['nombre'];
                    $hecho = (bool) $e['entrega_ok'] && ($e['hito'] !== null || !empty($e['agenda']));
                    $nota = $fila && !$hecho ? 'en firmas' : null;
                    if (!empty($ent['asignacion']) && $fila === null && $e['hito'] !== null) {
                        $nota = 'esperando al Coordinador de Docencia';
                    }
                    $add($e, $nombre, $hecho, $archivo($fila, $nombre), $nota);
                    foreach ($e['personal']['extras'] ?? [] as $x) {
                        $fx = $docs[$e['clave'] . '|' . $x['clave']] ?? null;
                        $add($e, $x['nombre'], !empty($fx['subdir_en']), $archivo($fx, $x['nombre']));
                    }
                    if (!empty($ent['asignacion'])) {
                        $asig = Jurado::vigente($tid, $e['clave']);
                        if ($asig && $asig['estado'] === 'firmada') {
                            $sins = Jurado::sinodales((int) $asig['id']);
                            $arch = [];
                            foreach ($sins as $s) {
                                $of = $docs[$e['clave'] . '|oficio_sinodal_' . $s['orden']] ?? null;
                                if ($of) {
                                    $arch[] = ['nombre' => 'Oficio · ' . $s['nombre'], 'url' => $base . '/documento/' . (int) $of['id']];
                                }
                            }
                            $acuses = count(array_filter($sins, static fn(array $s): bool => $s['estado'] === 'acuse'));
                            $add($e, 'Oficios a los sinodales y acuses de recibido', $sins !== [] && $acuses === count($sins), $arch, $acuses . ' de ' . count($sins) . ' acuses');
                        }
                    }
                }
            }
        }
        // Documentos del expediente que no son de un paso fijo: informe de estatus, oficio de prórroga, constancias…
        $usados = [];
        foreach ($pasos as $p) {
            foreach ($p['archivos'] as $a) {
                $usados[$a['url']] = true;
            }
        }
        $constancias = [];
        foreach (Database::todas('SELECT documento_id, tipo, nombre FROM tit_constancias WHERE tramite_id = ?', [$tid]) as $c) {
            $constancias[(int) $c['documento_id']] = Constancias::titulo((string) $c['tipo']) . ': ' . $c['nombre'];
        }
        foreach ($docs as $d) {
            $url = $base . '/documento/' . (int) $d['id'];
            if (empty($d['ruta']) || isset($usados[$url]) || str_starts_with((string) $d['clave'], 'oficio_sinodal_')) {
                continue;
            }
            $nombre = $constancias[(int) $d['id']] ?? (Oficios::TIPOS[$d['clave']] ?? null);
            if ($nombre === null) {
                continue;
            }
            $firmado = $d['origen'] !== 'personal' || !empty($d['subdir_en']);
            $pasos[] = ['etapa' => 'Otros documentos del expediente', 'titulo' => $nombre, 'hecho' => true, 'nota' => $firmado ? null : 'en firma',
                'archivos' => [['nombre' => $nombre, 'url' => $url]]];
        }
        // Estado: lo hecho, el primer paso sin hacer es el actual y el resto queda pendiente (atenuado).
        $actual = false;
        foreach ($pasos as &$p) {
            if ($p['hecho']) {
                $p['estado'] = 'hecho';
            } elseif (!$actual && $t['estado'] === 'abierto') {
                $p['estado'] = 'actual';
                $actual = true;
            } else {
                $p['estado'] = 'pendiente';
            }
        }
        unset($p);
        return $pasos;
    }
}
