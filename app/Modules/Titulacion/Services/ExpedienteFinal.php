<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;

/**
 * Verificación final del expediente de titulación y su envío a Servicios Escolares (Administración Central).
 *
 * Al concluir la evaluación profesional, el departamento recorre la lista de verificación oficial («Formato de
 * integración de expediente», una por opción de titulación), confirma lo que se entrega en físico y verifica el
 * expediente. Los expedientes verificados se exportan en una carpeta comprimida (ZIP) con los documentos en el orden
 * del formato, el propio formato llenado y un índice con la huella SHA-256 de cada archivo.
 */
final class ExpedienteFinal
{
    /** Documentos de la plataforma que cubren cada renglón (clave de documento del catálogo). */
    private static function d(string $k, string $texto, array|string $docs, bool $cond = false): array
    {
        return ['k' => $k, 'texto' => $texto, 'docs' => (array) $docs, 'cond' => $cond];
    }

    /** Renglón que el responsable confirma contra el expediente físico. */
    private static function m(string $k, string $texto, bool $cond = false): array
    {
        return ['k' => $k, 'texto' => $texto, 'docs' => [], 'cond' => $cond];
    }

    /** Renglón que genera la plataforma al integrar el expediente. */
    private static function g(string $k, string $texto, string $generado): array
    {
        return ['k' => $k, 'texto' => $texto, 'docs' => [], 'cond' => false, 'generado' => $generado];
    }

    /**
     * Renglones del formato de integración de expediente de la opción (mismo orden que el formato oficial).
     * @return list<array{k:string,texto:string,docs:list<string>,cond:bool,generado?:string}>
     */
    public static function renglones(string $modalidad): array
    {
        $fotos = self::m('fotos', 'Dos fotografías tamaño semitítulo, blanco y negro, papel mate, fondo gris claro, de frente, con retoque, varones con camisa blanca, saco y corbata oscuros, mujeres con blazer oscuro');
        $acta = self::m('acta_evaluacion', 'Acta de evaluación profesional');
        $bach = self::d('cert_bachillerato', 'Copia del certificado de bachillerato con firma y sello de cotejo con el original', 'cert_bachillerato');
        $auten = self::d('const_autenticacion', 'Constancia de autenticación si el bachillerato no es UAEM', 'const_autenticacion', true);
        $reval = self::m('dictamen_revalidacion', 'Dictamen de revalidación expedido por la Secretaría de Educación Pública (sólo si aplica)', true);
        $nac = self::d('acta_nacimiento', 'Acta de nacimiento', 'acta_nacimiento');
        $cert = self::d('cert_licenciatura', 'Certificado total de licenciatura', 'cert_licenciatura');
        $solicitud = self::d('solicitud', 'Solicitud por escrito del egresado dirigida a la Subdirección Académica con firma y sello de la misma', ['solicitud_firmada', 'solicitud_eval']);
        $art5 = self::d('art5', 'Autorización para continuar con trámites de titulación con fundamento en el artículo 5° del Reglamento de Evaluación Profesional (sólo si aplica)', 'dictamen_consejos', true);
        $adeudo = self::d('no_adeudo', 'Constancia de no adeudo a Servicios Escolares', ['no_adeudo_control_escolar', 'recibo_no_adeudo_uaem']);
        $revision = self::g('formato_revision', 'Formato de revisión para autorización de evaluación profesional', 'requisitos');
        $integracion = self::g('formato_integracion', 'Formato de integración de expediente', 'integracion');
        $datos = self::d('datos_titulo', 'Formato de datos del interesado para expedición de título profesional', 'datos_titulo');

        return match ($modalidad) {
            'aprovechamiento' => [$fotos, $acta, $bach, $auten, $reval, $nac, $cert, $solicitud, $adeudo, $revision, $integracion, $datos],
            'egel' => [
                $fotos, $acta, $bach, $auten, $reval, $nac, $cert, $solicitud,
                self::d('reporte_ceneval', 'Constancia reporte (individual) con Vo.Bo (firma y sello) de la Subdirección Académica', 'reporte_ceneval'),
                self::m('acta_aplazamiento', 'Acta de aplazamiento (sólo si aplica)', true),
                self::m('solicitud_2a', 'Solicitud por escrito del egresado dirigida a la Subdirección Académica con firma y sello de la misma (segunda vez, sólo si aplica)', true),
                self::m('reporte_2a', 'Constancia reporte (individual) con Vo.Bo (firma y sello) de la Subdirección Académica (segunda vez, sólo si aplica)', true),
                $art5, $adeudo, $revision, $integracion, $datos,
            ],
            'creditos' => [
                $fotos, $acta, $bach, $auten, $reval, $nac, $cert, $solicitud,
                self::d('cert_estudios_avanzados', 'Certificado que ampare el 75 % de maestría o doctorado integral o el 100 % del diplomado superior', 'cert_estudios_avanzados'),
                self::d('dictamen_equivalencia', 'Dictamen de equivalencia expedido por la Secretaría de Ciencia para cualquier diplomado superior universitario, maestría o doctorado integral', 'dictamen_equivalencia'),
                $art5, $adeudo, $revision, $integracion, $datos,
            ],
            'articulo' => [
                $fotos, $acta, $bach, $auten, $reval, $nac, $cert, $solicitud,
                self::d('carta_publicacion', 'Carta de aceptación del documento en la revista arbitrada', 'carta_publicacion'),
                self::d('constancia_indices', 'Oficio de validación emitido por la Secretaría de Ciencia', 'constancia_indices'),
                $art5, $adeudo, $revision, $integracion, $datos,
            ],
            default => [$fotos, $acta, $bach, $auten, $reval, $nac, $cert, $solicitud, $adeudo, $art5, $revision, $integracion, $datos],
        };
    }

