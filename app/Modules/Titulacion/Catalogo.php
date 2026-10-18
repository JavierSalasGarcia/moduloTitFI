<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

/**
 * Catálogo de modalidades de titulación de licenciatura, etapas del asistente,
 * documentos requeridos y formatos que el sistema genera. Refleja los formatos
 * "Requisitos…" vigentes (v082025) y las páginas fi.uaemex.mx/titulacion; cuando
 * el departamento cambie un requisito, es el único archivo PHP que se edita.
 */
final class Catalogo
{
    public const COSTOS = [
        'evaluacion_trabajo' => 3024,
        'toma_protesta' => 230,
        'titulo' => 1187,
        'constancia' => 100,
    ];

    /** @return array<string,array{nombre:string,siglas:array<int,string>}> */
    public static function carreras(): array
    {
        return [
            'civil' => ['nombre' => 'Ingeniería Civil', 'siglas' => ['ICI']],
            'mecanica' => ['nombre' => 'Ingeniería Mecánica', 'siglas' => ['IME']],
            'computacion' => ['nombre' => 'Ingeniería en Computación', 'siglas' => ['ICO']],
            'electronica' => ['nombre' => 'Ingeniería en Electrónica', 'siglas' => ['IEL']],
            'sistemas' => ['nombre' => 'Ingeniería en Sistemas Energéticos Sustentables', 'siglas' => ['ISES']],
        ];
    }

    /** @return array<string,string> */
    public static function sistemas(): array
    {
        return ['fi' => 'FI-UAEMéx', 'ui' => 'UI-CUI', 'cesu' => 'CESU', 'cutv' => 'CUTV', 'uif' => 'UIF'];
    }

    public static function carreraPorSiglas(?string $siglas): string
    {
        foreach (self::carreras() as $clave => $c) {
            if (in_array(strtoupper((string) $siglas), $c['siglas'], true)) {
                return $clave;
            }
        }
        return '';
    }

    public const MESES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
        'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /** @return array<string,array<string,mixed>> */
    public static function modalidades(): array
    {
        return [
            'aprovechamiento' => [
                'nombre' => 'Aprovechamiento académico', 'tipo' => 'sin_trabajo', 'art' => 'Art. 14, REP',
                'resumen' => 'Reconoce el esfuerzo y la dedicación durante toda la trayectoria escolar.',
                'requisitos' => [
                    'Promedio general mayor o igual a 8.5.',
                    'Haber cursado el plan de estudios sin interrupciones.',
                    'Sin calificaciones reprobatorias ni anotaciones literales.',
                    'Haber cubierto al menos el 50 % de los créditos en la Universidad (si hubo revalidación o convalidación).',
                    'Solicitar la opción por escrito dentro del primer año posterior a concluir la carrera.',
                    'No haber sido sancionado por faltas a la responsabilidad universitaria.',
                ],
            ],
            'egel' => [
                'nombre' => 'Examen General de Egreso (EGEL)', 'tipo' => 'sin_trabajo', 'art' => 'Art. 29, REP',
                'resumen' => 'Presentas el EGEL Plus del CENEVAL en sede UAEM y obtienes Desempeño Satisfactorio o Sobresaliente.',
                'requisitos' => [
                    'Tramitar la solicitud en el Departamento de Evaluación Profesional antes de presentar el examen.',
                    'Presentar el EGEL en sede UAEM.',
                    'Obtener testimonio de Desempeño Satisfactorio o Sobresaliente.',
                    'Si el resultado es «Aún no satisfactorio» cuenta como evaluación aplazada; puedes pedir una segunda y última vez después de seis meses.',
                ],
                'variantes' => [
                    'pagando_menos' => 'Convocatoria «Pagando menos» (pagas el 50 %)',
                    'nacional' => 'Convocatoria nacional',
                ],
            ],
            'creditos' => [
                'nombre' => 'Créditos en estudios avanzados', 'tipo' => 'sin_trabajo', 'art' => 'Art. 22, REP',
                'resumen' => 'Acreditas módulos o unidades de un diplomado superior, especialidad o maestría afín a tu licenciatura.',
                'requisitos' => [
                    'Presentar la solicitud y obtener la autorización de la Subdirección Académica antes de inscribirte a los estudios.',
                    'Que la Coordinación de Docencia dictamine la afinidad con tu licenciatura.',
                    'Contar con carta de aceptación y con el plan de estudios oficial aprobado por los Consejos.',
                    'Certificar el 100 % de los créditos (diplomado superior o especialidad) o el 75 % (maestría).',
                ],
            ],
            'articulo' => [
                'nombre' => 'Artículo especializado para publicar en revista indizada', 'tipo' => 'con_trabajo', 'art' => 'Art. 16, REP',
                'resumen' => 'Artículo original e inédito, enviado a una revista inscrita en índices validados.',
                'requisitos' => [
                    'Artículo original e inédito, con autoría del pasante.',
                    'Tema relacionado con el plan de estudios o con un área científica o técnica de la profesión.',
                    'Evidencia de que la revista está en alguno de los índices del Art. 16 (e-Revist@s, Redalyc, Scielo, Scopus, CONACYT, Thomson-Reuters, JCR).',
                    'Enviar el artículo hasta contar con el voto aprobatorio del asesor.',
                    'Reporte de similitud iThenticate no mayor al 20 %.',
                ],
                'similitud' => 20,
            ],
            'ensayo' => [
                'nombre' => 'Ensayo', 'tipo' => 'con_trabajo', 'art' => 'REP',
                'resumen' => 'Exposición crítica y propositiva de un tema, con postura intelectual propia.',
                'requisitos' => ['Reporte de similitud iThenticate no mayor al 15 %.'],
                'similitud' => 15,
            ],
            'memoria' => [
                'nombre' => 'Memoria de experiencia laboral', 'tipo' => 'con_trabajo', 'art' => 'Art. 33, REP',
                'resumen' => 'Recopilación y resultados de una experiencia profesional en un ámbito reconocido.',
                'requisitos' => [
                    'Constancia de la organización con al menos dos años continuos de servicios, o participación en un proyecto desde su inicio hasta su conclusión.',
                    'El ingreso a los servicios o al proyecto debe ser posterior a la conclusión del plan de estudios.',
                    'Reporte de similitud iThenticate no mayor al 20 %.',
                ],
                'similitud' => 20,
            ],
            'aplicacion' => [
                'nombre' => 'Reporte de aplicación de conocimientos', 'tipo' => 'con_trabajo', 'art' => 'REP',
                'resumen' => 'Propuesta de solución a un problema determinado, derivada de lo aprendido en la carrera.',
                'requisitos' => ['Reporte de similitud iThenticate no mayor al 20 %.'],
                'similitud' => 20,
            ],
            'autoempleo' => [
                'nombre' => 'Reporte de autoempleo profesional', 'tipo' => 'con_trabajo', 'art' => 'Art. 52 y 53, REP',
                'resumen' => 'Informe sobre la constitución y desarrollo de una unidad económica formal.',
                'requisitos' => [
                    'Unidad económica creada formal y legalmente (evidencia expedida por autoridad competente).',
                    'Participación como propietario, socio, empresario o empleador durante al menos un año, concluido antes de iniciar el procedimiento.',
                    'Individual o hasta tres pasantes del mismo programa.',
                    'Reporte de similitud iThenticate no mayor al 20 %.',
                ],
                'similitud' => 20,
            ],
            'residencia' => [
                'nombre' => 'Reporte de residencia de investigación', 'tipo' => 'con_trabajo', 'art' => 'Art. 59, REP',
                'resumen' => 'Informe de actividades de apoyo a la investigación bajo la dirección de un investigador activo en el SNI.',
                'requisitos' => [
                    'Proyecto registrado en un centro o instituto de investigación de prestigio, con líneas afines a tu formación.',
                    'Carta de estancia de al menos seis meses, expedida por la máxima autoridad del centro o instituto.',
                    'Reporte de similitud iThenticate no mayor al 20 %.',
                ],
                'similitud' => 20,
            ],
            'tesina' => [
                'nombre' => 'Tesina', 'tipo' => 'con_trabajo', 'art' => 'Art. 73, REP',
                'resumen' => 'Investigación documental de carácter monográfico, con aportación al estado del arte.',
                'requisitos' => ['Reporte de similitud iThenticate no mayor al 20 %.'],
                'similitud' => 20,
            ],
            'tesis' => [
                'nombre' => 'Tesis', 'tipo' => 'con_trabajo', 'art' => 'Art. 80, REP',
                'resumen' => 'Investigación documental, experimental, empírica o teórica; individual o de hasta tres pasantes.',
                'requisitos' => [
                    'Extensión mínima de 60 cuartillas (individual) a interlineado máximo de 1.5.',
                    'Reporte de similitud iThenticate no mayor al 20 %.',
                ],
                'similitud' => 20,
            ],
        ];
    }

