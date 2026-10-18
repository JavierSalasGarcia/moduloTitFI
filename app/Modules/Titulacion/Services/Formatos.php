<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Flujo;
use RuntimeException;

/** Une lo capturado en el trámite con los motores de PDF y Word. */
final class Formatos
{
    public const MIME_DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    /** Etapa cuya captura debe estar completa, además de «datos», para generar el formato. */
    public static function etapaRequerida(string $formato, array $tramite): ?string
    {
        return match ($formato) {
            'aprovechamiento', 'egel_50', 'egel_nacional', 'creditos' => 'solicitud',
            'protocolo' => 'protocolo',
            'revision' => 'revision',
            'sustentacion' => 'sustentacion',
            'art5' => 'art5',
            default => null,
        };
    }

    /** Mensaje de por qué todavía no se puede generar, o null si ya se puede. */
    public static function impedimento(string $formato, array $tramite): ?string
    {
        if (!isset(Catalogo::formatos()[$formato])) {
            return 'Formato desconocido.';
        }
        $etapas = Catalogo::etapas((string) $tramite['modalidad'], $tramite['variante'] ?: null);
        $claves = array_column($etapas, 'clave');
        $aplica = false;
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
        $faltan = ['datos'];
        $req = self::etapaRequerida($formato, $tramite);
        if ($req !== null) {
            $faltan[] = $req;
        }
        if ($formato === 'sustentacion') {
            $faltan[] = 'revision';
        }
        foreach ($faltan as $clave) {
            if ($clave === 'art5') {
                if (trim((string) ($tramite['datos']['art5']['motivos'] ?? '')) === '') {
                    return 'Escribe primero los motivos por los que excediste el plazo.';
                }
                continue;
            }
            $i = array_search($clave, $claves, true);
            if ($i === false) {
                return 'Este formato no aplica a la modalidad elegida.';
            }
            if (!Flujo::capturaCompleta($etapas[$i], $tramite['datos'])) {
                return 'Completa primero la etapa «' . $etapas[$i]['titulo'] . '».';
            }
        }
        return null;
    }

    /** Nombre del Subdirector Académico tal como debe aparecer; se captura una vez en Ajustes. */
    public static function subdirector(): string
    {
        $nombre = trim((string) \ajuste('titulacion_subdirector', 'DR. JAVIER SALAS GARCÍA'));
        return $nombre !== '' ? $nombre : 'DR. JAVIER SALAS GARCÍA';
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

    private static function partesFecha(?string $iso): array
    {
        $f = $iso ? \DateTimeImmutable::createFromFormat('!Y-m-d', $iso) : false;
        if (!$f) {
            return ['', '', ''];
        }
        return [$f->format('j'), Catalogo::MESES[(int) $f->format('n')], $f->format('y')];
    }

    /** Valores para el motor de PDF a partir de las capturas del trámite. */
    public static function valoresPdf(array $t): array
    {
        $d = $t['datos'];
        $p = $d['datos'] ?? [];
        $hoy = self::fechaHoy();
        $titulo = (string) ($d['revision']['titulo'] ?? $t['titulo'] ?? '');
        $carreras = Catalogo::carreras();
        [$pd, $pm, $pa] = self::partesFecha($d['revision']['fecha_protocolo'] ?? null);
        [$vd, $vm, $va] = self::partesFecha($d['sustentacion']['fecha_voto'] ?? null);
        $v = [
            'fecha_dia' => $hoy['dia'], 'fecha_mes' => $hoy['mes'], 'fecha_anio' => $hoy['anio2'],
            'nombre' => $p['nombre'] ?? '', 'cuenta' => $p['cuenta'] ?? '',
            'carrera' => $p['carrera'] ?? '', 'sistema' => $p['sistema'] ?? '',
            'anio_ingreso' => $p['anio_ingreso'] ?? '', 'anio_egreso' => $p['anio_egreso'] ?? '',
            'telefono' => $p['telefono'] ?? '', 'correo_inst' => $p['correo_inst'] ?? '', 'correo_pers' => $p['correo_pers'] ?? '',
            'ocupacion' => $p['ocupacion'] ?? '', 'acentos' => $p['acentos'] ?? '',
            'licenciatura' => $carreras[$p['carrera'] ?? '']['nombre'] ?? '',
            'vez' => $d['solicitud']['vez'] ?? '',
            'modalidad' => $t['modalidad'],
            'titulo' => $titulo,
            'prot_dia' => $pd, 'prot_mes' => $pm, 'prot_anio' => $pa,
            'voto_dia' => $vd, 'voto_mes' => $vm, 'voto_anio' => $va,
            'voto2_dia' => $vd ?: $hoy['dia'], 'voto2_mes' => $vm ?: $hoy['mes'], 'voto2_anio' => $va ?: $hoy['anio2'],
            'motivos' => $d['art5']['motivos'] ?? '',
            '_subdirector' => self::subdirector(),
        ];
        foreach (['carrera', 'sistema', 'modalidad', 'nombre', 'cuenta', 'titulo'] as $k) {
            $v[$k . '_v'] = $v[$k];
        }
        return $v;
    }

    /** @return array{0:string,1:string,2:string} [contenido, nombre de archivo, mime] */
    public static function generar(string $formato, array $tramite, array $hitos = []): array
    {
        $error = self::impedimento($formato, $tramite);
        if ($error !== null) {
            throw new RuntimeException($error);
        }
        $folio = (string) $tramite['folio'];
        $def = Catalogo::formatos()[$formato];
        if ($def['tipo'] === 'docx') {
            return [self::protocolo($tramite), 'Protocolo-' . $tramite['modalidad'] . '_' . $folio . '.docx', self::MIME_DOCX];
        }
        return [PdfFormato::generar($formato, self::valoresPdf($tramite)), ucfirst($formato) . '_' . $folio . '.pdf', 'application/pdf'];
    }

    private static function protocolo(array $t): string
    {
        $proto = Catalogo::protocolo((string) $t['modalidad']);
        if ($proto === null) {
            throw new RuntimeException('Esta modalidad no tiene formato de protocolo.');
        }
        $d = $t['datos'];
        $p = $d['datos'] ?? [];
        $respuestas = $d['protocolo'] ?? [];
        $hoy = self::fechaHoy();
        $secciones = [];
        $tema = '';
        foreach ($proto['campos'] as $campo) {
            $valor = $respuestas[$campo['k']] ?? ($campo['tipo'] === 'cronograma' ? [] : '');
            $campo['valor'] = $valor;
            $secciones[] = $campo;
            if (!empty($campo['tema'])) {
                $tema = (string) $valor;
            }
        }
        return ProtocoloMembretado::generar((string) $t['modalidad'], [
            'dia' => $hoy['dia'], 'mes' => $hoy['mes'], 'anio' => $hoy['anio'],
            'nombre' => $p['nombre'] ?? '', 'cuenta' => $p['cuenta'] ?? '', 'carrera' => $p['carrera'] ?? '',
            'tema' => $tema, 'secciones' => $secciones, 'subdirector' => self::subdirector(),
            'fecha_nacimiento' => !empty($p['fecha_nacimiento']) ? ProtocoloMembretado::fechaLarga((string) $p['fecha_nacimiento']) : '',
            'correo' => $p['correo_pers'] ?? '', 'telefono' => $p['telefono'] ?? '',
        ]);
    }
}
