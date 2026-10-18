<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/agenda.php';
require_once __DIR__ . '/util.php';
require_once __DIR__ . '/identidad.php';

/** Consultas de la parte publica: dependencias, fila, turnos y citas. */
function asegurar_esquema_horarios(): void
{
    static $listo=false;
    if ($listo) return;
    $columnas=array_column(filas('SHOW COLUMNS FROM horarios'),'Field');
    if (!in_array('activo',$columnas,true)) {
        db()->exec('ALTER TABLE horarios ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER fin');
    }
    $listo=true;
}

function modalidad_de_atencion(array $datos): array
{
    $video=(string)($datos['videoconferencia']??'0')==='1';
    $enlace=$video?trim((string)($datos['enlace_teams']??'')):'';
    if ($enlace!=='') {
        $url=parse_url($enlace);
        if (strlen($enlace)>2048 || !filter_var($enlace,FILTER_VALIDATE_URL) || !$url || ($url['scheme']??'')!=='https'
            || !in_array(strtolower($url['host']??''),['teams.microsoft.com','teams.live.com','teams.cloud.microsoft'],true)
            || isset($url['user']) || isset($url['pass']) || (isset($url['port']) && $url['port']!==443)) {
            throw new InvalidArgumentException('Introduce un enlace HTTPS válido de Microsoft Teams o deja el campo vacío.');
        }
    }
    return ['videoconferencia'=>$video?1:0,'enlace_teams'=>$enlace];
}
function texto_modalidad(array $atencion): string
{
    if (empty($atencion['videoconferencia'])) return 'Atención presencial.';
    return 'Videoconferencia por Teams. '.(!empty($atencion['enlace_teams'])?'Enlace: '.$atencion['enlace_teams']:'Sin enlace: el titular de la oficina te llamará por Teams cuando llegue tu turno, usando tu correo institucional.');
}

function dependencia(string $clave): ?array
{
    return fila_una('SELECT * FROM dependencias WHERE clave = ? AND activa = 1', [mb_strtolower($clave)]);
}

function dependencia_id(int $id): ?array
{
    return fila_una('SELECT * FROM dependencias WHERE id = ?', [$id]);
}

function dependencias(): array
{
    return filas('SELECT * FROM dependencias ORDER BY clave');
}

function horarios(int $dependencia): array
{
    asegurar_esquema_horarios();
    return filas('SELECT * FROM horarios WHERE dependencia_id = ? AND activo = 1 ORDER BY dia, inicio', [$dependencia]);
}

/** Incluye también los intervalos deshabilitados para poder reactivarlos sin reordenarlos. */
function horarios_configurados(int $dependencia): array
{
    asegurar_esquema_horarios();
    return filas('SELECT * FROM horarios WHERE dependencia_id = ? ORDER BY dia, inicio', [$dependencia]);
}

function dia_semana_inhabil(array $horarios, int $dia): bool
{
    foreach ($horarios as $tramo) {
        if ((int)($tramo['dia']??0)===$dia && empty($tramo['activo'])) return true;
    }
    return false;
}

function excepciones(int $dependencia, string $desde, string $hasta): array
{
    return filas('SELECT * FROM excepciones WHERE dependencia_id = ? AND fecha BETWEEN ? AND ? ORDER BY fecha',
        [$dependencia, $desde, $hasta]);
}

/** Un cierre sin horas hace que la fecha completa sea inhábil. */
function dia_inhabil(array $excepciones, string $fecha): bool
{
    foreach ($excepciones as $excepcion) {
        if (($excepcion['fecha']??'')===$fecha && ($excepcion['tipo']??'cerrado')==='cerrado'
            && empty($excepcion['inicio'])) return true;
    }
    return false;
}

function bloqueos(int $dependencia, DateTimeImmutable $dia): array
{
    $crudos = filas(
        'SELECT * FROM bloqueos WHERE dependencia_id = ? AND inicio < ? AND fin > ? ORDER BY inicio',
        [$dependencia, $dia->setTime(23, 59, 59)->format('Y-m-d H:i:s'),
         $dia->setTime(0, 0, 0)->format('Y-m-d H:i:s')]
    );
    // array_merge y no '+': el operador conservaria las cadenas originales.
    return array_map(
        fn($b) => array_merge($b, ['inicio' => a_momento($b['inicio']), 'fin' => a_momento($b['fin'])]),
        $crudos
    );
}

function turnos_del_dia(int $dependencia, string $fecha): array
{
    return filas('SELECT * FROM turnos WHERE dependencia_id = ? AND fecha = ? ORDER BY folio', [$dependencia, $fecha]);
}

/**
 * Estado completo de la fila con las horas estimadas ya calculadas.
 * Es la unica fuente de verdad: la usan la pagina publica, la pantalla y la laptop.
 */
