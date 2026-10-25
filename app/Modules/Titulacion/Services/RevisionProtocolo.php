<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\App;
use App\Core\Audit;
use App\Core\Persona;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\RevisionRepository;
use App\Modules\Titulacion\TitulacionRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/** Flujo de revisión de protocolos: recepción, comisión, evaluadores, firma y dictamen. */
final class RevisionProtocolo
{
    // ------------------------------------------------------------------ recepción

    /**
     * El asesor (y coasesor) firmaron el protocolo: se abre una vuelta de revisión y pasa directo al responsable
     * del comité de la licenciatura. Solo si la licenciatura aún no tiene comité se avisa al personal de Titulación.
     */
    public static function nuevaRonda(array $tramite, int $docId): void
    {
        $abierta = RevisionRepository::rondaAbierta((int) $tramite['id']);
        if ($abierta && $abierta['estado'] === 'recibida') {
            RevisionRepository::actualizarRonda((int) $abierta['id'], ['documento_id' => $docId]);
            $id = (int) $abierta['id'];
        } else {
            if ($abierta) {
                RevisionRepository::actualizarRonda((int) $abierta['id'], ['estado' => 'cancelada']);
            }
            $id = RevisionRepository::crearRonda((int) $tramite['id'], (string) ($tramite['datos']['datos']['carrera'] ?? ''), $docId);
        }
        $ronda = RevisionRepository::ronda($id);
        $n = (int) ($ronda['numero'] ?? 1);
        self::avisarFirmado($tramite, $docId, $n);
        if (RevisionRepository::comisionConfigurada((string) $ronda['carrera'])) {
            self::turnar($ronda, null);
            return;
        }
        TitulacionRepository::evento((int) $tramite['id'], (int) $tramite['alumno_id'], 'revision', 'Protocolo recibido (' . $n . 'ª vuelta). Titulación lo turnará al comité de revisión de tu licenciatura.');
        $url = Correo::enlace('titulacion/protocolos');
        foreach (RevisionRepository::correosPersonal((array) App::config('titulacion.departamentos', []), (array) App::config('titulacion.gestores', [])) as $email => $nombre) {
            Correo::enviar($email, $nombre, 'Protocolo sin comité de revisión · ' . $tramite['folio'],
                'El protocolo de ' . ($tramite['datos']['datos']['nombre'] ?? $tramite['alumno_nombre'] ?? '') . ' ya tiene la firma de su asesor, pero su licenciatura no tiene responsable del comité de revisión. Regístralo en Titulación → Estructura y turna el protocolo.',
                'Ver protocolos recibidos', $url, 'protocolo_recibido', (int) $tramite['id']);
        }
    }

    /** Copia de aviso al pasante y a quienes firmaron como asesores: el protocolo firmado va al comité. */
    private static function avisarFirmado(array $tramite, int $docId, int $n): void
    {
        $titulo = (string) ($tramite['datos']['protocolo']['titulo'] ?? $tramite['titulo'] ?? '');
        $texto = 'El protocolo «' . $titulo . '» ya tiene todas las firmas (pasante y asesoría) y se envió al Comité de Revisión de Protocolos de la licenciatura para su ' . Mensajes::ordinal($n) . ' vuelta de revisión.';
        Correo::enviar((string) $tramite['alumno_email'], (string) ($tramite['datos']['datos']['nombre'] ?? $tramite['alumno_nombre'] ?? ''), 'Protocolo enviado al comité · ' . $tramite['folio'],
            $texto . ' Te avisaremos en tus Notificaciones (y por correo) cuando concluya la revisión.', 'Ver mi protocolo',
            Correo::enlace('titulacion/licenciatura/tramite/' . (int) $tramite['id'] . '?etapa=protocolo'), 'protocolo_enviado', (int) $tramite['id']);
        foreach (FirmasExternas::pendientesDe($docId) as $f) {
            if ($f['estado'] === 'firmada') {
                Correo::enviar((string) $f['email'], ['nombre' => $f['nombre'], 'grado' => $f['grado'], 'genero' => $f['genero']], 'Protocolo enviado al comité · ' . $tramite['folio'],
                    $texto . ' Gracias por su firma.', null, null, 'protocolo_enviado', (int) $tramite['id']);
            }
        }
    }

