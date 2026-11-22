<?php

declare(strict_types=1);

namespace App\Modules\Consejos\Services;

/**
 * Plantillas de solicitud por tipo de trámite, según la estructura canónica de las actas de 2026 (docs/CONSEJOS.md):
 * sujeto y calidad + «solicita / presenta a los H.H. Consejos Académico y de Gobierno» + acción colegiada + objeto + finalidad o fundamento.
 * Los datos que cambian van entre corchetes; el formulario avisa si queda alguno sin llenar. El acuerdo no se plantilla aquí:
 * se arma al conducir la sesión repitiendo el núcleo de la solicitud («otorga el aval a / para …»).
 */
final class Plantillas
{
    /** @return list<array{clave:string,grupo:string,nombre:string,solicitud:string,antecedentes:string,tabla:list<string>,gobierno:bool,datos:list<string>}> */
    public static function todas(): array
    {
        $c = 'a los H.H. Consejos Académico y de Gobierno';
        $of = 'Oficio de solicitud';
        $filas = [
            // ---- Casos de profesores
            ['profesor', 'sabatico_goce', 'Año sabático: solicitud para gozarlo',
                "El [Dr./Dra. nombre], profesor de tiempo completo adscrito a esta Facultad, solicita $c autorización para gozar de la prestación de año sabático para el periodo escolar [periodo], en el cual realizará actividades de [actividades].", "$of / Programa de actividades", []],
            ['profesor', 'sabatico_informe', 'Año sabático: informe y solicitud de comisión revisora',
                "El [Dr./Dra. nombre] presenta $c su informe de actividades de año sabático correspondiente al periodo [periodo] para su revisión y aprobación, solicitando la designación de la Comisión Revisora correspondiente.", 'Oficio de solicitud / Informe de actividades', []],
            ['profesor', 'sabatico_dictamen', 'Año sabático: dictamen de la comisión revisora',
                "La Comisión revisora del informe de actividades de año sabático del [Dr./Dra. nombre], correspondiente al periodo [periodo], informa $c que, después de analizar el informe de año sabático, la comisión determina: “El informe final cumple satisfactoriamente con el programa de actividades propuesto por [el docente]. Por lo que el reporte es aprobado por esta comisión”.", 'Oficio dictamen', []],
            ['profesor', 'estancia', 'Permiso, licencia o estancia académica externa',
                "El [Dr./Dra. nombre], [cargo] de esta Facultad, solicita $c su autorización para realizar una estancia académica en [institución] durante el periodo comprendido del [fechas], cuyo propósito es desarrollar el proyecto titulado: [título del proyecto].", 'Oficio de solicitud / Plan de trabajo', []],
            ['profesor', 'finiquito', 'Finiquito técnico de proyecto de investigación',
                "El [Dr./Dra. nombre], responsable técnico del proyecto de investigación [título] con clave [clave] financiado por [fuente de financiamiento], solicita $c el aval correspondiente con la finalidad de continuar con los trámites que conduzcan al finiquito técnico ante la [instancia].", $of, []],
            ['profesor', 'salida_capitulo', 'Salida de campo o visita académica de capítulo estudiantil',
                "El [Dr./Dra. nombre], [cargo] y asesor facultativo del Capítulo Estudiantil [nombre del capítulo], solicita $c la autorización para la salida académica a realizarse del [fechas] en [lugar], en la cual participarán alumnos de la Licenciatura en [programa].", $of, ['Alumno', 'Número de cuenta']],

            // ---- Trámites de alumnos
            ['alumno', 'homologacion_prelim', 'Carta de homologación preliminar (movilidad)',
                "La [cargo] solicita $c el aval a la Carta de Homologación preliminar de asignaturas de la/del alumna(o) [nombre], con número de cuenta [cuenta], de la Licenciatura en [programa], quien desea realizar movilidad [nacional/internacional] en [institución] durante el periodo [periodo].", $of, ['Asignatura de origen', 'Asignatura en destino']],
            ['alumno', 'homologacion_def', 'Homologación definitiva / reconocimiento de estudios de movilidad',
                "Se presenta a consideración de $c la aprobación del reconocimiento de estudios y homologación definitiva de las asignaturas cursadas en [institución] durante el periodo escolar [periodo], por la/el alumna(o) [nombre], con número de cuenta [cuenta], de la Licenciatura en [programa].", $of, ['Asignatura cursada', 'Calificación', 'Asignatura que se reconoce']],
            ['alumno', 'depuracion', 'Depuración de trayectoria académica',
                "La [cargo] de Control Escolar solicita autorización para que sea depurada la trayectoria académica de la/del C. [nombre], con número de cuenta [cuenta], del Plan de Estudios [programa], quien cuenta con Certificado Total ([número]), con la finalidad de realizar la emisión del Certificado Electrónico.", 'Oficio con solicitud y evidencias', []],
            ['alumno', 'servicio_social', 'Exención de servicio social o prácticas profesionales',
                "El C. [nombre], con número de cuenta [cuenta], alumno del Plan de Estudios de [programa], solicita $c la autorización para la exención de la prestación del Servicio Social, con fundamento en lo dispuesto en los Artículos 18 y 20 del Reglamento de Servicio Social de la UAEMex, en virtud de contar con más de dos años ininterrumpidos laborando para [dependencia].", 'Oficio de solicitud / Comprobantes de pago', []],
            ['alumno', 'cambio_carrera', 'Reconocimiento de estudios por cambio de carrera o de plantel',
                "Se somete a consideración de $c la aprobación del reconocimiento de estudios para [cambio de licenciatura sin haber concluido estudios / cambio de Plantel], en favor de la/del C. [nombre], con número de cuenta [cuenta], para ingresar a la Licenciatura en [programa].", $of, ['Asignatura de origen', 'Asignatura equivalente']],
            ['alumno', 'baja', 'Baja extemporánea de asignatura',
                "La/El alumna(o) [nombre], con número de cuenta [cuenta], de la Licenciatura en [programa], solicita $c la baja extemporánea de la unidad de aprendizaje [materia], grupo [grupo], correspondiente al periodo [periodo], debido a motivos de [motivo].", $of, []],
            ['alumno', 'sicde', 'Validación o regularización de calificación en el SICDE',
                "La [cargo] de Control Escolar solicita autorización para validar la calificación de la asignatura [materia] de la/del egresada(o) [nombre], con número de cuenta [cuenta], del Plan de Estudios [programa], con la finalidad de dar de alta la calificación en el SICDE y proceder con la emisión del certificado correspondiente.", 'Oficio de solicitud con evidencias', []],
            ['alumno', 'titulacion', 'Opción de titulación o prórroga para titularse',
                "La/El pasante [nombre], con número de cuenta [cuenta], del Plan de Estudios [programa], solicita $c autorización para registrar su opción de titulación mediante la modalidad de [modalidad] / ampliación de plazo para titulación del tema titulado [tema].", $of, []],
            ['alumno', 'otro_alumno', 'Otro trámite de alumno',
                "El C. [nombre], con número de cuenta [cuenta], alumno del Plan de Estudios de [programa], solicita $c [lo que solicita].", $of, []],

            // ---- Comisiones académicas y evaluadoras (asuntos generales)
            ['general', 'programas_guias', 'Comisiones para programas de estudio o guías de evaluación',
                "La/El [cargo] solicita $c el aval a las comisiones propuestas para la elaboración / actualización de [Programas de Estudio / Guías de Evaluación] correspondientes al [n]° periodo del Plan de Estudios de la Licenciatura en [programa] [si aplica: dado que cuentan con opinión técnica favorable de la Dirección de Estudios Profesionales].", $of, ['Asignatura', 'Comisión', 'Correo', 'Responsable']],
            ['general', 'examen', 'Comisión de examen especial o de examen por competencias',
                "La/El [cargo] solicita $c el aval a la comisión integrada para la evaluación del [Examen Especial / Examen por Competencias] del periodo [periodo] de la unidad de aprendizaje [materia], solicitada por la/el alumna(o) [nombre], con número de cuenta [cuenta].", $of, ['Alumno', 'Número de cuenta', 'Unidad de aprendizaje', 'Comisión']],
            ['general', 'evaluacion_profesores', 'Comisiones para evaluar a profesores candidatos',
                "La/El [cargo] solicita $c el aval a las comisiones de profesores encargadas de evaluar a los docentes candidatos a impartir unidades de aprendizaje de la Licenciatura en [programa] para el periodo escolar [periodo].", $of, ['Unidad de aprendizaje', 'Profesor evaluado', 'Comisión']],
            ['general', 'admision', 'Comisiones del proceso de admisión a posgrado',
                "La [cargo] de Estudios Avanzados solicita $c el aval de las Comisiones formadas para el proceso de Admisión periodo [periodo] del programa de [programa].", $of, ['Aspirante', 'Comisión']],
            ['general', 'tesis_posgrado', 'Comisiones de evaluación de proyectos terminales o tesis de posgrado',
                "La [cargo] de Estudios Avanzados solicita $c el aval a la comisión de evaluación / el aval al cumplimiento de la comisión de evaluación de los Proyectos de [Trabajo Terminal de Grado / Tesis de Grado] del periodo [periodo] de los estudiantes de la Maestría / Doctorado en [programa].", $of, ['Alumno', 'Proyecto', 'Comité']],

            // ---- Asuntos generales y de gestión colegiada
            ['general', 'plantilla', 'Movimientos de plantilla docente',
                "La [cargo], Subdirectora Académica de esta Facultad, presenta $c los movimientos de plantilla de personal académico correspondientes al periodo [periodo] para su análisis y aprobación.", $of, []],
            ['general', 'visita', 'Visita académica institucional y apoyo de transporte',
                "La/El [cargo] solicita $c el aval para la realización de la visita académica a [lugar], programada para el día [fecha], para estudiantes de la Licenciatura en [programa], así como el apoyo de autobús institucional para su traslado.", $of, []],
            ['general', 'diplomado', 'Apertura de Diplomado Superior o de educación continua',
                "Se solicita $c la aprobación para la apertura del Diplomado Superior en [tema], que se desarrollará del [fecha] al [fecha] con una duración de [horas] horas, así como el aval a la plantilla de instructores propuesta.", $of, ['Módulo', 'Instructor', 'Horas']],
            ['general', 'fe_erratas', 'Fe de erratas de actas o acuerdos previos',
                "La [cargo] solicita $c autorización para realizar la Fe de erratas en el acuerdo asentado en el acta de la sesión [ordinaria/extraordinaria] de fecha [fecha], para quedar de la siguiente forma: donde dice «[texto]», debe decir «[texto]».", 'Acta del día [fecha], dice […] Debe decir […]', []],
            ['general', 'calendarios', 'Calendario escolar o calendarios de evaluación',
                "La [cargo], Subdirectora Académica, presenta el [Calendario Escolar / Calendarios de exámenes departamentales / Calendarios de Evaluación] de la Facultad de Ingeniería correspondiente al periodo escolar [periodo], para su revisión y, en su caso, aprobación por los H.H. Consejos Académico y de Gobierno.", 'Calendario propuesto (anexo con periodos de evaluación y suspensión de labores)', []],
            ['general', 'presea', 'Presea o postulación de aspirantes (mérito universitario)',
                "El [Dr./Dra. nombre], Director de la Facultad de Ingeniería, presenta [la propuesta de aspirante / el listado de postulantes] para participar en la convocatoria de la Presea “[nombre de la presea]”, versión [año], con base en el dictamen de la Comisión Especial del Mérito Universitario.", 'Dictamen de la Comisión Especial del Mérito Universitario', ['Candidato', 'Puntaje']],
            ['general', 'nota_laudatoria', 'Nota Laudatoria (solo Consejo de Gobierno)',
                "El [Dr./Dra. nombre], Director de la Facultad, presenta a consideración del H. Consejo de Gobierno a los candidatos a recibir la Nota Laudatoria [año], cuyos expedientes fueron revisados por la comisión designada por el H. Consejo Académico, obteniendo los puntajes indicados en la tabla adjunta. Asimismo, la Dirección General de Evaluación y Control de la Gestión Universitaria ha ratificado los puntajes obtenidos por la comisión, con base en el punto [número] de la Convocatoria.", 'Dictamen de la comisión / Puntajes ratificados', ['Candidato', 'Puntaje'], true],
            ['general', 'reconocimiento_admin', 'Reconocimiento al desempeño o nota al servicio administrativo',
                "La [cargo], Subdirectora Académica, presenta el dictamen de la comisión revisora de los expedientes de los solicitantes a participar en el [Reconocimiento al Desempeño Administrativo / Nota al Servicio Administrativo] [año] para su análisis y aprobación.", 'Dictamen de la comisión revisora', ['Solicitante', 'Puntaje']],
            ['general', 'informe', 'Informe o reporte para conocimiento',
                "La [cargo] presenta a los H.H. Consejos Académico y de Gobierno el [reporte con las Estadísticas de Titulación / Informe de actividades] correspondiente al periodo [periodo o del fecha al fecha], para su conocimiento.", 'Reporte (anexo)', []],
            ['general', 'plan_desarrollo', 'Plan de desarrollo de una licenciatura',
                "La/El [cargo] presenta a los H.H. Consejos Académico y de Gobierno el Plan de Desarrollo 2025-2029 de la Licenciatura en [programa] para su revisión y, en su caso, aprobación.", 'Plan de Desarrollo (anexo)', []],
            ['general', 'comision_gobierno', 'Comisión de Glosa o de Procesos Electorales (solo Consejo de Gobierno)',
                "Con base en la reglamentación vigente, el [Dr./Dra. nombre], Director de la Facultad de Ingeniería, solicita a este H. Consejo de Gobierno la conformación de la [Comisión de Glosa para el n° informe de actividades / Comisión de Procesos Electorales Ampliada para el Proceso de Elección de Titular de la Dirección para el periodo ordinario AAAA-AAAA].", $of, [], true],
            ['general', 'calificacion_eleccion', 'Auscultación y calificación de la elección de Director (solo Consejo de Gobierno)',
                "Con fundamento en el artículo 107 fracción III del Estatuto Universitario y la Base [número] de la Convocatoria para la Elección de Directora o Director de la Facultad de Ingeniería de la Universidad Autónoma del Estado de México, para el periodo ordinario [periodo], el [Dr./Dra. nombre], Presidente del H. Consejo de Gobierno, somete a consideración el acta de auscultación cuantitativa del citado proceso para su calificación y emisión del acuerdo correspondiente.", 'Acta de auscultación cuantitativa', [], true],
            ['general', 'otro_general', 'Otro asunto general',
                "La/El [cargo] solicita $c [lo que solicita].", $of, []],
        ];
        return array_map(static fn(array $f): array => [
            'clave' => $f[1], 'grupo' => $f[0], 'nombre' => $f[2], 'solicitud' => $f[3], 'antecedentes' => $f[4], 'tabla' => $f[5],
            'gobierno' => $f[6] ?? false, 'datos' => self::datos($f[3]),
        ], $filas);
    }

