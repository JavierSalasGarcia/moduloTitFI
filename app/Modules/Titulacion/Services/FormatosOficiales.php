<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Persona;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;
use RuntimeException;

/**
 * Formatos oficiales del Departamento de Evaluación Profesional (fi.uaemex.mx/titulacion), generados por la
 * plataforma con los datos del trámite: ya no hay plantillas ni coordenadas. Los nombres de quien firma o a quien
 * se dirigen salen de Organización; el texto sigue la versión vigente de cada formato.
 * Cada generador devuelve [PDF, cajas de firma por rol] para estampar después las firmas electrónicas.
 */
final class FormatosOficiales
{
    /** Formatos que llevan la autorización (firma) de la Subdirección Académica. */
    public const CON_SUBDIRECCION = ['aprovechamiento', 'egel_50', 'egel_nacional', 'creditos', 'trabajo_eval'];

    /** Formatos con Vo. Bo. del asesor, que lo firma desde el enlace de su correo. */
    public const CON_ASESOR = ['revision', 'sustentacion', 'prorroga', 'cambios'];

    // ------------------------------------------------------------------ datos

    /** Datos del trámite que usan los formatos. */
    public static function datos(array $t): array
    {
        $d = $t['datos'] ?? [];
        $p = $d['datos'] ?? [];
        $pr = $d['protocolo'] ?? [];
        $hitos = !empty($t['id']) ? TitulacionRepository::hitos((int) $t['id']) : [];
        $asesor = Persona::completar(['nombre' => trim((string) ($pr['asesor'] ?? '')), 'grado' => $pr['asesor_grado'] ?? null, 'genero' => $pr['asesor_genero'] ?? null], (string) ($pr['asesor_correo'] ?? ''));
        $co = trim((string) ($pr['coasesor'] ?? ''));
        $titulo = (string) ($d['revision']['titulo'] ?? '');
        if ($titulo === '') {
            $titulo = (string) ($t['titulo'] ?? '');
        }
        if ($titulo === '') {
            foreach (Catalogo::protocolo((string) $t['modalidad'])['campos'] ?? [] as $c) {
                if (!empty($c['tema'])) {
                    $titulo = (string) ($pr[$c['k']] ?? '');
                }
            }
        }
        return [
            'folio' => (string) ($t['folio'] ?? ''),
            'nombre' => (string) ($p['nombre'] ?? $t['alumno_nombre'] ?? ''),
            'cuenta' => (string) ($p['cuenta'] ?? ''),
            'carrera' => (string) ($p['carrera'] ?? ''),
            'licenciatura' => Catalogo::carreras()[$p['carrera'] ?? '']['nombre'] ?? '',
            'sistema' => (string) ($p['sistema'] ?? 'fi'),
            'ingreso' => (string) ($p['anio_ingreso'] ?? ''), 'egreso' => (string) ($p['anio_egreso'] ?? ''),
            'telefono' => (string) ($p['telefono'] ?? ''), 'correo_inst' => (string) ($p['correo_inst'] ?? ''), 'correo_pers' => (string) ($p['correo_pers'] ?? ''),
            'ocupacion' => (string) ($p['ocupacion'] ?? ''), 'acentos' => (string) ($p['acentos'] ?? ''),
            'modalidad' => (string) $t['modalidad'],
            'modalidad_nombre' => Catalogo::modalidad((string) $t['modalidad'])['nombre'] ?? '',
            'titulo' => $titulo,
            'asesor' => $asesor['nombre'] !== '' ? Persona::tratamiento($asesor) : '',
            'asesor_p' => $asesor,
            'coasesor' => $co !== '' && mb_strtolower($co) !== 'no aplica' ? Persona::tratamiento(['nombre' => $co, 'grado' => $pr['coasesor_grado'] ?? null]) : '',
            'fecha_protocolo' => (string) ($hitos['protocolo']['datos']['fecha'] ?? ''),
            'vez' => (string) ($d['solicitud']['vez'] ?? ''),
            'incorporada' => ($p['sistema'] ?? 'fi') !== 'fi',
        ];
    }

    private static function subdirector(): array
    {
        $s = Autoridades::subdirector() ?? [];
        return ['nombre' => $s ? Persona::tratamiento($s) : Formatos::subdirector(), 'cargo' => Persona::f($s, 'SUBDIRECTOR ACADÉMICO', 'SUBDIRECTORA ACADÉMICA'),
            'secretario' => Persona::f($s, 'SECRETARIO', 'SECRETARIA')];
    }

    /** «DR. … / SUBDIRECTOR ACADÉMICO DE LA FACULTAD DE INGENIERÍA / UNIVERSIDAD AUTÓNOMA DEL ESTADO DE MÉXICO». */
    private static function alSubdirector(Hoja $pdf): void
    {
        $s = self::subdirector();
        $pdf->destinatario([$s['nombre'], $s['cargo'] . ' DE LA FACULTAD DE INGENIERÍA', 'UNIVERSIDAD AUTÓNOMA DEL ESTADO DE MÉXICO']);
    }

    private static function encabezadoPasante(Hoja $pdf, array $x, string $encabezado = 'Quien suscribe,'): void
    {
        $pdf->texto($encabezado, '', 10);
        $pdf->datos([['Nombre:', $x['nombre']], ['Número de cuenta:', $x['cuenta']]], 38);
        $pdf->licenciatura($x['carrera'], $x['sistema']);
    }

    private static function tituloTrabajo(Hoja $pdf, array $x, string $prefijo = 'cuyo título es:'): void
    {
        $pdf->campo($prefijo, $x['titulo']);
        $pdf->Ln(6);
    }

    private static function firmaPasanteAsesor(Hoja $pdf, array $x, bool $conAsesor = true): array
    {
        $bloques = [['rol' => 'pasante', 'arriba' => 'ATENTAMENTE', 'nombre' => $x['nombre'], 'cargo' => ['NOMBRE Y FIRMA DEL PASANTE']]];
        if ($conAsesor) {
            $bloques[] = ['rol' => 'asesor', 'arriba' => 'Vo.Bo.', 'nombre' => $x['asesor'] ?: ' ', 'cargo' => [Persona::f($x['asesor_p'], 'NOMBRE Y FIRMA DEL ASESOR', 'NOMBRE Y FIRMA DE LA ASESORA')]];
        }
        return $pdf->firmas($bloques);
    }

    private static function fechaLarga(string $iso): string
    {
        return $iso !== '' ? Hoja::fecha($iso) : '____ de ______________ de 20____';
    }

    // ------------------------------------------------------------------ catálogo