    /** Respaldo para el personal: turna a mano un protocolo de una licenciatura que no tenía comité. */
    public static function enviarAComision(array $ronda, int $usuarioId, string $carrera): void
    {
        if ($ronda['estado'] !== 'recibida') {
            throw new RuntimeException('Este protocolo ya fue turnado.');
        }
        if (!isset(Catalogo::carreras()[$carrera])) {
            throw new InvalidArgumentException('Elige la licenciatura cuyo comité revisará el protocolo.');
        }
        if (!RevisionRepository::encargados($carrera)) {
            throw new RuntimeException('El comité de ' . Catalogo::carreras()[$carrera]['nombre'] . ' no tiene responsable. Configúralo en Titulación → Estructura.');
        }
        RevisionRepository::actualizarRonda((int) $ronda['id'], ['carrera' => $carrera]);
        self::turnar(RevisionRepository::ronda((int) $ronda['id']), $usuarioId);
    }

    private static function turnar(array $ronda, ?int $usuarioId): void
    {
        $carrera = (string) $ronda['carrera'];
        $nombreCarrera = Catalogo::carreras()[$carrera]['nombre'] ?? $carrera;
        RevisionRepository::actualizarRonda((int) $ronda['id'], ['estado' => 'en_comision', 'enviada_por' => $usuarioId, 'enviada_en' => date('Y-m-d H:i:s')]);
        TitulacionRepository::evento((int) $ronda['tramite_id'], $usuarioId ?? (int) $ronda['alumno_id'], 'revision', 'Tu protocolo está con el responsable del Comité de Revisión de Protocolos de ' . $nombreCarrera . ' (' . (int) $ronda['numero'] . 'ª vuelta).');
        $correccion = (int) $ronda['numero'] > 1;
        foreach (RevisionRepository::encargados($carrera) as $e) {
            Correo::enviar($e['email'], $e['nombre'], ($correccion ? 'Protocolo corregido · ' : 'Protocolo para revisión · ') . $ronda['folio'],
                $correccion
                    ? 'Llegó la versión corregida del protocolo de ' . $ronda['alumno_nombre'] . ' (' . (int) $ronda['numero'] . 'ª vuelta). Determina si atiende las observaciones y apruébalo, o envíalo a una nueva vuelta de revisión; los revisores tendrán de nuevo ' . Calendario::diasPlazo() . ' días hábiles.'
                    : 'Llegó al comité de ' . $nombreCarrera . ' el protocolo de ' . $ronda['alumno_nombre'] . ', firmado por su asesor. Envíalo a ' . self::revisoresPorProtocolo() . ' profesores del comité para su revisión; tendrán ' . Calendario::diasPlazo() . ' días hábiles.',
                'Abrir el protocolo', Correo::enlace('titulacion/comision/' . (int) $ronda['id']), 'turno_comision', (int) $ronda['tramite_id']);
        }
    }

    public static function revisoresPorProtocolo(): int
    {
        return max(1, min(9, (int) \ajuste('titulacion_revisores_protocolo', '3')));
    }

    // --------------------------------------------------------------- evaluadores

    private static function nuevoToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    private static function vence(string $limite): string
    {
        $gracia = max(1, (int) \ajuste('titulacion_token_gracia', '14'));
        return (new DateTimeImmutable($limite))->modify('+' . $gracia . ' days')->format('Y-m-d');
    }

    public static function urlToken(string $token): string
    {
        return Correo::enlace('titulacion/revision/t/' . $token);
    }