    /** Modalidades con trabajo escrito que marca el encabezado de su formato (todas comparten el mismo). */
    public static function opcionesTrabajoEscrito(): array
    {
        return ['tesis' => 'Tesis', 'tesina' => 'Tesina', 'ensayo' => 'Ensayo', 'memoria' => 'Memoria de experiencia laboral', 'obra' => 'Obra artística',
            'residencia' => 'Reporte de residencia de investigación', 'autoempleo' => 'Reporte de autoempleo profesional',
            'aplicacion' => 'Reporte de aplicación de conocimientos', 'salud' => 'Reporte de servicio social en el área de la salud'];
    }

    public static function titulo(string $modalidad): string
    {
        return match ($modalidad) {
            'aprovechamiento' => 'INTEGRACIÓN DE EXPEDIENTE: APROVECHAMIENTO ACADÉMICO',
            'egel' => 'INTEGRACIÓN DE EXPEDIENTE: EXAMEN GENERAL DE EGRESO',
            'creditos' => 'INTEGRACIÓN DE EXPEDIENTE: CRÉDITOS EN ESTUDIOS AVANZADOS',
            'articulo' => 'INTEGRACIÓN DE EXPEDIENTE: ARTÍCULO ESPECIALIZADO PUBLICADO EN REVISTA ARBITRADA',
            default => 'FORMATO DE INTEGRACIÓN DE EXPEDIENTE: MODALIDADES CON TRABAJO ESCRITO',
        };
    }

    // ------------------------------------------------------------------ estado

    /** ¿Ya se puede integrar el expediente? Solo cuando la evaluación concluyó y no fue aplazada. */
    public static function elegible(array $t): bool
    {
        return $t['estado'] === 'concluido' && !in_array((string) $t['resultado'], ['', 'aplazado'], true) && (int) $t['historico'] === 0;
    }

