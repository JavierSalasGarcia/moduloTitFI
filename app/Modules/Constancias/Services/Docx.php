<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

/**
 * Lee el cuerpo de una constancia de un archivo de Word (.docx) sin dependencias externas: abre el ZIP con ZipArchive
 * y recorre word/document.xml. Conserva párrafos (con negritas, alineación y saltos de línea) y tablas (filas y
 * celdas; la primera fila es encabezado si está toda en negritas). Ignora imágenes, cuadros de texto, notas, colores y
 * estilos complejos, y devuelve avisos de lo que ignoró. Rechaza lo que no sea un .docx real y los documentos con macros.
 */
final class Docx
{
    public const MAX_BYTES = 2 * 1024 * 1024;
    private const MAX_XML = 12 * 1024 * 1024;
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private array $ignorado = [];

    public static function disponible(): bool
    {
        return class_exists(\ZipArchive::class);
    }

    /**
     * @param string $ruta archivo temporal subido; $nombre su nombre original (para la extensión)
     * @return array{bloques:array,avisos:string[]}
     */
    public static function leer(string $ruta, string $nombre): array
    {
        if (!self::disponible()) {
            throw new RuntimeException('Este servidor no puede leer archivos de Word (falta la extensión ZipArchive de PHP). Escribe el texto en el campo.');
        }
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if ($ext === 'docm' || $ext === 'dotm') {
            throw new RuntimeException('Los documentos de Word con macros (.docm) no se aceptan. Guárdalo como «Documento de Word (.docx)».');
        }
        if ($ext !== 'docx') {
            throw new RuntimeException('El archivo debe ser un documento de Word con extensión .docx.');
        }
        $tam = @filesize($ruta);
        if ($tam === false || $tam <= 0) {
            throw new RuntimeException('El archivo está vacío.');
        }
        if ($tam > self::MAX_BYTES) {
            throw new RuntimeException('El archivo de Word pesa más de 2 MB. Quita las imágenes o pega el texto en el campo.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($ruta, \ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('El archivo no es un documento de Word válido (.docx).');
        }
        try {
            $tipos = $zip->getFromName('[Content_Types].xml');
            $stat = $zip->statName('word/document.xml');
            if ($tipos === false || $stat === false) {
                throw new RuntimeException('El archivo no es un documento de Word válido (.docx).');
            }
            if ($zip->locateName('word/vbaProject.bin') !== false || stripos($tipos, 'macroEnabled') !== false || stripos($tipos, 'vbaProject') !== false) {
                throw new RuntimeException('El documento contiene macros y no se acepta. Guárdalo como «Documento de Word (.docx)» sin macros.');
            }
            if ((int) $stat['size'] > self::MAX_XML) {
                throw new RuntimeException('El contenido del documento es demasiado grande.');
            }
            $xml = $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }
        if (!is_string($xml) || $xml === '') {
            throw new RuntimeException('El documento de Word no tiene contenido.');
        }
        return (new self())->procesar($xml);
    }

    private function procesar(string $xml): array
    {
        if (preg_match('/<!DOCTYPE/i', $xml)) {
            throw new RuntimeException('El documento de Word no es válido.');
        }
        $dom = new DOMDocument();
        $previo = libxml_use_internal_errors(true);
        $ok = $dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($previo);
        if (!$ok) {
            throw new RuntimeException('No se pudo leer el contenido del documento de Word.');
        }
        $body = $dom->getElementsByTagNameNS(self::W, 'body')->item(0);
        if (!$body instanceof DOMElement) {
            throw new RuntimeException('El documento de Word no tiene cuerpo.');
        }
        $bloques = [];
        $this->contenedor($body, $bloques);
        // Quita párrafos vacíos al inicio y al final, y colapsa los vacíos repetidos.
        $limpios = [];
        foreach ($bloques as $b) {
            $vacio = $b['t'] === 'p' && trim(implode('', array_column($b['runs'], 0))) === '';
            if ($vacio) {
                continue;
            }
            $limpios[] = $b;
        }
        $avisos = [];
        foreach ($this->ignorado as $que => $n) {
            $avisos[] = $que . ($n > 1 ? ' (' . $n . ')' : '') . '.';
        }
        return ['bloques' => Cuerpo::sanear($limpios), 'avisos' => $avisos];
    }

    private function ignorar(string $que): void
    {
        $this->ignorado[$que] = ($this->ignorado[$que] ?? 0) + 1;
    }

    /** Recorre un contenedor (cuerpo, control de contenido) y agrega párrafos y tablas. */
    private function contenedor(DOMNode $nodo, array &$bloques): void
    {
        foreach ($nodo->childNodes as $h) {
            if (!$h instanceof DOMElement || $h->namespaceURI !== self::W) {
                continue;
            }
            switch ($h->localName) {
                case 'p':
                    $bloques[] = $this->parrafo($h);
                    break;
                case 'tbl':
                    $t = $this->tabla($h);
                    if ($t !== null) {
                        $bloques[] = $t;
                    }
                    break;
                case 'sdt':
                    foreach ($h->childNodes as $c) {
                        if ($c instanceof DOMElement && $c->localName === 'sdtContent') {
                            $this->contenedor($c, $bloques);
                        }
                    }
                    break;
                case 'sectPr':
                    break;
                default:
                    $this->ignorar('Se ignoró contenido que no es texto ni tabla');
            }
        }
    }

    private function hijo(DOMElement $e, string $nombre): ?DOMElement
    {
        foreach ($e->childNodes as $c) {
            if ($c instanceof DOMElement && $c->namespaceURI === self::W && $c->localName === $nombre) {
                return $c;
            }
        }
        return null;
    }

    private function activo(?DOMElement $e): bool
    {
        if ($e === null) {
            return false;
        }
        $v = strtolower($e->getAttributeNS(self::W, 'val'));
        return !in_array($v, ['0', 'false', 'off', 'none'], true);
    }

    private function parrafo(DOMElement $p): array
    {
        $ppr = $this->hijo($p, 'pPr');
        $alinear = 'J';
        $vineta = false;
        if ($ppr) {
            $jc = $this->hijo($ppr, 'jc');
            $alinear = match ($jc ? strtolower($jc->getAttributeNS(self::W, 'val')) : '') {
                'center' => 'C', 'right', 'end' => 'R', 'left', 'start' => 'L', default => 'J',
            };
            $vineta = $this->hijo($ppr, 'numPr') !== null;
        }
        $runs = [];
        $this->runs($p, $runs);
        if ($vineta && $runs) {
            array_unshift($runs, ['• ', false]);
        }
        // Une los fragmentos contiguos con el mismo estilo.
        $unidos = [];
        foreach ($runs as [$t, $b]) {
            $n = count($unidos);
            if ($n > 0 && $unidos[$n - 1][1] === $b) {
                $unidos[$n - 1][0] .= $t;
            } else {
                $unidos[] = [$t, $b];
            }
        }
        return ['t' => 'p', 'alinear' => $alinear, 'runs' => $unidos];
    }

    /** Fragmentos de texto de un párrafo (o de un hipervínculo, inserción o campo dentro de él). */
    private function runs(DOMElement $p, array &$runs): void
    {
        foreach ($p->childNodes as $c) {
            if (!$c instanceof DOMElement || $c->namespaceURI !== self::W) {
                if ($c instanceof DOMElement && in_array($c->localName, ['oMath', 'oMathPara'], true)) {
                    $this->ignorar('Se ignoraron ecuaciones');
                }
                continue;
            }
            switch ($c->localName) {
                case 'r':
                    $this->run($c, $runs);
                    break;
                case 'hyperlink': case 'ins': case 'smartTag': case 'fldSimple': case 'customXml':
                    $this->runs($c, $runs);
                    break;
                case 'sdt':
                    $contenido = $this->hijo($c, 'sdtContent');
                    if ($contenido) {
                        $this->runs($contenido, $runs);
                    }
                    break;
                case 'del': case 'pPr': case 'bookmarkStart': case 'bookmarkEnd': case 'proofErr': case 'commentRangeStart': case 'commentRangeEnd':
                    break;
                default:
                    break;
            }
        }
    }

    private function run(DOMElement $r, array &$runs): void
    {
        $rpr = $this->hijo($r, 'rPr');
        $negrita = $rpr !== null && $this->activo($this->hijo($rpr, 'b'));
        if ($rpr !== null && $this->activo($this->hijo($rpr, 'vanish'))) {
            return;   // texto oculto
        }
        foreach ($r->childNodes as $c) {
            if (!$c instanceof DOMElement) {
                continue;
            }
            switch ($c->localName) {
                case 't':
                    $runs[] = [$c->textContent, $negrita];
                    break;
                case 'tab':
                    $runs[] = [' ', $negrita];
                    break;
                case 'br': case 'cr':
                    if ($c->getAttributeNS(self::W, 'type') === 'page') {
                        $this->ignorar('Se ignoraron saltos de página');
                    } else {
                        $runs[] = ["\n", $negrita];
                    }
                    break;
                case 'noBreakHyphen':
                    $runs[] = ['-', $negrita];
                    break;
                case 'sym':
                    $this->ignorar('Se ignoraron símbolos especiales');
                    break;
                case 'drawing': case 'pict': case 'object':
                    $this->ignorar('Se ignoraron imágenes, gráficos o cuadros de texto');
                    break;
                case 'footnoteReference': case 'endnoteReference':
                    $this->ignorar('Se ignoraron notas al pie');
                    break;
                default:
                    break;
            }
        }
    }

    private function tabla(DOMElement $tbl): ?array
    {
        $filas = [];
        foreach ($tbl->childNodes as $tr) {
            if (!$tr instanceof DOMElement || $tr->localName !== 'tr') {
                continue;
            }
            $fila = [];
            foreach ($tr->childNodes as $tc) {
                if (!$tc instanceof DOMElement || $tc->localName !== 'tc') {
                    continue;
                }
                $span = 1;
                $continua = false;
                $tcpr = $this->hijo($tc, 'tcPr');
                if ($tcpr) {
                    $gs = $this->hijo($tcpr, 'gridSpan');
                    $span = $gs ? max(1, min(Cuerpo::MAX_COLUMNAS, (int) $gs->getAttributeNS(self::W, 'val'))) : 1;
                    $vm = $this->hijo($tcpr, 'vMerge');
                    $continua = $vm !== null && strtolower($vm->getAttributeNS(self::W, 'val')) !== 'restart';
                }
                $textos = [];
                $todoNegrita = true;
                $hayTexto = false;
                foreach ($tc->childNodes as $x) {
                    if (!$x instanceof DOMElement) {
                        continue;
                    }
                    if ($x->localName === 'tbl') {
                        $this->ignorar('Una tabla dentro de otra se pasó como texto');
                        $interna = $this->tabla($x);
                        foreach ($interna['filas'] ?? [] as $f) {
                            $textos[] = implode(' · ', array_column($f, 'texto'));
                        }
                        continue;
                    }
                    if ($x->localName !== 'p') {
                        continue;
                    }
                    $p = $this->parrafo($x);
                    $t = '';
                    foreach ($p['runs'] as [$texto, $b]) {
                        $t .= $texto;
                        if (trim($texto) !== '') {
                            $hayTexto = true;
                            $todoNegrita = $todoNegrita && $b;
                        }
                    }
                    $textos[] = $t;
                }
                if ($span > 1) {
                    $this->ignorar('Se separaron celdas combinadas');
                }
                if ($continua) {
                    $this->ignorar('Se separaron celdas combinadas');
                    $textos = [];
                }
                $fila[] = ['texto' => trim(implode("\n", $textos)), 'negrita' => $hayTexto && $todoNegrita];
                for ($i = 1; $i < $span; $i++) {
                    $fila[] = ['texto' => '', 'negrita' => false];
                }
            }
            if ($fila) {
                if (count($fila) > Cuerpo::MAX_COLUMNAS) {
                    $this->ignorar('Se recortaron columnas de más (máximo ' . Cuerpo::MAX_COLUMNAS . ')');
                }
                $filas[] = array_slice($fila, 0, Cuerpo::MAX_COLUMNAS);
            }
            if (count($filas) >= Cuerpo::MAX_FILAS) {
                $this->ignorar('Se recortaron renglones de más (máximo ' . Cuerpo::MAX_FILAS . ')');
                break;
            }
        }
        return $filas ? Cuerpo::normalizarTabla($filas, false) : null;
    }
}
