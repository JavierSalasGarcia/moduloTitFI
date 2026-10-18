<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

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
        return mb_strtoupper((string) \ajuste('institucion_director_nombre', 'DR. MARCELO ROMERO HUERTAS'));
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
            'asesor' => (string) ($d['protocolo']['asesor'] ?? ''),
        ];
    }

    /**
     * Oficio firmado por la Subdirección o la Dirección.
     * @param array $extra para 'oficio_sinodal': ['sinodal' => fila, 'asig' => fila]
     * @return array{0:string,1:array} [PDF, caja de firma]
     */
    public static function generar(string $clave, array $tramite, array $datosHito, array $extra = []): array
    {
        if (!isset(self::TIPOS[$clave])) {
            throw new \RuntimeException('Oficio desconocido.');
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
            $caja = self::firma($pdf, self::director(), ['Director', 'FACULTAD DE INGENIERÍA', 'U.A.E.M.'], true);
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
            $pdf->Cell(0, 5.5, self::l1(mb_strtoupper((string) $s['nombre'])), 0, 1);
            $pdf->Cell(0, 5.5, 'P r e s e n t e .', 0, 1);
            $pdf->Ln(8);
            $pdf->SetFont('Helvetica', '', 11);
            $cuerpo = 'Por este medio le comunico que, a propuesta de la Coordinación de Docencia de la Licenciatura en ' . $x['carrera'] . ', fue designado como sinodal ' . $s['calidad']
                . ' para el ' . $examen . ' de ' . $x['nombre'] . ' (cuenta ' . $x['cuenta'] . '), opción «' . $x['modalidad'] . '»'
                . ($x['titulo'] !== '' && !in_array($tramite['modalidad'], ['aprovechamiento', 'egel', 'creditos'], true) ? ', con el trabajo «' . $x['titulo'] . '»' : '') . '.';
            $pdf->MultiCell(0, 5.8, self::l1($cuerpo), 0, 'J');
            $pdf->Ln(4);
            $pdf->SetFont('Helvetica', 'B', 11);
            $pdf->MultiCell(0, 5.8, self::l1(ucfirst($examen) . ': ' . self::fecha($a['fecha']) . ', a las ' . $a['hora'] . ' horas, en ' . $a['lugar'] . '.'), 0, 'J');
            $pdf->Ln(4);
            $pdf->SetFont('Helvetica', '', 11);
            $pdf->MultiCell(0, 5.8, self::l1('Le ruego confirmar de recibido desde el enlace del correo que acompaña este oficio, donde también podrá descargar el trabajo escrito. Para el pago del examen definitivo, los trámites deben realizarse fuera del horario de labores de la UAEM.'), 0, 'J');
            $caja = self::firma($pdf, mb_strtoupper(Formatos::subdirector()), ['SUBDIRECTOR ACADÉMICO', 'FACULTAD DE INGENIERÍA'], true);
            return [$pdf->Output('S'), $caja];
        }

        $cuerpo = match ($clave) {
            'oficio_aprobacion_tema' => "Por este medio le informo que, con base en el dictamen de la Comisión Revisora de Protocolos de la Licenciatura en {$x['carrera']}, el $fecha fue aprobado el tema «{$x['titulo']}» con la opción de evaluación profesional «{$x['modalidad']}»" . ($x['asesor'] !== '' ? ", bajo la dirección de {$x['asesor']}" : '') . ".\n\nCon ello queda registrado el tema y la modalidad. Cuenta con exclusividad sobre el tema por dos años, de conformidad con el Reglamento de Evaluación Profesional de la UAEM.",
            'oficio_impresion' => "Una vez atendidas las observaciones de los revisores y contando con el voto aprobatorio, se autoriza la impresión del trabajo escrito «{$x['titulo']}», presentado por {$x['nombre']} (Licenciatura en {$x['carrera']}), con fecha de autorización $fecha.\n\nSe le recuerda cargar el PDF final del trabajo en la plataforma, con una copia de este oficio, y completar los documentos requeridos para la evaluación profesional.",
            'carta_afinidad' => "Por este medio se hace constar que, tras el dictamen de la Coordinación de Docencia, los estudios avanzados que {$x['nombre']} (Licenciatura en {$x['carrera']}) pretende cursar para la opción «{$x['modalidad']}» son afines a la licenciatura, con fecha de autorización $fecha.",
        };
        self::cabecera($pdf, 'Ciudad Universitaria, Toluca México; a ' . $hoy);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->MultiCell(0, 6, self::l1(mb_strtoupper(self::TIPOS[$clave])), 0, 'L');
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell(0, 5.8, self::l1($cuerpo), 0, 'J');
        $caja = self::firma($pdf, mb_strtoupper(Formatos::subdirector()), ['SUBDIRECTOR ACADÉMICO', 'FACULTAD DE INGENIERÍA'], true);
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
        $pdf->MultiCell(0, 5.2, self::l1('Quien suscribe, ' . mb_strtoupper((string) $asig['coord_nombre']) . ', Coordinador de Docencia de la Licenciatura en ' . $x['carrera'] . ', mediante el presente solicita la elaboración de oficios para el (los) alumno (s):'), 0, 'J');
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
            $pdf->Cell(0, 5.5, self::l1(mb_strimwidth($s['nombre'] . '  <' . $s['email'] . '>', 0, 88, '…')), 1, 1);
        }
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->MultiCell(0, 5, self::l1(ucfirst(self::EXAMEN[$asig['tipo']] ?? 'examen') . ': ' . self::fecha($asig['fecha']) . ' a las ' . $asig['hora'] . ' horas, en ' . $asig['lugar'] . '.'), 0, 'J');
        $caja = self::firma($pdf, mb_strtoupper((string) $asig['coord_nombre']), ['COORDINADOR DE DOCENCIA DE LA', 'LICENCIATURA EN ' . mb_strtoupper($x['carrera'])], false, 14.0, 215.0);
        return [$pdf->Output('S'), $caja];
    }
}
