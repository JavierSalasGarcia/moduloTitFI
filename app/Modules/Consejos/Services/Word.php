<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;

/**
 * Genera el acta de cada Consejo como documento de Word (.docx) sin dependencias externas: arma el XML de WordprocessingML
 * y lo empaqueta con ZipArchive. El formato imita el acta de ejemplo (DM Sans 9 pt, una tabla por asunto con clave,
 * solicitud, antecedentes y acuerdos). Las tablas pegadas desde Word se reproducen como tablas reales, con celdas combinadas.
 */
final class Word
{
    private const NS = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';
    private const ANCHO = 8838;   // carta con márgenes de 3 cm

    public static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // ------------------------------------------------------------------ párrafos y tablas de HTML limpio

    private static function run(string $texto, int $sz, bool $b, bool $i, bool $u): string
    {
        $rpr = '<w:rFonts w:ascii="DM Sans" w:hAnsi="DM Sans" w:cs="DM Sans"/>' . ($b ? '<w:b/><w:bCs/>' : '') . ($i ? '<w:i/><w:iCs/>' : '')
            . '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>' . ($u ? '<w:u w:val="single"/>' : '');
        $partes = [];
        foreach (explode("\t", $texto) as $k => $p) {
            $partes[] = ($k > 0 ? '<w:tab/>' : '') . ($p === '' ? '' : '<w:t xml:space="preserve">' . self::esc($p) . '</w:t>');
        }
        return '<w:r><w:rPr>' . $rpr . '</w:rPr>' . implode('', $partes) . '</w:r>';
    }

    /** Párrafo con texto simple. @param array{jc?:string,b?:bool,i?:bool,sz?:int,after?:int,before?:int,left?:int,hanging?:int,keep?:bool} $o */
    public static function parrafo(string $texto, array $o = []): string
    {
        $sz = $o['sz'] ?? 18;
        $lineas = explode("\n", $texto);
        $runs = '';
        foreach ($lineas as $k => $l) {
            if ($k > 0) {
                $runs .= '<w:r><w:br/></w:r>';
            }
            $runs .= self::run($l, $sz, !empty($o['b']), !empty($o['i']), false);
        }
        return self::parrafoRuns($runs, $o);
    }

    public static function parrafoRuns(string $runs, array $o = []): string
    {
        $ppr = (!empty($o['keep']) ? '<w:keepNext/>' : '')
            . '<w:spacing w:before="' . ($o['before'] ?? 0) . '" w:after="' . ($o['after'] ?? 60) . '" w:line="240" w:lineRule="auto"/>'
            . (isset($o['left']) ? '<w:ind w:left="' . $o['left'] . '"' . (isset($o['hanging']) ? ' w:hanging="' . $o['hanging'] . '"' : '') . '/>' : '')
            . '<w:jc w:val="' . ($o['jc'] ?? 'both') . '"/>';
        return '<w:p><w:pPr>' . $ppr . '</w:pPr>' . $runs . '</w:p>';
    }

    /** Párrafo con una etiqueta en negritas y después texto normal («Asunto: …»). */
    public static function etiquetado(string $etiqueta, string $texto, array $o = []): string
    {
        $sz = $o['sz'] ?? 18;
        return self::parrafoRuns(self::run($etiqueta . ' ', $sz, true, false, false) . self::run($texto, $sz, false, false, false), $o);
    }