    /** @return array<string,array{0:string,1:callable}> clave => [nombre, generador] */
    public static function generadores(): array
    {
        return [
            'aprovechamiento' => ['Solicitud de evaluación profesional por Aprovechamiento académico', [self::class, 'solicitudSinTrabajo']],
            'egel_50' => ['Solicitud de EGEL (Convocatoria «Pagando menos»)', [self::class, 'solicitudSinTrabajo']],
            'egel_nacional' => ['Solicitud de EGEL (Convocatoria nacional)', [self::class, 'solicitudSinTrabajo']],
            'creditos' => ['Solicitud de Créditos en estudios avanzados', [self::class, 'solicitudSinTrabajo']],
            'datos_titulo' => ['Formato de datos del interesado para expedición de título profesional', [self::class, 'datosTitulo']],
            'revision' => ['Solicitud de revisión de trabajo escrito (examen previo)', [self::class, 'revision']],
            'voto' => ['Voto aprobatorio', [self::class, 'voto']],
            'sustentacion' => ['Solicitud de sustentación del trabajo escrito (examen definitivo)', [self::class, 'sustentacion']],
            'trabajo_eval' => ['Solicitud para presentar la evaluación profesional mediante trabajo escrito', [self::class, 'trabajoEval']],
            'art5' => ['Solicitud para continuar con la evaluación profesional (Art. 5, REP)', [self::class, 'articulo5']],
            'prorroga' => ['Solicitud de prórroga del tema', [self::class, 'prorroga']],
            'cambio_asesor' => ['Solicitud de cambio de asesor', [self::class, 'cambioAsesor']],
            'baja' => ['Solicitud de baja de tema y/o sustentante, o cambio de sustentante', [self::class, 'baja']],
            'cambios' => ['Solicitud de cambios al protocolo', [self::class, 'cambios']],
            'coasesoria' => ['Solicitud para coasesorar el trabajo escrito', [self::class, 'coasesoria']],
            'carta_presentacion' => ['Carta de presentación (instituciones incorporadas)', [self::class, 'cartaPresentacion']],
            'carta_ri' => ['Carta de autorización para publicación en el Repositorio Institucional', [self::class, 'cartaRi']],
            'carta_excepcion_ri' => ['Carta de excepción para publicación en el Repositorio Institucional', [self::class, 'cartaExcepcionRi']],
            'autoriz_eval' => ['Solicitud de autorización de evaluación profesional (instituciones incorporadas)', [self::class, 'autorizacionEval']],
            'revision_protocolo' => ['Revisión de protocolo', [self::class, 'revisionProtocolo']],
            'requisitos' => ['Requisitos para la evaluación profesional', [self::class, 'requisitos']],
        ];
    }

    /** @return array{0:string,1:array<string,array>} */
    public static function generar(string $clave, array $tramite, array $extra = []): array
    {
        $g = self::generadores()[$clave] ?? null;
        if ($g === null) {
            throw new RuntimeException('Formato desconocido.');
        }
        return ($g[1])($tramite, $extra + ['_clave' => $clave]);
    }

    // ------------------------------------------------------------------ sin trabajo escrito

    public static function solicitudSinTrabajo(array $t, array $e): array
    {
        $x = self::datos($t);
        $clave = $e['_clave'];
        $pdf = Hoja::nueva($x['folio'], true, $clave === 'egel_50' ? 'v012026' : 'v082025');
        $opcion = ['aprovechamiento' => 'APROVECHAMIENTO ACADÉMICO', 'creditos' => 'CRÉDITOS EN ESTUDIOS AVANZADOS'][$clave] ?? 'EXAMEN GENERAL DE EGRESO DE LICENCIATURA (EGEL)';
        if (str_starts_with($clave, 'egel')) {
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->Cell(0, 4.5, self::l($clave === 'egel_50' ? 'Convocatoria «Titúlate presentando el EGEL, pagando menos»' : 'Convocatoria Nacional en sede UAEM'), 0, 1, 'R');
        }
        $pdf->titulo('Oficio de solicitud para optar por evaluación profesional mediante', $opcion);
        $pdf->lugarFecha();
        self::alSubdirector($pdf);
        $pdf->texto('Quien suscribe:', '', 10);
        $pdf->datos([['Nombre del pasante', $x['nombre']], ['No. de cuenta', $x['cuenta']]], 40);
        $pdf->licenciatura($x['carrera'], $x['sistema']);
        $pdf->texto('Año de ingreso a la Licenciatura: ' . ($x['ingreso'] ?: '______') . '        Año de egreso de la Licenciatura: ' . ($x['egreso'] ?: '______'), '', 9.5);
        $pdf->Ln(2);
        $pdf->texto('Solicita de Ud. autorización para realizar la Evaluación Profesional mediante la opción de:', '', 10);
        $pdf->texto(match ($clave) {
            'aprovechamiento' => 'APROVECHAMIENTO ACADÉMICO de acuerdo con lo dispuesto en el Título Segundo, Capítulo Primero, Artículo 14, numeral V.- Presentar la solicitud por escrito para esta opción de evaluación profesional, dentro del primer año posterior a la conclusión del plan de estudios cursados; y Título Tercero, Capítulo Primero Artículos 83 y 84, todos del Reglamento de Evaluación Profesional de la Universidad Autónoma del Estado de México.',
            'creditos' => 'CRÉDITOS EN ESTUDIOS AVANZADOS, de acuerdo con lo dispuesto en los artículos 83, 21 y 22; considerando el resultado como lo marca el Título Tercero, Capítulo Séptimo, Artículo 113 inciso IV y sujetándose a lo establecido en el Artículo 118 del mismo capítulo; todos estos artículos del Reglamento de Evaluación Profesional de la Universidad Autónoma del Estado de México.',
            default => 'EXAMEN GENERAL DE EGRESO DE LICENCIATURA, de acuerdo con lo dispuesto en el Título Segundo, Capítulo Quinto, Artículos 29 y 30; considerando el resultado como lo marca el Título Tercero, Capítulo Séptimo, Artículo 113 inciso IV y sujetándose a lo establecido en el Artículo 118 del mismo capítulo; todos estos artículos del Reglamento de Evaluación Profesional de la Universidad Autónoma del Estado de México. Asimismo, entiendo que, para que el resultado del EGEL sea reconocido, debo presentarlo en sede UAEM.',
        }, '', 9.5);
        if ($clave !== 'aprovechamiento') {
            $pdf->Ln(1);
            $pdf->texto('La solicitud se presenta por:   Primera vez ( ' . ($x['vez'] === 'primera' ? 'X' : '  ') . ' )     Segunda vez ( ' . ($x['vez'] === 'segunda' ? 'X' : '  ') . ' )', 'B', 9.5);
        }
        $pdf->Ln(1);
        $pdf->texto('Para tal objeto, se proporcionan los datos y documentación (en tamaño carta y, en caso necesario, por ambos lados) siguientes:', '', 9.5);
        $pdf->datos([['Teléfono', $x['telefono']], ['Correo institucional', $x['correo_inst']], ['Correo personal', $x['correo_pers']]], 40);
        $anexos = match ($clave) {
            'aprovechamiento' => ['Copia fotostática del certificado de estudios.', 'Original de la trayectoria académica firmada por el Jefe de Control Escolar.', 'Copia fotostática del certificado de Servicio Social.'],
            'creditos' => ['Copia fotostática del certificado de estudios.', 'Copia fotostática del certificado de Servicio Social.', 'Plan de estudios que avale cursos, créditos, actividades con valor curricular, plazos mínimos, perfiles y contenidos programáticos.', 'Registro del programa ante la Dirección General de Profesiones de la SEP o validez oficial (estudios fuera de la UAEM).', 'Tres fotografías tamaño óvalo miñón, papel mate, de frente, ropa clara (solo FI-UAEMéx).'],
            default => ['Copia fotostática del certificado total de estudios.', 'Copia fotostática del certificado de Servicio Social.', 'Tres fotografías tamaño óvalo miñón, papel mate, de frente, ropa clara y en caso de los hombres con corbata (solo sistema dependiente: FI-UAEMéx).'],
        };
        $pdf->texto('Documentos anexos:', 'B', 9.5);
        foreach ($anexos as $a) {
            $pdf->SetX(30);
            $pdf->texto('•  ' . $a, '', 8.5, 'L', 4.0);
        }
        $s = self::subdirector();
        $cajas = $pdf->firmas([
            ['rol' => 'pasante', 'arriba' => 'ATENTAMENTE', 'nombre' => $x['nombre'], 'cargo' => ['NOMBRE Y FIRMA DEL PASANTE']],
            ['rol' => 'subdirector', 'arriba' => 'AUTORIZACIÓN DE LA SUBDIRECCIÓN ACADÉMICA', 'nombre' => $s['nombre'], 'cargo' => [$s['cargo']]],
        ], 13);
        return [$pdf->Output('S'), $cajas];
    }