    public static function modalidad(string $clave): ?array
    {
        return self::modalidades()[$clave] ?? null;
    }

    /** Documentos descargables en blanco (Word/PDF oficiales) que acompañan a una etapa. */
    public static function archivosBlanco(): array
    {
        return [
            'prorroga' => ['Solicitud de prórroga del tema', 'SolicitudProrrogaTrabEscrito.pdf'],
            'cambio_asesor' => ['Cambio de asesor', 'ProtocoloCambioAsesor.docx'],
            'baja' => ['Baja de tema y/o sustentante, o cambio de sustentante', 'ProtocoloBajaTemaSustentante.docx'],
            'cambios' => ['Cambios al protocolo aprobado', 'ProtocoloCambios.docx'],
            'coasesoria' => ['Solicitud de coasesoría', 'SolicitudCoasesoria.pdf'],
            'carta_presentacion' => ['Carta de presentación (solo instituciones incorporadas)', 'CartaPresentacion_v082025.docx'],
            'carta_ri' => ['Carta de autorización para publicar en el Repositorio Institucional', 'CartaDeAutorizacionRI.pdf'],
            'carta_ri_2' => ['Carta de autorización para publicar en el Repositorio (2 tesistas)', 'CartaDeAutorizacionRI2Tesistas.pdf'],
            'carta_excepcion_ri' => ['Carta de excepción para publicación en el Repositorio', 'CartaDeExcepcionRI.pdf'],
            'carta_excepcion_ri_2' => ['Carta de excepción para publicación (2 tesistas)', 'CartaDeExcepcionRI2Tesistas.pdf'],
            'rep' => ['Reglamento de Evaluación Profesional', 'REP.pdf'],
            'privacidad' => ['Aviso de privacidad', 'avisoDePrivacidadFI.pdf'],
        ];
    }

    // ------------------------------------------------------------------ formatos

    /** Formatos que el sistema llena. 'pdf' usa formatos/pdf_specs.json; 'docx' se arma por modalidad. */
    public static function formatos(): array
    {
        return Configuracion::aplicarFormatos(self::formatosBase());
    }

    private static function formatosBase(): array
    {
        return [
            'aprovechamiento' => ['tipo' => 'pdf', 'nombre' => 'Solicitud de evaluación profesional por Aprovechamiento académico', 'firma' => 'el pasante'],
            'egel_50' => ['tipo' => 'pdf', 'nombre' => 'Solicitud de evaluación profesional por EGEL (convocatoria «Pagando menos»)', 'firma' => 'el pasante'],
            'egel_nacional' => ['tipo' => 'pdf', 'nombre' => 'Solicitud de evaluación profesional por EGEL (convocatoria nacional)', 'firma' => 'el pasante'],
            'creditos' => ['tipo' => 'pdf', 'nombre' => 'Solicitud de evaluación profesional por Créditos en estudios avanzados', 'firma' => 'el pasante'],
            'datos_titulo' => ['tipo' => 'pdf', 'nombre' => 'Formato de datos del interesado para expedición de título', 'firma' => 'el pasante, de su puño y letra'],
            'protocolo' => ['tipo' => 'docx', 'nombre' => 'Solicitud de aprobación de tema y protocolo', 'firma' => 'el pasante y el asesor (Vo. Bo.)'],
            'revision' => ['tipo' => 'pdf', 'nombre' => 'Solicitud de revisión del trabajo escrito', 'firma' => 'el pasante y el asesor (Vo. Bo.)'],
            'sustentacion' => ['tipo' => 'pdf', 'nombre' => 'Solicitud de sustentación y Voto aprobatorio', 'firma' => 'el pasante y el asesor; el voto lo firman asesor y revisores'],
            'trabajo_eval' => ['tipo' => 'pdf', 'nombre' => 'Solicitud para presentar la evaluación profesional mediante trabajo escrito', 'firma' => 'el pasante'],
            'art5' => ['tipo' => 'pdf', 'nombre' => 'Solicitud para continuar el trámite fuera de plazo (Art. 5, REP)', 'firma' => 'el pasante (y asesor si hay trabajo escrito)'],
        ];
    }

