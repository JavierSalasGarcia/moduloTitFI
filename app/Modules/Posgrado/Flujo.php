<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

/**
 * Estado del trámite a partir de lo capturado, los documentos, los hitos del personal y los oficios emitidos:
 * qué requisitos faltan, en qué momento va y cuál es el siguiente paso (uno solo) para el egresado.
 */
final class Flujo
{
    /** Campos obligatorios de «datos del interesado»: clave => etiqueta. */
    public const OBLIGATORIOS = [
        'nombre' => 'nombre', 'acentos' => '¿lleva acentos?', 'cuenta' => 'número de cuenta', 'programa' => 'programa',
        'nivel' => 'nivel', 'plan' => 'plan de estudios', 'telefono' => 'teléfono', 'correo' => 'correo',
        'ocupacion' => 'ocupación', 'periodo_inicio' => 'periodo de inicio', 'titulo' => 'título del trabajo',
        'modalidad' => 'modalidad', 'fecha_previo' => 'fecha del examen previo', 'director_nombre' => 'nombre del director',
        'director_grado' => 'grado del director', 'director_correo' => 'correo del director', 'art53' => '¿aplica el art. 53?',
    ];

    /** @return string[] etiquetas de los campos que faltan */
    public static function faltantesInteresado(array $t): array
    {
        $i = $t['datos']['interesado'] ?? [];
        $faltan = [];
        foreach (self::OBLIGATORIOS as $k => $etiqueta) {
            if (trim((string) ($i[$k] ?? '')) === '') {
                $faltan[] = $etiqueta;
            }
        }
        return $faltan;
    }

    public static function art53(array $t): bool
    {
        return ($t['datos']['interesado']['art53'] ?? '') === 'si';
    }

    /** @return string[] lo que falta en la propuesta de sínodo y fecha (los siete correos son obligatorios) */
    public static function faltantesSinodo(array $t): array
    {
        $s = $t['datos']['sinodo'] ?? [];
        $faltan = [];
        $miembros = $s['miembros'] ?? [];
        for ($i = 0; $i < 7; $i++) {
            $m = $miembros[$i] ?? [];
            $quien = $i < 5 ? 'propietario ' . ($i + 1) : 'suplente ' . ($i - 4);
            if (trim((string) ($m['nombre'] ?? '')) === '' || trim((string) ($m['tipo'] ?? '')) === '') {
                $faltan[] = $quien;
            } elseif (!filter_var((string) ($m['correo'] ?? ''), FILTER_VALIDATE_EMAIL)) {
                $faltan[] = 'correo del ' . $quien;
            }
        }
        foreach (['fecha' => 'fecha', 'hora' => 'hora', 'modalidad' => 'modalidad'] as $k => $e) {
            if (trim((string) ($s[$k] ?? '')) === '') {
                $faltan[] = $e;
            }
        }
        return $faltan;
    }

