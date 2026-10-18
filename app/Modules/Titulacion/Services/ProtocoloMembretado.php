<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;

/**
 * Protocolo de titulación en hoja membretada: texto corrido con las mismas partes del
 * formato oficial, sin logotipo lateral ni tablas (salvo el cronograma).
 */
final class ProtocoloMembretado
{
    private const FUENTE = '<w:rFonts w:ascii="DM Sans" w:hAnsi="DM Sans" w:cs="Arial"/>';

    public static function fechaLarga(string $iso): string
    {
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
        return $f ? (int) $f->format('j') . ' de ' . Catalogo::MESES[(int) $f->format('n')] . ' de ' . $f->format('Y') : $iso;
    }

    private static function x(string $t): string
    {
        return htmlspecialchars($t, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @param array<int,array{0:string,1?:bool}> $runs [texto, negrita] */
    private static function p(array $runs, string $alinea = 'both', int $despues = 120, bool $salto = false, bool $mantener = false): string
    {
        $cuerpo = '';
        foreach ($runs as $r) {
            $txt = $r[0];
            $b = !empty($r[1]) ? '<w:b/><w:bCs/>' : '';
            $cuerpo .= '<w:r><w:rPr>' . self::FUENTE . $b . '<w:sz w:val="22"/><w:szCs w:val="22"/><w:lang w:val="es-MX"/></w:rPr><w:t xml:space="preserve">' . self::x($txt) . '</w:t></w:r>';
        }
        return '<w:p><w:pPr>' . ($mantener ? '<w:keepNext/>' : '') . ($salto ? '<w:pageBreakBefore/>' : '')
            . '<w:spacing w:after="' . $despues . '" w:line="264" w:lineRule="auto"/><w:jc w:val="' . $alinea . '"/></w:pPr>' . $cuerpo . '</w:p>';
    }

    /** Respuesta de varias líneas: un párrafo por renglón. */
    private static function texto(string $valor, string $alinea = 'both'): string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return self::p([['—']], 'left');
        }
        $salida = '';
        foreach (preg_split('/\R/u', $valor) ?: [] as $linea) {
            $salida .= self::p([[trim($linea)]], $alinea, 80);
        }
        return $salida;
    }

    private static function cronograma(array $filas): string
    {
        $borde = '<w:tcBorders><w:top w:val="single" w:sz="4" w:color="808080"/><w:left w:val="single" w:sz="4" w:color="808080"/><w:bottom w:val="single" w:sz="4" w:color="808080"/><w:right w:val="single" w:sz="4" w:color="808080"/></w:tcBorders>';
        $celda = static function (string $txt, int $ancho, bool $negrita = false, string $fondo = '', string $alinea = 'center') use ($borde): string {
            $sombra = $fondo !== '' ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $fondo . '"/>' : '';
            $b = $negrita ? '<w:b/>' : '';
            return '<w:tc><w:tcPr><w:tcW w:w="' . $ancho . '" w:type="dxa"/>' . $borde . $sombra . '<w:vAlign w:val="center"/></w:tcPr><w:p><w:pPr><w:spacing w:before="40" w:after="40"/><w:jc w:val="' . $alinea . '"/></w:pPr>'
                . '<w:r><w:rPr>' . self::FUENTE . $b . '<w:sz w:val="18"/><w:szCs w:val="18"/></w:rPr><w:t xml:space="preserve">' . self::x($txt) . '</w:t></w:r></w:p></w:tc>';
        };
        $colA = 2838;
        $colB = 500;
        $grid = '<w:tblGrid><w:gridCol w:w="' . $colA . '"/>' . str_repeat('<w:gridCol w:w="' . $colB . '"/>', 12) . '</w:tblGrid>';
        $enc = '<w:tr><w:trPr><w:cantSplit/><w:tblHeader/></w:trPr>' . $celda('Actividades', $colA, true, 'D9D9D9', 'left');
        for ($b = 1; $b <= 12; $b++) {
            $enc .= $celda((string) $b, $colB, true, 'D9D9D9');
        }
        $enc .= '</w:tr>';
        $cuerpo = '';
        foreach (array_values($filas) as $fila) {
            $cuerpo .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>' . $celda((string) $fila['a'], $colA, false, '', 'left');
            for ($b = 1; $b <= 12; $b++) {
                $cuerpo .= $celda(in_array($b, (array) ($fila['b'] ?? []), true) ? 'X' : '', $colB);
            }
            $cuerpo .= '</w:tr>';
        }
        $rotulo = self::p([['Bimestres de ejecución (1 a 12)']], 'left', 60, false, true);
        return $rotulo . '<w:tbl><w:tblPr><w:tblW w:w="' . ($colA + 12 * $colB) . '" w:type="dxa"/><w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="70" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            . $grid . $enc . $cuerpo . '</w:tbl>' . self::p([['']], 'left', 120);
    }

