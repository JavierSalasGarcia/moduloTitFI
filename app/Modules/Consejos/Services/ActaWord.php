<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use RuntimeException;

/**
 * Lee un acta de los Consejos en Word (.docx) y devuelve sus datos y casos: encabezado (Consejo, sesión, fecha, hora, lugar,
 * convocatoria), mesa, consejeros, actas anteriores y, por cada tabla, la clave (P#, A#, AG#), la solicitud, los antecedentes
 * (con sus tablas, celdas combinadas incluidas) y los acuerdos de cada Consejo. Sin dependencias: ZipArchive y DOM.
 * Rechaza lo que no sea un .docx real y los documentos con macros.
 */
final class ActaWord
{
    public const MAX_BYTES = 8 * 1024 * 1024;
    private const MAX_XML = 25 * 1024 * 1024;
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /**
     * @return array{consejo:string,sesion:string,fecha:?string,hora:?string,lugar:string,convocatoria:string,presidente:string,secretario:string,consejeros:string[],actas_anteriores:string[],fin:?string,casos:array<int,array>}
     */
    public static function leer(string $ruta, string $nombre): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new RuntimeException('Este servidor no puede leer archivos de Word (falta la extensión ZipArchive de PHP).');
        }
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if ($ext !== 'docx') {
            throw new RuntimeException('El archivo debe ser un documento de Word con extensión .docx (los .docm con macros no se aceptan).');
        }
        $tam = @filesize($ruta);
        if (!$tam || $tam > self::MAX_BYTES) {
            throw new RuntimeException('El archivo está vacío o pesa más de ' . (int) (self::MAX_BYTES / 1048576) . ' MB.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($ruta, \ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('El archivo no es un documento de Word válido (.docx).');
        }
        try {
            $stat = $zip->statName('word/document.xml');
            if ($stat === false || $zip->getFromName('[Content_Types].xml') === false || $stat['size'] > self::MAX_XML) {
                throw new RuntimeException('El archivo no es un documento de Word válido (.docx).');
            }
            if ($zip->locateName('word/vbaProject.bin') !== false) {
                throw new RuntimeException('Los documentos con macros no se aceptan. Guárdalo como «Documento de Word (.docx)».');
            }
            $xml = (string) $zip->getFromName('word/document.xml');
        } finally {
            $zip->close();
        }
        $doc = new DOMDocument();
        if (!@$doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('No se pudo leer el contenido del documento.');
        }
        $body = $doc->getElementsByTagNameNS(self::W, 'body')->item(0);
        if (!$body instanceof DOMElement) {
            throw new RuntimeException('El documento está vacío.');
        }
        return self::analizar($body);
    }

    // ------------------------------------------------------------------ encabezado y casos

    private static function analizar(DOMElement $body): array
    {
        $parrafos = [];
        $tablas = [];
        foreach ($body->childNodes as $n) {
            if (!$n instanceof DOMElement) {
                continue;
            }
            if ($n->localName === 'p') {
                $t = trim(self::texto($n));
                if ($t !== '') {
                    $parrafos[] = $t;
                }
            } elseif ($n->localName === 'tbl') {
                $tablas[] = $n;
            }
        }
        $titulo = '';
        foreach ($parrafos as $p) {
            if (stripos($p, 'ACTA DE LA SESI') === 0) {
                $titulo = $p;
                break;
            }
        }
        if ($titulo === '') {
            throw new RuntimeException('No encontré el encabezado «ACTA DE LA SESIÓN…». ¿Es el acta de un Consejo?');
        }
        $t = Texto::norm($titulo);
        $r = ['consejo' => str_contains($t, 'consejo de gobierno') ? 'gobierno' : (str_contains($t, 'consejo academico') ? 'academico' : ''),
            'sesion' => str_contains($t, 'extraordinaria') ? 'extraordinaria' : 'ordinaria', 'fecha' => null, 'hora' => null, 'lugar' => '', 'convocatoria' => 'segunda',
            'presidente' => '', 'secretario' => '', 'consejeros' => [], 'actas_anteriores' => [], 'fin' => null, 'casos' => []];
        if ($r['consejo'] === '') {
            throw new RuntimeException('No pude saber si es el acta del Consejo Académico o del de Gobierno.');
        }
        if (preg_match('/(\d{1,2})\s+de\s+([a-z]+)\s+de\s+(\d{4})/', $t, $m) && ($k = array_search($m[2], Texto::MESES, true)) !== false) {
            $r['fecha'] = sprintf('%04d-%02d-%02d', (int) $m[3], $k + 1, (int) $m[1]);
        }
        if (preg_match('/a las (\d{1,2}):(\d{2}) horas/', $t, $m)) {
            $r['hora'] = sprintf('%02d:%s', (int) $m[1], $m[2]);
        }
        if (preg_match('/ en (.+?), de esta facultad/iu', $titulo, $m)) {
            $r['lugar'] = Texto::limpio($m[1]);
        }
        $r['convocatoria'] = str_contains($t, 'primera convocatoria') ? 'primera' : (str_contains($t, 'unica convocatoria') ? 'unica' : 'segunda');
        foreach ($parrafos as $i => $p) {
            if (str_starts_with($p, 'PRESIDENTE:')) {
                $r['presidente'] = trim(substr($p, 11));
            } elseif (str_starts_with($p, 'SECRETARIO:')) {
                $r['secretario'] = trim(substr($p, 11));
            } elseif (str_starts_with($p, 'CONSEJEROS:')) {
                $r['consejeros'][] = trim(substr($p, 11));
                foreach (array_slice($parrafos, $i + 1) as $q) {
                    if (stripos($q, 'LECTURA') === 0) {
                        break;
                    }
                    $r['consejeros'][] = $q;
                }
            } elseif (preg_match('/^Asunto:\s*Se adjunt[oó] el acta de la sesi[oó]n (.+?) y se pregunt/iu', $p, $m)) {
                $r['actas_anteriores'][] = trim($m[1]);
            } elseif (stripos($p, 'Sin otro asunto que tratar') === 0 && preg_match('/siendo las (\d{1,2}):(\d{2})/', $p, $m)) {
                $r['fin'] = sprintf('%02d:%s', (int) $m[1], $m[2]);
            }
        }
        $r['consejeros'] = array_values(array_filter(array_map('trim', $r['consejeros'])));
        foreach ($tablas as $tbl) {
            $c = self::caso($tbl);
            if ($c) {
                $r['casos'][] = $c;
            }
        }
        if (!$r['casos']) {
            throw new RuntimeException('El acta no trae casos (tablas con clave P1, A1, AG1…).');
        }
        return $r;
    }

    /** @return ?array{grupo:string,numero:int,solicitud_html:string,antecedentes_html:string,acuerdo_academico:string,acuerdo_gobierno:string} */
    private static function caso(DOMElement $tbl): ?array
    {
        $filas = self::hijos($tbl, 'tr');
        if (!$filas) {
            return null;
        }
        $tcs0 = self::hijos($filas[0], 'tc');
        if (count($tcs0) < 2 || !preg_match('/^(AG|P|A)(\d+)$/', preg_replace('/\s+/', '', self::textoCelda($tcs0[0])) ?? '', $m)) {
            return null;
        }
        $c = ['grupo' => ['P' => 'profesor', 'A' => 'alumno', 'AG' => 'general'][$m[1]], 'numero' => (int) $m[2], 'solicitud_html' => self::contenido($tcs0[1]),
            'antecedentes_html' => '', 'acuerdo_academico' => '', 'acuerdo_gobierno' => ''];
        $pend = null;
        foreach (array_slice($filas, 1) as $tr) {
            $tcs = self::hijos($tr, 'tc');
            if (!$tcs) {
                continue;
            }
            $txt = trim(implode(' ', array_map([self::class, 'textoCelda'], $tcs)));
            $n = Texto::norm($txt);
            $campo = null;
            if (str_starts_with($n, 'documentos y/o antecedentes')) {
                $campo = 'antecedentes_html';
            } elseif (str_starts_with($n, 'acuerdo del h. consejo academico')) {
                $campo = 'acuerdo_academico';
            } elseif (str_starts_with($n, 'acuerdo del h. consejo de gobierno')) {
                $campo = 'acuerdo_gobierno';
            }
            if ($campo) {
                $pend = $campo;
                continue;
            }
            if ($pend) {
                $c[$pend] = $pend === 'antecedentes_html' ? self::contenido($tcs[0]) : $txt;
                $pend = null;
            }
        }
        return $c;
    }

    // ------------------------------------------------------------------ texto y HTML mínimo

    /** @return DOMElement[] hijos directos con ese nombre local */
    private static function hijos(DOMNode $n, string $local): array
    {
        $r = [];
        foreach ($n->childNodes as $h) {
            if ($h instanceof DOMElement && $h->localName === $local && $h->namespaceURI === self::W) {
                $r[] = $h;
            }
        }
        return $r;
    }

    private static function texto(DOMElement $p): string
    {
        $s = '';
        foreach ($p->getElementsByTagNameNS(self::W, 't') as $t) {
            $s .= $t->textContent;
        }
        return $s;
    }

    /** Texto de los párrafos directos de una celda, uno por línea. */
    private static function textoCelda(DOMElement $tc): string
    {
        return trim(implode("\n", array_map([self::class, 'texto'], self::hijos($tc, 'p'))));
    }

    private static function activo(?DOMElement $rpr, string $local): bool
    {
        if (!$rpr) {
            return false;
        }
        foreach ($rpr->childNodes as $e) {
            if ($e instanceof DOMElement && $e->localName === $local) {
                $v = $e->getAttributeNS(self::W, 'val');
                return !in_array($v, ['0', 'false', 'off', 'none'], true);
            }
        }
        return false;
    }

    private static function runs(DOMElement $p): string
    {
        $out = '';
        foreach ($p->getElementsByTagNameNS(self::W, 'r') as $run) {
            $t = '';
            foreach ($run->childNodes as $h) {
                if (!$h instanceof DOMElement) {
                    continue;
                }
                if ($h->localName === 't') {
                    $t .= $h->textContent;
                } elseif ($h->localName === 'tab') {
                    $t .= ' ';
                } elseif ($h->localName === 'br') {
                    $t .= "\n";
                }
            }
            if ($t === '') {
                continue;
            }
            $rpr = null;
            foreach ($run->childNodes as $h) {
                if ($h instanceof DOMElement && $h->localName === 'rPr') {
                    $rpr = $h;
                }
            }
            $s = str_replace("\n", '<br>', htmlspecialchars($t, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
            if (self::activo($rpr, 'u')) {
                $s = '<u>' . $s . '</u>';
            }
            if (self::activo($rpr, 'i')) {
                $s = '<i>' . $s . '</i>';
            }
            if (self::activo($rpr, 'b')) {
                $s = '<b>' . $s . '</b>';
            }
            $out .= $s;
        }
        return $out;
    }

    /** Contenido de una celda como HTML mínimo: párrafos y tablas anidadas. */
    private static function contenido(DOMElement $tc): string
    {
        $out = '';
        foreach ($tc->childNodes as $h) {
            if (!$h instanceof DOMElement) {
                continue;
            }
            if ($h->localName === 'p') {
                $x = preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', self::runs($h)) ?? '';
                if (preg_replace('/[\s\x{00A0}]+/u', '', strip_tags($x)) !== '') {
                    $out .= '<p>' . $x . '</p>';
                }
            } elseif ($h->localName === 'tbl') {
                $out .= self::tabla($h);
            }
        }
        return $out;
    }

    private static function tabla(DOMElement $tbl): string
    {
        $filas = [];
        foreach (self::hijos($tbl, 'tr') as $tr) {
            $col = 0;
            $f = [];
            foreach (self::hijos($tr, 'tc') as $tc) {
                $cs = 1;
                $vm = null;
                foreach (self::hijos($tc, 'tcPr') as $pr) {
                    foreach ($pr->childNodes as $e) {
                        if ($e instanceof DOMElement && $e->localName === 'gridSpan') {
                            $cs = max(1, (int) $e->getAttributeNS(self::W, 'val'));
                        } elseif ($e instanceof DOMElement && $e->localName === 'vMerge') {
                            $vm = $e->getAttributeNS(self::W, 'val') ?: 'cont';
                        }
                    }
                }
                $f[] = ['col' => $col, 'cs' => $cs, 'vm' => $vm, 'tc' => $tc];
                $col += $cs;
            }
            $filas[] = $f;
        }
        $out = '<table><tbody>';
        foreach ($filas as $i => $f) {
            $out .= '<tr>';
            foreach ($f as $c) {
                if ($c['vm'] === 'cont') {
                    continue;
                }
                $rs = 1;
                if ($c['vm'] === 'restart') {
                    for ($j = $i + 1; $j < count($filas); $j++) {
                        $sigue = false;
                        foreach ($filas[$j] as $d) {
                            if ($d['col'] === $c['col'] && $d['vm'] === 'cont') {
                                $sigue = true;
                            }
                        }
                        if (!$sigue) {
                            break;
                        }
                        $rs++;
                    }
                }
                $at = ($c['cs'] > 1 ? ' colspan="' . min($c['cs'], 30) . '"' : '') . ($rs > 1 ? ' rowspan="' . min($rs, 60) . '"' : '');
                $out .= '<td' . $at . '>' . self::contenido($c['tc']) . '</td>';
            }
            $out .= '</tr>';
        }
        return $out . '</tbody></table>';
    }
}
