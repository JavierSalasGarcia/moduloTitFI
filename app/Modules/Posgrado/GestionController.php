<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Posgrado\Services\Aviso;
use App\Modules\Posgrado\Services\Firmas;
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
        return Catalogo::requisitos()[$clave]['nombre'] ?? $clave;
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
        if (isset($ev['hitos']['cotejo'])) {
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
        Aviso::egresado($t, 'Cotejo realizado', 'Apoyo a Grado cotejó tus originales y emitió el oficio de cotejo para tu director. En cuanto lo firme la Coordinación de Estudios Avanzados podrás proponer tu sínodo y la fecha de tu examen en tu trámite ' . $t['folio'] . '.');
        flash($emitido ? 'exito' : 'error', $emitido ? 'Cotejo registrado. El oficio de cotejo quedó en firma de la Coordinación.' : 'Cotejo registrado, pero no se pudo emitir el oficio; intenta emitirlo de nuevo.');
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

    /**
     * Programación del examen: cierra el segundo momento. Requiere que hayan firmado el director y los siete integrantes,
     * sin respuestas «No» ni vigencias de firma UAEMéx insuficientes; cualquiera de esas faltas solo se salva con una
     * justificación escrita que queda en la bitácora.
     */
    public function programacion(string $id): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $volver = $this->volver($t, 'momento-2');
        [$ev] = $this->evaluar($t);
        if (!$ev['hecho'][1] || $ev['faltanSinodo'] || !$ev['propuestaEnviada'] || ($ev['externos'] && !$ev['externosAlta'])) {
            flash('error', 'Para programar se necesita el cotejo, la propuesta completa y enviada a firma' . ($ev['externos'] ? ' y el alta de los sinodales externos' : '') . '.');
            redirigir($volver);
        }
        $fecha = self::linea($_POST['fecha'] ?? '', 10);
        $hora = self::linea($_POST['hora'] ?? '', 5);
        $lugar = self::linea($_POST['lugar'] ?? '', 200);
        $modalidad = in_array($_POST['modalidad'] ?? '', ['presencial', 'virtual'], true) ? (string) $_POST['modalidad'] : 'presencial';
        $justificacion = trim((string) preg_replace('/\s+/u', ' ', $this->post('justificacion')));
        if (!self::fechaValida($fecha) || $fecha < date('Y-m-d') || !self::horaValida($hora) || $lugar === '') {
            flash('error', 'Indica fecha (hoy o posterior), hora y lugar del examen.');
            redirigir($volver);
        }
        $faltas = [];
        if (!$ev['firmasM2']) {
            $faltas[] = 'aún no firman todos (director y siete integrantes)';
        }
        foreach ($ev['respuestasNo'] as $r) {
            $faltas[] = $r['nombre'] . ' contestó «No» (' . implode(', ', $r['preguntas']) . ')';
        }
        foreach (Firmas::avisosVigencia(Firmas::estado((int) $t['id']), $fecha) as $a) {
            $faltas[] = $a;
        }
        if ($faltas && mb_strlen($justificacion) < 15) {
            flash('error', 'No se puede programar todavía: ' . implode('; ', array_map(static fn(string $x): string => rtrim($x, '.'), array_slice($faltas, 0, 4))) . '. Si aun así debe programarse, escribe la justificación (mínimo 15 caracteres).');
            redirigir($volver);
        }
        PosgradoRepository::guardarHito((int) $t['id'], 'programacion', ['fecha' => $fecha, 'hora' => $hora, 'lugar' => $lugar, 'modalidad' => $modalidad] + ($faltas ? ['justificacion' => mb_substr($justificacion, 0, 900)] : []), Auth::id());
        PosgradoRepository::asignarResponsable((int) $t['id'], Auth::id());
        if ($faltas) {
            PosgradoRepository::evento((int) $t['id'], Auth::id(), 'justificacion', 'Programación con salvedades (' . implode('; ', $faltas) . '). Justificación: ' . $justificacion, false);
        }
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'programacion', 'Examen programado para el ' . Flujo::fechaLarga($fecha) . ' a las ' . $hora . ' horas.');
        Audit::record('posgrado::programacion', 'correcto', 'Trámite ' . (int) $t['id'] . ($faltas ? ' (con justificación)' : ''));
        $t = $this->sincronizar((int) $t['id']);
        Aviso::egresado($t, 'Tu examen de grado está programado', 'Apoyo a Grado programó tu examen para el ' . Flujo::fechaLarga($fecha) . ' a las ' . $hora . ' horas, ' . ($modalidad === 'virtual' ? 'en modalidad virtual' : 'en ' . $lugar) . '. Después la Coordinación firmará tus invitaciones y cada integrante de tu sínodo recibirá la suya por correo. Trámite ' . $t['folio'] . '.');
        flash('exito', 'Examen programado. Ahora emite las invitaciones, el acuse y la notificación al director; quedan en firma de la Coordinación.');
        redirigir($this->volver($t, 'momento-3'));
    }

    /** Devuelve la propuesta al egresado (p. ej. por una respuesta «No»): se cancelan la solicitud, el visto bueno y sus firmas. */
    public function devolver(string $id): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $volver = $this->volver($t, 'momento-2');
        [$ev] = $this->evaluar($t);
        $comentario = trim((string) preg_replace('/\s+/u', ' ', $this->post('comentario')));
        if ($ev['hecho'][2] || !$ev['propuestaEnviada']) {
            flash('error', 'No hay una propuesta en firma que devolver.');
            redirigir($volver);
        }
        if (mb_strlen($comentario) < 10) {
            flash('error', 'Explica al egresado qué debe ajustar (mínimo 10 caracteres).');
            redirigir($volver);
        }
        foreach (['solicitud', 'visto_bueno'] as $doc) {
            Firmas::cancelarDocumento((int) $t['id'], $doc, Auth::id(), 'Apoyo a Grado devolvió la propuesta');
        }
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'observacion', 'Propuesta de sínodo y fecha devuelta: ' . mb_substr($comentario, 0, 400));
        Audit::record('posgrado::devolver', 'correcto', 'Trámite ' . (int) $t['id']);
        $t = $this->sincronizar((int) $t['id']);
        Aviso::egresado($t, 'Ajusta tu propuesta de sínodo y fecha', "Apoyo a Grado te devolvió tu propuesta con esta observación:\n\n" . $comentario . "\n\nAjústala y envíala de nuevo a firma en tu trámite " . $t['folio'] . '.');
        flash('exito', 'Propuesta devuelta al egresado; se cancelaron las firmas pendientes y hechas.');
        redirigir($volver);
    }

    /** Anula un documento del egresado ya firmado (voto o formato de datos) para que corrija sus datos antes del cotejo. */
    public function anular(string $id, string $doc): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $volver = $this->volver($t, 'momento-1');
        if (!in_array($doc, ['voto', 'datos_interesado'], true)) {
            $this->noEncontrado();
        }
        $justificacion = trim((string) preg_replace('/\s+/u', ' ', $this->post('justificacion')));
        if (isset(PosgradoRepository::hitos((int) $t['id'])['cotejo'])) {
            flash('error', 'Después del cotejo ya no se anulan estos documentos.');
            redirigir($volver);
        }
        if (mb_strlen($justificacion) < 10) {
            flash('error', 'Escribe por qué se anula (mínimo 10 caracteres).');
            redirigir($volver);
        }
        Firmas::cancelarDocumento((int) $t['id'], $doc, Auth::id(), 'anulado por Apoyo a Grado: ' . mb_substr($justificacion, 0, 300));
        Audit::record('posgrado::anular', 'correcto', 'Trámite ' . (int) $t['id'] . ' · ' . $doc);
        $t = $this->sincronizar((int) $t['id']);
        Aviso::egresado($t, 'Puedes corregir tus datos', 'Apoyo a Grado anuló «' . Firmas::nombreDocumento($doc) . '» para que corrijas tus datos: ' . $justificacion . ' Después vuelve a pedir la firma en tu trámite ' . $t['folio'] . '.');
        flash('exito', 'Documento anulado; el egresado puede corregir sus datos y pedir de nuevo la firma.');
        redirigir($volver);
    }

    /** Cierra las invitaciones sin todos los acuses, con justificación escrita en la bitácora. */
    public function cerrarAcuse(string $id): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $volver = $this->volver($t, 'momento-3');
        [$ev] = $this->evaluar($t);
        $justificacion = trim((string) preg_replace('/\s+/u', ' ', $this->post('justificacion')));
        if (!$ev['hecho'][2] || $ev['hecho'][3] || !$ev['invitacionesFirmadas']) {
            flash('error', 'Se cierra después de que la Coordinación firme las invitaciones y el acuse.');
            redirigir($volver);
        }
        if (mb_strlen($justificacion) < 15) {
            flash('error', 'Escribe la justificación para cerrar sin todos los acuses (mínimo 15 caracteres).');
            redirigir($volver);
        }
        PosgradoRepository::guardarHito((int) $t['id'], 'acuse_cierre', ['justificacion' => mb_substr($justificacion, 0, 900)], Auth::id());
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'justificacion', 'Invitaciones cerradas sin todos los acuses. Justificación: ' . $justificacion, false);
        PosgradoRepository::evento((int) $t['id'], Auth::id(), 'acuse', 'Apoyo a Grado cerró las invitaciones al sínodo.');
        Audit::record('posgrado::cerrar_acuse', 'correcto', 'Trámite ' . (int) $t['id']);
        $this->sincronizar((int) $t['id']);
        flash('exito', 'Invitaciones cerradas.');
        redirigir($this->volver($t, 'momento-4'));
    }

    /** Resultado del examen; «aplazado» reabre el segundo momento para reprogramar. */
    public function resultado(string $id): void
    {
        $t = $this->tramite($id, true);
        $this->abierto($t);
        $volver = $this->volver($t, 'momento-4');
        [$ev] = $this->evaluar($t);
        if (!$ev['hecho'][3]) {
            flash('error', 'El resultado se registra después de que el sínodo acuse de recibido sus invitaciones (o de cerrarlas con justificación).');
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
            PosgradoRepository::borrarHito((int) $t['id'], 'acuse_cierre');
            foreach (['solicitud', 'visto_bueno', 'invitaciones', 'acuse', 'notificacion'] as $doc) {
                Firmas::cancelarDocumento((int) $t['id'], $doc, Auth::id(), 'examen aplazado');
            }
            PosgradoRepository::evento((int) $t['id'], Auth::id(), 'resultado', 'Examen aplazado (' . Flujo::fechaLarga($fecha) . '). El trámite vuelve a «Propón sínodo y fecha».');
            Audit::record('posgrado::resultado', 'correcto', 'Trámite ' . (int) $t['id'] . ' aplazado');
            $t = $this->sincronizar((int) $t['id']);
            Aviso::egresado($t, 'Tu examen de grado quedó aplazado', 'Tu trámite ' . $t['folio'] . ' sigue abierto. Cuando estés listo, propón de nuevo la fecha de tu examen y envíala a firma: tu director y tu sínodo firmarán otra vez en línea.');
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
        if (!$ev['aprobado'] || $ev['folioSicde'] === '' || Flujo::estadoFirma($ev['firmas'], 'integracion') !== 'firmado') {
            flash('error', 'Para integrar el expediente se necesita el resultado aprobatorio, el folio del SICDE del egresado y el formato de integración firmado por la Coordinación.');
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
        flash($ok ? 'exito' : 'error', $ok ? '«' . $def['nombre'] . '» emitido: quedó en firma de la Coordinación de Estudios Avanzados.' : 'No se pudo emitir el documento. Intenta de nuevo.');
        redirigir($volver);
    }

    /** Genera, guarda y deja en firma de la Coordinación un oficio (la emisión anterior y sus firmas se cancelan). */
    private function emitirClave(array $t, string $clave): bool
    {
        try {
            Firmas::emitir($t, $clave, Auth::id());
        } catch (Throwable $e) {
            error_log('Posgrado: no se pudo emitir ' . $clave . ': ' . $e->getMessage());
            return false;
        }
        return true;
    }
}
