<?php
declare(strict_types=1);

namespace App\Modules\Justificantes;

use App\Core\{Auth, Database, Persona};
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Services\{Autoridades, Hoja, NumeroOficio};

/**
 * Oficios para justificar las faltas de alumnos. Los redactan los Coordinadores de Docencia de las licenciaturas y
 * la Subdirección Académica (la administración puede elegir quién firma). Se generan en la hoja membretada de la
 * Facultad y los archivos adjuntos (PDF o imágenes) se incluyen al final del mismo PDF.
 */
final class Justificantes
{
    public const SALUDO = 'Estimado/a profesor/a:';

    public const ASUNTO = 'Justificación de inasistencias de alumnos';

    /** Mensaje genérico: el primer párrafo va antes de los datos y la lista de alumnos; el resto, después. */
    public const CUERPO = "Por medio del presente, me permito solicitar su amable apoyo para justificar las inasistencias de las y los alumnos que se relacionan a continuación, quienes no pudieron asistir a sus clases en el periodo señalado por el motivo que se indica.\n\n"
        . "Le agradeceré las facilidades que se sirva otorgarles para que puedan presentar los trabajos y evaluaciones que hayan quedado pendientes durante su ausencia, sin que esto afecte su desempeño académico.\n\n"
        . "Sin otro particular, aprovecho la ocasión para enviarle un cordial saludo.";

    public const MAX_ADJUNTOS = 6;

    // ------------------------------------------------------------------ quién puede redactar

    /**
     * Quién puede firmar los oficios de la persona en sesión: la Subdirección Académica o la Coordinación de Docencia
     * de cada licenciatura que atiende. La administración los ve todos.
     * @return array<string,array{clave:string,nombre:string,cargo:string,persona:array,sufijo:string}>
     */
    public static function firmantes(): array
    {
        $todos = [];
        try {
            $sub = Autoridades::subdirector();
            if ($sub) {
                $todos['subdirector'] = [
                    'clave' => 'subdirector', 'nombre' => mb_strtoupper(Persona::tratamiento($sub)), 'persona' => $sub,
                    'cargo' => Persona::f($sub, 'Subdirector', 'Subdirectora') . ' Académic' . Persona::f($sub, 'o', 'a'),
                    'sufijo' => 'subdireccion',
                ];
            }
            foreach (Catalogo::carreras() as $clave => $carrera) {
                $c = Autoridades::coordinadorDocencia($clave);
                if ($c) {
                    $todos['coord_' . $clave] = [
                        'clave' => 'coord_' . $clave, 'nombre' => mb_strtoupper(Persona::tratamiento($c)), 'persona' => $c,
                        'cargo' => Persona::f($c, 'Coordinador', 'Coordinadora') . ' de Docencia de la Licenciatura en ' . $carrera['nombre'],
                        'sufijo' => (string) ($carrera['siglas'][0] ?? strtoupper($clave)),
                    ];
                }
            }
        } catch (\Throwable) {
            return [];
        }
        if (Auth::tieneRol('admin')) {
            return $todos;
        }
        $email = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        return $email === '' ? [] : array_filter($todos, static fn(array $f): bool => strtolower((string) ($f['persona']['email'] ?? '')) === $email);
    }

    public static function puede(): bool
    {
        return self::firmantes() !== [];
    }

    /** Número de oficio que se sugiere (se puede cambiar al redactar). */
    public static function numeroSugerido(array $firmante): string
    {
        $anio = (int) date('Y');
        if ($firmante['clave'] === 'subdirector') {
            return NumeroOficio::texto('subdireccion', NumeroOficio::siguiente('subdireccion', $anio), $anio);
        }
        $n = (int) Database::valor('SELECT COUNT(*) FROM jus_oficios WHERE firmante = ? AND YEAR(fecha) = ?', [$firmante['clave'], $anio]) + 1;
        return 'FI/CD-' . $firmante['sufijo'] . '/' . str_pad((string) $n, 3, '0', STR_PAD_LEFT) . '/' . $anio;
    }

    // ------------------------------------------------------------------ datos

