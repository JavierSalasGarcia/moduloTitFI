<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/datos.php';
require_once __DIR__ . '/lib/plantilla.php';
require_once __DIR__ . '/lib/correo.php';
require_once __DIR__ . '/lib/push.php';

date_default_timezone_set((string) (config()['zona'] ?? 'America/Mexico_City'));

$camino = trim((string) ($_GET['r'] ?? ($_SERVER['PATH_INFO'] ?? '')), '/');
$partes = $camino === '' ? [] : explode('/', $camino);
$metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if (($partes[0] ?? '') === 'api') {
    require __DIR__ . '/api.php';
    exit;
}
if (($partes[0] ?? '') === 'admin') {
    require __DIR__ . '/admin.php';
    exit;
}

// --- avisos --------------------------------------------------------------

function aviso(string $titulo, string $texto, ?array $dep = null, int $codigo = 200): void
{
    http_response_code($codigo);
    render('mensaje', ['dep' => $dep, 'titulo_aviso' => $titulo, 'texto' => $texto],
        ['titulo' => $titulo]);
    exit;
}

function dependencia_o_404(string $clave): array
{
    $dep = dependencia($clave);
    if ($dep === null) {
        aviso('No encontramos esa oficina',
            'Revisa el enlace o el código QR que escaneaste.', null, 404);
    }
    return $dep;
}

// --- subida de archivos --------------------------------------------------

const EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx', 'xls', 'xlsx', 'txt'];
const MAX_BYTES = 8388608;      // 8 MB por archivo
const MAX_ARCHIVOS = 5;

function guardar_subidas(string $carpeta): array
{
    if (empty($_FILES['archivos']['name'][0])) {
        return [];
    }
    $destino = carpeta_subidas() . '/' . $carpeta;
    if (!is_dir($destino) && !mkdir($destino, 0770, true) && !is_dir($destino)) {
        return [];
    }
    $guardados = [];
    $nombres = (array) $_FILES['archivos']['name'];
    foreach ($nombres as $indice => $nombre) {
        if (count($guardados) >= MAX_ARCHIVOS) {
            break;
        }
        if ((int) $_FILES['archivos']['error'][$indice] !== UPLOAD_ERR_OK) {
            continue;
        }
        $bytes = (int) $_FILES['archivos']['size'][$indice];
        $extension = mb_strtolower(pathinfo((string) $nombre, PATHINFO_EXTENSION));
        if ($bytes <= 0 || $bytes > MAX_BYTES || !in_array($extension, EXTENSIONES, true)) {
            continue;
        }
        $limpio = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $nombre) ?: 'archivo';
        $ruta = $destino . '/' . substr(u_token(4), 0, 6) . '-' . mb_substr($limpio, 0, 80);
        if (move_uploaded_file((string) $_FILES['archivos']['tmp_name'][$indice], $ruta)
            || rename((string) $_FILES['archivos']['tmp_name'][$indice], $ruta)) {
            $guardados[] = [
                'nombre' => mb_substr((string) $nombre, 0, 160),
                'ruta' => 'subidas/' . $carpeta . '/' . basename($ruta),
                'tipo' => (string) ($_FILES['archivos']['type'][$indice] ?? ''),
                'bytes' => $bytes,
            ];
        }
    }
    return $guardados;
}

// --- rutas ---------------------------------------------------------------

if (!$partes) {
    render('inicio', ['dep' => null, 'lista' => dependencias()], ['titulo' => 'Citas']);
    exit;
}

