<?php
declare(strict_types=1);
namespace App\Modules\Reuniones;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Modules\Reuniones\Policies\ReunionPolicy;
use App\Modules\Reuniones\Repositories\ReunionRepository;
use App\Modules\Reuniones\Services\HostingerMailer;
use App\Modules\Reuniones\Services\ReunionBackupService;
use App\Modules\Reuniones\Services\CoordinadorService;
use App\Modules\Reuniones\Services\VisorReunionesService;
use App\Modules\Reuniones\Services\ConfirmacionNotifier;
use App\Modules\Reuniones\Services\IcsReunion;
use App\Modules\Reuniones\Services\ChatNotifier;
final class ReunionesController extends Controller
{
    public function __construct(string $modulo, private readonly ReunionRepository $repositorio = new ReunionRepository()) {
        parent::__construct($modulo);
    }
    public function index(): void
    {
        if (!ReunionPolicy::puedeAccederModulo()) {
            http_response_code(403);
            echo 'Acceso denegado.';
            return;
        }
        $this->vista('index', [
            'titulo' => 'Reuniones',
            'minutas'=>array_map([\App\Modules\Reuniones\Services\MinutasService::class,'contenido'],\App\Modules\Reuniones\Services\MinutasService::listar(Auth::id(),Auth::usuario()['email'])),
            'pendientesDisponibilidad' => (new VisorReunionesService())->pendientes(Auth::id()),
            'reuniones' => (new VisorReunionesService())->listar(Auth::id(), trim((string) ($_GET['q'] ?? '')), (int) ($_GET['categoria'] ?? 0)),
            'categorias' => (new VisorReunionesService())->categorias(Auth::id()),
            'busqueda' => trim((string) ($_GET['q'] ?? '')),
            'categoriaSeleccionada' => (int) ($_GET['categoria'] ?? 0),
            'puedePrecargar' => ReunionPolicy::puedePrecargar(),
            'pestana' => $_GET['tab'] ?? 'convocar',
        ]);
    }
    public function crearCategoria(): void
    {
        if (!ReunionPolicy::puedeAccederModulo()) { http_response_code(403); echo 'Acceso denegado.'; return; }
        $ok = (new VisorReunionesService())->crearCategoria(Auth::id(), trim($this->post('nombre')));
        flash($ok ? 'success' : 'danger', $ok ? 'Categoría creada.' : 'No se pudo crear la categoría.');
        redirigir('reuniones');
    }
    public function nueva(): void
    {
        if (!ReunionPolicy::puedeAccederModulo()) {
            http_response_code(403);
            echo 'Acceso denegado.';
            return;
        }
        $this->vista('nueva', [
            'titulo' => 'Convocar reunión',
            'posgrados' => $this->repositorio->posgrados(),
            'usuario' => $this->repositorio->usuarioActual(),
            'usuarios' => $this->repositorio->usuariosActivos(),
        ]);
    }
    public function coordinadores(): void
    {
        if (!ReunionPolicy::puedeAdministrarCoordinadores()) { http_response_code(403); echo 'Acceso denegado.'; return; }
        $this->vista('coordinadores', [
            'titulo' => 'Coordinadores de posgrado',
            'asignaciones' => (new CoordinadorService())->asignaciones(),
            'usuarios' => (new CoordinadorService())->usuarios(),
        ]);
    }
    public function asignarCoordinador(): void
    {
        if (!ReunionPolicy::puedeAdministrarCoordinadores()) { http_response_code(403); echo 'Acceso denegado.'; return; }
        $ok = (new CoordinadorService())->asignar((int) $this->post('posgrado_id'), (int) $this->post('usuario_id'));
        flash($ok ? 'success' : 'danger', $ok ? 'Coordinador asignado.' : 'La asignación no es válida.');
        redirigir('reuniones/coordinadores');
    }
    public function revocarCoordinador(string $id): void
    {
        if (!ReunionPolicy::puedeAdministrarCoordinadores()) { http_response_code(403); echo 'Acceso denegado.'; return; }
        flash((new CoordinadorService())->revocar((int) $id) ? 'success' : 'danger', 'Asignación actualizada.');
        redirigir('reuniones/coordinadores');
    }
    public function crear(): void
    {
        if (!ReunionPolicy::puedeAccederModulo()) {
            http_response_code(403);
            echo 'Acceso denegado.';
            return;
        }
        $u = Auth::usuario();
        $organizador = $u;
        if (Auth::tieneRol('coordinador') && (int) $this->post('organizador_id') > 0) $organizador = Database::una('SELECT id, nombre, email FROM usuarios WHERE id = ? AND activo = 1', [(int) $this->post('organizador_id')]) ?: $u;
        $asunto = $this->post('asunto');
        $descripcion = $this->post('descripcion');
        $inicio = $this->post('rango_inicio');
        $fin = $this->post('rango_fin');
        $horaInicio = $this->post('hora_inicio', '08:00');
        $horaFin = $this->post('hora_fin', '20:00');
        $posgrado = (int) $this->post('posgrado_id', '0') ?: null;
        if (Auth::tieneRol('coordinador') && (!$posgrado || !Database::una('SELECT id FROM reuniones_coordinadores WHERE usuario_id = ? AND posgrado_id = ? AND activo = 1', [(int) $u['id'], $posgrado]))) { flash('danger', 'Solo puedes precargar reuniones de un posgrado que tengas asignado.'); redirigir('reuniones/nueva'); }
        $horarios = json_decode((string) ($_POST['horarios_json'] ?? '[]'), true);

        if ($asunto === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fin) || $inicio > $fin || !preg_match('/^(08|09|1[0-9]|20):[0-5][0-9]$/', $horaInicio) || !preg_match('/^(08|09|1[0-9]|20):[0-5][0-9]$/', $horaFin)) {
            flash('danger', 'Captura un asunto y un rango de fechas válido.');
            redirigir('reuniones/nueva');
        }