function fila_estado(array $dep, ?DateTimeImmutable $ahora = null): array
{
    $ahora = $ahora ?? new DateTimeImmutable('now');
    $dia = $ahora->setTime(0, 0, 0);
    $fecha = $ahora->format('Y-m-d');

    $jornada = jornada($dep, $fecha, true);
    $reuniones = bloqueos((int) $dep['id'], $dia);
    $turnos = turnos_del_dia((int) $dep['id'], $fecha);
    // El horario semanal propone; la jornada del dia decide.
    $excepcionesDia=excepciones((int) $dep['id'], $fecha, $fecha);
    $diaInhabil=dia_inhabil($excepcionesDia,$fecha);
    $numeroDia=(int)$dia->format('N');
    $configurados=horarios_configurados((int)$dep['id']);
    $inhabilSemanal=dia_semana_inhabil($configurados,$numeroDia);
    $diaInhabil=$diaInhabil||$inhabilSemanal;
    $base = Agenda::ventanas($dia, horarios((int) $dep['id']),$excepcionesDia);
    $ventanas = [];
    $apertura = $jornada ? Agenda::conHora($dia, (string) $jornada['apertura']) : null;
    $cierre = $jornada ? Agenda::conHora($dia, (string) $jornada['cierre']) : null;
    $estadoJornada = $diaInhabil ? 'cancelada' : ($jornada['estado'] ?? 'sin-jornada');

    if ($jornada !== null && !in_array($estadoJornada, ['cancelada', 'cerrada'], true)) {
        if (!$base) {
            $base = [['inicio' => $apertura, 'fin' => $cierre]];   // dia fuera del horario semanal
        }
        // La hora de apertura manda: al abrir se guarda la hora real de llegada.
        $ventanas = Agenda::recortar($base, $apertura, $cierre);
    }
    // "No disponible" pausa el intervalo en curso, no todos los intervalos
    // restantes del día. También corrige en memoria bloqueos antiguos que se
    // guardaron hasta el cierre total de la jornada.
    foreach ($reuniones as &$bloqueo) {
        if (($bloqueo['origen']??'')!=='pausa') continue;
        foreach ($ventanas as $ventana) {
            if ($bloqueo['inicio'] >= $ventana['inicio'] && $bloqueo['inicio'] < $ventana['fin']) {
                if ($bloqueo['fin'] > $ventana['fin']) $bloqueo['fin']=$ventana['fin'];
                break;
            }
        }
    }
    unset($bloqueo);
    $pausaManual = null;
    foreach ($reuniones as $bloqueo) {
        if (($bloqueo['origen'] ?? '') === 'pausa' && $bloqueo['inicio'] <= $ahora && $bloqueo['fin'] > $ahora) {
            $pausaManual = $bloqueo;
            break;
        }
    }
    $ventanaActual=null;
    foreach ($ventanas as $ventana) {
        if ($ventana['inicio'] <= $ahora && $ventana['fin'] > $ahora) { $ventanaActual=$ventana; break; }
    }

    $entrada = array_map(fn($t) => [
        'id' => (int) $t['id'],
        'minutos' => (int) $t['minutos'],
        'estado' => $t['estado'],
        'llamado' => a_momento($t['llamado']),
    ], $turnos);

    $calculo = Agenda::calcular($ahora, $ventanas, $reuniones, $entrada, [
        'colchon_turno' => (int) $dep['colchon_turno'],
        'colchon_reunion' => (int) $dep['colchon_reunion'],
    ]);

    $actual = null;
    $lista = [];
    foreach ($turnos as $turno) {
        $turno['estimado_guardado'] = $turno['estimado'];   // el de la vuelta anterior
        $turno['estimado'] = $calculo['estimados'][(int) $turno['id']] ?? null;
        $turno['espera'] = Agenda::espera($ahora, $turno['estimado']);
        if ($turno['estado'] === 'llamado') {
            // Cuanto falta de esta reunion, contando desde que la llamaste.
            $inicio = a_momento($turno['llamado']) ?? $ahora;
            $fin = $inicio->modify('+' . (int) $turno['minutos'] . ' minutes');
            $turno['restan'] = (int) round(($fin->getTimestamp() - $ahora->getTimestamp()) / 60);
            $turno['transcurridos'] = (int) round(($ahora->getTimestamp() - $inicio->getTimestamp()) / 60);
            $actual = $turno;
        }
        $lista[] = $turno;
    }

    $siguiente = Agenda::acomodar($calculo['libre'], (int) $dep['duracion_max'], $ventanas, $reuniones,
        (int) $dep['colchon_reunion']);

    // Hasta que hora se puede uno formar: lo que tu fijaste, y ademas que el
    // turno alcance a ser atendido antes de que cierres.
    $tope = ($jornada && $jornada['tope']) ? Agenda::conHora($dia, (string) $jornada['tope']) : null;
    $dentroDelTope = $tope === null || $ahora < $tope;
    // Nadie puede formarse antes de que comience la atención del día.
    $inicioDia = null;
    foreach ($ventanas as $ventana) {
        if ($inicioDia === null || $ventana['inicio'] < $inicioDia) $inicioDia = $ventana['inicio'];
    }
    $antesDeAbrir = $inicioDia !== null && $ahora < $inicioDia;
    $abierta = in_array($estadoJornada, ['programada', 'abierta'], true)
        && $dentroDelTope && !$antesDeAbrir && $siguiente !== null;

    $horarioActivo=in_array($estadoJornada,['programada','abierta'],true) && $ventanaActual!==null;
    $ocupadoHasta = $horarioActivo
        ? Agenda::ocupado_hasta($ahora, $reuniones) : null;
    $disponibleHasta = ($horarioActivo && $ocupadoHasta === null)
        ? Agenda::disponible_hasta($ahora, $ventanas, $reuniones) : null;

    if ($pausaManual !== null) {
        $atencion = 'ocupado';
    } elseif ($estadoJornada === 'cerrada') {
        $atencion = 'cerrada';
    } elseif ($estadoJornada === 'cancelada' || $jornada === null) {
        $atencion = 'sin-atencion';
    } elseif ($ventanaActual === null) {
        $atencion = $cierre !== null && $ahora >= $cierre ? 'cerrada' : 'pausada';
    } elseif ($ocupadoHasta !== null) {
        $atencion = 'ocupado';
    } else {
        $atencion = 'atendiendo';
    }

    $nota = trim((string) ($jornada['nota'] ?? '')) ?: trim((string) $dep['mensaje']);
    if ($diaInhabil) {
        if ($inhabilSemanal) $nota='Día inhábil.';
        foreach ($excepcionesDia as $excepcion) {
            if (($excepcion['tipo']??'cerrado')==='cerrado' && empty($excepcion['inicio'])) {
                $nota=trim((string)($excepcion['motivo']??''))?:'Día inhábil.';
                break;
            }
        }
    }
    $motivo = '';
    if (!$abierta) {
        if ($atencion === 'cerrada') {
            $motivo = $nota ?: 'La atención de hoy terminó.';
        } elseif ($atencion === 'sin-atencion') {
            $motivo = $nota ?: 'Hoy no hay atención.';
        } elseif ($antesDeAbrir) {
            $motivo = 'Aún no comienza la atención. Podrás tomar turno a partir de las ' . u_hora($inicioDia) . '.';
        } elseif (!$dentroDelTope) {
            $motivo = 'El registro en la cola cerró a las ' . u_hora($tope) . '.';
        } else {
            $motivo = 'Por hoy ya no alcanza el horario de atención.';
        }
    }

    $proximaAtencion = null;
    if ($atencion === 'pausada' && $apertura !== null && $apertura > $ahora) {
        $proximaAtencion = $apertura;
    } elseif ($atencion === 'ocupado' && $ocupadoHasta !== null && $cierre !== null && $ocupadoHasta < $cierre) {
        // Si el bloqueo termina justo al cerrar un intervalo, la atención se
        // reanuda al inicio del siguiente (por ejemplo, de 11:00 a 15:00).
        $proximaAtencion = Agenda::acomodar($ocupadoHasta,1,$ventanas,$reuniones,0);
    } elseif ($atencion !== 'atendiendo') {
        $proximaAtencion = proxima_atencion($dep, $ahora);
    }

    return [
        'dependencia' => $dep,
        'jornada' => $jornada,
        'ahora' => $ahora,
        'ventanas' => $ventanas,
        'bloqueos' => $reuniones,
        'turnos' => $lista,
        'actual' => $actual,
        'espera' => array_values(array_filter($lista, fn($t) => $t['estado'] === 'espera')),
        'proximo_hueco' => $siguiente,
        'abierta' => $abierta,
        'atencion' => $atencion,
        'apertura' => $apertura,
        'cierre' => $cierre,
        'tope' => $tope,
        'disponible_hasta' => $disponibleHasta,
        'no_disponible_hasta' => $ocupadoHasta ?? ($atencion === 'pausada' ? ($proximaAtencion??$apertura) : null),
        'nota' => $nota,
        'motivo_cierre' => $motivo,
        'proxima_atencion' => $proximaAtencion,
        'pausa_manual' => $pausaManual !== null,
        'dia_inhabil' => $diaInhabil,
    ];
}

