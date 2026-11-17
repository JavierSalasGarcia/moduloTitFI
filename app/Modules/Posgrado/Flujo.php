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

    /** @return string[] lo que falta en la propuesta de sínodo y fecha */
    public static function faltantesSinodo(array $t): array
    {
        $s = $t['datos']['sinodo'] ?? [];
        $faltan = [];
        $miembros = $s['miembros'] ?? [];
        for ($i = 0; $i < 7; $i++) {
            $m = $miembros[$i] ?? [];
            if (trim((string) ($m['nombre'] ?? '')) === '' || trim((string) ($m['tipo'] ?? '')) === '') {
                $faltan[] = ($i < 5 ? 'propietario ' . ($i + 1) : 'suplente ' . ($i - 4));
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

    /**
     * @param array<string,array> $docs documentos vigentes por clave
     * @param array<string,array> $hitos
     * @param array<string,array> $generados última emisión por clave
     */
    public static function evaluar(array $t, array $docs, array $hitos, array $generados): array
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
        foreach (Catalogo::requisitos() as $clave => $r) {
            if (!Catalogo::aplica($r, $nivel, $art53)) {
                continue;
            }
            $doc = $docs[$clave] ?? null;
            $estado = 'pendiente';
            if ($r['tipo'] === 'pdf') {
                $estado = $doc ? (string) $doc['estado'] : 'pendiente';
            } elseif ($r['tipo'] === 'fisico') {
                $estado = $doc && $doc['estado'] === 'recibido' ? 'recibido' : 'pendiente';
            } else {
                $formato = (string) ($r['formato'] ?? '');
                if ($formato === 'datos_interesado') {
                    $estado = $faltanDatos ? 'pendiente' : 'listo';
                } else {
                    $estado = isset($generados[$formato]) ? 'listo' : (($r['cuenta'] ?? true) ? 'pendiente' : 'al_final');
                }
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
                        $r['tipo'] === 'pdf' => $porSubir[] = $clave,
                        $r['tipo'] === 'fisico' => $fisicosPend[] = $clave,
                        default => $sinGenerar[] = $clave,
                    };
                }
            }
            $reqs[$clave] = $r + ['clave' => $clave, 'estado' => $estado, 'doc' => $doc, 'listo' => $listo];
        }

        $m2docs = [];
        foreach (Catalogo::documentosMomento() as $clave => $d) {
            $doc = $docs[$clave] ?? null;
            $m2docs[$clave] = $d + ['clave' => $clave, 'estado' => $doc ? (string) $doc['estado'] : 'pendiente', 'doc' => $doc];
        }

        $faltanSinodo = self::faltantesSinodo($t);
        $externos = self::hayExternos($t);
        $m2listos = ['solicitud_firmada', 'visto_bueno_firmado', 'comprobantes_sicde'];
        $m2validados = count(array_filter($m2listos, static fn(string $c): bool => $m2docs[$c]['estado'] === 'validado')) === count($m2listos);

        $hecho = [
            1 => isset($hitos['cotejo']),
            2 => isset($hitos['programacion']),
            3 => isset($hitos['programacion']) && $m2docs['acuse_firmado']['estado'] === 'validado',
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
            'sinGenerar' => $sinGenerar, 'requisitosCompletos' => $listos === $total && !$faltanDatos,
            'm2docs' => $m2docs, 'faltanSinodo' => $faltanSinodo, 'externos' => $externos, 'externosAlta' => isset($hitos['externos']),
            'm2validados' => $m2validados, 'hecho' => $hecho, 'actual' => $completo ? 5 : $actual, 'completo' => $completo,
            'hitos' => $hitos, 'generados' => $generados, 'nivel' => $nivel, 'art53' => $art53,
            'invitacionesListas' => isset($generados['invitaciones'], $generados['acuse']),
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
     * Una sola acción principal para el egresado.
     * @return array{titulo:string,texto:string,turno:string,ancla:?string,boton:?string}
     */
    public static function siguientePaso(array $t, array $ev): array
    {
        $reqs = $ev['requisitos'];
        $p = static fn(string $titulo, string $texto, string $turno = 'tuyo', ?string $ancla = null, ?string $boton = null): array
            => compact('titulo', 'texto', 'turno', 'ancla', 'boton');

        if ($t['estado'] === 'cancelado') {
            return $p('Trámite cancelado', 'Este trámite ya no está activo. Si necesitas retomarlo, comunícate con Apoyo a Grado.', 'nadie');
        }
        if ($ev['completo']) {
            return $p('¡Felicidades! Tu trámite concluyó', 'Apoyo a Grado integró tu expediente. Recuerda que tu director debe subir tu tesis al Repositorio Institucional.', 'nadie');
        }
        switch ($ev['actual']) {
            case 1:
                if ($ev['faltanDatos']) {
                    return $p('Captura tus datos', 'Empieza por tus datos y los de tu director: con ellos llenamos tus formatos. Te faltan ' . count($ev['faltanDatos']) . ' campos.', 'tuyo', 'datos', 'Capturar mis datos');
                }
                if ($ev['devueltos']) {
                    $r = $reqs[$ev['devueltos'][0]];
                    return $p('Corrige: ' . $r['nombre'], 'Apoyo a Grado te devolvió este documento con una observación. Léela, corrígelo y vuelve a subirlo.', 'tuyo', 'req-' . $r['clave'], 'Ver la observación');
                }
                if ($ev['porSubir']) {
                    $r = $reqs[$ev['porSubir'][0]];
                    $n = count($ev['porSubir']);
                    if ($r['clave'] === 'voto_aprobatorio') {
                        return $p('Recaba el voto aprobatorio de tu director', 'Descarga el formato ya llenado, pídele que lo firme y súbelo en PDF.' . ($n > 1 ? ' Te faltan ' . $n . ' documentos en total.' : ''), 'tuyo', 'req-voto_aprobatorio', 'Ir al voto aprobatorio');
                    }
                    return $p('Sube tus documentos en PDF', 'Te ' . ($n === 1 ? 'falta 1 documento' : 'faltan ' . $n . ' documentos') . '. El siguiente es «' . $r['nombre'] . '». Puedes subirlos en el orden que quieras.', 'tuyo', 'req-' . $r['clave'], 'Subir «' . $r['nombre'] . '»');
                }
                if ($ev['enRevision'] > 0) {
                    return $p('Apoyo a Grado revisa tus documentos', 'Ya subiste todo. Mientras los validan, prepara tus originales y copias, las fotografías, el resumen impreso y las tres USB para el cotejo.', 'personal', 'requisitos');
                }
                if (in_array('no_adeudo_mobiliario', $ev['sinGenerar'], true)) {
                    return $p('Pide tu constancia de no adeudo', 'Tus PDF están validados. Pide a Apoyo a Grado tu constancia de no adeudo de mobiliario y equipo y lleva tus originales para el cotejo.', 'personal', 'requisitos');
                }
                return $p('Lleva tus originales para el cotejo', 'Tus documentos están validados. Entrega en persona a Apoyo a Grado tus originales y copias, las fotografías, el resumen impreso, las tres USB y tu formato de datos firmado.', 'tuyo', 'requisitos');
            case 2:
                if ($ev['faltanSinodo']) {
                    return $p('Propón tu sínodo y la fecha de tu examen', 'Captura a los cinco propietarios, los dos suplentes, la fecha y la hora. Elige una fecha con al menos ' . Catalogo::DIAS_MINIMOS . ' días hábiles de anticipación (te sugerimos ' . Catalogo::DIAS_SUGERIDOS . ').', 'tuyo', 'sinodo', 'Proponer sínodo y fecha');
                }
                foreach (['solicitud_firmada', 'visto_bueno_firmado', 'comprobantes_sicde'] as $c) {
                    $d = $ev['m2docs'][$c];
                    if ($d['estado'] === 'devuelto') {
                        return $p('Corrige: ' . $d['nombre'], 'Apoyo a Grado te devolvió este documento con una observación. Corrígelo y vuelve a subirlo.', 'tuyo', 'doc-' . $c, 'Ver la observación');
                    }
                }
                foreach (['solicitud_firmada', 'visto_bueno_firmado', 'comprobantes_sicde'] as $c) {
                    $d = $ev['m2docs'][$c];
                    if ($d['estado'] === 'pendiente') {
                        return $p('Recaba firmas y sube: ' . $d['nombre'], $d['ayuda'], 'tuyo', 'doc-' . $c, 'Ir al documento');
                    }
                }
                if (!$ev['m2validados']) {
                    return $p('Apoyo a Grado revisa tu solicitud', 'Ya subiste la solicitud, el visto bueno y los comprobantes. Te avisaremos cuando estén validados.', 'personal', 'sinodo');
                }
                if ($ev['externos'] && !$ev['externosAlta']) {
                    return $p('Apoyo a Grado verifica a tus sinodales externos', 'Revisan que estén dados de alta en Control Escolar. Si falta algún documento del profesor, te lo pedirán a ti y a tu director.', 'personal', 'sinodo');
                }
                return $p('Apoyo a Grado programa tu examen', 'Tu solicitud está completa. Apoyo a Grado confirmará la fecha, la hora y el lugar.', 'personal', 'sinodo');
            case 3:
                if (!$ev['invitacionesListas']) {
                    return $p('Apoyo a Grado prepara tus invitaciones', 'Tu examen ya está programado. En cuanto emitan los oficios de invitación y el acuse, podrás descargarlos aquí.', 'personal', 'invitaciones');
                }
                $a = $ev['m2docs']['acuse_firmado'];
                if ($a['estado'] === 'devuelto') {
                    return $p('Corrige el acuse firmado', 'Apoyo a Grado te devolvió el acuse con una observación. Corrígelo y vuelve a subirlo.', 'tuyo', 'doc-acuse_firmado', 'Ver la observación');
                }
                if ($a['estado'] === 'pendiente') {
                    return $p('Entrega tus invitaciones y sube el acuse', 'Descarga los oficios, entrégalos a cada integrante con tu trabajo final y sube el acuse firmado al menos dos días antes de tu examen.', 'tuyo', 'invitaciones', 'Ver mis invitaciones');
                }
                return $p('Apoyo a Grado revisa tu acuse', 'Ya subiste el acuse firmado. Te avisaremos cuando quede validado.', 'personal', 'invitaciones');
            default:
                $prog = $ev['hitos']['programacion']['datos'] ?? [];
                if (!isset($ev['hitos']['resultado'])) {
                    return $p('Presenta tu examen', 'Tu examen es el ' . self::fechaLarga((string) ($prog['fecha'] ?? '')) . ' a las ' . ($prog['hora'] ?? '') . ' horas, ' . (($prog['modalidad'] ?? '') === 'virtual' ? 'en modalidad virtual' : 'en ' . ($prog['lugar'] ?? 'el lugar indicado')) . '. ¡Mucho éxito!', 'tuyo', 'cierre');
                }
                if ($ev['aprobado'] && $ev['folioSicde'] === '') {
                    return $p('Registra tu grado en el SICDE', 'Tienes 48 horas después del examen para registrar tu grado electrónico en el portal de alumnos de Control Escolar. Después captura aquí el folio de seguimiento.', 'tuyo', 'cierre', 'Capturar mi folio');
                }
                return $p('Apoyo a Grado integra tu expediente', 'Emitirán las constancias de tu sínodo y el formato de integración de expediente. Te avisaremos al concluir.', 'personal', 'cierre');
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
