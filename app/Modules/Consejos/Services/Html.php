<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Texto enriquecido de los asuntos: lo que se pega desde Word (párrafos, negritas, listas y tablas con celdas
 * combinadas) se reduce a un HTML mínimo y seguro. Solo sobreviven p, ul, ol, li, table, tr, td, th, b, i, u y br; de las
 * celdas solo colspan y rowspan. Estilos, clases, scripts, imágenes y enlaces se descartan, de modo que el resultado
 * puede mostrarse tal cual y convertirse a Word sin sorpresas.
 */
final class Html
{
    private const BLOQUES = ['p', 'div', 'ul', 'ol', 'table', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'center'];
    private const DESCARTAR = ['script', 'style', 'head', 'title', 'meta', 'link', 'xml', 'object', 'iframe', 'svg', 'img', 'canvas', 'noscript', 'template'];
    private const MAX_BYTES = 400000;

    /** Devuelve el HTML limpio (vacío si no queda contenido). */
    public static function limpiar(string $html): string
    {
        $html = mb_substr($html, 0, self::MAX_BYTES);
        $html = preg_replace('/<!--.*?-->/s', '', $html) ?? '';
        $html = preg_replace('/<!\[if[^\]]*\]>.*?<!\[endif\]>/si', '', $html) ?? '';
        if (trim(strip_tags($html)) === '' && stripos($html, '<table') === false) {
            return '';
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $body = $doc->getElementsByTagName('body')->item(0);
        if (!$body) {
            return '';
        }
        $salida = self::bloques($body);
        return trim($salida);
    }

    /** Texto plano (para el buscador y las vistas previas): celdas separadas por espacio, párrafos por salto de línea. */
    public static function texto(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $html = preg_replace('~</(p|li|tr|ul|ol|table)>|<br\s*/?>~i', "\n", $html) ?? $html;
        $html = preg_replace('~</(td|th)>~i', ' ', $html) ?? $html;
        $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('/[ \t\x{00A0}]+/u', ' ', $t) ?? $t;
        $t = preg_replace('/ *\n[ \n]*/', "\n", $t) ?? $t;
        return trim($t);
    }

    /** Convierte texto plano en HTML mínimo (un párrafo por línea). */
    public static function deTexto(string $texto): string
    {
        $lineas = preg_split('/\R/u', trim($texto)) ?: [];
        $p = [];
        foreach ($lineas as $l) {
            if (trim($l) !== '') {
                $p[] = '<p>' . htmlspecialchars(trim($l), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
            }
        }
        return implode('', $p);
    }

    /** Recorre los hijos de un nodo y arma bloques; el texto suelto se agrupa en párrafos. */
    private static function bloques(DOMNode $padre): string
    {
        $out = '';
        $pendiente = '';
        $vaciar = static function () use (&$out, &$pendiente): void {
            if (trim(html_entity_decode(strip_tags($pendiente), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '') {
                $out .= '<p>' . trim($pendiente) . '</p>';
            }
            $pendiente = '';
        };
        foreach (iterator_to_array($padre->childNodes) as $n) {
            if ($n instanceof DOMText) {
                $pendiente .= self::textoNodo($n->nodeValue ?? '');
                continue;
            }
            if (!$n instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($n->tagName);
            if (in_array($tag, self::DESCARTAR, true)) {
                continue;
            }
            if (in_array($tag, self::BLOQUES, true)) {
                $vaciar();
                if ($tag === 'ul' || $tag === 'ol') {
                    $out .= self::lista($n, $tag);
                } elseif ($tag === 'table') {
                    $out .= self::tabla($n);
                } elseif ($tag === 'p' || $tag === 'div' || $tag === 'center' || $tag === 'blockquote' || $tag === 'pre') {
                    // Un div con bloques adentro se aplana; uno solo con texto es un párrafo.
                    if (self::tieneBloques($n)) {
                        $out .= self::bloques($n);
                    } else {
                        $interno = trim(self::inline($n));
                        if (trim(strip_tags(str_replace('<br>', '', $interno))) !== '') {
                            $out .= '<p>' . $interno . '</p>';
                        }
                    }
                } else {   // encabezados h1-h6 → párrafo en negritas
                    $interno = trim(self::inline($n));
                    if (trim(strip_tags($interno)) !== '') {
                        $out .= '<p><b>' . $interno . '</b></p>';
                    }
                }
                continue;
            }
            $pendiente .= self::inlineNodo($n);
        }
        $vaciar();
        return $out;
    }

    private static function tieneBloques(DOMElement $e): bool
    {
        foreach ($e->childNodes as $h) {
            if ($h instanceof DOMElement && (in_array(strtolower($h->tagName), self::BLOQUES, true))) {
                return true;
            }
        }
        return false;
    }

    private static function lista(DOMElement $l, string $tag): string
    {
        $items = '';
        foreach ($l->childNodes as $li) {
            if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                $t = trim(self::inline($li));
                if (trim(strip_tags($t)) !== '') {
                    $items .= '<li>' . $t . '</li>';
                }
            }
        }
        return $items === '' ? '' : "<{$tag}>{$items}</{$tag}>";
    }

    private static function tabla(DOMElement $t): string
    {
        $filas = '';
        $filasNodos = [];
        foreach ($t->getElementsByTagName('tr') as $tr) {
            // Solo las filas de esta tabla, no las de tablas anidadas.
            $p = $tr->parentNode;
            while ($p && $p !== $t && strtolower($p->nodeName) !== 'table') {
                $p = $p->parentNode;
            }
            if ($p === $t) {
                $filasNodos[] = $tr;
            }
        }
        foreach ($filasNodos as $tr) {
            $celdas = '';
            foreach ($tr->childNodes as $c) {
                if (!$c instanceof DOMElement || !in_array(strtolower($c->tagName), ['td', 'th'], true)) {
                    continue;
                }
                $tag = strtolower($c->tagName);
                $attr = '';
                foreach (['colspan', 'rowspan'] as $a) {
                    $v = (int) $c->getAttribute($a);
                    if ($v > 1) {
                        $attr .= ' ' . $a . '="' . min($v, 30) . '"';
                    }
                }
                $dentro = self::bloques($c);
                $celdas .= "<{$tag}{$attr}>{$dentro}</{$tag}>";
            }
            if ($celdas !== '') {
                $filas .= '<tr>' . $celdas . '</tr>';
            }
        }
        return $filas === '' ? '' : '<table><tbody>' . $filas . '</tbody></table>';
    }

    private static function inline(DOMNode $n): string
    {
        $s = '';
        foreach ($n->childNodes as $h) {
            $s .= $h instanceof DOMText ? self::textoNodo($h->nodeValue ?? '') : ($h instanceof DOMElement ? self::inlineNodo($h) : '');
        }
        return $s;
    }

    private static function inlineNodo(DOMElement $n): string
    {
        $tag = strtolower($n->tagName);
        if (in_array($tag, self::DESCARTAR, true)) {
            return '';
        }
        if ($tag === 'br') {
            return '<br>';
        }
        // Dentro de un párrafo, una tabla o lista anidada se aplana a texto.
        if (in_array($tag, self::BLOQUES, true)) {
            return self::inline($n) . '<br>';
        }
        $dentro = self::inline($n);
        if (trim(strip_tags($dentro)) === '') {
            return $dentro;
        }
        // Negrita, cursiva y subrayado, también cuando Word los pone con estilos en línea.
        $estilo = strtolower($n->getAttribute('style'));
        $negrita = in_array($tag, ['b', 'strong'], true) || preg_match('/font-weight:\s*(bold|[6-9]00)/', $estilo);
        $cursiva = in_array($tag, ['i', 'em'], true) || str_contains($estilo, 'font-style:italic') || str_contains($estilo, 'font-style: italic');
        $sub = $tag === 'u' || str_contains($estilo, 'underline');
        if ($sub) {
            $dentro = '<u>' . $dentro . '</u>';
        }
        if ($cursiva) {
            $dentro = '<i>' . $dentro . '</i>';
        }
        if ($negrita) {
            $dentro = '<b>' . $dentro . '</b>';
        }
        return $dentro;
    }

    private static function textoNodo(string $t): string
    {
        $t = str_replace("\u{00A0}", ' ', $t);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;
        return htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
