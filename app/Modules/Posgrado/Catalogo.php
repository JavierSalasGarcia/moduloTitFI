<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

/**
 * Catálogos del trámite de evaluación de grado (fuente: formulariosPosgrado/, «REQUISITOS EVALUACION DE GRADO CEA-FI»,
 * «PROCEDIMIENTO EVALUACION DE GRADO» y «PUNTOS A CONSIDERAR»).
 */
final class Catalogo
{
    /** Costos vigentes de los recibos (requisitos 9, 10 y 11). */
    public const COSTOS = ['examen' => '$5,710.00', 'titulo' => '$1,787.00', 'certificado' => '$589.00'];

    public const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    public const DIAS = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    /** Mínimo de días hábiles entre hoy y la fecha propuesta, y lo que se sugiere. */
    public const DIAS_MINIMOS = 10;
    public const DIAS_SUGERIDOS = 15;

    public static function niveles(): array
    {
        return ['maestria' => 'Maestría', 'doctorado' => 'Doctorado', 'especialidad' => 'Especialidad'];
    }

    /** Modalidades del trabajo de grado, en el orden del formato de integración de expediente. */
    public static function modalidades(): array
    {
        return [
            'proyecto_terminal' => 'Proyecto terminal',
            'tesis' => 'Tesis',
            'trabajo_terminal' => 'Trabajo terminal de grado',
            'articulos' => 'Tesis por artículos especializados o capítulo para libro',
        ];
    }

    /** Cómo se nombra el trabajo dentro de un oficio: «su tesis», «su trabajo terminal de grado». */
    public static function trabajo(string $modalidad): string
    {
        return match ($modalidad) {
            'trabajo_terminal' => 'trabajo terminal de grado',
            'proyecto_terminal' => 'proyecto terminal',
            'articulos' => 'tesis por artículos especializados',
            default => 'tesis',
        };
    }

    public static function tiposMiembro(): array
    {
        return [
            'director' => 'Director de tesis',
            'codirector' => 'Co-Director de tesis',
            'tutor' => 'Tutor',
            'nab' => 'Miembro del NAB del programa',
            'profesor' => 'Profesor de la UAEMéx',
            'externo' => 'Evaluador externo',
        ];
    }

    /** Etiqueta del tipo de miembro como va en los oficios («Miembro del NAB de la MMT»). */
    public static function tipoMiembro(string $tipo, string $siglas = ''): string
    {
        if ($tipo === 'nab' && $siglas !== '') {
            return 'Miembro del NAB de la ' . $siglas;
        }
        return self::tiposMiembro()[$tipo] ?? $tipo;
    }

    public static function resultados(): array
    {
        return ['unanimidad' => 'Aprobado por unanimidad', 'mayoria' => 'Aprobado por mayoría', 'aplazado' => 'Aplazado'];
    }

    public static function estados(): array
    {
        return ['abierto' => 'En proceso', 'concluido' => 'Concluido', 'cancelado' => 'Cancelado', 'abandonado' => 'Abandonado (en pausa)'];
    }

    /** Los cuatro momentos, como se muestran al egresado. */
    public static function momentos(): array
    {
        return [
            1 => ['titulo' => 'Reúne tus requisitos', 'breve' => 'Requisitos', 'texto' => 'Captura tus datos, sube tus documentos en PDF y lleva los originales a Apoyo a Grado para el cotejo.'],
            2 => ['titulo' => 'Propón sínodo y fecha', 'breve' => 'Sínodo y fecha', 'texto' => 'Propón a los siete integrantes de tu sínodo y la fecha de tu examen; tu director y tu sínodo firman en línea desde su correo.'],
            3 => ['titulo' => 'Invitaciones al sínodo', 'breve' => 'Invitaciones', 'texto' => 'La Coordinación firma las invitaciones; cada integrante recibe la suya por correo y acusa de recibido en línea.'],
            4 => ['titulo' => 'Examen y cierre', 'breve' => 'Examen y cierre', 'texto' => 'Presentas tu examen, registras tu grado en el SICDE y Apoyo a Grado integra tu expediente.'],
        ];
    }

