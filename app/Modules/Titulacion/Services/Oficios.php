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
        'constancia_revision' => 'Constancia de revisión de protocolo',
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

    /** Hoja membretada como fondo de cada página. */
    private static function nuevo(): \setasign\Fpdi\Fpdi
    {
        self::libs();
        $pdf = new class extends \setasign\Fpdi\Fpdi {
            public ?string $fondo = null;

            public function Header(): void
            {
                if ($this->fondo !== null) {
                    $this->useTemplate($this->fondo);
                }
            }
        };
        $pdf->setSourceFile(dirname(__DIR__) . '/plantillas/pdf/Membretada.pdf');
        $pdf->fondo = $pdf->importPage(1);
        $pdf->SetMargins(25, 40, 25);
        $pdf->SetAutoPageBreak(true, 34);
        $pdf->AddPage('P', 'Letter');
        $pdf->SetTextColor(25, 25, 25);
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
        if (!isset(self::TIPOS[$clave]) || in_array($clave, ['oficio_aprobacion_tema', 'constancia_revision'], true)) {
            throw new \RuntimeException('Oficio desconocido.');   // estos dos tienen su propio generador
        }
        $x = self::datos($tramite);
        $hoy = self::fecha(date('Y-m-d'));
        $fecha = self::fecha($datosHito['fecha'] ?? null);
        $pdf = self::nuevo();
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
            'carta_afinidad' => "Por este medio se hace constar que, tras el dictamen de la Coordinación de Docencia, los estudios avanzados que {$x['nombre']} (Licenciatura en {$x['carrera']}) pretende cursar para la opción «{$x['modalidad']}» son afines a la licenciatura, con fecha de autorización $fecha.",
        };
        self::cabecera($pdf, 'Ciudad Universitaria, Toluca México; a ' . $hoy);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->MultiCell(0, 6, self::l1(mb_strtoupper(self::TIPOS[$clave])), 0, 'L');
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
        $pdf = self::nuevo();
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

    /**
     * Constancia para quien revisó un protocolo aprobado; la firma la Subdirección.
     * @param array $revisor ['nombre','email', y si se conocen 'grado','genero']; $vueltas números de vuelta en que revisó
     * @return array{0:string,1:array} [PDF, caja de firma]
     */
    public static function constanciaRevision(array $tramite, array $revisor, array $vueltas, string $fechaAprobacion): array
    {
        $x = self::datos($tramite);
        $r = Persona::completar($revisor, (string) ($revisor['email'] ?? ''));
        $ordinales = array_map(static fn(int $n): string => Mensajes::ordinal($n), $vueltas);
        $enVueltas = count($ordinales) > 1
            ? 'las vueltas ' . implode(', ', array_slice($ordinales, 0, -1)) . ' y ' . end($ordinales)
            : 'la ' . ($ordinales[0] ?? 'primera') . ' vuelta';
        $pdf = self::nuevo();
        self::cabecera($pdf, 'Ciudad Universitaria, Toluca, Méx., ' . self::fecha(date('Y-m-d')));
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 13);
        $pdf->Cell(0, 7, 'C O N S T A N C I A', 0, 1, 'C');
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 5.5, 'A QUIEN CORRESPONDA:', 0, 1);
        $pdf->Ln(5);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell(0, 5.8, self::l1('La Subdirección Académica de la Facultad de Ingeniería hace constar que ' . Persona::f($r, 'el', 'la') . ' ' . Persona::tratamiento($r)
            . ' participó como ' . Persona::f($r, 'revisor', 'revisora') . ' del protocolo de evaluación profesional titulado «' . $x['titulo'] . '», presentado por ' . $x['nombre']
            . ' (No. Cuenta ' . $x['cuenta'] . '), pasante de la Licenciatura en ' . $x['carrera'] . ', en la opción «' . $x['modalidad'] . '», como integrante del Comité de Revisión de Protocolos de la licenciatura, en '
            . $enVueltas . ' de revisión. El protocolo fue aprobado el ' . self::fecha($fechaAprobacion) . '.'), 0, 'J');
        $pdf->Ln(4);
        $pdf->MultiCell(0, 5.8, self::l1('Se extiende la presente para los fines que ' . Persona::f($r, 'al interesado', 'a la interesada') . ' convengan.'), 0, 'J');
        $caja = self::firma($pdf, mb_strtoupper(Formatos::subdirector()), [Formatos::cargoSubdirector(), 'FACULTAD DE INGENIERÍA'], true);
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
        $pdf = self::nuevo();
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