    // -------------------------------------------------------------- captura base

    /** Campos que se piden una sola vez al iniciar el trámite. */
    public static function camposPasante(): array
    {
        $carreras = [];
        foreach (self::carreras() as $k => $c) {
            $carreras[$k] = $c['nombre'];
        }
        return [
            ['k' => 'nombre', 'label' => 'Nombre completo (con acentos, tal como aparecerá en el título)', 'tipo' => 'texto', 'max' => 120, 'req' => true],
            ['k' => 'cuenta', 'label' => 'Número de cuenta', 'tipo' => 'texto', 'max' => 12, 'req' => true, 'patron' => '^[0-9]{6,10}$', 'ayuda' => 'Solo dígitos.'],
            ['k' => 'carrera', 'label' => 'Licenciatura', 'tipo' => 'select', 'opciones' => $carreras, 'req' => true],
            ['k' => 'sistema', 'label' => 'Sistema', 'tipo' => 'select', 'opciones' => self::sistemas(), 'req' => true, 'ayuda' => 'Facultad (FI-UAEMéx), unidad incorporada o centro universitario.'],
            ['k' => 'anio_ingreso', 'label' => 'Año de ingreso', 'tipo' => 'texto', 'max' => 4, 'req' => true, 'patron' => '^(19|20)[0-9]{2}$'],
            ['k' => 'anio_egreso', 'label' => 'Año de egreso', 'tipo' => 'texto', 'max' => 4, 'req' => true, 'patron' => '^(19|20)[0-9]{2}$'],
            ['k' => 'telefono', 'label' => 'Teléfono', 'tipo' => 'tel', 'max' => 20, 'req' => true],
            ['k' => 'correo_inst', 'label' => 'Correo institucional', 'tipo' => 'email', 'max' => 120, 'req' => true],
            ['k' => 'correo_pers', 'label' => 'Correo personal', 'tipo' => 'email', 'max' => 120, 'req' => true],
            ['k' => 'ocupacion', 'label' => 'Ocupación actual', 'tipo' => 'texto', 'max' => 120, 'req' => false],
            ['k' => 'fecha_nacimiento', 'label' => 'Fecha de nacimiento', 'tipo' => 'fecha', 'req' => false],
            ['k' => 'acentos', 'label' => 'Mi nombre o apellidos llevan acentos', 'tipo' => 'select', 'opciones' => ['Sí' => 'Sí', 'No' => 'No'], 'req' => true],
        ];
    }

    // ------------------------------------------------------------------ protocolo

