<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Configuracion;
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
        $def = Catalogo::formatos()[$formato] ?? null;
        if ($def === null) {
            return 'Formato desconocido.';
        }
        if (!$def['activo']) {
            return 'Este formato ya no se utiliza.';
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

    /**
     * Campos de datos que un formato puede imprimir (los usa el visor de coordenadas).
     * 'marca' pone una X en la casilla de la opción elegida; 'texto' imprime el valor.
     *
     * @return array<string,array{label:string,tipo:string,opciones?:array<string,string>}>
     */
    public static function variables(): array
    {
        $carreras = array_map(static fn(array $c): string => $c['nombre'], Catalogo::carreras());
        $modalidades = array_map(static fn(array $m): string => $m['nombre'], Catalogo::modalidades());
        $t = static fn(string $l): array => ['label' => $l, 'tipo' => 'texto'];
        $v = [
            'nombre' => $t('Nombre del pasante'), 'cuenta' => $t('Número de cuenta'),
            'licenciatura' => $t('Licenciatura (nombre completo)'), 'sistema_nombre' => $t('Sistema (FI-UAEMéx, UI-CUI…)'),
            'anio_ingreso' => $t('Año de ingreso'), 'anio_egreso' => $t('Año de egreso'),
            'telefono' => $t('Teléfono'), 'correo_inst' => $t('Correo institucional'), 'correo_pers' => $t('Correo personal'),
            'ocupacion' => $t('Ocupación actual'), 'fecha_nacimiento' => $t('Fecha de nacimiento'), 'acentos' => $t('Nombre lleva acentos (Sí/No)'),
            'modalidad_nombre' => $t('Opción de titulación (nombre)'), 'titulo' => $t('Título del trabajo / tema'),
            'asesor' => $t('Asesor'), 'coasesor' => $t('Coasesor'), 'area' => $t('Área académica'),
            'folio' => $t('Folio del trámite'), 'subdirector' => $t('Nombre del Subdirector Académico'),
            'fecha_dia' => $t('Fecha de hoy: día'), 'fecha_mes' => $t('Fecha de hoy: mes'), 'fecha_anio' => $t('Fecha de hoy: año (2 dígitos)'),
            'fecha_anio4' => $t('Fecha de hoy: año (4 dígitos)'), 'fecha_larga' => $t('Fecha de hoy completa'),
            'prot_dia' => $t('Aprobación del protocolo: día'), 'prot_mes' => $t('Aprobación del protocolo: mes'), 'prot_anio' => $t('Aprobación del protocolo: año'),
            'voto_dia' => $t('Voto aprobatorio: día'), 'voto_mes' => $t('Voto aprobatorio: mes'), 'voto_anio' => $t('Voto aprobatorio: año'),
            'voto2_dia' => $t('Fecha del voto (hoja 2): día'), 'voto2_mes' => $t('Fecha del voto (hoja 2): mes'), 'voto2_anio' => $t('Fecha del voto (hoja 2): año'),
            'motivos' => $t('Motivos (Art. 5)'), 'nombre_v' => $t('Nombre (voto aprobatorio)'), 'cuenta_v' => $t('Cuenta (voto aprobatorio)'), 'titulo_v' => $t('Título (voto aprobatorio)'),
            'licenciatura_titulo' => $t('Encabezado: LICENCIATURA DE INGENIERÍA EN…'), 'siglas' => $t('Siglas de la licenciatura'),
            'fecha_recepcion' => $t('Fecha de recepción del protocolo'), 'fecha_entrega' => $t('Fecha de entrega del dictamen'),
            'observaciones' => $t('Observaciones del dictamen'), 'firmante' => $t('Nombre de quien firma el dictamen'),
            'revision' => ['label' => 'Casilla: número de revisión', 'tipo' => 'marca', 'opciones' => ['1' => '1ª revisión', '2' => '2ª revisión']],
            'dictamen' => ['label' => 'Casilla: dictamen (se aprueba / no se aprueba)', 'tipo' => 'marca', 'opciones' => ['aprobado' => 'Se aprueba', 'no_aprobado' => 'No se aprueba']],
            'requiere_coord' => ['label' => 'Casilla: requiere revisión del coordinador', 'tipo' => 'marca', 'opciones' => ['si' => 'Sí', 'no' => 'No']],
            'carrera' => ['label' => 'Casilla: licenciatura', 'tipo' => 'marca', 'opciones' => $carreras],
            'carrera_v' => ['label' => 'Casilla: licenciatura (voto)', 'tipo' => 'marca', 'opciones' => $carreras],
            'sistema' => ['label' => 'Casilla: sistema', 'tipo' => 'marca', 'opciones' => Catalogo::sistemas()],
            'sistema_v' => ['label' => 'Casilla: sistema (voto)', 'tipo' => 'marca', 'opciones' => Catalogo::sistemas()],
            'modalidad' => ['label' => 'Casilla: opción de titulación', 'tipo' => 'marca', 'opciones' => $modalidades],
            'modalidad_v' => ['label' => 'Casilla: opción (voto)', 'tipo' => 'marca', 'opciones' => $modalidades],
            'vez' => ['label' => 'Casilla: primera / segunda vez', 'tipo' => 'marca', 'opciones' => ['primera' => 'Primera vez', 'segunda' => 'Segunda vez']],
        ];
        return $v;
    }

    /** Datos de ejemplo para la vista previa del visor. */
    public static function ejemplo(): array
    {
        $hoy = self::fechaHoy();
        return [
            'fecha_dia' => $hoy['dia'], 'fecha_mes' => $hoy['mes'], 'fecha_anio' => $hoy['anio2'], 'fecha_anio4' => $hoy['anio'],
            'fecha_larga' => $hoy['dia'] . ' de ' . $hoy['mes'] . ' de ' . $hoy['anio'],
            'nombre' => 'María José Hernández Peña', 'cuenta' => '1912345', 'carrera' => 'computacion', 'sistema' => 'fi',
            'licenciatura' => 'Ingeniería en Computación', 'sistema_nombre' => 'FI-UAEMéx', 'anio_ingreso' => '2019', 'anio_egreso' => '2024',
            'telefono' => '722 123 4567', 'correo_inst' => 'mjhernandezp@alumno.uaemex.mx', 'correo_pers' => 'maria@example.com',
            'ocupacion' => 'Desarrolladora de software', 'fecha_nacimiento' => '17 de mayo de 1999', 'acentos' => 'Sí', 'vez' => 'primera',
            'modalidad' => 'tesis', 'modalidad_nombre' => 'Tesis',
            'titulo' => 'Diseño e implementación de un sistema distribuido para la validación en línea de trámites de titulación en instituciones públicas',
            'asesor' => 'Dr. Juan Pérez Gómez', 'coasesor' => 'No aplica', 'area' => 'Computación', 'folio' => 'TIT-2026-00001',
            'subdirector' => mb_strtoupper(self::subdirector()), 'motivos' => 'Motivos laborales y familiares que impidieron concluir el trámite en el plazo.',
            'prot_dia' => '3', 'prot_mes' => 'marzo', 'prot_anio' => '26', 'voto_dia' => '5', 'voto_mes' => 'septiembre', 'voto_anio' => '26',
            'voto2_dia' => '5', 'voto2_mes' => 'septiembre', 'voto2_anio' => '26',
            'carrera_v' => 'computacion', 'sistema_v' => 'fi', 'modalidad_v' => 'tesis', 'nombre_v' => 'María José Hernández Peña',
            'licenciatura_titulo' => 'LICENCIATURA DE INGENIERÍA EN COMPUTACIÓN', 'siglas' => 'ICO', 'fecha_recepcion' => '25-09-2025',
            'fecha_entrega' => '03-11-2025', 'revision' => '1', 'dictamen' => 'aprobado', 'requiere_coord' => 'no', 'firmante' => 'DR. JAVIER SALAS GARCÍA',
            'observaciones' => 'FORMA: Revisar la redacción del título y la numeración de las secciones. FONDO: Ampliar la metodología y precisar los alcances del diseño propuesto, de modo que sea posible evaluar si el trabajo está completo.',
            'cuenta_v' => '1912345', 'titulo_v' => 'Diseño e implementación de un sistema distribuido', '_subdirector' => self::subdirector(),
        ];
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
            'fecha_anio4' => $hoy['anio'], 'fecha_larga' => $hoy['dia'] . ' de ' . $hoy['mes'] . ' de ' . $hoy['anio'],
            'sistema_nombre' => Catalogo::sistemas()[$p['sistema'] ?? ''] ?? '',
            'modalidad_nombre' => Catalogo::modalidad((string) $t['modalidad'])['nombre'] ?? '',
            'fecha_nacimiento' => !empty($p['fecha_nacimiento']) ? ProtocoloMembretado::fechaLarga((string) $p['fecha_nacimiento']) : '',
            'asesor' => $d['protocolo']['asesor'] ?? '', 'coasesor' => $d['protocolo']['coasesor'] ?? '', 'area' => $d['protocolo']['area'] ?? '',
            'folio' => (string) $t['folio'], 'subdirector' => mb_strtoupper(self::subdirector()),
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
        $r = Configuracion::resolverPdf($formato);
        if ($r === null) {
            throw new RuntimeException('El formato no tiene plantilla disponible.');
        }
        return [PdfFormato::generarCon($r['spec'], $r['plantilla'], self::valoresPdf($tramite)), ucfirst($formato) . '_' . $folio . '.pdf', 'application/pdf'];
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
