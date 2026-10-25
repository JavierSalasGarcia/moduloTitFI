<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

/**
 * Código QR (ISO/IEC 18004) en modo byte, nivel de corrección M, versiones 1 a 10; suficiente para la URL de
 * verificación de los documentos. Devuelve la matriz de módulos y la dibuja en un PDF de FPDF.
 */
final class Qr
{
    /** versión => [codewords totales, codewords de corrección por bloque, bloques] (nivel M) */
    private const TABLA = [
        1 => [26, 10, 1], 2 => [44, 16, 1], 3 => [70, 26, 1], 4 => [100, 18, 2], 5 => [134, 24, 2],
        6 => [172, 16, 4], 7 => [196, 18, 4], 8 => [242, 22, 4], 9 => [292, 22, 5], 10 => [346, 26, 5],
    ];
    private const ALINEACION = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34],
        7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

    private int $n;
    /** @var array<int,array<int,bool>> [fila][columna] */
    private array $m = [];
    /** @var array<int,array<int,bool>> */
    private array $fijo = [];

    /** @return array<int,array<int,bool>> matriz [fila][columna], true = módulo oscuro */
    public static function matriz(string $texto): array
    {
        $len = strlen($texto);
        foreach (self::TABLA as $v => [$total, $ec, $bloques]) {
            if (4 + 8 + 8 * $len <= ($total - $ec * $bloques) * 8) {
                return (new self())->codificar($texto, $v)->m;
            }
        }
        throw new \InvalidArgumentException('Texto demasiado largo para el código QR.');
    }

    /** Dibuja el código en ($x, $y) con lado $lado (mm), con su margen blanco incluido. */
    public static function dibujar(\FPDF $pdf, string $texto, float $x, float $y, float $lado): void
    {
        $m = self::matriz($texto);
        $n = count($m);
        $paso = $lado / ($n + 4);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->Rect($x, $y, $lado, $lado, 'F');
        $pdf->SetFillColor(0, 0, 0);
        foreach ($m as $f => $fila) {
            $c = 0;
            while ($c < $n) {
                if (!$fila[$c]) {
                    $c++;
                    continue;
                }
                $ini = $c;
                while ($c < $n && $fila[$c]) {
                    $c++;
                }
                $pdf->Rect($x + (2 + $ini) * $paso, $y + (2 + $f) * $paso, ($c - $ini) * $paso + 0.01, $paso + 0.01, 'F');
            }
        }
    }

    private function codificar(string $texto, int $v): self
    {
        [$total, $ec, $bloques] = self::TABLA[$v];
        $capacidad = $total - $ec * $bloques;
        // Flujo de datos: modo byte, longitud (8 bits hasta la versión 9, 16 después), datos, terminador y relleno.
        $bits = '0100' . str_pad(decbin(strlen($texto)), $v < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach (str_split($texto) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $capacidad * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $datos = array_map('bindec', str_split($bits, 8));
        for ($i = 0; count($datos) < $capacidad; $i++) {
            $datos[] = $i % 2 === 0 ? 0xEC : 0x11;
        }
        // Bloques, corrección Reed-Solomon e intercalado.
        $cortos = $bloques - $capacidad % $bloques;
        $largo = intdiv($capacidad, $bloques);
        $divisor = self::divisor($ec);
        $bd = [];
        $be = [];
        $p = 0;
        for ($b = 0; $b < $bloques; $b++) {
            $l = $largo + ($b < $cortos ? 0 : 1);
            $bd[$b] = array_slice($datos, $p, $l);
            $p += $l;
            $be[$b] = self::residuo($bd[$b], $divisor);
        }
        $final = [];
        for ($i = 0; $i <= $largo; $i++) {
            foreach ($bd as $blk) {
                if ($i < count($blk)) {
                    $final[] = $blk[$i];
                }
            }
        }
        for ($i = 0; $i < $ec; $i++) {
            foreach ($be as $blk) {
                $final[] = $blk[$i];
            }
        }

        $this->n = 17 + 4 * $v;
        $this->m = array_fill(0, $this->n, array_fill(0, $this->n, false));
        $this->fijo = $this->m;
        $this->patrones($v);
        $this->colocar($final);
        $mejor = null;
        $base = $this->m;
        $elegida = 0;
        for ($k = 0; $k < 8; $k++) {
            $this->m = $base;
            $this->mascara($k);
            $this->formato($k);
            $pen = $this->penalizacion();
            if ($mejor === null || $pen < $mejor) {
                $mejor = $pen;
                $elegida = $k;
            }
        }
        $this->m = $base;
        $this->mascara($elegida);
        $this->formato($elegida);
        return $this;
    }

    private function poner(int $f, int $c, bool $oscuro): void
    {
        $this->m[$f][$c] = $oscuro;
        $this->fijo[$f][$c] = true;
    }

    private function patrones(int $v): void
    {
        $n = $this->n;
        for ($i = 0; $i < $n; $i++) {
            $this->poner(6, $i, $i % 2 === 0);
            $this->poner($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [3, $n - 4], [$n - 4, 3]] as [$cf, $cc]) {
            for ($df = -4; $df <= 4; $df++) {
                for ($dc = -4; $dc <= 4; $dc++) {
                    $f = $cf + $df;
                    $c = $cc + $dc;
                    if ($f >= 0 && $f < $n && $c >= 0 && $c < $n) {
                        $d = max(abs($df), abs($dc));
                        $this->poner($f, $c, $d !== 2 && $d !== 4);
                    }
                }
            }
        }
        $pos = self::ALINEACION[$v];
        $k = count($pos);
        foreach ($pos as $i => $cf) {
            foreach ($pos as $j => $cc) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $k - 1) || ($i === $k - 1 && $j === 0)) {
                    continue;
                }
                for ($df = -2; $df <= 2; $df++) {
                    for ($dc = -2; $dc <= 2; $dc++) {
                        $this->poner($cf + $df, $cc + $dc, max(abs($df), abs($dc)) !== 1);
                    }
                }
            }
        }
        $this->formato(0);
        if ($v >= 7) {
            $r = $v;
            for ($i = 0; $i < 12; $i++) {
                $r = ($r << 1) ^ (($r >> 11) * 0x1F25);
            }
            $bits = $v << 12 | $r;
            for ($i = 0; $i < 18; $i++) {
                $b = (($bits >> $i) & 1) === 1;
                $a = $n - 11 + $i % 3;
                $c = intdiv($i, 3);
                $this->poner($c, $a, $b);
                $this->poner($a, $c, $b);
            }
        }
    }

    /** Información de formato (nivel M y máscara) en sus dos copias, y el módulo oscuro fijo. */
    private function formato(int $mascara): void
    {
        $dato = (0 << 3) | $mascara;
        $r = $dato;
        for ($i = 0; $i < 10; $i++) {
            $r = ($r << 1) ^ (($r >> 9) * 0x537);
        }
        $bits = ($dato << 10 | $r) ^ 0x5412;
        $b = static fn(int $i): bool => (($bits >> $i) & 1) === 1;
        $n = $this->n;
        for ($i = 0; $i <= 5; $i++) {
            $this->poner($i, 8, $b($i));
        }
        $this->poner(7, 8, $b(6));
        $this->poner(8, 8, $b(7));
        $this->poner(8, 7, $b(8));
        for ($i = 9; $i < 15; $i++) {
            $this->poner(8, 14 - $i, $b($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $this->poner(8, $n - 1 - $i, $b($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $this->poner($n - 15 + $i, 8, $b($i));
        }
        $this->poner($n - 8, 8, true);
    }

    /** @param int[] $codewords */
    private function colocar(array $codewords): void
    {
        $n = $this->n;
        $total = count($codewords) * 8;
        $i = 0;
        for ($der = $n - 1; $der >= 1; $der -= 2) {
            if ($der === 6) {
                $der = 5;
            }
            $sube = (($der + 1) & 2) === 0;
            for ($k = 0; $k < $n; $k++) {
                $f = $sube ? $n - 1 - $k : $k;
                for ($j = 0; $j < 2; $j++) {
                    $c = $der - $j;
                    if (!$this->fijo[$f][$c] && $i < $total) {
                        $this->m[$f][$c] = (($codewords[$i >> 3] >> (7 - ($i & 7))) & 1) === 1;
                        $i++;
                    }
                }
            }
        }
    }

    private function mascara(int $k): void
    {
        for ($f = 0; $f < $this->n; $f++) {
            for ($c = 0; $c < $this->n; $c++) {
                if ($this->fijo[$f][$c]) {
                    continue;
                }
                $inv = match ($k) {
                    0 => ($c + $f) % 2 === 0, 1 => $f % 2 === 0, 2 => $c % 3 === 0, 3 => ($c + $f) % 3 === 0,
                    4 => (intdiv($c, 3) + intdiv($f, 2)) % 2 === 0, 5 => $c * $f % 2 + $c * $f % 3 === 0,
                    6 => ($c * $f % 2 + $c * $f % 3) % 2 === 0, default => (($c + $f) % 2 + $c * $f % 3) % 2 === 0,
                };
                if ($inv) {
                    $this->m[$f][$c] = !$this->m[$f][$c];
                }
            }
        }
    }

    /** Penalización de la norma (reglas 1, 2, 3 y 4) para elegir la máscara. */
    private function penalizacion(): int
    {
        $n = $this->n;
        $p = 0;
        $oscuros = 0;
        $lineas = [];
        for ($i = 0; $i < $n; $i++) {
            $fila = '';
            $col = '';
            for ($j = 0; $j < $n; $j++) {
                $fila .= $this->m[$i][$j] ? '1' : '0';
                $col .= $this->m[$j][$i] ? '1' : '0';
            }
            $lineas[] = $fila;
            $lineas[] = $col;
            $oscuros += substr_count($fila, '1');
        }
        foreach ($lineas as $l) {
            preg_match_all('/0{5,}|1{5,}/', $l, $r);
            foreach ($r[0] as $s) {
                $p += strlen($s) - 2;
            }
            $p += 40 * (preg_match_all('/(?=10111010000|00001011101)/', $l));
        }
        for ($f = 0; $f < $n - 1; $f++) {
            for ($c = 0; $c < $n - 1; $c++) {
                $v = $this->m[$f][$c];
                if ($v === $this->m[$f][$c + 1] && $v === $this->m[$f + 1][$c] && $v === $this->m[$f + 1][$c + 1]) {
                    $p += 3;
                }
            }
        }
        return $p + intdiv(abs($oscuros * 20 - $n * $n * 10), $n * $n) * 10;
    }

    private static function mul(int $x, int $y): int
    {
        $z = 0;
        for ($i = 7; $i >= 0; $i--) {
            $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0xFF;
            if ((($y >> $i) & 1) === 1) {
                $z ^= $x;
            }
        }
        return $z;
    }

    /** @return int[] */
    private static function divisor(int $grado): array
    {
        $r = array_fill(0, $grado, 0);
        $r[$grado - 1] = 1;
        $raiz = 1;
        for ($i = 0; $i < $grado; $i++) {
            for ($j = 0; $j < $grado; $j++) {
                $r[$j] = self::mul($r[$j], $raiz);
                if ($j + 1 < $grado) {
                    $r[$j] ^= $r[$j + 1];
                }
            }
            $raiz = self::mul($raiz, 0x02);
        }
        return $r;
    }

    /** @param int[] $datos @param int[] $divisor @return int[] */
    private static function residuo(array $datos, array $divisor): array
    {
        $r = array_fill(0, count($divisor), 0);
        foreach ($datos as $b) {
            $f = $b ^ array_shift($r);
            $r[] = 0;
            foreach ($divisor as $i => $d) {
                $r[$i] ^= self::mul($d, $f);
            }
        }
        return $r;
    }
}