    /**
     * Secciones del protocolo por modalidad. 'h' es el texto exacto del encabezado
     * en el Word oficial; el motor lo localiza y reemplaza la guía por la respuesta.
     */
    public static function protocolo(string $modalidad): ?array
    {
        $titulo = fn(string $h = 'Título', string $label = 'Título del trabajo') => ['k' => 'titulo', 'h' => $h, 'label' => $label, 'tipo' => 'area', 'filas' => 2, 'max' => 400, 'req' => true, 'tema' => true, 'ayuda' => 'Especifica claramente el qué, es decir, el objeto de estudio.'];
        $asesor = [
            ['k' => 'asesor', 'h' => 'Asesor', 'label' => 'Asesor (adscrito a la UAEM)', 'tipo' => 'texto', 'max' => 160, 'req' => true],
            ['k' => 'coasesor', 'h' => 'Coasesor', 'label' => 'Coasesor (opcional)', 'tipo' => 'texto', 'max' => 160, 'req' => false, 'vacio' => 'No aplica'],
        ];
        $area = ['k' => 'area', 'h' => 'Área académica', 'label' => 'Área académica', 'tipo' => 'texto', 'max' => 160, 'req' => true, 'ayuda' => 'La que corresponda según la Coordinación de Docencia.'];
        $a = fn(string $k, string $h, string $label, string $ayuda = '', bool $req = true) => ['k' => $k, 'h' => $h, 'label' => $label, 'tipo' => 'area', 'filas' => 5, 'max' => 4000, 'req' => $req, 'ayuda' => $ayuda];
        $refs = $a('referencias', 'Referencias y/o fuentes de información', 'Referencias y/o fuentes de información', 'Fuentes académicas oficiales y sistema de referencias (Harvard, Chicago, Vancouver, APA, numerado).');
        $crono = fn(string $h) => ['k' => 'cronograma', 'h' => $h, 'label' => 'Cronograma de actividades', 'tipo' => 'cronograma', 'req' => true, 'ayuda' => 'Marca los bimestres (hasta 12) en que realizarás cada actividad. El tema tiene vigencia de dos años.'];
        $objetivo = fn(string $h = 'Objetivo general', string $ayuda = 'Incluye un verbo en infinitivo (analizar, construir, aplicar).') => $a('objetivo', $h, 'Objetivo general', $ayuda);

        $definiciones = [
            'tesis' => ['ProtocoloTesis.docx', [$titulo(), ...$asesor, $area,
                $a('planteamiento', 'Planteamiento del problema', 'Planteamiento del problema', 'Contexto o antecedente, actualidad y relevancia, vinculación con el área de estudio.'),
                $a('justificacion', 'Justificación', 'Justificación', 'Importancia, impacto, beneficios, alcances y limitaciones.'),
                $a('hipotesis', 'Hipótesis', 'Hipótesis', 'Lo que se busca probar; explicaciones tentativas del fenómeno.'),
                $objetivo(), $a('metodologia', 'Metodología', 'Metodología', 'Técnicas y métodos para el desarrollo de la investigación.'), $refs, $crono('Cronograma de actividades')]],
            'tesina' => ['ProtocoloTesina.docx', [$titulo(), ...$asesor, $area,
                $a('planteamiento', 'Planteamiento de la investigación documental o traducción crítica', 'Planteamiento de la investigación documental o traducción crítica', 'Descripción general del tema, actualidad, relevancia y vinculación con el área de estudio.'),
                $a('justificacion', 'Justificación', 'Justificación'),
                $objetivo(), $a('metodologia', 'Metodología', 'Metodología', 'Técnicas y métodos de investigación documental.'), $refs, $crono('Cronograma de actividades')]],
            'ensayo' => ['ProtocoloEnsayo.docx', [$titulo('Tema', 'Tema del ensayo'), ...$asesor, $area,
                $a('descripcion', 'Descripción del tema', 'Descripción del tema', 'Descripción general, actualidad, relevancia y vinculación con el área de estudio.'),
                $a('justificacion', 'Justificación', 'Justificación', 'Necesidad de abordar el tema: importancia, impacto y beneficios.'),
                $refs, $crono('Planificación de actividades')]],
            'memoria' => ['ProtocoloMemoria.docx', [$titulo(), ['k' => 'periodo', 'h' => 'Periodo de participación', 'label' => 'Periodo de participación', 'tipo' => 'texto', 'max' => 160, 'req' => true, 'ayuda' => 'Por ejemplo: marzo de 2021 a junio de 2024.'],
                ...$asesor, $area,
                $a('descripcion', 'Descripción de la experiencia laboral', 'Descripción de la experiencia laboral', 'Actividades laborales, problema que abordará la memoria, cómo te involucraste y vinculación con el área de estudio.'),
                $a('justificacion', 'Justificación', 'Justificación'),
                $objetivo('Objetivo general', 'Qué experiencia se documentará y el procedimiento de solución.'), $refs, $crono('Planificación de actividades')]],
            'aplicacion' => ['ProtocoloAplicacion.docx', [$titulo(), ...$asesor, $area,
                $a('planteamiento', 'Planteamiento del problema', 'Planteamiento del problema', 'Problemática abordada, actualidad, relevancia y vinculación con el área de estudio.'),
                $a('justificacion', 'Justificación', 'Justificación'),
                $objetivo('Objetivo general', 'Qué se logró con la aplicación del conocimiento.'), $a('metodologia', 'Metodología', 'Metodología', 'Técnicas y métodos para alcanzar el objetivo.'), $refs, $crono('Cronograma de actividades')]],
            'autoempleo' => ['ProtocoloAutoempleo.docx', [$titulo('Título', 'Título (nombre de la unidad económica)'),
                ['k' => 'periodo', 'h' => 'Periodo de participación (mínimo un año)', 'label' => 'Periodo de participación (mínimo un año)', 'tipo' => 'texto', 'max' => 160, 'req' => true],
                ...$asesor, $area,
                $a('planteamiento', 'Planteamiento', 'Planteamiento', 'Objeto, nombre o razón social, domicilio fiscal, giro, número de accionistas y de empleados, tipo de participación (propietario, socio, empresario o empleador).'),
                $a('justificacion', 'Justificación', 'Justificación'),
                $objetivo('Objetivo general', 'Actividades realizadas dentro de la unidad económica.'), $refs, $crono('Planificación de actividades')]],
            'residencia' => ['ProtocoloResidencia.docx', [
                ['k' => 'investigador', 'h' => 'Nombre del profesor investigador responsable del proyecto', 'label' => 'Profesor investigador responsable del proyecto (SNI u organismo similar)', 'tipo' => 'texto', 'max' => 160, 'req' => true],
                ...$asesor, $area,
                ['k' => 'proyecto', 'h' => 'Título del proyecto de investigación', 'label' => 'Título del proyecto de investigación', 'tipo' => 'area', 'filas' => 2, 'max' => 400, 'req' => true],
                ['k' => 'clave_proyecto', 'h' => 'Clave de registro del proyecto', 'label' => 'Clave de registro del proyecto', 'tipo' => 'texto', 'max' => 80, 'req' => true],
                $a('descripcion', 'Descripción general del proyecto de investigación', 'Descripción general del proyecto de investigación', 'Antecedentes y objetivo del proyecto al que te incorporaste.'),
                ['k' => 'titulo', 'h' => 'Título del reporte de residencia de investigación', 'label' => 'Título del reporte de residencia', 'tipo' => 'area', 'filas' => 2, 'max' => 400, 'req' => true, 'tema' => true],
                $objetivo('Objetivo general de la residencia', 'Verbo en infinitivo medible u observable.'), $crono('Planificación de actividades')],
                ['Fecha de inicio de la residencia:' => 'fecha_inicio', 'Fecha de conclusión:' => 'fecha_fin'],
                [['k' => 'fecha_inicio', 'label' => 'Fecha de inicio de la residencia', 'tipo' => 'fecha', 'req' => true], ['k' => 'fecha_fin', 'label' => 'Fecha de conclusión', 'tipo' => 'fecha', 'req' => true]]],
            'articulo' => ['ProtocoloArticulo.docx', [$titulo('Título tentativo del artículo especializado', 'Título tentativo del artículo'), $area, ...$asesor,
                ['k' => 'revista', 'h' => 'Nombre de la revista indizada', 'label' => 'Nombre de la revista indizada', 'tipo' => 'texto', 'max' => 200, 'req' => true],
                ['k' => 'indice', 'h' => 'Nombre del índice al que pertenece', 'label' => 'Índice al que pertenece (nombre y URL)', 'tipo' => 'area', 'filas' => 2, 'max' => 400, 'req' => true],
                $a('resumen', 'Descripción general (resumen) del artículo especializado', 'Descripción general (resumen) del artículo', 'Contexto o antecedentes, planteamiento del problema, objetivos y metodología.'),
                $refs]],
        ];
        if (!isset($definiciones[$modalidad])) {
            return null;
        }
        $def = $definiciones[$modalidad];
        $campos = $def[1];
        $etiquetasExtra = $def[2] ?? [];
        if (!empty($def[3])) {
            // Campos que se llenan en celdas «etiqueta: valor» además de las secciones.
            $campos = [...$campos, ...$def[3]];
        }
        return ['plantilla' => $def[0], 'campos' => $campos, 'etiquetas_extra' => $etiquetasExtra];
    }

    // ------------------------------------------------------------------- etapas

    /** Documento que sube el pasante. */
    private static function doc(string $clave, string $nombre, string $nota = '', ?string $condicion = null, ?string $formato = null): array
    {
        return ['clave' => $clave, 'nombre' => $nombre, 'nota' => $nota, 'condicion' => $condicion, 'formato' => $formato,
            'activo' => true, 'fisico' => false, 'cotejo' => false, 'firma_cotejo' => 'sello'];
    }