    private static function correoAsignacion(array $ronda, array $miembro, string $token, string $limite, bool $recordatorio): bool
    {
        $f = (new DateTimeImmutable($limite))->format('d/m/Y');
        $txt = $recordatorio
            ? 'Le escribimos con todo respeto para recordarle que tiene pendiente la revisión del protocolo «' . $ronda['titulo'] . '» de ' . $ronda['alumno_nombre'] . ', cuyo plazo de ' . Calendario::diasPlazo() . ' días hábiles concluyó el ' . $f . '. '
                . 'Sabemos que sus actividades son muchas; le agradeceremos mucho registrar sus observaciones en cuanto le sea posible, pues el pasante espera el resultado para continuar su trámite. Gracias por su valioso apoyo al Comité de Revisión de Protocolos.'
            : 'Se le asignó la revisión del protocolo «' . $ronda['titulo'] . '» de ' . $ronda['alumno_nombre'] . ' (' . Mensajes::ordinal((int) $ronda['numero']) . ' vuelta). Tiene ' . Calendario::diasPlazo() . ' días hábiles, hasta el ' . $f . '.';
        $txt .= "\n\nAl abrir el enlace puede leer el protocolo en PDF y redactar sus observaciones de forma y de fondo sin iniciar sesión; para firmar su revisión necesita entrar a Traza FI con una cuenta verificada con este mismo correo (" . $miembro['email'] . '). El enlace es personal: no lo comparta.';
        return Correo::enviar($miembro['email'], $miembro['nombre'], ($recordatorio ? 'Recordatorio amable · ' : '') . 'Protocolo por revisar · ' . $ronda['folio'], $txt, 'Abrir el protocolo', self::urlToken($token), $recordatorio ? 'recordatorio' : 'asignacion', (int) $ronda['tramite_id']);
    }

    /** @param int[] $miembroIds @return array{asignados:int,omitidos:int,sin_correo:int} */
    public static function asignar(array $ronda, array $miembroIds, int $usuarioId): array
    {
        if (!in_array($ronda['estado'], ['en_comision', 'en_evaluacion', 'por_dictaminar'], true)) {
            throw new RuntimeException('Este protocolo no está en tu comisión para asignarse.');
        }
        $limite = Calendario::agregarHabiles(new DateTimeImmutable('today'), Calendario::diasPlazo())->format('Y-m-d');
        $existentes = [];
        foreach (RevisionRepository::asignaciones((int) $ronda['id']) as $a) {
            $existentes[(int) $a['miembro_id']] = $a;
        }
        $activos = count(array_filter($existentes, static fn(array $a): bool => $a['estado'] !== 'cancelada'));
        $nuevos = count(array_filter(array_unique(array_map('intval', $miembroIds)), static fn(int $m): bool => !isset($existentes[$m]) || $existentes[$m]['estado'] === 'cancelada'));
        if ($activos + $nuevos > self::revisoresPorProtocolo()) {
            throw new RuntimeException('Cada protocolo se envía a ' . self::revisoresPorProtocolo() . ' revisores; ya tiene ' . $activos . '. Retira a alguien antes de agregar a otra persona.');
        }
        $res = ['asignados' => 0, 'omitidos' => 0, 'sin_correo' => 0];
        foreach (array_unique(array_map('intval', $miembroIds)) as $mid) {
            $m = RevisionRepository::miembro($mid);
            if (!$m || $m['carrera'] !== $ronda['carrera'] || !(int) $m['activo'] || (isset($existentes[$mid]) && $existentes[$mid]['estado'] !== 'cancelada')) {
                $res['omitidos']++;
                continue;
            }
            $token = self::nuevoToken();
            if (isset($existentes[$mid])) {
                \App\Core\Database::ejecutar(
                    "UPDATE tit_revision_asignaciones SET estado = 'pendiente', token_hash = ?, token_vence = ?, fecha_limite = ?, asignada_por = ?, asignada_en = NOW(), propuesta = NULL, requiere_coord = NULL, forma = NULL, fondo = NULL WHERE id = ?",
                    [hash('sha256', $token), self::vence($limite), $limite, $usuarioId, (int) $existentes[$mid]['id']]
                );
            } else {
                RevisionRepository::crearAsignacion((int) $ronda['id'], $mid, hash('sha256', $token), $limite, self::vence($limite), $usuarioId);
            }
            $res['asignados']++;
            if (!self::correoAsignacion($ronda, $m, $token, $limite, false)) {
                $res['sin_correo']++;
            }
        }
        if ($res['asignados'] > 0) {
            RevisionRepository::actualizarRonda((int) $ronda['id'], ['estado' => 'en_evaluacion', 'evaluacion_en' => $ronda['evaluacion_en'] ?: date('Y-m-d H:i:s')]);
            TitulacionRepository::evento((int) $ronda['tramite_id'], $usuarioId, 'revision', 'Tu protocolo está en revisión. Plazo de los revisores: ' . (new DateTimeImmutable($limite))->format('d/m/Y') . '.');
        }
        return $res;
    }