    public static function datosTitulo(array $t, array $e): array
    {
        $x = self::datos($t);
        $pdf = Hoja::nueva($x['folio']);
        $pdf->titulo('Formato de datos del interesado para expedición de título profesional');
        $pdf->texto('Instrucciones: El interesado deberá llenar su nombre con mayúsculas, minúsculas y acentos. Tal como lo escriba aparecerá en el título profesional. Es necesario indicar claramente los acentos. El formato no es válido si se llena únicamente con letra mayúscula o presenta abreviaturas en el nombre que no concuerden con la CURP.', '', 9.5);
        $pdf->Ln(3);
        $pdf->datos([
            ['Nombre:', $x['nombre']], ['No. de cuenta:', $x['cuenta']],
            ['Mi nombre y/o apellidos llevan acentos:', $x['acentos']],
            ['Licenciatura:', $x['licenciatura']], ['Teléfono:', $x['telefono']], ['Correo electrónico:', $x['correo_pers'] ?: $x['correo_inst']],
            ['Ocupación actual:', $x['ocupacion']],
        ], 62);
        $pdf->Ln(2);
        $pdf->texto('NOTA: El nombre en este formato debe corresponder (mayúsculas, minúsculas y acentos) al que se indique en el proceso de actualización de datos en el portal de alumnos. Cualquier inconsistencia será causante de atraso en la expedición del Título Profesional.', 'B', 9);
        $pdf->Ln(2);
        $pdf->texto('Hago constar que la información proporcionada en este documento es correcta y soy responsable de los datos mencionados.', '', 9.5);
        $cajas = $pdf->firmas([['rol' => 'pasante', 'nombre' => $x['nombre'], 'cargo' => ['Nombre y firma del Egresado']]]);
        return [$pdf->Output('S'), $cajas];
    }

    // ------------------------------------------------------------------ con trabajo escrito

    public static function revision(array $t, array $e): array
    {
        $x = self::datos($t);
        $pdf = Hoja::nueva($x['folio'], true, 'v082025');
        $pdf->titulo('Solicitud de revisión de trabajo escrito');
        $pdf->lugarFecha();
        self::alSubdirector($pdf);
        self::encabezadoPasante($pdf, $x);
        $pdf->texto('quien ha concluido el desarrollo del trabajo escrito en la modalidad de:', '', 10);
        $pdf->casillas(Hoja::opcionesTrabajo(), $x['modalidad'], 6.0, 2, 9);
        self::tituloTrabajo($pdf, $x);
        $pdf->texto('y que fue aprobado por el Comité Revisor de Protocolos, el día ' . self::fechaLarga($x['fecha_protocolo']) . ', mediante el presente, solicita a usted, fecha y asignación de revisores para llevar a cabo la revisión del trabajo escrito.', '', 10);
        $pdf->Ln(2);
        $pdf->texto('Asimismo, adjunto el REPORTE DE PORCENTAJE DE SIMILITUD (art. 93 del Reglamento de Evaluación Profesional) emitido por la herramienta iThenticate, mismo que también he integrado al trabajo escrito.', '', 10);
        $cajas = self::firmaPasanteAsesor($pdf, $x);
        $pdf->texto('Con base en el Reglamento de Evaluación Profesional, se deberá presentar para la evaluación profesional: 1) Artículo: carta de recepción del artículo por la autoridad de la revista y evidencia del comité académico o científico y su registro en índices. 2) Memoria: constancia de servicios laborales dirigida al director de la Facultad. 3) Autoempleo: carta de participación y evidencia de la constitución legal de la unidad económica. 4) Residencia: constancia de adscripción al SNI del investigador, oficio de registro del proyecto y constancia de estancia de al menos seis meses.', '', 7.5, 'J', 3.6);
        return [$pdf->Output('S'), $cajas];
    }

    public static function sustentacion(array $t, array $e): array
    {
        $x = self::datos($t);
        $pdf = Hoja::nueva($x['folio'], true, 'v082025');
        $pdf->titulo('Oficio de solicitud para la sustentación del trabajo escrito', '(Evaluación profesional)');
        $pdf->lugarFecha();
        self::alSubdirector($pdf);
        self::encabezadoPasante($pdf, $x, 'Quien suscribe:');
        $pdf->texto('quien ha concluido las correcciones solicitadas durante la revisión del trabajo escrito, avaladas por el voto aprobatorio de fecha ' . self::fechaLarga((string) ($e['fecha_voto'] ?? '')) . ', correspondiente a la opción de:', '', 10);
        $pdf->casillas(Hoja::opcionesTrabajo(), $x['modalidad'], 6.0, 2, 9);
        self::tituloTrabajo($pdf, $x, 'y cuyo título es:');
        $pdf->texto('mediante el presente, solicita a usted: a) autorización para la impresión del trabajo escrito, b) fecha y asignación del jurado de sustentación del trabajo escrito (evaluación profesional).', '', 10);
        $pdf->Ln(1);
        $pdf->texto('Se adjunta: a) el índice genérico del trabajo escrito y b) voto aprobatorio.', '', 10);
        $cajas = self::firmaPasanteAsesor($pdf, $x);
        return [$pdf->Output('S'), $cajas];
    }

