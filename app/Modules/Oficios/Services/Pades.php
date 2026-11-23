<?php
declare(strict_types=1);

namespace App\Modules\Oficios\Services;

use RuntimeException;

/**
 * Prepara un PDF (el que genera FPDF/FPDI de la plataforma) para recibir una firma CMS desprendida (/adbe.pkcs7.detached) mediante una
 * actualización incremental: agrega el campo de firma invisible, deja reservado el espacio de la firma y calcula el /ByteRange. El navegador
 * firma los bytes fuera de la reserva (`contenido`) y el servidor inserta la firma (`incrustar`). Solo admite PDF con tabla de referencias clásica.
 */
final class Pades
{
    public const RESERVA = 8192;   // bytes reservados para la firma (16 384 caracteres hexadecimales)

    /** @return array{pdf:string,inicio:int,largo:int} inicio = posición del «<» de /Contents; largo = bytes de «<…>» */
    public static function preparar(string $pdf, string $nombre, string $razon, int $momento): array
    {
        if (!str_starts_with($pdf, '%PDF-')) {
            throw new RuntimeException('El archivo no es un PDF.');
        }
        if (str_contains($pdf, '/ByteRange')) {
            throw new RuntimeException('El PDF ya tiene una firma.');
        }
        if (!preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $m)) {
            throw new RuntimeException('No se encontró el final del PDF.');
        }
        $prev = (int) $m[1];
        $posTrailer = strrpos($pdf, 'trailer');
        if ($posTrailer === false) {
            throw new RuntimeException('Este PDF usa un formato de referencias que no se puede firmar.');
        }
        $trailer = substr($pdf, $posTrailer, (int) strpos($pdf, 'startxref', $posTrailer) - $posTrailer);
        if (!preg_match('~/Size\s+(\d+)~', $trailer, $ms) || !preg_match('~/Root\s+(\d+)\s+0\s+R~', $trailer, $mr)) {
            throw new RuntimeException('El PDF no tiene catálogo.');
        }
        $info = preg_match('~/Info\s+(\d+)\s+0\s+R~', $trailer, $mi) ? ' /Info ' . $mi[1] . ' 0 R' : '';
        $size = (int) $ms[1];
        $root = (int) $mr[1];

        $catalogo = self::objeto($pdf, $root);
        if (str_contains($catalogo, '/AcroForm')) {
            throw new RuntimeException('El PDF ya tiene formularios.');
        }
        if (!preg_match('~/Pages\s+(\d+)\s+0\s+R~', $catalogo, $mp)) {
            throw new RuntimeException('El PDF no tiene páginas.');
        }
        $paginas = self::objeto($pdf, (int) $mp[1]);
        if (!preg_match('~/Kids\s*\[\s*(\d+)\s+0\s+R~', $paginas, $mk)) {
            throw new RuntimeException('El PDF no tiene páginas.');
        }
        $pagNum = (int) $mk[1];
        $pagina = self::objeto($pdf, $pagNum);

        $sigNum = $size;
        $widNum = $size + 1;
        // Catálogo con el formulario de firmas; página con la anotación.
        $catalogoNuevo = rtrim(substr($catalogo, 0, -2)) . "\n/AcroForm << /Fields [" . $widNum . " 0 R] /SigFlags 3 >>\n>>";
        if (preg_match('~/Annots\s*\[([^\]]*)\]~', $pagina)) {
            $paginaNueva = preg_replace('~/Annots\s*\[([^\]]*)\]~', '/Annots [$1 ' . $widNum . ' 0 R]', $pagina, 1) ?? $pagina;
        } elseif (str_contains($pagina, '/Annots')) {
            throw new RuntimeException('La página ya tiene anotaciones que no se pueden ampliar.');
        } else {
            $paginaNueva = rtrim(substr($pagina, 0, -2)) . "\n/Annots [" . $widNum . " 0 R]\n>>";
        }
        $fecha = gmdate("YmdHis", $momento);
        $firma = $sigNum . " 0 obj\n<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached\n/ByteRange [0 0000000000 0000000000 0000000000]\n"
            . '/Contents <' . str_repeat('0', self::RESERVA * 2) . ">\n/M (D:" . $fecha . "+00'00')\n/Name " . self::cadena($nombre) . "\n/Reason " . self::cadena($razon)
            . "\n/Location " . self::cadena('Toluca, Estado de México') . "\n>>\nendobj\n";
        $widget = $widNum . ' 0 obj' . "\n<< /Type /Annot /Subtype /Widget /FT /Sig /T (Firma1) /Rect [0 0 0 0] /V " . $sigNum . ' 0 R /F 132 /P ' . $pagNum . " 0 R >>\nendobj\n";