    public static function hayExternos(array $t): bool
    {
        foreach ($t['datos']['sinodo']['miembros'] ?? [] as $m) {
            if (!empty($m['externo']) && trim((string) ($m['nombre'] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }

    /** Estado de un documento firmable: sin_emitir | en_firma | firmado. */
    public static function estadoFirma(array $firmas, string $doc): string
    {
        if (!isset($firmas[$doc])) {
            return 'sin_emitir';
        }
        return $firmas[$doc]['completo'] ? 'firmado' : 'en_firma';
    }

    /** Primera firma pendiente (ya enviada) de un documento, para mostrar la espera. */
    public static function primeraPendiente(array $firmas, string $doc, ?string $rol = null): ?array
    {
        foreach ($firmas[$doc]['firmas'] ?? [] as $f) {
            if ($f['estado'] === 'pendiente' && ($rol === null || $f['rol'] === $rol)) {
                return $f;
            }
        }
        return null;
    }

    /**
     * @param array<string,array> $docs documentos vigentes por clave
     * @param array<string,array> $hitos
     * @param array<string,array> $generados emisión vigente por clave
     * @param array<string,array> $firmas Services\Firmas::estado()
     */
    public static function evaluar(array $t, array $docs, array $hitos, array $generados, array $firmas = []): array
    {
        $nivel = (string) ($t['datos']['interesado']['nivel'] ?? $t['nivel']);
        $art53 = self::art53($t);
        $faltanDatos = self::faltantesInteresado($t);

        // Requisitos del momento 1.
        $reqs = [];
        $listos = 0;
        $total = 0;
        $porSubir = [];
        $devueltos = [];
        $enRevision = 0;
        $fisicosPend = [];
        $sinGenerar = [];
        $enFirma = [];
        foreach (Catalogo::requisitos() as $clave => $r) {
            if (!Catalogo::aplica($r, $nivel, $art53)) {
                continue;
            }
            $doc = $docs[$clave] ?? null;
            $formato = (string) ($r['formato'] ?? '');
            $ef = $formato !== '' ? self::estadoFirma($firmas, $formato) : 'sin_emitir';
            if ($r['tipo'] === 'pdf') {
                $estado = $doc ? (string) $doc['estado'] : 'pendiente';
            } elseif ($r['tipo'] === 'fisico') {
                $estado = $doc && $doc['estado'] === 'recibido' ? 'recibido' : 'pendiente';
            } elseif ($r['tipo'] === 'firma') {
                $estado = match ($ef) {
                    'firmado' => 'listo',
                    'en_firma' => 'en_firma',
                    default => $faltanDatos ? 'pendiente' : 'por_pedir',
                };
            } elseif ($formato === 'datos_interesado') {
                $estado = $faltanDatos ? 'pendiente' : ($ef === 'firmado' ? 'listo' : 'por_firmar');
            } else {
                $estado = match ($ef) {
                    'firmado' => 'listo',
                    'en_firma' => 'en_firma',
                    default => ($r['cuenta'] ?? true) ? 'pendiente' : 'al_final',
                };
            }
            $listo = in_array($estado, ['validado', 'recibido', 'listo'], true);
            $cuenta = $r['cuenta'] ?? true;
            if ($cuenta) {
                $total++;
                $listos += $listo ? 1 : 0;
                if (!$listo) {
                    match (true) {
                        $estado === 'devuelto' => $devueltos[] = $clave,
                        $estado === 'subido' => $enRevision++,
                        $estado === 'en_firma' => $enFirma[] = $clave,
                        $r['tipo'] === 'pdf' => $porSubir[] = $clave,
                        $r['tipo'] === 'fisico' => $fisicosPend[] = $clave,
                        default => $sinGenerar[] = $clave,
                    };
                }
            }
            $reqs[$clave] = $r + ['clave' => $clave, 'estado' => $estado, 'doc' => $doc, 'listo' => $listo];
        }

        $faltanSinodo = self::faltantesSinodo($t);
        $externos = self::hayExternos($t);
        $propuestaEnviada = isset($firmas['solicitud'], $firmas['visto_bueno']);
        $firmasM2 = $propuestaEnviada && $firmas['solicitud']['completo'] && $firmas['visto_bueno']['completo'];
        $fechaExamen = Services\Firmas::fechaExamen($t, $hitos);
        $vigencias = Services\Firmas::vigenciasInsuficientes($firmas, $fechaExamen);
        $respuestasNo = Services\Firmas::respuestasNo($firmas);
        $cotejoFirmado = self::estadoFirma($firmas, 'cotejo') === 'firmado';

        $hecho = [
            1 => isset($hitos['cotejo']) && $cotejoFirmado,
            2 => isset($hitos['programacion']),
            3 => isset($hitos['programacion']) && (self::estadoFirma($firmas, 'acuse') === 'firmado' || isset($hitos['acuse_cierre'])),
            4 => isset($hitos['expediente']),
        ];
        $actual = 4;
        foreach ($hecho as $n => $ok) {
            if (!$ok) {
                $actual = $n;
                break;
            }
        }
        $completo = $hecho[4];

        $ev = [
            'requisitos' => $reqs, 'listos' => $listos, 'total' => $total, 'faltanDatos' => $faltanDatos,
            'porSubir' => $porSubir, 'devueltos' => $devueltos, 'enRevision' => $enRevision, 'fisicosPend' => $fisicosPend,
            'sinGenerar' => $sinGenerar, 'enFirma' => $enFirma, 'requisitosCompletos' => $listos === $total && !$faltanDatos,
            'faltanSinodo' => $faltanSinodo, 'externos' => $externos, 'externosAlta' => isset($hitos['externos']),
            'propuestaEnviada' => $propuestaEnviada, 'firmasM2' => $firmasM2, 'vigencias' => $vigencias, 'respuestasNo' => $respuestasNo,
            'avisosVigencia' => Services\Firmas::avisosVigencia($firmas, $fechaExamen), 'fechaExamen' => $fechaExamen,
            'hecho' => $hecho, 'actual' => $completo ? 5 : $actual, 'completo' => $completo,
            'hitos' => $hitos, 'generados' => $generados, 'firmas' => $firmas, 'nivel' => $nivel, 'art53' => $art53,
            'invitacionesListas' => isset($firmas['invitaciones'], $firmas['acuse']),
            'invitacionesFirmadas' => self::estadoFirma($firmas, 'invitaciones') === 'firmado' && Services\Firmas::rolCompleto($firmas, 'acuse', 'coordinacion'),
            'aprobado' => in_array((string) ($hitos['resultado']['datos']['resultado'] ?? ''), ['unanimidad', 'mayoria'], true),
            'folioSicde' => trim((string) ($t['datos']['cierre']['folio_sicde'] ?? '')),
        ];
        $ev['paso'] = self::siguientePaso($t, $ev);
        return $ev;
    }

    /** Momento al que corresponde guardar en pos_tramites.momento (5 = concluido se guarda como 4). */
    public static function momentoGuardado(array $ev): int
    {
        return min(4, (int) $ev['actual']);
    }

    /**
     * Una sola acción principal para el egresado. «reenviar»: id de la firma cuya espera se muestra (botón «Reenviar enlace»).
     * @return array{titulo:string,texto:string,turno:string,ancla:?string,boton:?string,reenviar:?int}
     */
    public static function siguientePaso(array $t, array $ev): array
    {
        $reqs = $ev['requisitos'];
        $firmas = $ev['firmas'];
        $p = static fn(string $titulo, string $texto, string $turno = 'tuyo', ?string $ancla = null, ?string $boton = null, ?int $reenviar = null): array
            => compact('titulo', 'texto', 'turno', 'ancla', 'boton', 'reenviar');
        $espera = static fn(array $f): string => Services\Firmas::espera($f);

        if ($t['estado'] === 'cancelado') {
            return $p('Trámite cancelado', 'Este trámite ya no está activo. Si necesitas retomarlo, comunícate con Apoyo a Grado.', 'nadie');
        }
        if ($t['estado'] === 'abandonado') {
            return $p('Trámite en pausa', 'El egresado dejó este trámite en pausa. Se conserva todo y puede retomarlo donde se quedó; mientras tanto nadie puede firmar en él.', 'nadie');
        }
        if ($ev['completo']) {
            return $p('¡Felicidades! Tu trámite concluyó', 'Apoyo a Grado integró tu expediente. Recuerda que tu director debe subir tu tesis al Repositorio Institucional.', 'nadie');
        }
        switch ($ev['actual']) {
            case 1:
                if ($ev['faltanDatos']) {
                    return $p('Captura tus datos', 'Empieza por tus datos y los de tu director (con su correo, para pedirle su firma en línea). Te faltan ' . count($ev['faltanDatos']) . ' campos.', 'tuyo', 'datos', 'Capturar mis datos');
                }
                if ($ev['devueltos']) {
                    $r = $reqs[$ev['devueltos'][0]];
                    return $p('Corrige: ' . $r['nombre'], 'Apoyo a Grado te devolvió este documento con una observación. Léela, corrígelo y vuelve a subirlo.', 'tuyo', 'req-' . $r['clave'], 'Ver la observación');
                }
                if (($reqs['voto_aprobatorio']['estado'] ?? '') === 'por_pedir') {
                    return $p('Pide el voto aprobatorio a tu director', 'Con tus datos llenamos el formato. Pulsa el botón y tu director recibirá por correo un enlace personal para firmarlo en línea; no hay que imprimir ni subir nada.', 'tuyo', 'req-voto_aprobatorio', 'Ir al voto aprobatorio');
                }
                if (($reqs['datos_interesado']['estado'] ?? '') === 'por_firmar') {
                    return $p('Firma tu formato de datos del interesado', 'Revisa el formato con tus datos y fírmalo en línea con un botón.', 'tuyo', 'req-datos_interesado', 'Ir a mi formato');
                }
                if ($ev['porSubir']) {
                    $r = $reqs[$ev['porSubir'][0]];
                    $n = count($ev['porSubir']);
                    return $p('Sube tus documentos en PDF', 'Te ' . ($n === 1 ? 'falta 1 documento' : 'faltan ' . $n . ' documentos') . '. El siguiente es «' . $r['nombre'] . '». Puedes subirlos en el orden que quieras.', 'tuyo', 'req-' . $r['clave'], 'Subir «' . $r['nombre'] . '»');
                }
                if (($reqs['voto_aprobatorio']['estado'] ?? '') === 'en_firma' && ($f = self::primeraPendiente($firmas, 'voto')) !== null) {
                    return $p($espera($f), 'Tu director recibió por correo su enlace para firmar el voto aprobatorio. Si no lo encuentra, revisa que su correo esté bien escrito o reenvíale el enlace.', 'espera', 'req-voto_aprobatorio', null, (int) $f['id']);
                }
                if ($ev['enRevision'] > 0) {
                    return $p('Apoyo a Grado revisa tus documentos', 'Ya subiste todo. Mientras los validan, prepara tus originales y copias, las fotografías, el resumen impreso y las tres USB para el cotejo.', 'personal', 'requisitos');
                }
                if (($reqs['no_adeudo_mobiliario']['estado'] ?? '') === 'en_firma') {
                    return $p('La Coordinación firma tu constancia de no adeudo', 'Apoyo a Grado ya emitió tu constancia de no adeudo de mobiliario y equipo; aparecerá aquí en cuanto la firme la Coordinación de Estudios Avanzados.', 'personal', 'requisitos');
                }
                if (in_array('no_adeudo_mobiliario', $ev['sinGenerar'], true)) {
                    return $p('Pide tu constancia de no adeudo', 'Tus PDF están validados. Pide a Apoyo a Grado tu constancia de no adeudo de mobiliario y equipo y lleva tus originales para el cotejo.', 'personal', 'requisitos');
                }
                if (isset($ev['hitos']['cotejo'])) {
                    return $p('La Coordinación firma tu oficio de cotejo', 'Apoyo a Grado ya cotejó tus originales. En cuanto la Coordinación de Estudios Avanzados firme el oficio de cotejo podrás proponer tu sínodo.', 'personal', 'requisitos');
                }
                return $p('Lleva tus originales para el cotejo', 'Tus documentos están validados. Entrega en persona a Apoyo a Grado tus originales y copias, las fotografías, el resumen impreso y las tres USB.', 'tuyo', 'requisitos');
            case 2:
                if ($ev['faltanSinodo']) {
                    return $p('Propón tu sínodo y la fecha de tu examen', 'Captura a los cinco propietarios y los dos suplentes con su correo, la fecha y la hora. Elige una fecha con al menos ' . Catalogo::DIAS_MINIMOS . ' días hábiles de anticipación (te sugerimos ' . Catalogo::DIAS_SUGERIDOS . ').', 'tuyo', 'sinodo', 'Proponer sínodo y fecha');
                }
                if (!$ev['propuestaEnviada']) {
                    return $p('Envía tu propuesta a firma', 'Tu propuesta está completa. Al enviarla, tu director recibe la solicitud y cada integrante el visto bueno, con un enlace personal para firmar en línea.', 'tuyo', 'sinodo', 'Revisar y enviar mi propuesta');
                }
                if ($ev['vigencias']) {
                    $v = $ev['vigencias'][0];
                    $f = null;
                    foreach ($firmas['visto_bueno']['firmas'] ?? [] as $x) {
                        $f = (int) $x['orden'] === $v['orden'] ? $x : $f;
                    }
                    return $p($ev['avisosVigencia'][0] ?? 'Una firma UAEMéx debe renovarse', 'Pide a ' . $v['nombre'] . ' que renueve su firma electrónica en ' . \App\Modules\Perfil\FirmaUaemex::URL . ' y actualice la fecha desde su enlace. Mientras tanto no se puede programar tu examen.', 'espera', 'firmas-m2', null, $f ? (int) $f['id'] : null);
                }
                if (!$ev['firmasM2']) {
                    $f = self::primeraPendiente($firmas, 'solicitud') ?? self::primeraPendiente($firmas, 'visto_bueno');
                    $faltan = array_map(static fn(array $x): string => $x['nombre'], array_filter(array_merge($firmas['solicitud']['firmas'] ?? [], $firmas['visto_bueno']['firmas'] ?? []), static fn(array $x): bool => $x['estado'] === 'pendiente'));
                    return $p($f ? $espera($f) : 'Esperando firmas', 'Aún esperan firmar: ' . implode(', ', array_unique($faltan)) . '. Cada quien recibió su enlace por correo; puedes reenviarlo o corregir su correo abajo.', 'espera', 'firmas-m2', null, $f ? (int) $f['id'] : null);
                }
                if ($ev['respuestasNo']) {
                    return $p('Apoyo a Grado revisa tu propuesta', $ev['respuestasNo'][0]['nombre'] . ' contestó «No» en el visto bueno. Apoyo a Grado decidirá si la valida o te la devuelve para ajustarla.', 'personal', 'firmas-m2');
                }
                if ($ev['externos'] && !$ev['externosAlta']) {
                    return $p('Apoyo a Grado verifica a tus sinodales externos', 'Revisan que estén dados de alta en Control Escolar. Si falta algún documento del profesor, te lo pedirán a ti y a tu director.', 'personal', 'sinodo');
                }
                return $p('Apoyo a Grado programa tu examen', 'Ya firmaron tu director y tu sínodo. Apoyo a Grado confirmará la fecha, la hora y el lugar.', 'personal', 'sinodo');
            case 3:
                if (!$ev['invitacionesListas']) {
                    return $p('Apoyo a Grado prepara tus invitaciones', 'Tu examen ya está programado. Apoyo a Grado emitirá los oficios de invitación, el acuse y la notificación a tu director.', 'personal', 'invitaciones');
                }
                if (!$ev['invitacionesFirmadas']) {
                    return $p('La Coordinación firma tus invitaciones', 'En cuanto la Coordinación de Estudios Avanzados firme los oficios, cada integrante de tu sínodo recibirá el suyo por correo para acusar de recibido en línea.', 'personal', 'invitaciones');
                }
                $f = self::primeraPendiente($firmas, 'acuse', 'sinodal');
                if ($f !== null) {
                    return $p('Esperando el acuse de recibido de ' . $f['nombre'] . ($f['enviada_en'] ? ' (enviada el ' . date('d/m/Y', strtotime((string) $f['enviada_en'])) . ')' : ''),
                        'Cada integrante recibió su invitación por correo y acusa de recibido en línea. Entrégales tu trabajo final; puedes reenviarles el enlace abajo.', 'espera', 'invitaciones', null, (int) $f['id']);
                }
                return $p('Apoyo a Grado cierra las invitaciones', 'Tu sínodo ya recibió sus invitaciones.', 'personal', 'invitaciones');
            default:
                $prog = $ev['hitos']['programacion']['datos'] ?? [];
                if (!isset($ev['hitos']['resultado'])) {
                    return $p('Presenta tu examen', 'Tu examen es el ' . self::fechaLarga((string) ($prog['fecha'] ?? '')) . ' a las ' . ($prog['hora'] ?? '') . ' horas, ' . (($prog['modalidad'] ?? '') === 'virtual' ? 'en modalidad virtual' : 'en ' . ($prog['lugar'] ?? 'el lugar indicado')) . '. ¡Mucho éxito!', 'tuyo', 'cierre');
                }
                if ($ev['aprobado'] && $ev['folioSicde'] === '') {
                    return $p('Registra tu grado en el SICDE', 'Tienes 48 horas después del examen para registrar tu grado electrónico en el portal de alumnos de Control Escolar. Después captura aquí el folio de seguimiento.', 'tuyo', 'cierre', 'Capturar mi folio');
                }
                return $p('Apoyo a Grado integra tu expediente', 'Emitirán las constancias de tu sínodo y el formato de integración de expediente; la Coordinación las firma en línea y aparecerán aquí. Te avisaremos al concluir.', 'personal', 'cierre');
        }
    }

    /** «viernes 2 de octubre de 2026» */
    public static function fechaLarga(string $iso): string
    {
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $iso);
        if (!$f) {
            return '';
        }
        return Catalogo::DIAS[(int) $f->format('N')] . ' ' . $f->format('d') . ' de ' . Catalogo::MESES[(int) $f->format('n')] . ' de ' . $f->format('Y');
    }
}
