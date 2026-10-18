<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Modules\Titulacion\Catalogo;
use DOMElement;
use RuntimeException;

/** Llena el Word oficial de solicitud y protocolo con lo capturado por el pasante. */
final class ProtocoloDocx
{
    private const CARRERA_ETIQUETA = [
        'civil' => 'civil',
        'mecanica' => 'mecánica',
        'computacion' => 'en computación',
        'electronica' => 'electrónica',
        'sistemas' => 'en sistemas energéticos sustentables',
    ];

    public static function fechaLarga(string $iso): string
    {
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
        if (!$f) {
            return $iso;
        }
        return (int) $f->format('j') . ' de ' . Catalogo::MESES[(int) $f->format('n')] . ' de ' . $f->format('Y');
    }

    /**
     * @param array $d [
     *   'dia','mes','anio','nombre','cuenta','carrera','tema',
     *   'etiquetas' => ['Etiqueta:' => 'valor'],
     *   'secciones' => [campo definido en Catalogo::protocolo() + 'valor'],
     * ]
     */
    public static function generar(string $modalidad, array $d): string
    {
        $proto = Catalogo::protocolo($modalidad);
        if ($proto === null) {
            throw new RuntimeException('Modalidad sin protocolo.');
        }
        $ed = new DocxEditor(dirname(__DIR__) . '/plantillas/docx/' . $proto['plantilla']);
        $xp = $ed->xp();

        $encabezados = [];
        foreach ($proto['campos'] as $c) {
            if (isset($c['h'])) {
                $encabezados[DocxEditor::normalizar($c['h'])] = true;
            }
        }
        $etiquetas = [];
        foreach ($d['etiquetas'] ?? [] as $etiqueta => $valor) {
            if ((string) $valor !== '') {
                $etiquetas[DocxEditor::normalizar((string) $etiqueta)] = (string) $valor;
            }
        }
        $carreraTxt = self::CARRERA_ETIQUETA[$d['carrera'] ?? ''] ?? null;
        $esperaTema = false;

        foreach ($ed->parrafos() as $p) {
            $texto = $ed->texto($p);
            $norm = DocxEditor::normalizar($texto);
            $enTabla = $xp->query('ancestor::w:tc', $p)->length > 0;

            if (!$enTabla && !empty($d['subdirector']) && preg_match('/DR\.\s+JAVIER\s+SALAS\s+GARC[ÍI]A/u', $texto)) {
                $ed->reemplazar($p, '/DR\.\s+JAVIER\s+SALAS\s+GARC[ÍI]A/u', [mb_strtoupper((string) $d['subdirector'])]);
                continue;
            }

            if (!$enTabla && str_starts_with($norm, 'toluca, méxico;') && str_contains($texto, '__')) {
                $ed->reemplazar($p, '/_{2,}/u', [(string) $d['dia'], (string) $d['mes'], (string) $d['anio']]);
            } elseif (!$enTabla && str_starts_with($norm, 'quien suscribe')) {
                $ed->reemplazar($p, '/_{3,}/u', [(string) $d['nombre'], (string) $d['cuenta']]);
            } elseif (!$enTabla && str_contains($norm, 'somete a su consideración el tema')) {
                $esperaTema = true;
            } elseif (!$enTabla && $esperaTema && preg_match('/^_{10,}\s*$/u', trim($texto))) {
                $ed->reemplazar($p, '/_{10,}/u', [(string) ($d['tema'] ?? '')]);
                $esperaTema = false;
            } elseif ($enTabla && $carreraTxt !== null && preg_match('/^(.*?)\s*\(\s*\)\s*$/u', trim($texto), $m)
                && DocxEditor::normalizar($m[1]) === $carreraTxt) {
                $ed->reemplazar($p, '/\(\s*\)/u', ['(X)']);
            } elseif ($enTabla && isset($etiquetas[$norm])) {
                $ed->agregarTexto($p, ' ' . $etiquetas[$norm]);
            }
        }

        foreach ($d['secciones'] ?? [] as $campo) {
            if (!isset($campo['h']) || ($campo['tipo'] ?? '') === 'cronograma') {
                continue;
            }
            $valor = trim((string) ($campo['valor'] ?? ''));
            if ($valor === '') {
                $valor = (string) ($campo['vacio'] ?? '');
            }
            if ($valor === '') {
                continue;
            }
            self::reemplazarSeccion($ed, $campo['h'], $valor, $encabezados);
        }

        foreach ($d['secciones'] ?? [] as $campo) {
            if (($campo['tipo'] ?? '') === 'cronograma' && is_array($campo['valor'] ?? null)) {
                self::llenarCronograma($ed, $campo['valor']);
            }
        }
        return $ed->guardar();
    }

    private static function reemplazarSeccion(DocxEditor $ed, string $encabezado, string $valor, array $encabezados): void
    {
        $xp = $ed->xp();
        $buscado = DocxEditor::normalizar($encabezado);
        foreach ($xp->query('//w:tc') as $tc) {
            $parrafos = $ed->parrafos($tc, true);
            foreach ($parrafos as $i => $p) {
                if (DocxEditor::normalizar($ed->texto($p)) !== $buscado) {
                    continue;
                }
                // Límite de la sección: siguiente encabezado conocido dentro de la misma celda.
                for ($j = $i + 1; $j < count($parrafos); $j++) {
                    $q = $parrafos[$j];
                    $t = $ed->texto($q);
                    if (isset($encabezados[DocxEditor::normalizar($t)])) {
                        break;
                    }
                    if (trim($t) !== '' && !str_starts_with(trim($t), 'NOTA')) {
                        $ed->eliminar($q);
                    }
                }
                $ed->insertarRespuesta($p, $valor);
                return;
            }
        }
    }

    /** @param array<int,array{a:string,b:array<int,int>}> $filas */
    private static function llenarCronograma(DocxEditor $ed, array $filas): void
    {
        $xp = $ed->xp();
        foreach ($xp->query('//w:tbl') as $tbl) {
            $trs = iterator_to_array($xp->query('./w:tr', $tbl));
            if (count($trs) < 3) {
                continue;
            }
            $cabecera = DocxEditor::normalizar($ed->texto($trs[0]));
            if (!str_contains($cabecera, 'actividades') || !str_contains($cabecera, 'bimestre')) {
                continue;
            }
            $libres = [];
            for ($i = 2; $i < count($trs); $i++) {
                if (str_contains($ed->texto($trs[$i]), 'Vo. Bo.')) {
                    break;
                }
                $libres[] = $trs[$i];
            }
            foreach (array_values($filas) as $n => $fila) {
                if (!isset($libres[$n])) {
                    break;
                }
                $tcs = iterator_to_array($xp->query('./w:tc', $libres[$n]));
                if (count($tcs) < 2) {
                    continue;
                }
                $p0 = $xp->query('./w:p', $tcs[0])->item(0);
                if ($p0 instanceof DOMElement) {
                    $ed->fijarTexto($p0, mb_substr((string) $fila['a'], 0, 120));
                }
                foreach ((array) ($fila['b'] ?? []) as $b) {
                    $b = (int) $b;
                    if ($b >= 1 && $b <= 12 && isset($tcs[$b])) {
                        $pb = $xp->query('./w:p', $tcs[$b])->item(0);
                        if ($pb instanceof DOMElement) {
                            $ed->fijarTexto($pb, 'X');
                        }
                    }
                }
            }
            return;
        }
    }
}