        $horarios = is_array($horarios) ? array_values(array_filter($horarios, static function ($h) use ($inicio, $fin): bool {
            if (!is_array($h) || !isset($h['inicio'], $h['fin'])) return false;
            try { $a = new \DateTimeImmutable((string) $h['inicio']); $b = new \DateTimeImmutable((string) $h['fin']); }
            catch (\Throwable) { return false; }
            return $a->format('Y-m-d') >= $inicio && $b->format('Y-m-d') <= $fin && ($b->getTimestamp() - $a->getTimestamp()) === 3600;
        })) : [];

        if (!$horarios) {
            $base = new \DateTimeImmutable($inicio . ' ' . $horaInicio . ':00');
            if ((new \DateTimeImmutable($inicio . ' ' . $horaFin . ':00'))->getTimestamp() - $base->getTimestamp() !== 3600) { flash('danger', 'La franja inicial debe durar exactamente 60 minutos.'); redirigir('reuniones/nueva'); }
            $horarios[] = ['inicio' => $base->format('Y-m-d H:i:s'), 'fin' => $base->modify('+1 hour')->format('Y-m-d H:i:s')];
        }

        $invitados = [];
        try { $guestIds=array_unique(array_merge((array)($_POST['invitados']??[]),\App\Core\RecipientGroups::recipients('grupos_invitados'))); }
        catch (\InvalidArgumentException $ex) { flash('error',$ex->getMessage()); redirigir('reuniones/nueva'); }
        foreach ($guestIds as $id) {
            $invitado = Database::una('SELECT id, nombre, email FROM usuarios WHERE id = ? AND activo = 1', [(int) $id]);
            if ($invitado && (int) $invitado['id'] !== (int) $u['id']) {
                $invitados[$invitado['email']] = ['usuario_id' => (int) $invitado['id'], 'nombre' => $invitado['nombre'], 'correo' => $invitado['email']];
            }
        }