    /**
     * Voto aprobatorio: lo firman el asesor y los revisores. $e['firmantes'] = [['rol' => 'asesor'|'revisor_N', 'nombre' => …], …];
     * $e['fecha_observaciones'] fecha de entrega de observaciones (examen previo).
     */
    public static function voto(array $t, array $e): array
    {
        $x = self::datos($t);
        $pdf = Hoja::nueva($x['folio'], true, 'v082025');
        $pdf->titulo('Revisión de trabajo escrito para evaluación profesional', 'Voto aprobatorio');
        $pdf->lugarFecha($e['fecha'] ?? null);
        self::alSubdirector($pdf);
        $pdf->texto('Los suscritos, profesores designados como asesor y revisores del trabajo escrito siguiente:', '', 10);
        $pdf->datos([['Nombre del pasante', $x['nombre']], ['No. de cuenta', $x['cuenta']], ['Licenciatura de Ingeniería', $x['licenciatura'] . ($x['carrera'] === 'computacion' ? ' (' . (Catalogo::sistemas()[$x['sistema']] ?? '') . ')' : '')],
            ['Opción', $x['modalidad_nombre']], ['Título del trabajo', $x['titulo']],
            ['Fecha de entrega de observaciones derivadas de la revisión del trabajo escrito', self::fechaLarga((string) ($e['fecha_observaciones'] ?? ''))]], 62);
        $pdf->texto('Con base en los artículos 97 y 98 del Capítulo Tercero del Reglamento de Evaluación Profesional de la Universidad Autónoma del Estado de México, y una vez que hemos revisado que se han atendido las observaciones y el trabajo cumple con los requisitos y cualidades que corresponden a la opción de evaluación profesional, le informamos que el pasante puede solicitar la sustentación del trabajo escrito ante el jurado.', '', 9.5);
        $pdf->Ln(3);
        // Tabla: calidad | nombre | firma | observaciones
        $pdf->SetFont('Helvetica', 'B', 9);
        $pdf->Cell(22, 6, '', 1, 0, 'C');
        $pdf->Cell(62, 6, 'Nombre', 1, 0, 'C');
        $pdf->Cell(52, 6, 'Firma', 1, 0, 'C');
        $pdf->Cell(30, 6, 'Observaciones', 1, 1, 'C');
        $cajas = [];
        $mm = 72 / 25.4;
        foreach ($e['firmantes'] ?? [] as $f) {
            if ($pdf->GetY() > 235) {
                $pdf->AddPage();
            }
            $y = $pdf->GetY();
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $pdf->Cell(22, 17, self::l($f['rol'] === 'asesor' ? 'Asesor' : 'Revisor'), 1, 0, 'C');
            $pdf->SetFont('Helvetica', '', 8.5);
            $pdf->Cell(62, 17, self::l(mb_strimwidth((string) $f['nombre'], 0, 40, '…')), 1, 0, 'L');
            $pdf->Cell(52, 17, '', 1, 0);
            $pdf->Cell(30, 17, '', 1, 1);
            $cajas[$f['rol']] = ['p' => $pdf->PageNo() - 1, 'x' => 109 * $mm, 'y' => ($y + 16.5) * $mm, 'w' => 52 * $mm, 'h' => 11.0];
        }
        return [$pdf->Output('S'), $cajas];
    }

    public static function trabajoEval(array $t, array $e): array
    {
        $x = self::datos($t);
        $pdf = Hoja::nueva($x['folio'], true, 'v082025');
        $pdf->titulo('Oficio de solicitud para optar por evaluación profesional mediante trabajo escrito');
        $pdf->lugarFecha();
        self::alSubdirector($pdf);
        $pdf->texto('Quien suscribe:', '', 10);
        $pdf->datos([['Nombre del pasante', $x['nombre']], ['No. de cuenta', $x['cuenta']]], 40);
        $pdf->licenciatura($x['carrera'], $x['sistema']);
        $pdf->texto('Solicita de Ud. autorización para realizar la Evaluación Profesional mediante la opción de:', '', 10);
        $pdf->casillas(Hoja::opcionesTrabajo(), $x['modalidad'], 6.0, 2, 9);
        $pdf->texto('de acuerdo con lo dispuesto en el Título Tercero, Capítulo Primero, Artículo 86 del Reglamento de Evaluación Profesional de la Universidad Autónoma del Estado de México. Para tal objeto, se adjunta la documentación siguiente:', '', 9.5);
        foreach (['Copia del certificado de estudios de licenciatura (descargado del portal de alumnos)', 'Copia del certificado de estudios de bachillerato', 'Copia del certificado de servicio social', 'Oficio de autorización de Protocolo de trabajo escrito', 'Voto aprobatorio del asesor y de los dos revisores del trabajo escrito'] as $a) {
            $pdf->SetX(30);
            $pdf->texto('•  ' . $a, '', 8.5, 'L', 4.0);
        }
        $pdf->Ln(1);
        $pdf->texto('Asimismo, entiendo que, para poder presentar la evaluación profesional deberé cumplir con todos los requisitos para la opción señalada, establecidos en el Reglamento de Evaluación Profesional y demás aplicables por la Universidad.', '', 9.5);
        $s = self::subdirector();
        $cajas = $pdf->firmas([
            ['rol' => 'pasante', 'arriba' => 'ATENTAMENTE', 'nombre' => $x['nombre'], 'cargo' => ['NOMBRE Y FIRMA DEL PASANTE']],
            ['rol' => 'subdirector', 'arriba' => 'AUTORIZACIÓN DE LA SUBDIRECCIÓN ACADÉMICA', 'nombre' => $s['nombre'], 'cargo' => [$s['cargo']]],
        ], 13);
        return [$pdf->Output('S'), $cajas];
    }

    public static function articulo5(array $t, array $e): array
    {
        $x = self::datos($t);
        $trabajo = ($t['tipo'] ?? '') === 'con_trabajo';
        $s = self::subdirector();
        $pdf = Hoja::nueva($x['folio'], true, 'v082025');
        $pdf->titulo('Solicitud para continuar con la evaluación profesional (Art. 5 - REP)');
        $pdf->lugarFecha();
        $pdf->destinatario([$s['nombre'], $s['secretario'] . ' DEL H. CONSEJO ACADÉMICO', 'DE LA FACULTAD DE INGENIERÍA DE LA UAEM']);
        self::encabezadoPasante($pdf, $x);
        $pdf->texto('Solicita a los HH. Consejos Académico y de Gobierno le permitan continuar con sus trámites de titulación, debido a que por el Título Primero, Artículo 5 del Reglamento de Evaluación Profesional de la Universidad Autónoma del Estado de México, excedí el tiempo para realizar dicho trámite.', '', 10);
        $pdf->Ln(2);
        $pdf->recuadro('Los motivos por los que excedí el tiempo son:', (string) ($t['datos']['art5']['motivos'] ?? $e['motivos'] ?? ''), 22);
        $pdf->texto('Anexo al presente:', 'B', 9.5);
        $pdf->casillas(['cv' => 'Currículum vitae', 'tema' => 'Oficio de autorización del tema (solo para trabajos escritos)', 'prorroga' => 'Oficio de autorización de prórroga del tema (solo para trabajos escritos, siempre y cuando aplique)'], null, 6.0, 1, 9);
        $bloques = [['rol' => 'pasante', 'arriba' => 'ATENTAMENTE', 'nombre' => $x['nombre'], 'cargo' => ['NOMBRE Y FIRMA DEL PASANTE']]];
        if ($trabajo) {
            $bloques[] = ['rol' => 'asesor', 'arriba' => 'Vo.Bo.', 'nombre' => $x['asesor'] ?: ' ', 'cargo' => ['NOMBRE Y FIRMA DEL ASESOR']];
        }
        if ($x['incorporada']) {
            $bloques[] = ['rol' => 'responsable', 'arriba' => $trabajo ? '' : 'Vo.Bo.', 'nombre' => ' ', 'cargo' => ['RESPONSABLE DEL ÁREA DE TITULACIÓN', 'Y SELLO DE LA INSTITUCIÓN']];
        }
        $cajas = $pdf->firmas($bloques);
        return [$pdf->Output('S'), $cajas];
    }