/** La fila pública identifica a todas las personas únicamente por folio. */
function etiqueta_publica(array $dep, array $turno): string
{
    return 'Turno ' . folio_publico($dep,$turno);
}

function muestra_nombres(array $dep): bool
{
    return false;
}

/** Atención presencial iniciada desde el panel, sin pedir datos a la primera visita. */
function es_atencion_directa(?array $turno): bool
{
    return $turno !== null && ($turno['email'] ?? '') === 'atencion-directa@fila.local';
}

/**
 * La frase que ve la gente en la pantalla y en la pagina:
 * "Disponible hasta 12:00", "No disponible hasta 14:00", etcetera.
 */
function leyenda_atencion(array $estado): string
{
    if (!empty($estado['pausa_manual'])) {
        return !empty($estado['proxima_atencion'])
            ? 'No disponible. Próxima atención: ' . lcfirst(u_proxima_atencion_breve(
                $estado['proxima_atencion'], $estado['ahora'] ?? null))
            : 'No disponible';
    }
    switch ($estado['atencion']) {
        case 'atendiendo':
            return $estado['disponible_hasta']
                ? 'Disponible ahora · hasta las ' . u_hora_12($estado['disponible_hasta'])
                : 'Disponible';
        case 'ocupado':
            return $estado['no_disponible_hasta']
                ? 'No disponible hasta ' . u_hora($estado['no_disponible_hasta'])
                : 'No disponible en este momento';
        case 'pausada':
            return ($estado['proxima_atencion']??$estado['apertura'])
                ? 'Disponible a partir de las ' . u_hora($estado['proxima_atencion']??$estado['apertura'])
                : 'La atención comienza más tarde';
        case 'cerrada':
            return $estado['nota'] ?: 'La atención de hoy terminó';
        default:
            return $estado['nota'] ?: 'Hoy no hay atención';
    }
}

function leyenda_publica_atencion(array $estado): string
{
    if (!empty($estado['pausa_manual'])) return leyenda_atencion($estado);
    if (($estado['atencion'] ?? '') !== 'atendiendo' && !empty($estado['proxima_atencion'])) {
        return 'Próxima atención: ' . lcfirst(u_proxima_atencion_breve(
            $estado['proxima_atencion'], $estado['ahora'] ?? null));
    }
    return leyenda_atencion($estado);
}

/** Primer inicio futuro de atención, respetando jornadas y excepciones. */
function proxima_atencion(array $dep, DateTimeImmutable $ahora, int $dias = 14): ?DateTimeImmutable
{
    $horario = horarios((int) $dep['id']);
    $configurados=horarios_configurados((int)$dep['id']);
    $fin = $ahora->modify("+{$dias} days")->format('Y-m-d');
    $excepciones = excepciones((int) $dep['id'], $ahora->format('Y-m-d'), $fin);
    for ($i = 0; $i <= $dias; $i++) {
        $dia = $ahora->setTime(0, 0)->modify("+{$i} days");
        $fecha = $dia->format('Y-m-d');
        if (dia_inhabil($excepciones,$fecha)
            || dia_semana_inhabil($configurados,(int)$dia->format('N'))) continue;
        $jornada = jornada($dep, $fecha, false);
        if ($jornada !== null) {
            if (in_array((string) $jornada['estado'], ['cancelada', 'cerrada'], true)) continue;
            $ventanas=Agenda::ventanas($dia,$horario,$excepciones);
            $apertura=Agenda::conHora($dia,(string)$jornada['apertura']);
            $cierre=Agenda::conHora($dia,(string)$jornada['cierre']);
            if (!$ventanas && $apertura && $cierre) $ventanas=[['inicio'=>$apertura,'fin'=>$cierre]];
            $ventanas=Agenda::recortar($ventanas,$apertura,$cierre);
            foreach ($ventanas as $ventana) if ($ventana['inicio']>$ahora) return $ventana['inicio'];
            continue;
        }
        foreach (Agenda::ventanas($dia, $horario, $excepciones) as $ventana) {
            if ($ventana['inicio'] > $ahora) return $ventana['inicio'];
        }
    }
    return null;
}

/**
 * Que puede hacer quien trae este token. El reloj lleva una llave aparte que
 * solo abre su propia vista: si se pierde, se regenera sin tocar la de la
 * laptop, y con ella nadie puede llamar turnos ni cancelar nada.
 */
function token_permite(array $dep, string $token, string $accion): bool
{
    if ($token === '') {
        return false;
    }
    $huella = hash('sha256', $token);
    if (hash_equals((string) ($dep['token_hash'] ?? ''), $huella)) {
        return true;
    }
    $delReloj = (string) ($dep['token_reloj_hash'] ?? '');
    return $delReloj !== '' && $accion === 'reloj' && hash_equals($delReloj, $huella);
}

