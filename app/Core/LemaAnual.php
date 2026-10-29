<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Leyenda oficial del año, que va bajo «PATRIA, CIENCIA Y TRABAJO» en todos los oficios que genera la plataforma.
 * El administrador la registra por año en Ajustes → Leyendas oficiales; cada oficio toma la del año en que se emite.
 */
final class LemaAnual
{
    public const CLAVE = 'institucion_lemas_anuales';

    /** Leyendas conocidas cuando aún no se han guardado en Ajustes. */
    public const POR_OMISION = [
        '2026' => "2026, Conmemoración del ingreso de la científica y académica\nElena Cárdenas Guerrero al Instituto Científico y Literario",
    ];

    /** @return array<string,string> año => leyenda (con saltos de línea), del más reciente al más antiguo */
    public static function todas(): array
    {
        $guardado = Database::valor('SELECT valor FROM ajustes WHERE clave = ?', [self::CLAVE]);
        $lemas = $guardado === false || $guardado === null ? self::POR_OMISION : (json_decode((string) $guardado, true) ?: []);
        $lemas = array_filter(array_map(static fn($t): string => trim((string) $t), $lemas), static fn(string $t): bool => $t !== '');
        krsort($lemas);
        return $lemas;
    }

    /** Líneas de la leyenda del año (el actual si no se indica); vacío si no hay leyenda registrada. @return string[] */
    public static function lineas(?int $anio = null): array
    {
        $texto = self::todas()[(string) ($anio ?? (int) date('Y'))] ?? '';
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', $texto) ?: []), static fn(string $l): bool => $l !== ''));
    }

    /** Escribe la leyenda en cursivas, centrada y línea por línea, en la posición actual del PDF. */
    public static function imprimir(\FPDF $pdf, ?int $anio = null, float $alto = 4.5): void
    {
        $lineas = self::lineas($anio);
        if (!$lineas) {
            return;
        }
        $pdf->SetFont('Helvetica', 'I', 8.5);
        foreach ($lineas as $l) {
            $pdf->Cell(0, $alto, iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $l) ?: $l, 0, 1, 'C');
        }
    }

    /**
     * Guarda las leyendas capturadas en Ajustes (año => texto). Admite «\n» escrito a mano como salto de línea.
     * @param array<int|string,string> $anios @param array<int|string,string> $textos
     */
    public static function guardar(array $anios, array $textos): void
    {
        $lemas = [];
        foreach ($anios as $i => $anio) {
            $anio = trim((string) $anio);
            $texto = trim(str_replace(['\\n', "\r\n", "\r"], "\n", mb_scrub((string) ($textos[$i] ?? ''), 'UTF-8')));
            if (preg_match('/^(19|20|21)\d{2}$/D', $anio) && $texto !== '') {
                $lemas[$anio] = mb_substr($texto, 0, 500);
            }
        }
        krsort($lemas);
        $json = json_encode($lemas, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            return;
        }
        Database::ejecutar(
            'INSERT INTO ajustes (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            [self::CLAVE, $json, 'Leyenda oficial de cada año, bajo «Patria, Ciencia y Trabajo» en todos los oficios (JSON año => texto).']
        );
    }
}