    public static function recordatorio(array $asig, array $ronda): bool
    {
        if (!in_array($asig['estado'], ['pendiente', 'borrador'], true)) {
            throw new RuntimeException('Esa revisión ya no está pendiente.');
        }
        if (Calendario::semaforo((string) $asig['estado'], (string) $asig['fecha_limite']) !== 'rojo') {
            throw new RuntimeException('El recordatorio se envía cuando el plazo de ' . Calendario::diasPlazo() . ' días hábiles ya venció (semáforo en rojo).');
        }
        $token = self::nuevoToken();
        RevisionRepository::rotarToken((int) $asig['id'], hash('sha256', $token), self::vence((string) $asig['fecha_limite']));
        return self::correoAsignacion($ronda, ['nombre' => $asig['miembro_nombre'], 'email' => $asig['miembro_email']], $token, (string) $asig['fecha_limite'], true);
    }

    // ------------------------------------------------------------------ evaluador

    public static function guardar(array $asig, array $in): void
    {
        $propuesta = in_array($in['propuesta'] ?? '', ['aprobado', 'no_aprobado'], true) ? $in['propuesta'] : null;
        $req = ($in['requiere_coord'] ?? '') === 'si' ? 1 : (($in['requiere_coord'] ?? '') === 'no' ? 0 : null);
        RevisionRepository::guardarBorrador((int) $asig['id'], $propuesta, $req, mb_substr(trim((string) ($in['forma'] ?? '')), 0, 2500), mb_substr(trim((string) ($in['fondo'] ?? '')), 0, 2500));
    }

    /** Valores base de «Revisión de protocolo» a partir de la ronda. */
    private static function valores(array $ronda): array
    {
        $datos = json_decode((string) $ronda['tramite_datos'], true) ?: [];
        $p = $datos['datos'] ?? [];
        $c = Catalogo::carreras()[$ronda['carrera']] ?? null;
        return [
            'licenciatura_titulo' => $c ? 'LICENCIATURA DE ' . mb_strtoupper($c['nombre']) : '',
            'siglas' => $c['siglas'][0] ?? '',
            'nombre' => (string) ($p['nombre'] ?? $ronda['alumno_nombre']), 'cuenta' => (string) ($p['cuenta'] ?? ''),
            'titulo' => (string) ($ronda['titulo'] ?? ''), 'modalidad_nombre' => Catalogo::modalidad((string) $ronda['modalidad'])['nombre'] ?? '',
            'fecha_recepcion' => date('d-m-Y', strtotime((string) $ronda['fecha_recepcion'])),
            'fecha_entrega' => date('d-m-Y'), 'revision' => (string) (int) $ronda['numero'], 'folio' => (string) $ronda['folio'],
        ];
    }