    /**
     * @param array $d dia, mes, anio, nombre, cuenta, carrera, tema, subdirector,
     *                 fecha_nacimiento (texto largo), correo, telefono, secciones (campos del catálogo con 'valor')
     */
    public static function generar(string $modalidad, array $d): string
    {
        $m = Catalogo::modalidad($modalidad);
        $proto = Catalogo::protocolo($modalidad);
        if ($m === null || $proto === null) {
            throw new \RuntimeException('Modalidad sin protocolo.');
        }
        $carrera = Catalogo::carreras()[$d['carrera'] ?? '']['nombre'] ?? '____________________';
        $subdirector = mb_strtoupper((string) ($d['subdirector'] ?: 'DR. JAVIER SALAS GARCÍA'));
        $nombre = (string) $d['nombre'];
        $x = '';

        $x .= self::p([['SOLICITUD DE APROBACIÓN DE TEMA DE TITULACIÓN MEDIANTE ' . mb_strtoupper($m['nombre']), true]], 'center', 240);
        $x .= self::p([['Toluca, México; ' . $d['dia'] . ' de ' . $d['mes'] . ' de ' . $d['anio']]], 'right', 240);
        foreach ([$subdirector, 'SUBDIRECTOR ACADÉMICO', 'DE LA FACULTAD DE INGENIERÍA', 'DE LA UNIVERSIDAD AUTÓNOMA DEL ESTADO DE MÉXICO'] as $l) {
            $x .= self::p([[$l, true]], 'left', 0);
        }
        $x .= self::p([['PRESENTE', true]], 'left', 240);
        $x .= self::p([['Quien suscribe, '], [$nombre, true], [', con número de cuenta: '], [(string) $d['cuenta'], true], [', pasante de ' . $carrera . ', mediante el presente somete a su consideración el tema:']]);
        $x .= self::p([[(string) ($d['tema'] ?: '—'), true]], 'center', 160);
        $x .= self::p([['El cual será desarrollado para la evaluación profesional en la modalidad de '], [$m['nombre'], true], ['.']]);
        $x .= self::p([['Adjunto al presente, el protocolo con la propuesta del tema para que sea revisado y, en su caso, aprobado.']]);
        $x .= self::p([['Sin otro particular, quedo a sus órdenes.']], 'both', 360);
        $x .= self::p([['ATENTAMENTE', true]], 'center', 720, false, true);
        $x .= self::p([['______________________________________']], 'center', 0, false, true);
        $x .= self::p([['NOMBRE Y FIRMA DEL PASANTE', true]], 'center', 0, false, true);
        $x .= self::p([[$nombre]], 'center', 360);
        $x .= self::p([['Fecha de entrega: ____ / ____ / ________      Firma de recibido: ______________________']], 'left', 80);
        $x .= self::p([['Fecha de dictamen (la llena la comisión evaluadora): ____ / ____ / ________']], 'left', 80);

        $x .= self::p([['INFORMACIÓN DEL PROTOCOLO', true]], 'center', 200, true, true);
        $x .= self::p([['Nombre del pasante: ', true], [$nombre]], 'left', 40);
        $x .= self::p([['Número de cuenta: ', true], [(string) $d['cuenta']]], 'left', 200);

        $cron = null;
        $secciones = $d['secciones'];
        usort($secciones, static fn(array $a, array $b): int => (($b['tipo'] ?? '') === 'fecha') <=> (($a['tipo'] ?? '') === 'fecha'));
        foreach ($secciones as $c) {
            $valor = $c['valor'] ?? '';
            if (($c['tipo'] ?? '') === 'cronograma') {
                $cron = $c;
                continue;
            }
            if (is_string($valor) && trim($valor) === '' && !empty($c['vacio'])) {
                $valor = (string) $c['vacio'];
            }
            if (($c['tipo'] ?? '') === 'fecha') {
                $x .= self::p([[$c['label'] . ': ', true], [$valor !== '' ? self::fechaLarga((string) $valor) : '—']], 'left', 120);
                continue;
            }
            if (!isset($c['h'])) {
                continue;
            }
            $x .= self::p([[$c['h'], true]], 'left', 40, false, true);
            $x .= self::texto((string) $valor);
        }
        if ($cron !== null) {
            $x .= self::p([[$cron['h'], true]], 'left', 60, false, true);
            $x .= self::cronograma(is_array($cron['valor'] ?? null) ? $cron['valor'] : []);
        }
        $x .= self::p([['NOTA: ', true], ['El tema tendrá una vigencia de dos años, a partir de la fecha de aceptación (Ver Art. 86, Fracc. VIII, del Reglamento de Evaluación Profesional).']], 'both', 480);

        $x .= self::p([['Vo. Bo.', true]], 'center', 600, false, true);
        $x .= self::p([['______________________________            ______________________________']], 'center', 0, false, true);
        $x .= self::p([['Nombre y firma del pasante                              Nombre y firma del asesor']], 'center', 360);

        $x .= self::p([['DATOS PERSONALES', true]], 'left', 80, false, true);
        $x .= self::p([['Fecha de nacimiento: ', true], [(string) ($d['fecha_nacimiento'] ?: '—')]], 'left', 40);
        $x .= self::p([['Correo electrónico: ', true], [(string) ($d['correo'] ?: '—')]], 'left', 40);
        $x .= self::p([['Teléfono celular: ', true], [(string) ($d['telefono'] ?: '—')]], 'left', 40);

        $ed = new DocxEditor(dirname(__DIR__) . '/plantillas/docx/Membretada.docx');
        $ed->reemplazarCuerpo($x);
        return $ed->guardar();
    }
}