    /** Grupos de requisitos, en el orden en que se muestran. */
    public static function grupos(): array
    {
        return [
            'identidad' => 'Identidad y antecedentes académicos',
            'pagos' => 'Pagos',
            'trabajo' => 'Trabajo de grado',
            'constancias' => 'Constancias y no adeudos',
            'fisico' => 'Entregas en físico',
            'formatos' => 'Formatos',
        ];
    }

    /**
     * Requisitos para examen de grado (26, más la evidencia de publicación del doctorado).
     * aplica: todos | doctorado | art53. tipo: pdf | fisico | generado. cuenta=false: no lo entrega el egresado antes del cotejo.
     * @return array<string,array{n:string,grupo:string,nombre:string,ayuda:string,aplica:string,tipo:string,formato?:string,cuenta?:bool,nota?:string}>
     */
    public static function requisitos(): array
    {
        $c = self::COSTOS;
        $originales = 'Al cotejo lleva el original y 2 copias.';
        return [
            'acta_nacimiento' => ['n' => '1', 'grupo' => 'identidad', 'nombre' => 'Acta de nacimiento', 'ayuda' => 'Con fecha de expedición no mayor a 6 meses. Al cotejo lleva 2 copias.', 'aplica' => 'todos', 'tipo' => 'pdf'],
            'certificado_parcial' => ['n' => '2', 'grupo' => 'identidad', 'nombre' => 'Certificado parcial del posgrado', 'ayuda' => $originales, 'aplica' => 'todos', 'tipo' => 'pdf'],
            'certificado_licenciatura' => ['n' => '3', 'grupo' => 'identidad', 'nombre' => 'Certificado de licenciatura', 'ayuda' => $originales, 'aplica' => 'todos', 'tipo' => 'pdf'],
            'certificado_maestria' => ['n' => '4', 'grupo' => 'identidad', 'nombre' => 'Certificado de maestría', 'ayuda' => $originales, 'aplica' => 'doctorado', 'tipo' => 'pdf'],
            'cedula_licenciatura' => ['n' => '5', 'grupo' => 'identidad', 'nombre' => 'Cédula profesional de licenciatura', 'ayuda' => $originales, 'aplica' => 'todos', 'tipo' => 'pdf'],
            'cedula_maestria' => ['n' => '6', 'grupo' => 'identidad', 'nombre' => 'Cédula profesional de maestría', 'ayuda' => $originales, 'aplica' => 'doctorado', 'tipo' => 'pdf'],
            'curp' => ['n' => '22', 'grupo' => 'identidad', 'nombre' => 'CURP', 'ayuda' => 'Impresión actual con la leyenda «Verificada por el Registro Civil».', 'aplica' => 'todos', 'tipo' => 'pdf'],

            'pago_examen' => ['n' => '9', 'grupo' => 'pagos', 'nombre' => 'Pago de derechos de examen recepcional', 'ayuda' => 'Recibo de ' . $c['examen'] . '. La referencia se pide en Control Escolar de la F.I. ' . $originales, 'aplica' => 'todos', 'tipo' => 'pdf', 'nota' => 'Los pagos solo valen para presentar el examen en el mismo año en que se expiden y pagan.'],
            'pago_titulo' => ['n' => '10', 'grupo' => 'pagos', 'nombre' => 'Pago de expedición de título de grado', 'ayuda' => 'Recibo de ' . $c['titulo'] . '. La referencia se pide en Control Escolar de la F.I. ' . $originales, 'aplica' => 'todos', 'tipo' => 'pdf'],
            'pago_certificado' => ['n' => '11', 'grupo' => 'pagos', 'nombre' => 'Hoja de referencia de pago del certificado total', 'ayuda' => 'Recibo de ' . $c['certificado'] . '. La referencia se pide en Control Escolar de la F.I. ' . $originales, 'aplica' => 'todos', 'tipo' => 'pdf'],

            'registro_protocolo' => ['n' => '21', 'grupo' => 'trabajo', 'nombre' => 'Oficio-constancia de registro de protocolo de tesis', 'ayuda' => 'El título de tu trabajo debe ser igual al de esta constancia.', 'aplica' => 'todos', 'tipo' => 'pdf'],
            'certificado_autenticidad' => ['n' => '19', 'grupo' => 'trabajo', 'nombre' => 'Certificado de autenticidad del trabajo', 'ayuda' => 'Lo emite el coordinador de tu programa.', 'aplica' => 'todos', 'tipo' => 'pdf'],
            'articulo_aceptado' => ['n' => 'D', 'grupo' => 'trabajo', 'nombre' => 'Evidencia de artículo aceptado o capítulo de libro', 'ayuda' => 'Artículo de investigación original aceptado por una revista especializada arbitrada e indexada de reconocimiento internacional, o capítulo de libro.', 'aplica' => 'doctorado', 'tipo' => 'pdf', 'nota' => 'Art. 60 Ter, fracciones III, IV y V del REA y normatividad 15.10 del DCI.'],
            'autorizacion_art53' => ['n' => '8', 'grupo' => 'trabajo', 'nombre' => 'Autorización para continuar con los trámites de obtención de grado', 'ayuda' => 'La solicitas al coordinador de tu programa. Al cotejo lleva el original.', 'aplica' => 'art53', 'tipo' => 'pdf', 'nota' => 'Art. 53 del Reglamento de Estudios Avanzados.'],

            'no_adeudo_biblioteca' => ['n' => '16', 'grupo' => 'constancias', 'nombre' => 'Constancia de no adeudo bibliotecario', 'ayuda' => 'Se tramita directamente en la biblioteca de la F.I.', 'aplica' => 'todos', 'tipo' => 'pdf'],
            'donacion_libro' => ['n' => '17', 'grupo' => 'constancias', 'nombre' => 'Oficio de donación del libro', 'ayuda' => 'Se tramita en la biblioteca de la F.I.', 'aplica' => 'todos', 'tipo' => 'pdf'],
            'no_adeudo_mobiliario' => ['n' => '18', 'grupo' => 'constancias', 'nombre' => 'Constancia de no adeudo de mobiliario y equipo', 'ayuda' => 'La emite Apoyo a Grado y la firma en línea la Coordinación de Estudios Avanzados; aparecerá aquí cuando esté firmada.', 'aplica' => 'todos', 'tipo' => 'generado', 'formato' => 'no_adeudo'],
            'cvu' => ['n' => '20', 'grupo' => 'constancias', 'nombre' => 'Constancia del llenado del CVU', 'ayuda' => 'Avalada por el coordinador de tu programa.', 'aplica' => 'todos', 'tipo' => 'pdf'],
            'folio_egresados' => ['n' => '23', 'grupo' => 'constancias', 'nombre' => 'Folio del Sistema de Seguimiento de Egresados de posgrado', 'ayuda' => 'Se obtiene en alumniversitario.uaemex.mx/posgrado. Si el sistema falla, pide por correo a la Dirección de Alumniversitario una constancia de la falla.', 'aplica' => 'todos', 'tipo' => 'pdf'],

            'usb' => ['n' => '12', 'grupo' => 'fisico', 'nombre' => '3 memorias USB con tu trabajo de grado', 'ayuda' => 'Cada USB con la tesis o trabajo terminal y tus requisitos escaneados, cada uno en un PDF por separado.', 'aplica' => 'todos', 'tipo' => 'fisico'],
            'resumen' => ['n' => '13', 'grupo' => 'fisico', 'nombre' => 'Resumen impreso del trabajo', 'ayuda' => 'De no más de cinco cuartillas.', 'aplica' => 'todos', 'tipo' => 'fisico'],
            'fotos_ovalo' => ['n' => '14', 'grupo' => 'fisico', 'nombre' => '7 fotografías tamaño óvalo miñón', 'ayuda' => 'Blanco y negro, papel mate opalina, fondo blanco, de frente. Saco negro, camisa blanca y corbata lisa negra, o blazer negro y blusa blanca, aretes pequeños y sin cabello en la frente.', 'aplica' => 'todos', 'tipo' => 'fisico'],
            'fotos_semititulo' => ['n' => '15', 'grupo' => 'fisico', 'nombre' => '2 fotografías tamaño semitítulo', 'ayuda' => 'Blanco y negro, papel mate, fondo gris claro, de frente con retoque. Camisa blanca, saco y corbata oscuros, o blazer oscuro y blusa blanca, aretes pequeños y sin cabello en la frente.', 'aplica' => 'todos', 'tipo' => 'fisico'],

            'voto_aprobatorio' => ['n' => '7', 'grupo' => 'formatos', 'nombre' => 'Voto aprobatorio de tu director', 'ayuda' => 'Tu director lo firma en línea desde su correo: la plataforma llena el formato y le envía su enlace personal. No hay que imprimir ni subir nada.', 'aplica' => 'todos', 'tipo' => 'firma', 'formato' => 'voto'],
            'datos_interesado' => ['n' => '24', 'grupo' => 'formatos', 'nombre' => 'Formato de datos del interesado', 'ayuda' => 'Se llena con tus datos. Revísalo y fírmalo en línea con el botón «Firmar mi formato».', 'aplica' => 'todos', 'tipo' => 'generado', 'formato' => 'datos_interesado'],
            'formato_revision' => ['n' => '26', 'grupo' => 'formatos', 'nombre' => 'Formato de revisión para autorización de evaluación', 'ayuda' => 'Te lo proporciona y lo llena Apoyo a Grado durante el cotejo.', 'aplica' => 'todos', 'tipo' => 'fisico'],
            'integracion' => ['n' => '25', 'grupo' => 'formatos', 'nombre' => 'Formato de integración de expediente', 'ayuda' => 'Lo prepara Apoyo a Grado al final, al integrar tu expediente.', 'aplica' => 'todos', 'tipo' => 'generado', 'formato' => 'integracion', 'cuenta' => false],
        ];
    }