    public static function obtener(int $tramiteId): ?array
    {
        $f = Database::una('SELECT e.*, v.nombre AS verificador, s.nombre AS enviador FROM tit_expediente_final e
            LEFT JOIN usuarios v ON v.id = e.verificado_por LEFT JOIN usuarios s ON s.id = e.enviado_por WHERE e.tramite_id = ?', [$tramiteId]);
        if ($f) {
            $f['marcas'] = json_decode((string) $f['marcas'], true) ?: [];
        }
        return $f;
    }

    private static function fila(array $docs, string $clave): ?array
    {
        foreach ($docs as $f) {
            if ($f['clave'] === $clave) {
                return $f;
            }
        }
        return null;
    }

    /**
     * Lista de verificación del trámite con el estado de cada renglón y sus archivos.
     * Estado: ok (cumple), no_aplica (renglón condicional que no le toca), falta.
     * @return list<array<string,mixed>>
     */
    public static function evaluar(array $t, array $docs, ?array $guardado): array
    {
        $marcas = $guardado['marcas'] ?? [];
        $acta = (string) Database::valor("SELECT numero FROM tit_actas WHERE tramite_id = ? AND tipo = 'evaluacion'", [(int) $t['id']]);
        $res = [];
        foreach (self::renglones((string) $t['modalidad']) as $r) {
            $r['estado'] = 'falta';
            $r['origen'] = 'fisico';
            $r['detalle'] = '';
            $r['archivos'] = [];
            $filas = [];
            foreach ($r['docs'] as $clave) {
                if ($f = self::fila($docs, $clave)) {
                    $filas[$clave] = $f;
                }
            }
            if (isset($r['generado'])) {
                $r['origen'] = 'plataforma';
                $r['estado'] = 'ok';
                $r['detalle'] = $r['generado'] === 'integracion' ? 'Se genera llenado al exportar el expediente.' : 'La plataforma lo genera con el estado de cada documento.';
            } elseif ($filas) {
                $r['origen'] = 'plataforma';
                $estados = array_column($filas, 'estado');
                if (count($filas) < count($r['docs']) && count($r['docs']) > 1 && !in_array($r['k'], ['solicitud'], true)) {
                    $r['detalle'] = 'Falta subir: ' . implode(', ', array_diff($r['docs'], array_keys($filas))) . '.';
                } elseif (array_unique($estados) === ['no_aplica']) {
                    $r['estado'] = 'no_aplica';
                    $r['detalle'] = 'El pasante indicó que no le aplica.';
                } elseif (!array_diff($estados, ['aprobado', 'no_aplica'])) {
                    $r['estado'] = 'ok';
                    $r['detalle'] = 'Validado en la plataforma.';
                } else {
                    $r['detalle'] = 'Documento aún no validado (' . implode(', ', array_unique($estados)) . ').';
                }
                foreach ($filas as $f) {
                    if (!empty($f['ruta']) && $f['estado'] === 'aprobado') {
                        $r['archivos'][] = ['nombre' => $f['clave'], 'ruta' => (string) ($f['subdir_ruta'] ?: $f['ruta']), 'doc' => $f];
                        if (!empty($f['cotejo_ruta'])) {
                            $r['archivos'][] = ['nombre' => $f['clave'] . '_cotejo', 'ruta' => (string) $f['cotejo_ruta'], 'doc' => $f];
                        }
                    }
                }
            } else {
                $m = $marcas[$r['k']] ?? '';
                if ($r['k'] === 'acta_evaluacion' && $acta !== '') {
                    $r['detalle'] = 'Acta n.º ' . $acta . ' (registrada en el resultado de la evaluación). Confirma que el acta firmada está en el expediente físico.';
                }
                $permite = $r['docs'] === [] || $r['cond'];
                if ($permite && ($m === 'ok' || ($m === 'no_aplica' && $r['cond']))) {
                    $r['estado'] = $m;
                }
                if (!$permite) {
                    $r['detalle'] = 'El pasante aún no sube este documento a la plataforma.';
                }
            }
            // Un renglón condicional sin documento en la plataforma se resuelve a mano (cuando existe el documento, manda su estado).
            $r['manual'] = !isset($r['generado']) && !$filas && ($r['docs'] === [] || $r['cond']);
            $res[] = $r;
        }
        return $res;
    }

    /** Renglones condicionales sin documento en la plataforma también se pueden marcar «no aplica» a mano. */
    public static function completo(array $items): bool
    {
        foreach ($items as $i) {
            if ($i['estado'] === 'falta') {
                return false;
            }
        }
        return $items !== [];
    }

    public static function faltantes(array $items): array
    {
        return array_values(array_filter($items, static fn(array $i): bool => $i['estado'] === 'falta'));
    }

    // --------------------------------------------------------------- acciones

    /** Guarda las confirmaciones manuales y las observaciones. */
    public static function guardar(int $tramiteId, array $items, array $post, string $observaciones): void
    {
        $marcas = [];
        foreach ($items as $i) {
            $v = (string) ($post['m'][$i['k']] ?? '');
            if ($v === 'ok' || ($v === 'no_aplica' && $i['cond'])) {
                $marcas[$i['k']] = $v;
            }
        }
        Database::ejecutar(
            "INSERT INTO tit_expediente_final (tramite_id, marcas, observaciones) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE marcas = VALUES(marcas), observaciones = VALUES(observaciones)",
            [$tramiteId, json_encode($marcas, JSON_UNESCAPED_UNICODE), $observaciones === '' ? null : mb_substr($observaciones, 0, 1000)]
        );
    }

    public static function verificar(int $tramiteId, int $usuarioId): void
    {
        Database::ejecutar("UPDATE tit_expediente_final SET estado = 'verificado', verificado_por = ?, verificado_en = NOW() WHERE tramite_id = ? AND estado = 'en_integracion'", [$usuarioId, $tramiteId]);
    }

    public static function reabrir(int $tramiteId): void
    {
        Database::ejecutar("UPDATE tit_expediente_final SET estado = 'en_integracion', verificado_por = NULL, verificado_en = NULL WHERE tramite_id = ? AND estado = 'verificado'", [$tramiteId]);
    }

    /** Expedientes verificados (o ya enviados) para la pantalla del departamento. */
    public static function bandeja(): array
    {
        $filas = Database::todas(
            "SELECT t.id, t.folio, t.modalidad, t.resultado, t.actualizado_en, t.datos, t.alumno_id,
                    COALESCE(u.nombre, JSON_UNQUOTE(JSON_EXTRACT(t.datos, '$.datos.nombre'))) AS alumno_nombre,
                    e.estado AS exp_estado, e.verificado_en, e.enviado_en, e.lote_id
             FROM tit_tramites t
             LEFT JOIN usuarios u ON u.id = t.alumno_id
             LEFT JOIN tit_expediente_final e ON e.tramite_id = t.id
             WHERE t.estado = 'concluido' AND t.historico = 0 AND (t.resultado IS NULL OR t.resultado <> 'aplazado')
             ORDER BY FIELD(COALESCE(e.estado, 'en_integracion'), 'verificado', 'en_integracion', 'enviado'), t.actualizado_en DESC LIMIT 300"
        );
        foreach ($filas as &$f) {
            $f['exp_estado'] = $f['exp_estado'] ?: 'sin_iniciar';
        }
        return $filas;
    }

    // ---------------------------------------------------------------- exportar

    private static function libs(): void
    {
        $lib = dirname(APP_RAIZ) . '/lib';
        if (!defined('FPDF_FONTPATH')) {
            define('FPDF_FONTPATH', $lib . '/fpdf/font/');
        }
        require_once $lib . '/fpdf/fpdf.php';
    }

    private static function l1(string $s): string
    {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s) ?: $s;
    }