    private static function docsIdentidad(bool $ingles, bool $dictamen): array
    {
        $d = [
            self::doc('cert_bachillerato', 'Certificado de estudios de bachillerato'),
            self::doc('const_autenticacion', 'Constancia de autenticación de bachillerato', 'Se tramita en la escuela donde cursaste el bachillerato.', 'Solo si tus estudios de bachillerato no fueron en instituciones dependientes o incorporadas a la UAEM.'),
            self::doc('acta_nacimiento', 'Acta de nacimiento', 'Copia certificada vigente (del año en curso).'),
            self::doc('curp', 'CURP', 'Debe mostrar la leyenda «Validada por el Registro Civil».'),
        ];
        if ($ingles) {
            $d[] = self::doc('const_ingles', 'Constancia o certificado de aprobación del idioma inglés', '', 'Solo planes 98 en adelante, si en tu certificado no aparecen Inglés C1 e Inglés C2.');
        }
        $d[] = self::doc('no_adeudo_biblioteca_fi', 'Constancia de no adeudo a la Biblioteca de la Facultad', 'El recibo de pago se tramita en Control Escolar de la Facultad; la constancia, en la Biblioteca.');
        $d[] = self::doc('no_adeudo_biblioteca_central', 'Constancia de no adeudo a la Biblioteca Central');
        $d[] = self::doc('contra_recibo_donacion', 'Contra-recibo de la donación de libros a la Biblioteca de la Facultad');
        $d[] = self::doc('no_adeudo_control_escolar', 'Constancia de no adeudo a la Dirección de Control Escolar de la UAEM', 'Se solicita en el Portal de alumnos: controlescolar.uaemex.mx.');
        $d[] = self::doc('recibo_no_adeudo_uaem', 'Recibo de no adeudo a la UAEM', 'Se solicita en el Portal de alumnos de Control Escolar.');
        $d[] = self::doc('datos_titulo', 'Formato de datos personales (para el título)', 'Descárgalo llenado desde este sistema, imprímelo, escribe tu nombre a mano y fírmalo.', null, 'datos_titulo');
        $d[] = self::doc('comprobante_egresados', 'Comprobante del sistema de Seguimiento de Egresados', 'Se obtiene en alumniversitario.uaemex.mx/1dae.php.');
        if ($dictamen) {
            $d[] = self::doc('dictamen_consejos', 'Dictamen de autorización de los HH. Consejos', 'Se solicita en la Subdirección Académica con el formato del Art. 5.', 'Solo si excediste el plazo de 10 años para presentar la evaluación profesional (Art. 5, REP).', 'art5');
        }
        return $d;
    }

    private static function presencialBase(bool $conTrabajo): array
    {
        $l = [
            'Fotografías de semitítulo: 3, blanco y negro, fondo blanco, papel mate, de frente y con retoque.',
            'Fotografías óvalo miñón: 6, blanco y negro, fondo gris claro, papel mate, de frente y con retoque.',
            'Copias en tamaño carta, legibles y por ambos lados cuando corresponda; los originales se entregan en Apoyo a Titulación.',
        ];
        if ($conTrabajo) {
            array_unshift($l, 'Seis ejemplares del trabajo escrito (4 impresos o en PDF en CD y 2 en PDF en CD, con el mismo empastado).');
        }
        return $l;
    }

    /**
     * Etapas del asistente para una modalidad. Tipos de etapa:
     *  captura       el pasante llena datos (y genera formatos)
     *  confirmacion  el pasante confirma un hito propio
     *  personal      completa exclusivamente el personal de Titulación
     * Cualquier etapa puede traer 'documentos' (los valida el personal) y 'personal'.
     */
    public static function etapas(string $modalidad, ?string $variante = null): array
    {
        return Configuracion::aplicarEtapas(self::etapasBase($modalidad, $variante), $modalidad);
    }

    /** Etapas tal como las define el catálogo, sin los cambios de configuración. */
    public static function etapasBase(string $modalidad, ?string $variante = null): array
    {
        $m = self::modalidad($modalidad);
        if ($m === null) {
            return [];
        }
        $datos = [
            'clave' => 'datos', 'titulo' => 'Tus datos', 'tipo' => 'captura',
            'descripcion' => 'Captura una sola vez tus datos; con ellos se llenan automáticamente todos los formatos del trámite.',
            'campos' => self::camposPasante(),
        ];
        return $m['tipo'] === 'sin_trabajo'
            ? self::etapasSinTrabajo($modalidad, $variante, $datos)
            : self::etapasConTrabajo($modalidad, $datos);
    }