    public static function prorroga(array $t, array $e): array
    {
        $x = self::datos($t);
        $s = self::subdirector();
        $c = $t['datos']['adicional']['prorroga'] ?? [];
        $pdf = Hoja::nueva($x['folio'], true, 'Rev082025');
        $pdf->titulo('Solicitud de prórroga para trabajo escrito de evaluación profesional');
        $pdf->lugarFecha();
        $pdf->destinatario([$s['nombre'], 'SUBDIRECCIÓN ACADÉMICA DE LA FACULTAD DE INGENIERÍA', 'UAEMex']);
        self::encabezadoPasante($pdf, $x);
        $pdf->texto('quien desarrolla trabajo escrito en la modalidad de:', '', 10);
        $pdf->casillas(Hoja::opcionesTrabajo(), $x['modalidad'], 6.0, 2, 9);
        self::tituloTrabajo($pdf, $x);
        $pdf->texto('con la asesoría ' . Persona::f($x['asesor_p'], 'del', 'de la') . ' ' . ($x['asesor'] ?: '________________') . ', y que fue aprobado por el Comité Revisor de Protocolos de la Coordinación de Docencia respectiva el día ' . self::fechaLarga($x['fecha_protocolo']) . ', mediante el presente, solicita una prórroga por UN AÑO a partir de los dos años de vencimiento de la fecha indicada, para concluir el desarrollo del mencionado tema. Entiendo que, con base en el Artículo 86, Fracción VIII del Reglamento de Evaluación Profesional de la UAEM, vencido ese plazo, si no he realizado la sustentación ante el jurado, el tema quedará libre para ser presentado por otro pasante.', '', 9.5);
        $pdf->Ln(1);
        $pdf->texto('Hasta el momento el trabajo cuenta aproximadamente con un ' . ((string) ($c['avance'] ?? '') !== '' ? $c['avance'] : '____') . ' % de avance.', '', 9.5);
        $pdf->recuadro('Los motivos por los cuales se solicita esta prórroga son:', (string) ($c['motivos'] ?? ''), 18);
        $pdf->texto('Adjunto copia del oficio de aprobación del protocolo.', '', 9.5);
        $cajas = self::firmaPasanteAsesor($pdf, $x);
        return [$pdf->Output('S'), $cajas];
    }

    /** Encabezado común de las solicitudes de cambios sobre un protocolo ya aprobado. */
    private static function solicitudSobreProtocolo(array $t, string $titulo, array $destinatario): array
    {
        $x = self::datos($t);
        $pdf = Hoja::nueva($x['folio'], true, 'v082025');
        $pdf->titulo($titulo);
        $pdf->lugarFecha();
        $pdf->destinatario($destinatario);
        self::encabezadoPasante($pdf, $x);
        $pdf->texto('quien desarrolla trabajo escrito en la modalidad de:', '', 10);
        $pdf->casillas(Hoja::opcionesTrabajo(), $x['modalidad'], 6.0, 2, 9);
        self::tituloTrabajo($pdf, $x);
        return [$pdf, $x];
    }

    public static function cambioAsesor(array $t, array $e): array
    {
        $s = self::subdirector();
        $c = $t['datos']['adicional']['cambio_asesor'] ?? [];
        [$pdf, $x] = self::solicitudSobreProtocolo($t, 'Solicitud de cambio de asesor para trabajo escrito de evaluación profesional', [$s['nombre'], $s['cargo'], 'DE LA FACULTAD DE INGENIERÍA, UAEMex']);
        $pdf->texto('y que fue aprobado el día ' . self::fechaLarga($x['fecha_protocolo']) . ', mediante el presente, solicita de la manera más atenta cambio de asesor.', '', 10);
        $pdf->Ln(1);
        $pdf->datos([['Asesor actual:', $x['asesor']], ['Asesor propuesto:', (string) ($c['propuesto'] ?? '')]], 38);
        $pdf->recuadro('Motivos del cambio:', (string) ($c['motivos'] ?? ''), 16);
        $pdf->texto('El asesor propuesto cumple con lo estipulado en el art. 88 del REP. Adjunto copia del oficio de aprobación del protocolo (y prórroga, si es el caso).', '', 9.5);
        $cajas = $pdf->firmas([
            ['rol' => 'pasante', 'arriba' => 'ATENTAMENTE', 'nombre' => $x['nombre'], 'cargo' => ['NOMBRE Y FIRMA DEL PASANTE']],
            ['rol' => 'asesor', 'nombre' => $x['asesor'] ?: ' ', 'cargo' => ['NOMBRE Y FIRMA DEL ASESOR ACTUAL']],
            ['rol' => 'asesor_propuesto', 'nombre' => (string) ($c['propuesto'] ?? ' '), 'cargo' => ['NOMBRE Y FIRMA DEL ASESOR PROPUESTO']],
        ]);
        return [$pdf->Output('S'), $cajas];
    }

    public static function baja(array $t, array $e): array
    {
        $s = self::subdirector();
        $c = $t['datos']['adicional']['baja'] ?? [];
        [$pdf, $x] = self::solicitudSobreProtocolo($t, 'Solicitud de baja de tema y/o sustentante y/o cambio de sustentante de trabajo escrito de evaluación profesional', [$s['nombre'], $s['cargo'], 'DE LA FACULTAD DE INGENIERÍA, UAEMex']);
        $pdf->texto('y que fue aprobado el día ' . self::fechaLarga($x['fecha_protocolo']) . ', mediante el presente, solicita de la manera más atenta:', '', 10);
        $pdf->casillas(['baja_tema' => 'Baja de tema', 'baja_sustentante' => 'Baja de sustentante', 'cambio_sustentante' => 'Cambio de sustentante'], (string) ($c['tipo'] ?? ''), 6.0, 3);
        $pdf->recuadro('Por los siguientes motivos:', (string) ($c['motivos'] ?? ''), 18);
        $pdf->texto('Adjunto copia del oficio de aprobación del protocolo (y prórroga, si es el caso).', '', 9.5);
        $cajas = $pdf->firmas([
            ['rol' => 'pasante', 'arriba' => 'ATENTAMENTE', 'nombre' => $x['nombre'], 'cargo' => ['NOMBRE Y FIRMA DEL PASANTE']],
            ['rol' => 'asesor', 'nombre' => $x['asesor'] ?: ' ', 'cargo' => ['NOMBRE Y FIRMA DEL ASESOR']],
            ['rol' => 'sustentante', 'nombre' => (string) ($c['sustentante'] ?? ' '), 'cargo' => ['NOMBRE, NO. DE CUENTA Y FIRMA', 'DEL SUSTENTANTE PROPUESTO']],
        ]);
        return [$pdf->Output('S'), $cajas];
    }

