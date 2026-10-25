<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

/**
 * Lector mínimo de libros .xlsx (sin dependencias): nombres de hoja y filas como arreglos de valores. Las celdas con
 * formato de fecha se devuelven como 'AAAA-MM-DD'; el resto como texto o número. Se recorre en flujo (XMLReader)
 * y se detiene tras muchas filas vacías, porque algunas hojas declaran millones de filas.
 */
final class Xlsx
{
    private \ZipArchive $zip;
    /** @var string[] */
    private array $textos = [];
    /** @var array<int,bool> índice de estilo => es fecha */
    private array $estilosFecha = [];
    /** @var array<string,string> nombre de hoja => ruta dentro del zip */
    private array $hojas = [];

    public function __construct(string $archivo)
    {
        $this->zip = new \ZipArchive();
        if ($this->zip->open($archivo) !== true) {
            throw new \RuntimeException('El archivo no es un libro de Excel (.xlsx) válido.');
        }
        $this->leerTextos();
        $this->leerEstilos();
        $this->leerHojas();
    }

    /** @return string[] */
    public function hojas(): array
    {
        return array_keys($this->hojas);
    }

    /**
     * @return \Generator<int,array<int,string|float|null>> número de fila (1..) => valores por columna (0..$maxCol-1)
     */
    public function filas(string $hoja, int $maxCol = 40, int $vaciasMax = 300): \Generator
    {
        $xml = $this->zip->getFromName($this->hojas[$hoja] ?? '');
        if ($xml === false) {
            return;
        }
        $r = new \XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $vacias = 0;
        $fila = null;
        $num = 0;
        $celda = null;
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'row') {
                $num = (int) $r->getAttribute('r');
                $fila = array_fill(0, $maxCol, null);
                if ($r->isEmptyElement) {
                    $fila = null;
                    if (++$vacias > $vaciasMax) {
                        return;
                    }
                }
            } elseif ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'c' && $fila !== null) {
                $col = self::columna((string) $r->getAttribute('r'));
                $celda = ['col' => $col, 't' => (string) $r->getAttribute('t'), 's' => (int) $r->getAttribute('s'), 'v' => null];
                if ($r->isEmptyElement) {
                    $celda = null;
                }
            } elseif ($r->nodeType === \XMLReader::ELEMENT && ($r->localName === 'v' || $r->localName === 't') && $celda !== null) {
                $celda['v'] = ($celda['v'] ?? '') . $r->readString();
            } elseif ($r->nodeType === \XMLReader::END_ELEMENT && $r->localName === 'c' && $celda !== null && $fila !== null) {
                if ($celda['col'] < $maxCol) {
                    $fila[$celda['col']] = $this->valor($celda);
                }
                $celda = null;
            } elseif ($r->nodeType === \XMLReader::END_ELEMENT && $r->localName === 'row' && $fila !== null) {
                if (array_filter($fila, static fn($v): bool => $v !== null && $v !== '') === []) {
                    if (++$vacias > $vaciasMax) {
                        return;
                    }
                } else {
                    $vacias = 0;
                    yield $num => $fila;
                }
                $fila = null;
            }
        }
    }

    private function valor(array $c): string|float|null
    {
        $v = $c['v'];
        if ($v === null) {
            return null;
        }
        return match ($c['t']) {
            's' => trim(str_replace("\u{a0}", ' ', $this->textos[(int) $v] ?? '')),
            'inlineStr', 'str' => trim(str_replace("\u{a0}", ' ', $v)),
            'b' => $v === '1' ? 'TRUE' : 'FALSE',
            default => is_numeric($v) ? (!empty($this->estilosFecha[$c['s']]) ? self::fechaSerial((float) $v) : (float) $v) : trim($v),
        };
    }

    public static function fechaSerial(float $n): ?string
    {
        if ($n < 1 || $n > 2958465) {
            return null;
        }
        return (new \DateTimeImmutable('1899-12-30'))->modify('+' . (int) floor($n) . ' days')->format('Y-m-d');
    }

    private static function columna(string $ref): int
    {
        $n = 0;
        foreach (str_split(preg_replace('/[^A-Z]/', '', strtoupper($ref)) ?? '') as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return max(0, $n - 1);
    }

    private function leerTextos(): void
    {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return;
        }
        $r = new \XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        $actual = null;
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'si') {
                $actual = '';
            } elseif ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 't' && $actual !== null) {
                $actual .= $r->readString();
            } elseif ($r->nodeType === \XMLReader::END_ELEMENT && $r->localName === 'si') {
                $this->textos[] = (string) $actual;
                $actual = null;
            }
        }
    }

    private function leerEstilos(): void
    {
        $xml = $this->zip->getFromName('xl/styles.xml');
        if ($xml === false) {
            return;
        }
        $sx = @simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET);
        if ($sx === false) {
            return;
        }
        $propios = [];
        foreach ($sx->numFmts->numFmt ?? [] as $f) {
            $codigo = preg_replace('/"[^"]*"|\[[^\]]*\]/', '', strtolower((string) $f['formatCode'])) ?? '';
            $propios[(int) $f['numFmtId']] = (bool) preg_match('/[dy]|m{3,}/', $codigo);
        }
        $i = 0;
        foreach ($sx->cellXfs->xf ?? [] as $xf) {
            $id = (int) $xf['numFmtId'];
            $this->estilosFecha[$i++] = ($id >= 14 && $id <= 22) || in_array($id, [27, 30, 36, 45, 46, 47, 50, 57], true) || !empty($propios[$id]);
        }
    }

    private function leerHojas(): void
    {
        $wb = @simplexml_load_string((string) $this->zip->getFromName('xl/workbook.xml'), \SimpleXMLElement::class, LIBXML_NONET);
        $rels = @simplexml_load_string((string) $this->zip->getFromName('xl/_rels/workbook.xml.rels'), \SimpleXMLElement::class, LIBXML_NONET);
        if ($wb === false || $rels === false) {
            throw new \RuntimeException('El libro de Excel no tiene la estructura esperada.');
        }
        $destinos = [];
        foreach ($rels->Relationship as $rel) {
            $destinos[(string) $rel['Id']] = ltrim(preg_replace('#^/?xl/#', '', (string) $rel['Target']) ?? '', '/');
        }
        foreach ($wb->sheets->sheet as $s) {
            $id = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            if (isset($destinos[$id])) {
                $this->hojas[(string) $s['name']] = 'xl/' . $destinos[$id];
            }
        }
    }
}