        foreach (preg_split('/[,\s]+/', $this->post('invitados_externos'), -1, PREG_SPLIT_NO_EMPTY) as $correo) {
            if (filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $invitados[strtolower($correo)] = ['usuario_id' => null, 'nombre' => null, 'correo' => strtolower($correo)];
            }
        }

        $permitidas = ['doc', 'docx', 'ppt', 'pptx', 'pdf'];
        $archivosCarga = [];
        foreach ((array) ($_FILES['archivos']['name'] ?? []) as $i => $nombre) {
            if (($_FILES['archivos']['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $archivo = [
                'name' => $nombre, 'type' => $_FILES['archivos']['type'][$i],
                'tmp_name' => $_FILES['archivos']['tmp_name'][$i], 'error' => $_FILES['archivos']['error'][$i],
                'size' => (int) $_FILES['archivos']['size'][$i],
            ];
            $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
            $mime = $archivo['error'] === UPLOAD_ERR_OK && is_file($archivo['tmp_name']) ? (new \finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']) : ''; $mimesPermitidos = ['doc' => ['application/msword'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'], 'ppt' => ['application/vnd.ms-powerpoint'], 'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'], 'pdf' => ['application/pdf']];
            if ($archivo['error'] !== UPLOAD_ERR_OK || $archivo['size'] > 5 * 1024 * 1024 || !in_array($ext, $permitidas, true) || !in_array($mime, $mimesPermitidos[$ext] ?? [], true)) {
                flash('danger', 'Todos los archivos deben ser válidos, pesar máximo 5 MB y usar doc, docx, ppt, pptx o pdf.');
                redirigir('reuniones/nueva');
            }
            $archivosCarga[] = $archivo;
        }
        $rutasCreadas = [];
        try {
            $id = $this->repositorio->crear([
                'asunto' => $asunto, 'descripcion' => $descripcion, 'creador_id' => $u['id'],
                'organizador_id' => $organizador['id'], 'organizador_correo' => $organizador['email'], 'organizador_nombre' => $organizador['nombre'],
                'posgrado_id' => $posgrado, 'estado' => Auth::tieneRol('coordinador') ? 'precargada' : 'borrador', 'rango_inicio' => $inicio, 'rango_fin' => $fin,
                'hora_inicio' => $horaInicio . ':00', 'hora_fin' => $horaFin . ':00', 'invitados' => array_values($invitados),
                'horarios' => $horarios,
            ]);
            foreach ($archivosCarga as $archivo) {
                $ruta = guardar_archivo_datos($archivo, 'reuniones/' . $id, $permitidas);
                $rutasCreadas[] = $ruta;
                $this->repositorio->registrarArchivo($id, (int) $u['id'], $archivo, $ruta);
            }
            flash('success', "Borrador de reunión #{$id} creado.");
            redirigir('reuniones/' . $id);
        } catch (\Throwable $e) {
            foreach (($rutasCreadas ?? []) as $ruta) { $fisica = rtrim((string) \App\Core\App::config('archivos.storage'), '/\\') . '/' . ltrim($ruta, '/\\'); if (is_file($fisica)) @unlink($fisica); }
            flash('danger', 'No se pudo crear la reunión.');
            redirigir('reuniones/nueva');
        }
    }
    public function detalle(string $id): void
    {
        $reunion = $this->repositorio->detalle((int) $id, Auth::id());
        if (!$reunion) { http_response_code(404); echo 'Reunión no encontrada.'; return; }
        $this->vista('detalle', ['titulo' => 'Reunión #' . $reunion['id'], 'reunion' => $reunion, 'categorias' => (new VisorReunionesService())->categorias(Auth::id())]);
    }
    public function descargarIcs(string $id): never { $r = $this->repositorio->detalle((int) $id, Auth::id()); if (!$r || !$r['fecha_confirmada']) { http_response_code(404); exit('Calendario no disponible.'); } header('Content-Type: text/calendar; charset=utf-8'); header('Content-Disposition: attachment; filename=reunion-' . (int) $id . '.ics'); echo (new IcsReunion())->contenido($r); exit; }
    public function guardarDisponibilidad(string $id): void
    {
        $reunion = $this->repositorio->detalle((int) $id, Auth::id());
        if (!$reunion || in_array($reunion['estado'], ['finalizada', 'cancelada'], true)) { http_response_code(403); echo 'No se puede modificar esta reunión.'; return; }
        $estado = $_POST['estado'] ?? '';
        if (!in_array($estado, ['disponible', 'no_disponible'], true)) { redirigir('reuniones/' . (int) $id); }
        $this->repositorio->guardarDisponibilidad((int) ($_POST['horario_id'] ?? 0), (int) $reunion['participante_id'], $estado, (int) $id);
        $datos = $this->repositorio->datosNotificacionDisponibilidad((int) $id, (int) $reunion['participante_id']);
        $ok = $datos ? (new HostingerMailer())->enviar($datos['organizador_correo'], 'Disponibilidad actualizada: ' . $datos['asunto'], "{$datos['nombre']} actualizó su disponibilidad en la reunión #{$datos['id']}.") : false;
        if ($datos) $this->repositorio->registrarNotificacion((int) $id, $datos['organizador_correo'], $ok ? 'enviado' : 'fallido', $ok ? null : 'No se pudo enviar la notificación.', 'disponibilidad');
        flash('success', 'Disponibilidad actualizada.');
        redirigir('reuniones/' . (int) $id);
    }
    public function asignarCategoria(string $id): void
    {
        if (!ReunionPolicy::puedeAccederModulo()) { http_response_code(403); echo 'Acceso denegado.'; return; }
        $ok = (new VisorReunionesService())->asignar(Auth::id(), (int) $id, (int) $this->post('categoria_id'));
        flash($ok ? 'success' : 'danger', $ok ? 'Reunión categorizada.' : 'No se pudo asignar la categoría.');
        redirigir('reuniones/' . (int) $id);
    }
    public function confirmarHorario(string $id): void
    {
        $reunion = $this->repositorio->detalle((int) $id, Auth::id());
        if (!$reunion || (int) $reunion['organizador_id'] !== Auth::id()) {
            http_response_code(403); echo 'Solo el organizador puede confirmar el horario.'; return;
        }
        $ok = $this->repositorio->confirmarHorario((int) $id, Auth::id(), isset($_POST['horario_ids']) ? array_map('intval', (array) $_POST['horario_ids']) : (int) ($_POST['horario_id'] ?? 0));
        if ($ok) (new ConfirmacionNotifier())->enviar($reunion, (int) $id, $this->repositorio);
        flash($ok ? 'success' : 'danger', $ok ? 'Horario confirmado.' : 'El horario no es válido.');
        redirigir('reuniones/' . (int) $id);
    }
    public function enviarConvocatoria(string $id): void
    {
        $reunion = $this->repositorio->detalle((int) $id, Auth::id());
        if (!$reunion || (int) $reunion['organizador_id'] !== Auth::id()) { http_response_code(403); echo 'Solo el organizador puede convocar.'; return; }
        $participantes = $this->repositorio->participantesParaConvocatoria((int) $id, Auth::id());
        $mailer = new HostingerMailer();
        $enviadas = 0;
        foreach ($participantes as $participante) {
            $nombre = $participante['nombre'] ?: 'Invitado';
            $token = $this->repositorio->generarTokenExterno((int) $participante['id']);
            $enlace = url('reuniones/invitacion/' . rawurlencode($token));
            $texto = "Hola {$nombre},\n\nHas sido invitado a la reunión #{$reunion['id']}: {$reunion['asunto']}.\n\nResponde tu disponibilidad aquí: {$enlace}\n\nDescripción:\n" . ($reunion['descripcion'] ?? '');
            $ok = $mailer->enviar($participante['correo'], 'Invitación a reunión: ' . $reunion['asunto'], $texto);
            $this->repositorio->registrarNotificacion((int) $id, $participante['correo'], $ok ? 'enviado' : 'fallido', $ok ? null : 'Hostinger no confirmó el envío.');
            if ($ok) $enviadas++;
            $this->repositorio->marcarInvitacionEnviada((int) $participante['id'], $ok);
        }
        $this->repositorio->marcarConvocada((int) $id, Auth::id());
        flash($enviadas === count($participantes) ? 'success' : 'warning', "Convocatoria procesada: {$enviadas} de " . count($participantes) . ' correos enviados.');
        redirigir('reuniones/' . (int) $id);
    }
    public function revocarInvitacionExterna(string $id, string $participanteId): void { $ok = $this->repositorio->revocarTokenExterno((int) $id, (int) $participanteId, Auth::id()); flash($ok ? 'success' : 'danger', $ok ? 'Invitación externa revocada.' : 'No se pudo revocar la invitación.'); redirigir('reuniones/' . (int) $id); }
    public function invitacionExterna(string $token): void
    {
        $invitacion = $this->repositorio->horariosExternos($token);
        if (!$invitacion) { http_response_code(404); echo 'La invitación no existe o expiró.'; return; }
        $invitacion['mensajes'] = $this->repositorio->mensajesExternos($token);
        $this->vista('externa', ['titulo' => 'Invitación a reunión', 'invitacion' => $invitacion, 'token' => $token], 'auth');
    }
    public function enviarMensajeExterno(string $token): void { $ok = $this->repositorio->guardarMensajeExterno($token, (string) ($_POST['contenido'] ?? ''), isset($_POST['urgente']) ? 'urgente' : 'normal'); if (!$ok) { http_response_code(400); exit('No se pudo guardar el mensaje.'); } redirigir('reuniones/invitacion/' . rawurlencode($token)); }
    public function guardarDisponibilidadExterna(string $token): void
    {
        $ok = $this->repositorio->guardarDisponibilidadExterna($token, (int) ($_POST['horario_id'] ?? 0), (string) ($_POST['estado'] ?? ''));
        if (!$ok) { http_response_code(400); echo 'No se pudo guardar la disponibilidad.'; return; }
        $datos = $this->repositorio->horariosExternos($token);
        $enviado = $datos ? (new HostingerMailer())->enviar($datos['organizador_correo'], 'Disponibilidad actualizada: ' . $datos['asunto'], "{$datos['correo']} actualizó su disponibilidad en la reunión #{$datos['id']}.") : false;
        if ($datos) $this->repositorio->registrarNotificacion((int) $datos['id'], $datos['organizador_correo'], $enviado ? 'enviado' : 'fallido', $enviado ? null : 'No se pudo enviar la notificación.', 'disponibilidad');
        redirigir('reuniones/invitacion/' . rawurlencode($token));
    }
    public function descargarIcsPublico(string $token): never { $r = $this->repositorio->horariosExternos($token); if (!$r || !$r['fecha_confirmada']) { http_response_code(404); exit('Calendario no disponible.'); } header('Content-Type: text/calendar; charset=utf-8'); header('Content-Disposition: attachment; filename=reunion-' . (int) $r['id'] . '.ics'); echo (new IcsReunion())->contenido($r); exit; }
    public function enviarMensaje(string $id): void
    {
        $reunion = $this->repositorio->detalle((int) $id, Auth::id());
        $contenido = $this->post('contenido');
        $tipo = $_POST['tipo'] ?? 'normal';
        if (!$reunion || $contenido === '' || !in_array($tipo, ['normal', 'urgente'], true) || in_array($reunion['estado'], ['finalizada', 'cancelada'], true)) { http_response_code(400); echo 'Mensaje no válido.'; return; }
        $mensajeId = $this->repositorio->guardarMensaje((int) $id, (int) $reunion['participante_id'], $contenido, $tipo);
        (new ChatNotifier())->enviar((int) $id, Auth::id(), $reunion['asunto'], $contenido, $this->repositorio, $tipo);
        flash('success', 'Mensaje guardado' . ($tipo === 'urgente' ? ' y marcado como urgente.' : '.'));
        redirigir('reuniones/' . (int) $id);
    }
    public function subirArchivoComplementario(string $id): void
    {
        $reunion = $this->repositorio->detalle((int) $id, Auth::id());
        if (!$reunion || in_array($reunion['estado'], ['finalizada', 'cancelada'], true)) { http_response_code(403); echo 'No se pueden subir archivos.'; return; }
        $archivo = $_FILES['archivo'] ?? null;
        $permitidas = ['doc', 'docx', 'ppt', 'pptx', 'pdf'];
        $ext = strtolower(pathinfo((string) ($archivo['name'] ?? ''), PATHINFO_EXTENSION));
        $mime = $archivo && $archivo['error'] === UPLOAD_ERR_OK && is_file($archivo['tmp_name']) ? (new \finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']) : ''; $mimes = ['doc' => ['application/msword'], 'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'], 'ppt' => ['application/vnd.ms-powerpoint'], 'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'], 'pdf' => ['application/pdf']];
        if (!$archivo || $archivo['error'] !== UPLOAD_ERR_OK || $archivo['size'] > 5 * 1024 * 1024 || !in_array($ext, $permitidas, true) || !in_array($mime, $mimes[$ext] ?? [], true)) { flash('danger', 'Archivo no válido.'); redirigir('reuniones/' . (int) $id); }
        $ruta = guardar_archivo_datos($archivo, 'reuniones/' . (int) $id, $permitidas);
        $this->repositorio->registrarArchivoComplementario((int) $id, Auth::id(), $archivo, $ruta);
        flash('success', 'Archivo añadido al expediente.');
        redirigir('reuniones/' . (int) $id);
    }
    public function finalizar(string $id): void
    {
        $reunion = $this->repositorio->detalle((int) $id, Auth::id());
        if (!$reunion || (int) $reunion['organizador_id'] !== Auth::id()) { http_response_code(403); echo 'Solo el organizador puede finalizar.'; return; }
        $storage = rtrim((string) \App\Core\App::config('archivos.storage'), '/\\');
        $contenido = "Reunión #{$id}\nAsunto: {$reunion['asunto']}\n\nCHAT\n";
        foreach ($reunion['mensajes'] as $mensaje) $contenido .= '[' . $mensaje['creado_en'] . '] ' . ($mensaje['nombre'] ?: $mensaje['correo']) . ': ' . $mensaje['contenido'] . "\n";
        try {
            $respaldo = (new ReunionBackupService())->generar($storage, (int) $id, $reunion);
            $rutaRelativa = $respaldo['ruta'];
        } catch (\Throwable $e) {
            $this->repositorio->auditar((int) $id, Auth::id(), 'error_generacion_zip', ['error' => $e->getMessage()]);
            http_response_code(500); echo 'No se pudo generar el respaldo del expediente.'; return;
        }
        $this->repositorio->finalizarConRespaldo((int) $id, Auth::id(), $rutaRelativa, $respaldo['hash']);
        flash('success', 'Asunto finalizado y respaldo ZIP generado.');
        redirigir('reuniones/' . (int) $id);
    }
    public function descargarRespaldo(string $id): never { $ruta = $this->repositorio->puedeDescargarRespaldo((int) $id, Auth::id()); if (!$ruta) { http_response_code(404); exit('Respaldo no disponible.'); } $fisica = rtrim((string) \App\Core\App::config('archivos.storage'), '/\\') . '/' . ltrim($ruta, '/\\'); $hash = $this->repositorio->hashRespaldo((int) $id); if (!$hash || !is_file($fisica) || !hash_equals($hash, (string) hash_file('sha256', $fisica))) { http_response_code(409); exit('El respaldo no supera la verificación de integridad.'); } descargar_archivo($ruta); }
    public function descargarArchivo(string $id, string $archivoId): never { $ruta = $this->repositorio->archivoDescargable((int) $id, (int) $archivoId, Auth::id()); if (!$ruta) { http_response_code(404); exit('Archivo no disponible.'); } descargar_archivo($ruta); }
}
