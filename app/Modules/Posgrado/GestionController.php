<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Posgrado\Services\Aviso;
use App\Modules\Posgrado\Services\Expediente;
use App\Modules\Posgrado\Services\Formatos;
use Throwable;

/** Bandeja y validación para el personal de Apoyo a Grado (Coordinación de Estudios Avanzados). */
final class GestionController extends PosgradoBase
{
    public function bandeja(): void
    {
        $this->privado();
        $this->soloPersonal();
        $filtros = [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80),
            'estado' => array_key_exists((string) ($_GET['estado'] ?? ''), Catalogo::estados()) ? (string) $_GET['estado'] : '',
            'momento' => in_array((int) ($_GET['momento'] ?? 0), [1, 2, 3, 4], true) ? (int) $_GET['momento'] : 0,
            'nivel' => array_key_exists((string) ($_GET['nivel'] ?? ''), Catalogo::niveles()) ? (string) $_GET['nivel'] : '',
            'pendientes' => !empty($_GET['pendientes']),
        ];
        $this->vista('gestion_bandeja', [
            'titulo' => 'Posgrado: validar trámites',
            'filas' => PosgradoRepository::bandeja($filtros),
            'filtros' => $filtros,
            'resumen' => PosgradoRepository::resumen(),
        ]);
    }

    public function ver(string $id): void
    {
        $this->privado();
        $t = $this->tramite($id, true);
        [$ev] = $this->evaluar($t);
        $this->vista('gestion_tramite', [
            'titulo' => 'Expediente de grado ' . $t['folio'],
            't' => $t,
            'ev' => $ev,
            'eventos' => PosgradoRepository::eventos((int) $t['id'], false),
        ]);
    }

    private function volver(array $t, string $ancla = ''): string
    {
        return 'posgrado/gestion/' . (int) $t['id'] . ($ancla !== '' ? '#' . $ancla : '');
    }

    private function abierto(array $t): void
    {
        if ($t['estado'] !== 'abierto') {
            flash('error', 'El trámite ya no está abierto.');
            redirigir($this->volver($t));
        }
    }

    /** Nombre legible de un documento por su clave. */
    private static function nombreDocumento(string $clave): string
    {
        return Catalogo::requisitos()[$clave]['nombre'] ?? Catalogo::documentosMomento()[$clave]['nombre'] ?? $clave;
    }

    // ------------------------------------------------------------------ documentos

    public function revisar(string $id, string $docId): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $d = ctype_digit($docId) && strlen($docId) < 19 ? PosgradoRepository::documento((int) $docId) : null;
        if ($d === null || (int) $d['tramite_id'] !== (int) $t['id'] || $d['tipo'] !== 'pdf' || $d['estado'] === 'reemplazado') {
            $this->noEncontrado();
        }
        $decision = $this->post('decision');
        $comentario = trim((string) preg_replace('/\r\n?/', "\n", $this->post('comentario')));
        $volver = $this->volver($t, 'doc-' . $d['clave']);
        if (!in_array($decision, ['validado', 'devuelto'], true)) {
            flash('error', 'Elige validar o devolver el documento.');
            redirigir($volver);
        }
        if ($decision === 'devuelto' && mb_strlen($comentario) < 5) {
            flash('error', 'Explica al egresado qué debe corregir (mínimo 5 caracteres).');
            redirigir($volver);
        }
        if (mb_strlen($comentario) > 900) {
            flash('error', 'La observación excede 900 caracteres.');
            redirigir($volver);
        }
        $hitos = PosgradoRepository::hitos((int) $t['id']);
        if (isset(Catalogo::requisitos()[$d['clave']]) && isset($hitos['cotejo'])) {
            flash('error', 'Los requisitos ya se cotejaron; no se pueden cambiar.');
            redirigir($volver);
        }
        $nombre = self::nombreDocumento((string) $d['clave']);
        PosgradoRepository::revisarDocumento((int) $d['id'], $decision, $comentario !== '' ? mb_substr($comentario, 0, 900) : null, Auth::id());
        PosgradoRepository::asignarResponsable((int) $t['id'], Auth::id());
        PosgradoRepository::evento((int) $t['id'], Auth::id(), $decision === 'validado' ? 'validacion' : 'observacion',
            ($decision === 'validado' ? 'Documento validado: ' : 'Documento devuelto con observaciones: ') . $nombre . ($comentario !== '' ? ' — ' . $comentario : ''));
        Audit::record('posgrado::revisar', 'correcto', 'Documento ' . (int) $d['id'] . ' ' . $decision);
        $t = $this->sincronizar((int) $t['id']);
        Aviso::egresado($t, $decision === 'validado' ? 'Documento validado: ' . $nombre : 'Corrige tu documento: ' . $nombre,
            $decision === 'validado'
                ? 'Apoyo a Grado validó «' . $nombre . '» en tu trámite de evaluación de grado ' . $t['folio'] . '.'
                : 'Apoyo a Grado te devolvió «' . $nombre . "» con esta observación:\n\n" . $comentario . "\n\nCorrígelo y vuelve a subirlo en tu trámite " . $t['folio'] . '.',
            $decision === 'devuelto');
        flash('exito', $decision === 'validado' ? 'Documento validado.' : 'Documento devuelto al egresado con tu observación.');
        redirigir($volver);
    }

    /** Marca o desmarca una entrega en físico (USB, fotografías, resumen, formato de revisión). */
    public function fisico(string $id, string $clave): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $req = Catalogo::requisitos()[$clave] ?? null;
        if ($req === null || $req['tipo'] !== 'fisico') {
            $this->noEncontrado();
        }
        $volver = $this->volver($t, 'req-' . $clave);
        if (isset(PosgradoRepository::hitos((int) $t['id'])['cotejo'])) {
            flash('error', 'Los requisitos ya se cotejaron; no se pueden cambiar.');
            redirigir($volver);
        }
        if ($this->post('accion') === 'quitar') {
            PosgradoRepository::retirarDocumento((int) $t['id'], $clave);
            PosgradoRepository::evento((int) $t['id'], Auth::id(), 'fisico', 'Entrega en físico desmarcada: ' . $req['nombre'], false);
        } else {
            PosgradoRepository::registrarDocumento((int) $t['id'], $clave, 'fisico', 'recibido', null, null, null, Auth::id());
            PosgradoRepository::evento((int) $t['id'], Auth::id(), 'fisico', 'Recibido en físico: ' . $req['nombre']);
        }
        PosgradoRepository::asignarResponsable((int) $t['id'], Auth::id());
        Audit::record('posgrado::fisico', 'correcto', 'Trámite ' . (int) $t['id'] . ' · ' . $clave);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Listo.');
        redirigir($volver);
    }

    // ------------------------------------------------------------------- hitos

    /** Cotejo de originales: cierra el primer momento y emite el oficio de cotejo dirigido al director. */
    public function cotejo(string $id): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $volver = $this->volver($t, 'momento-1');
        [$ev] = $this->evaluar($t);
        if ($ev['hecho'][1]) {
            flash('error', 'El cotejo ya está registrado.');
            redirigir($volver);
        }
        if (!$ev['requisitosCompletos']) {
            $faltan = array_map(static fn(string $c): string => $ev['requisitos'][$c]['nombre'], array_merge($ev['porSubir'], $ev['devueltos'], $ev['fisicosPend'], $ev['sinGenerar']));
            $porValidar = $ev['enRevision'] > 0 ? ' Hay ' . $ev['enRevision'] . ' documento(s) por validar.' : '';
            flash('error', 'Aún no se puede cotejar.' . ($ev['faltanDatos'] ? ' Faltan datos del interesado.' : '') . $porValidar . ($faltan ? ' Pendiente: ' . implode('; ', array_slice($faltan, 0, 5)) . (count($faltan) > 5 ? '…' : '') . '.' : ''));
            redirigir($volver);
        }
        $fecha = self::linea($_POST['fecha'] ?? '', 10);
        $hora = self::linea($_POST['hora'] ?? '', 5);
        if (!self::fechaValida($fecha) || $fecha > date('Y-m-d') || !self::horaValida($hora)) {
            flash('error', 'Indica la fecha (hoy o anterior) y la hora en que recibiste el total de requisitos.');
            redirigir($volver);
        }
        PosgradoRepository::guardarHito((int) $t['id'], 'cotejo', ['fecha' => $fecha, 'hora' => $hora], Auth::id());
        PosgradoRepository::asignarResponsable((int) $t['id'], Auth::id());
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'cotejo', 'Cotejo de originales registrado.');
        Audit::record('posgrado::cotejo', 'correcto', 'Trámite ' . (int) $t['id']);
        $emitido = $this->emitirClave($t, 'cotejo');
        $t = $this->sincronizar((int) $t['id']);
        Aviso::egresado($t, 'Cotejo realizado: ya puedes proponer tu sínodo', 'Apoyo a Grado cotejó tus originales y emitió el oficio de cotejo para tu director. Ya puedes proponer tu sínodo y la fecha de tu examen en tu trámite ' . $t['folio'] . '.');
        flash($emitido ? 'exito' : 'error', $emitido ? 'Cotejo registrado y oficio de cotejo emitido.' : 'Cotejo registrado, pero no se pudo emitir el oficio; intenta emitirlo de nuevo.');
        redirigir($volver);
    }

    /** Renglón «externos dados de alta en Control Escolar». */
    public function externos(string $id): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        if ($this->post('accion') === 'quitar') {
            PosgradoRepository::borrarHito((int) $t['id'], 'externos');
            PosgradoRepository::evento((int) $t['id'], Auth::id(), 'externos', 'Se desmarcó el alta de sinodales externos.', false);
        } else {
            PosgradoRepository::guardarHito((int) $t['id'], 'externos', ['fecha' => date('Y-m-d')], Auth::id());
            PosgradoRepository::evento((int) $t['id'], Auth::id(), 'externos', 'Sinodales externos verificados en Control Escolar.');
        }
        Audit::record('posgrado::externos', 'correcto', 'Trámite ' . (int) $t['id']);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Listo.');
        redirigir($this->volver($t, 'momento-2'));
    }

    /** Programación del examen: cierra el segundo momento. */
    public function programacion(string $id): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $volver = $this->volver($t, 'momento-2');
        [$ev] = $this->evaluar($t);
        if (!$ev['hecho'][1] || $ev['faltanSinodo'] || !$ev['m2validados'] || ($ev['externos'] && !$ev['externosAlta'])) {
            flash('error', 'Para programar se necesita el cotejo, la propuesta completa, la solicitud, el visto bueno y los comprobantes validados' . ($ev['externos'] ? ' y el alta de los sinodales externos' : '') . '.');
            redirigir($volver);
        }
        $fecha = self::linea($_POST['fecha'] ?? '', 10);
        $hora = self::linea($_POST['hora'] ?? '', 5);
        $lugar = self::linea($_POST['lugar'] ?? '', 200);
        $modalidad = in_array($_POST['modalidad'] ?? '', ['presencial', 'virtual'], true) ? (string) $_POST['modalidad'] : 'presencial';
        if (!self::fechaValida($fecha) || $fecha < date('Y-m-d') || !self::horaValida($hora) || $lugar === '') {
            flash('error', 'Indica fecha (hoy o posterior), hora y lugar del examen.');
            redirigir($volver);
        }
        PosgradoRepository::guardarHito((int) $t['id'], 'programacion', ['fecha' => $fecha, 'hora' => $hora, 'lugar' => $lugar, 'modalidad' => $modalidad], Auth::id());
        PosgradoRepository::asignarResponsable((int) $t['id'], Auth::id());
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'programacion', 'Examen programado para el ' . Flujo::fechaLarga($fecha) . ' a las ' . $hora . ' horas.');
        Audit::record('posgrado::programacion', 'correcto', 'Trámite ' . (int) $t['id']);
        $t = $this->sincronizar((int) $t['id']);
        Aviso::egresado($t, 'Tu examen de grado está programado', 'Apoyo a Grado programó tu examen para el ' . Flujo::fechaLarga($fecha) . ' a las ' . $hora . ' horas, ' . ($modalidad === 'virtual' ? 'en modalidad virtual' : 'en ' . $lugar) . '. En cuanto emitan tus invitaciones podrás descargarlas en tu trámite ' . $t['folio'] . '.');
        flash('exito', 'Examen programado. Ahora puedes emitir las invitaciones, el acuse y la notificación al director.');
        redirigir($this->volver($t, 'momento-3'));
    }

    /** Resultado del examen; «aplazado» reabre el segundo momento para reprogramar. */
    public function resultado(string $id): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $volver = $this->volver($t, 'momento-4');
        [$ev] = $this->evaluar($t);
        if (!$ev['hecho'][3]) {
            flash('error', 'El resultado se registra después de validar el acuse firmado por el sínodo.');
            redirigir($volver);
        }
        $resultado = array_key_exists($this->post('resultado'), Catalogo::resultados()) ? $this->post('resultado') : '';
        $fecha = self::linea($_POST['fecha'] ?? '', 10);
        if ($resultado === '' || !self::fechaValida($fecha) || $fecha > date('Y-m-d')) {
            flash('error', 'Elige el resultado e indica la fecha del examen (hoy o anterior).');
            redirigir($volver);
        }
        $definitivo = [];
        foreach ((array) ($_POST['definitivo'] ?? []) as $n) {
            if (is_string($n) && ctype_digit($n) && (int) $n < 7 && trim((string) ($t['datos']['sinodo']['miembros'][(int) $n]['nombre'] ?? '')) !== '') {
                $definitivo[(int) $n] = (int) $n;
            }
        }
        ksort($definitivo);
        if ($resultado !== 'aplazado' && count($definitivo) !== 5) {
            flash('error', 'Marca a los cinco integrantes que conformaron el sínodo definitivo.');
            redirigir($volver);
        }
        if ($resultado === 'aplazado') {
            // Se conserva el antecedente y se abre una nueva programación.
            $datos = $t['datos'];
            $datos['aplazamientos'][] = ['fecha' => $fecha, 'programacion' => $ev['hitos']['programacion']['datos'] ?? [], 'registrado' => date('Y-m-d H:i:s')];
            $datos['sinodo']['fecha'] = '';
            $datos['sinodo']['hora'] = '';
            PosgradoRepository::guardarDatos((int) $t['id'], $datos);
            PosgradoRepository::borrarHito((int) $t['id'], 'programacion');
            PosgradoRepository::borrarHito((int) $t['id'], 'resultado');
            foreach (['solicitud_firmada', 'visto_bueno_firmado', 'comprobantes_sicde', 'acuse_firmado'] as $clave) {
                PosgradoRepository::retirarDocumento((int) $t['id'], $clave);
            }
            PosgradoRepository::evento((int) $t['id'], Auth::id(), 'resultado', 'Examen aplazado (' . Flujo::fechaLarga($fecha) . '). El trámite vuelve a «Propón sínodo y fecha».');
            Audit::record('posgrado::resultado', 'correcto', 'Trámite ' . (int) $t['id'] . ' aplazado');
            $t = $this->sincronizar((int) $t['id']);
            Aviso::egresado($t, 'Tu examen de grado quedó aplazado', 'Tu trámite ' . $t['folio'] . ' sigue abierto. Cuando estés listo, propón de nuevo la fecha de tu examen y recaba otra vez la solicitud, el visto bueno y los comprobantes.');
            flash('exito', 'Resultado registrado: aplazado. El trámite volvió al momento 2 para reprogramar.');
            redirigir($this->volver($t, 'momento-2'));
        }
        PosgradoRepository::guardarHito((int) $t['id'], 'resultado', ['resultado' => $resultado, 'fecha' => $fecha, 'definitivo' => array_values($definitivo)], Auth::id());
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'resultado', 'Resultado del examen: ' . Catalogo::resultados()[$resultado] . '.');
        Audit::record('posgrado::resultado', 'correcto', 'Trámite ' . (int) $t['id'] . ' ' . $resultado);
        $t = $this->sincronizar((int) $t['id']);
        Aviso::egresado($t, '¡Felicidades! Aprobaste tu examen de grado', 'Recuerda registrar tu grado electrónico en el portal de alumnos de Control Escolar en máximo 48 horas y capturar el folio de seguimiento del SICDE en tu trámite ' . $t['folio'] . '.');
        flash('exito', 'Resultado registrado.');
        redirigir($volver);
    }

    /** Expediente integrado: concluye el trámite. */
    public function expediente(string $id): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $volver = $this->volver($t, 'momento-4');
        [$ev] = $this->evaluar($t);
        if (!$ev['aprobado'] || $ev['folioSicde'] === '' || !isset($ev['generados']['integracion'])) {
            flash('error', 'Para integrar el expediente se necesita el resultado aprobatorio, el folio del SICDE del egresado y el formato de integración emitido.');
            redirigir($volver);
        }
        PosgradoRepository::guardarHito((int) $t['id'], 'expediente', ['fecha' => date('Y-m-d')], Auth::id());
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'expediente', 'Expediente integrado. Trámite concluido.');
        Audit::record('posgrado::expediente', 'correcto', 'Trámite ' . (int) $t['id']);
        $t = $this->sincronizar((int) $t['id']);
        Aviso::egresado($t, 'Tu trámite de evaluación de grado concluyó', 'Apoyo a Grado integró tu expediente (' . $t['folio'] . '). ¡Felicidades por tu grado!');
        flash('exito', 'Expediente integrado: el trámite quedó concluido.');
        redirigir($volver);
    }

    // ----------------------------------------------------------------- oficios

    public function emitir(string $id, string $clave): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $def = Catalogo::emitibles()[$clave] ?? null;
        if ($def === null) {
            $this->noEncontrado();
        }
        $volver = $this->volver($t, 'momento-' . $def['momento']);
        $hitos = PosgradoRepository::hitos((int) $t['id']);
        $falta = Formatos::impedimento($clave, $t, $hitos);
        if ($falta !== null) {
            flash('error', $falta);
            redirigir($volver);
        }
        if ($clave === 'no_adeudo' && isset($hitos['cotejo']) && isset(PosgradoRepository::generadosVigentes((int) $t['id'])['no_adeudo'])) {
            // Ya se entregó y se cotejó: se permite reemitir, solo se deja constancia.
            PosgradoRepository::evento((int) $t['id'], Auth::id(), 'oficio', 'Reemisión de la constancia de no adeudo después del cotejo.', false);
        }
        $ok = $this->emitirClave($t, $clave);
        $t = $this->sincronizar((int) $t['id']);
        if ($ok && in_array($clave, ['invitaciones', 'acuse', 'notificacion', 'no_adeudo', 'constancias_revisores', 'constancias_direccion'], true)) {
            Aviso::egresado($t, 'Nuevo documento en tu trámite: ' . $def['nombre'], 'Apoyo a Grado emitió «' . $def['nombre'] . '» en tu trámite ' . $t['folio'] . '. Ya puedes descargarlo.');
        }
        flash($ok ? 'exito' : 'error', $ok ? '«' . $def['nombre'] . '» emitido y guardado en el expediente.' : 'No se pudo emitir el documento. Intenta de nuevo.');
        redirigir($volver);
    }

    /** Genera, guarda y registra un oficio. */
    private function emitirClave(array $t, string $clave): bool
    {
        try {
            [$bytes] = Formatos::generar($clave, $t, PosgradoRepository::hitos((int) $t['id']));
            $nombre = Catalogo::emitibles()[$clave]['nombre'] ?? $clave;
            $gid = Expediente::guardar($t, $clave, $nombre, $bytes, Auth::id());
        } catch (Throwable $e) {
            error_log('Posgrado: no se pudo emitir ' . $clave . ': ' . $e->getMessage());
            return false;
        }
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'oficio', 'Emitido: ' . $nombre . '.');
        Audit::record('posgrado::emitir', 'correcto', 'Trámite ' . (int) $t['id'] . ' · ' . $clave . ' · ' . $gid);
        return true;
    }
}