// --- jornada del dia ------------------------------------------------------
//
// La jornada dice a que hora llegas, hasta que hora atiendes y hasta que hora
// se puede formar la gente. El horario semanal solo sirve para proponer esos
// valores; lo que manda es la jornada, que puedes configurar con dias de
// anticipacion o cambiar en el momento.

function jornada(array $dep, string $fecha, bool $crear = true): ?array
{
    $fila = fila_una('SELECT * FROM jornadas WHERE dependencia_id = ? AND fecha = ?',
        [$dep['id'], $fecha]);
    if ($fila !== null || !$crear) {
        return $fila;
    }

    $dia = new DateTimeImmutable($fecha . ' 00:00:00');
    $ventanas = Agenda::ventanas($dia, horarios((int) $dep['id']),
        excepciones((int) $dep['id'], $fecha, $fecha));
    if (!$ventanas) {
        return null;                    // ese dia no hay atencion programada
    }
    $apertura = $ventanas[0]['inicio']->format('H:i');
    $cierre = end($ventanas)['fin']->format('H:i');
    consulta('INSERT INTO jornadas (dependencia_id, fecha, apertura, cierre, estado, creado) '
        . 'VALUES (?, ?, ?, ?, ?, ?)',
        [$dep['id'], $fecha, $apertura, $cierre, 'programada', ahora_texto()]);
    return jornada($dep, $fecha, false);
}

/**
 * Tras editar el horario semanal, las jornadas aun "programadas" (hoy y dias
 * futuros) conservaban la apertura/cierre del horario anterior. Las recalcula.
 */
function resincronizar_jornadas(array $dep): void
{
    $hoy = new DateTimeImmutable('today');
    $horario = horarios((int) $dep['id']);
    $filas = filas("SELECT * FROM jornadas WHERE dependencia_id = ? AND fecha >= ? AND estado = 'programada'",
        [$dep['id'], $hoy->format('Y-m-d')]);
    foreach ($filas as $fila) {
        $dia = new DateTimeImmutable($fila['fecha'] . ' 00:00:00');
        $ventanas = Agenda::ventanas($dia, $horario,
            excepciones((int) $dep['id'], $fila['fecha'], $fila['fecha']));
        if (!$ventanas) continue;
        consulta('UPDATE jornadas SET apertura = ?, cierre = ? WHERE id = ?',
            [$ventanas[0]['inicio']->format('H:i'), end($ventanas)['fin']->format('H:i'), $fila['id']]);
    }
}

/** Configura un dia, hoy o uno futuro, sin necesidad de estar en la oficina. */
function configurar_jornada(array $dep, string $fecha, array $datos): array
{
    $actual = jornada($dep, $fecha, true);
    $apertura = $datos['apertura'] ?? ($actual['apertura'] ?? '09:00');
    $cierre = $datos['cierre'] ?? ($actual['cierre'] ?? '15:00');
    $tope = array_key_exists('tope', $datos) ? $datos['tope'] : ($actual['tope'] ?? null);
    $estado = $datos['estado'] ?? ($actual['estado'] ?? 'programada');
    $nota = u_limpio($datos['nota'] ?? ($actual['nota'] ?? ''), 160);

    if ($actual === null) {
        consulta('INSERT INTO jornadas (dependencia_id, fecha, apertura, cierre, tope, estado, '
            . 'nota, creado) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$dep['id'], $fecha, $apertura, $cierre, $tope, $estado, $nota, ahora_texto()]);
    } else {
        consulta('UPDATE jornadas SET apertura = ?, cierre = ?, tope = ?, estado = ?, nota = ? '
            . 'WHERE id = ?',
            [$apertura, $cierre, $tope, $estado, $nota, $actual['id']]);
    }
    evento((int) $dep['id'], 'jornada', "{$fecha} {$apertura}-{$cierre} ({$estado})");
    return jornada($dep, $fecha, false);
}

/** Llegaste a la oficina: la atencion empieza ahora. */
function abrir_jornada(array $dep, ?string $cierre = null, ?string $tope = null,
                       ?string $fecha = null, ?string $apertura = null): array
{
    $ahora = new DateTimeImmutable('now');
    $fecha = $fecha ?? $ahora->format('Y-m-d');
    $actual = jornada($dep, $fecha, true);
    // Abrir significa "llegue": la atencion arranca a esta hora, no a la prevista.
    $apertura = $apertura ?: ($fecha === $ahora->format('Y-m-d')
        ? $ahora->format('H:i') : ($actual['apertura'] ?? '09:00'));

    configurar_jornada($dep, $fecha, [
        'apertura' => $apertura,
        'cierre' => $cierre ?: ($actual['cierre'] ?? '15:00'),
        'tope' => $tope !== null ? ($tope ?: null) : ($actual['tope'] ?? null),
        'estado' => 'abierta',
    ]);
    consulta('UPDATE jornadas SET abierta_en = ?, cerrada_en = NULL WHERE dependencia_id = ? '
        . 'AND fecha = ?', [ahora_texto(), $dep['id'], $fecha]);
    evento((int) $dep['id'], 'jornada-abierta', $fecha);
    return jornada($dep, $fecha, false);
}

function cerrar_jornada(array $dep, string $nota = '', ?string $fecha = null): array
{
    $fecha = $fecha ?? (new DateTimeImmutable('now'))->format('Y-m-d');
    jornada($dep, $fecha, true);
    consulta('UPDATE jornadas SET estado = ?, nota = ?, cerrada_en = ? WHERE dependencia_id = ? '
        . 'AND fecha = ?', ['cerrada', u_limpio($nota, 160), ahora_texto(), $dep['id'], $fecha]);
    evento((int) $dep['id'], 'jornada-cerrada', $fecha . ($nota ? " · {$nota}" : ''));
    return jornada($dep, $fecha, false);
}

/**
 * Cancela de golpe a quienes esperan en la cola. Devuelve los turnos que se
 * cancelaron para poder avisarles por correo.
 */
function cancelar_cola(array $dep, string $motivo = '', bool $cerrar = true): array
{
    $fecha = (new DateTimeImmutable('now'))->format('Y-m-d');
    $afectados = filas('SELECT * FROM turnos WHERE dependencia_id = ? AND fecha = ? '
        . 'AND estado IN (?, ?) ORDER BY folio', [$dep['id'], $fecha, 'espera', 'llamado']);
    foreach ($afectados as $turno) {
        cancelar_turno($turno, 'cancelacion de cola');
    }
    if ($cerrar) {
        cerrar_jornada($dep, $motivo);
    }
    evento((int) $dep['id'], 'cola-cancelada', count($afectados) . ' turno(s)');
    return $afectados;
}

/** Citas confirmadas de hoy en adelante, para poder cancelarlas. */
function citas_confirmadas(int $dependencia, int $limite = 100): array
{
    return filas('SELECT * FROM citas WHERE dependencia_id = ? AND estado = ? AND confirmada >= ? '
        . 'ORDER BY confirmada LIMIT ?',
        [$dependencia, 'aprobada', (new DateTimeImmutable('now'))->format('Y-m-d 00:00:00'), $limite]);
}

function turno_por_token(string $token): ?array
{
    return fila_una('SELECT * FROM turnos WHERE token = ?', [$token]);
}

function minutas_del_turno(array $turno): array
{
    return filas('SELECT id,resumen,archivo,nombre_archivo,creado FROM fila_expedientes WHERE turno_id=? AND dependencia_id=? ORDER BY id DESC',
        [$turno['id'],$turno['dependencia_id']]);
}

function minuta_del_enlace(string $token, int $id): ?array
{
    return fila_una('SELECT e.archivo,e.nombre_archivo FROM fila_expedientes e JOIN turnos t ON t.id=e.turno_id AND t.dependencia_id=e.dependencia_id WHERE t.token=? AND e.id=?',[$token,$id]);
}

function crear_turno(array $dep, string $nombre, string $email, string $asunto, int $minutos, array $modalidad=[]): array
{
    $modalidad=modalidad_de_atencion($modalidad);
    fila_guardar_alias($dep,$email,$nombre);
    $nombre=fila_identidad($dep,$email,$nombre)['nombre'];
    $fecha = (new DateTimeImmutable('now'))->format('Y-m-d');
    $token = u_token();
    for ($intento = 0; $intento < 5; $intento++) {
        $folio = (int) (fila_una('SELECT COALESCE(MAX(folio), 0) + 1 AS siguiente FROM turnos '
            . 'WHERE dependencia_id = ? AND fecha = ?', [$dep['id'], $fecha])['siguiente'] ?? 1);
        try {
            consulta('INSERT INTO turnos (dependencia_id, fecha, folio, nombre, nombre_publico, email, '
                . 'asunto, minutos, estado, token, creado,videoconferencia,enlace_teams) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$dep['id'], $fecha, $folio, $nombre, u_nombre_corto($nombre), $email, $asunto,
                 $minutos, 'espera', $token, ahora_texto(),$modalidad['videoconferencia'],$modalidad['enlace_teams']]);
            evento((int) $dep['id'], 'turno', "folio {$folio} · {$email}");
            return turno_por_token($token);
        } catch (PDOException $error) {
            if ($intento === 4) {
                throw $error;   // dos personas tomaron turno en el mismo instante
            }
            usleep(50000);
        }
    }
    throw new RuntimeException('No se pudo crear el turno.');
}