    /** @return array<int,array{0:string,1:string,2:string}> [nombre, cuenta, grupo] */
    public static function alumnos(string $texto): array
    {
        $filas = [];
        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '') {
                continue;
            }
            $p = array_map('trim', preg_split('/[;\t|,]/u', $linea) ?: []);
            $filas[] = [mb_substr($p[0], 0, 120), mb_substr($p[1] ?? '', 0, 20), mb_substr($p[2] ?? '', 0, 40)];
        }
        return $filas;
    }

    // ------------------------------------------------------------------ PDF

    private static function l1(string $s): string
    {
        return Hoja::l1($s);
    }

    /**
     * @param array<string,mixed> $d datos del oficio (numero, fecha ISO, asunto, destinatario, saludo, cuerpo, periodo, motivo, alumnos)
     * @param array<int,array{nombre:string,ruta:string}> $adjuntos archivos que se incluyen al final (rutas absolutas)
     * @throws \InvalidArgumentException si un anexo no se puede incluir
     */
    public static function pdf(array $d, array $firmante, array $adjuntos): string
    {
        $pdf = Hoja::nueva('');
        $pdf->SetAutoPageBreak(true, 30);
        $pdf->SetY(40);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Cell(0, 5, self::l1('Ciudad Universitaria, Toluca, Méx., ' . Hoja::fecha((string) $d['fecha'])), 0, 1, 'R');
        if (trim((string) $d['numero']) !== '') {
            $pdf->SetFont('Helvetica', 'B', 10.5);
            $pdf->Cell(0, 5, self::l1('Oficio No. ' . $d['numero']), 0, 1, 'R');
        }
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5, self::l1('ASUNTO: ' . $d['asunto']), 0, 1, 'R');
        $pdf->Ln(5);
        $dest = trim((string) $d['destinatario']);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        if ($dest !== '') {
            $pdf->Cell(0, 5.2, self::l1(mb_strtoupper($dest)), 0, 1);
        }
        $pdf->Cell(0, 5.2, 'PRESENTE.', 0, 1);
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', '', 10.5);
        if (trim((string) $d['saludo']) !== '') {
            $pdf->Cell(0, 5.6, self::l1((string) $d['saludo']), 0, 1);
            $pdf->Ln(2);
        }

        $parrafos = array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', trim((string) $d['cuerpo'])) ?: []), static fn(string $p): bool => $p !== ''));
        $escribir = static function (string $p) use ($pdf): void {
            $pdf->SetFont('Helvetica', '', 10.5);
            $pdf->MultiCell(0, 5.6, self::l1(preg_replace('/\s*\R\s*/u', ' ', $p) ?? ''), 0, 'J');
            $pdf->Ln(2);
        };
        if ($parrafos) {
            $escribir($parrafos[0]);
        }
        foreach ([['Periodo de inasistencia:', (string) $d['periodo']], ['Motivo:', (string) $d['motivo']]] as [$et, $val]) {
            if (trim($val) !== '') {
                $pdf->SetFont('Helvetica', 'B', 10.5);
                $pdf->Write(5.6, self::l1($et . ' '));
                $pdf->SetFont('Helvetica', '', 10.5);
                $pdf->MultiCell(0, 5.6, self::l1($val), 0, 'L');
            }
        }
        $filas = self::alumnos((string) $d['alumnos']);
        if ($filas) {
            $pdf->Ln(2);
            $w = [10.0, 100.0, 28.0, 28.0];
            $cabecera = static function () use ($pdf, $w): void {
                $pdf->SetFillColor(...Hoja::VERDE);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetFont('Helvetica', 'B', 9.5);
                foreach (['N.º', 'Nombre de la o el alumno', 'N.º de cuenta', 'Grupo'] as $i => $t) {
                    $pdf->Cell($w[$i], 6.4, self::l1($t), 1, 0, 'C', true);
                }
                $pdf->Ln();
                $pdf->SetTextColor(25, 25, 25);
                $pdf->SetFont('Helvetica', '', 9.5);
            };
            $cabecera();
            foreach ($filas as $i => $f) {
                if ($pdf->GetY() > 235) {
                    $pdf->AddPage();
                    $pdf->SetY(40);
                    $cabecera();
                }
                $pdf->Cell($w[0], 6, (string) ($i + 1), 1, 0, 'C');
                $pdf->Cell($w[1], 6, self::l1($f[0]), 1, 0);
                $pdf->Cell($w[2], 6, self::l1($f[1]), 1, 0, 'C');
                $pdf->Cell($w[3], 6, self::l1($f[2]), 1, 1, 'C');
            }
            $pdf->Ln(3);
        }
        foreach (array_slice($parrafos, 1) as $p) {
            $escribir($p);
        }
        if ($adjuntos) {
            $pdf->SetFont('Helvetica', 'B', 9.5);
            $pdf->Cell(0, 5, self::l1('Se anexa (' . count($adjuntos) . '):'), 0, 1);
            $pdf->SetFont('Helvetica', '', 9.5);
            foreach ($adjuntos as $i => $a) {
                $pdf->Cell(0, 4.6, self::l1('   ' . ($i + 1) . '. ' . $a['nombre']), 0, 1);
            }
        }
        self::firma($pdf, $firmante);

        foreach ($adjuntos as $a) {
            self::anexar($pdf, $a);
        }
        return $pdf->Output('S');
    }

    private static function firma(Hoja $pdf, array $firmante): void
    {
        if ($pdf->GetY() > 200) {
            $pdf->AddPage();
            $pdf->SetY(40);
        }
        $pdf->Ln(4);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5, 'A T E N T A M E N T E', 0, 1, 'C');
        $pdf->Cell(0, 5, self::l1('PATRIA, CIENCIA Y TRABAJO'), 0, 1, 'C');
        \App\Core\LemaAnual::imprimir($pdf);
        $pdf->Ln(20);
        $y = $pdf->GetY();
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(55, $y - 1, 160, $y - 1);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5, self::l1((string) $firmante['nombre']), 0, 1, 'C');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 4.8, self::l1((string) $firmante['cargo']), 0, 'C');
    }

    /** Agrega al final del PDF las páginas del anexo (PDF) o la imagen, sin hoja membretada. */
    private static function anexar(Hoja $pdf, array $a): void
    {
        $ext = strtolower(pathinfo($a['ruta'], PATHINFO_EXTENSION));
        $pdf->sinFondo = true;
        if ($ext === 'pdf') {
            try {
                $n = $pdf->setSourceFile($a['ruta']);
                for ($i = 1; $i <= $n; $i++) {
                    $tpl = $pdf->importPage($i);
                    $t = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($t['orientation'], [$t['width'], $t['height']]);
                    $pdf->useTemplate($tpl);
                }
            } catch (\Throwable) {
                throw new \InvalidArgumentException('No se pudo incluir el PDF «' . $a['nombre'] . '» (formato no compatible). Vuelve a guardarlo como PDF estándar o tómale una captura y súbela como imagen.');
            }
            return;
        }
        $info = @getimagesize($a['ruta']);
        $origen = $info ? ($info[2] === IMAGETYPE_PNG ? @imagecreatefrompng($a['ruta']) : @imagecreatefromjpeg($a['ruta'])) : false;
        if (!$info || !$origen) {
            throw new \InvalidArgumentException('No se pudo leer la imagen «' . $a['nombre'] . '».');
        }
        // FPDF no admite PNG con transparencia: todo se pasa a JPEG sobre fondo blanco.
        $lienzo = imagecreatetruecolor($info[0], $info[1]);
        imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 255, 255, 255));
        imagecopy($lienzo, $origen, 0, 0, 0, 0, $info[0], $info[1]);
        $tmp = tempnam(sys_get_temp_dir(), 'jus') ?: throw new \RuntimeException('Sin carpeta temporal.');
        imagejpeg($lienzo, $tmp, 88);
        $w = 190.0;
        $h = $w * $info[1] / $info[0];
        if ($h > 250) {
            $h = 250.0;
            $w = $h * $info[0] / $info[1];
        }
        $pdf->AddPage('P', 'Letter');
        $pdf->Image($tmp, (215.9 - $w) / 2, 14, $w, $h, 'JPG');
        @unlink($tmp);
    }
}