    private static function observaciones(?string $forma, ?string $fondo): string
    {
        $partes = [];
        if (trim((string) $forma) !== '') {
            $partes[] = 'FORMA: ' . trim((string) $forma);
        }
        if (trim((string) $fondo) !== '') {
            $partes[] = 'FONDO: ' . trim((string) $fondo);
        }
        return implode(' ', $partes);
    }

    private static function generarPdf(array $valores): string
    {
        return FormatosOficiales::revisionProtocolo(['folio' => $valores['folio'] ?? ''], $valores)[0];
    }

    /** Genera el PDF, lo firma y devuelve [ruta relativa, huella, tipo]. */
    private static function firmarPdf(array $ronda, string $prefijo, array $valores, string $nombreFirmante, string $etiqueta, ?int $usuarioId, string $codigo, string $nivel, ?int $asignacionId = null): array
    {
        $dir = 'titulacion/' . (int) $ronda['tramite_id'];
        $sufijo = bin2hex(random_bytes(4));
        $sinFirma = FirmaElectronica::almacen() . '/' . $dir . '/tmp-' . $prefijo . '-' . $sufijo . '.pdf';
        $relFinal = $dir . '/' . $prefijo . '-' . $sufijo . '.pdf';
        if (!is_dir(dirname($sinFirma))) {
            mkdir(dirname($sinFirma), 0755, true);
        }
        $bytes = self::generarPdf($valores);
        file_put_contents($sinFirma, $bytes);
        try {
            $folio = $ronda['folio'] . ' R' . (int) $ronda['numero'];
            if ($nivel === 'pades') {
                FirmaElectronica::pades((int) $usuarioId, $codigo, $sinFirma, FirmaElectronica::almacen() . '/' . $relFinal, $folio, 'Revisión de protocolo');
                $hash = hash_file('sha256', FirmaElectronica::almacen() . '/' . $relFinal) ?: '';
            } else {
                $hash = FirmaElectronica::sellar($sinFirma, FirmaElectronica::almacen() . '/' . $relFinal, ['etiqueta' => $etiqueta, 'nombre' => $nombreFirmante, 'folio' => $folio]);
            }
        } finally {
            @unlink($sinFirma);
        }
        Verificacion::guardado($bytes, $relFinal, $asignacionId === null ? (int) $ronda['id'] : null, $asignacionId);
        return [$relFinal, $hash, $nivel];
    }

    /** El evaluador firma su revisión: se genera el formato con su dictamen y observaciones. */
    public static function firmar(array $asig, array $ronda, array $usuario, string $codigo): void
    {
        if (!in_array($asig['estado'], ['pendiente', 'borrador'], true)) {
            throw new RuntimeException('Esta revisión ya fue firmada o cancelada.');
        }
        if (empty($asig['propuesta']) || $asig['requiere_coord'] === null) {
            throw new InvalidArgumentException('Indica si se aprueba el protocolo y si requiere revisión del coordinador antes de firmar.');
        }
        $obs = self::observaciones($asig['forma'], $asig['fondo']);
        if ($asig['propuesta'] === 'no_aprobado' && mb_strlen($obs) < 12) {
            throw new InvalidArgumentException('Si no se aprueba, escribe tus recomendaciones de forma y/o de fondo.');
        }
        $v = self::valores($ronda) + [
            'dictamen' => $asig['propuesta'], 'requiere_coord' => (int) $asig['requiere_coord'] === 1 ? 'si' : 'no',
            'observaciones' => $obs, 'firmante' => mb_strtoupper((string) $asig['miembro_nombre']),
        ];
        $nivel = FirmaElectronica::nivelRevisores();
        [$ruta, $hash, $tipo] = self::firmarPdf($ronda, 'revision-' . (int) $ronda['id'] . '-a' . (int) $asig['id'], $v, (string) $usuario['nombre'], 'Firma', (int) $usuario['id'], $codigo, $nivel, (int) $asig['id']);
        RevisionRepository::marcarFirmada((int) $asig['id'], $tipo, (int) $usuario['id'], $ruta, $hash);
        TitulacionRepository::evento((int) $ronda['tramite_id'], (int) $usuario['id'], 'revision', 'Revisión firmada por ' . $asig['miembro_nombre'] . ' (' . ($asig['propuesta'] === 'aprobado' ? 'propone aprobar' : 'propone no aprobar') . ').', false);
        Audit::record('titulacion::revision_firma', 'correcto', 'Asignación ' . (int) $asig['id']);

        $pendientes = array_filter(RevisionRepository::asignaciones((int) $ronda['id']), static fn(array $a): bool => in_array($a['estado'], ['pendiente', 'borrador'], true));
        if (!$pendientes) {
            RevisionRepository::actualizarRonda((int) $ronda['id'], ['estado' => 'por_dictaminar']);
            foreach (RevisionRepository::encargados((string) $ronda['carrera']) as $e) {
                Correo::enviar($e['email'], $e['nombre'], 'Revisiones completas · ' . $ronda['folio'], 'Los revisores terminaron sus observaciones al protocolo de ' . $ronda['alumno_nombre'] . ' (' . Mensajes::ordinal((int) $ronda['numero']) . ' vuelta). Ya aparece en tu tablero para revisar la compilación de observaciones y enviar el dictamen al pasante.', 'Abrir el protocolo', Correo::enlace('titulacion/comision/' . (int) $ronda['id']), 'listo_dictamen', (int) $ronda['tramite_id']);
            }
        }
    }