function cancelar_turno(array $turno, string $quien = 'persona'): void
{
    consulta('UPDATE turnos SET estado = ?, cerrado = ? WHERE id = ? AND estado IN (?, ?)',
        ['cancelado', ahora_texto(), $turno['id'], 'espera', 'llamado']);
    evento((int) $turno['dependencia_id'], 'cancelacion', "folio {$turno['folio']} · {$quien}");
}

function turnos_activos_de(int $dependencia, string $email): array
{
    return filas('SELECT * FROM turnos WHERE dependencia_id = ? AND fecha = ? AND email = ? '
        . 'AND estado IN (?, ?) ORDER BY id DESC',
        [$dependencia, (new DateTimeImmutable('now'))->format('Y-m-d'), u_email($email), 'espera', 'llamado']);
}

/**
 * Crea un turno con solo el correo. Si ya existe uno pendiente, devuelve ese
 * mismo turno para que los reenvíos del formulario sean idempotentes.
 */
function turno_rapido(array $dep, string $email): array
{
    $email=u_email($email);
    $activos=turnos_activos_de((int)$dep['id'],$email);
    if ($activos) return ['turno'=>$activos[0],'nuevo'=>false];
    $turno=crear_turno($dep,'',$email,'Turno en ventanilla',(int)$dep['duracion_max']);
    return ['turno'=>$turno,'nuevo'=>true];
}

// --- citas ---------------------------------------------------------------

function cita_activa_del_dia(int $dep, string $email, string $fecha, int $excluir=0): ?array
{
    $dia=a_momento($fecha);
    if (!$dia) throw new DomainException('La fecha de la cita no es válida.');
    return fila_una('SELECT * FROM citas WHERE dependencia_id=? AND LOWER(email)=? AND id<>? AND estado IN (?,?) '
        .'AND COALESCE(confirmada,propuesta)>=? AND COALESCE(confirmada,propuesta)<? LIMIT 1',
        [$dep,u_email($email),$excluir,'solicitada','aprobada',$dia->format('Y-m-d').' 00:00:00',
         $dia->modify('+1 day')->format('Y-m-d').' 00:00:00']);
}

function exigir_cita_unica(int $dep, string $email, string $fecha, int $excluir=0): void
{
    if (cita_activa_del_dia($dep,$email,$fecha,$excluir)) throw new DomainException('Ya tienes una cita activa para ese día en esta oficina. Debes cancelarla desde el enlace que recibiste por correo antes de solicitar otra.');
}