    /** «Formato de integración de expediente» de la opción, con X en lo que cumple, y la firma del responsable. */
    public static function pdfFormato(array $t, array $items, string $responsable): string
    {
        self::libs();
        $pdf = new \FPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(22, 14, 22);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AddPage();
        $logo = dirname(APP_RAIZ) . '/public/assets/img/logo.png';
        if (is_file($logo)) {
            try {
                $pdf->Image($logo, 95, 10, 26);
            } catch (\Throwable) {
                // FPDF no admite todos los PNG (p. ej. con canal alfa): el formato se genera sin el logotipo.
            }
        }
        $pdf->SetY(34);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->MultiCell(0, 5.5, self::l1(self::titulo((string) $t['modalidad'])), 0, 'C');
        $pdf->Ln(3);
        $pdf->SetFont('Arial', '', 8.5);
        $pdf->Cell(0, 4.5, self::l1('Folio ' . $t['folio'] . ' · ' . ($t['alumno_nombre'] ?? '') . ' · Cuenta ' . ($t['datos']['datos']['cuenta'] ?? '')), 0, 1, 'C');
        $pdf->Ln(3);
        $caja = static function (\FPDF $p, bool $x): void {
            $p->SetFont('Arial', 'B', 9);
            $p->Cell(6, 5, $x ? 'X' : '', 1, 0, 'C');
            $p->Cell(2, 5, '', 0, 0);
        };
        if (Catalogo::modalidad((string) $t['modalidad'])['tipo'] === 'con_trabajo') {
            $pdf->SetFont('Arial', 'B', 9.5);
            $pdf->Cell(0, 6, self::l1('Marque con una X la modalidad de titulación a elegir:'), 0, 1);
            $mod = (string) $t['modalidad'];
            $mapa = ['tesis' => 'tesis', 'tesina' => 'tesina', 'ensayo' => 'ensayo', 'memoria' => 'memoria', 'residencia' => 'residencia', 'autoempleo' => 'autoempleo', 'aplicacion' => 'aplicacion'];
            foreach (self::opcionesTrabajoEscrito() as $k => $nombre) {
                $caja($pdf, ($mapa[$k] ?? '') === $mod);
                $pdf->SetFont('Arial', '', 9);
                $pdf->Cell(0, 5, self::l1($nombre), 0, 1);
                $pdf->Ln(0.8);
            }
            $pdf->Ln(2);
        }
        $pdf->SetFont('Arial', 'B', 9.5);
        $pdf->Cell(0, 6, self::l1('Documentos para solicitar la elaboración del título profesional (marque con una X):'), 0, 1);
        foreach ($items as $i) {
            $y = $pdf->GetY();
            $caja($pdf, $i['estado'] === 'ok');
            $pdf->SetFont('Arial', '', 8.5);
            $x = $pdf->GetX();
            $texto = $i['texto'] . ($i['estado'] === 'no_aplica' ? ' [No aplica]' : '');
            $pdf->MultiCell(0, 5, self::l1($texto), 0, 'L');
            $pdf->Ln(0.8);
        }
        $pdf->Ln(5);
        $pdf->SetFont('Arial', '', 9);
        $pdf->MultiCell(0, 5, self::l1('Hago constar que se ha revisado el expediente y contiene todos los documentos requeridos para esta modalidad en este formato en el orden establecido.'), 0, 'J');
        $pdf->Ln(14);
        $pdf->Cell(0, 5, '______________________________________', 0, 1, 'C');
        $pdf->Cell(0, 5, self::l1($responsable), 0, 1, 'C');
        $pdf->Cell(0, 5, self::l1('Nombre y firma del Responsable de Evaluación Profesional'), 0, 1, 'C');
        $pdf->Cell(0, 5, self::l1('Sello del Espacio Académico'), 0, 1, 'C');
        return $pdf->Output('S');
    }