    /** ¿El requisito aplica al trámite? */
    public static function aplica(array $req, string $nivel, bool $art53): bool
    {
        return match ($req['aplica']) {
            'doctorado' => $nivel === 'doctorado',
            'art53' => $art53,
            default => true,
        };
    }

    /**
     * Documentos que se firman en línea: nombre, cómo se nombra en un correo y quién firma.
     * @return array<string,array{nombre:string,que:string,firman:string[]}>
     */
    public static function firmables(): array
    {
        return [
            'voto' => ['nombre' => 'Voto aprobatorio', 'que' => 'el voto aprobatorio', 'firman' => ['director']],
            'datos_interesado' => ['nombre' => 'Formato de datos del interesado', 'que' => 'el formato de datos del interesado', 'firman' => ['egresado']],
            'solicitud' => ['nombre' => 'Solicitud de evaluación de grado', 'que' => 'la solicitud de evaluación de grado', 'firman' => ['director']],
            'visto_bueno' => ['nombre' => 'Visto bueno del sínodo', 'que' => 'el visto bueno para la evaluación de grado', 'firman' => ['sinodal']],
            'cotejo' => ['nombre' => 'Oficio de cotejo', 'que' => 'el oficio de cotejo', 'firman' => ['coordinacion']],
            'invitaciones' => ['nombre' => 'Oficios de invitación al sínodo', 'que' => 'los oficios de invitación', 'firman' => ['coordinacion']],
            'acuse' => ['nombre' => 'Acuse de recibido de oficios', 'que' => 'el acuse de recibido de su oficio de invitación', 'firman' => ['coordinacion', 'sinodal']],
            'notificacion' => ['nombre' => 'Notificación al director de tesis', 'que' => 'la notificación al director', 'firman' => ['coordinacion']],
            'constancias_revisores' => ['nombre' => 'Constancias de revisores', 'que' => 'las constancias de revisores', 'firman' => ['coordinacion']],
            'constancias_direccion' => ['nombre' => 'Constancias de director, codirector y tutor', 'que' => 'las constancias de dirección', 'firman' => ['coordinacion']],
            'no_adeudo' => ['nombre' => 'Constancia de no adeudo de equipo y mobiliario', 'que' => 'la constancia de no adeudo', 'firman' => ['coordinacion']],
            'integracion' => ['nombre' => 'Formato de integración de expediente', 'que' => 'el formato de integración de expediente', 'firman' => ['coordinacion']],
        ];
    }