        $base = strlen($pdf) + (str_ends_with($pdf, "\n") ? 0 : 1);
        $nuevo = $root . " 0 obj\n" . $catalogoNuevo . "\nendobj\n";
        $pBase = strlen($nuevo);
        $nuevo .= $pagNum . " 0 obj\n" . $paginaNueva . "\nendobj\n";
        $sBase = strlen($nuevo);
        $nuevo .= $firma;
        $wBase = strlen($nuevo);
        $nuevo .= $widget;

        $xrefPos = $base + strlen($nuevo);
        $f = static fn(int $o): string => sprintf("%010d 00000 n \n", $base + $o);
        $xref = "xref\n" . $root . " 1\n" . $f(0) . $pagNum . " 1\n" . $f($pBase) . $sigNum . " 2\n" . $f($sBase) . $f($wBase)
            . "trailer\n<< /Size " . ($size + 2) . ' /Root ' . $root . ' 0 R' . $info . ' /Prev ' . $prev . " >>\nstartxref\n" . $xrefPos . "\n%%EOF\n";

        $final = $pdf . (str_ends_with($pdf, "\n") ? '' : "\n") . $nuevo . $xref;
        $inicio = (int) strpos($final, '/Contents <', $base) + 10;
        $largo = self::RESERVA * 2 + 2;
        $rango = sprintf('[0 %010d %010d %010d]', $inicio, $inicio + $largo, strlen($final) - $inicio - $largo);
        $final = substr_replace($final, $rango, (int) strpos($final, '[0 0000000000 0000000000 0000000000]', $base), strlen($rango));
        return ['pdf' => $final, 'inicio' => $inicio, 'largo' => $largo];
    }

    /** Bytes que se firman: todo el PDF menos el espacio de la firma. */
    public static function contenido(string $pdf): string
    {
        [$a, $b, $c, $d] = self::rango($pdf);
        return substr($pdf, $a, $b) . substr($pdf, $c, $d);
    }

    /** @return array{0:int,1:int,2:int,3:int} */
    public static function rango(string $pdf): array
    {
        if (!preg_match_all('~/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]~', $pdf, $m, PREG_SET_ORDER) || !$m) {
            throw new RuntimeException('El PDF no tiene firma preparada.');
        }
        $u = end($m);
        return [(int) $u[1], (int) $u[2], (int) $u[3], (int) $u[4]];
    }

    /** Inserta la firma (hexadecimal) en el espacio reservado. */
    public static function incrustar(string $pdf, string $hexCms): string
    {
        [, $b, $c] = self::rango($pdf);
        $inicio = $b;                                   // posición del «<»
        $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $hexCms) ?? '');
        if ($hex === '' || strlen($hex) > self::RESERVA * 2) {
            throw new RuntimeException('La firma no cabe en el espacio reservado o está vacía.');
        }
        if (($pdf[$inicio] ?? '') !== '<' || ($pdf[$c - 1] ?? '') !== '>') {
            throw new RuntimeException('El PDF preparado no es válido.');
        }
        return substr($pdf, 0, $inicio + 1) . str_pad($hex, self::RESERVA * 2, '0') . substr($pdf, $c - 1);
    }

    /** Firma incrustada (DER) de un PDF firmado. */
    public static function firmaDe(string $pdf): string
    {
        [, $b, $c] = self::rango($pdf);
        $hex = rtrim(substr($pdf, $b + 1, $c - $b - 2));
        $bin = hex2bin(strlen($hex) % 2 === 0 ? $hex : $hex . '0');
        if ($bin === false) {
            throw new RuntimeException('La firma incrustada no es válida.');
        }
        return $bin;
    }

    private static function objeto(string $pdf, int $n): string
    {
        if (!preg_match_all('~(?:^|[\r\n])' . $n . '\s+0\s+obj\s*(<<.*?>>)\s*endobj~s', $pdf, $m) || !$m[1]) {
            throw new RuntimeException('No se encontró el objeto ' . $n . ' del PDF.');
        }
        return trim((string) end($m[1]));
    }

    /** Cadena PDF en UTF-16BE hexadecimal (admite acentos). */
    private static function cadena(string $s): string
    {
        return '<FEFF' . strtoupper(bin2hex((string) mb_convert_encoding($s, 'UTF-16BE', 'UTF-8'))) . '>';
    }
}