    /**
     * Arma la carpeta comprimida con los expedientes indicados (individual o por lote).
     * @param list<int> $ids trámites verificados
     * @return array{0:string,1:list<string>} [bytes del ZIP, folios incluidos]
     */
    public static function zip(array $ids, string $quien): array
    {
        $base = rtrim((string) \App\Core\App::config('archivos.storage'), '/\\');
        $tmp = tempnam(sys_get_temp_dir(), 'se');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo armar la carpeta comprimida.');
        }
        $folios = [];
        $relacion = "Envío de expedientes de titulación a Servicios Escolares (Administración Central)\nFacultad de Ingeniería, UAEMéx\nGenerado el " . date('d/m/Y H:i') . " por $quien\n\n";
        foreach ($ids as $id) {
            $t = TitulacionRepository::obtener($id);
            $guardado = self::obtener($id);
            if (!$t || !$guardado || !in_array($guardado['estado'], ['verificado', 'enviado'], true)) {
                continue;
            }
            $docs = TitulacionRepository::documentosVigentes($id);
            $items = self::evaluar($t, $docs, $guardado);
            $nombre = preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT', (string) ($t['alumno_nombre'] ?? '')) ?: 'pasante');
            $dir = $t['folio'] . '_' . trim((string) $nombre, '_') . '/';
            $zip->addEmptyDir($dir);
            $indice = "Expediente {$t['folio']} · " . ($t['alumno_nombre'] ?? '') . ' · ' . (Catalogo::modalidad((string) $t['modalidad'])['nombre'] ?? $t['modalidad'])
                . "\nVerificado por {$guardado['verificador']} el {$guardado['verificado_en']}\n";
            if (!empty($guardado['observaciones'])) {
                $indice .= 'Observaciones: ' . $guardado['observaciones'] . "\n";
            }
            $indice .= "\nDocumentos en el orden del formato de integración de expediente:\n\n";
            $n = 0;
            $meter = static function (string $nombreArchivo, string $bytes, string $detalle) use ($zip, $dir, &$n, &$indice): void {
                $n++;
                $archivo = sprintf('%02d_%s.pdf', $n, preg_replace('/[^A-Za-z0-9_-]+/', '_', $nombreArchivo));
                $zip->addFromString($dir . $archivo, $bytes);
                $indice .= $archivo . "\n  " . $detalle . "\n  SHA-256: " . hash('sha256', $bytes) . "\n\n";
            };
            foreach ($items as $i) {
                if ($i['estado'] === 'no_aplica') {
                    $indice .= '— ' . $i['texto'] . ": NO APLICA\n\n";
                    continue;
                }
                if (isset($i['generado'])) {
                    try {
                        $bytes = $i['generado'] === 'integracion'
                            ? self::pdfFormato($t, $items, (string) $guardado['verificador'])
                            : Formatos::generar('requisitos', $t)[0];
                    } catch (\Throwable) {
                        $indice .= '— ' . $i['texto'] . ": no se pudo generar\n\n";
                        continue;
                    }
                    $meter($i['k'], $bytes, $i['texto'] . ' (generado por la plataforma)');
                    continue;
                }
                $hubo = false;
                foreach ($i['archivos'] as $a) {
                    $abs = realpath($base . '/' . $a['ruta']);
                    if ($abs !== false && is_file($abs) && str_starts_with($abs, (string) realpath($base) . DIRECTORY_SEPARATOR)) {
                        $meter($i['k'] . (str_ends_with($a['nombre'], '_cotejo') ? '_cotejo' : ''), (string) file_get_contents($abs), $i['texto']);
                        $hubo = true;
                    }
                }
                if (!$hubo) {
                    $indice .= '— ' . $i['texto'] . ($i['origen'] === 'fisico' ? ": se entrega en físico\n\n" : ": sin archivo\n\n");
                }
            }
            $zip->addFromString($dir . '00_indice.txt', $indice);
            $relacion .= $t['folio'] . ' · ' . ($t['alumno_nombre'] ?? '') . ' · cuenta ' . ($t['datos']['datos']['cuenta'] ?? '') . ' · ' . (Catalogo::modalidad((string) $t['modalidad'])['nombre'] ?? '') . "\n";
            $folios[] = (string) $t['folio'];
        }
        $zip->addFromString('00_relacion_de_envio.txt', $relacion . "\nTotal de expedientes: " . count($folios) . "\n");
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return [$bytes, $folios];
    }

    /** Registra el envío: el expediente pasa a «enviado» y queda ligado al lote. */
    public static function registrarEnvio(array $ids, array $folios, string $huella, int $usuarioId): int
    {
        $lote = Database::ejecutar('INSERT INTO tit_expediente_lotes (creado_por, total, folios, huella) VALUES (?, ?, ?, ?)',
            [$usuarioId, count($folios), mb_substr(implode(', ', $folios), 0, 1000), $huella]);
        foreach ($ids as $id) {
            Database::ejecutar("UPDATE tit_expediente_final SET estado = 'enviado', enviado_por = ?, enviado_en = NOW(), lote_id = ? WHERE tramite_id = ? AND estado = 'verificado'", [$usuarioId, $lote, $id]);
        }
        return (int) $lote;
    }

    public static function lotes(): array
    {
        return Database::todas('SELECT l.*, u.nombre AS autor FROM tit_expediente_lotes l JOIN usuarios u ON u.id = l.creado_por ORDER BY l.id DESC LIMIT 30');
    }
}
