<?php
declare(strict_types=1);

namespace App\Modules\Posgrado\Services;

use App\Core\LemaAnual;
use App\Modules\Posgrado\Catalogo;
use App\Modules\Posgrado\Configuracion;
use App\Modules\Posgrado\Flujo;
use App\Modules\Titulacion\Services\Hoja;
use RuntimeException;

/**
 * Formatos y oficios del trámite de evaluación de grado sobre la hoja membretada de la Facultad
 * (App\Modules\Titulacion\Services\Hoja). La redacción reproduce los machotes de formulariosPosgrado/:
 *
 *  voto                   R 7 VOTO APROBATORIO.docx
 *  datos_interesado       R 24 Formato de Datos del interesado.pdf
 *  solicitud              1.1 OFICIO DE SOLICITUD DE EVALUACIÓN DE GRADO.docx
 *  visto_bueno            1.2 VISTO BUENO.docx
 *  cotejo                 1 OFICIO DE COTEJO DE ENTREGA TOTAL DE REQUISITOS PARA EXAMEN DE GRADO.docx (+ anexo 3: PUNTOS A CONSIDERAR)
 *  invitaciones           2 oficios para sinodales.docx (una hoja por integrante)
 *  acuse                  3 ACUSE DE RECIBIDO DE OFICIOS DE CADA MIEMBRO DEL SINODO.docx
 *  notificacion           1.3 notificacion.docx
 *  constancias_revisores  4 Constancias REVISORES.docx (una hoja por integrante del sínodo definitivo)
 *  constancias_direccion  5 constancias TUTORES.docx (director, codirector y tutor)
 *  no_adeudo              Constancia No adeudo equipo y mobiliario.docx
 *  integracion            25. Formato de integración de expedientes (Posgrado)
 *
 * El QR de verificación de Hoja depende de tit_tramites (Verificacion::guardado), así que estas hojas se crean sin
 * folio de Titulación (Hoja::nueva('')) y llevan un pie propio con el folio del trámite de grado.
 */
final class Formatos
{
    public const NOMBRES = [
        'voto' => 'Voto aprobatorio', 'datos_interesado' => 'Formato de datos del interesado', 'solicitud' => 'Solicitud de evaluación de grado',
        'visto_bueno' => 'Visto bueno del sínodo', 'cotejo' => 'Oficio de cotejo', 'invitaciones' => 'Oficios de invitación al sínodo',
        'acuse' => 'Acuse de recibido de oficios', 'notificacion' => 'Notificación al director de tesis', 'constancias_revisores' => 'Constancias de revisores',
        'constancias_direccion' => 'Constancias de director, codirector y tutor', 'no_adeudo' => 'Constancia de no adeudo de equipo y mobiliario',
        'integracion' => 'Formato de integración de expediente',
    ];

    private const IZQ = 25.0;
    private const ANCHO = 166.0;

    /**
     * @param array $t trámite (con 'datos', 'folio', 'alumno_genero') @param array $hitos hitos por clave
     * @return array{0:string,1:string} [bytes PDF, nombre de archivo]
     */
    public static function generar(string $clave, array $t, array $hitos = []): array
    {
        if (!isset(self::NOMBRES[$clave])) {
            throw new RuntimeException('Formato desconocido.');
        }
        $falta = self::impedimento($clave, $t, $hitos);
        if ($falta !== null) {
            throw new RuntimeException($falta);
        }
        $x = self::datos($t, $hitos);
        $pdf = match ($clave) {
            'voto' => self::voto($x),
            'datos_interesado' => self::datosInteresado($x),
            'solicitud' => self::solicitud($x),
            'visto_bueno' => self::vistoBueno($x),
            'cotejo' => self::cotejo($x, $hitos['cotejo']['datos'] ?? []),
            'invitaciones' => self::invitaciones($x),
            'acuse' => self::acuse($x),
            'notificacion' => self::notificacion($x),
            'constancias_revisores' => self::constanciasRevisores($x),
            'constancias_direccion' => self::constanciasDireccion($x),
            'no_adeudo' => self::noAdeudo($x),
            'integracion' => self::integracion($x),
        };
        self::pie($pdf, (string) ($t['folio'] ?? ''));
        $nombre = preg_replace('/[^A-Za-z0-9_-]+/', '_', self::NOMBRES[$clave]) . '_' . ($t['folio'] ?? 'PG') . '.pdf';
        return [$pdf->Output('S'), $nombre];
    }

    /** Qué falta capturar para generar el formato; null si se puede. */
    public static function impedimento(string $clave, array $t, array $hitos = []): ?string
    {
        $faltan = Flujo::faltantesInteresado($t);
        if ($faltan && $clave !== 'no_adeudo') {
            return 'Para generar este formato faltan datos del interesado: ' . implode(', ', array_slice($faltan, 0, 5)) . (count($faltan) > 5 ? '…' : '') . '.';
        }
        if (in_array($clave, ['solicitud', 'visto_bueno'], true) && ($f = Flujo::faltantesSinodo($t))) {
            return 'Completa primero la propuesta de sínodo y fecha (falta: ' . implode(', ', array_slice($f, 0, 4)) . ').';
        }
        if ($clave === 'cotejo' && !isset($hitos['cotejo'])) {
            return 'Registra primero la fecha y hora del cotejo de originales.';
        }
        if (in_array($clave, ['invitaciones', 'acuse', 'notificacion'], true) && !isset($hitos['programacion'])) {
            return 'Registra primero la programación del examen (fecha, hora y lugar).';
        }
        if (in_array($clave, ['constancias_revisores', 'constancias_direccion', 'integracion'], true) && !in_array((string) ($hitos['resultado']['datos']['resultado'] ?? ''), ['unanimidad', 'mayoria'], true)) {
            return 'Registra primero el resultado aprobatorio del examen.';
        }
        return null;
    }

    // ---------------------------------------------------------------- utilidades

    private static function l1(string $s): string
    {
        return Hoja::l1($s);
    }

    private static function nuevo(): Hoja
    {
        // Sin folio de Titulación: ni QR ni «ID del proceso de titulación»; el pie propio lo pone self::pie().
        $pdf = Hoja::nueva('');
        $pdf->SetAutoPageBreak(true, 30);
        return $pdf;
    }

    /**
     * Folio del trámite de grado y número de página en la esquina inferior derecha de cada hoja. Hoja es final y su pie
     * no se puede cambiar, así que se escribe al final recorriendo las páginas ya compuestas.
     */
    private static function pie(Hoja $pdf, string $folio): void
    {
        $total = $pdf->PageNo();
        try {
            $prop = new \ReflectionProperty(\FPDF::class, 'page');
        } catch (\ReflectionException) {
            return;
        }
        $pdf->SetAutoPageBreak(false);
        $actual = $prop->getValue($pdf);
        for ($p = 1; $p <= $total; $p++) {
            $prop->setValue($pdf, $p);
            // Cambiar de fuente obliga a FPDF a escribir la selección en el contenido de esta página.
            $pdf->SetFont('Courier', '', 7);
            $pdf->SetFont('Helvetica', '', 7);
            $pdf->SetTextColor(90, 90, 90);
            $pdf->SetXY(111, 279.4 - 12);
            $texto = 'Folio del trámite de grado: ' . ($folio !== '' ? $folio : '—') . ($total > 1 ? '  ·  Hoja ' . $p . ' de ' . $total : '');
            $pdf->Cell(98, 4, self::l1($texto), 0, 0, 'R');
        }
        $prop->setValue($pdf, $actual);
        $pdf->SetTextColor(25, 25, 25);
    }

