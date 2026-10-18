<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Edición mínima de un .docx (word/document.xml) sin dependencias externas.
 * Solo ofrece las operaciones que necesitan los formatos de titulación:
 * sustituir líneas subrayadas, marcar casillas, completar celdas y reemplazar
 * el texto guía de una sección por la respuesta del pasante.
 */
final class DocxEditor
{
    public const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private DOMDocument $dom;
    private DOMXPath $xp;
    private string $archivo;

    public function __construct(string $plantilla)
    {
        if (!is_file($plantilla)) {
            throw new RuntimeException('Falta la plantilla ' . basename($plantilla));
        }
        $this->archivo = $plantilla;
        $zip = new ZipArchive();
        if ($zip->open($plantilla) !== true) {
            throw new RuntimeException('No se pudo abrir la plantilla Word.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) {
            throw new RuntimeException('La plantilla Word no contiene document.xml.');
        }
        $this->dom = new DOMDocument();
        $this->dom->preserveWhiteSpace = true;
        if (!$this->dom->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('document.xml no es válido.');
        }
        $this->xp = new DOMXPath($this->dom);
        $this->xp->registerNamespace('w', self::NS);
    }

    public function xp(): DOMXPath
    {
        return $this->xp;
    }

    /** Devuelve el .docx modificado como cadena binaria. */
    public function guardar(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tit');
        if ($tmp === false || !copy($this->archivo, $tmp)) {
            throw new RuntimeException('No se pudo preparar el archivo temporal.');
        }
        $zip = new ZipArchive();
        if ($zip->open($tmp) !== true) {
            @unlink($tmp);
            throw new RuntimeException('No se pudo reabrir el documento.');
        }
        $zip->addFromString('word/document.xml', $this->dom->saveXML());
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    /** @return DOMElement[] */
    public function parrafos(?DOMNode $ctx = null, bool $soloDirectos = false): array
    {
        $consulta = $soloDirectos ? './w:p' : './/w:p';
        $lista = $this->xp->query($consulta, $ctx ?? $this->dom->documentElement);
        return $lista ? iterator_to_array($lista) : [];
    }

    public function texto(DOMNode $p): string
    {
        $t = '';
        foreach ($this->xp->query('.//w:t', $p) as $nodo) {
            $t .= $nodo->textContent;
        }
        return $t;
    }

    public static function normalizar(string $texto): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto) ?? '');
        $texto = rtrim($texto, ': ');
        return mb_strtolower($texto);
    }

    /**
     * Reemplaza cada coincidencia de $regex en el texto del párrafo (aunque Word
     * la haya partido en varios runs). $valores se consume en orden; si hay menos
     * valores que coincidencias, las demás quedan intactas.
     *
     * @param string[] $valores
     */
    public function reemplazar(DOMElement $p, string $regex, array $valores): void
    {
        $nodos = iterator_to_array($this->xp->query('.//w:t', $p));
        if (!$nodos) {
            return;
        }
        $completo = '';
        $rangos = [];
        foreach ($nodos as $i => $n) {
            $ini = strlen($completo);
            $completo .= $n->textContent;
            $rangos[$i] = [$ini, strlen($completo)];
        }
        if (!preg_match_all($regex, $completo, $m, PREG_OFFSET_CAPTURE)) {
            return;
        }
        $coinc = $m[0];
        $valores = array_values($valores);
        for ($k = count($coinc) - 1; $k >= 0; $k--) {
            if (!array_key_exists($k, $valores)) {
                continue;
            }
            [$txt, $off] = $coinc[$k];
            $fin = $off + strlen($txt);
            $primero = true;
            foreach ($nodos as $i => $n) {
                [$ns, $ne] = $rangos[$i];
                if ($ne <= $off || $ns >= $fin) {
                    continue;
                }
                $actual = $n->textContent;
                $a = max($off - $ns, 0);
                $b = min($fin - $ns, $ne - $ns);
                $nuevo = substr($actual, 0, $a) . ($primero ? (string) $valores[$k] : '') . substr($actual, $b);
                $n->nodeValue = '';
                $n->appendChild($this->dom->createTextNode($nuevo));
                $n->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
                $primero = false;
            }
        }
    }

    /** Añade texto al final del párrafo reutilizando el formato del último run. */
    public function agregarTexto(DOMElement $p, string $texto, bool $normal = true): void
    {
        $runs = $this->xp->query('./w:r', $p);
        $rPr = null;
        if ($runs->length > 0) {
            $origen = $this->xp->query('./w:rPr', $runs->item($runs->length - 1))->item(0);
            $rPr = $origen ? $origen->cloneNode(true) : null;
        } else {
            $origen = $this->xp->query('./w:pPr/w:rPr', $p)->item(0);
            $rPr = $origen ? $origen->cloneNode(true) : null;
        }
        if ($rPr && $normal) {
            $this->quitarEnfasis($rPr);
        }
        $p->appendChild($this->crearRun($texto, $rPr));
    }

    /**
     * Quita negritas, cursivas y color, y los apaga de forma explícita porque el
     * estilo del párrafo (p. ej. «Texto independiente») puede ser negrita.
     */
    private function quitarEnfasis(DOMElement $rPr): void
    {
        foreach (['b', 'bCs', 'i', 'iCs', 'color', 'u', 'highlight'] as $nombre) {
            foreach (iterator_to_array($this->xp->query('./w:' . $nombre, $rPr)) as $x) {
                $rPr->removeChild($x);
            }
        }
        $ancla = $this->xp->query('./w:rFonts', $rPr)->item(0);
        $despues = $ancla ? $ancla->nextSibling : $rPr->firstChild;
        foreach (['b', 'bCs', 'i', 'iCs'] as $nombre) {
            $apagado = $this->dom->createElementNS(self::NS, 'w:' . $nombre);
            $apagado->setAttributeNS(self::NS, 'w:val', '0');
            $rPr->insertBefore($apagado, $despues);
        }
    }

    private function crearRun(string $texto, ?DOMElement $rPr): DOMElement
    {
        $run = $this->dom->createElementNS(self::NS, 'w:r');
        if ($rPr) {
            $run->appendChild($rPr);
        }
        $partes = preg_split('/\t/', $texto) ?: [$texto];
        foreach ($partes as $i => $parte) {
            if ($i > 0) {
                $run->appendChild($this->dom->createElementNS(self::NS, 'w:tab'));
            }
            $t = $this->dom->createElementNS(self::NS, 'w:t');
            $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
            $t->appendChild($this->dom->createTextNode($parte));
            $run->appendChild($t);
        }
        return $run;
    }

    /** Escribe $texto en un párrafo vacío o lo reemplaza conservando el primer run. */
    public function fijarTexto(DOMElement $p, string $texto): void
    {
        $runs = iterator_to_array($this->xp->query('./w:r', $p));
        $rPr = null;
        if ($runs) {
            $origen = $this->xp->query('./w:rPr', $runs[0])->item(0);
            $rPr = $origen ? $origen->cloneNode(true) : null;
        } else {
            $origen = $this->xp->query('./w:pPr/w:rPr', $p)->item(0);
            $rPr = $origen ? $origen->cloneNode(true) : null;
        }
        foreach ($runs as $r) {
            $p->removeChild($r);
        }
        $p->appendChild($this->crearRun($texto, $rPr));
    }

    /** Crea párrafos de respuesta después de $ref con el formato de $ref sin énfasis. */
    public function insertarRespuesta(DOMElement $ref, string $texto): void
    {
        $lineas = preg_split('/\R/u', trim($texto)) ?: [];
        $ancla = $ref;
        $rPrBase = $this->xp->query('.//w:r/w:rPr', $ref)->item(0);
        foreach ($lineas as $linea) {
            $nuevo = $this->dom->createElementNS(self::NS, 'w:p');
            $pPr = $this->xp->query('./w:pPr', $ref)->item(0);
            if ($pPr) {
                $copia = $pPr->cloneNode(true);
                foreach (['keepNext', 'numPr', 'pageBreakBefore'] as $n) {
                    foreach (iterator_to_array($this->xp->query('./w:' . $n, $copia)) as $x) {
                        $copia->removeChild($x);
                    }
                }
                $nuevo->appendChild($copia);
            }
            $rPr = $rPrBase ? $rPrBase->cloneNode(true) : null;
            if ($rPr) {
                $this->quitarEnfasis($rPr);
            }
            $nuevo->appendChild($this->crearRun(trim($linea), $rPr));
            $ancla->parentNode->insertBefore($nuevo, $ancla->nextSibling);
            $ancla = $nuevo;
        }
    }

    public function eliminar(DOMNode $nodo): void
    {
        $nodo->parentNode?->removeChild($nodo);
    }
}
