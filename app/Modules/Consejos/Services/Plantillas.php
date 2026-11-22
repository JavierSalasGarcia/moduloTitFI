<?php

declare(strict_types=1);

namespace App\Modules\Consejos\Services;

/**
 * Plantillas de solicitud por tipo de trámite, tomadas de la redacción literal de las actas de 2026 (docs/CONSEJOS.md).
 * Los datos que cambian van entre corchetes; el formulario avisa si queda alguno sin llenar. El acuerdo no se plantilla aquí:
 * se arma al conducir la sesión repitiendo parte de la solicitud.
 */
final class Plantillas
{
    /** @return list<array{clave:string,grupo:string,nombre:string,solicitud:string,antecedentes:string,tabla:list<string>}> */
    public static function todas(): array
    {
        $c = 'a los H.H. Consejos Académico y de Gobierno';
        $filas = [
            ['profesor', 'sabatico_informe', 'Dictamen del informe de año sabático',
                "La Comisión revisora del informe de actividades de año sabático del [Dr./Dra. nombre] correspondiente al periodo [periodo], informa $c que después de analizar el informe de año sabático, la comisión determina: “[dictamen de la comisión]”.", 'Oficio dictamen', []],
            ['profesor', 'sabatico_comision', 'Informe de año sabático y comisión revisora',
                "El [Dr./Dra. nombre], profesor de tiempo completo de esta Facultad, presenta $c su informe de actividades de año sabático correspondiente al periodo [periodo] para su revisión.", 'Oficio de solicitud / Informe de actividades', []],
            ['profesor', 'estancia', 'Estancia académica o de investigación',
                "El [Dr./Dra. nombre], [cargo] de esta Facultad, solicita $c su autorización para realizar una estancia académica en [institución] durante el periodo comprendido del [fechas], cuyo propósito es [propósito o proyecto].", 'Oficio de solicitud / Plan de trabajo', []],
            ['profesor', 'salida_capitulo', 'Salida o visita académica de capítulo estudiantil',
                "El [Dr./Dra. nombre], [cargo] representante del Capítulo Estudiantil [nombre del capítulo], solicita $c la autorización para la salida indicada a continuación.", 'Oficio de solicitud', ['Destino', 'Fechas', 'Número de alumnos', 'Apoyo solicitado']],
            ['alumno', 'homologacion', 'Carta de homologación preliminar (movilidad)',
                "La [cargo], [área] de esta Facultad, solicita el aval a la Carta de Homologación preliminar de asignaturas del alumno [nombre] con número de cuenta [cuenta] de la Licenciatura de [programa], quien desea realizar movilidad [nacional/internacional] en la [institución] en el periodo [periodo].", 'Oficio de solicitud', ['Asignatura de origen', 'Asignatura en destino']],
            ['alumno', 'depuracion', 'Depuración de trayectoria académica',
                "La [cargo], Jefa del Departamento de Control Escolar solicita autorización para que sea depurada la trayectoria académica del C. [nombre] con número de cuenta [cuenta] del Plan de Estudios [programa] quien ya cuenta con número de Certificado Total ([número]) con la finalidad de realizar la emisión del Certificado Electrónico.", 'Oficio con solicitud y evidencias', []],
            ['alumno', 'servicio_social', 'Exención de servicio social',
                "El C. [nombre], con número de cuenta [cuenta] alumno del Plan de Estudios de [programa], solicita $c la autorización para la exención de la prestación del Servicio Social con fundamento en lo dispuesto en los Artículos [artículos] del Reglamento de Servicio Social de la UAEMex. En virtud a que tiene [antigüedad] laborando para [dependencia] adjunta comprobantes de pago.", 'Oficio de solicitud', []],
            ['alumno', 'otro_alumno', 'Otro trámite de alumno con número de cuenta',
                "El C. [nombre], con número de cuenta [cuenta] alumno del Plan de Estudios de [programa], solicita $c [lo que solicita].", 'Oficio de solicitud', []],
            ['general', 'examen_especial', 'Comisión de examen especial',
                "La [cargo], Coordinadora de Docencia de [programa] solicita $c el aval a la comisión integrada para la evaluación de Examen Especial periodo [periodo] del alumno listado a continuación:", 'Oficio de solicitud', ['Alumno', 'Número de cuenta', 'Unidad de aprendizaje', 'Comisión']],
            ['general', 'competencias', 'Comisión de examen por competencias',
                "La [cargo] solicita $c el aval a la comisión integrada para la evaluación de Exámenes por Competencias del periodo [periodo] del alumno listado a continuación:", 'Oficio de solicitud', ['Alumno', 'Número de cuenta', 'Unidad de aprendizaje', 'Comisión']],
            ['general', 'evaluacion_profesores', 'Comisiones para evaluar a profesores',
                "La [cargo], Coordinadora de Docencia de [programa] solicita $c el aval a las comisiones que evaluaron a los docentes que se integran a la plantilla de la Licenciatura de [programa] para el periodo [periodo].", 'Oficio de solicitud', ['Unidad de aprendizaje', 'Profesor evaluado', 'Comisión']],
            ['general', 'programas', 'Comisiones para programas de estudio',
                "La [cargo], Coordinadora de Docencia de [programa] solicita $c el aval a la integración de las comisiones para la elaboración de Programas de Estudios correspondientes al [n]° periodo del Plan de estudios de [programa].", 'Oficio de solicitud', ['Asignatura', 'Comisión', 'Correo', 'Responsable']],
            ['general', 'guias', 'Guías de evaluación',
                "La [cargo], Coordinadora de Docencia de la Licenciatura de [programa] solicita $c la aprobación de las Guías de Evaluación correspondientes al [n]° semestre de la Licenciatura de [programa] para que se pueda brindar el aval dado que ya fueron revisadas, validadas y cuentan con opinión técnica favorable por la Dirección de Estudios Profesionales.", 'Oficio de solicitud', []],
            ['general', 'admision', 'Comisiones del proceso de admisión a posgrado',
                "La [cargo], Coordinadora de Estudios Avanzados de esta Facultad solicita $c el aval de las Comisiones formadas para el proceso de Admisión periodo [periodo] de la Maestría en [programa].", 'Oficio de solicitud', ['Aspirante', 'Comisión']],
            ['general', 'tesis_posgrado', 'Comisiones de evaluación de proyectos de tesis o trabajo terminal',
                "La [cargo], Coordinadora de Estudios Avanzados de esta Facultad solicita $c el aval a las comisiones de evaluación de los Proyectos de [Tesis de Grado / Trabajo Terminal de Grado] periodo [periodo] de los estudiantes del [programa].", 'Oficio de solicitud', ['Alumno', 'Proyecto', 'Comité']],
            ['general', 'plantilla', 'Movimientos de plantilla docente',
                "La [cargo], Subdirectora Académica de la Facultad de Ingeniería solicita $c los movimientos de plantilla de personal académico correspondientes al periodo [periodo] para su análisis y aprobación.", 'Oficio de solicitud', []],
            ['general', 'fe_erratas', 'Fe de erratas',
                "La [cargo] solicita $c autorización para realizar la Fe de erratas, en el acta [ordinaria/extraordinaria] del [fecha] y dar por asentada con el presente acuerdo.", 'Acta del día [fecha], dice […] Debe decir […]', []],
        ];
        return array_map(static fn(array $f): array => [
            'clave' => $f[1], 'grupo' => $f[0], 'nombre' => $f[2], 'solicitud' => $f[3], 'antecedentes' => $f[4], 'tabla' => $f[5],
        ], $filas);
    }

    /** Las plantillas de un grupo de casos, con el HTML ya armado (solicitud, antecedentes y tabla vacía con sus encabezados). */
    public static function paraGrupo(string $grupo): array
    {
        $r = [];
        foreach (self::todas() as $p) {
            if ($p['grupo'] !== $grupo) {
                continue;
            }
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
            $r[] = [
                'clave' => $p['clave'], 'nombre' => $p['nombre'],
                'solicitud' => '<p>' . htmlspecialchars($p['solicitud'], ENT_QUOTES, 'UTF-8') . '</p>' . $tabla,
                'antecedentes' => '<p>' . htmlspecialchars($p['antecedentes'], ENT_QUOTES, 'UTF-8') . '</p>',
            ];
        }
        return $r;
    }
}