    // ------------------------------------------------------------------- dictamen

    /** Revisiones firmadas de la vuelta, numeradas como Revisor 1, 2, 3 con sus observaciones de forma y fondo. */
    public static function revisiones(int $rondaId): array
    {
        $salida = [];
        $n = 0;
        foreach (RevisionRepository::asignaciones($rondaId) as $a) {
            if ($a['estado'] !== 'firmada') {
                continue;
            }
            $a['n'] = ++$n;
            $a['texto'] = self::observacionesLegibles($a['forma'], $a['fondo']);
            $salida[] = $a;
        }
        return $salida;
    }

    private static function observacionesLegibles(?string $forma, ?string $fondo): string
    {
        $partes = [];
        if (trim((string) $forma) !== '') {
            $partes[] = "De forma:\n" . trim((string) $forma);
        }
        if (trim((string) $fondo) !== '') {
            $partes[] = "De fondo:\n" . trim((string) $fondo);
        }
        return $partes ? implode("\n\n", $partes) : 'Sin observaciones.';
    }

    /** ¿El responsable puede resolver ya esta vuelta? Tras las revisiones, o directamente si es una versión corregida. */
    public static function puedeDictaminar(array $ronda): bool
    {
        if ($ronda['estado'] === 'por_dictaminar') {
            return true;
        }
        if ($ronda['estado'] !== 'en_comision' || (int) $ronda['numero'] < 2) {
            return false;
        }
        return !array_filter(RevisionRepository::asignaciones((int) $ronda['id']), static fn(array $a): bool => $a['estado'] !== 'cancelada');
    }

