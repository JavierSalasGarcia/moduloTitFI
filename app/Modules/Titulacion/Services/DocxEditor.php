<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use DOMDocument;
use RuntimeException;
use ZipArchive;

/**
 * Toma una hoja membretada .docx y sustituye el contenido de su cuerpo (conserva
 * encabezado, pie y márgenes, que viven en la propiedad de sección).
 */
final class DocxEditor
{
    public const NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private DOMDocument $dom;

    public function __construct(private string $plantilla)
    {
        if (!is_file($plantilla)) {
            throw new RuntimeException('Falta la plantilla ' . basename($plantilla));
        }
        $zip = new ZipArchive();
        if ($zip->open($plantilla) !== true) {
            throw new RuntimeException('No se pudo abrir la plantilla Word.');
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        $this->dom = new DOMDocument();
        if ($xml === false || !$this->dom->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('document.xml no es válido.');
        }
    }

    /** Reemplaza todo el cuerpo salvo la sección final por el fragmento WordprocessingML dado. */
    public function reemplazarCuerpo(string $fragmentoXml): void
    {
        $cuerpo = $this->dom->getElementsByTagNameNS(self::NS, 'body')->item(0);
        if ($cuerpo === null) {
            throw new RuntimeException('La plantilla no tiene cuerpo.');
        }
        $seccion = null;
        foreach (iterator_to_array($cuerpo->childNodes) as $nodo) {
            if ($nodo->localName === 'sectPr') {
                $seccion = $nodo;
            } else {
                $cuerpo->removeChild($nodo);
            }
        }
        $aux = new DOMDocument();
        if (!$aux->loadXML('<w:raiz xmlns:w="' . self::NS . '">' . $fragmentoXml . '</w:raiz>', LIBXML_NONET)) {
            throw new RuntimeException('No se pudo armar el contenido del documento.');
        }
        foreach ($aux->documentElement->childNodes as $hijo) {
            $cuerpo->insertBefore($this->dom->importNode($hijo, true), $seccion);
        }
    }

    public function guardar(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tit');
        if ($tmp === false || !copy($this->plantilla, $tmp)) {
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
}
