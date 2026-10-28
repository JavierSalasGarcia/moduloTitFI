<?php
declare(strict_types=1);

namespace App\Modules\Formularios;

/** Escritor mínimo de libros .xlsx (texto en celdas, una o varias hojas, las dos primeras filas en negritas). */
final class XlsxEscritor
{
    /** @var array<int,array{nombre:string,filas:array,negritas:int[]}> */
    private array $hojas = [];

    /** @param int[]|null $negritas índices de fila (desde 0) en negritas; null = las dos primeras */
    public function hoja(string $nombre, array $filas, ?array $negritas = null): void
    {
        $nombre = mb_substr(trim(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $nombre) ?? 'Hoja'), 0, 31) ?: 'Hoja';
        $usados = array_column($this->hojas, 'nombre');
        for ($i = 2, $base = $nombre; in_array($nombre, $usados, true); $i++) {
            $nombre = mb_substr($base, 0, 28) . ' ' . $i;
        }
        $this->hojas[] = ['nombre' => $nombre, 'filas' => $filas, 'negritas' => $negritas ?? [0, 1]];
    }

    private static function x(string $s): string
    {
        return htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? '', ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function columna(int $i): string
    {
        $s = '';
        for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
            $s = chr(65 + ($i - 1) % 26) . $s;
        }
        return $s;
    }

    public function generar(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $z = new \ZipArchive();
        $z->open($tmp, \ZipArchive::OVERWRITE);
        $n = count($this->hojas);
        $tipos = '';
        $hojasWb = '';
        $rels = '';
        foreach ($this->hojas as $i => $h) {
            $k = $i + 1;
            $tipos .= '<Override PartName="/xl/worksheets/sheet' . $k . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $hojasWb .= '<sheet name="' . self::x($h['nombre']) . '" sheetId="' . $k . '" r:id="rId' . $k . '"/>';
            $rels .= '<Relationship Id="rId' . $k . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $k . '.xml"/>';
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach (array_values($h['filas']) as $f => $fila) {
                $xml .= '<row r="' . ($f + 1) . '">';
                foreach (array_values($fila) as $c => $v) {
                    $estilo = in_array($f, $h['negritas'], true) ? ' s="1"' : '';
                    $xml .= '<c r="' . self::columna($c) . ($f + 1) . '" t="inlineStr"' . $estilo . '><is><t xml:space="preserve">' . self::x((string) $v) . '</t></is></c>';
                }
                $xml .= '</row>';
            }
            $z->addFromString('xl/worksheets/sheet' . $k . '.xml', $xml . '</sheetData></worksheet>');
        }
        $z->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>' . $tipos . '</Types>');
        $z->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $z->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>' . $hojasWb . '</sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels . '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $z->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="2"><xf xfId="0"/><xf xfId="0" fontId="1" applyFont="1"/></cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>');
        $z->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }
}