    /** Preguntas del visto bueno del sínodo, en el orden de las columnas del formato. */
    public static function preguntasVistoBueno(): array
    {
        return ['concluida' => 'Tesis concluida', 'entregada' => 'Tesis entregada a usted', 'periodo' => 'Revisión en el periodo solicitado'];
    }

    /**
     * Vistas previas que el egresado (y el personal) pueden abrir antes de pedir las firmas; no se firman ni se suben:
     * lo que se firma es el documento que la plataforma genera una sola vez al pedir las firmas.
     */
    public static function formatosEgresado(): array
    {
        return [
            'voto' => 'Voto aprobatorio (R 7)',
            'datos_interesado' => 'Formato de datos del interesado (R 24)',
            'solicitud' => 'Solicitud de evaluación de grado',
            'visto_bueno' => 'Visto bueno del sínodo',
        ];
    }

    /** Oficios y constancias que emite el personal y se guardan en el expediente. */
    public static function emitibles(): array
    {
        return [
            'cotejo' => ['nombre' => 'Oficio de cotejo', 'momento' => 1],
            'invitaciones' => ['nombre' => 'Oficios de invitación al sínodo', 'momento' => 3],
            'acuse' => ['nombre' => 'Acuse de recibido de oficios', 'momento' => 3],
            'notificacion' => ['nombre' => 'Notificación al director de tesis', 'momento' => 3],
            'constancias_revisores' => ['nombre' => 'Constancias de revisores', 'momento' => 4],
            'constancias_direccion' => ['nombre' => 'Constancias de director, codirector y tutor', 'momento' => 4],
            'no_adeudo' => ['nombre' => 'Constancia de no adeudo de equipo y mobiliario', 'momento' => 1],
            'integracion' => ['nombre' => 'Formato de integración de expediente', 'momento' => 4],
        ];
    }

    /** Presentación del estado de un documento: [clase CSS, icono, texto]. */
    public static function estadoDocumento(string $estado): array
    {
        return match ($estado) {
            'subido' => ['pos-e-rev', 'reloj', 'En revisión'],
            'validado' => ['pos-e-ok', 'ok', 'Validado'],
            'recibido' => ['pos-e-ok', 'ok', 'Recibido'],
            'listo' => ['pos-e-ok', 'ok', 'Listo'],
            'devuelto' => ['pos-e-mal', 'alerta', 'Corregir'],
            'en_firma' => ['pos-e-rev', 'reloj', 'En firma'],
            'firmado' => ['pos-e-ok', 'ok', 'Firmado'],
            'por_firmar' => ['pos-e-pend', '', 'Por firmar'],
            'por_pedir' => ['pos-e-pend', '', 'Por pedir'],
            'al_final' => ['pos-e-na', '', 'Al final'],
            default => ['pos-e-pend', '', 'Pendiente'],
        };
    }

    /** Grados más comunes para el director y el sínodo. */
    public static function grados(): array
    {
        return ['Dr.', 'Dra.', 'Mtro.', 'Mtra.', 'M. en C.', 'M. en I.', 'Ing.'];
    }
}