// Mi turno: ver y cancelar.
if ($partes[0] === 't' && isset($partes[1])) {
    $turno = turno_por_token($partes[1]);
    if ($turno === null) {
        aviso('Ese turno ya no existe', 'Es posible que el enlace sea de otro día.', null, 404);
    }
    $dep = dependencia_id((int) $turno['dependencia_id']);
    header('Cache-Control: private, no-store');
    header('Referrer-Policy: no-referrer');
    if (isset($partes[2])) {
        if ($metodo!=='GET' || $partes[2]!=='minuta' || count($partes)!==4 || !ctype_digit($partes[3])) {
            aviso('Enlace no válido','Revisa el enlace de tu turno.',$dep,404);
        }
        $registro=minuta_del_enlace($turno['token'],(int)$partes[3]);
        $archivo=$registro && $registro['archivo']!=='' ? realpath(ruta_subida($registro['archivo'])) : false;
        $base=realpath(carpeta_subidas());
        if (!$archivo || !$base || !str_starts_with($archivo,$base.DIRECTORY_SEPARATOR) || !is_file($archivo)) {
            aviso('Minuta no disponible','Ese archivo no pertenece a tu turno o no está disponible.',$dep,404);
        }
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: application/octet-stream');
        header("Content-Disposition: attachment; filename=\"minuta.".pathinfo($archivo,PATHINFO_EXTENSION)."\"; filename*=UTF-8''".rawurlencode($registro['nombre_archivo']));
        readfile($archivo);
        exit;
    }
    if ($metodo === 'POST' && csrf_valido() && ($_POST['accion'] ?? '') === 'cancelar') {
        cancelar_turno($turno);
        u_redirigir(ruta('t/' . $turno['token']));
    }
    $estado = fila_estado($dep);
    $mio = null;
    $adelante = 0;
    foreach ($estado['turnos'] as $fila) {
        if ((int) $fila['id'] === (int) $turno['id']) {
            $mio = $fila;
            break;
        }
        if ($fila['estado'] === 'espera') {
            $adelante++;
        }
    }
    render('mi_turno', ['dep' => $dep, 'turno' => $mio ?? $turno, 'estado' => $estado,
                        'adelante' => $adelante], ['titulo' => 'Tu turno', 'ocultar_plan' => true]);
    exit;
}

// Mi cita: ver y cancelar.
if ($partes[0] === 'c' && isset($partes[1])) {
    $cita = cita_por_token($partes[1]);
    if ($cita === null) {
        aviso('Esa solicitud ya no existe', 'Revisa el enlace que recibiste por correo.', null, 404);
    }
    $dep = dependencia_id((int) $cita['dependencia_id']);
    if ($metodo === 'POST' && csrf_valido() && ($_POST['accion'] ?? '') === 'cancelar') {
        cancelar_cita($cita);
        u_redirigir(ruta('c/' . $cita['token']));
    }
    render('mi_cita', ['dep' => $dep, 'cita' => cita_por_token($partes[1]),
                       'adjuntos' => adjuntos_de((int) $cita['id'])], ['titulo' => 'Tu solicitud']);
    exit;
}

$dep = dependencia_o_404($partes[0]);
$seccion = $partes[1] ?? '';
if ($seccion==='identidad' && $metodo==='POST') fila_consultar_identidad($dep);

if ($seccion === 'fila') {
    render('fila', ['dep' => $dep, 'estado' => fila_estado($dep)], ['titulo' => 'La fila de hoy']);
    exit;
}

if ($seccion === 'pantalla') {
    render('pantalla', ['dep' => $dep, 'estado' => fila_estado($dep)],
        ['titulo' => 'Turnos', 'desnudo' => true]);
    exit;
}

if ($seccion === 'estado.json') {
    $estado = fila_estado($dep);
    $espera = $estado['espera']; // El kiosco rota páginas sin ocultar turnos posteriores.
    u_json([
        'ahora' => $estado['ahora']->format('H:i'),
        'disponible' => $estado['atencion'] === 'atendiendo',
        'abierta' => $estado['abierta'],
        'atencion' => $estado['atencion'],
        'pausa_manual' => !empty($estado['pausa_manual']),
        'leyenda' => leyenda_atencion($estado),
        'leyenda_publica' => leyenda_publica_atencion($estado),
        'proxima_atencion' => $estado['proxima_atencion']?->format('Y-m-d H:i:s'),
        'proxima_atencion_texto' => u_proxima_atencion_breve($estado['proxima_atencion'],$estado['ahora']),
        'disponible_hasta' => u_hora($estado['disponible_hasta']),
        'no_disponible_hasta' => u_hora($estado['no_disponible_hasta']),
        'mensaje' => $estado['motivo_cierre'],
        'nombres' => false,
        'actual' => $estado['actual'] ? [
            'folio' => folio_publico($dep,$estado['actual']),
            'directa' => es_atencion_directa($estado['actual']),
        ] : null,
        'siguientes' => array_map(fn($t) => [
            'folio' => folio_publico($dep,$t),
            'hora' => $t['estimado'] ? u_hora($t['estimado']) : null,
        ], $espera),
        // El monitor de la entrada usa el horario para prender y apagar la pantalla.
        'horarios' => array_map(fn($h) => [
            'dia' => (int) $h['dia'], 'inicio' => $h['inicio'], 'fin' => $h['fin'],
        // Aunque el día esté inhabilitado, conserva su ventana de encendido:
        // la pantalla debe anunciar la próxima atención, no quedar apagada.
        ], horarios_configurados((int) $dep['id'])),
    ]);
    exit;
}