    public static function cambios(array $t, array $e): array
    {
        $c = $t['datos']['adicional']['cambios'] ?? [];
        [$pdf, $x] = self::solicitudSobreProtocolo($t, 'Solicitud de cambios al protocolo de trabajo escrito de evaluación profesional', ['COMITÉ REVISOR DE PROTOCOLOS', 'FACULTAD DE INGENIERÍA, UAEMex']);
        $pdf->texto('y que fue aprobado el día ' . self::fechaLarga($x['fecha_protocolo']) . ', mediante el presente, solicita de la manera más atenta, cambios al protocolo.', '', 10);
        $pdf->recuadro('Situación actual:', (string) ($c['actual'] ?? ''), 10);
        $pdf->recuadro('Cambios propuestos:', (string) ($c['propuestos'] ?? ''), 10);
        $pdf->recuadro('Motivos del cambio:', (string) ($c['motivos'] ?? ''), 10);
        $pdf->texto('Se adjunta: copia del protocolo aprobado, y del oficio de aprobación del protocolo (y prórroga, si es el caso).', '', 9.5);
        $cajas = self::firmaPasanteAsesor($pdf, $x);
        return [$pdf->Output('S'), $cajas];
    }

    public static function coasesoria(array $t, array $e): array
    {
        $x = self::datos($t);
        $s = self::subdirector();
        $c = $t['datos']['adicional']['coasesoria'] ?? [];
        $pdf = Hoja::nueva($x['folio'], true, 'v082025');
        $pdf->titulo('Solicitud para coasesorar trabajo escrito de evaluación profesional');
        $pdf->lugarFecha();
        $pdf->destinatario([$s['nombre'], $s['secretario'] . ' DEL H. CONSEJO ACADÉMICO', 'DE LA FACULTAD DE INGENIERÍA DE LA UAEM']);
        $pdf->campo('Quien suscribe,', $x['asesor']);
        $pdf->Write(5.2, Hoja::l1(' asesor del tema '));
        $pdf->SetFont('Helvetica', 'BU', 10);
        $pdf->Write(5.2, Hoja::l1($x['titulo']));
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Write(5.2, Hoja::l1(' en la modalidad de:'));
        $pdf->Ln(7);
        $pdf->casillas(['tesis' => 'Tesis', 'articulo' => 'Artículo especializado para publicar en revista indizada', 'residencia' => 'Reporte de residencia de investigación'], $x['modalidad']);
        $pdf->texto('que desarrolla el pasante ' . $x['nombre'] . ', con número de cuenta ' . $x['cuenta'] . ', de la', '', 10);
        $pdf->licenciatura($x['carrera'], $x['sistema'], 'Licenciatura de Ingeniería:');
        $coasesor = (string) ($c['coasesor'] ?? $x['coasesor']);
        $pdf->texto('mediante el presente, solicita a usted la autorización para que el Prof. ' . ($coasesor ?: '________________') . ', participe como coasesor en el tema en mención, en el entendido de que, de ser aprobada, el coasesor no formará parte del jurado en la revisión del trabajo escrito, ni en la evaluación profesional.', '', 9.5);
        $pdf->recuadro('Las razones por las que se requiere la coasesoría, son las siguientes:', (string) ($c['razones'] ?? ''), 18);
        $bloques = [
            ['rol' => 'asesor', 'arriba' => 'ATENTAMENTE', 'nombre' => $x['asesor'] ?: ' ', 'cargo' => ['NOMBRE Y FIRMA DEL ASESOR']],
            ['rol' => 'coasesor', 'arriba' => 'Vo.Bo.', 'nombre' => $coasesor ?: ' ', 'cargo' => ['NOMBRE Y FIRMA DEL', 'COASESOR PROPUESTO']],
        ];
        if ($x['incorporada']) {
            $bloques[] = ['rol' => 'responsable', 'nombre' => ' ', 'cargo' => ['RESPONSABLE DEL ÁREA DE TITULACIÓN', 'Y SELLO DE LA INSTITUCIÓN']];
        }
        $cajas = $pdf->firmas($bloques);
        return [$pdf->Output('S'), $cajas];
    }

    public static function cartaPresentacion(array $t, array $e): array
    {
        $x = self::datos($t);
        $s = self::subdirector();
        $pdf = Hoja::nueva($x['folio'], false, 'v082025');
        $pdf->SetY(25);
        $pdf->texto('OFICIO EN PAPEL MEMBRETADO DE LA ESCUELA INCORPORADA', 'B', 8, 'L');
        $pdf->Ln(4);
        $pdf->titulo('De presentación de asesor y alumno(s) de la escuela incorporada a la Universidad Autónoma del Estado de México');
        $pdf->lugarFecha(null, 'Toluca, Méx.');
        $pdf->destinatario([$s['nombre'], $s['cargo'], 'DE LA FACULTAD DE INGENIERÍA', 'UNIVERSIDAD AUTÓNOMA DEL ESTADO DE MÉXICO']);
        $pdf->texto('Quienes suscriben, por este medio presentamos a Ud. ' . Persona::f($x['asesor_p'], 'al', 'a la') . ' Prof. ' . ($x['asesor'] ?: '<grado y nombre>') . ' como miembro de nuestro claustro académico y al pasante de ' . $x['licenciatura'] . ' ' . $x['nombre'] . ' con número de cuenta ' . $x['cuenta'] . ', como egresado de nuestro plantel, para que pueda realizar el registro del tema para evaluación profesional.', '', 10);
        $cajas = $pdf->firmas([
            ['rol' => 'responsable', 'arriba' => 'ATENTAMENTE', 'nombre' => ' ', 'cargo' => ['Nombre y firma del responsable', 'de la Escuela Incorporada']],
            ['rol' => 'jefe_incorporada', 'nombre' => ' ', 'cargo' => ['Jefe del Departamento de Titulación', 'de la Escuela Incorporada']],
        ]);
        return [$pdf->Output('S'), $cajas];
    }

