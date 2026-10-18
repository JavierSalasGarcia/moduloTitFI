<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\App;
use App\Core\Audit;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\Configuracion;
use App\Modules\Titulacion\RevisionRepository;
use App\Modules\Titulacion\TitulacionRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use RuntimeException;

/** Flujo de revisión de protocolos: recepción, comisión, evaluadores, firma y dictamen. */
final class RevisionProtocolo
{
    // ------------------------------------------------------------------ recepción

    /** El pasante subió su protocolo: abre una ronda (o actualiza la que aún no se envía) y avisa al personal. */
    public static function nuevaRonda(array $tramite, int $docId): void
    {
        $abierta = RevisionRepository::rondaAbierta((int) $tramite['id']);
        if ($abierta && $abierta['estado'] === 'recibida') {
            RevisionRepository::actualizarRonda((int) $abierta['id'], ['documento_id' => $docId]);
            return;
        }
        if ($abierta) {
            RevisionRepository::actualizarRonda((int) $abierta['id'], ['estado' => 'cancelada']);
        }
        $carrera = (string) ($tramite['datos']['datos']['carrera'] ?? '');
        $id = RevisionRepository::crearRonda((int) $tramite['id'], $carrera, $docId);
        $n = (int) (RevisionRepository::ronda($id)['numero'] ?? 1);
        TitulacionRepository::evento((int) $tramite['id'], (int) $tramite['alumno_id'], 'revision', 'Protocolo recibido para revisión (' . $n . 'ª revisión). Titulación lo turnará a la comisión de tu licenciatura.');
        $url = Correo::enlace('titulacion/protocolos');
        foreach (RevisionRepository::correosPersonal((array) App::config('titulacion.departamentos', []), (array) App::config('titulacion.gestores', [])) as $email => $nombre) {
            Correo::enviar($email, $nombre, 'Nuevo protocolo recibido · ' . $tramite['folio'],
                'El pasante ' . ($tramite['datos']['datos']['nombre'] ?? $tramite['alumno_nombre'] ?? '') . ' subió su protocolo (' . $n . 'ª revisión) y está listo para turnarse a la comisión de revisión.',
                'Ver protocolos recibidos', $url, 'protocolo_recibido', (int) $tramite['id']);
        }
    }