// Reagendar una cita cancelada o rechazada, sin volver a capturar nada.
if ($seccion === 'reagendar' && isset($partes[2])) {
    $previa = cita_por_token($partes[2]);
    if ($previa === null || (int) $previa['dependencia_id'] !== (int) $dep['id']) {
        aviso('Esa solicitud ya no existe', 'Revisa el enlace que recibiste por correo.', $dep, 404);
    }
    if (!puede_reagendarse($previa)) {
        aviso('Esa solicitud sigue vigente',
            'Solo se reagendan las citas canceladas o no confirmadas. Consulta la tuya con el '
            . 'enlace que recibiste.', $dep);
    }

    $minutos = (int) $previa['minutos'];
    $libres = huecos($dep, $minutos);

    if ($metodo === 'POST') {
        if (!csrf_valido()) {
            aviso('La página caducó', 'Vuelve a abrir el enlace del correo.', $dep, 400);
        }
        $elegida = (string) ($_POST['hora'] ?? '');
        $valida = false;
        foreach ($libres as $dia) {
            foreach ($dia['opciones'] as $opcion) {
                if ($opcion->format('Y-m-d H:i') === $elegida) {
                    $valida = true;
                    break 2;
                }
            }
        }
        if (!$valida) {
            render('reagendar', ['dep' => $dep, 'cita' => $previa, 'huecos' => $libres,
                                 'adjuntos' => adjuntos_de((int) $previa['id']),
                                 'error' => 'Esa hora ya no está libre. Elige otra de la lista.'],
                ['titulo' => 'Elegir otra fecha']);
            exit;
        }
        try { $nueva = reagendar_cita($previa, $elegida . ':00'); }
        catch (DomainException $e) {
            render('reagendar',['dep'=>$dep,'cita'=>$previa,'huecos'=>$libres,
                'adjuntos'=>adjuntos_de((int)$previa['id']),'error'=>$e->getMessage()],['titulo'=>'Elegir otra fecha']);
            exit;
        }
        correo_cita_solicitada($dep, $nueva, url_absoluta('c/' . $nueva['token']));
        u_redirigir(ruta('c/' . $nueva['token']));
    }

    render('reagendar', ['dep' => $dep, 'cita' => $previa, 'huecos' => $libres,
                         'adjuntos' => adjuntos_de((int) $previa['id']), 'error' => ''],
        ['titulo' => 'Elegir otra fecha']);
    exit;
}

// Horas libres para agendar, en JSON, cuando cambia la duracion elegida.
if (in_array($seccion, ['', 'turno'], true) && $metodo === 'GET' && isset($_GET['huecos'])) {
    $minutos = (int) $_GET['huecos'];
    if (!in_array($minutos, minutos_de_cita($dep), true)) {
        $minutos = minutos_de_cita($dep)[0];
    }
    u_json(['dias' => array_map(fn($dia) => [
        'etiqueta' => u_fecha_larga($dia['dia']->setTime(0, 0)),
        'opciones' => array_map(fn($o) => ['valor' => $o->format('Y-m-d H:i'), 'hora' => u_hora($o)],
            $dia['opciones']),
    ], huecos($dep, $minutos))]);
    exit;
}

// La entrada de la oficina es el kiosco; el QR abre el formulario en el celular.
if ($seccion === '' && $metodo === 'GET') {
    render('pantalla', ['dep'=>$dep, 'estado'=>fila_estado($dep)],
        ['titulo'=>'Fila', 'desnudo'=>true]);
    exit;
}