    /** Convierte HTML limpio (Html::limpiar) en párrafos y tablas de Word. */
    public static function htmlABloques(string $html, int $sz = 18, bool $negrita = false, int $ancho = self::ANCHO): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }
        $doc = new DOMDocument();
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $body = $doc->getElementsByTagName('body')->item(0);
        return $body ? self::bloquesDe($body, $sz, $negrita, $ancho) : '';
    }

    private static function bloquesDe(DOMNode $padre, int $sz, bool $negrita, int $ancho): string
    {
        $xml = '';
        foreach ($padre->childNodes as $n) {
            if ($n instanceof DOMText) {
                if (trim($n->nodeValue ?? '') !== '') {
                    $xml .= self::parrafoRuns(self::run(trim($n->nodeValue), $sz, $negrita, false, false), ['sz' => $sz]);
                }
                continue;
            }
            if (!$n instanceof DOMElement) {
                continue;
            }
            switch (strtolower($n->tagName)) {
                case 'p':
                    $xml .= self::parrafoRuns(self::inline($n, $sz, $negrita, false, false), ['sz' => $sz]);
                    break;
                case 'ul':
                case 'ol':
                    $k = 0;
                    foreach ($n->childNodes as $li) {
                        if ($li instanceof DOMElement && strtolower($li->tagName) === 'li') {
                            $k++;
                            $marca = strtolower($n->tagName) === 'ol' ? $k . '.' : '•';
                            $xml .= self::parrafoRuns(self::run($marca . "\t", $sz, false, false, false) . self::inline($li, $sz, $negrita, false, false),
                                ['sz' => $sz, 'left' => 425, 'hanging' => 283, 'after' => 20]);
                        }
                    }
                    break;
                case 'table':
                    $xml .= self::tabla($n, $sz, $negrita, $ancho);
                    break;
                default:
                    $xml .= self::bloquesDe($n, $sz, $negrita, $ancho);
            }
        }
        return $xml;
    }

    private static function inline(DOMNode $n, int $sz, bool $b, bool $i, bool $u): string
    {
        $xml = '';
        foreach ($n->childNodes as $h) {
            if ($h instanceof DOMText) {
                if (($h->nodeValue ?? '') !== '') {
                    $xml .= self::run($h->nodeValue, $sz, $b, $i, $u);
                }
            } elseif ($h instanceof DOMElement) {
                switch (strtolower($h->tagName)) {
                    case 'br':
                        $xml .= '<w:r><w:br/></w:r>';
                        break;
                    case 'b':
                        $xml .= self::inline($h, $sz, true, $i, $u);
                        break;
                    case 'i':
                        $xml .= self::inline($h, $sz, $b, true, $u);
                        break;
                    case 'u':
                        $xml .= self::inline($h, $sz, $b, $i, true);
                        break;
                    default:
                        $xml .= self::inline($h, $sz, $b, $i, $u);
                }
            }
        }
        return $xml;
    }

    /** Tabla de Word a partir de una tabla HTML, con celdas combinadas en columnas (gridSpan) y filas (vMerge). */
    private static function tabla(DOMElement $t, int $sz, bool $negrita, int $ancho): string
    {
        $filas = [];
        foreach ($t->getElementsByTagName('tr') as $tr) {
            $p = $tr->parentNode;
            while ($p && $p !== $t && strtolower($p->nodeName) !== 'table') {
                $p = $p->parentNode;
            }
            if ($p === $t) {
                $filas[] = $tr;
            }
        }
        // Posición de cada celda en la rejilla.
        $ocupado = [];
        $celdas = [];   // [fila => [col => ['dom'=>, 'cs'=>, 'rs'=>, 'cont'=>bool]]]
        $ncols = 0;
        foreach ($filas as $r => $tr) {
            $col = 0;
            foreach ($tr->childNodes as $c) {
                if (!$c instanceof DOMElement || !in_array(strtolower($c->tagName), ['td', 'th'], true)) {
                    continue;
                }
                while (!empty($ocupado[$r][$col])) {
                    $col++;
                }
                $cs = max(1, min(30, (int) $c->getAttribute('colspan')));
                $rs = max(1, min(60, (int) $c->getAttribute('rowspan')));
                $celdas[$r][$col] = ['dom' => $c, 'cs' => $cs, 'rs' => $rs, 'cont' => false, 'th' => strtolower($c->tagName) === 'th'];
                for ($rr = $r; $rr < $r + $rs; $rr++) {
                    for ($cc = $col; $cc < $col + $cs; $cc++) {
                        $ocupado[$rr][$cc] = true;
                    }
                    if ($rr > $r) {
                        $celdas[$rr][$col] = ['dom' => null, 'cs' => $cs, 'rs' => 1, 'cont' => true, 'th' => false];
                    }
                }
                $col += $cs;
                $ncols = max($ncols, $col);
            }
        }
        if (!$celdas || $ncols === 0) {
            return '';
        }
        $colw = intdiv($ancho, $ncols);
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . ($colw * $ncols) . '" w:type="dxa"/><w:tblBorders>';
        foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $l) {
            $xml .= '<w:' . $l . ' w:val="single" w:sz="4" w:space="0" w:color="000000"/>';
        }
        $xml .= '</w:tblBorders><w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="70" w:type="dxa"/><w:right w:w="70" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';
        $xml .= str_repeat('<w:gridCol w:w="' . $colw . '"/>', $ncols) . '</w:tblGrid>';
        foreach (array_keys($filas) as $r) {
            $fila = $celdas[$r] ?? [];
            ksort($fila);
            $xml .= '<w:tr><w:trPr><w:cantSplit/>' . ($r === 0 ? '<w:tblHeader/>' : '') . '</w:trPr>';
            $col = 0;
            foreach ($fila as $c0 => $c) {
                if ($c0 > $col) {   // hueco: celda vacía para que la fila cierre
                    $xml .= self::celdaVacia(($c0 - $col) * $colw, $c0 - $col);
                }
                $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . ($colw * $c['cs']) . '" w:type="dxa"/>' . ($c['cs'] > 1 ? '<w:gridSpan w:val="' . $c['cs'] . '"/>' : '')
                    . ($c['cont'] ? '<w:vMerge/>' : ($c['rs'] > 1 ? '<w:vMerge w:val="restart"/>' : '')) . '</w:tcPr>';
                $interior = $c['dom'] ? self::bloquesDe($c['dom'], $sz, $negrita || $c['th'], max(1200, $colw * $c['cs'] - 160)) : '';
                if ($interior === '' || str_ends_with($interior, '</w:tbl>')) {
                    $interior .= '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr></w:p>';
                }
                $xml .= $interior . '</w:tc>';
                $col = $c0 + $c['cs'];
            }
            if ($col < $ncols) {
                $xml .= self::celdaVacia(($ncols - $col) * $colw, $ncols - $col);
            }
            $xml .= '</w:tr>';
        }
        return $xml . '</w:tbl>' . '<w:p><w:pPr><w:spacing w:before="0" w:after="0" w:line="120" w:lineRule="exact"/></w:pPr></w:p>';
    }

    private static function celdaVacia(int $w, int $span): string
    {
        return '<w:tc><w:tcPr><w:tcW w:w="' . $w . '" w:type="dxa"/>' . ($span > 1 ? '<w:gridSpan w:val="' . $span . '"/>' : '')
            . '</w:tcPr><w:p><w:pPr><w:spacing w:before="0" w:after="0"/></w:pPr></w:p></w:tc>';
    }

    // ------------------------------------------------------------------ empaquetado

    /** Arma el .docx (cadena binaria) con el XML del cuerpo. */
    public static function documento(string $cuerpo, array $comentarios = []): string
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Este servidor no puede crear archivos de Word (falta la extensión ZipArchive de PHP).');
        }
        $doc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document ' . self::NS . '><w:body>' . $cuerpo
            . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1417" w:right="1701" w:bottom="1417" w:left="1701" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>';
        $estilos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles ' . self::NS . '><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="DM Sans" w:hAnsi="DM Sans" w:cs="DM Sans" w:eastAsia="DM Sans"/>'
            . '<w:sz w:val="18"/><w:szCs w:val="18"/><w:lang w:val="es-MX"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="60" w:line="240" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style></w:styles>';
        $tipos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . ($comentarios ? '<Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>' : '') . '</Types>';
        $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
        $relsDoc = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . ($comentarios ? '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>' : '') . '</Relationships>';
        $tmp = tempnam(sys_get_temp_dir(), 'cns');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible crear el archivo de Word.');
        }
        $zip->addFromString('[Content_Types].xml', $tipos);
        $zip->addFromString('_rels/.rels', $rels);
        $zip->addFromString('word/document.xml', $doc);
        $zip->addFromString('word/styles.xml', $estilos);
        $zip->addFromString('word/_rels/document.xml.rels', $relsDoc);
        if ($comentarios) {
            $c = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:comments ' . self::NS . '>';
            foreach ($comentarios as $id => $k) {
                $iniciales = mb_strtoupper(implode('', array_map(static fn($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/u', trim($k['autor'])) ?: [], 0, 3))));
                $c .= '<w:comment w:id="' . $id . '" w:author="' . self::esc($k['autor']) . '" w:date="' . date('Y-m-d\TH:i:s\Z') . '" w:initials="' . self::esc($iniciales) . '">';
                foreach (preg_split('/\R/u', $k['texto']) ?: [] as $linea) {
                    $c .= '<w:p>' . self::run($linea, 20, false, false, false) . '</w:p>';
                }
                $c .= '</w:comment>';
            }
            $zip->addFromString('word/comments.xml', $c . '</w:comments>');
        }
        $zip->close();
        $bin = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    // ------------------------------------------------------------------ actas

    /**
     * Acta de un Consejo en Word.
     * @param array $r reunión; $consejo 'academico' o 'gobierno'; $asuntos filas de consejos_asuntos ordenadas por grupo y número
     */
    public static function acta(array $r, string $consejo, array $asuntos): string
    {
        self::$comentarios = [];
        $gob = $consejo === 'gobierno';
        $nombre = $gob ? 'H. CONSEJO DE GOBIERNO' : 'H. CONSEJO ACADÉMICO';
        $fecha = (string) ($r[$consejo . '_fecha'] ?? '');
        $hora = substr((string) ($r[$consejo . '_hora'] ?? ''), 0, 5);
        $lugar = Texto::mayus((string) ($r[$consejo . '_lugar'] ?? ''));
        // La hora guardada es la de la segunda convocatoria (la primera es media hora antes); la extraordinaria tiene una sola.
        $extra = ($r['tipo_sesion'] ?? '') === 'extraordinaria';
        $horaTxt = $hora === '' ? '[HORA]' : $hora;
        $cuandoHora = $extra || $hora === ''
            ? 'A LAS ' . $horaTxt . ' HORAS EN ' . ($extra ? 'ÚNICA' : 'SEGUNDA') . ' CONVOCATORIA'
            : 'A LAS ' . date('H:i', strtotime($hora) - 1800) . ' HORAS EN PRIMERA CONVOCATORIA Y A LAS ' . $hora . ' HORAS EN SEGUNDA CONVOCATORIA';
        $t = $fecha !== '' ? strtotime($fecha) : false;
        $cuando = $t === false ? '[FECHA]' : mb_strtoupper(Texto::diaSemana($fecha)) . ' ' . (int) date('j', $t) . ' DE ' . mb_strtoupper(Texto::MESES[(int) date('n', $t) - 1]) . ' DE ' . date('Y', $t);
        $titulo = 'ACTA DE LA SESIÓN ' . mb_strtoupper((string) $r['tipo_sesion']) . ' DEL ' . $nombre . ' DE LA FACULTAD DE INGENIERÍA, CELEBRADA EL ' . $cuando
            . ($lugar !== '' ? ', EN ' . $lugar . ', DE ESTA FACULTAD' : '') . ', ' . $cuandoHora . ', BAJO EL SIGUIENTE ORDEN DEL DÍA:';
        $x = self::parrafo($titulo, ['b' => true, 'after' => 120]);
        foreach (['Lista de presentes', 'Lectura y aprobación, en su caso, del acta de la sesión anterior', 'Casos profesores', 'Casos de alumnos', 'Asuntos generales'] as $k => $o) {
            $x .= self::parrafo(($k + 1) . '.' . "\t" . $o, ['left' => 425, 'hanging' => 425, 'after' => 0]);
        }
        $x .= self::parrafo('', ['after' => 120]);
        $x .= self::parrafo('LISTA DE PRESENTES', ['b' => true, 'jc' => 'left', 'before' => 120]);
        $x .= self::parrafo('PRESIDENTE:' . "\t" . Texto::mayus((string) $r['presidente']), ['jc' => 'left', 'after' => 0]);
        $x .= self::parrafo('SECRETARIO:' . "\t" . Texto::mayus((string) $r['secretario']), ['jc' => 'left', 'after' => 0]);
        $cons = Texto::lineas($r['consejeros_' . $consejo] ?? '');
        foreach ($cons as $k => $c) {
            $x .= self::parrafo(($k === 0 ? 'CONSEJEROS:' : '') . "\t" . Texto::mayus($c), ['jc' => 'left', 'after' => 0]);
        }
        $x .= self::parrafo('', ['after' => 120]);
        $x .= self::parrafo('LECTURA Y APROBACIÓN, EN SU CASO, DEL ACTA DE LA SESIÓN ANTERIOR', ['b' => true, 'jc' => 'left', 'before' => 120]);
        foreach (Texto::lineas($r['actas_anteriores_' . $consejo] ?? '') as $a) {
            $x .= self::etiquetado('Asunto:', 'Se adjuntó el acta de la sesión ' . rtrim($a, '. ') . ' y se preguntó si existían observaciones.', ['after' => 20]);
            $x .= self::etiquetado('Acuerdo:', 'Se aprueba el acta.', ['after' => 100]);
        }
        $titulos = ['profesor' => 'CASOS DE PROFESORES', 'alumno' => 'CASOS DE ALUMNOS', 'general' => 'ASUNTOS GENERALES'];
        foreach ($titulos as $g => $enc) {
            $x .= self::parrafo($enc, ['b' => true, 'jc' => 'left', 'before' => 200, 'after' => 100, 'keep' => true]);
            foreach ($asuntos as $a) {
                if ($a['grupo'] === $g) {
                    $x .= self::tablaAsunto($a, $gob);
                }
            }
        }
        $fin = (string) ($r[$consejo . '_fin'] ?? '');
        $x .= self::parrafo('Sin otro asunto que tratar, se dio por terminada la sesión siendo las ' . ($fin !== '' ? date('H:i', strtotime($fin)) : '[HORA]') . ' horas de la fecha indicada.', ['before' => 240, 'after' => 600]);
        $x .= self::firmas($r, $nombre);
        return self::documento($x, self::$comentarios);
    }

    /** Comentarios de Word pendientes de escribir: [id => ['autor'=>, 'texto'=>]]. */
    private static array $comentarios = [];

    private static function tablaAsunto(array $a, bool $gob): string
    {
        $clave = Texto::clave($a['grupo'], (int) $a['numero']);
        $borde = '<w:tcBorders>' . implode('', array_map(static fn($l) => '<w:' . $l . ' w:val="single" w:sz="4" w:space="0" w:color="000000"/>', ['top', 'left', 'bottom', 'right'])) . '</w:tcBorders>';
        $celda = static fn(int $w, string $contenido, int $span = 1, string $extra = '') =>
            '<w:tc><w:tcPr><w:tcW w:w="' . $w . '" w:type="dxa"/>' . ($span > 1 ? '<w:gridSpan w:val="' . $span . '"/>' : '') . $borde . $extra . '</w:tcPr>' . $contenido . '</w:tc>';
        $fila = static fn(string $celdas) => '<w:tr><w:trPr><w:cantSplit/></w:trPr>' . $celdas . '</w:tr>';
        $c1 = 573;
        $c2 = self::ANCHO - $c1;
        $titulo = static fn(string $t) => self::parrafo($t, ['b' => true, 'jc' => 'center', 'keep' => true]);
        $acuerdo = static fn(string $t) => $t === '' ? self::parrafo('') : self::parrafo($t);
        $acAcad = trim((string) $a['acuerdo_academico']);
        $acGob = trim((string) $a['acuerdo_gobierno']);
        if ($gob && trim((string) $a['nota_gobierno']) !== '') {
            $acGob = rtrim($acGob, ' ') . ($acGob !== '' ? ' ' : '') . trim((string) $a['nota_gobierno']);
        }
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . self::ANCHO . '" w:type="dxa"/><w:tblBorders>'
            . implode('', array_map(static fn($l) => '<w:' . $l . ' w:val="single" w:sz="4" w:space="0" w:color="000000"/>', ['top', 'left', 'bottom', 'right', 'insideH', 'insideV']))
            . '</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid><w:gridCol w:w="' . $c1 . '"/><w:gridCol w:w="' . $c2 . '"/></w:tblGrid>';
        $obs = trim((string) ($a['observacion'] ?? ''));
        if ($obs !== '') {
            $cid = count(self::$comentarios);
            self::$comentarios[$cid] = ['autor' => trim((string) ($a['observacion_autor'] ?? '')) ?: 'Subdirección Académica', 'texto' => $obs];
            $claveXml = self::parrafoRuns('<w:commentRangeStart w:id="' . $cid . '"/>' . self::run($clave, 18, true, false, false) . '<w:commentRangeEnd w:id="' . $cid . '"/><w:r><w:commentReference w:id="' . $cid . '"/></w:r>', ['jc' => 'center']);
        } else {
            $claveXml = self::parrafo($clave, ['b' => true, 'jc' => 'center']);
        }
        $xml .= $fila($celda($c1, $claveXml, 1, '<w:vAlign w:val="center"/>')
            . $celda($c2, self::htmlABloques((string) $a['solicitud_html']) ?: self::parrafo(''), 1, '<w:vAlign w:val="center"/>'));
        $xml .= $fila($celda(self::ANCHO, $titulo('Documentos y/o Antecedentes'), 2));
        $xml .= $fila($celda(self::ANCHO, self::htmlABloques((string) $a['antecedentes_html']) ?: self::parrafo(''), 2));
        if (($a['origen'] ?? 'academico') !== 'gobierno') {
            $xml .= $fila($celda(self::ANCHO, $titulo('Acuerdo del H. Consejo Académico'), 2));
            $xml .= $fila($celda(self::ANCHO, $acuerdo($acAcad), 2));
        }
        if ($gob) {
            $xml .= $fila($celda(self::ANCHO, $titulo('Acuerdo del H. Consejo de Gobierno'), 2));
            $xml .= $fila($celda(self::ANCHO, $acuerdo($acGob), 2));
        }
        return $xml . '</w:tbl>' . self::parrafo('', ['after' => 120]);
    }

    private static function firmas(array $r, string $consejo): string
    {
        $mitad = intdiv(self::ANCHO, 2);
        $sinBorde = '<w:tcBorders><w:top w:val="nil"/><w:left w:val="nil"/><w:bottom w:val="nil"/><w:right w:val="nil"/></w:tcBorders>';
        $bloque = static fn(string $nombre, string $cargo) => '<w:tc><w:tcPr><w:tcW w:w="' . $mitad . '" w:type="dxa"/>' . $sinBorde . '</w:tcPr>'
            . self::parrafo('', ['after' => 600]) . self::parrafo(Texto::mayus($nombre), ['b' => true, 'jc' => 'center', 'after' => 0])
            . self::parrafo($cargo . ' DE LA FACULTAD DE INGENIERÍA DE LA UAEMéx', ['b' => true, 'jc' => 'center', 'after' => 0]) . '</w:tc>';
        return '<w:tbl><w:tblPr><w:tblW w:w="' . self::ANCHO . '" w:type="dxa"/><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid><w:gridCol w:w="' . $mitad . '"/><w:gridCol w:w="' . $mitad . '"/></w:tblGrid>'
            . '<w:tr><w:trPr><w:cantSplit/></w:trPr>'
            . $bloque((string) $r['presidente'], 'PRESIDENTE DEL ' . $consejo)
            . $bloque((string) $r['secretario'], 'SECRETARIO DEL ' . $consejo) . '</w:tr></w:tbl>' . self::parrafo('');
    }
}