    private static function etapasSinTrabajo(string $modalidad, ?string $variante, array $datos): array
    {
        $vez = ['k' => 'vez', 'label' => 'La solicitud se presenta por', 'tipo' => 'select', 'opciones' => ['primera' => 'Primera vez', 'segunda' => 'Segunda vez'], 'req' => true];
        $etapas = [$datos];

        if ($modalidad === 'aprovechamiento') {
            $etapas[] = [
                'clave' => 'solicitud', 'titulo' => 'Solicitud de la opción', 'tipo' => 'captura',
                'descripcion' => 'La solicitud debe presentarse dentro del primer año posterior a concluir tu plan de estudios.',
                'campos' => [],
                'formatos' => ['aprovechamiento'],
                'documentos' => [
                    self::doc('solicitud_firmada', 'Solicitud firmada por el pasante', 'Imprime el formato llenado, fírmalo y escanéalo.', null, 'aprovechamiento'),
                    self::doc('cert_licenciatura', 'Certificado de estudios de licenciatura', 'Descargado del Portal de alumnos.'),
                    self::doc('historial_academico', 'Historial académico (trayectoria)', 'Firmado y sellado por el Jefe del Departamento de Control Escolar.'),
                    self::doc('cert_servicio_social', 'Certificado de servicio social'),
                ],
                'personal' => [
                    'titulo' => 'Autorización de la Subdirección Académica',
                    'descripcion' => 'Apoyo a Titulación verifica tus datos y recaba la firma y sello de la Subdirección.',
                    'campos' => [['k' => 'fecha', 'label' => 'Fecha de autorización', 'tipo' => 'fecha', 'req' => true]],
                    'entrega' => self::doc('solicitud_autorizada', 'Solicitud autorizada (firma y sello)'),
                ],
            ];
        } elseif ($modalidad === 'egel') {
            $formato = $variante === 'nacional' ? 'egel_nacional' : 'egel_50';
            $etapas[] = [
                'clave' => 'solicitud', 'titulo' => 'Solicitud para presentar el EGEL', 'tipo' => 'captura',
                'descripcion' => 'Debes tramitar la solicitud antes de presentar el examen. Después te registras en el sitio de la Dirección de Estudios Profesionales (Pagando menos) o en la Facultad de Contaduría (convocatoria nacional).',
                'campos' => [$vez],
                'formatos' => [$formato],
                'documentos' => [
                    self::doc('solicitud_firmada', 'Solicitud firmada por el pasante', '', null, $formato),
                    self::doc('cert_licenciatura', 'Certificado total de estudios de licenciatura'),
                    self::doc('cert_servicio_social', 'Certificado de servicio social'),
                ],
                'presencial' => ['Tres fotografías tamaño óvalo miñón, papel mate, de frente y ropa clara; los hombres con corbata (solo sistema dependiente FI-UAEMéx).'],
                'personal' => [
                    'titulo' => 'Autorización de la Subdirección Académica',
                    'campos' => [['k' => 'fecha', 'label' => 'Fecha de autorización', 'tipo' => 'fecha', 'req' => true]],
                    'entrega' => self::doc('solicitud_autorizada', 'Solicitud autorizada (firma y sello)'),
                ],
            ];
            $etapas[] = [
                'clave' => 'ceneval', 'titulo' => 'Registro y resultado del EGEL', 'tipo' => 'captura',
                'descripcion' => 'Registra tu examen en sede UAEM, preséntalo y sube tu resultado. Un resultado «Aún no satisfactorio» cuenta como evaluación aplazada.',
                'campos' => [['k' => 'fecha_examen', 'label' => 'Fecha en que presentaste el EGEL', 'tipo' => 'fecha', 'req' => true]],
                'documentos' => [
                    self::doc('comprobante_registro_egel', 'Comprobante de registro y pago del EGEL'),
                    self::doc('reporte_ceneval', 'Reporte individual de resultados y Testimonio de Desempeño Satisfactorio o Sobresaliente', 'Emitido por el CENEVAL. El Vo. Bo. y sello de la Subdirección los gestiona Apoyo a Titulación.'),
                ],
            ];
        } else {
            $etapas[] = [
                'clave' => 'solicitud', 'titulo' => 'Solicitud de la opción', 'tipo' => 'captura',
                'descripcion' => 'Presenta la solicitud antes de inscribirte a los estudios avanzados. La Coordinación de Docencia dictamina la afinidad y la Subdirección Académica autoriza.',
                'campos' => [$vez],
                'formatos' => ['creditos'],
                'documentos' => [
                    self::doc('solicitud_firmada', 'Solicitud firmada por el pasante', 'Debe estar fechada antes de iniciar los estudios avanzados.', null, 'creditos'),
                    self::doc('cert_licenciatura', 'Certificado de estudios de licenciatura'),
                    self::doc('cert_servicio_social', 'Certificado de servicio social'),
                    self::doc('plan_estudios', 'Plan de estudios oficial de los estudios avanzados', 'Debe avalar cursos, créditos, actividades con valor curricular, plazos mínimos, perfiles y contenidos programáticos.'),
                    self::doc('registro_dgp', 'Registro del programa ante la Dirección General de Profesiones (o validez oficial)', '', 'Solo para estudios que no se realicen en la UAEMéx.'),
                ],
                'personal' => [
                    'titulo' => 'Dictamen de afinidad y autorización',
                    'descripcion' => 'La Coordinación de Docencia dictamina la afinidad y la Subdirección Académica autoriza.',
                    'campos' => [['k' => 'fecha', 'label' => 'Fecha de autorización', 'tipo' => 'fecha', 'req' => true]],
                    'entrega' => self::doc('carta_afinidad', 'Carta de afinidad y solicitud autorizada (firma y sello)'),
                ],
            ];
            $etapas[] = [
                'clave' => 'estudios', 'titulo' => 'Estudios avanzados cursados', 'tipo' => 'captura',
                'descripcion' => 'Cuando concluyas, sube la documentación que acredita el 100 % de los créditos (diplomado superior o especialidad) o el 75 % (maestría), cubiertos en los plazos mínimos.',
                'campos' => [['k' => 'programa', 'label' => 'Programa y institución donde cursaste', 'tipo' => 'texto', 'max' => 200, 'req' => true]],
                'documentos' => [
                    self::doc('carta_aceptacion', 'Carta de aceptación a los estudios avanzados', 'Con fecha posterior a la carta de afinidad.'),
                    self::doc('dictamen_equivalencia', 'Dictamen de equivalencia de la Secretaría de Investigación y Estudios Avanzados'),
                    self::doc('cert_estudios_avanzados', 'Certificado de estudios y constancia de calificaciones de créditos cubiertos', 'Especifica que se cubrieron en los plazos mínimos.'),
                ],
            ];
        }

        $ingles = $modalidad !== 'aprovechamiento';
        $documentos = self::docsIdentidad($ingles, $ingles);
        // En aprovechamiento los certificados de licenciatura y servicio social ya se validaron en la solicitud.
        $derechos = self::doc('recibo_derechos', 'Recibo de pago: derechos de evaluación profesional ($' . number_format(self::COSTOS['toma_protesta']) . ')', 'Se solicita en Control Escolar de la Facultad.');
        $titulo = self::doc('recibo_titulo', 'Recibo de pago: expedición de título ($' . number_format(self::COSTOS['titulo']) . ')', 'Se solicita en Control Escolar de la Facultad.');
        $etapas[] = [
            'clave' => 'protesta', 'titulo' => 'Documentación para la toma de protesta', 'tipo' => 'captura',
            'descripcion' => 'Sube cada documento en PDF, legible y a color. Cuando todo esté validado, Apoyo a Titulación te citará para entregar originales y copias y fijar la fecha.',
            'campos' => [],
            'formatos' => ['datos_titulo'],
            'documentos' => [...$documentos, $derechos, $titulo],
            'presencial' => self::presencialBase(false),
        ];
        $etapas[] = [
            'clave' => 'fecha', 'titulo' => 'Fecha, hora y lugar de la toma de protesta', 'tipo' => 'personal',
            'descripcion' => 'Apoyo a Titulación asigna el jurado y te comunica la fecha. Preséntate puntualmente.',
            'personal' => [
                'titulo' => 'Asignar fecha, hora y lugar',
                'campos' => [
                    ['k' => 'fecha', 'label' => 'Fecha', 'tipo' => 'fecha', 'req' => true],
                    ['k' => 'hora', 'label' => 'Hora', 'tipo' => 'hora', 'req' => true],
                    ['k' => 'lugar', 'label' => 'Lugar', 'tipo' => 'texto', 'req' => true],
                    ['k' => 'jurado', 'label' => 'Jurado (opcional)', 'tipo' => 'area', 'req' => false],
                ],
            ],
        ];
        $etapas[] = self::etapaResultado(false);
        return $etapas;
    }

