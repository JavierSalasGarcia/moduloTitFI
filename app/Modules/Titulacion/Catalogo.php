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
            'ia' => ['nombre' => 'Ingeniería en Inteligencia Artificial', 'siglas' => ['IIA', 'IA']],
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

    /** Documentos de consulta que acompañan a una etapa (los formatos ya los genera la plataforma). */
    public static function archivosBlanco(): array
    {
        return [
            'rep' => ['Reglamento de Evaluación Profesional', 'REP.pdf'],
            'privacidad' => ['Aviso de privacidad', 'avisoDePrivacidadFI.pdf'],
        ];
    }

    /** Formatos que genera la plataforma (Services\FormatosOficiales) con los datos del trámite. */
    public static function formatos(): array
    {
        $f = static fn(string $nombre, string $firma, array $extra = []): array => ['tipo' => 'pdf', 'nombre' => $nombre, 'firma' => $firma, 'activo' => true] + $extra;
        return [
            'aprovechamiento' => $f('Solicitud de evaluación profesional por Aprovechamiento académico', 'el pasante; autoriza la Subdirección Académica'),
            'egel_50' => $f('Solicitud de EGEL (convocatoria «Pagando menos»)', 'el pasante; autoriza la Subdirección Académica'),
            'egel_nacional' => $f('Solicitud de EGEL (convocatoria nacional)', 'el pasante; autoriza la Subdirección Académica'),
            'creditos' => $f('Solicitud de Créditos en estudios avanzados', 'el pasante; autoriza la Subdirección Académica'),
            'datos_titulo' => $f('Formato de datos del interesado para expedición de título profesional', 'el pasante'),
            'protocolo' => $f('Solicitud de aprobación de tema y protocolo', 'el pasante y el asesor (Vo. Bo.)'),
            'revision' => $f('Solicitud de revisión de trabajo escrito (examen previo)', 'el pasante y el asesor (Vo. Bo.)'),
            'voto' => $f('Voto aprobatorio', 'el asesor y los revisores', ['interno' => true]),
            'sustentacion' => $f('Solicitud de sustentación del trabajo escrito (examen definitivo)', 'el pasante y el asesor (Vo. Bo.)'),
            'trabajo_eval' => $f('Solicitud para presentar la evaluación profesional mediante trabajo escrito', 'el pasante; autoriza la Subdirección Académica'),
            'requisitos' => $f('Requisitos para la evaluación profesional (con el estado de cada documento)', 'informativo'),
            'carta_ri' => $f('Carta de autorización para publicación en el Repositorio Institucional', 'el pasante (y coautor, si lo hay)'),
            'carta_excepcion_ri' => $f('Carta de excepción para publicación en el Repositorio Institucional', 'el pasante (y coautor, si lo hay)'),
            'art5' => $f('Solicitud para continuar con la evaluación profesional (Art. 5, REP)', 'el pasante (y asesor si hay trabajo escrito)'),
            'prorroga' => $f('Solicitud de prórroga del tema', 'el pasante y el asesor (Vo. Bo.)'),
            'cambio_asesor' => $f('Solicitud de cambio de asesor', 'el pasante, el asesor actual y el propuesto'),
            'baja' => $f('Solicitud de baja de tema y/o sustentante, o cambio de sustentante', 'el pasante, el asesor y, en su caso, el sustentante propuesto'),
            'cambios' => $f('Solicitud de cambios al protocolo aprobado', 'el pasante y el asesor (Vo. Bo.)'),
            'coasesoria' => $f('Solicitud para coasesorar el trabajo escrito', 'el asesor y el coasesor propuesto'),
            'carta_presentacion' => $f('Carta de presentación (solo instituciones incorporadas)', 'la escuela incorporada'),
            'autoriz_eval' => $f('Solicitud de autorización de evaluación profesional (solo instituciones incorporadas)', 'el pasante y el área de titulación de la escuela'),
            'revision_protocolo' => $f('Revisión de protocolo (dictamen del comité)', 'cada revisor; el dictamen, el responsable del comité', ['interno' => true]),
        ];
    }

    /**
     * Solicitudes que el pasante puede necesitar en algún momento del trámite: capturan algunos datos más y
     * la plataforma genera el formato ya llenado. clave => [etapas donde aplica ('*' todas), campos].
     */
    public static function formatosAdicionales(string $tipo): array
    {
        $area = static fn(string $k, string $l, int $max = 900): array => ['k' => $k, 'label' => $l, 'tipo' => 'area', 'filas' => 3, 'max' => $max, 'req' => true];
        $txt = static fn(string $k, string $l, bool $req = true): array => ['k' => $k, 'label' => $l, 'tipo' => 'texto', 'max' => 160, 'req' => $req];
        $comunes = [
            'art5' => [$area('motivos', 'Motivos por los que excediste el plazo')],
            'autoriz_eval' => [$txt('institucion', 'Nombre de la institución incorporada')],
        ];
        if ($tipo !== 'con_trabajo') {
            return $comunes;
        }
        return [
            'prorroga' => [['k' => 'avance', 'label' => 'Porcentaje aproximado de avance', 'tipo' => 'texto', 'max' => 3, 'req' => true, 'patron' => '^[0-9]{1,3}$'], $area('motivos', 'Motivos de la prórroga')],
            'cambio_asesor' => [$txt('propuesto', 'Grado y nombre del asesor propuesto'), $area('motivos', 'Motivos del cambio')],
            'baja' => [['k' => 'tipo', 'label' => 'Solicitud', 'tipo' => 'select', 'opciones' => ['baja_tema' => 'Baja de tema', 'baja_sustentante' => 'Baja de sustentante', 'cambio_sustentante' => 'Cambio de sustentante'], 'req' => true], $txt('sustentante', 'Sustentante propuesto (nombre y número de cuenta)', false), $area('motivos', 'Motivos')],
            'cambios' => [$area('actual', 'Situación actual'), $area('propuestos', 'Cambios propuestos'), $area('motivos', 'Motivos del cambio')],
            'coasesoria' => [$txt('coasesor', 'Grado y nombre del coasesor propuesto'), $area('razones', 'Razones por las que se requiere la coasesoría')],
            'carta_presentacion' => [],
            'carta_ri' => [$txt('coautor', 'Coautor (solo si son dos tesistas)', false), $txt('coautor_cuenta', 'Número de cuenta del coautor', false)],
            'carta_excepcion_ri' => [$txt('medio', 'Publicación o medio donde está en dictaminación o embargo'), $txt('folio_medio', 'Número de folio (si lo hay)', false), ['k' => 'fecha_libre', 'label' => 'Fecha en que termina el embargo', 'tipo' => 'fecha', 'req' => true], $txt('coautor', 'Coautor (solo si son dos tesistas)', false), $txt('coautor_cuenta', 'Número de cuenta del coautor', false)],
        ] + $comunes;
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
            ['k' => 'asesor', 'h' => 'Asesor', 'label' => 'Asesor (adscrito a la UAEM): nombre', 'tipo' => 'texto', 'max' => 160, 'req' => true, 'prof' => 'asesor', 'ayuda' => 'Escribe el nombre o el correo: si está registrado se completan solos. Si no, captura ambos; le llegará un correo para firmar tu protocolo.'],
            ['k' => 'asesor_correo', 'label' => 'Asesor: correo electrónico', 'tipo' => 'email', 'max' => 120, 'req' => true, 'prof_correo' => 'asesor'],
            ['k' => 'asesor_grado', 'label' => 'Asesor: grado (Dr., Mtra., Ing.…)', 'tipo' => 'texto', 'max' => 30, 'req' => true, 'prof_grado' => 'asesor'],
            ['k' => 'asesor_genero', 'label' => 'Asesor: género', 'tipo' => 'select', 'opciones' => ['masculino' => 'Masculino', 'femenino' => 'Femenino'], 'req' => true, 'prof_genero' => 'asesor'],
            ['k' => 'coasesor', 'h' => 'Coasesor', 'label' => 'Coasesor (opcional): nombre', 'tipo' => 'texto', 'max' => 160, 'req' => false, 'vacio' => 'No aplica', 'prof' => 'coasesor'],
            ['k' => 'coasesor_correo', 'label' => 'Coasesor: correo electrónico', 'tipo' => 'email', 'max' => 120, 'req' => false, 'prof_correo' => 'coasesor'],
            ['k' => 'coasesor_grado', 'label' => 'Coasesor: grado', 'tipo' => 'texto', 'max' => 30, 'req' => false, 'prof_grado' => 'coasesor'],
            ['k' => 'coasesor_genero', 'label' => 'Coasesor: género', 'tipo' => 'select', 'opciones' => ['masculino' => 'Masculino', 'femenino' => 'Femenino'], 'req' => false, 'prof_genero' => 'coasesor'],
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
            'activo' => true, 'fisico' => false, 'cotejo' => false, 'firma_cotejo' => 'sello',
            'generado' => $formato !== null];
    }

    /** Asignación de sinodales que firma el Coordinador de Docencia (se activa al fijar la fecha del examen). */
    private static function asignacion(string $clave, string $nombre, string $tipo): array
    {
        return ['asignacion' => $tipo] + self::doc($clave, $nombre);
    }

    /** Oficio que genera la plataforma con los datos del trámite y firma la Subdirección. */
    private static function oficio(string $clave, string $nombre): array
    {
        return ['oficio' => true, 'generado' => true] + self::doc($clave, $nombre);
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
        $d[] = self::doc('datos_titulo', 'Formato de datos personales (para el título)', 'La plataforma lo llena con tus datos; lo firmas electrónicamente.', null, 'datos_titulo');
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
            array_unshift($l, 'Una memoria USB en un estuche para cada sinodal con tu trabajo escrito en PDF (lo acuerdas con cada profesor); Titulación solo valida el PDF completo que subiste.');
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
                    self::doc('solicitud_firmada', 'Solicitud firmada por el pasante', 'La plataforma lo genera con tus datos; lo firmas electrónicamente.', null, 'aprovechamiento'),
                    self::doc('cert_licenciatura', 'Certificado de estudios de licenciatura', 'Descargado del Portal de alumnos.'),
                    self::doc('historial_academico', 'Historial académico (trayectoria)', 'Firmado y sellado por el Jefe del Departamento de Control Escolar.'),
                    self::doc('cert_servicio_social', 'Certificado de servicio social'),
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
                'campos' => [$vez, ['k' => 'programa', 'label' => 'Estudios avanzados que cursarás (programa, institución y, si aplica, RVOE)', 'tipo' => 'texto', 'max' => 250, 'req' => true]],
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
                    'entrega' => self::oficio('carta_afinidad', 'Carta de afinidad'),
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
            'formatos' => ['datos_titulo', 'requisitos'],
            'documentos' => [...$documentos, $derechos, $titulo],
            'presencial' => self::presencialBase(false),
        ];
        $etapas[] = [
            'clave' => 'fecha', 'titulo' => 'Fecha, hora y lugar de la toma de protesta', 'tipo' => 'personal',
            'descripcion' => 'Apoyo a Titulación asigna el jurado y te comunica la fecha. Preséntate puntualmente.',
            'personal' => [
                'titulo' => 'Fecha y lugar de la toma de protesta',
                'descripcion' => 'Titulación fija la fecha. En esta opción la comisión es siempre el Director (presidente) y el Subdirector Académico (secretario); el acta oficial se genera en otro sistema y se firma en papel.',
                'comision_fija' => true,
                'campos' => [
                    ['k' => 'fecha', 'label' => 'Fecha', 'tipo' => 'fecha', 'req' => true],
                    ['k' => 'hora', 'label' => 'Hora', 'tipo' => 'hora', 'req' => true],
                    ['k' => 'lugar', 'label' => 'Lugar', 'tipo' => 'texto', 'req' => true],
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
            'descripcion' => 'Captura la propuesta de tu trabajo. El sistema llena el formato oficial de solicitud y protocolo; escribe aquí todo el protocolo; la plataforma lo firma por ti, le pide la firma a tu asesor por correo y lo envía al responsable del Comité de Revisión de Protocolos de tu licenciatura. Si el comité te pide correcciones, las verás en tus Notificaciones: corrige aquí y vuelve a enviarlo.',
            'campos' => $proto['campos'] ?? [],
            'formatos' => ['protocolo'],
            'documentos' => [
                ['validacion' => 'comision'] + self::doc('protocolo_firmado', 'Solicitud y protocolo firmados (pasante y asesor)', 'La plataforma lo genera con lo que escribiste; tu asesor lo firma por correo. Después lo revisa el comité de tu licenciatura.', null, 'protocolo'),
                self::doc('carta_presentacion', 'Carta de presentación del pasante y asesor', 'Descárgala en blanco desde «Formatos en blanco».', 'Solo para instituciones incorporadas.'),
            ],
            'personal' => [
                'titulo' => 'Aprobación del protocolo y oficio de aprobación del tema',
                'descripcion' => 'Cuando el comité aprueba el protocolo, la plataforma genera el oficio de aprobación del tema; lo firman la Jefatura de Evaluación Profesional (Vo. Bo.) y la Subdirección Académica.',
                'campos' => [
                    ['k' => 'fecha', 'label' => 'Fecha de aprobación del protocolo', 'tipo' => 'fecha', 'req' => true],
                    ['k' => 'observaciones', 'label' => 'Observaciones del oficio (vacío: la leyenda del coasesor, si lo hay, o «Ninguna.»)', 'tipo' => 'area', 'max' => 1500],
                ],
                'entrega' => self::oficio('oficio_aprobacion_tema', 'Oficio de aprobación de tema y modalidad'),
            ],
            'blanco' => ['rep'],
        ];

        $etapas[] = [
            'clave' => 'desarrollo', 'titulo' => 'Desarrollo del trabajo escrito', 'tipo' => 'confirmacion',
            'descripcion' => 'Tienes exclusividad sobre el tema durante dos años a partir de la aprobación; puedes renovarla con una prórroga hasta por un año, que incluye la sustentación (Art. 86, REP). Desarróllalo con tu asesor; cuando cuente con su visto bueno, confirma aquí para continuar.',
            'confirmar' => 'Concluí el trabajo escrito y mi asesor lo avala para revisión',
        ];

        $etapas[] = [
            'clave' => 'revision', 'titulo' => 'Solicitud de revisión del trabajo escrito (examen previo)', 'tipo' => 'captura',
            'descripcion' => 'Llena la solicitud de revisión del trabajo escrito y sube los documentos probatorios: el reporte de similitud de iThenticate (máximo ' . $limite . ' %) y tu trabajo en PDF. Después elige la fecha y hora de tu examen previo y propone a tu sínodo; tu solicitud llega al Coordinador de Docencia de tu licenciatura.',
            'campos' => [
                ['k' => 'titulo', 'label' => 'Título del trabajo escrito', 'tipo' => 'area', 'filas' => 3, 'max' => 400, 'req' => true],
            ],
            'formatos' => ['revision'],
            'documentos' => [
                self::doc('revision_firmada', 'Solicitud de revisión firmada (pasante y asesor)', 'La firmas tú y tu asesor da su Vo. Bo. desde su correo.', null, 'revision'),
                self::doc('similitud_previo', 'Reporte de porcentaje de similitud (iThenticate)', 'No mayor al ' . $limite . ' %.'),
                self::doc('trabajo_previo', 'Trabajo escrito en PDF para revisión', 'Con copia del oficio de aprobación de tema al inicio.'),
                ['solo_si' => 'protocolo_vencido'] + self::doc('solicitud_prorroga', 'Solicitud de prórroga del tema (pasante y asesor)', 'Tu protocolo se aprobó hace más del plazo de exclusividad (Art. 86, REP). Captura avance y motivos en «Solicitudes y formatos adicionales», firma aquí y tu asesor da su Vo. Bo.; al validarla, la plataforma genera el oficio de aprobación de prórroga que firma la Subdirección.', null, 'prorroga'),
            ],
            'agenda' => 'previo',
            'personal' => [
                'titulo' => 'Fecha del examen previo y sínodo',
                'descripcion' => 'Eliges fecha y hora y propones tu sínodo; el Coordinador de Docencia de tu licenciatura lo ratifica o modifica y firma desde el enlace de su correo. Después se generan los oficios individuales.',
                'campos' => [],
                'entrega' => self::asignacion('asignacion_revisores', 'Asignación de sínodo (Coordinación de Docencia)', 'previo'),
            ],
        ];

        $etapas[] = [
            'clave' => 'sustentacion', 'titulo' => 'Solicitud de sustentación y voto aprobatorio', 'tipo' => 'captura',
            'descripcion' => 'Cuando hayas atendido las correcciones del examen previo, genera el voto aprobatorio: la plataforma lo envía a tu asesor y a tus revisores para que lo firmen desde su correo. Con el voto firmado, firma tu solicitud de sustentación (tu asesor da su Vo. Bo.) para obtener la autorización de impresión y la asignación del jurado. Escribe aquí la estructura capitular: con ella la plataforma genera el oficio de estructura capitular que firma el Director.',
            'campos' => [
                ['k' => 'capitulado', 'label' => 'Estructura capitular (un capítulo por línea, sin numerar)', 'tipo' => 'area', 'filas' => 8, 'max' => 2000, 'req' => true,
                    'ayuda' => 'Por ejemplo: «Introducción». El oficio los numerará como Capítulo 1, 2… Titulación valida que correspondan a tu trabajo.'],
            ],
            'formatos' => ['sustentacion'],
            'documentos' => [
                self::doc('voto_aprobatorio', 'Voto aprobatorio (lo firman tu asesor y tus revisores)', 'La plataforma lo genera y pide las firmas por correo.', null, 'voto'),
                self::doc('sustentacion_firmada', 'Solicitud de sustentación firmada (pasante y asesor)', 'Se habilita cuando el voto aprobatorio está firmado.', null, 'sustentacion'),
            ],
            'personal' => [
                'titulo' => 'Autorización de impresión',
                'campos' => [['k' => 'fecha', 'label' => 'Fecha de autorización', 'tipo' => 'fecha', 'req' => true]],
                'entrega' => self::oficio('oficio_impresion', 'Oficio de autorización de impresión'),
                'extras' => [self::oficio('oficio_estructura', 'Oficio de estructura capitular (para incluir después de la portada)')],
            ],
        ];

        $evidencias = self::evidenciasModalidad($modalidad);
        $docsFinal = [
            self::doc('solicitud_eval', 'Solicitud para presentar la evaluación profesional (trabajo escrito)', 'La plataforma lo genera con tus datos; lo firmas electrónicamente y la Subdirección lo autoriza.', null, 'trabajo_eval'),
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
            'formatos' => ['trabajo_eval', 'datos_titulo', 'requisitos'],
            'blanco' => ['rep'],
            'documentos' => $docsFinal,
            'presencial' => self::presencialBase(true),
        ];
        $etapas[] = [
            'clave' => 'fecha', 'titulo' => 'Solicitud del examen definitivo', 'tipo' => 'captura',
            'descripcion' => 'Elige la fecha y hora de tu examen definitivo y propone a tu jurado; tu solicitud llega al Coordinador de Docencia de tu licenciatura, que lo ratifica o modifica.',
            'campos' => [],
            'agenda' => 'definitivo',
            'personal' => [
                'titulo' => 'Fecha del examen definitivo y jurado',
                'descripcion' => 'Eliges fecha y hora y propones tu jurado; el Coordinador de Docencia lo ratifica o modifica y firma desde el enlace de su correo. Después se generan los oficios individuales.',
                'campos' => [],
                'entrega' => self::asignacion('asignacion_jurado', 'Asignación de jurado (Coordinación de Docencia)', 'definitivo'),
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