    private static function cartaRepositorio(array $t, bool $excepcion): array
    {
        $x = self::datos($t);
        $c = $t['datos']['adicional'][$excepcion ? 'carta_excepcion_ri' : 'carta_ri'] ?? [];
        $pdf = Hoja::nueva($x['folio']);
        $pdf->lugarFecha(null, 'Toluca, México a');
        $pdf->texto('Espacio Académico, Subdirector Académico, Coordinador de Programa', '', 10, 'L');
        $pdf->texto('Presente', '', 10, 'L');
        $pdf->Ln(4);
        $pdf->texto($excepcion ? 'Carta de excepción para publicación en el RI' : 'Carta de autorización para publicación en el RI', 'B', 10.5, 'C');
        $pdf->Ln(2);
        $pdf->texto($excepcion ? 'Declaración de autoría original, bajo embargo y solicitud de evaluación de grado.' : 'Declaración de autoría original, libre de embargo, susceptible de ser depositado en el Repositorio Institucional y solicitud de evaluación de grado.', 'B', 10, $excepcion ? 'C' : 'J');
        $pdf->Ln(3);
        $coautor = trim((string) ($c['coautor'] ?? ''));
        $pdf->texto('Quien' . ($coautor !== '' ? 'es firmamos' : ' firma') . ' al calce, declar' . ($coautor !== '' ? 'amos' : 'o') . ' bajo protesta de decir verdad que ' . ($coautor !== '' ? 'somos autores' : 'soy ' . Persona::f(Persona::porId((int) ($t['alumno_id'] ?? 0)) ?? [], 'el autor', 'la autora')) . ' de la obra ' . mb_strtolower($x['modalidad_nombre']) . ' titulada «' . $x['titulo'] . '» y ' . ($coautor !== '' ? 'estamos' : 'estoy') . ' de acuerdo con la totalidad de su contenido' . ($excepcion ? '.' : ', manifiesto mi conformidad y mi autorización para que se publique en Repositorio Institucional de la Universidad Autónoma del Estado de México, con fines académicos y culturales en formato de acceso abierto, en los términos del Reglamento de Acceso Abierto, la Normatividad vigente emitida por la Institución y las legislaciones aplicables en la materia.'), '', 10);
        $pdf->Ln(2);
        if ($excepcion) {
            $pdf->texto('Que ' . mb_strtolower($x['modalidad_nombre']) . ' presentado es original y se encuentra en proceso de dictaminación o embargo en: ' . ((string) ($c['medio'] ?? '') ?: '________________') . ', del cual se adjunta captura de pantalla como evidencia y en su caso número de folio ' . ((string) ($c['folio_medio'] ?? '') ?: '________') . '.', '', 10);
            $pdf->Ln(2);
        }
        $pdf->texto('Así mismo, declaro que responderé de la autoría y originalidad de la obra de mérito y del ejercicio pacífico de los derechos que autorizo en este acto, manifiesto que no existe otra persona física o moral a la que pertenezca; por lo cual libero de toda responsabilidad a la Universidad Autónoma del Estado de México de cualquier demanda o reclamación que llegara a formular alguna persona física o moral que se considere con derecho sobre la obra, asumiendo todas las consecuencias legales y económicas a que hubiera lugar.', '', 10);
        $pdf->Ln(2);
        $pdf->texto($excepcion
            ? 'De igual forma permito que la Oficina de Conocimiento Abierto perteneciente a esta Máxima Casa de Estudios, realice lo propio para el almacenamiento, preservación y difusión de la obra únicamente de la portada, capitulado o índice, resumen y datos de contacto del autor y/o coautores.'
            : 'De igual forma permito que la Oficina de Conocimiento Abierto perteneciente a esta Máxima Casa de Estudios, realice lo propio para el almacenamiento, preservación y difusión de la obra, con fines académicos y culturales en formato de acceso abierto y sin fines de lucro.', '', 10);
        if ($excepcion) {
            $pdf->Ln(2);
            $pdf->texto('Autorizo una vez transcurrido el tiempo de dictaminación o embargo (' . ((string) ($c['fecha_libre'] ?? '') !== '' ? Hoja::fecha((string) $c['fecha_libre']) : '________________') . ') sea visible en el Repositorio Institucional a texto completo con fines académicos y culturales en formato de acceso abierto, sin ánimo de lucro.', '', 10);
        }
        $pdf->Ln(2);
        $pdf->texto('Firmo de conformidad y bajo protesta de decir verdad', '', 10, 'C');
        $bloques = [['rol' => 'pasante', 'nombre' => $x['nombre'], 'cargo' => ['No. de cuenta: ' . $x['cuenta']]]];
        if ($coautor !== '') {
            $bloques[] = ['rol' => 'coautor', 'nombre' => $coautor, 'cargo' => ['No. de cuenta: ' . (string) ($c['coautor_cuenta'] ?? '')]];
        }
        $cajas = $pdf->firmas($bloques, 14);
        $pdf->texto('Conozco y acepto los términos de privacidad de la Universidad Autónoma del Estado de México http://web.uaemex.mx/avisos/Aviso_Privacidad.pdf', '', 8, 'C', 4);
        return [$pdf->Output('S'), $cajas];
    }

    public static function cartaRi(array $t, array $e): array
    {
        return self::cartaRepositorio($t, false);
    }

    public static function cartaExcepcionRi(array $t, array $e): array
    {
        return self::cartaRepositorio($t, true);
    }

    public static function autorizacionEval(array $t, array $e): array
    {
        $x = self::datos($t);
        $s = self::subdirector();
        $c = $t['datos']['adicional']['autoriz_eval'] ?? [];
        $pdf = Hoja::nueva($x['folio'], true, 'v082025');
        $pdf->titulo('Solicitud de autorización de evaluación profesional');
        $pdf->lugarFecha();
        $pdf->destinatario([$s['nombre'], $s['secretario'] . ' DE LOS HH. CONSEJOS ACADÉMICO Y DE', 'GOBIERNO DE LA FACULTAD DE INGENIERÍA DE LA UAEM']);
        $pdf->texto('Quien suscribe, ' . $x['nombre'] . ', con número de cuenta ' . $x['cuenta'] . ', y pasante de la Licenciatura de ' . $x['licenciatura'] . ', de ' . ((string) ($c['institucion'] ?? '') ?: '________________') . ' incorporada a la Universidad Autónoma del Estado de México, mediante el presente, solicita a usted la autorización de la evaluación profesional en la opción de:', '', 9.5);
        $pdf->casillas(Hoja::opcionesTrabajo() + ['egel' => 'Examen General de Egreso de la Licenciatura', 'aprovechamiento' => 'Aprovechamiento Académico', 'creditos' => 'Créditos en Estudios Avanzados'], $x['modalidad'], 6.0, 2, 8.5);
        $pdf->texto('para que en cumplimiento con el Título Tercero, Capítulo Primero, Fracción IV del Artículo 84 o 86, del Reglamento de Evaluación Profesional de la U. A. E. M., los HH. Consejos Académico y de Gobierno puedan dictaminar al respecto. Adjunto copias de la documentación que prueba el cumplimiento de los requisitos que corresponden a la opción de evaluación profesional indicada.', '', 9);
        $pdf->Ln(1);
        $pdf->texto('Asimismo, me doy por enterado que, para proceder con la asignación de presidente de jurado, además de los requisitos establecidos, debo entregar copia de la resolución a esta solicitud en Apoyo a Titulación de la Facultad de Ingeniería de la UAEM.', '', 9);
        $cajas = $pdf->firmas([
            ['rol' => 'pasante', 'arriba' => 'ATENTAMENTE', 'nombre' => $x['nombre'], 'cargo' => ['NOMBRE Y FIRMA DEL PASANTE']],
            ['rol' => 'responsable', 'arriba' => 'Vo.Bo.', 'nombre' => ' ', 'cargo' => ['NOMBRE Y FIRMA DEL RESPONSABLE', 'DEL ÁREA DE TITULACIÓN Y SELLO DE LA INSTITUCIÓN']],
        ]);
        return [$pdf->Output('S'), $cajas];
    }