    /** Los datos que hay que llenar: lo que va entre [corchetes] (sin repetir). */
    private static function datos(string $texto): array
    {
        preg_match_all('/\[([^\[\]]+)\]/u', $texto, $m);
        return array_values(array_unique($m[1]));
    }

    /**
     * Todas las plantillas con el HTML ya armado (solicitud, antecedentes y, si aplica, tabla con sus encabezados). Las del grupo
     * del caso van primero y marcadas como «propia»; las demás quedan disponibles porque un mismo trámite puede presentarse en
     * otro apartado del acta.
     */
    public static function paraGrupo(string $grupo): array
    {
        $propias = [];
        $otras = [];
        foreach (self::todas() as $p) {
            $tabla = '';
            if ($p['tabla'] !== []) {
                $th = '';
                $td = '';
                foreach ($p['tabla'] as $col) {
                    $th .= '<td><b>' . htmlspecialchars($col, ENT_QUOTES, 'UTF-8') . '</b></td>';
                    $td .= '<td><br></td>';
                }
                $tabla = '<table><tbody><tr>' . $th . '</tr><tr>' . $td . '</tr></tbody></table><p><br></p>';
            }
            $fila = [
                'clave' => $p['clave'], 'grupo' => $p['grupo'], 'nombre' => $p['nombre'], 'propia' => $p['grupo'] === $grupo,
                'solicitud' => '<p>' . htmlspecialchars($p['solicitud'], ENT_QUOTES, 'UTF-8') . '</p>',
                'antecedentes' => '<p>' . htmlspecialchars($p['antecedentes'], ENT_QUOTES, 'UTF-8') . '</p>' . $tabla,
            ];
            if ($fila['propia']) {
                $propias[] = $fila;
            } else {
                $otras[] = $fila;
            }
        }
        return array_merge($propias, $otras);
    }
}