    private static function fecha(?string $iso = null, bool $cero = false): string
    {
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso ?? date('Y-m-d'));
        return $f ? $f->format($cero ? 'd' : 'j') . ' de ' . Catalogo::MESES[(int) $f->format('n')] . ' de ' . $f->format('Y') : '';
    }

    /** Número en letras (1 a 2099), para las fechas de las constancias. */
    public static function letras(int $n): string
    {
        $u = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve', 'diez', 'once', 'doce', 'trece', 'catorce', 'quince',
            'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve', 'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco',
            'veintiséis', 'veintisiete', 'veintiocho', 'veintinueve'];
        $d = [3 => 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $c = [1 => 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
        if ($n <= 0) {
            return 'cero';
        }
        $partes = [];
        if ($n >= 1000) {
            $miles = intdiv($n, 1000);
            $partes[] = $miles === 1 ? 'mil' : self::letras($miles) . ' mil';
            $n %= 1000;
        }
        if ($n >= 100) {
            $partes[] = $n === 100 ? 'cien' : $c[intdiv($n, 100)];
            $n %= 100;
        }
        if ($n > 0) {
            $partes[] = $n < 30 ? $u[$n] : $d[intdiv($n, 10)] . ($n % 10 ? ' y ' . $u[$n % 10] : '');
        }
        return implode(' ', $partes);
    }

    /** «a los dos días del mes de octubre del año 2026» */
    private static function extiende(?string $iso, bool $anioEnLetras = false): string
    {
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso ?? date('Y-m-d')) ?: new \DateTimeImmutable('today');
        $dia = (int) $f->format('j');
        $mes = Catalogo::MESES[(int) $f->format('n')];
        $anio = $anioEnLetras ? self::letras((int) $f->format('Y')) : $f->format('Y');
        return $anioEnLetras
            ? 'a los ' . self::letras($dia) . ' días de ' . $mes . ' del año ' . $anio
            : 'a los ' . self::letras($dia) . ' días del mes de ' . $mes . ' del año ' . $anio;
    }

    /** Periodo escolar de una fecha: agosto a enero es «B», febrero a julio es «A». */
    public static function periodo(?string $iso): string
    {
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $iso) ?: new \DateTimeImmutable('today');
        $m = (int) $f->format('n');
        $a = (int) $f->format('Y');
        return $m === 1 ? ($a - 1) . 'B' : $a . ($m >= 8 ? 'B' : 'A');
    }

    private static function persona(string $grado, string $nombre): string
    {
        $grado = trim($grado);
        $nombre = trim($nombre);
        if ($grado !== '' && !preg_match('/^' . preg_quote(rtrim($grado, '.'), '/') . '\b/iu', $nombre)) {
            return $grado . ' ' . $nombre;
        }
        return $nombre;
    }

    /** Datos comunes ya armados para la redacción. */
    public static function datos(array $t, array $hitos): array
    {
        $i = $t['datos']['interesado'] ?? [];
        $s = $t['datos']['sinodo'] ?? [];
        $g = in_array($t['alumno_genero'] ?? null, ['masculino', 'femenino'], true) ? $t['alumno_genero'] : null;
        $f = static fn(string $m, string $fe, string $n) => $g === 'femenino' ? $fe : ($g === 'masculino' ? $m : $n);
        $prog = $hitos['programacion']['datos'] ?? [];
        $res = $hitos['resultado']['datos'] ?? [];
        $miembros = [];
        foreach (array_slice(array_values($s['miembros'] ?? []), 0, 7) as $n => $m) {
            $miembros[] = [
                'nombre' => trim((string) ($m['nombre'] ?? '')), 'tipo' => (string) ($m['tipo'] ?? ''),
                'etiqueta' => Catalogo::tipoMiembro((string) ($m['tipo'] ?? ''), (string) ($i['programa_siglas'] ?? '')),
                'cargo' => $n < 5 ? 'Propietario' : 'Suplente', 'externo' => !empty($m['externo']),
            ];
        }
        $definitivo = [];
        foreach ((array) ($res['definitivo'] ?? []) as $n) {
            if (isset($miembros[(int) $n]) && $miembros[(int) $n]['nombre'] !== '') {
                $definitivo[] = $miembros[(int) $n];
            }
        }
        if (!$definitivo) {
            $definitivo = array_values(array_filter(array_slice($miembros, 0, 5), static fn(array $m): bool => $m['nombre'] !== ''));
        }
        $modalidad = (string) ($i['modalidad'] ?? '');
        return [
            'folio' => (string) ($t['folio'] ?? ''),
            'nombre' => trim((string) ($i['nombre'] ?? '')),
            'acentos' => ($i['acentos'] ?? '') === 'si' ? 'Sí' : (($i['acentos'] ?? '') === 'no' ? 'No' : ''),
            'cuenta' => (string) ($i['cuenta'] ?? ''),
            'programa' => trim((string) ($i['programa'] ?? '')),
            'siglas' => (string) ($i['programa_siglas'] ?? ''),
            'nivel' => (string) ($i['nivel'] ?? $t['nivel'] ?? 'maestria'),
            'plan' => (string) ($i['plan'] ?? ''),
            'telefono' => (string) ($i['telefono'] ?? ''),
            'correo' => (string) ($i['correo'] ?? ''),
            'ocupacion' => (string) ($i['ocupacion'] ?? ''),
            'periodo_inicio' => (string) ($i['periodo_inicio'] ?? ''),
            'titulo' => trim((string) ($i['titulo'] ?? '')),
            'modalidad' => $modalidad,
            'modalidad_nombre' => Catalogo::modalidades()[$modalidad] ?? '',
            'trabajo' => Catalogo::trabajo($modalidad),
            'fecha_previo' => (string) ($i['fecha_previo'] ?? ''),
            'art53' => ($i['art53'] ?? '') === 'si',
            'director' => self::persona((string) ($i['director_grado'] ?? ''), (string) ($i['director_nombre'] ?? '')),
            'codirector' => self::persona((string) ($i['codirector_grado'] ?? ''), (string) ($i['codirector_nombre'] ?? '')),
            'tutor' => self::persona((string) ($i['tutor_grado'] ?? ''), (string) ($i['tutor_nombre'] ?? '')),
            'egresado' => $f('el egresado', 'la egresada', 'el/la egresado/a'),
            'alumno_de' => $f('del alumno', 'de la alumna', 'del/de la alumno/a'),
            'interesado' => $f('del interesado', 'de la interesada', 'de la persona interesada'),
            'articulo' => $f('el', 'la', 'el/la'),
            'miembros' => $miembros,
            'definitivo' => $definitivo,
            'propuesta' => ['fecha' => (string) ($s['fecha'] ?? ''), 'hora' => (string) ($s['hora'] ?? ''), 'modalidad' => (string) ($s['modalidad'] ?? '')],
            'prog' => ['fecha' => (string) ($prog['fecha'] ?? ''), 'hora' => (string) ($prog['hora'] ?? ''), 'lugar' => (string) ($prog['lugar'] ?? ''), 'modalidad' => (string) ($prog['modalidad'] ?? 'presencial')],
            'fecha_examen' => (string) ($res['fecha'] ?? $prog['fecha'] ?? ''),
            'firmante' => Configuracion::firmante(),
            'cargo' => Configuracion::cargo(),
        ];
    }

    private static function lugarFecha(Hoja $pdf, string $texto, float $y = 40.0): void
    {
        $pdf->SetY($y);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Cell(0, 5, self::l1($texto), 0, 1, 'R');
        $pdf->Ln(5);
    }

    private static function tituloOficio(Hoja $pdf, string $texto, float $tam = 11.0): void
    {
        $pdf->SetFont('Helvetica', 'B', $tam);
        $pdf->MultiCell(0, 5.5, self::l1(mb_strtoupper($texto)), 0, 'C');
        $pdf->Ln(4);
    }

    private static function parrafo(Hoja $pdf, string $texto, float $tam = 10.5, float $despues = 3.0, string $alin = 'J'): void
    {
        $pdf->SetFont('Helvetica', '', $tam);
        $pdf->MultiCell(0, round($tam * 0.5, 2), self::l1($texto), 0, $alin);
        $pdf->Ln($despues);
    }

    /** Párrafo con fragmentos en negritas: lista de [texto, negritas]. */
    private static function mixto(Hoja $pdf, array $trozos, float $tam = 10.5, float $despues = 3.0): void
    {
        // FPDF no justifica con Write(); se usa alineación izquierda.
        foreach ($trozos as [$texto, $negritas]) {
            $pdf->SetFont('Helvetica', $negritas ? 'B' : '', $tam);
            $pdf->Write(5.3, self::l1($texto));
        }
        $pdf->Ln(5.3 + $despues);
    }

    private static function destinatario(Hoja $pdf, array $lineas, string $presente = 'P r e s e n t e.', float $despues = 4.0): void
    {
        $pdf->SetFont('Helvetica', 'B', 10.5);
        foreach ($lineas as $l) {
            $pdf->MultiCell(0, 5.2, self::l1($l), 0, 'L');
        }
        if ($presente !== '') {
            $pdf->Cell(0, 5.2, self::l1($presente), 0, 1);
        }
        $pdf->Ln($despues);
    }

    /** «A T E N T A M E N T E», lema, leyenda del año y firma de la Coordinación de Estudios Avanzados. */
    private static function firmaCoordinacion(Hoja $pdf, array $x, float $espacio = 18.0, ?array $cargo = null): void
    {
        self::firma($pdf, $x['firmante'], $cargo ?? [$x['cargo']], $espacio);
    }

    private static function firma(Hoja $pdf, string $nombre, array $cargo, float $espacio = 18.0, bool $lema = true): void
    {
        $necesario = ($lema ? 22 : 6) + $espacio + 6 + 5 * count($cargo);
        if ($pdf->GetY() + $necesario > 279.4 - 30) {
            $pdf->AddPage();
            $pdf->SetY(40);
        }
        $pdf->Ln(4);
        if ($lema) {
            $pdf->SetFont('Helvetica', 'B', 10.5);
            $pdf->Cell(0, 5, 'A T E N T A M E N T E', 0, 1, 'C');
            $pdf->Cell(0, 5, self::l1('PATRIA, CIENCIA Y TRABAJO'), 0, 1, 'C');
            try {
                LemaAnual::imprimir($pdf);
            } catch (\Throwable) {
                // Sin base de datos (pruebas) no hay leyenda del año.
            }
        }
        $pdf->Ln($espacio);
        $y = $pdf->GetY();
        $pdf->SetDrawColor(70, 70, 70);
        $pdf->SetLineWidth(0.2);
        $pdf->Line(63, $y - 1, 153, $y - 1);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->MultiCell(0, 5, self::l1($nombre), 0, 'C');
        $pdf->SetFont('Helvetica', '', 10);
        foreach ($cargo as $c) {
            $pdf->MultiCell(0, 4.8, self::l1($c), 0, 'C');
        }
    }

    private static function ccp(Hoja $pdf, array $lineas): void
    {
        $pdf->Ln(2.5);
        $pdf->SetFont('Helvetica', '', 8.5);
        foreach ($lineas as $l) {
            $pdf->Cell(0, 4, self::l1($l), 0, 1);
        }
    }

    /**
     * Tabla con encabezado verde y celdas que crecen con el texto.
     * @param array<int,array{0:string,1:float,2?:string}> $columnas [encabezado, ancho, alineación]
     * @param array<int,array<int,string>> $filas
     */
    private static function tabla(Hoja $pdf, array $columnas, array $filas, float $tam = 9.0, float $minimo = 6.0): void
    {
        $alto = 4.4;
        $encabezado = function () use ($pdf, $columnas, $tam, $alto): void {
            $pdf->SetFont('Helvetica', 'B', $tam);
            $lineas = 1;
            foreach ($columnas as $c) {
                $lineas = max($lineas, $pdf->renglones($c[1], self::l1($c[0])));
            }
            $h = $lineas * $alto + 1.6;
            $x = self::IZQ;
            $y = $pdf->GetY();
            $pdf->SetFillColor(...Hoja::VERDE);
            $pdf->SetTextColor(255, 255, 255);
            foreach ($columnas as $c) {
                $pdf->Rect($x, $y, $c[1], $h, 'DF');
                $pdf->SetXY($x, $y + 0.8);
                $pdf->MultiCell($c[1], $alto, self::l1($c[0]), 0, 'C');
                $x += $c[1];
            }
            $pdf->SetTextColor(25, 25, 25);
            $pdf->SetXY(self::IZQ, $y + $h);
        };
        $pdf->SetDrawColor(110, 110, 110);
        $pdf->SetLineWidth(0.2);
        $encabezado();
        foreach ($filas as $fila) {
            $pdf->SetFont('Helvetica', '', $tam);
            $lineas = 1;
            foreach ($columnas as $k => $c) {
                $lineas = max($lineas, $pdf->renglones($c[1], self::l1((string) ($fila[$k] ?? ''))));
            }
            $h = max($minimo, $lineas * $alto + 1.6);
            if ($pdf->GetY() + $h > 279.4 - 32) {
                $pdf->AddPage();
                $pdf->SetY(40);
                $encabezado();
                $pdf->SetFont('Helvetica', '', $tam);
            }
            $x = self::IZQ;
            $y = $pdf->GetY();
            foreach ($columnas as $k => $c) {
                $pdf->Rect($x, $y, $c[1], $h);
                $texto = self::l1((string) ($fila[$k] ?? ''));
                $usadas = $pdf->renglones($c[1], $texto);
                $pdf->SetXY($x, $y + ($h - $usadas * $alto) / 2);
                $pdf->MultiCell($c[1], $alto, $texto, 0, $c[2] ?? 'L');
                $x += $c[1];
            }
            $pdf->SetXY(self::IZQ, $y + $h);
        }
        $pdf->Ln(3);
    }

    /** Renglón «Etiqueta: valor» con el valor en negritas y subrayado. */
    private static function campo(Hoja $pdf, string $etiqueta, string $valor, float $tam = 10.5): void
    {
        $pdf->SetFont('Helvetica', '', $tam);
        $pdf->Write(6, self::l1($etiqueta . ' '));
        $pdf->SetFont('Helvetica', 'BU', $tam);
        $pdf->Write(6, self::l1($valor !== '' ? $valor : str_repeat(' ', 40)));
        $pdf->SetFont('Helvetica', '', $tam);
        $pdf->Ln(8);
    }

    private static function casilla(Hoja $pdf, bool $marcada, string $texto, float $sangria = 6.0, float $ancho = 0.0, float $tam = 9.5): void
    {
        $pdf->SetX(self::IZQ + $sangria);
        $y = $pdf->GetY();
        $pdf->SetDrawColor(60, 60, 60);
        $pdf->Rect(self::IZQ + $sangria, $y + 0.8, 3.6, 3.6);
        if ($marcada) {
            $pdf->SetFont('Helvetica', 'B', 9);
            $pdf->SetXY(self::IZQ + $sangria, $y + 0.6);
            $pdf->Cell(3.6, 4, 'X', 0, 0, 'C');
        }
        $pdf->SetFont('Helvetica', '', $tam);
        $pdf->SetXY(self::IZQ + $sangria + 6, $y);
        $pdf->MultiCell($ancho > 0 ? $ancho : self::ANCHO - $sangria - 6, 4.6, self::l1($texto), 0, 'L');
        $pdf->Ln(0.8);
    }

    private static function hora(string $h): string
    {
        return preg_match('/^\d{2}:\d{2}$/', $h) ? $h : ($h !== '' ? $h : '____');
    }

    // ------------------------------------------------------------------- formatos

    /** R 7 · Voto aprobatorio (firma autógrafa del director). */
    private static function voto(array $x): Hoja
    {
        $pdf = self::nuevo();
        self::lugarFecha($pdf, 'Fecha: ' . str_repeat('_', 30));
        self::tituloOficio($pdf, 'Formato voto aprobatorio');
        self::destinatario($pdf, [$x['firmante'], $x['cargo']], '');
        self::parrafo($pdf, 'Con el envío de un cordial saludo, me dirijo a usted para informarle que el/la egresado/a: ' . $x['nombre']
            . ' con número de cuenta: ' . $x['cuenta'] . ' del Programa: ' . $x['programa'] . ', ha atendido satisfactoriamente las observaciones derivadas de su examen previo efectuado en fecha: '
            . self::fecha($x['fecha_previo'], true) . ', por lo que su proyecto de investigación de la modalidad: ' . $x['modalidad_nombre']
            . ' intitulado: «' . $x['titulo'] . '» se encuentra en condiciones de ser sustentado en evaluación de grado, motivo por el cual en calidad de Director de tesis o Trabajo terminal de grado emito el VOTO APROBATORIO, para que continue con el proceso correspondiente.');
        self::parrafo($pdf, 'Sin otro particular de momento, le reitero la seguridad de mi consideración distinguida.');
        self::firma($pdf, $x['director'], ['Nombre, y firma autógrafa del', 'Director de tesis o Trabajo terminal de grado.'], 20.0);
        self::ccp($pdf, ['c.c.p. Alumno/egresado.']);
        return $pdf;
    }

    /** R 24 · Formato de datos del interesado para posgrado. */
    private static function datosInteresado(array $x): Hoja
    {
        $pdf = self::nuevo();
        $pdf->SetY(40);
        self::tituloOficio($pdf, 'Formato de datos del interesado para posgrado' . "\n" . '(Maestría, doctorado y diploma de especialidad con evaluación final)', 10.5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Write(5.2, 'Instrucciones: ');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Write(5.2, self::l1('El interesado deberá llenar autógrafamente su nombre, con mayúsculas, minúsculas y acentos. Tal como lo escriba aparecerá en grado o diploma. Es necesario indicar claramente los acentos. El formato no es válido si se llena únicamente con letra mayúscula o presenta abreviaturas en el nombre que no concuerden con la CURP.'));
        $pdf->Ln(11);
        self::campo($pdf, 'Nombre:', $x['nombre']);
        self::campo($pdf, 'No. de cuenta:', $x['cuenta']);
        self::parrafo($pdf, 'Coloque Sí o No, para completar el siguiente enunciado de acuerdo con su caso:', 10.5, 1.5);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Write(6, self::l1('El nombre en el grado o diploma '));
        $pdf->SetFont('Helvetica', 'BU', 10.5);
        $pdf->Write(6, self::l1('  ' . $x['acentos'] . '  '));
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Write(6, self::l1(' lleva acentos.'));
        $pdf->Ln(9);
        self::campo($pdf, 'Plan de estudio:', $x['plan'] !== '' ? $x['plan'] : $x['programa']);
        self::campo($pdf, 'Teléfono:', $x['telefono']);
        self::campo($pdf, 'Correo electrónico:', $x['correo']);
        self::campo($pdf, 'Ocupación actual:', $x['ocupacion']);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Write(5.2, 'NOTA: ');
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->Write(5.2, self::l1('El nombre en este formato debe corresponder (mayúsculas, minúsculas y acentos) al que se indique en el proceso de actualización de datos en el portal de alumnos. Cualquier inconsistencia será causante de atraso en la expedición del grado o diploma.'));
        $pdf->Ln(10);
        self::parrafo($pdf, 'Hago constar que la información proporcionada en este documento es correcta y soy responsable de los datos mencionados.');
        self::firma($pdf, $x['nombre'], ['Nombre y firma del Interesado'], 20.0, false);
        return $pdf;
    }

    /** 1.1 · Oficio de solicitud de evaluación de grado (firma autógrafa del director). */
    private static function solicitud(array $x): Hoja
    {
        $pdf = self::nuevo();
        $pdf->SetY(40);
        self::tituloOficio($pdf, 'Oficio de solicitud de evaluación de grado');
        self::destinatario($pdf, [$x['firmante'], $x['cargo']], '', 2.0);
        $p = $x['propuesta'];
        self::parrafo($pdf, 'Con el envío de un cordial saludo, me dirijo a usted para solicitar su autorización para que el egresado/a: ' . $x['nombre']
            . ' con número de cuenta: ' . $x['cuenta'] . ' del Programa: ' . $x['programa'] . ', presente su EVALUACIÓN DE GRADO, de su proyecto de investigación de la modalidad: '
            . $x['modalidad_nombre'] . ' intitulado: «' . $x['titulo'] . '» el día: ' . self::fecha($p['fecha'], true) . ' a las ' . self::hora($p['hora'])
            . ' horas, con el siguiente Sínodo propuesto:', 10, 1.5);
        $cols = [['No.', 10, 'C'], ['Nombre completo del profesor', 96], ['Tipo de miembro', 60]];
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Propietarios:', 0, 1);
        $filas = [];
        foreach (array_slice($x['miembros'], 0, 5) as $n => $m) {
            $filas[] = [(string) ($n + 1), $m['nombre'], $m['etiqueta'] . ($m['externo'] ? ' (externo a la UAEMéx)' : '')];
        }
        self::tabla($pdf, $cols, $filas);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, 'Suplentes:', 0, 1);
        $filas = [];
        foreach (array_slice($x['miembros'], 5, 2) as $n => $m) {
            $filas[] = [(string) ($n + 6), $m['nombre'], $m['etiqueta'] . ($m['externo'] ? ' (externo a la UAEMéx)' : '')];
        }
        self::tabla($pdf, $cols, $filas);
        self::parrafo($pdf, 'Sin otro particular de momento, le reitero la seguridad de mi consideración distinguida.', 10, 0);
        self::firma($pdf, $x['director'], ['Nombre, fecha y firma autógrafa del', 'Director de tesis o Trabajo terminal de grado.'], 12.0);
        self::ccp($pdf, ['c.c.p. Alumno/egresado.']);
        return $pdf;
    }

    /** 1.2 · Visto bueno del sínodo (cada integrante firma). */
    private static function vistoBueno(array $x): Hoja
    {
        $pdf = self::nuevo();
        $pdf->SetY(40);
        self::tituloOficio($pdf, 'Visto bueno para realización de evaluación definitiva', 11);
        $p = $x['propuesta'];
        self::campo($pdf, 'Programa de Posgrado:', $x['programa']);
        self::campo($pdf, 'Nombre del sustentante:', $x['nombre']);
        $pdf->SetFont('Helvetica', '', 10.5);
        $pdf->Write(6, self::l1('Título de la tesis: '));
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Write(6, self::l1('«' . $x['titulo'] . '»'));
        $pdf->Ln(8);
        self::campo($pdf, 'Fecha programada del examen:', self::fecha($p['fecha'], true));
        self::campo($pdf, 'Hora del examen:', self::hora($p['hora']) . ' horas');
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 6, self::l1('Visto Bueno del Sínodo respecto a:'), 0, 1);
        $pdf->Ln(1);
        // Encabezado de dos niveles.
        $x0 = self::IZQ;
        $y = $pdf->GetY();
        $wNom = 58.0;
        $wSN = 12.0;
        $wFirma = self::ANCHO - $wNom - 6 * $wSN;
        $pdf->SetFillColor(...Hoja::VERDE);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(110, 110, 110);
        $pdf->SetFont('Helvetica', 'B', 8.5);
        $alto = 18.0;
        $pdf->Rect($x0, $y, $wNom, $alto, 'DF');
        $pdf->SetXY($x0, $y + 7);
        $pdf->Cell($wNom, 4, self::l1('Integrante del sínodo'), 0, 0, 'C');
        $grupos = ['Tesis concluida', 'Tesis entregada a usted', 'Revisión en el periodo solicitado'];
        foreach ($grupos as $k => $g) {
            $gx = $x0 + $wNom + $k * 2 * $wSN;
            $pdf->SetFont('Helvetica', 'B', 7.5);
            $pdf->Rect($gx, $y, 2 * $wSN, 13, 'DF');
            $l = $pdf->renglones(2 * $wSN, self::l1($g));
            $pdf->SetXY($gx, $y + (13 - $l * 3.3) / 2);
            $pdf->MultiCell(2 * $wSN, 3.3, self::l1($g), 0, 'C');
            $pdf->SetFont('Helvetica', 'B', 8.5);
            foreach (['Sí', 'No'] as $j => $sn) {
                $pdf->Rect($gx + $j * $wSN, $y + 13, $wSN, 5, 'DF');
                $pdf->SetXY($gx + $j * $wSN, $y + 13.5);
                $pdf->Cell($wSN, 4, self::l1($sn), 0, 0, 'C');
            }
        }
        $fx = $x0 + $wNom + 6 * $wSN;
        $pdf->Rect($fx, $y, $wFirma, $alto, 'DF');
        $pdf->SetXY($fx, $y + 7);
        $pdf->Cell($wFirma, 4, 'Firma', 0, 0, 'C');
        $pdf->SetTextColor(25, 25, 25);
        $y += $alto;
        foreach ($x['miembros'] as $n => $m) {
            $h = 13.0;
            $pdf->Rect($x0, $y, $wNom, $h);
            $pdf->SetFont('Helvetica', 'B', 8.5);
            $texto = self::l1($m['nombre']);
            $l = $pdf->renglones($wNom, $texto);
            $pdf->SetXY($x0, $y + 1);
            $pdf->MultiCell($wNom, 3.8, $texto, 0, 'L');
            $pdf->SetFont('Helvetica', '', 7.5);
            $pdf->SetXY($x0, $y + 1 + $l * 3.8);
            $pdf->MultiCell($wNom, 3.4, self::l1($m['etiqueta'] . ' · ' . $m['cargo']), 0, 'L');
            for ($k = 0; $k < 6; $k++) {
                $pdf->Rect($x0 + $wNom + $k * $wSN, $y, $wSN, $h);
            }
            $pdf->Rect($fx, $y, $wFirma, $h);
            $y += $h;
        }
        $pdf->SetY($y + 4);
        self::parrafo($pdf, 'Cada integrante marca Sí o No en cada columna y firma de manera autógrafa.', 8.5, 1, 'L');
        return $pdf;
    }

    /** 1 · Oficio de cotejo dirigido al director, con el anexo de instrucciones (Puntos a considerar). */
    private static function cotejo(array $x, array $c): Hoja
    {
        $pdf = self::nuevo();
        $pdf->SetY(38);
        self::tituloOficio($pdf, 'Oficio de cotejo de entrega total de requisitos para examen de grado', 10.5);
        $pdf->SetFont('Helvetica', 'B', 9.5);
        $pdf->Cell(0, 4.6, self::l1('Recepción del total de requisitos:'), 0, 1, 'R');
        $pdf->SetFont('Helvetica', '', 9.5);
        $pdf->Cell(0, 4.6, self::l1('Fecha: ' . self::fecha((string) ($c['fecha'] ?? ''), true)), 0, 1, 'R');
        $pdf->Cell(0, 4.6, self::l1('Hora: ' . self::hora((string) ($c['hora'] ?? '')) . ' horas'), 0, 1, 'R');
        $pdf->Ln(3);
        self::destinatario($pdf, [$x['director'] . '.', 'Director de tesis o trabajo terminal de grado.']);
        self::parrafo($pdf, 'Por este conducto hago de su conocimiento que el alumno o egresado: ' . $x['nombre'] . ', con número de cuenta: ' . $x['cuenta']
            . ' del Programa: ' . $x['programa'] . ' de Estudios Avanzados ha entregado el total de requisitos para examen de grado.', 10.5, 2.5);
        self::parrafo($pdf, 'Por lo anterior, ya se encuentra en posibilidad de proponer: sínodo (artículo 65 del REA), día y hora para llevar a cabo su Examen de Grado. Este deberá programarse con mínimo 10 días hábiles a partir de la fecha que entregue de manera presencial:', 10.5, 1.5);
        foreach ([
            '1) Oficio de solicitud firmado de manera autógrafa por Usted, (anexo 1)',
            '2) Formato de Visto Bueno firmado de manera autógrafa por todos los miembros del Sínodo Propuesto, (anexo 2) y',
            '3) Confirmación de firma electrónica activa en el SICDE (sacar hoja de prueba del portal: https://fise.uaemex.mx/#/login)',
        ] as $l) {
            $pdf->SetX(self::IZQ + 6);
            $pdf->SetFont('Helvetica', '', 10.5);
            $pdf->MultiCell(self::ANCHO - 6, 5.3, self::l1($l), 0, 'L');
        }
        $pdf->Ln(2);
        self::mixto($pdf, [['Nota importante: ', true], ['Considerar el tiempo que requiere para recabar firmas de cada uno de los sinodales en el formato de visto bueno del sínodo.', false]], 10, 1.5);
        self::mixto($pdf, [['Anexo 3: ', true], ['Instrucciones a considerar para su examen de Grado.', false]], 10, 0);
        self::firmaCoordinacion($pdf, $x, 14.0);
        self::ccp($pdf, ['c.c.p. Alumno/egresado.']);

        // Anexo 3: «Puntos a considerar» del trámite.
        $pdf->AddPage();
        $pdf->SetY(40);
        self::tituloOficio($pdf, 'Anexo 3. Puntos a considerar para su evaluación de grado', 10.5);
        $correos = Configuracion::apoyoCorreos();
        $puntos = [
            'No se podrá programar examen definitivo si existe un periodo inferior a: 10 días hábiles entre la recepción del oficio de solicitud de examen definitivo y la fecha propuesta para el examen. (SE SUGIERE CONSIDERAR 15 DÍAS o más entre la entrega total de requisitos y la fecha propuesta para hacer el examen).',
            'Para DOCTORADO (DCI) es necesario presentar evidencia de: «…un artículo de investigación original aceptado por una revista especializada arbitraria e indexada de reconocimiento internacional, o capítulo de libro…» Lo anterior, de acuerdo a lo que establece el artículo 60 Ter en sus fracciones III, IV y V. Además de lo que dicta la normatividad 15.10 del programa del DCI.',
            'El Director de Tesis, es el responsable de verificar que las firmas electrónicas de los profesores del sínodo estén vigentes a la fecha de la evaluación de grado.',
            'Para el caso de que haya profesores externos al programa, como parte del Sínodo se deberá verificar si se encuentra registrado en el Sistema de Control Escolar de la UAEMex, en caso contrario se le pedirá al alumno y a su Director de Tesis, documentos del profesor para solicitar sea dado de alta en dicho sistema (esto se debe verificar previo a la programación de su Examen de grado). Además de contar con la evidencia de que el profesor externo cuente ya con su firma electrónica para firmar su Acta de Evaluación a través del SICDE.',
            'Una vez aprobado el examen de grado el alumno deberá registrar su grado electrónico en el portal de alumnos de Control Escolar en máximo 48 horas después de concluido el examen y entregar o enviar vía correo electrónico el número de folio de seguimiento que le arroja el SICDE al correo ' . $correos . '.',
            'Director(a) de Tesis: La OCA (Oficina del Conocimiento Abierto) de la UAEM solicita atentamente su colaboración para que las tesis de grado se suban al RI (Repositorio Institucional) en los 3 días siguientes a la aprobación del examen de grado.',
        ];
        foreach ($puntos as $n => $texto) {
            $pdf->SetFont('Helvetica', 'B', 10);
            $pdf->Cell(8, 5.2, ($n + 1) . '.', 0, 0);
            $pdf->SetFont('Helvetica', '', 10);
            $pdf->MultiCell(self::ANCHO - 8, 5.2, self::l1($texto), 0, 'J');
            $pdf->Ln(2);
        }
        $pdf->Ln(2);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 5.2, 'FORMATOS REQUERIDOS', 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        foreach (['i.  Hoja Membretada-2025-2029', 'ii.  Visto Bueno para realización de evaluación definitiva en la fecha solicitada.'] as $l) {
            $pdf->SetX(self::IZQ + 6);
            $pdf->Cell(0, 5.2, self::l1($l), 0, 1);
        }
        return $pdf;
    }

    /** Frase del examen: «bajo la modalidad presencial el próximo viernes 02 de octubre de 2026 a las 12:00 horas, en …» */
    private static function examen(array $x, bool $bajo = true): string
    {
        $p = $x['prog'];
        $lugar = trim($p['lugar']);
        return ($bajo ? 'bajo la modalidad ' : 'en modalidad ') . ($p['modalidad'] === 'virtual' ? 'virtual' : 'presencial') . ' el próximo ' . Flujo::fechaLarga($p['fecha'])
            . ' a las ' . self::hora($p['hora']) . ' horas' . ($lugar !== '' ? ', en ' . $lugar : '');
    }

    private static function textoInvitacion(array $x, string $cargo): string
    {
        $de = preg_match('/^(Doctorado|Diplomado)\b/iu', $x['programa']) ? 'del ' : 'de la ';
        return 'Por este conducto me permito solicitar a Usted aceptar el cargo de miembro ' . $cargo . ' del sínodo para la evaluación del examen de grado ' . $de
            . $x['programa'] . ' que presenta ' . $x['egresado'] . ': ' . $x['nombre'] . ' con su ' . $x['trabajo'] . ': «' . $x['titulo'] . '»';
    }

    /** 2 · Oficios de invitación: una hoja por integrante (propietarios y suplentes). */
    private static function invitaciones(array $x): Hoja
    {
        $pdf = self::nuevo();
        $primera = true;
        foreach ($x['miembros'] as $m) {
            if ($m['nombre'] === '') {
                continue;
            }
            if (!$primera) {
                $pdf->AddPage();
            }
            $primera = false;
            self::lugarFecha($pdf, 'Ciudad Universitaria, Toluca; México a ' . self::fecha() . '.');
            self::destinatario($pdf, [$m['nombre'] . '.', $m['etiqueta'] . '.']);
            self::parrafo($pdf, self::textoInvitacion($x, $m['cargo'] === 'Propietario' ? 'propietario' : 'suplente'));
            self::parrafo($pdf, 'Dicho examen se llevará a cabo ' . self::examen($x) . '.');
            self::parrafo($pdf, 'Sin más por el momento, y esperando contar con su participación y disponibilidad para realizar trámites administrativos futuros que se deriven de este examen.');
            self::firmaCoordinacion($pdf, $x, 18.0);
            self::ccp($pdf, ['c.c.p. Archivo.']);
        }
        return $pdf;
    }

    /** 3 · Acuse de recibido de oficios de cada miembro del sínodo. */
    private static function acuse(array $x): Hoja
    {
        $pdf = self::nuevo();
        $pdf->SetY(38);
        self::tituloOficio($pdf, 'Acuse de recibido de oficios de cada miembro del sínodo del examen de grado', 10.5);
        self::parrafo($pdf, self::textoInvitacion($x, 'propietario o suplente'), 10, 2);
        self::parrafo($pdf, 'Dicho examen se llevará a cabo ' . self::examen($x) . '.', 10, 2);
        $trabajo = $x['modalidad'] === 'trabajo_terminal' ? 'TTG' : $x['trabajo'];
        self::parrafo($pdf, 'Se pide su firma de su confirmación de la entrega de su respectivo oficio y ' . ($trabajo === 'TTG' ? 'el TTG' : 'el trabajo de ' . $trabajo) . ' final ' . $x['alumno_de'] . '.', 10, 2);
        $filas = [];
        foreach ($x['miembros'] as $n => $m) {
            $filas[] = [(string) ($n + 1), $m['nombre'], $m['etiqueta'], $m['cargo'], ''];
        }
        self::tabla($pdf, [['No.', 10, 'C'], ['Docente', 54], ['Tipo de miembro', 42], ['Cargo', 24, 'C'], ['Firma', 36]], $filas, 8.5, 11.0);
        self::parrafo($pdf, 'Es importante que verifiquen que cuentan con su firma electrónica activa del SICDE para que puedan participar en este examen de grado.', 9.5, 0);
        self::firmaCoordinacion($pdf, $x, 12.0);
        return $pdf;
    }

    /** 1.3 · Notificación al director de tesis. */
    private static function notificacion(array $x): Hoja
    {
        $pdf = self::nuevo();
        self::lugarFecha($pdf, 'Ciudad Universitaria, a ' . self::fecha() . '.', 36);
        $nombre = mb_strtoupper($x['nombre']);
        self::destinatario($pdf, [mb_strtoupper($x['director']) . '.', 'DIRECTOR DE TESIS DE ' . $nombre . '.'], 'P r e s e n t e', 2.5);
        $tam = 9.4;
        $del = $x['modalidad'] === 'trabajo_terminal' ? 'del Trabajo Terminal de Grado' : 'de la ' . ucfirst($x['trabajo']);
        self::parrafo($pdf, 'Estimado Director (a) ' . $del . ' de la evaluación de grado de ' . $nombre . ', la cual se llevará a cabo el próximo ' . Flujo::fechaLarga($x['prog']['fecha'])
            . ' a las ' . self::hora($x['prog']['hora']) . ' horas, en modalidad ' . ($x['prog']['modalidad'] === 'virtual' ? 'virtual' : 'presencial') . ($x['prog']['lugar'] !== '' ? ' en ' . $x['prog']['lugar'] : '') . '.', $tam, 2);
        self::parrafo($pdf, 'Atendiendo al Procedimiento general para presentar exámenes de grado emitido por la Facultad de Ingeniería a través de la Coordinación de Estudios Avanzados F.I. y para dar cumplimiento a lo establecido por la Dirección de Servicios Escolares de la UAEMex, le solicito realizar las siguientes actividades:', $tam, 2);
        $items = [
            'Tres días hábiles previos al examen de grado Usted Director(a) de tesis del alumno (a) deberá enviar vía correo electrónico comprobante de que la firma electrónica de todos los sinodales y en especial el de los externos esta activa, lo anterior de acuerdo a lo siguiente: Para renovación de firma cuando se conoce la clave, ingresa al siguiente formulario: https://fise.uaemex.mx/#/login',
            'Cinco minutos previos del comienzo del examen de grado, deberá indicar o notificar al personal de apoyo de Apoyo a Grado la conformación definitiva del Sínodo; posterior a ello (durante la deliberación) deberá notificar el resultado del examen (veredicto) y los datos necesarios para la captura del acta correspondiente. Al mismo tiempo el departamento le entregará el acta para proceder con firmas autógrafas y con la lectura correspondiente.',
            'Todos los sinodales deberán firmar electrónicamente el acta dentro de las 24 horas después de concluido el examen.',
            'Recordar al Tesista que deberá registrar su grado electrónico en el portal de alumnos de Control Escolar en máximo 48 horas después de concluido el examen, y entregar o enviar vía correo electrónico el número de folio de seguimiento que le arroja el SICDE al Departamento de Apoyo a Grado.',
        ];
        foreach ($items as $it) {
            $pdf->SetFont('Helvetica', '', $tam);
            $pdf->SetX(self::IZQ + 3);
            $pdf->Cell(4, 5, chr(149), 0, 0);
            $pdf->MultiCell(self::ANCHO - 7, 4.7, self::l1($it), 0, 'J');
            $pdf->Ln(0.6);
        }
        $pdf->Ln(1);
        $apoyo = Configuracion::apoyoNombre();
        self::parrafo($pdf, 'Para agilizar el procedimiento y resolver cualquier duda al respecto, puede ponerse en contacto a través del correo electrónico: ' . Configuracion::apoyoCorreos()
            . ' con ' . ($apoyo !== '' ? $apoyo : 'el personal') . ' de Apoyo a Grado F.I. o al número telefónico ' . Configuracion::apoyoTelefono() . ', dentro del horario de lunes a viernes, de 08:00 a 15:00 horas.', $tam, 2);
        self::parrafo($pdf, 'Sin más por el momento, agradezco de antemano su compromiso y apoyo para realizar la evaluación de grado en tiempo, en forma y con apego a las directrices definidas. Quedo a sus órdenes para cualquier aclaración.', $tam, 0);
        self::firmaCoordinacion($pdf, $x, 8.0);
        self::ccp($pdf, ['c.c.p. ' . $nombre . '. /Egresado (a).', 'c.c.p. Archivo.']);
        return $pdf;
    }

    /** Tabla de las constancias 4 y 5. */
    private static function tablaConstancia(Hoja $pdf, array $x): void
    {
        self::tabla($pdf, [
            ['Título', 54, 'C'], ['Nombre del Alumno', 34, 'C'], ['Periodo de Inicio de estudios del alumno', 24, 'C'], ['Nombre del PE', 32, 'C'], ['Fecha de examen de grado.', 22, 'C'],
        ], [['«' . $x['titulo'] . '»', $x['nombre'], $x['periodo_inicio'], $x['programa'], self::fecha($x['fecha_examen'], true)]], 8.5);
    }

    private static function constancia(Hoja $pdf, array $x, string $quien, string $actividad, string $periodo): void
    {
        $pdf->SetY(42);
        self::destinatario($pdf, ['A QUIEN CORRESPONDA'], 'P R E S E N T E');
        self::parrafo($pdf, Configuracion::suscribe() . ', ' . $x['firmante'] . ', ' . Configuracion::cargoSinFi() . ' de la Facultad de Ingeniería de la Universidad Autónoma del Estado de México, hace CONSTAR que él/la '
            . mb_strtoupper($quien) . ' cumplió con las actividades como ' . $actividad . ', ' . $periodo . '.', 10.5, 4);
        self::tablaConstancia($pdf, $x);
        $pdf->Ln(2);
        self::parrafo($pdf, 'Se extiende la presente CONSTANCIA ' . self::extiende($x['fecha_examen']) . '.', 10.5, 0);
        self::firmaCoordinacion($pdf, $x, 16.0);
    }

    /** 4 · Constancias de revisores: una por integrante del sínodo definitivo. */
    private static function constanciasRevisores(array $x): Hoja
    {
        $pdf = self::nuevo();
        $periodo = 'dentro del periodo ' . self::periodo($x['fecha_examen']);
        foreach ($x['definitivo'] as $n => $m) {
            if ($n > 0) {
                $pdf->AddPage();
            }
            self::constancia($pdf, $x, $m['nombre'], 'REVISOR/a de Tesis de Grado o Trabajo Terminal de Grado (encargado/a de revisar el proyecto)', $periodo);
        }
        return $pdf;
    }

    /** 5 · Constancias de director, codirector y tutor. */
    private static function constanciasDireccion(array $x): Hoja
    {
        $pdf = self::nuevo();
        $periodo = 'durante el periodo ' . ($x['periodo_inicio'] !== '' ? $x['periodo_inicio'] . '-' : '') . self::periodo($x['fecha_examen']);
        $n = 0;
        foreach (['director' => 'DIRECTOR/a', 'codirector' => 'CO-DIRECTOR/a', 'tutor' => 'TUTOR/a'] as $k => $rol) {
            if ($x[$k] === '') {
                continue;
            }
            if ($n++ > 0) {
                $pdf->AddPage();
            }
            self::constancia($pdf, $x, $x[$k], $rol . ' de Tesis de Grado o Trabajo Terminal de Grado (encargado/a de dirigir el trabajo; Art. 61 del Reglamento de Estudios Avanzados- UAEM) de la tesis', $periodo);
        }
        return $pdf;
    }

    /** Constancia de no adeudo de equipo de cómputo, mobiliario y llaves. */
    private static function noAdeudo(array $x): Hoja
    {
        $pdf = self::nuevo();
        $pdf->SetY(40);
        $pdf->SetFont('Helvetica', 'B', 10.5);
        $pdf->Cell(0, 5, 'Asunto: CONSTANCIA', 0, 1, 'R');
        $pdf->Ln(4);
        self::destinatario($pdf, ['A QUIEN CORRESPONDA:'], 'P r e s e n t e');
        $art = $x['articulo'];
        $quien = $x['nivel'] === 'doctorado' ? $art . ' Doctorante' : $x['egresado'];
        $cierreDe = match ($art) {
            'la' => 'de la interesada',
            'el' => 'del interesado',
            default => 'de la persona interesada',
        };
        self::parrafo($pdf, Configuracion::suscribe() . ', ' . $x['firmante'] . ', ' . Configuracion::cargoSinFi() . ' de la Facultad de Ingeniería de la Universidad Autónoma del Estado de México, hace CONSTAR que '
            . $quien . ' ' . $x['nombre'] . ', del programa ' . $x['programa'] . ', no adeuda a esta Coordinación equipo de cómputo, mobiliario o llaves de módulo de trabajo, que tenía bajo su resguardo.', 10.5, 3);
        self::parrafo($pdf, 'A petición ' . $cierreDe . ' y para los fines académicos que haya lugar, se extiende la presente CONSTANCIA en la Ciudad de Toluca de Lerdo, Estado de México, ' . self::extiende(null, true) . '.', 10.5, 0);
        self::firmaCoordinacion($pdf, $x, 18.0, [Configuracion::cargoSinFi()]);
        self::ccp($pdf, ['c.c.p. Tutor.', 'c.c.p. Archivo.']);
        return $pdf;
    }

    /** 25 · Formato de integración de expediente con las casillas según modalidad y nivel. */
    private static function integracion(array $x): Hoja
    {
        $pdf = self::nuevo();
        $pdf->SetY(40);
        self::tituloOficio($pdf, 'Integración de expediente: posgrado' . "\n" . '(Maestría, doctorado y diploma de especialidad con evaluación final)', 10.5);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, self::l1('Marque con una X la modalidad de titulación a elegir:'), 0, 1);
        $pdf->Ln(1);
        $y = $pdf->GetY();
        foreach (['proyecto_terminal' => 'El proyecto terminal', 'tesis' => 'Tesis', 'trabajo_terminal' => 'Trabajo terminal', 'articulos' => 'Tesis por artículos especializados o capítulo para libro'] as $k => $texto) {
            if ($k === 'articulos') {
                $fin = $pdf->GetY();
                $pdf->SetY($y);
                self::casilla($pdf, $x['modalidad'] === $k, $texto, 90.0, 70.0);
                $pdf->SetY(max($fin, $pdf->GetY()));
            } else {
                self::casilla($pdf, $x['modalidad'] === $k, $texto, 6.0, 74.0);
            }
        }
        $pdf->Ln(3);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 6, self::l1('Documentos para solicitar la elaboración para posgrado (marque con una X):'), 0, 1);
        $pdf->Ln(1);
        $doc = $x['nivel'] === 'doctorado';
        $docs = [
            ['Dos fotografías tamaño semitítulo, blanco y negro, papel mate, fondo gris claro, de frente, con retoque, varones con camisa blanca y saco y corbata oscuros, mujeres con blazer oscuro', true],
            ['Acta de evaluación profesional', true],
            ['Copia del certificado de total de grado (Maestría o Doctorado)', true],
            ['Copia del certificado de licenciatura con sello y firma de cotejo del responsable de titulación (maestría y/o especialidad)', !$doc],
            ['Copia de la cédula profesional de licenciatura con sello y firma de cotejo del responsable de titulación (maestría y especialidad, doctorado con antecedente de licenciatura)', true],
            ['Copia del certificado de maestría con sello y firma de cotejo del responsable de titulación (doctorado)', $doc],
            ['Copia de la cédula de maestría con sello y firma de cotejo del responsable de titulación (doctorado)', $doc],
            ['Acta de nacimiento', true],
            ['Autorización para continuar con trámites de obtención de grado o diploma, con fundamento en el artículo 53° del Reglamento de Estudios Avanzados', $x['art53']],
            ['Formato de revisión para autorización de evaluación para posgrado', true],
            ['Formato de integración de expediente', true],
            ['Formato de datos del interesado para posgrado', true],
        ];
        foreach ($docs as [$texto, $marcada]) {
            self::casilla($pdf, $marcada, $texto);
        }
        $pdf->Ln(4);
        self::parrafo($pdf, 'Hago constar que se ha revisado el expediente y contiene todos los documentos requeridos para esta modalidad en este formato en el orden establecido.', 10, 0);
        $pdf->Ln(4);
        self::mixto($pdf, [['Egresado: ', true], [$x['nombre'] . ' · No. de cuenta ' . $x['cuenta'] . ' · ' . $x['programa'], false]], 9, 0);
        self::firma($pdf, ' ', ['Nombre y firma del Responsable de Evaluación de Grado', 'Sello del Espacio Académico'], 16.0, false);
        return $pdf;
    }
}