/** Advertencia informativa: otras oficinas no impiden reservar o formarse. */
function advertencia_otras_filas(int $dep, string $email, ?DateTimeImmutable $hora): string
{
    if (!$hora) return '';
    $otras=filas('SELECT COALESCE(confirmada,propuesta) AS hora FROM citas WHERE dependencia_id<>? AND LOWER(email)=? AND estado IN (?,?) AND COALESCE(confirmada,propuesta)>? AND COALESCE(confirmada,propuesta)<?',
        [$dep,u_email($email),'solicitada','aprobada',$hora->modify('-15 minutes')->format('Y-m-d H:i:s'),$hora->modify('+15 minutes')->format('Y-m-d H:i:s')]);
    $aviso='Advertencia: tienes una cita o turno en otra oficina con menos de 15 minutos de diferencia. Puedes continuar, pero considera el tiempo de traslado y posibles retrasos.';
    if ($otras) return $aviso;
    if ($hora->format('Y-m-d')!==date('Y-m-d')) return '';
    $turnos=filas('SELECT * FROM turnos WHERE dependencia_id<>? AND LOWER(email)=? AND fecha=? AND estado IN (?,?)',
        [$dep,u_email($email),date('Y-m-d'),'espera','llamado']);
    $estados=[];
    foreach ($turnos as $t) {
        $id=(int)$t['dependencia_id'];
        $estado=$estados[$id]??=fila_estado(dependencia_id($id));
        foreach ($estado['turnos'] as $calculado) {
            if ((int)$calculado['id']!==(int)$t['id']) continue;
            $otraHora=$t['estado']==='llamado' ? a_momento($t['llamado']) : $calculado['estimado'];
            if ($otraHora && abs($otraHora->getTimestamp()-$hora->getTimestamp())<900) return $aviso;
        }
    }
    return '';
}

function bloquear_citas(): void
{
    // Orden común: serializa altas y cambios de día incluso entre oficinas.
    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') consulta('SELECT id FROM dependencias ORDER BY id FOR UPDATE');
}

function crear_cita(array $dep, string $nombre, string $email, string $asunto, string $descripcion,
                    int $minutos, string $propuesta, array $modalidad=[]): array
{
    $modalidad=modalidad_de_atencion($modalidad);
    if (!empty(config()['integrado']) && !in_array($minutos,[15,20],true)) throw new DomainException('Las reuniones nuevas deben durar 15 o 20 minutos. Elige una de esas duraciones.');
    $email=u_email($email);
    $propia=!db()->inTransaction();
    if ($propia) db()->beginTransaction();
    try {
    bloquear_citas();
    exigir_cita_unica((int)$dep['id'],$email,$propuesta);
    fila_guardar_alias($dep,$email,$nombre);
    $nombre=fila_identidad($dep,$email,$nombre)['nombre'];
    $token = u_token();
    consulta('INSERT INTO citas (dependencia_id, nombre, email, asunto, descripcion, minutos, '
        . 'propuesta, estado, token, creado,videoconferencia,enlace_teams) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$dep['id'], $nombre, $email, $asunto, $descripcion, $minutos, $propuesta, 'solicitada',
         $token, ahora_texto(),$modalidad['videoconferencia'],$modalidad['enlace_teams']]);
    evento((int) $dep['id'], 'cita', "{$email} · {$propuesta}");
    $resultado=cita_por_token($token);
    if ($propia) db()->commit();
    return $resultado;
    } catch (Throwable $e) { if ($propia && db()->inTransaction()) db()->rollBack(); throw $e; }
}

function cita_por_token(string $token): ?array
{
    return fila_una('SELECT * FROM citas WHERE token = ?', [$token]);
}

function citas(int $dependencia, string $estado = 'solicitada'): array
{
    return filas('SELECT * FROM citas WHERE dependencia_id = ? AND estado = ? ORDER BY propuesta',
        [$dependencia, $estado]);
}

function adjuntos_de(int $cita): array
{
    return filas('SELECT * FROM adjuntos WHERE cita_id = ? ORDER BY id', [$cita]);
}

/** Aprueba la cita y reserva su bloque para que la fila lo respete. */
function aprobar_cita(array $cita, ?string $inicio = null, string $motivo = ''): array
{
    $inicio = $inicio ?: $cita['propuesta'];
    $propia=!db()->inTransaction();
    if ($propia) db()->beginTransaction();
    try {
    bloquear_citas();
    exigir_cita_unica((int)$cita['dependencia_id'],$cita['email'],$inicio,(int)$cita['id']);
    $arranque = a_momento($inicio);
    $fin = $arranque->modify('+' . (int) $cita['minutos'] . ' minutes');

    consulta('UPDATE citas SET estado = ?, confirmada = ?, motivo = ?, resuelta = ? WHERE id = ?',
        ['aprobada', $arranque->format('Y-m-d H:i:s'), $motivo, ahora_texto(), $cita['id']]);
    consulta('DELETE FROM bloqueos WHERE cita_id = ?', [$cita['id']]);
    consulta('INSERT INTO bloqueos (dependencia_id, inicio, fin, motivo, origen, cita_id) '
        . 'VALUES (?, ?, ?, ?, ?, ?)',
        [$cita['dependencia_id'], $arranque->format('Y-m-d H:i:s'), $fin->format('Y-m-d H:i:s'),
         mb_substr('Cita: ' . $cita['asunto'], 0, 160), 'cita', $cita['id']]);
    evento((int) $cita['dependencia_id'], 'cita-aprobada', $cita['email']);
    $resultado=cita_por_token($cita['token']);
    if ($propia) db()->commit();
    return $resultado;
    } catch (Throwable $e) { if ($propia && db()->inTransaction()) db()->rollBack(); throw $e; }
}

function rechazar_cita(array $cita, string $motivo): array
{
    consulta('UPDATE citas SET estado = ?, motivo = ?, resuelta = ? WHERE id = ?',
        ['rechazada', mb_substr($motivo, 0, 255), ahora_texto(), $cita['id']]);
    consulta('DELETE FROM bloqueos WHERE cita_id = ?', [$cita['id']]);
    evento((int) $cita['dependencia_id'], 'cita-rechazada', $cita['email']);
    return cita_por_token($cita['token']);
}