// Paso 1: formulario público de turno o de solicitud de reunion.
if ($seccion === 'turno' && $metodo === 'GET') {
    if (($_GET['olvidar']??'')==='1') {
        fila_olvidar_correo();
        u_redirigir(ruta($dep['clave'].'/turno'.((($_GET['tipo']??'')==='cita')?'?tipo=cita':'')));
    }
    $correoRecordado=fila_correo_recordado();
    if ($correoRecordado!==''&&!u_email_valido($correoRecordado,(string)$dep['dominio_correo'])) $correoRecordado='';
    $identidadRecordada=$correoRecordado!=='' ? fila_identidad($dep,$correoRecordado) : null;
    $datosRecordados=$correoRecordado!=='' ? ['email'=>$correoRecordado] : [];
    if (($identidadRecordada['registrado']??false)) $datosRecordados['nombre']=$identidadRecordada['nombre_real'];
    $tituloTurno=($_GET['tipo']??'')==='cita' ? 'Solicitar reunión' : 'Tomar turno';
    render('turno', [
        'dep' => $dep,
        'estado' => fila_estado($dep),
        'huecos' => huecos($dep, minutos_de_cita($dep)[0]),
        'minutos_cita' => minutos_de_cita($dep),
        'datos' => $datosRecordados,
        'correo_recordado' => $correoRecordado,
        'identidad_recordada' => $identidadRecordada,
        'error' => (string) ($_GET['error'] ?? ''),
    ], ['titulo' => $tituloTurno, 'ocultar_plan'=>true]);
    exit;
}

// Turno rápido: correo, folio inmediato y copia por email. Sin código.
if (in_array($seccion,['turno','confirmar'],true) && $metodo==='POST'
    && ($_POST['tipo']??'turno')!=='cita') {
    if (!csrf_valido()) aviso('La página caducó','Vuelve a intentarlo.',$dep,400);
    $email=u_email_institucional($_POST['email']??'',(string)$dep['dominio_correo']);
    $estado=fila_estado($dep);
    $volver=function(string $error) use($dep,$estado,$email): never {
        render('turno',['dep'=>$dep,'estado'=>$estado,
            'huecos'=>huecos($dep,minutos_de_cita($dep)[0]),
            'minutos_cita'=>minutos_de_cita($dep),'datos'=>['email'=>$email],
            'error'=>$error],['titulo'=>'Tomar turno','ocultar_plan'=>true]);
        exit;
    };
    if (!u_email_dominio_exacto($email,(string)$dep['dominio_correo'])) {
        $volver('Escribe un usuario válido de '.$dep['dominio_correo'].'.');
    }
    if (!$estado['abierta']) $volver($estado['motivo_cierre']);

    $resultado=turno_rapido($dep,$email);
    $turno=$resultado['turno'];
    if ($resultado['nuevo']) {
        $nuevo=fila_estado($dep);
        $estimado=null;
        foreach ($nuevo['turnos'] as $fila) {
            if ((int)$fila['id']===(int)$turno['id']) $estimado=$fila['estimado'];
        }
        correo_turno($dep,$turno,$estimado,url_absoluta('t/'.$turno['token']));
        push_turno_nuevo($dep,$turno,$estimado);
    }
    fila_recordar_correo($email);
    u_redirigir(ruta('t/'.$turno['token']));
}

