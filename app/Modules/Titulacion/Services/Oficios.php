<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Persona;
use App\Modules\Titulacion\Catalogo;

/** Oficios del departamento sobre la hoja membretada de la Facultad. */
final class Oficios
{
    /** Oficios que firma la Subdirección o la Dirección: clave del documento => nombre. */
    public const TIPOS = [
        'oficio_aprobacion_tema' => 'Oficio de aprobación de tema y modalidad',
        'oficio_estructura' => 'Oficio de estructura capitular',
        'oficio_impresion' => 'Oficio de autorización de impresión',
        'carta_afinidad' => 'Carta de afinidad',
        'oficio_sinodal' => 'Oficio de citación a sinodal',
        'constancia' => 'Constancia de titulación',
        'constancia_estatus' => 'Informe sobre el estatus del protocolo',
        'oficio_prorroga' => 'Oficio de aprobación de prórroga',
    ];

    /** Oficios que firma el Director (el resto, el Subdirector). */
    public const DIRECCION = ['oficio_estructura'];

    public const EXAMEN = ['previo' => 'examen previo', 'definitivo' => 'examen definitivo', 'protesta' => 'toma de protesta'];

    private static function libs(): void
    {
        $lib = dirname(APP_RAIZ) . '/lib';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $lib . '/fpdf/font/');
        }
        require_once $lib . '/fpdf/fpdf.php';
        require_once $lib . '/fpdi/autoload.php';
    }

    private static function l1(string $s): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?: $s;
    }

    public static function fecha(?string $iso): string
    {
        $f = $iso ? \DateTimeImmutable::createFromFormat('!Y-m-d', $iso) : false;
        return $f ? $f->format('j') . ' de ' . Catalogo::MESES[(int) $f->format('n')] . ' de ' . $f->format('Y') : '____ de ________ de ____';
    }

    public static function director(): string
    {
        $a = Autoridades::director();
        return $a ? mb_strtoupper(Persona::tratamiento($a)) : 'DR. MARCELO ROMERO HUERTAS';
    }

    private static function cargoDirector(): string
    {
        return Persona::f(Autoridades::director() ?? [], 'Director', 'Directora');
    }

    /** Hoja membretada como fondo de cada página y el ID del proceso en la esquina inferior derecha. */
    private static function nuevo(string $folio = ''): Hoja
    {
        $pdf = Hoja::nueva($folio);
        $pdf->SetAutoPageBreak(true, 30);
        return $pdf;
    }

    private static function cabecera(\FPDF $pdf, string $fechaLinea): void
    {
        $pdf->SetY(40);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Cell(0, 5, self::l1($fechaLinea), 0, 1, 'R');
        $pdf->Ln(6);
    }

    /** Bloque de firma; devuelve la caja {p,x,y,w,h} (puntos) del nombre impreso. */
    private static function firma(\FPDF $pdf, string $nombre, array $cargo, bool $lema, float $espacio = 26.0, float $limite = 175.0): array
    {
        if ($pdf->GetY() > $limite) {
            $pdf->AddPage('P', 'Letter');
            $pdf->SetY(40);
        }
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5, 'A T E N T A M E N T E', 0, 1, 'C');
        $pdf->Cell(0, 5, self::l1('PATRIA, CIENCIA Y TRABAJO'), 0, 1, 'C');
        $texto = trim((string) \ajuste('institucion_lema_anual', ''));
        if ($lema && $texto !== '') {
            $pdf->SetFont('Helvetica', 'I', 8.5);
            $pdf->Cell(0, 4.5, self::l1('"' . date('Y') . ', ' . $texto . '"'), 0, 1, 'C');
        }
        $pdf->Ln($espacio);
        $y = $pdf->GetY();
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(65, $y - 1, 150, $y - 1);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5, self::l1($nombre), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 10);
        foreach ($cargo as $c) {
            $pdf->Cell(0, 4.8, self::l1($c), 0, 1, 'C');
        }
        $mm = 72 / 25.4;
        return ['p' => $pdf->PageNo() - 1, 'x' => 65 * $mm, 'y' => $y * $mm, 'w' => 85 * $mm, 'h' => 11.0];
    }

    private static function asesor(array $r): string
    {
        $n = trim((string) ($r['asesor'] ?? ''));
        return $n === '' ? '' : Persona::tratamiento(['nombre' => $n, 'grado' => $r['asesor_grado'] ?? null]);
    }

    /** Datos comunes del trámite. */
    private static function datos(array $t): array
    {
        $d = $t['datos'];
        $p = $d['datos'] ?? [];
        return [
            'nombre' => mb_strtoupper((string) ($p['nombre'] ?? '')),
            'cuenta' => (string) ($p['cuenta'] ?? ''),
            'carrera' => Catalogo::carreras()[$p['carrera'] ?? '']['nombre'] ?? '',
            'modalidad' => Catalogo::modalidad((string) $t['modalidad'])['nombre'] ?? '',
            'titulo' => (string) ($d['revision']['titulo'] ?? $t['titulo'] ?? $d['protocolo']['titulo'] ?? ''),
            'asesor' => self::asesor($d['protocolo'] ?? []),
            'pasante' => Persona::porId((int) $t['alumno_id']) ?? [],
        ];
    }

    /**
     * Oficio firmado por la Subdirección o la Dirección.
     * @param array $extra para 'oficio_sinodal': ['sinodal' => fila, 'asig' => fila]
     * @return array{0:string,1:array} [PDF, caja de firma]
     */
    public static function generar(string $clave, array $tramite, array $datosHito, array $extra = []): array
    {
        if (!isset(self::TIPOS[$clave]) || in_array($clave, ['oficio_aprobacion_tema', 'constancia', 'constancia_estatus', 'oficio_prorroga'], true)) {
            throw new \RuntimeException('Oficio desconocido.');   // estos dos tienen su propio generador
        }
        $x = self::datos($tramite);
        $hoy = self::fecha(date('Y-m-d'));
        $fecha = self::fecha($datosHito['fecha'] ?? null);
        $pdf = self::nuevo((string) ($tramite['folio'] ?? ''));
        $direccion = in_array($clave, self::DIRECCION, true);

        if ($clave === 'oficio_estructura') {
            self::cabecera($pdf, 'Ciudad Universitaria, Toluca México; a ' . $hoy);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->Cell(0, 5.5, self::l1($x['nombre']), 0, 1);
            $pdf->Cell(0, 5.5, 'P r e s e n t e .', 0, 1);
            $pdf->Ln(8);
            $pdf->SetFont('Helvetica', '', 11);
            $dirigida = $x['asesor'] !== '' ? ', dirigida por ' . $x['asesor'] : '';
            $pdf->MultiCell(0, 5.8, self::l1('A continuación transcribo el tema y capitulado aprobado por esta Institución para el desarrollo de su ' . mb_strtolower($x['modalidad']) . $dirigida . ', siendo de la siguiente manera:'), 0, 'J');
            $pdf->Ln(5);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->MultiCell(0, 5.8, self::l1('"' . mb_strtoupper($x['titulo']) . '"'), 0, 'C');
            $pdf->Ln(4);
            $pdf->SetFont('Helvetica', '', 10.5);
            foreach (['Resumen', 'Abstract', 'Índices'] as $l) {
                $pdf->SetX(55);
                $pdf->Cell(0, 5.2, self::l1($l), 0, 1);
            }
            $caps = array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) ($tramite['datos']['sustentacion']['capitulado'] ?? '')) ?: []), static fn(string $l): bool => $l !== ''));
            foreach ($caps as $i => $c) {
                $pdf->SetX(25);
                $pdf->Cell(28, 5.2, self::l1('Capítulo ' . ($i + 1) . '.'), 0, 0);
                $pdf->MultiCell(0, 5.2, self::l1($c), 0, 'L');
            }
            $pdf->SetX(55);
            $pdf->Cell(0, 5.2, self::l1('Apéndices'), 0, 1);
            $pdf->Ln(4);
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->MultiCell(0, 5.8, self::l1('Asimismo, para la aprobación del trabajo y demás trámites deberá sujetarse al reglamento respectivo de esta Institución.'), 0, 'J');
            $caja = self::firma($pdf, self::director(), [self::cargoDirector(), 'FACULTAD DE INGENIERÍA', 'U.A.E.M.'], true);
            $pdf->SetFont('Helvetica', '', 9);
            $pdf->Cell(0, 6, 'c.c.p. Archivo.', 0, 1);
            return [$pdf->Output('S'), $caja];
        }

        if ($clave === 'oficio_sinodal') {
            $s = $extra['sinodal'];
            $a = $extra['asig'];
            $examen = self::EXAMEN[$a['tipo']] ?? 'examen';
            self::cabecera($pdf, 'Ciudad Universitaria, Toluca México; a ' . $hoy);
            $pdf->SetFont('Helvetica', 'B', 11);
            $s = Persona::completar($s, (string) $s['email']);
            $pdf->Cell(0, 5.5, self::l1(mb_strtoupper(Persona::tratamiento($s))), 0, 1);
            $pdf->Cell(0, 5.5, 'P r e s e n t e .', 0, 1);
            $pdf->Ln(8);
            $pdf->SetFont('Helvetica', '', 11);
            $cuerpo = 'Por este medio le comunico que, a propuesta de la Coordinación de Docencia de la Licenciatura en ' . $x['carrera'] . ', fue ' . Persona::f($s, 'designado', 'designada') . ' como sinodal ' . ($s['calidad'] === 'suplente' ? 'suplente' : Persona::f($s, 'propietario', 'propietaria'))
                . ' para el ' . $examen . ' de ' . $x['nombre'] . ' (cuenta ' . $x['cuenta'] . '), opción «' . $x['modalidad'] . '»'
                . ($x['titulo'] !== '' && !in_array($tramite['modalidad'], ['aprovechamiento', 'egel', 'creditos'], true) ? ', con el trabajo «' . $x['titulo'] . '»' : '') . '.';
            $pdf->MultiCell(0, 5.8, self::l1($cuerpo), 0, 'J');
            $pdf->Ln(4);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->MultiCell(0, 5.8, self::l1(ucfirst($examen) . ': ' . self::fecha($a['fecha']) . ', a las ' . $a['hora'] . ' horas, en ' . $a['lugar'] . '.'), 0, 'J');
            $pdf->Ln(4);
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->MultiCell(0, 5.8, self::l1('Le ruego confirmar de recibido desde el enlace del correo que acompaña este oficio, donde también podrá descargar el trabajo escrito. Para el pago del examen definitivo, los trámites deben realizarse fuera del horario de labores de la UAEM.'), 0, 'J');
            $caja = self::firma($pdf, mb_strtoupper(Formatos::subdirector()), [Formatos::cargoSubdirector(), 'FACULTAD DE INGENIERÍA'], true);
            return [$pdf->Output('S'), $caja];
        }

        $cuerpo = match ($clave) {
            'oficio_impresion' => "Una vez atendidas las observaciones de los revisores y contando con el voto aprobatorio, se autoriza la impresión del trabajo escrito «{$x['titulo']}», presentado por {$x['nombre']} (Licenciatura en {$x['carrera']}), con fecha de autorización $fecha.\n\nSe le recuerda cargar el PDF final del trabajo en la plataforma, con una copia de este oficio, y completar los documentos requeridos para la evaluación profesional.",
            'carta_afinidad' => 'En respuesta a la solicitud que usted presentó para optar por Créditos en Estudios Avanzados como modalidad de evaluación profesional, se ha determinado que ' . ((string) ($tramite['datos']['solicitud']['programa'] ?? '') ?: 'el programa de estudios avanzados que propuso') . ' SÍ ES AFÍN a la disciplina del plan de estudios de licenciatura que usted cursó. Por lo que, a partir de esta fecha, puede inscribirse para cursarlo.\n\nDicha modalidad está establecida como una de las opciones de titulación, en la Fracción III, Artículo 8, Capítulo Segundo del Título Primero, del Reglamento de Evaluación Profesional (REP) de la Universidad Autónoma del Estado de México.\n\nCon base en lo anterior, y considerando que la documentación presentada se apega al REP y a los Acuerdos del H. Consejo de Gobierno del 03 de diciembre de 2014 y del 08 de junio de 2022, le informo que puede hacer uso de esta modalidad de titulación. Cabe señalar que, aunque se le otorgue el visto bueno a su solicitud, no tendrá validez hasta que haya cumplido los requisitos correspondientes.\n\nEs importante resaltar que la opción de evaluación profesional de Créditos en Estudios Avanzados constituye una expectativa de derecho y no así un derecho adquirido; por ende, podrá solicitar la evaluación profesional una vez que cumpla con la totalidad de requisitos establecidos en la fracción IV del artículo 22 del REP, que consiste en certificar el 75 % de créditos para estudios de maestría o el 100 % para diplomado superior y especialidad en los plazos mínimos establecidos en el plan de estudios a cursar.',
        };
        self::cabecera($pdf, 'Ciudad Universitaria, Toluca México; a ' . $hoy);
        $pdf->SetFont('Helvetica', 'B', 11);
        if ($clave === 'carta_afinidad') {
            $sis = Catalogo::sistemas()[$tramite['datos']['datos']['sistema'] ?? 'fi'] ?? 'FI-UAEMéx';
            foreach (['C. ' . $x['nombre'] . ' (' . $x['cuenta'] . ')', 'PASANTE DE ' . mb_strtoupper($x['carrera']), $sis === 'FI-UAEMéx' ? 'FACULTAD DE INGENIERÍA, UAEMéx' : $sis, 'PRESENTE'] as $l) {
                $pdf->Cell(0, 5.5, self::l1($l), 0, 1);
            }
        } else {
            $pdf->MultiCell(0, 6, self::l1(mb_strtoupper(self::TIPOS[$clave])), 0, 'L');
        }
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell(0, 5.8, self::l1($cuerpo), 0, 'J');
        $caja = self::firma($pdf, mb_strtoupper(Formatos::subdirector()), [Formatos::cargoSubdirector(), 'FACULTAD DE INGENIERÍA'], true);
        return [$pdf->Output('S'), $caja];
    }

    /** Línea con etiqueta en negritas seguida de texto normal. */
    private static function etiqueta(\FPDF $pdf, string $etiqueta, string $texto, float $alto = 5.8): void
    {
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Write($alto, self::l1($etiqueta . ' '));
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Write($alto, self::l1($texto));
        $pdf->Ln($alto);
    }

    /** «Atentamente», lema y leyenda del año, centrados. */
    private static function atentamente(\FPDF $pdf): void
    {
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5, 'A T E N T A M E N T E', 0, 1, 'C');
        $pdf->Cell(0, 5, self::l1('PATRIA, CIENCIA Y TRABAJO'), 0, 1, 'C');
        $texto = trim((string) \ajuste('institucion_lema_anual', ''));
        if ($texto !== '') {
            $pdf->SetFont('Helvetica', 'I', 8.5);
            $pdf->Cell(0, 4.5, self::l1('"' . date('Y') . ', ' . $texto . '"'), 0, 1, 'C');
        }
    }

    /** Texto por omisión de las observaciones del oficio: la leyenda del coasesor, si lo hay. */
    public static function observacionesAprobacion(array $tramite): string
    {
        $p = $tramite['datos']['protocolo'] ?? [];
        $nombre = trim((string) ($p['coasesor'] ?? ''));
        if ($nombre === '' || mb_strtolower($nombre) === 'no aplica') {
            return 'Ninguna.';
        }
        $c = Persona::completar(['nombre' => $nombre, 'grado' => $p['coasesor_grado'] ?? null, 'genero' => $p['coasesor_genero'] ?? null], (string) ($p['coasesor_correo'] ?? ''));
        return 'La solicitud de participación ' . Persona::f($c, 'del', 'de la') . ' ' . Persona::tratamiento($c) . ' como ' . Persona::f($c, 'coasesor', 'coasesora')
            . ' deberá tramitarse ante el H. Consejo Académico. De ser aprobada, con base en el acuerdo de los HH. Consejos Académico y de Gobierno del 08 de febrero del 2013, '
            . Persona::f($c, 'el coasesor', 'la coasesora') . ' no deberá formar parte del jurado.';
    }

    /**
     * Oficio de aprobación del tema: lo firman el Subdirector Académico y, con Vo. Bo., la Jefatura de Titulación.
     * @return array{0:string,1:array,2:array} [PDF, caja de firma del Subdirector, caja del Vo. Bo.]
     */
    public static function aprobacionTema(array $tramite, string $fechaIso, string $observaciones): array
    {
        $x = self::datos($tramite);
        $p = $tramite['datos']['protocolo'] ?? [];
        $asesor = Persona::completar(['nombre' => trim((string) ($p['asesor'] ?? '')), 'grado' => $p['asesor_grado'] ?? null, 'genero' => $p['asesor_genero'] ?? null], (string) ($p['asesor_correo'] ?? ''));
        $pdf = self::nuevo((string) ($tramite['folio'] ?? ''));
        self::cabecera($pdf, 'Ciudad Universitaria, Toluca, Méx., ' . self::fecha($fechaIso));
        self::etiqueta($pdf, 'Pasante de licenciatura:', $x['nombre'] . ' (No. Cuenta ' . $x['cuenta'] . ')');
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell(0, 5.8, self::l1('Habiendo estudiado la Comisión de Evaluación Profesional de la Coordinación de ' . $x['carrera'] . ', el tema propuesto por USTED, titulado:'), 0, 'J');
        $pdf->Ln(3);
        self::etiqueta($pdf, 'Título:', $x['titulo']);
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell(0, 5.8, self::l1('Para que sea desarrollado con la asesoría ' . Persona::f($asesor, 'del', 'de la') . ' ' . Persona::tratamiento($asesor) . ', la Comisión de Evaluación Profesional determinó lo siguiente:'), 0, 'J');
        $pdf->Ln(3);
        $vineta = chr(149) . '  ';
        $pdf->SetLeftMargin(32);
        $pdf->SetX(32);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Write(5.8, $vineta . 'Se ');
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Write(5.8, 'APRUEBA');
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Write(5.8, self::l1(' el tema como ' . $x['modalidad'] . '.'));
        $pdf->Ln(7);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Write(5.8, $vineta . 'Observaciones: ');
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->Write(5.8, self::l1(trim($observaciones) !== '' ? trim($observaciones) : 'Ninguna.'));
        $pdf->SetLeftMargin(25);
        $pdf->Ln(9);
        $pdf->MultiCell(0, 5.8, self::l1('Con base en el artículo 86 del Reglamento de Evaluación Profesional, usted tendrá exclusividad sobre el tema durante dos años contados a partir de esta fecha; a su término, podrá renovarla mediante la solicitud de una prórroga hasta por un año, tiempo que incluye la sustentación hasta el jurado. Vencido el plazo, el tema quedará libre para ser presentado por otro pasante.'), 0, 'J');
        if ($pdf->GetY() > 185) {
            $pdf->AddPage('P', 'Letter');
            $pdf->SetY(40);
        }
        self::atentamente($pdf);
        $sub = Autoridades::subdirector() ?? [];
        $jefe = Autoridades::jefeTitulacion();
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->SetX(115);
        $pdf->Cell(75, 5, 'Vo. Bo.', 0, 1, 'C');
        $pdf->Ln(18);
        $y = $pdf->GetY();
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(30, $y - 1, 95, $y - 1);
        $pdf->Line(120, $y - 1, 185, $y - 1);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->SetXY(25, $y);
        $pdf->MultiCell(75, 4.8, self::l1(mb_strtoupper(Formatos::subdirector())), 0, 'C');
        $yIzq = $pdf->GetY();
        $pdf->SetXY(115, $y);
        $pdf->MultiCell(75, 4.8, self::l1($jefe ? mb_strtoupper(Persona::tratamiento($jefe)) : 'JEFATURA DEL DEPARTAMENTO DE EVALUACIÓN PROFESIONAL Y GRADO'), 0, 'C');
        $pdf->SetXY(25, $yIzq);
        $pdf->Cell(75, 4.8, self::l1(Persona::f($sub, 'SUBDIRECTOR ACADÉMICO', 'SUBDIRECTORA ACADÉMICA')), 0, 1, 'C');
        $mm = 72 / 25.4;
        $pagina = $pdf->PageNo() - 1;
        $cajaSub = ['p' => $pagina, 'x' => 25 * $mm, 'y' => $y * $mm, 'w' => 75 * $mm, 'h' => 11.0];
        $cajaJefe = ['p' => $pagina, 'x' => 115 * $mm, 'y' => $y * $mm, 'w' => 75 * $mm, 'h' => 11.0];
        return [$pdf->Output('S'), $cajaSub, $cajaJefe];
    }

    // ------------------------------------------------------------------ constancias y oficios del departamento

    private const UNIDADES = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince',
        'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis',
        'veintisiete', 'veintiocho', 'veintinueve', 'treinta', 'treinta y uno'];

    private static function numeroEnLetras(int $n): string
    {
        if ($n < 32) {
            return self::UNIDADES[$n];
        }
        $decenas = [3 => 'treinta', 4 => 'cuarenta', 5 => 'cincuenta', 6 => 'sesenta', 7 => 'setenta', 8 => 'ochenta', 9 => 'noventa'];
        if ($n < 100) {
            return $decenas[intdiv($n, 10)] . ($n % 10 ? ' y ' . self::UNIDADES[$n % 10] : '');
        }
        if ($n >= 2000 && $n < 2100) {
            return 'dos mil' . ($n > 2000 ? ' ' . self::numeroEnLetras($n - 2000) : '');
        }
        return (string) $n;
    }

    /** «a los veintiocho días del mes de agosto del año dos mil veintiséis». */
    public static function fechaEnLetras(?string $iso = null): string
    {
        $f = new \DateTimeImmutable($iso ?? 'today');
        $d = (int) $f->format('j');
        return ($d === 1 ? 'al primer día' : 'a los ' . self::numeroEnLetras($d) . ' días') . ' del mes de ' . Catalogo::MESES[(int) $f->format('n')] . ' del año ' . self::numeroEnLetras((int) $f->format('Y'));
    }

    /** Fecha corta de las tablas: 6/agosto/2025. */
    private static function fechaTabla(?string $iso): string
    {
        $f = $iso ? \DateTimeImmutable::createFromFormat('!Y-m-d', substr($iso, 0, 10)) : false;
        return $f ? $f->format('j') . '/' . Catalogo::MESES[(int) $f->format('n')] . '/' . $f->format('Y') : '';
    }

    private static function jefe(): array
    {
        $j = Autoridades::jefeTitulacion() ?? [];
        return ['nombre' => $j ? Persona::tratamiento($j) : 'JEFATURA DE EVALUACIÓN PROFESIONAL Y GRADO', 'p' => $j,
            'cargo' => Persona::f($j, 'JEFE', 'JEFA') . ' DEL DEPARTAMENTO DE EVALUACIÓN PROFESIONAL Y GRADO'];
    }

    /**
     * Constancia de la Subdirección para un profesor (revisor de protocolo, revisor de trabajo escrito o asesor), con la
     * tabla de los trabajos en que participó. $filas: [['pasante','modalidad','clave','fecha','titulo'], …].
     * @return array{0:string,1:array}
     */
    public static function constanciaProfesor(string $folio, array $profesor, string $rol, string $desde, string $hasta, array $filas): array
    {
        $pdf = self::nuevo($folio);
        self::cabecera($pdf, 'Ciudad Universitaria, Toluca, Méx., ' . self::fecha(date('Y-m-d')));
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'A quien corresponda:', 0, 1);
        $pdf->Ln(3);
        $s = Autoridades::subdirector() ?? [];
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5.6, self::l1('Quien suscribe, ' . mb_strtoupper(Formatos::subdirector()) . ', ' . Persona::f($s, 'Subdirector', 'Subdirectora') . ' Académic' . Persona::f($s, 'o', 'a') . ' de la Facultad de Ingeniería de la Universidad Autónoma del Estado de México, mediante la presente hace constar que ' . Persona::f($profesor, 'el profesor', 'la profesora') . ':'), 0, 'J');
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 6, self::l1(mb_strtoupper(Persona::tratamiento($profesor))), 0, 1, 'C');
        $pdf->Ln(1);
        $pdf->SetFont('Helvetica', '', 10.5);
        $periodo = $desde === $hasta ? 'el ' . self::fecha($desde) : 'del ' . self::fecha($desde) . ' al ' . self::fecha($hasta);
        $pdf->MultiCell(0, 5.6, self::l1('Participó ' . $periodo . ' en los temas que a continuación se relacionan, cumpliendo con las actividades de su nombramiento como:'), 0, 'J');
        $pdf->Ln(1);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 6, self::l1(mb_strtoupper($rol)), 0, 1, 'C');
        $pdf->Ln(1);
        $anchos = [40, 34, 30, 62];
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $pdf->SetFillColor(230, 236, 231);
        foreach (['Pasante', 'Modalidad/Clave', 'Fecha de dictamen', 'Nombre del trabajo'] as $i => $h) {
            $pdf->Cell($anchos[$i], 6, self::l1($h), 1, 0, 'C', true);
        }
        $pdf->Ln();
        $pdf->SetFont('Helvetica', '', 8.5);
        foreach ($filas as $f) {
            $celdas = [$f['pasante'], $f['modalidad'] . ($f['clave'] !== '' ? "\n" . $f['clave'] : ''), self::fechaTabla($f['fecha']), $f['titulo']];
            $lineas = 1;
            foreach ($celdas as $i => $c) {
                $lineas = max($lineas, (int) ceil(max(1, $pdf->GetStringWidth(self::l1(str_replace("\n", ' ', $c)))) / ($anchos[$i] - 2)) + substr_count($c, "\n"));
            }
            $alto = 4.4 * $lineas + 1.2;
            if ($pdf->GetY() + $alto > 245) {
                $pdf->AddPage('P', 'Letter');
                $pdf->SetY(40);
            }
            $y = $pdf->GetY();
            $x = 25.0;
            foreach ($celdas as $i => $c) {
                $pdf->Rect($x, $y, $anchos[$i], $alto);
                $pdf->SetXY($x + 0.5, $y + 0.6);
                $pdf->MultiCell($anchos[$i] - 1, 4.4, self::l1($c), 0, 'L');
                $x += $anchos[$i];
            }
            $pdf->SetY($y + $alto);
        }
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5.6, self::l1('Para los fines que ' . Persona::f($profesor, 'el interesado', 'la interesada') . ' considere convenientes, se extiende la presente CONSTANCIA ' . self::fechaEnLetras() . '.'), 0, 'J');
        $caja = self::firma($pdf, mb_strtoupper(Formatos::subdirector()), [Formatos::cargoSubdirector()], true, 22.0, 200.0);
        return [$pdf->Output('S'), $caja];
    }

    /**
     * Constancia de cumplimiento o nombramiento como director del trabajo escrito; la firma la Jefatura de Evaluación Profesional.
     * @param string $asunto 'CONSTANCIA' o 'NOMBRAMIENTO'
     * @return array{0:string,1:array}
     */
    public static function constanciaDirector(array $tramite, array $profesor, string $asunto, string $fechaExamen): array
    {
        $x = self::datos($tramite);
        $j = self::jefe();
        $pasante = $x['pasante'] + ['nombre' => $x['nombre']];
        $pdf = self::nuevo((string) $tramite['folio']);
        self::cabecera($pdf, 'Ciudad Universitaria, Toluca, Méx., ' . self::fecha(date('Y-m-d')));
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5, 'ASUNTO: ' . $asunto, 0, 1, 'R');
        $pdf->Ln(4);
        $pdf->Cell(0, 5.5, 'A QUIEN CORRESPONDA:', 0, 1);
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5.6, self::l1(Persona::f($j['p'], 'El que suscribe', 'La que suscribe') . ', ' . Persona::tratamiento($j['p'] ?: ['nombre' => $j['nombre']]) . ', ' . Persona::f($j['p'], 'jefe', 'jefa') . ' del Departamento de Evaluación Profesional y Grado de la Facultad de Ingeniería de la Universidad Autónoma del Estado de México, otorga ' . ($asunto === 'NOMBRAMIENTO' ? 'el presente nombramiento' : 'la presente constancia de cumplimiento') . ' ' . Persona::f($profesor, 'al', 'a la') . ':'), 0, 'J');
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 6, self::l1(mb_strtoupper(Persona::tratamiento($profesor))), 0, 1, 'C');
        $pdf->Ln(1);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5.6, self::l1(($asunto === 'NOMBRAMIENTO' ? 'Como ' : 'Quien participó como ') . Persona::f($profesor, 'DIRECTOR', 'DIRECTORA') . ' del tema de ' . $x['modalidad'] . ' «' . $x['titulo'] . '» que presentó ' . Persona::f($pasante, 'el alumno', 'la alumna') . ' ' . $x['nombre'] . ' para su Evaluación Profesional de la Licenciatura de ' . $x['carrera'] . ' el día ' . self::fecha($fechaExamen) . ', cumpliendo en tiempo y forma con las actividades encomendadas.'), 0, 'J');
        $pdf->Ln(3);
        $pdf->MultiCell(0, 5.6, self::l1('A petición ' . Persona::f($profesor, 'del interesado', 'de la interesada') . ' y para los fines legales a que haya lugar, se extiende la presente ' . ($asunto === 'NOMBRAMIENTO' ? 'constancia de NOMBRAMIENTO' : 'CONSTANCIA') . ' en la Ciudad de Toluca de Lerdo, Estado de México, ' . self::fechaEnLetras() . '.'), 0, 'J');
        $caja = self::firma($pdf, mb_strtoupper($j['nombre']), [$j['cargo'], 'DE LA FACULTAD DE INGENIERÍA, UAEM'], true);
        return [$pdf->Output('S'), $caja];
    }

    /** Informe sobre el estatus del protocolo (en revisión del comité); lo firma la Jefatura de Evaluación Profesional. */
    public static function estatusProtocolo(array $tramite, string $fechaTurno): array
    {
        $x = self::datos($tramite);
        $j = self::jefe();
        $pdf = self::nuevo((string) $tramite['folio']);
        self::cabecera($pdf, 'Ciudad Universitaria, Toluca, Méx., ' . self::fecha(date('Y-m-d')));
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->MultiCell(0, 5, self::l1('ASUNTO: INFORME SOBRE EL ESTATUS DE PROTOCOLO DE TITULACIÓN.'), 0, 'R');
        $pdf->Ln(4);
        $pdf->Cell(0, 5.5, 'A QUIEN CORRESPONDA:', 0, 1);
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5.6, self::l1('Por medio del presente, se hace constar que el protocolo de ' . $x['nombre'] . ' (No. Cuenta ' . $x['cuenta'] . ') para la obtención del Título de la Licenciatura en ' . $x['carrera'] . ', titulado «' . $x['titulo'] . '», se encuentra actualmente en proceso de revisión por parte de la comisión revisora correspondiente, a la cual fue turnado desde el día ' . self::fecha($fechaTurno) . '.'), 0, 'J');
        $pdf->Ln(3);
        $pdf->MultiCell(0, 5.6, self::l1('Lo anterior, para los fines académicos y administrativos que a la persona interesada convengan, haciendo constar que el trámite se encuentra en la etapa de revisión correspondiente. Sin otro particular, se extiende el presente para los efectos conducentes.'), 0, 'J');
        $caja = self::firma($pdf, mb_strtoupper($j['nombre']), [$j['cargo'], 'DE LA FACULTAD DE INGENIERÍA, UAEM'], true);
        return [$pdf->Output('S'), $caja];
    }

    /** Respuesta a la solicitud de prórroga: se aprueba y fija el nuevo plazo. Firma la Subdirección. */
    public static function aprobacionProrroga(array $tramite, string $desde, string $hasta): array
    {
        $x = self::datos($tramite);
        $pdf = self::nuevo((string) $tramite['folio']);
        self::cabecera($pdf, 'Ciudad Universitaria, Toluca, Méx., ' . self::fecha(date('Y-m-d')));
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 5.5, self::l1($x['nombre'] . ' (No. Cuenta: ' . $x['cuenta'] . ')'), 0, 1);
        $pdf->Cell(0, 5.5, 'P r e s e n t e.', 0, 1);
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5.6, self::l1('Con respecto a la solicitud de prórroga que Usted pide para concluir el tema titulado «' . $x['titulo'] . '» (' . mb_strtoupper($x['modalidad']) . ')' . ($x['asesor'] !== '' ? ', con la asesoría ' . Persona::f(['genero' => $tramite['datos']['protocolo']['asesor_genero'] ?? null], 'del', 'de la') . ' ' . mb_strtoupper($x['asesor']) : '') . ', se determinó lo siguiente:'), 0, 'J');
        $pdf->Ln(3);
        $pdf->SetX(32);
        $pdf->Write(5.6, chr(149) . '  Se ');
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Write(5.6, self::l1('APRUEBA la PRÓRROGA.'));
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Ln(9);
        $pdf->MultiCell(0, 5.6, self::l1('Con base en el artículo 86, fracción VIII del Reglamento de Evaluación Profesional, usted cuenta hasta el ' . self::fecha($hasta) . ' (a partir del ' . self::fecha($desde) . ') para sustentar el trabajo escrito ante el jurado (evaluación profesional definitiva); en caso de no hacerlo así, el tema quedará libre para ser presentado por otro pasante.'), 0, 'J');
        $caja = self::firma($pdf, mb_strtoupper(Formatos::subdirector()), [Formatos::cargoSubdirector()], true);
        return [$pdf->Output('S'), $caja];
    }

    private const TRABAJOS = [
        'tesis' => 'TESIS', 'memoria' => 'MEMORIA DE EXPERIENCIA LABORAL', 'tesina' => 'TESINA', 'ensayo' => 'ENSAYO',
        'articulo' => 'ARTÍCULO ESPECIALIZADO PARA PUBLICAR EN REVISTA INDIZADA', 'aprovechamiento' => 'APROVECHAMIENTO ACADÉMICO.',
        'egel' => 'EXAMEN GENERAL DE EGRESO DE LICENCIATURA (E. G. E. L.)', 'creditos' => 'CRÉDITOS EN ESTUDIOS AVANZADOS',
        'aplicacion' => 'REPORTE DE APLICACIÓN DE CONOCIMIENTOS', 'autoempleo' => 'REPORTE DE AUTOEMPLEO PROFESIONAL',
        'residencia' => 'REPORTE DE RESIDENCIA DE INVESTIGACIÓN',
    ];

    /**
     * Formato «Asignación de jurado» que llena y firma el Coordinador de Docencia.
     * @param array $asig fila de tit_asignaciones_jurado; $sinodales: [['calidad','nombre','email'], ...]
     * @return array{0:string,1:array} [PDF, caja de firma del Coordinador]
     */
    public static function asignacion(array $tramite, array $asig, array $sinodales): array
    {
        $x = self::datos($tramite);
        $pdf = self::nuevo((string) ($tramite['folio'] ?? ''));
        self::cabecera($pdf, 'Ciudad Universitaria de Toluca, Méx., a ' . self::fecha(date('Y-m-d')));
        $pdf->SetFont('Helvetica', 'B', 12);
        $pdf->Cell(0, 5, self::l1('ASIGNACIÓN DE JURADO'), 0, 1, 'C');
        $pdf->SetFont('Helvetica', 'B', 10.5);
        foreach ([self::director(), 'DIRECTOR DE LA F.I. DE LA U.A.E.MEX', 'P R E S E N T E .'] as $l) {
            $pdf->Cell(0, 5, self::l1($l), 0, 1);
        }
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->MultiCell(0, 5.2, self::l1('Quien suscribe, ' . mb_strtoupper((string) $asig['coord_nombre']) . ', ' . Persona::f(['genero' => $asig['coord_genero'] ?? null], 'Coordinador', 'Coordinadora') . ' de Docencia de la Licenciatura en ' . $x['carrera'] . ', mediante el presente solicita la elaboración de oficios para el (los) alumno (s):'), 0, 'J');
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->Cell(35, 6, 'No. de cuenta', 1, 0, 'C');
        $pdf->Cell(0, 6, 'Nombre', 1, 1, 'C');
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->Cell(35, 6, $x['cuenta'], 1, 0, 'C');
        $pdf->Cell(0, 6, self::l1($x['nombre']), 1, 1, 'C');
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->Cell(0, 5, 'Tipo de trabajo:', 0, 1);
        $pdf->SetFont('Helvetica', '', 8.5);
        foreach (self::TRABAJOS as $k => $et) {
            $pdf->Cell(8, 3.8, $k === $tramite['modalidad'] ? '( X )' : '(    )', 0, 0);
            $pdf->Cell(0, 3.8, self::l1($et), 0, 1);
        }
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->Cell(0, 5, 'Jurado propuesto:', 0, 1);
        $pdf->Cell(32, 5.5, 'Calidad', 1, 0, 'C');
        $pdf->Cell(0, 5.5, 'Nombre y Correo', 1, 1, 'C');
        $pdf->SetFont('Helvetica', '', 9);
        foreach ($sinodales as $s) {
            $pdf->Cell(32, 5.5, $s['calidad'] === 'suplente' ? 'Suplente' : 'Propietario', 1, 0);
            $pdf->Cell(0, 5.5, self::l1(mb_strimwidth(Persona::tratamiento($s) . '  <' . $s['email'] . '>', 0, 88, '…')), 1, 1);
        }
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->MultiCell(0, 5, self::l1(ucfirst(self::EXAMEN[$asig['tipo']] ?? 'examen') . ': ' . self::fecha($asig['fecha']) . ' a las ' . $asig['hora'] . ' horas, en ' . $asig['lugar'] . '.'), 0, 'J');
        $caja = self::firma($pdf, mb_strtoupper((string) $asig['coord_nombre']), [Persona::f(['genero' => $asig['coord_genero'] ?? null], 'COORDINADOR', 'COORDINADORA') . ' DE DOCENCIA DE LA', 'LICENCIATURA EN ' . mb_strtoupper($x['carrera'])], false, 14.0, 215.0);
        return [$pdf->Output('S'), $caja];
    }
}