    private static function etapaResultado(bool $conTrabajo): array
    {
        $opciones = $conTrabajo
            ? ['mencion' => 'Aprobado con mención honorífica', 'unanimidad' => 'Aprobado por unanimidad de votos', 'mayoria' => 'Aprobado por mayoría de votos', 'aplazado' => 'Aplazado']
            : ['aprobado' => 'Aprobado (toma de protesta)', 'aplazado' => 'Aplazado'];
        return [
            'clave' => 'resultado', 'titulo' => 'Resultado de la evaluación', 'tipo' => 'personal',
            'descripcion' => 'Al concluir, el departamento registra el resultado. Después podrás consultar cómo descargar tu título y cédula profesional.',
            'personal' => [
                'titulo' => 'Registrar resultado',
                'campos' => [
                    ['k' => 'resultado', 'label' => 'Resultado', 'tipo' => 'select', 'opciones' => $opciones, 'req' => true],
                    ['k' => 'fecha', 'label' => 'Fecha de la evaluación', 'tipo' => 'fecha', 'req' => true],
                ],
                'final' => true,
            ],
        ];
    }

    private static function etapasConTrabajo(string $modalidad, array $datos): array
    {
        $m = self::modalidad($modalidad);
        $proto = self::protocolo($modalidad);
        $limite = (int) ($m['similitud'] ?? 20);
        $etapas = [$datos];

        $etapas[] = [
            'clave' => 'protocolo', 'titulo' => 'Protocolo y aprobación del tema', 'tipo' => 'captura',
            'descripcion' => 'Captura la propuesta de tu trabajo. El sistema llena el formato oficial de solicitud y protocolo; imprímelo, fírmalo con tu asesor y súbelo para su revisión por la Comisión Revisora de Protocolos.',
            'campos' => $proto['campos'] ?? [],
            'formatos' => ['protocolo'],
            'documentos' => [
                self::doc('protocolo_firmado', 'Solicitud y protocolo firmados (pasante y asesor)', 'En PDF, completo y legible.', null, 'protocolo'),
                self::doc('carta_presentacion', 'Carta de presentación del pasante y asesor', 'Descárgala en blanco desde «Formatos en blanco».', 'Solo para instituciones incorporadas.'),
            ],
            'personal' => [
                'titulo' => 'Dictamen de la Comisión Revisora de Protocolos',
                'descripcion' => 'Si el protocolo requiere correcciones, rechaza el documento con las indicaciones; cuando se apruebe, sube el oficio de aprobación de tema y modalidad.',
                'campos' => [['k' => 'fecha', 'label' => 'Fecha de aprobación del protocolo', 'tipo' => 'fecha', 'req' => true]],
                'entrega' => self::doc('oficio_aprobacion_tema', 'Oficio de aprobación de tema y modalidad'),
            ],
            'blanco' => ['rep'],
        ];

        $etapas[] = [
            'clave' => 'desarrollo', 'titulo' => 'Desarrollo del trabajo escrito', 'tipo' => 'confirmacion',
            'descripcion' => 'Tienes exclusividad sobre el tema por dos años, renovable hasta por dos años más (Art. 86, REP). Desarróllalo con tu asesor; cuando cuente con su visto bueno, confirma aquí para continuar.',
            'confirmar' => 'Concluí el trabajo escrito y mi asesor lo avala para revisión',
            'blanco' => ['prorroga', 'cambio_asesor', 'baja', 'cambios', 'coasesoria'],
        ];

        $etapas[] = [
            'clave' => 'revision', 'titulo' => 'Solicitud de revisión (examen previo)', 'tipo' => 'captura',
            'descripcion' => 'Solicita fecha y asignación de revisores. Entrega el reporte de similitud de iThenticate (máximo ' . $limite . ' %) y tu trabajo en PDF.',
            'campos' => [
                ['k' => 'titulo', 'label' => 'Título del trabajo escrito', 'tipo' => 'area', 'filas' => 3, 'max' => 400, 'req' => true],
                ['k' => 'fecha_protocolo', 'label' => 'Fecha del oficio de aprobación del protocolo', 'tipo' => 'fecha', 'req' => true],
            ],
            'formatos' => ['revision'],
            'documentos' => [
                self::doc('revision_firmada', 'Solicitud de revisión firmada (pasante y asesor)', '', null, 'revision'),
                self::doc('similitud_previo', 'Reporte de porcentaje de similitud (iThenticate)', 'No mayor al ' . $limite . ' %.'),
                self::doc('trabajo_previo', 'Trabajo escrito en PDF para revisión', 'Con copia del oficio de aprobación de tema al inicio.'),
            ],
            'personal' => [
                'titulo' => 'Asignación de revisores y examen previo',
                'campos' => [
                    ['k' => 'revisores', 'label' => 'Revisores asignados', 'tipo' => 'area', 'req' => true],
                    ['k' => 'fecha', 'label' => 'Fecha del examen previo', 'tipo' => 'fecha', 'req' => true],
                    ['k' => 'hora', 'label' => 'Hora', 'tipo' => 'hora', 'req' => true],
                    ['k' => 'lugar', 'label' => 'Lugar', 'tipo' => 'texto', 'req' => true],
                ],
                'entrega' => self::doc('oficio_revisores', 'Oficios de asignación de revisores'),
            ],
        ];

        $etapas[] = [
            'clave' => 'sustentacion', 'titulo' => 'Solicitud de sustentación y voto aprobatorio', 'tipo' => 'captura',
            'descripcion' => 'Con las correcciones atendidas y el voto aprobatorio del asesor y revisores, solicita la autorización de impresión y la asignación del jurado.',
            'campos' => [['k' => 'fecha_voto', 'label' => 'Fecha del voto aprobatorio', 'tipo' => 'fecha', 'req' => true]],
            'formatos' => ['sustentacion'],
            'documentos' => [
                self::doc('sustentacion_firmada', 'Solicitud de sustentación firmada (pasante y asesor)', '', null, 'sustentacion'),
                self::doc('voto_aprobatorio', 'Voto aprobatorio firmado por el asesor y los revisores', 'Es la segunda hoja del formato de sustentación.'),
                self::doc('indice_generico', 'Título del trabajo e índice genérico'),
            ],
            'personal' => [
                'titulo' => 'Autorización de impresión',
                'campos' => [['k' => 'fecha', 'label' => 'Fecha de autorización', 'tipo' => 'fecha', 'req' => true]],
                'entrega' => self::doc('oficio_impresion', 'Oficio de autorización de impresión'),
            ],
        ];

        $evidencias = self::evidenciasModalidad($modalidad);
        $docsFinal = [
            self::doc('solicitud_eval', 'Solicitud para presentar la evaluación profesional (trabajo escrito)', 'Con firma del pasante; Apoyo a Titulación recaba firma y sello de la Subdirección.', null, 'trabajo_eval'),
            self::doc('trabajo_final', 'Trabajo escrito final en PDF', 'Con oficio de autorización de impresión, resumen y carta de autorización para el Repositorio Institucional.'),
            self::doc('similitud_final', 'Reporte de porcentaje de similitud (iThenticate)', 'No mayor al ' . $limite . ' %.'),
            ...$evidencias,
            self::doc('cert_licenciatura', 'Certificado de estudios de licenciatura', 'Descargado del Portal de alumnos.'),
            self::doc('cert_servicio_social', 'Certificado de servicio social'),
            ...self::docsIdentidad(true, true),
            self::doc('carta_ri', 'Carta de autorización o de excepción para publicar en el Repositorio Institucional', 'Entrega la que corresponda; descárgala en «Formatos en blanco».'),
            self::doc('recibo_derechos', 'Recibo de pago: derechos de evaluación profesional ($' . number_format(self::COSTOS['evaluacion_trabajo']) . ')', 'Se solicita en Control Escolar de la Facultad.'),
            self::doc('recibo_titulo', 'Recibo de pago: expedición de título ($' . number_format(self::COSTOS['titulo']) . ')'),
        ];
        $etapas[] = [
            'clave' => 'final', 'titulo' => 'Documentación para la evaluación profesional', 'tipo' => 'captura',
            'descripcion' => 'Reúne y sube cada documento en PDF. Cuando todos estén validados, Apoyo a Titulación te cita para entregar originales, copias y ejemplares, y fijar el jurado.',
            'campos' => [],
            'formatos' => ['trabajo_eval', 'datos_titulo'],
            'documentos' => $docsFinal,
            'presencial' => self::presencialBase(true),
            'blanco' => ['carta_ri', 'carta_ri_2', 'carta_excepcion_ri', 'carta_excepcion_ri_2'],
        ];
        $etapas[] = [
            'clave' => 'fecha', 'titulo' => 'Fecha, hora y lugar de la evaluación', 'tipo' => 'personal',
            'descripcion' => 'Apoyo a Titulación define fecha, hora y lugar con la Coordinación de Docencia, que asigna al jurado.',
            'personal' => [
                'titulo' => 'Asignar jurado, fecha, hora y lugar',
                'campos' => [
                    ['k' => 'jurado', 'label' => 'Jurado asignado', 'tipo' => 'area', 'req' => true],
                    ['k' => 'fecha', 'label' => 'Fecha', 'tipo' => 'fecha', 'req' => true],
                    ['k' => 'hora', 'label' => 'Hora', 'tipo' => 'hora', 'req' => true],
                    ['k' => 'lugar', 'label' => 'Lugar', 'tipo' => 'texto', 'req' => true],
                ],
                'entrega' => self::doc('oficio_jurado', 'Oficios de asignación de jurado'),
            ],
        ];
        $etapas[] = self::etapaResultado(true);
        return $etapas;
    }