// Reunión: validar todos los datos y mandar el código al correo.
if ($seccion === 'confirmar' && $metodo === 'POST') {
    if (!csrf_valido()) {
        aviso('La página caducó', 'Vuelve a llenar el formulario.', $dep, 400);
    }
    $tipo = 'cita';
    $nombre = u_limpio($_POST['nombre'] ?? '', 120);
    $email = u_email($_POST['email'] ?? '');
    $asunto = u_limpio($_POST['asunto'] ?? '', 200);
    $estado = fila_estado($dep);

    $volver = function (string $error) use ($dep, $estado, $nombre, $email, $asunto, $tipo) {
        $duraciones = minutos_de_cita($dep);
        $duracion = (int) ($_POST['minutos_cita'] ?? $duraciones[0]);
        if (!in_array($duracion, $duraciones, true)) { $duracion = $duraciones[0]; }
        render('turno', [
            'dep' => $dep, 'estado' => $estado,
            'huecos' => huecos($dep, $duracion),
            'minutos_cita' => $duraciones,
            'datos' => ['nombre' => $nombre, 'email' => $email, 'asunto' => $asunto,
                'tipo' => $tipo, 'minutos_cita' => $duracion,
                'minutos' => (int) ($_POST['minutos'] ?? 5),
                'videoconferencia'=>!empty($_POST['videoconferencia'])?1:0,'enlace_teams'=>(string)($_POST['enlace_teams']??''),
                'hora' => (string) ($_POST['hora'] ?? ''),
                'descripcion' => u_texto_largo($_POST['descripcion'] ?? '', 4000)],
            'error' => $error,
        ], ['titulo' => 'Solicitar reunión','ocultar_plan'=>true]);
        exit;
    };

    try { $modalidad=modalidad_de_atencion($_POST); }
    catch (InvalidArgumentException $e) { $volver($e->getMessage()); }
    if (!u_email_dominio_exacto($email, (string) $dep['dominio_correo'])) {
        $volver('La reunión requiere una cuenta terminada en @' . $dep['dominio_correo'] . '.');
    }
    if ($nombre === '') {
        $volver('Escribe tu nombre.');
    }
    if ($asunto === '') {
        $volver('Dinos en una línea de qué se trata.');
    }
    if (u_texto_largo($_POST['descripcion'] ?? '',4000) === '') {
        $volver('Describe brevemente el motivo de la reunión.');
    }
    if (demasiadas_solicitudes($email, u_ip())) {
        $volver('Has hecho varias solicitudes seguidas. Inténtalo más tarde.');
    }

    if ($tipo === 'turno') {
        $minutos = (int) ($_POST['minutos'] ?? 0);
        if (!in_array($minutos, [5, (int) $dep['duracion_max']], true)) {
            $volver('Elige cuánto tiempo necesitas.');
        }
        if (!$estado['abierta']) {
            $volver($estado['motivo_cierre'] . ' Puedes solicitar una reunión.');
        }
        if (turnos_activos_de((int) $dep['id'], $email)) {
            $volver('Ya tienes un turno pendiente de atención en esta oficina. Podrás tomar otro cuando ese turno aparezca como atendido; revisa el enlace que te llegó por correo.');
        }
        $carga = compact('tipo', 'nombre', 'email', 'asunto', 'minutos');
    } else {
        $minutos = (int) ($_POST['minutos_cita'] ?? 0);
        if (!in_array($minutos, minutos_de_cita($dep), true)) {
            $volver('Elige la duración de la reunión.');
        }
        $propuesta = (string) ($_POST['hora'] ?? '');
        try { exigir_cita_unica((int)$dep['id'],$email,$propuesta); }
        catch (DomainException $e) { $volver($e->getMessage()); }
        $valida = false;
        foreach (huecos($dep, $minutos) as $dia) {
            foreach ($dia['opciones'] as $opcion) {
                if ($opcion->format('Y-m-d H:i') === $propuesta) {
                    $valida = true;
                    break 2;
                }
            }
        }
        if (!$valida) {
            $volver('La hora elegida ya pasó o dejó de estar disponible. Conservamos los datos que escribiste; elige otra hora. Si adjuntaste archivos, selecciónalos de nuevo.');
        }
        $carga = [
            'tipo' => 'cita',
            'nombre' => $nombre,
            'email' => $email,
            'asunto' => $asunto,
            'minutos' => $minutos,
            'propuesta' => $propuesta . ':00',
            'descripcion' => u_texto_largo($_POST['descripcion'] ?? '', 4000),
            'archivos' => guardar_subidas('pendientes/' . u_token(8)),
        ];
    }

    $carga=[...$carga,...$modalidad];
    $verificacion = verificacion_crear($dep, $email, $carga);
    fila_recordar_correo($email);
    correo_codigo($dep, $email, $verificacion['codigo']);
    render('codigo', ['dep' => $dep, 'email' => $email, 'tipo' => $tipo, 'error' => '', 'verificacion_id'=>$verificacion['id'],
        'advertencia'=>$tipo==='cita' ? advertencia_otras_filas((int)$dep['id'],$email,a_momento($carga['propuesta'])) : ''],
        ['titulo' => 'Confirma tu correo','ocultar_plan'=>true]);
    exit;
}

