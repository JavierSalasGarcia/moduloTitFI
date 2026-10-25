<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Modules\Titulacion\Services\SubdireccionFirma;

/**
 * Evalúa en qué punto va un trámite: qué etapas están completas, cuál es la actual
 * y qué falta en ella. No toca la base de datos; recibe filas ya cargadas.
 */
final class Flujo
{
    /** Valida un campo capturado contra su definición. Devuelve mensaje de error o null. */
    public static function errorCampo(array $campo, mixed $valor): ?string
    {
        $valor = is_string($valor) ? trim($valor) : $valor;
        $vacio = $valor === null || $valor === '' || $valor === [];
        if ($vacio) {
            return !empty($campo['req']) ? 'Es obligatorio: ' . $campo['label'] . '.' : null;
        }
        $tipo = $campo['tipo'] ?? 'texto';
        if ($tipo === 'select') {
            if (!is_string($valor) || !array_key_exists($valor, $campo['opciones'] ?? [])) {
                return 'Elige una opción válida en «' . $campo['label'] . '».';
            }
            return null;
        }
        if ($tipo === 'cronograma') {
            return is_array($valor) ? null : 'El cronograma no es válido.';
        }
        if (!is_string($valor)) {
            return 'Dato no válido en «' . $campo['label'] . '».';
        }
        if (isset($campo['max']) && mb_strlen($valor) > (int) $campo['max']) {
            return '«' . $campo['label'] . '» excede ' . $campo['max'] . ' caracteres.';
        }
        if ($tipo === 'email' && filter_var($valor, FILTER_VALIDATE_EMAIL) === false) {
            return 'Revisa el correo de «' . $campo['label'] . '».';
        }
        if ($tipo === 'fecha') {
            $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
            if (!$f || $f->format('Y-m-d') !== $valor) {
                return 'La fecha de «' . $campo['label'] . '» no es válida.';
            }
        }
        if ($tipo === 'hora' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $valor)) {
            return 'La hora de «' . $campo['label'] . '» no es válida.';
        }
        if (!empty($campo['patron']) && !preg_match('/' . $campo['patron'] . '/D', $valor)) {
            return 'Revisa el formato de «' . $campo['label'] . '».';
        }
        return null;
    }

    /** ¿Venció la exclusividad del tema? (fecha de aprobación del protocolo más los años de vigencia de Ajustes) */
    public static function protocoloVencido(array $hitos, ?\DateTimeImmutable $hoy = null): bool
    {
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($hitos['protocolo']['datos']['fecha'] ?? ''));
        if (!$f) {
            return false;
        }
        $anios = max(1, (int) (function_exists('ajuste') ? \ajuste('titulacion_vigencia_protocolo', '2') : 2));
        return $f->modify('+' . $anios . ' years') < ($hoy ?? new \DateTimeImmutable('today'));
    }

    /** ¿Todos los campos obligatorios de la etapa están capturados y son válidos? */
    public static function capturaCompleta(array $etapa, array $datos): bool
    {
        foreach ($etapa['campos'] ?? [] as $campo) {
            $valor = $datos[$etapa['clave']][$campo['k']] ?? null;
            if (self::errorCampo($campo, $valor) !== null) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $tramite  fila de tit_tramites con 'datos' ya decodificado
     * @param array $docs     TitulacionRepository::documentosVigentes()
     * @param array $hitos    TitulacionRepository::hitos()
     * @return array{etapas: array<int,array<string,mixed>>, actual: ?string, completo: bool}
     */
    public static function evaluar(array $tramite, array $docs, array $hitos): array
    {
        $etapas = Catalogo::etapas((string) $tramite['modalidad'], $tramite['variante'] ?: null);
        $resultado = [];
        $bloquea = false;
        $actual = null;

        foreach ($etapas as $e) {
            $clave = $e['clave'];
            $e['captura_ok'] = self::capturaCompleta($e, $tramite['datos']);
            $e['docs_estado'] = [];
            $porValidar = $rechazados = $faltan = $porCotejar = $porFirmar = $porFirmasExt = 0;
            $vencido = self::protocoloVencido($hitos);
            foreach ($e['documentos'] ?? [] as $d) {
                if (($d['solo_si'] ?? '') === 'protocolo_vencido' && !$vencido) {
                    continue;
                }
                $fila = $docs[$clave . '|' . $d['clave']] ?? null;
                $estado = !empty($fila['pendiente_firmas']) ? 'firmas' : ($fila['estado'] ?? 'pendiente');
                $cotejado = empty($d['cotejo']) || !empty($fila['cotejo_en']);
                $firmado = SubdireccionFirma::tipoDeDocumento($d) === null || !empty($fila['subdir_en']);
                $ok = ($estado === 'aprobado' && $cotejado && $firmado) || ($estado === 'no_aplica' && $d['condicion'] !== null);
                if ($estado === 'firmas') {
                    $porFirmasExt++;
                } elseif ($estado === 'aprobado' && !$firmado) {
                    $porFirmar++;
                } elseif ($estado === 'aprobado' && !$cotejado) {
                    $porCotejar++;
                } elseif ($estado === 'en_revision') {
                    $porValidar++;
                } elseif ($estado === 'rechazado') {
                    $rechazados++;
                } elseif (!$ok) {
                    $faltan++;
                }
                $e['docs_estado'][$d['clave']] = ['def' => $d, 'fila' => $fila, 'estado' => $estado, 'ok' => $ok];
            }
            $e['por_validar'] = $porValidar;
            $e['por_cotejar'] = $porCotejar;
            $e['por_firmar'] = $porFirmar;
            $e['por_firmas_ext'] = $porFirmasExt;
            $e['rechazados'] = $rechazados;
            $e['faltan'] = $faltan;
            $e['docs_ok'] = array_reduce($e['docs_estado'], static fn(bool $c, array $x): bool => $c && $x['ok'], true);

            $hito = $hitos[$clave] ?? null;
            $e['hito'] = $hito;
            $entrega = $e['personal']['entrega'] ?? null;
            $filaEntrega = $entrega ? ($docs[$clave . '|' . $entrega['clave']] ?? null) : null;
            $entregaOk = $entrega === null
                || (($filaEntrega['estado'] ?? '') === 'aprobado'
                    && (SubdireccionFirma::tipoDeDocumento($entrega) === null || !empty($filaEntrega['subdir_en'])));
            foreach ($e['personal']['extras'] ?? [] as $extra) {
                $fx = $docs[$clave . '|' . $extra['clave']] ?? null;
                $okx = ($fx['estado'] ?? '') === 'aprobado' && (SubdireccionFirma::tipoDeDocumento($extra) === null || !empty($fx['subdir_en']));
                $entregaOk = $entregaOk && $okx;
            }
            $e['entrega_asignacion'] = !empty($entrega['asignacion']);
            $e['protocolo_vencido'] = $vencido;
            $e['entrega_por_firmar'] = $entrega !== null && !$entregaOk && $hito !== null;
            $e['entrega_ok'] = $entregaOk;
            $e['personal_ok'] = !isset($e['personal']) || ($hito !== null && $entregaOk);

            if ($e['tipo'] === 'confirmacion') {
                $e['confirmada'] = !empty($tramite['datos']['_confirmacion'][$clave]);
                $e['propia_ok'] = $e['confirmada'];
            } else {
                $e['propia_ok'] = $e['captura_ok'];
            }
            $e['completa'] = $e['propia_ok'] && $e['docs_ok'] && $e['personal_ok'];

            if ($e['completa'] && !$bloquea) {
                $e['situacion'] = 'completa';
            } elseif (!$bloquea) {
                $e['situacion'] = 'actual';
                $bloquea = true;
                $actual = $clave;
                $e['paso'] = self::paso($e);
            } else {
                $e['situacion'] = 'bloqueada';
            }
            $resultado[] = $e;
        }
        return ['etapas' => $resultado, 'actual' => $actual, 'completo' => $actual === null && $resultado !== []];
    }

    /** Qué sigue en la etapa actual, y de quién es el turno. */
    private static function paso(array $e): array
    {
        if (!$e['propia_ok']) {
            return $e['tipo'] === 'confirmacion'
                ? ['turno' => 'alumno', 'texto' => 'Confirma cuando tu trabajo esté listo.']
                : ['turno' => 'alumno', 'texto' => 'Completa los datos de esta etapa.'];
        }
        if ($e['rechazados'] > 0) {
            return $e['clave'] === 'protocolo'
                ? ['turno' => 'alumno', 'texto' => 'Atiende las observaciones del comité (Titulación → Mensajes), corrige tu protocolo y vuelve a enviarlo.']
                : ['turno' => 'alumno', 'texto' => 'Corrige y vuelve a subir ' . $e['rechazados'] . ' documento(s) observado(s).'];
        }
        if ($e['por_firmas_ext'] > 0) {
            return ['turno' => 'alumno', 'texto' => 'Esperando la firma de tu asesor: le llegó un correo con su enlace. Puedes reenviárselo.'];
        }
        if ($e['faltan'] > 0) {
            return ['turno' => 'alumno', 'texto' => 'Sube ' . $e['faltan'] . ' documento(s) pendiente(s).'];
        }
        if (!empty($e['agenda']) && empty($e['hito'])) {
            return ['turno' => 'alumno', 'texto' => 'Elige la fecha y hora de tu ' . ($e['agenda'] === 'previo' ? 'examen previo' : 'examen definitivo') . ', propón a tu sínodo y envía la solicitud al Coordinador de Docencia.'];
        }
        if ($e['por_validar'] > 0) {
            return $e['clave'] === 'protocolo'
                ? ['turno' => 'personal', 'texto' => 'Tu protocolo está en revisión del Comité de Revisión de Protocolos de tu licenciatura.']
                : ['turno' => 'personal', 'texto' => 'Personal de Titulación está validando ' . $e['por_validar'] . ' documento(s).'];
        }
        if ($e['por_firmar'] > 0) {
            return ['turno' => 'personal', 'texto' => 'Documento validado: esperando la firma electrónica de la Subdirección Académica.'];
        }
        if ($e['por_cotejar'] > 0) {
            return ['turno' => 'personal', 'texto' => 'Lleva a Apoyo a Titulación el original de ' . $e['por_cotejar'] . ' documento(s) para su cotejo.'];
        }
        if (!$e['personal_ok'] && !empty($e['hito']) && !empty($e['entrega_asignacion'])) {
            return ['turno' => 'personal', 'texto' => 'Solicitud enviada: esperando que el Coordinador de Docencia ratifique o modifique tu sínodo y firme (le llegó un correo con su enlace).'];
        }
        if (!$e['personal_ok'] && !empty($e['entrega_por_firmar'])) {
            return $e['clave'] === 'protocolo'
                ? ['turno' => 'personal', 'texto' => 'Protocolo aprobado: el oficio de aprobación del tema está en proceso de firmas (Vo. Bo. de la Jefatura de Evaluación Profesional y firma de la Subdirección Académica).']
                : ['turno' => 'personal', 'texto' => 'Oficio generado: esperando la firma electrónica de la Subdirección Académica.'];
        }
        if (!$e['personal_ok']) {
            return ['turno' => 'personal', 'texto' => 'Pendiente: ' . ($e['personal']['titulo'] ?? 'trámite del departamento') . '.'];
        }
        return ['turno' => 'alumno', 'texto' => 'Etapa completa.'];
    }
}