function cancelar_cita(array $cita, string $quien = 'persona'): void
{
    $propia=!db()->inTransaction();
    if ($propia) db()->beginTransaction();
    try {
    if (db()->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql') consulta('SELECT id FROM dependencias WHERE id=? FOR UPDATE',[$cita['dependencia_id']]);
    consulta('UPDATE citas SET estado = ?, resuelta = ? WHERE id = ?',
        ['cancelada', ahora_texto(), $cita['id']]);
    consulta('DELETE FROM bloqueos WHERE cita_id = ?', [$cita['id']]);
    evento((int) $cita['dependencia_id'], 'cita-cancelada', "{$cita['email']} · {$quien}");
    if ($propia) db()->commit();
    } catch (Throwable $e) { if ($propia && db()->inTransaction()) db()->rollBack(); throw $e; }
}

/**
 * Nueva solicitud a partir de una cita cancelada o rechazada, conservando
 * nombre, correo, asunto, descripcion y los archivos que ya habia enviado.
 * El enlace llego a su buzon, asi que no se le vuelve a pedir el codigo.
 */
function reagendar_cita(array $previa, string $propuesta, ?int $minutos = null): array
{
    $dep = dependencia_id((int) $previa['dependencia_id']);
    if ($dep === null) {
        throw new RuntimeException('La dependencia ya no existe.');
    }
    $nueva = crear_cita($dep, (string) $previa['nombre'], (string) $previa['email'],
        (string) $previa['asunto'], (string) $previa['descripcion'],
        $minutos ?: (int) $previa['minutos'], $propuesta,$previa);

    // Los adjuntos se reaprovechan: apuntan al mismo archivo ya subido.
    foreach (adjuntos_de((int) $previa['id']) as $adjunto) {
        consulta('INSERT INTO adjuntos (cita_id, nombre, ruta, tipo, bytes, creado) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
            [$nueva['id'], $adjunto['nombre'], $adjunto['ruta'], $adjunto['tipo'],
             $adjunto['bytes'], ahora_texto()]);
    }
    evento((int) $dep['id'], 'cita-reagendada', (string) $previa['email']);
    return $nueva;
}

/** Una cita cancelada o rechazada se puede reagendar una sola vez por enlace. */
function puede_reagendarse(?array $cita): bool
{
    return $cita !== null && in_array((string) $cita['estado'], ['cancelada', 'rechazada'], true);
}

// --- verificacion por correo --------------------------------------------

function verificacion_crear(array $dep, string $email, array $carga): array
{
    $codigo = u_codigo();
    $expira = (new DateTimeImmutable('now'))->modify('+20 minutes');
    consulta('INSERT INTO verificaciones (dependencia_id, email, codigo_hash, carga, expira, ip, creado) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$dep['id'], $email, hash('sha256', $codigo), json_encode($carga, JSON_UNESCAPED_UNICODE),
         $expira->format('Y-m-d H:i:s'), u_ip(), ahora_texto()]);
    return ['codigo' => $codigo, 'id' => (int) db()->lastInsertId()];
}

function verificacion_comprobar(array $dep, string $email, string $codigo, ?int $id=null): ?array
{
    $sql='SELECT * FROM verificaciones WHERE dependencia_id = ? AND email = ? '
        . 'AND usado IS NULL AND expira > ?';
    $params=[$dep['id'],u_email($email),ahora_texto()];
    if ($id!==null) { $sql.=' AND id=?'; $params[]=$id; }
    $registro=fila_una($sql.' ORDER BY id DESC LIMIT 1',$params);
    if ($registro === null || (int) $registro['intentos'] >= 5) {
        return null;
    }
    $intento=consulta('UPDATE verificaciones SET intentos = intentos + 1 WHERE id = ? AND usado IS NULL AND intentos < 5 AND expira > ?', [$registro['id'],ahora_texto()]);
    if ($intento->rowCount()!==1) return null;
    if (!hash_equals($registro['codigo_hash'], hash('sha256', trim($codigo)))) {
        return null;
    }
    $uso=consulta('UPDATE verificaciones SET usado = ? WHERE id = ? AND usado IS NULL AND expira > ?', [ahora_texto(), $registro['id'],ahora_texto()]);
    if ($uso->rowCount()!==1) return null;
    $carga = json_decode((string) $registro['carga'], true);
    return is_array($carga) ? $carga : null;
}

/** Freno sencillo contra abuso del QR publico. */
function demasiadas_solicitudes(string $email, string $ip): bool
{
    $desde = (new DateTimeImmutable('now'))->modify('-1 hour')->format('Y-m-d H:i:s');
    $porCorreo = (int) (fila_una('SELECT COUNT(*) AS n FROM verificaciones WHERE email = ? AND creado > ?',
        [u_email($email), $desde])['n'] ?? 0);
    $porIp = (int) (fila_una('SELECT COUNT(*) AS n FROM verificaciones WHERE ip = ? AND creado > ?',
        [$ip, $desde])['n'] ?? 0);
    return $porCorreo >= 5 || $porIp >= 15;
}

function evento(?int $dependencia, string $accion, string $detalle = ''): void
{
    consulta('INSERT INTO eventos (dependencia_id, momento, accion, detalle) VALUES (?, ?, ?, ?)',
        [$dependencia, ahora_texto(), $accion, mb_substr($detalle, 0, 255)]);
}

/**
 * Horas libres para agendar una reunion de `minutos`, agrupadas por dia.
 * Descuenta reuniones ya confirmadas y solicitudes aun sin resolver, para que
 * dos personas no propongan el mismo hueco.
 */
function huecos(array $dep, int $minutos, int $dias = 10, int $porDia = 6): array
{
    $ahora = new DateTimeImmutable('now');
    $desde = $ahora->modify('+60 minutes');       // nadie agenda para dentro de un minuto
    $pendientes = filas('SELECT propuesta, minutos FROM citas WHERE dependencia_id = ? AND estado = ? '
        . 'AND propuesta > ?', [$dep['id'], 'solicitada', $ahora->format('Y-m-d H:i:s')]);

    $resultado = [];
    for ($salto = 0; $salto <= $dias && count($resultado) < 6; $salto++) {
        $dia = $ahora->modify("+{$salto} days")->setTime(0, 0, 0);
        $fecha = $dia->format('Y-m-d');
        $ventanas = Agenda::ventanas($dia, horarios((int) $dep['id']),
            excepciones((int) $dep['id'], $fecha, $fecha));
        if (!$ventanas) {
            continue;
        }
        $ocupado = bloqueos((int) $dep['id'], $dia);
        foreach ($pendientes as $cita) {
            $inicio = a_momento($cita['propuesta']);
            if ($inicio && $inicio->format('Y-m-d') === $fecha) {
                $ocupado[] = ['inicio' => $inicio,
                              'fin' => $inicio->modify('+' . (int) $cita['minutos'] . ' minutes')];
            }
        }

        $cursor = $salto === 0 ? $desde : $dia;
        $opciones = [];
        while (count($opciones) < $porDia) {
            $hueco = Agenda::acomodar($cursor, $minutos, $ventanas, $ocupado,
                (int) $dep['colchon_reunion']);
            if ($hueco === null || $hueco->format('Y-m-d') !== $fecha) {
                break;
            }
            $opciones[] = $hueco;
            $cursor = $hueco->modify('+' . ($minutos + (int) $dep['colchon_turno']) . ' minutes');
        }
        if ($opciones) {
            $resultado[] = ['dia' => $dia, 'opciones' => $opciones];
        }
    }
    return $resultado;
}

