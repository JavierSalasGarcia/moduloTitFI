<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Flujo;
use App\Modules\Titulacion\TitulacionRepository;
use RuntimeException;

/** Une lo capturado en el trámite con el generador de formatos oficiales. */
final class Formatos
{
    /** Etapa cuya captura debe estar completa, además de «datos», para generar el formato. */
    public static function etapaRequerida(string $formato): ?string
    {
        return match ($formato) {
            'aprovechamiento', 'egel_50', 'egel_nacional', 'creditos' => 'solicitud',
            'protocolo' => 'protocolo',
            'revision' => 'revision',
            'sustentacion' => 'sustentacion',
            default => null,
        };
    }

    /** Mensaje de por qué todavía no se puede generar, o null si ya se puede. */
    public static function impedimento(string $formato, array $tramite): ?string
    {
        $def = Catalogo::formatos()[$formato] ?? null;
        if ($def === null) {
            return 'Formato desconocido.';
        }
        $etapas = Catalogo::etapas((string) $tramite['modalidad'], $tramite['variante'] ?: null);
        $claves = array_column($etapas, 'clave');
        $adicionales = Catalogo::formatosAdicionales((string) $tramite['tipo']);
        if (isset($adicionales[$formato])) {
            foreach ($adicionales[$formato] as $campo) {
                $valor = $tramite['datos']['adicional'][$formato][$campo['k']] ?? ($formato === 'art5' ? ($tramite['datos']['art5'][$campo['k']] ?? null) : null);
                if (Flujo::errorCampo($campo, $valor) !== null) {
                    return 'Captura primero los datos de esta solicitud.';
                }
            }
        } else {
            $aplica = $formato === 'requisitos';
            foreach ($etapas as $e) {
                $usados = $e['formatos'] ?? [];
                foreach ($e['documentos'] ?? [] as $d) {
                    if (!empty($d['formato'])) {
                        $usados[] = $d['formato'];
                    }
                }
                $aplica = $aplica || in_array($formato, $usados, true);
            }
            if (!$aplica) {
                return 'Este formato no aplica a la modalidad elegida.';
            }
        }
        $faltan = ['datos'];
        if (($req = self::etapaRequerida($formato)) !== null) {
            $faltan[] = $req;
        }
        foreach ($faltan as $clave) {
            $i = array_search($clave, $claves, true);
            if ($i === false) {
                return 'Este formato no aplica a la modalidad elegida.';
            }
            if (!Flujo::capturaCompleta($etapas[$i], $tramite['datos'])) {
                return 'Completa primero la etapa «' . $etapas[$i]['titulo'] . '».';
            }
        }
        if ($formato === 'sustentacion' && self::fechaVoto($tramite) === null) {
            return 'Primero deben firmar el voto aprobatorio tu asesor y tus revisores.';
        }
        return null;
    }

    /** Fecha (AAAA-MM-DD) en que quedó firmado el voto aprobatorio, o null si aún no. */
    public static function fechaVoto(array $tramite): ?string
    {
        $v = TitulacionRepository::documentosVigentes((int) $tramite['id'])['sustentacion|voto_aprobatorio'] ?? null;
        if (!$v || !empty($v['pendiente_firmas']) || $v['estado'] === 'rechazado') {
            return null;
        }
        $ultima = \App\Core\Database::valor("SELECT MAX(firmada_en) FROM tit_firmas_externas WHERE documento_id = ? AND estado = 'firmada'", [(int) $v['id']]);
        return $ultima ? substr((string) $ultima, 0, 10) : substr((string) $v['creado_en'], 0, 10);
    }

    /** Datos extra que algunos formatos necesitan del flujo (voto aprobatorio, fechas). */
    public static function extra(string $formato, array $tramite): array
    {
        return match ($formato) {
            'sustentacion' => ['fecha_voto' => self::fechaVoto($tramite) ?? ''],
            'voto' => VotoAprobatorio::datos($tramite),
            default => [],
        };
    }

    /** @return array{0:string,1:string,2:string,3:array} [PDF, nombre de archivo, mime, cajas de firma] */
    public static function generar(string $formato, array $tramite): array
    {
        $error = self::impedimento($formato, $tramite);
        if ($error !== null) {
            throw new RuntimeException($error);
        }
        $folio = (string) $tramite['folio'];
        if ($formato === 'protocolo') {
            [$bytes, $cajas] = ProtocoloPdf::generar($tramite);
        } else {
            [$bytes, $cajas] = FormatosOficiales::generar($formato, $tramite, self::extra($formato, $tramite));
        }
        return [$bytes, ucfirst($formato) . '_' . $folio . '.pdf', 'application/pdf', $cajas];
    }

    /** Nombre del Subdirector Académico, de Organización. */
    public static function subdirector(): string
    {
        $a = Autoridades::subdirector();
        $nombre = $a ? trim(\App\Core\Persona::tratamiento($a)) : '';
        return $nombre !== '' ? $nombre : 'SUBDIRECCIÓN ACADÉMICA';
    }

    /** Cargo del Subdirector en la forma que corresponde a su género. */
    public static function cargoSubdirector(): string
    {
        return \App\Core\Persona::f(Autoridades::subdirector() ?? [], 'SUBDIRECTOR ACADÉMICO', 'SUBDIRECTORA ACADÉMICA');
    }

    public static function fechaHoy(): array
    {
        $ahora = new \DateTimeImmutable('now');
        return [
            'dia' => $ahora->format('j'),
            'mes' => Catalogo::MESES[(int) $ahora->format('n')],
            'anio' => $ahora->format('Y'),
            'anio2' => $ahora->format('y'),
        ];
    }
}
