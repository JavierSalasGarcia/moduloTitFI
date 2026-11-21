<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use DOMDocument;
use RuntimeException;

/**
 * Casos de una reunión desde una hoja de Excel (.xlsx), una fila por caso. Columnas (el orden no importa, se reconocen por el
 * encabezado): clave (P1, A2, AG3…), solicitud, antecedentes, acuerdo académico, acuerdo gobierno y observación. Las tablas no
 * caben en una celda: se agregan después con el lienzo, pegándolas desde Word. También genera la plantilla en blanco.
 */
final class ExcelCasos
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_XML = 25 * 1024 * 1024;
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    public const COLUMNAS = ['clave' => 'Clave', 'solicitud' => 'Solicitud', 'antecedentes' => 'Documentos y/o antecedentes', 'acuerdo_academico' => 'Acuerdo del Consejo Académico', 'acuerdo_gobierno' => 'Acuerdo del Consejo de Gobierno', 'observacion' => 'Observación'];

    /** @return array<int,array{grupo:string,numero:int,solicitud_html:string,antecedentes_html:string,acuerdo_academico:string,acuerdo_gobierno:string,observacion:string}> */
    public static function leer(string $ruta, string $nombre): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Este servidor no puede leer archivos de Excel (falta la extensión ZipArchive de PHP).');
        }
        if (strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new RuntimeException('El archivo debe ser un libro de Excel con extensión .xlsx.');
        }
        $tam = @filesize($ruta);
        if (!$tam || $tam > self::MAX_BYTES) {
            throw new RuntimeException('El archivo está vacío o pesa más de ' . (int) (self::MAX_BYTES / 1048576) . ' MB.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($ruta, \ZipArchive::RDONLY) !== true || $zip->getFromName('[Content_Types].xml') === false) {
            throw new RuntimeException('El archivo no es un libro de Excel válido (.xlsx).');
        }
        try {
            $hoja = self::primeraHoja($zip);
            $compartidas = self::compartidas($zip);
            $xml = $zip->statName($hoja);
            if ($xml === false || $xml['size'] > self::MAX_XML) {
                throw new RuntimeException('No se pudo leer la hoja de cálculo.');
            }
            $contenido = (string) $zip->getFromName($hoja);
        } finally {
            $zip->close();
        }
        $doc = new DOMDocument();
        if (!@$doc->loadXML($contenido, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('No se pudo leer la hoja de cálculo.');
        }
        $filas = [];
        foreach ($doc->getElementsByTagNameNS(self::NS, 'row') as $row) {
            $f = [];
            foreach ($row->getElementsByTagNameNS(self::NS, 'c') as $c) {
                $col = self::indice((string) $c->getAttribute('r'));
                $t = $c->getAttribute('t');
                $v = '';
                if ($t === 'inlineStr') {
                    foreach ($c->getElementsByTagNameNS(self::NS, 't') as $x) {
                        $v .= $x->textContent;
                    }
                } else {
                    $n = $c->getElementsByTagNameNS(self::NS, 'v')->item(0);
                    $v = $n ? $n->textContent : '';
                    if ($t === 's') {
                        $v = $compartidas[(int) $v] ?? '';
                    }
                }
                $f[$col] = trim($v);
            }
            $filas[] = $f;
        }
        return self::casos($filas);
    }

    private static function casos(array $filas): array
    {
        $mapa = [];
        $inicio = 0;
        foreach ($filas as $i => $f) {
            foreach ($f as $col => $v) {
                $n = Texto::norm((string) $v);
                $k = match (true) {
                    $n === 'clave' => 'clave',
                    $n === 'solicitud' => 'solicitud',
                    str_starts_with($n, 'documentos') || str_starts_with($n, 'antecedentes') => 'antecedentes',
                    str_contains($n, 'academico') => 'acuerdo_academico',
                    str_contains($n, 'gobierno') => 'acuerdo_gobierno',
                    str_starts_with($n, 'observaci') => 'observacion',
                    default => null,
                };
                if ($k !== null) {
                    $mapa[$k] = $col;
                }
            }
            if (isset($mapa['clave'], $mapa['solicitud'])) {
                $inicio = $i + 1;
                break;
            }
            $mapa = [];
        }
        if (!isset($mapa['clave'], $mapa['solicitud'])) {
            throw new RuntimeException('No encontré los encabezados «Clave» y «Solicitud» en la primera hoja. Descarga la plantilla y úsala como modelo.');
        }
        $casos = [];
        foreach (array_slice($filas, $inicio) as $f) {
            $get = static fn(string $k): string => isset($mapa[$k]) ? (string) ($f[$mapa[$k]] ?? '') : '';
            $clave = strtoupper(preg_replace('/\s+/', '', $get('clave')) ?? '');
            if ($clave === '' && trim($get('solicitud')) === '') {
                continue;
            }
            if (!preg_match('/^(AG|G|P|A)(\d{1,3})$/', $clave, $m)) {
                throw new RuntimeException('La clave «' . $get('clave') . '» no es válida. Usa P1 (profesores), A1 (alumnos) o AG1 (asuntos generales).');
            }
            if (trim($get('solicitud')) === '') {
                throw new RuntimeException('El caso ' . $clave . ' no tiene solicitud.');
            }
            $casos[] = ['grupo' => ['P' => 'profesor', 'A' => 'alumno', 'AG' => 'general', 'G' => 'general'][$m[1]], 'numero' => (int) $m[2],
                'solicitud_html' => Html::deTexto($get('solicitud')), 'antecedentes_html' => Html::deTexto($get('antecedentes')),
                'acuerdo_academico' => $get('acuerdo_academico'), 'acuerdo_gobierno' => $get('acuerdo_gobierno'), 'observacion' => $get('observacion')];
        }
        if (!$casos) {
            throw new RuntimeException('La hoja no trae casos debajo de los encabezados.');
        }
        return $casos;
    }

    /** «B3» → 1 (índice de columna desde 0). */
    private static function indice(string $ref): int
    {
        preg_match('/^([A-Z]+)/', strtoupper($ref), $m);
        $n = 0;
        foreach (str_split($m[1] ?? 'A') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    private static function primeraHoja(\ZipArchive $zip): string
    {
        $wb = (string) $zip->getFromName('xl/workbook.xml');
        $rels = (string) $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== '' && $rels !== '' && preg_match('/<sheet\b[^>]*r:id="([^"]+)"/', $wb, $m) && preg_match('/<Relationship\b[^>]*Id="' . preg_quote($m[1], '/') . '"[^>]*>/', $rels, $r)
            && preg_match('/Target="([^"]+)"/', $r[0], $t)) {
            $dest = ltrim($t[1], '/');
            return str_starts_with($dest, 'xl/') ? $dest : 'xl/' . $dest;
        }
        return 'xl/worksheets/sheet1.xml';
    }

    /** @return string[] */
    private static function compartidas(\ZipArchive $zip): array
    {
        $x = $zip->statName('xl/sharedStrings.xml');
        if ($x === false || $x['size'] > self::MAX_XML) {
            return [];
        }
        $doc = new DOMDocument();
        if (!@$doc->loadXML((string) $zip->getFromName('xl/sharedStrings.xml'), LIBXML_NONET | LIBXML_COMPACT)) {
            return [];
        }
        $r = [];
        foreach ($doc->getElementsByTagNameNS(self::NS, 'si') as $si) {
            $s = '';
            foreach ($si->getElementsByTagNameNS(self::NS, 't') as $t) {
                $s .= $t->textContent;
            }
            $r[] = $s;
        }
        return $r;
    }

    /** Plantilla .xlsx en blanco con dos renglones de ejemplo. */
    public static function plantilla(): string
    {
        $filas = [array_values(self::COLUMNAS),
            ['P1', 'El Dr. Nombre Apellidos, profesor de esta Facultad, solicita a los H.H. Consejos Académico y de Gobierno la autorización para…', 'Oficio de solicitud', '', '', ''],
            ['AG1', 'El M. en I. Nombre Apellidos, coordinador de docencia, presenta la plantilla del semestre 2027A para su aprobación.', 'Plantilla preliminar (adjunto)', '', '', '']];
        $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="' . self::NS . '"><cols><col min="1" max="1" width="10" customWidth="1"/><col min="2" max="6" width="45" customWidth="1"/></cols><sheetData>';
        foreach ($filas as $i => $f) {
            $sheet .= '<row r="' . ($i + 1) . '">';
            foreach ($f as $j => $v) {
                $sheet .= '<c r="' . chr(65 + $j) . ($i + 1) . '" t="inlineStr"' . ($i === 0 ? ' s="1"' : '') . '><is><t xml:space="preserve">' . htmlspecialchars((string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
            }
            $sheet .= '</row>';
        }
        $sheet .= '</sheetData></worksheet>';
        $estilos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="' . self::NS . '"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>';
        $tmp = tempnam(sys_get_temp_dir(), 'xls');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible crear la plantilla.');
        }
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="' . self::NS . '" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Casos" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheet);
        $zip->addFromString('xl/styles.xml', $estilos);
        $zip->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }
}