    /**
     * El responsable del comité resuelve la vuelta: compila (y edita) las observaciones de cada revisor, envía el mensaje
     * al pasante, firma el dictamen en PDF y, si se aprueba, emite el oficio de aprobación del tema.
     * @param array{dictamen:string,requiere_coord:string,revisores:array<int|string,string>,propias:string,asunto:string,mensaje:string,obs_oficio:string} $in
     */
    public static function dictaminar(array $ronda, array $in, array $usuario, string $codigo): void
    {
        if (!self::puedeDictaminar($ronda)) {
            throw new RuntimeException('El dictamen se emite cuando todos los revisores terminaron su revisión.');
        }
        $dictamen = $in['dictamen'] ?? '';
        $coord = $in['requiere_coord'] ?? 'no';
        if (!in_array($dictamen, ['aprobado', 'no_aprobado'], true) || !in_array($coord, ['si', 'no'], true)) {
            throw new InvalidArgumentException('Elige si el protocolo se aprueba o debe atender observaciones.');
        }
        // Compilación por revisor (con lo que haya editado el responsable) y observaciones propias.
        $bloques = [];
        foreach (self::revisiones((int) $ronda['id']) as $a) {
            $texto = trim(str_replace("\r", '', (string) ($in['revisores'][$a['id']] ?? $a['texto'])));
            $bloques[] = 'Revisor ' . $a['n'] . ":\n" . mb_substr($texto !== '' ? $texto : 'Sin observaciones.', 0, 6000);
        }
        $propias = trim(str_replace("\r", '', (string) ($in['propias'] ?? '')));
        if ($propias !== '') {
            $bloques[] = "Responsable del comité:\n" . mb_substr($propias, 0, 6000);
        }
        $obs = implode("\n\n", $bloques);
        $hayObs = $propias !== '' || array_filter(self::revisiones((int) $ronda['id']), static fn(array $x): bool => $x['texto'] !== 'Sin observaciones.') !== [];
        $mensaje = trim(str_replace("\r", '', (string) ($in['mensaje'] ?? '')));
        $asunto = trim((string) ($in['asunto'] ?? ''));
        if ($dictamen === 'no_aprobado' && (mb_strlen($mensaje) < 20 || $asunto === '')) {
            throw new InvalidArgumentException('Escribe el asunto y el mensaje con las observaciones que el pasante debe atender.');
        }
        if (mb_strlen($mensaje) > 20000) {
            throw new InvalidArgumentException('El mensaje es demasiado largo.');
        }
        $obsOficio = trim(str_replace("\r", '', (string) ($in['obs_oficio'] ?? '')));
        if ($dictamen === 'aprobado' && mb_strlen($obsOficio) > 1500) {
            throw new InvalidArgumentException('Las observaciones del oficio exceden 1,500 caracteres.');
        }
        // El formato impreso admite unos 1,900 caracteres; el texto completo va en el mensaje.
        $obsPdf = mb_strlen($obs) > 1900 ? mb_substr($obs, 0, 1850) . '… (texto completo en Traza FI)' : $obs;
        $v = self::valores($ronda) + [
            'dictamen' => $dictamen, 'requiere_coord' => $coord, 'observaciones' => $obsPdf,
            'firmante' => mb_strtoupper(Persona::tratamiento($usuario)),
        ];
        $nivel = FirmaElectronica::nivelRevisores();
        [$ruta, $hash, $tipo] = self::firmarPdf($ronda, 'dictamen-' . (int) $ronda['id'], $v, (string) $usuario['nombre'], 'Firma electrónica', (int) $usuario['id'], $codigo, $nivel);
        $tramite = TitulacionRepository::obtener((int) $ronda['tramite_id']);
        $vuelta = Mensajes::ordinal((int) $ronda['numero']);
        if ($dictamen === 'aprobado') {
            $asunto = 'Su protocolo fue aprobado';
            $mensaje = Persona::saludo(['nombre' => (string) ($tramite['datos']['datos']['nombre'] ?? $ronda['alumno_nombre'])] + (Persona::porId((int) $ronda['alumno_id']) ?? [])) . ":\n\n"
                . 'Le informo que, en la ' . $vuelta . ' vuelta de revisión, el Comité de Revisión de Protocolos aprobó su protocolo «' . $ronda['titulo'] . '». '
                . 'El oficio de aprobación del tema está en proceso de firmas (Vo. Bo. de la Jefatura de Evaluación Profesional y firma de la Subdirección Académica); le avisaremos cuando esté listo.'
                . ($hayObs ? "\n\nObservaciones de la revisión, para su consideración en el desarrollo del trabajo:\n\n" . $obs : '')
                . "\n\nAtentamente\n" . Persona::tratamiento($usuario) . "\nResponsable del Comité de Revisión de Protocolos";
        }
        RevisionRepository::actualizarRonda((int) $ronda['id'], [
            'estado' => 'dictaminada', 'dictamen' => $dictamen, 'requiere_coord' => $coord === 'si' ? 1 : 0, 'observaciones' => $obs, 'mensaje' => $mensaje,
            'obs_oficio' => $dictamen === 'aprobado' ? $obsOficio : null,
            'dictaminado_por' => (int) $usuario['id'], 'dictaminado_en' => date('Y-m-d H:i:s'), 'firma_tipo' => $tipo, 'dictamen_ruta' => $ruta, 'dictamen_hash' => $hash,
        ]);
        if (!empty($ronda['documento_id'])) {
            TitulacionRepository::revisarDocumento(
                (int) $ronda['documento_id'], $dictamen === 'aprobado' ? 'aprobado' : 'rechazado',
                $dictamen === 'aprobado' ? 'Aprobado por el Comité de Revisión de Protocolos.' : 'Atiende las observaciones de la ' . $vuelta . ' vuelta (ver Notificaciones) y envía la versión corregida.',
                (int) $usuario['id']
            );
        }
        $msjId = Mensajes::enviar((int) $ronda['tramite_id'], (int) $ronda['id'], (int) $usuario['id'], Persona::tratamiento($usuario) . ' · Comité de Revisión de Protocolos', $asunto, $mensaje);
        TitulacionRepository::evento((int) $ronda['tramite_id'], (int) $usuario['id'], 'revision', $dictamen === 'aprobado'
            ? 'El comité aprobó tu protocolo. El oficio de aprobación del tema está en proceso de firmas.'
            : 'Concluyó la ' . $vuelta . ' vuelta de revisión: atiende las observaciones (ver Notificaciones) y envía tu protocolo corregido.');
        if ($coord === 'si') {
            TitulacionRepository::evento((int) $ronda['tramite_id'], (int) $usuario['id'], 'revision', 'El dictamen indica que requiere revisión del coordinador de la carrera.', false);
        }
        Audit::record('titulacion::dictamen', 'correcto', 'Ronda ' . (int) $ronda['id'] . ' ' . $dictamen);
        Correo::enviar((string) $ronda['alumno_email'], (string) $ronda['alumno_nombre'], ($dictamen === 'aprobado' ? 'Protocolo aprobado · ' : 'Revisión de tu protocolo · ') . $ronda['folio'],
            $dictamen === 'aprobado'
                ? 'El Comité de Revisión de Protocolos aprobó tu protocolo. El oficio de aprobación del tema está en proceso de firmas; puedes seguirlo en «Titulación → Estado».'
                : 'Concluyó la ' . $vuelta . ' vuelta de revisión de tu protocolo. Inicia sesión en Traza FI para leer las observaciones del comité en tus Notificaciones; después corrige tu protocolo y vuelve a enviarlo.',
            'Ver la revisión', Correo::enlace('notificaciones/' . $msjId), 'dictamen', (int) $ronda['tramite_id'], false);
        if ($coord === 'si') {
            foreach (RevisionRepository::correosPersonal((array) App::config('titulacion.departamentos', []), (array) App::config('titulacion.gestores', [])) as $email => $nombre) {
                Correo::enviar($email, $nombre, 'Dictamen que requiere al coordinador · ' . $ronda['folio'], 'El dictamen del protocolo de ' . $ronda['alumno_nombre'] . ' indica que requiere revisión del coordinador de la carrera.', 'Ver protocolos', Correo::enlace('titulacion/protocolos'), 'requiere_coord', (int) $ronda['tramite_id']);
            }
        }
        if ($dictamen === 'aprobado' && $tramite) {
            AprobacionTema::emitir($tramite, date('Y-m-d'), $obsOficio !== '' ? $obsOficio : 'Ninguna.', (int) $usuario['id']);
        }
    }
}