    public static function enviarAComision(array $ronda, int $usuarioId, string $carrera): void
    {
        if ($ronda['estado'] !== 'recibida') {
            throw new RuntimeException('Este protocolo ya fue turnado.');
        }
        if (!isset(Catalogo::carreras()[$carrera])) {
            throw new InvalidArgumentException('Elige la licenciatura cuya comisión revisará el protocolo.');
        }
        $encargados = RevisionRepository::encargados($carrera);
        if (!$encargados) {
            throw new RuntimeException('La comisión de ' . Catalogo::carreras()[$carrera]['nombre'] . ' no tiene encargado. Configúralo en Titulación → Estructura.');
        }
        RevisionRepository::actualizarRonda((int) $ronda['id'], ['carrera' => $carrera, 'estado' => 'en_comision', 'enviada_por' => $usuarioId, 'enviada_en' => date('Y-m-d H:i:s')]);
        TitulacionRepository::evento((int) $ronda['tramite_id'], $usuarioId, 'revision', 'Tu protocolo fue turnado a la comisión de ' . Catalogo::carreras()[$carrera]['nombre'] . '.');
        foreach ($encargados as $e) {
            Correo::enviar($e['email'], $e['nombre'], 'Protocolo para asignar a revisión · ' . $ronda['folio'],
                'Titulación turnó a tu comisión (' . Catalogo::carreras()[$carrera]['nombre'] . ') el protocolo de ' . $ronda['alumno_nombre'] . '. Elige a quiénes de la comisión se enviará para su revisión; el plazo es de ' . Calendario::diasPlazo() . ' días hábiles.',
                'Abrir mi comisión', Correo::enlace('titulacion/comision/' . (int) $ronda['id']), 'turno_comision', (int) $ronda['tramite_id']);
        }
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
        $restan = Calendario::restantes(new DateTimeImmutable('today'), new DateTimeImmutable($limite));
        $f = (new DateTimeImmutable($limite))->format('d/m/Y');
        $txt = $recordatorio
            ? 'Recordatorio: tienes pendiente la revisión del protocolo «' . $ronda['titulo'] . '» (' . $ronda['alumno_nombre'] . '). Fecha límite: ' . $f . ($restan < 0 ? ' (vencida)' : ' (' . $restan . ' días hábiles)') . '.'
            : 'Se te asignó la revisión del protocolo «' . $ronda['titulo'] . '» de ' . $ronda['alumno_nombre'] . ' (' . ($ronda['numero']) . 'ª revisión). Fecha límite: ' . $f . ' (' . Calendario::diasPlazo() . ' días hábiles).';
        $txt .= "\n\nEl enlace es personal: no lo compartas. Puedes revisar y redactar tus recomendaciones de forma y de fondo sin iniciar sesión; para firmar necesitas una cuenta verificada con este mismo correo (" . $miembro['email'] . ').';
        return Correo::enviar($miembro['email'], $miembro['nombre'], ($recordatorio ? 'Recordatorio · ' : '') . 'Protocolo por evaluar · ' . $ronda['folio'], $txt, 'Abrir la revisión', self::urlToken($token), $recordatorio ? 'recordatorio' : 'asignacion', (int) $ronda['tramite_id']);
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
            TitulacionRepository::evento((int) $ronda['tramite_id'], $usuarioId, 'revision', 'Tu protocolo está en evaluación. Plazo de la comisión: ' . (new DateTimeImmutable($limite))->format('d/m/Y') . '.');
        }
        return $res;
    }

    public static function recordatorio(array $asig, array $ronda): bool
    {
        if (!in_array($asig['estado'], ['pendiente', 'borrador'], true)) {
            throw new RuntimeException('Esa revisión ya no está pendiente.');
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
            'fecha_entrega' => date('d-m-Y'), 'revision' => (int) $ronda['numero'] >= 2 ? '2' : '1',
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
        $r = Configuracion::resolverPdf('revision_protocolo');
        if ($r === null) {
            throw new RuntimeException('Falta la plantilla «Revisión de protocolo».');
        }
        return PdfFormato::generarCon($r['spec'], $r['plantilla'], $valores);
    }

    /** Genera el PDF, lo firma y devuelve [ruta relativa, huella, tipo]. */
    private static function firmarPdf(array $ronda, string $prefijo, array $valores, string $nombreFirmante, string $etiqueta, ?int $usuarioId, string $codigo, string $nivel): array
    {
        $dir = 'titulacion/' . (int) $ronda['tramite_id'];
        $sufijo = bin2hex(random_bytes(4));
        $sinFirma = FirmaElectronica::almacen() . '/' . $dir . '/tmp-' . $prefijo . '-' . $sufijo . '.pdf';
        $relFinal = $dir . '/' . $prefijo . '-' . $sufijo . '.pdf';
        if (!is_dir(dirname($sinFirma))) {
            mkdir(dirname($sinFirma), 0755, true);
        }
        file_put_contents($sinFirma, self::generarPdf($valores));
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
        [$ruta, $hash, $tipo] = self::firmarPdf($ronda, 'revision-' . (int) $ronda['id'] . '-a' . (int) $asig['id'], $v, (string) $usuario['nombre'], 'Firma', (int) $usuario['id'], $codigo, $nivel);
        RevisionRepository::marcarFirmada((int) $asig['id'], $tipo, (int) $usuario['id'], $ruta, $hash);
        TitulacionRepository::evento((int) $ronda['tramite_id'], (int) $usuario['id'], 'revision', 'Revisión firmada por ' . $asig['miembro_nombre'] . ' (' . ($asig['propuesta'] === 'aprobado' ? 'propone aprobar' : 'propone no aprobar') . ').', false);
        Audit::record('titulacion::revision_firma', 'correcto', 'Asignación ' . (int) $asig['id']);

        $pendientes = array_filter(RevisionRepository::asignaciones((int) $ronda['id']), static fn(array $a): bool => in_array($a['estado'], ['pendiente', 'borrador'], true));
        if (!$pendientes) {
            RevisionRepository::actualizarRonda((int) $ronda['id'], ['estado' => 'por_dictaminar']);
            foreach (RevisionRepository::encargados((string) $ronda['carrera']) as $e) {
                Correo::enviar($e['email'], $e['nombre'], 'Revisiones completas · ' . $ronda['folio'], 'Todos los evaluadores firmaron su revisión del protocolo de ' . $ronda['alumno_nombre'] . '. Ya puedes consolidar y firmar el dictamen.', 'Dictaminar', Correo::enlace('titulacion/comision/' . (int) $ronda['id']), 'listo_dictamen', (int) $ronda['tramite_id']);
            }
        }
    }

    // ------------------------------------------------------------------- dictamen

    /** Texto sugerido para el dictamen consolidado a partir de las revisiones firmadas. */
    public static function observacionesSugeridas(int $rondaId): string
    {
        $partes = [];
        foreach (RevisionRepository::asignaciones($rondaId) as $a) {
            if ($a['estado'] !== 'firmada') {
                continue;
            }
            $t = self::observaciones($a['forma'], $a['fondo']);
            if ($t !== '') {
                $partes[] = $t;
            }
        }
        return implode("\n", $partes);
    }

    public static function dictaminar(array $ronda, array $in, array $usuario, string $codigo): void
    {
        if ($ronda['estado'] !== 'por_dictaminar') {
            throw new RuntimeException('El dictamen se emite cuando todos los evaluadores firmaron su revisión.');
        }
        $dictamen = $in['dictamen'] ?? '';
        $coord = $in['requiere_coord'] ?? '';
        $obs = trim((string) ($in['observaciones'] ?? ''));
        if (!in_array($dictamen, ['aprobado', 'no_aprobado'], true) || !in_array($coord, ['si', 'no'], true)) {
            throw new InvalidArgumentException('Elige el dictamen y si requiere revisión del coordinador.');
        }
        if ($dictamen === 'no_aprobado' && mb_strlen($obs) < 12) {
            throw new InvalidArgumentException('Si no se aprueba, escribe las observaciones que el pasante debe atender.');
        }
        if (mb_strlen($obs) > 1900) {
            throw new InvalidArgumentException('Las observaciones exceden 1,900 caracteres; resúmelas para que quepan en el formato.');
        }
        $v = self::valores($ronda) + [
            'dictamen' => $dictamen, 'requiere_coord' => $coord, 'observaciones' => $obs,
            'firmante' => mb_strtoupper(Formatos::subdirector()),
        ];
        $nivel = FirmaElectronica::nivelRevisores();
        [$ruta, $hash, $tipo] = self::firmarPdf($ronda, 'dictamen-' . (int) $ronda['id'], $v, (string) $usuario['nombre'], 'Firma electrónica', (int) $usuario['id'], $codigo, $nivel);
        RevisionRepository::actualizarRonda((int) $ronda['id'], [
            'estado' => 'dictaminada', 'dictamen' => $dictamen, 'requiere_coord' => $coord === 'si' ? 1 : 0, 'observaciones' => $obs,
            'dictaminado_por' => (int) $usuario['id'], 'dictaminado_en' => date('Y-m-d H:i:s'), 'firma_tipo' => $tipo, 'dictamen_ruta' => $ruta, 'dictamen_hash' => $hash,
        ]);
        if (!empty($ronda['documento_id'])) {
            TitulacionRepository::revisarDocumento(
                (int) $ronda['documento_id'], $dictamen === 'aprobado' ? 'aprobado' : 'rechazado',
                $dictamen === 'aprobado' ? 'Aprobado por la comisión de evaluación de protocolos.' : mb_substr($obs, 0, 900), (int) $usuario['id']
            );
        }
        TitulacionRepository::evento((int) $ronda['tramite_id'], (int) $usuario['id'], 'revision', $dictamen === 'aprobado'
            ? 'La comisión aprobó tu protocolo.' : 'La comisión no aprobó tu protocolo; atiende las observaciones y vuelve a subirlo.');
        if ($coord === 'si') {
            TitulacionRepository::evento((int) $ronda['tramite_id'], (int) $usuario['id'], 'revision', 'El dictamen indica que requiere revisión del coordinador de la carrera.', false);
        }
        Audit::record('titulacion::dictamen', 'correcto', 'Ronda ' . (int) $ronda['id'] . ' ' . $dictamen);
        Correo::enviar((string) $ronda['alumno_email'], (string) $ronda['alumno_nombre'], 'Dictamen de tu protocolo · ' . $ronda['folio'],
            $dictamen === 'aprobado'
                ? 'La comisión de evaluación aprobó tu protocolo. Titulación te hará llegar el oficio de aprobación de tema y modalidad.'
                : 'La comisión de evaluación no aprobó tu protocolo en esta revisión. Consulta el dictamen, atiende las observaciones y vuelve a subirlo.',
            'Ver mi trámite', Correo::enlace('titulacion/licenciatura/tramite/' . (int) $ronda['tramite_id']), 'dictamen', (int) $ronda['tramite_id']);
        if ($coord === 'si') {
            foreach (RevisionRepository::correosPersonal((array) App::config('titulacion.departamentos', []), (array) App::config('titulacion.gestores', [])) as $email => $nombre) {
                Correo::enviar($email, $nombre, 'Dictamen que requiere al coordinador · ' . $ronda['folio'], 'El dictamen del protocolo de ' . $ronda['alumno_nombre'] . ' indica que requiere revisión del coordinador de la carrera.', 'Ver protocolos', Correo::enlace('titulacion/protocolos'), 'requiere_coord', (int) $ronda['tramite_id']);
            }
        }
    }
}
