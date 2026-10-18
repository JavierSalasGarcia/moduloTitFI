<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;

/** Oficios del departamento: se generan con los datos del trámite y quedan listos para la firma de la Subdirección. */
final class Oficios
{
    /** Oficios que genera la plataforma: clave del documento => nombre. */
    public const TIPOS = [
        'oficio_aprobacion_tema' => 'Oficio de aprobación de tema y modalidad',
        'oficio_revisores' => 'Oficio de asignación de revisores',
        'oficio_impresion' => 'Oficio de autorización de impresión',
        'oficio_jurado' => 'Oficio de asignación de jurado',
        'carta_afinidad' => 'Carta de afinidad',
    ];

    private static function libs(): void
    {
        $lib = dirname(APP_RAIZ) . '/lib';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $lib . '/fpdf/font/');
        }
        require_once $lib . '/fpdf/fpdf.php';
    }

    private static function l1(string $s): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?: $s;
    }

    private static function fecha(?string $iso): string
    {
        $f = $iso ? \DateTimeImmutable::createFromFormat('!Y-m-d', $iso) : false;
        return $f ? $f->format('j') . ' de ' . Catalogo::MESES[(int) $f->format('n')] . ' de ' . $f->format('Y') : '____ de ________ de ____';
    }

    /**
     * @param array $datosHito lo capturado por el personal en la etapa
     * @return array{0:string,1:array} [PDF, caja de firma {p,x,y,w,h} en puntos]
     */
    public static function generar(string $clave, array $tramite, array $datosHito): array
    {
        if (!isset(self::TIPOS[$clave])) {
            throw new \RuntimeException('Oficio desconocido.');
        }
        self::libs();
        $d = $tramite['datos'];
        $p = $d['datos'] ?? [];
        $nombre = mb_strtoupper((string) ($p['nombre'] ?? ''));
        $carrera = Catalogo::carreras()[$p['carrera'] ?? '']['nombre'] ?? '';
        $modalidad = Catalogo::modalidad((string) $tramite['modalidad'])['nombre'] ?? '';
        $titulo = (string) ($d['revision']['titulo'] ?? $tramite['titulo'] ?? $d['protocolo']['titulo'] ?? '');
        $asesor = (string) ($d['protocolo']['asesor'] ?? '');
        $hoy = self::fecha(date('Y-m-d'));
        $fecha = self::fecha($datosHito['fecha'] ?? null);
        $cita = trim(($datosHito['fecha'] ?? '') !== '' ? 'el ' . $fecha . (($datosHito['hora'] ?? '') !== '' ? ' a las ' . $datosHito['hora'] . ' h' : '') . (($datosHito['lugar'] ?? '') !== '' ? ', en ' . $datosHito['lugar'] : '') : '');

        $cuerpo = match ($clave) {
            'oficio_aprobacion_tema' => "Por este medio le informo que, con base en el dictamen de la Comisión Revisora de Protocolos de la Licenciatura en $carrera, el {$fecha} fue aprobado el protocolo del trabajo escrito «{$titulo}», presentado por {$nombre} con la opción de evaluación profesional «{$modalidad}»" . ($asesor !== '' ? ", bajo la dirección de $asesor" : '') . ".\n\nCon ello queda registrado el tema y la modalidad. El pasante cuenta con exclusividad sobre el tema por dos años, de conformidad con el Reglamento de Evaluación Profesional de la UAEM.",
            'oficio_revisores' => "Por este medio le comunico que, para el examen previo del trabajo escrito «{$titulo}», presentado por {$nombre} (Licenciatura en $carrera), fueron asignados como revisores:\n\n" . trim((string) ($datosHito['revisores'] ?? '')) . "\n\nEl examen previo se llevará a cabo " . ($cita !== '' ? $cita : 'en la fecha que se indique') . '.',
            'oficio_impresion' => "Una vez atendidas las observaciones de los revisores y contando con el voto aprobatorio, se autoriza la impresión del trabajo escrito «{$titulo}», presentado por {$nombre} (Licenciatura en $carrera), con fecha de autorización $fecha.\n\nSe le recuerda entregar los ejemplares y documentos requeridos para la evaluación profesional.",
            'oficio_jurado' => "Por este medio le comunico que fue designado como integrante del jurado para la evaluación profesional de {$nombre} (Licenciatura en $carrera), opción «{$modalidad}», trabajo escrito «{$titulo}».\n\nJurado asignado:\n\n" . trim((string) ($datosHito['jurado'] ?? '')) . "\n\nLa evaluación se llevará a cabo " . ($cita !== '' ? $cita : 'en la fecha que se indique') . '.',
            'carta_afinidad' => "Por este medio se hace constar que, tras el dictamen de la Coordinación de Docencia, los estudios avanzados que {$nombre} (Licenciatura en $carrera) pretende cursar para la opción «{$modalidad}» son afines a la licenciatura, con fecha de autorización $fecha.",
        };

        $pdf = new \FPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(25, 20, 25);
        $pdf->SetAutoPageBreak(true, 25);
        $pdf->AddPage();
        $img = dirname(__DIR__) . '/plantillas/img';
        if (is_file($img . '/uaem.jpg')) {
            $pdf->Image($img . '/uaem.jpg', 25, 14, 28);
        }
        if (is_file($img . '/facultad.jpg')) {
            $pdf->Image($img . '/facultad.jpg', 168, 14, 22);
        }
        $pdf->SetY(18);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->SetTextColor(44, 82, 52);
        $pdf->Cell(0, 5, self::l1('Universidad Autónoma del Estado de México'), 0, 1, 'C');
        $pdf->Cell(0, 5, self::l1('Facultad de Ingeniería'), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 9);
        $pdf->Cell(0, 5, self::l1('Subdirección Académica · Departamento de Evaluación Profesional'), 0, 1, 'C');
        $pdf->SetDrawColor(156, 132, 18);
        $pdf->SetLineWidth(0.6);
        $pdf->Line(25, 42, 190, 42);
        $pdf->SetTextColor(25, 25, 25);
        $pdf->SetY(48);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, self::l1('Oficio: ' . $tramite['folio'] . '/' . strtoupper(str_replace(['oficio_', 'carta_'], '', $clave))), 0, 1, 'R');
        $pdf->Cell(0, 5, self::l1('Toluca, México, a ' . $hoy), 0, 1, 'R');
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->MultiCell(0, 6, self::l1(mb_strtoupper(self::TIPOS[$clave])), 0, 'L');
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', '', 11);
        $pdf->MultiCell(0, 6, self::l1($cuerpo), 0, 'J');
        $pdf->Ln(8);
        $pdf->Cell(0, 6, 'Atentamente', 0, 1, 'C');
        $pdf->SetFont('Helvetica', 'I', 9);
        $pdf->Cell(0, 5, self::l1('Patria, Ciencia y Trabajo'), 0, 1, 'C');
        $pdf->Ln(30);
        $yNombre = $pdf->GetY();
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(70, $yNombre - 1, 145, $yNombre - 1);
        $pdf->SetFont('Helvetica', 'B', 11);
        $pdf->Cell(0, 5, self::l1(mb_strtoupper(Formatos::subdirector())), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Cell(0, 5, self::l1('SUBDIRECTOR ACADÉMICO'), 0, 1, 'C');
        $mm = 72 / 25.4;
        $caja = ['p' => 0, 'x' => 70 * $mm, 'y' => $yNombre * $mm, 'w' => 75 * $mm, 'h' => 11.0];
        return [$pdf->Output('S'), $caja];
    }
}