/** Minutos permitidos para una reunion agendada, segun la dependencia. */
function minutos_de_cita(array $dep): array
{
    return [15,20];
}

// --- minutas --------------------------------------------------------------
//
// La minuta en PDF se guarda aqui para poder mandarla desde el servidor: el
// correo institucional rechaza lo que sale de la laptop. Nunca sube la
// transcripcion original, y los archivos no se sirven por web (ver
// subidas/.htaccess); solo se bajan por la API con el token de la dependencia.

/** Carpeta donde viven los archivos subidos; las pruebas la mueven a un temporal. */
function carpeta_subidas(): string
{
    return rtrim((string) (config()['carpeta_subidas'] ?? (__DIR__ . '/../subidas')), '/');
}

/** De 'subidas/minutas/...' a la ruta absoluta en disco. */
function ruta_subida(string $relativa): string
{
    return carpeta_subidas() . '/' . preg_replace('#^subidas/#', '', $relativa);
}

function carpeta_minutas(array $dep, string $email): string
{
    return carpeta_subidas() . '/minutas/' . u_apodo((string) $dep['clave'])
        . '/' . u_apodo($email ?: 'sin-correo');
}

function minuta(int $id, int $dependencia): ?array
{
    return fila_una('SELECT * FROM minutas WHERE id = ? AND dependencia_id = ?', [$id, $dependencia]);
}

function minutas_de(int $dependencia, string $email = '', int $limite = 200): array
{
    if ($email !== '') {
        return filas('SELECT * FROM minutas WHERE dependencia_id = ? AND email = ? '
            . 'ORDER BY fecha DESC, id DESC LIMIT ?', [$dependencia, u_email($email), $limite]);
    }
    return filas('SELECT * FROM minutas WHERE dependencia_id = ? ORDER BY id DESC LIMIT ?',
        [$dependencia, $limite]);
}

/**
 * Guarda el PDF que sube la laptop. Si ya estaba (mismo contenido), devuelve
 * el registro existente en lugar de duplicarlo.
 */
function guardar_minuta(array $dep, array $datos, string $temporal): array
{
    $sha = hash_file('sha256', $temporal);
    $previa = fila_una('SELECT * FROM minutas WHERE dependencia_id = ? AND sha256 = ?',
        [$dep['id'], $sha]);
    if ($previa !== null && is_file(ruta_subida((string) $previa['archivo']))) {
        @unlink($temporal);
        return $previa;
    }

    $email = u_email($datos['email'] ?? '');
    $carpeta = carpeta_minutas($dep, $email);
    if (!is_dir($carpeta) && !mkdir($carpeta, 0770, true) && !is_dir($carpeta)) {
        throw new RuntimeException('No se pudo crear la carpeta de minutas.');
    }
    $fecha = a_momento((string) ($datos['fecha'] ?? '')) ?? new DateTimeImmutable('now');
    $nombre = $fecha->format('Ymd_Hi') . '_minuta_' . u_apodo((string) ($datos['persona'] ?? '')) . '.pdf';
    $destino = $carpeta . '/' . $nombre;
    if (is_file($destino)) {
        $destino = $carpeta . '/' . $fecha->format('Ymd_Hi') . '-' . substr($sha, 0, 6) . '.pdf';
    }
    if (!rename($temporal, $destino)) {
        throw new RuntimeException('No se pudo guardar la minuta.');
    }
    @chmod($destino, 0640);

    $relativa = 'subidas/minutas/' . u_apodo((string) $dep['clave']) . '/'
        . u_apodo($email ?: 'sin-correo') . '/' . basename($destino);
    consulta('INSERT INTO minutas (dependencia_id, reunion_local, persona, email, asunto, fecha, '
        . 'archivo, nombre, bytes, sha256, estado, creado) '
        . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$dep['id'], $datos['reunion_local'] ?? null, u_limpio($datos['persona'] ?? '', 120), $email,
         u_limpio($datos['asunto'] ?? '', 200), $fecha->format('Y-m-d H:i:s'), $relativa,
         basename($destino), filesize($destino), $sha, 'guardada', ahora_texto()]);
    evento((int) $dep['id'], 'minuta-guardada', "{$email} · " . basename($destino));

    return minuta((int) db()->lastInsertId(), (int) $dep['id']);
}

function marcar_minuta(int $id, bool $ok, string $destinatario, string $detalle = ''): void
{
    consulta('UPDATE minutas SET estado = ?, destinatario = ?, detalle = ?, enviado = ? WHERE id = ?',
        [$ok ? 'enviada' : 'error', $destinatario, mb_substr($detalle, 0, 255),
         $ok ? ahora_texto() : null, $id]);
}

function borrar_minuta(array $minuta): void
{
    $ruta = ruta_subida((string) $minuta['archivo']);
    if (is_file($ruta)) {
        @unlink($ruta);
    }
    consulta('DELETE FROM minutas WHERE id = ?', [$minuta['id']]);
    evento((int) $minuta['dependencia_id'], 'minuta-borrada', (string) $minuta['email']);
}

/** Retencion: 0 dias significa conservarlas mientras exista el expediente. */
function limpiar_minutas(array $dep, ?int $dias = null): int
{
    $dias = $dias ?? (int) (config()['minutas_dias'] ?? 0);
    if ($dias <= 0) {
        return 0;
    }
    $limite = (new DateTimeImmutable('now'))->modify("-{$dias} days")->format('Y-m-d H:i:s');
    $viejas = filas('SELECT * FROM minutas WHERE dependencia_id = ? AND creado < ?',
        [$dep['id'], $limite]);
    foreach ($viejas as $vieja) {
        borrar_minuta($vieja);
    }
    return count($viejas);
}