    private static function evidenciasModalidad(string $modalidad): array
    {
        return match ($modalidad) {
            'articulo' => [
                self::doc('constancia_indices', 'Constancia de pertenencia de la revista a los índices validados', 'Emitida por la Secretaría de Investigación y Estudios Avanzados (Art. 16, REP).'),
                self::doc('carta_publicacion', 'Carta de recepción o publicación del artículo expedida por la revista', 'Incluye criterios de publicación y documento de envío (Art. 20, REP).'),
            ],
            'memoria' => [
                self::doc('constancia_laboral', 'Constancia de servicios laborales', 'Dirigida al director de la Facultad; duración continua mínima de dos años o participación en un proyecto de principio a fin.'),
            ],
            'autoempleo' => [
                self::doc('carta_participacion', 'Carta de participación en la unidad económica'),
                self::doc('constitucion_legal', 'Evidencia de la constitución legal de la unidad económica', 'Expedida por autoridad competente.'),
            ],
            'residencia' => [
                self::doc('constancia_sni', 'Constancia de adscripción al SNI del profesor investigador'),
                self::doc('oficio_registro_proyecto', 'Oficio de registro del proyecto de investigación'),
                self::doc('constancia_estancia', 'Constancia de estancia de investigación', 'Duración mínima de seis meses, firmada por la máxima autoridad del centro o instituto.'),
            ],
            default => [],
        };
    }

    public static function etapa(string $modalidad, ?string $variante, string $clave): ?array
    {
        foreach (self::etapas($modalidad, $variante) as $e) {
            if ($e['clave'] === $clave) {
                return $e;
            }
        }
        return null;
    }

    /** Busca un documento (del pasante o de entrega del personal) dentro de una etapa. */
    public static function documentoDeEtapa(array $etapa, string $clave): ?array
    {
        foreach ($etapa['documentos'] ?? [] as $d) {
            if ($d['clave'] === $clave) {
                return $d + ['origen' => 'alumno'];
            }
        }
        $entrega = $etapa['personal']['entrega'] ?? null;
        if ($entrega && $entrega['clave'] === $clave) {
            return $entrega + ['origen' => 'personal'];
        }
        return null;
    }

    public static function estadosTramite(): array
    {
        return [
            'abierto' => 'En proceso',
            'concluido' => 'Concluido',
            'cancelado' => 'Cancelado',
        ];
    }
}