// Paso 3: el codigo confirma y crea el turno o la solicitud.
if ($seccion === 'codigo' && $metodo === 'POST') {
    if (!csrf_valido()) {
        aviso('La página caducó', 'Vuelve a empezar.', $dep, 400);
    }
    $email = u_email($_POST['email'] ?? '');
    $verificacionId=isset($_POST['verificacion_id']) ? (int)$_POST['verificacion_id'] : null;
    $carga = verificacion_comprobar($dep, $email, (string) ($_POST['codigo'] ?? ''),$verificacionId);
    if ($carga === null) {
        $destinoConfirmado=$verificacionId===null ? null : confirmacion_destino($verificacionId);
        if ($destinoConfirmado!==null) u_redirigir($destinoConfirmado);
        render('codigo', ['dep' => $dep, 'email' => $email, 'tipo' => (string) ($_POST['tipo'] ?? 'turno'),
                          'verificacion_id'=>$verificacionId, 'error' => 'El código no coincide con esta solicitud, ya venció o agotaste los 5 intentos. Usa el correo correspondiente a este formulario.'],
            ['titulo' => 'Confirma tu correo','ocultar_plan'=>true]);
        exit;
    }

    if (($carga['tipo'] ?? 'turno') === 'turno') {
        $estado = fila_estado($dep);
        if (!$estado['abierta']) {
            aviso('La fila se cerró', $estado['motivo_cierre']
                . ' Puedes solicitar una reunión desde la página de la oficina.', $dep);
        }
        if (turnos_activos_de((int) $dep['id'], $email)) {
            aviso('Ya tienes un turno pendiente',
                'No puedes apartar otro mientras el anterior siga en espera o en atención. Podrás tomar uno nuevo cuando aparezca como atendido.', $dep);
        }
        $turno = crear_turno($dep, $carga['nombre'], $email, $carga['asunto'], (int) $carga['minutos'],$carga);
        $nuevo = fila_estado($dep);
        $estimado = null;
        foreach ($nuevo['turnos'] as $fila) {
            if ((int) $fila['id'] === (int) $turno['id']) {
                $estimado = $fila['estimado'];
            }
        }
        correo_turno($dep, $turno, $estimado, url_absoluta('t/' . $turno['token']));
        push_turno_nuevo($dep, $turno, $estimado);   // el reloj vibra en tu muñeca
        $destino=ruta('t/' . $turno['token']);
        if ($verificacionId!==null) confirmacion_recordar($verificacionId,$destino);
        u_redirigir($destino);
    }

    try {
        $cita = crear_cita($dep, $carga['nombre'], $email, $carga['asunto'],
            (string) ($carga['descripcion'] ?? ''), (int) $carga['minutos'], (string) $carga['propuesta'],$carga);
    } catch (DomainException $e) {
        render('turno',['dep'=>$dep,'estado'=>fila_estado($dep),
            'huecos'=>huecos($dep,(int)$carga['minutos']),'minutos_cita'=>minutos_de_cita($dep),
            'datos'=>[...$carga,'minutos_cita'=>$carga['minutos'],'hora'=>substr($carga['propuesta'],0,16)],
            'error'=>$e->getMessage().' Conservamos tu captura; si eliges otra fecha, selecciona de nuevo los archivos.'],['titulo'=>'Solicitar cita','ocultar_plan'=>true]);
        exit;
    }
    foreach ((array) ($carga['archivos'] ?? []) as $archivo) {
        $origen = ruta_subida($archivo['ruta']);
        $carpeta = carpeta_subidas() . '/citas/' . $cita['id'];
        if (!is_dir($carpeta)) {
            mkdir($carpeta, 0770, true);
        }
        $destino = $carpeta . '/' . basename($archivo['ruta']);
        if (is_file($origen) && rename($origen, $destino)) {
            consulta('INSERT INTO adjuntos (cita_id, nombre, ruta, tipo, bytes, creado) '
                . 'VALUES (?, ?, ?, ?, ?, ?)',
                [$cita['id'], $archivo['nombre'], 'subidas/citas/' . $cita['id'] . '/' . basename($destino),
                 $archivo['tipo'], $archivo['bytes'], ahora_texto()]);
        }
    }
    correo_cita_solicitada($dep, $cita, url_absoluta('c/' . $cita['token']));
    $destino=ruta('c/' . $cita['token']);
    if ($verificacionId!==null) confirmacion_recordar($verificacionId,$destino);
    u_redirigir($destino);
}

function url_absoluta(string $destino): string
{
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string) ($_SERVER['HTTP_HOST'] ?? (config()['host'] ?? 'localhost'));
    return $esquema . '://' . $host . ruta($destino);
}

aviso('Página no encontrada', 'Revisa el enlace.', $dep ?? null, 404);