    // ------------------------------------------------------------------ internos

    /**
     * Formato «Revisión de protocolo» (revisión de cada integrante y dictamen del comité).
     * $e: nombre, cuenta, titulo, modalidad_nombre, fecha_recepcion, fecha_entrega, revision (1|2…), dictamen, requiere_coord, observaciones, firmante.
     */
    public static function revisionProtocolo(array $t, array $e): array
    {
        $pdf = Hoja::nueva((string) ($t['folio'] ?? ''), false);
        $pdf->SetY(14);
        $img = dirname(__DIR__) . '/plantillas/img';
        if (is_file($img . '/uaem.jpg')) {
            $pdf->Image($img . '/uaem.jpg', 22, 10, 22);
        }
        if (is_file($img . '/facultad.jpg')) {
            $pdf->Image($img . '/facultad.jpg', 168, 10, 26);
        }
        $pdf->SetFont('Helvetica', 'B', 10);
        foreach (['UNIVERSIDAD AUTÓNOMA DEL ESTADO DE MÉXICO', 'FACULTAD DE INGENIERÍA'] as $l) {
            $pdf->Cell(0, 5, Hoja::l1($l), 0, 1, 'C');
        }
        $pdf->Ln(3);
        $pdf->Cell(0, 5, Hoja::l1('REVISIÓN DE PROTOCOLO'), 0, 1, 'C');
        $pdf->SetY(40);
        $pdf->datos([
            ['NOMBRE DEL PASANTE:', (string) ($e['nombre'] ?? '')], ['No. DE CUENTA:', (string) ($e['cuenta'] ?? '')],
            ['TÍTULO DEL TRABAJO:', (string) ($e['titulo'] ?? '')], ['MODALIDAD:', (string) ($e['modalidad_nombre'] ?? '')],
            ['FECHA DE RECEPCIÓN:', (string) ($e['fecha_recepcion'] ?? '')], ['FECHA DE ENTREGA:', (string) ($e['fecha_entrega'] ?? '')],
            ['No. DE REVISIÓN:', (string) ($e['revision'] ?? '1') . 'ª'],
        ], 48);
        $pdf->texto('DICTAMEN DEL PROTOCOLO', 'B', 10, 'C');
        $pdf->Ln(1);
        $si = ($e['dictamen'] ?? '') === 'aprobado';
        $pdf->texto('SE APRUEBA: ( ' . ($si ? 'X' : '  ') . ' )      NO SE APRUEBA: ( ' . (!$si ? 'X' : '  ') . ' )      REQUIERE REVISIÓN DEL COORDINADOR: SÍ ( ' . (($e['requiere_coord'] ?? '') === 'si' ? 'X' : '  ') . ' )  NO ( ' . (($e['requiere_coord'] ?? '') !== 'si' ? 'X' : '  ') . ' )', '', 9, 'C');
        $pdf->Ln(3);
        $pdf->recuadro('OBSERVACIONES', (string) ($e['observaciones'] ?? ''), 60);
        $cajas = $pdf->firmas([['rol' => 'firmante', 'arriba' => 'ATENTAMENTE', 'nombre' => (string) ($e['firmante'] ?? ' '), 'cargo' => ['COMISIÓN DE EVALUACIÓN PROFESIONAL DE TRABAJOS DE TITULACIÓN']]], 14);
        return [$pdf->Output('S'), $cajas];
    }

    /** Lista de requisitos de la etapa de documentación, con el estado de cada documento en el trámite. */
    public static function requisitos(array $t, array $e): array
    {
        $x = self::datos($t);
        $pdf = Hoja::nueva($x['folio']);
        $pdf->titulo('Requisitos para la evaluación profesional', $x['modalidad_nombre']);
        $pdf->texto('Pasante: ' . $x['nombre'] . ' (No. de cuenta ' . $x['cuenta'] . ') · ' . $x['licenciatura'], '', 9.5);
        $pdf->texto('Relación de documentos que se deben entregar para asignar fecha y presentar la evaluación profesional; corte al ' . Hoja::fecha() . '.', '', 9.5);
        $pdf->Ln(2);
        $etiquetas = ['aprobado' => 'Validado', 'en_revision' => 'En validación', 'rechazado' => 'Con observaciones', 'no_aplica' => 'No aplica', 'firmas' => 'En firmas'];
        $docs = !empty($t['id']) ? TitulacionRepository::documentosVigentes((int) $t['id']) : [];
        foreach (Catalogo::etapas((string) $t['modalidad'], $t['variante'] ?: null) as $etapa) {
            if (!in_array($etapa['clave'], ['final', 'protesta'], true)) {
                continue;
            }
            foreach ($etapa['documentos'] ?? [] as $d) {
                $f = $docs[$etapa['clave'] . '|' . $d['clave']] ?? null;
                $estado = $f ? ($etiquetas[!empty($f['pendiente_firmas']) ? 'firmas' : $f['estado']] ?? $f['estado']) : 'Pendiente';
                $y = $pdf->GetY();
                $pdf->SetFont('Helvetica', '', 9);
                $pdf->SetX(25);
                $pdf->MultiCell(130, 4.6, Hoja::l1('• ' . $d['nombre'] . ($d['condicion'] ? ' (' . $d['condicion'] . ')' : '')), 0, 'L');
                $fin = $pdf->GetY();
                $pdf->SetXY(157, $y);
                $pdf->SetFont('Helvetica', 'B', 8.5);
                $pdf->Cell(34, 4.6, Hoja::l1($estado), 0, 0, 'R');
                $pdf->SetY($fin + 1);
            }
            foreach ($etapa['presencial'] ?? [] as $l) {
                $pdf->SetFont('Helvetica', 'I', 8.5);
                $pdf->MultiCell(0, 4.4, Hoja::l1('En físico: ' . $l), 0, 'L');
            }
        }
        return [$pdf->Output('S'), []];
    }

    private static function l(string $s): string
    {
        return Hoja::l1($s);
    }
}
