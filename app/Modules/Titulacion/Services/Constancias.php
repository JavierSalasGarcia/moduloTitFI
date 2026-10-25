<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Core\Persona;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;

/**
 * Constancias para profesores que se generan solas cuando se cumple lo que avalan: revisor de protocolo (al aprobarse
 * el protocolo); y al registrarse el resultado de la evaluación con trabajo escrito, las de director y codirector del
 * trabajo, la de cumplimiento y el nombramiento como director, y la de cada sinodal propietario (sínodo y revisión del
 * trabajo escrito). Las de la Subdirección las firma el Subdirector; las de la Jefatura, el Jefe de Evaluación
 * Profesional y Grado. Su texto se edita en «Constancias → Textos» con marcadores que se sustituyen al generarlas.
 */
final class Constancias
{
    /** tipo => [título, rol impreso, quién la emite y firma] */
    public const TIPOS = [
        'revisor_protocolo' => ['Revisor de protocolo', 'Revisor(a) de protocolo', 'subdirector'],
        'asesor_trabajo' => ['Director de trabajo escrito', 'Director(a) del trabajo escrito para evaluación profesional', 'subdirector'],
        'coasesor_trabajo' => ['Codirector de trabajo escrito', 'Codirector(a) del trabajo escrito para evaluación profesional', 'subdirector'],
        'sinodal' => ['Sinodal y revisor del trabajo escrito', 'Sinodal de evaluación profesional', 'subdirector'],
        'cumplimiento_director' => ['Constancia de cumplimiento como director', 'Director(a) del trabajo escrito', 'jefe_titulacion'],
        'nombramiento_director' => ['Nombramiento como director', 'Director(a) del trabajo escrito', 'jefe_titulacion'],
    ];

    public const EMISORES = ['subdirector' => 'Subdirección Académica', 'jefe_titulacion' => 'Departamento de Evaluación Profesional y Grado'];

    private const INTRO_SUB = 'Quien suscribe, {firmante}, {cargo_firmante} de la Facultad de Ingeniería de la Universidad Autónoma del Estado de México, mediante la presente hace constar que {el_profesor}:';
    private const INTRO_JEFE = '{suscribe}, {firmante}, {cargo_firmante} de la Facultad de Ingeniería de la Universidad Autónoma del Estado de México, otorga {la_presente} {al_profesor}:';
    private const CIERRE_SUB = 'Para los fines que {el_interesado} considere convenientes, se extiende la presente CONSTANCIA {fecha_letras}.';

    /** Textos de fábrica: asunto, introducción, cuerpo (párrafos separados por una línea en blanco), cierre y si lleva tabla. */
    public const TEXTOS = [
        'revisor_protocolo' => ['asunto' => 'CONSTANCIA', 'intro' => self::INTRO_SUB, 'tabla' => 1, 'cierre' => self::CIERRE_SUB,
            'cuerpo' => 'Participó {periodo} en el tema que a continuación se relaciona, cumpliendo con las actividades de su nombramiento como {rol}, integrante del Comité de Revisión de Protocolos de la Licenciatura en {licenciatura} ({vueltas}).'],
        'asesor_trabajo' => ['asunto' => 'CONSTANCIA', 'intro' => self::INTRO_SUB, 'tabla' => 1, 'cierre' => self::CIERRE_SUB,
            'cuerpo' => 'Participó {periodo} en el tema que a continuación se relaciona, cumpliendo con las actividades de su nombramiento como {rol}.'],
        'coasesor_trabajo' => ['asunto' => 'CONSTANCIA', 'intro' => self::INTRO_SUB, 'tabla' => 1, 'cierre' => self::CIERRE_SUB,
            'cuerpo' => 'Participó {periodo} en el tema que a continuación se relaciona, cumpliendo con las actividades de su nombramiento como {rol}.'],
        'sinodal' => ['asunto' => 'CONSTANCIA', 'intro' => self::INTRO_SUB, 'tabla' => 0, 'cierre' => self::CIERRE_SUB,
            'cuerpo' => 'Participó como parte del sínodo, con la función de {funcion}, en la evaluación profesional de {pasante} (No. de cuenta {cuenta}), pasante de la Licenciatura en {licenciatura}, en la opción de {modalidad}, celebrada el {fecha_examen}; y como {revisor} del trabajo escrito «{titulo}».'],
        'cumplimiento_director' => ['asunto' => 'CONSTANCIA', 'intro' => self::INTRO_JEFE, 'tabla' => 0,
            'cuerpo' => 'Quien participó como {director} del tema de {modalidad} «{titulo}» que presentó {el_pasante} {pasante} para su Evaluación Profesional de la Licenciatura de {licenciatura} el día {fecha_examen}, cumpliendo en tiempo y forma con las actividades encomendadas.',
            'cierre' => 'A petición {del_interesado} y para los fines legales a que haya lugar, se extiende la presente CONSTANCIA en la Ciudad de Toluca de Lerdo, Estado de México, {fecha_letras}.'],
        'nombramiento_director' => ['asunto' => 'NOMBRAMIENTO', 'intro' => self::INTRO_JEFE, 'tabla' => 0,
            'cuerpo' => 'Como {director} del tema de {modalidad} «{titulo}» que presentó {el_pasante} {pasante} para su Evaluación Profesional de la Licenciatura de {licenciatura} el día {fecha_examen}, cumpliendo en tiempo y forma con las actividades encomendadas.',
            'cierre' => 'A petición {del_interesado} y para los fines legales a que haya lugar, se extiende la presente constancia de NOMBRAMIENTO en la Ciudad de Toluca de Lerdo, Estado de México, {fecha_letras}.'],
    ];

    /** Marcadores disponibles en los textos => qué se escribe en su lugar. */
    public const MARCADORES = [
        '{profesor}' => 'Grado y nombre del profesor', '{el_profesor}' => '«el profesor» / «la profesora»', '{al_profesor}' => '«al» / «a la»',
        '{el_interesado}' => '«el interesado» / «la interesada»', '{del_interesado}' => '«del interesado» / «de la interesada»',
        '{rol}' => 'Rol que avala la constancia (en mayúsculas)', '{director}' => '«DIRECTOR» / «DIRECTORA»', '{funcion}' => 'presidente, secretario(a) o vocal',
        '{revisor}' => '«revisor» / «revisora»', '{pasante}' => 'Nombre del pasante', '{el_pasante}' => '«el alumno» / «la alumna»', '{cuenta}' => 'Número de cuenta',
        '{licenciatura}' => 'Licenciatura', '{modalidad}' => 'Opción de evaluación profesional', '{titulo}' => 'Título del trabajo escrito',
        '{fecha_examen}' => 'Fecha de la evaluación profesional', '{periodo}' => '«el 3 de mayo de 2026» o «del … al …»', '{vueltas}' => 'Vueltas de revisión del protocolo',
        '{acta}' => 'Número de acta', '{firmante}' => 'Quien firma', '{cargo_firmante}' => 'Su cargo', '{suscribe}' => '«El que suscribe» / «La que suscribe»',
        '{la_presente}' => '«la presente constancia de cumplimiento» o «el presente nombramiento»', '{fecha_letras}' => 'Fecha de hoy en letras',
    ];

    /** Texto vigente de un tipo: el editado o el de fábrica. */
    public static function texto(string $tipo): array
    {
        $base = self::TEXTOS[$tipo];
        $f = Database::una('SELECT asunto, intro, cuerpo, cierre, tabla FROM tit_constancia_textos WHERE tipo = ?', [$tipo]);
        return $f ? ['asunto' => (string) $f['asunto'], 'intro' => (string) $f['intro'], 'cuerpo' => (string) $f['cuerpo'], 'cierre' => (string) $f['cierre'], 'tabla' => (int) $f['tabla'], 'editado' => true] : $base + ['editado' => false];
    }

    public static function guardarTexto(string $tipo, array $t, int $usuarioId): void
    {
        Database::ejecutar(
            'INSERT INTO tit_constancia_textos (tipo, asunto, intro, cuerpo, cierre, tabla, actualizado_por) VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE asunto = VALUES(asunto), intro = VALUES(intro), cuerpo = VALUES(cuerpo), cierre = VALUES(cierre), tabla = VALUES(tabla), actualizado_por = VALUES(actualizado_por), actualizado_en = NOW()',
            [$tipo, $t['asunto'], $t['intro'], $t['cuerpo'], $t['cierre'], (int) $t['tabla'], $usuarioId]
        );
    }

    public static function restaurarTexto(string $tipo): void
    {
        Database::ejecutar('DELETE FROM tit_constancia_textos WHERE tipo = ?', [$tipo]);
    }

    /** Quién puede editar los textos: administración, Subdirección y Jefatura de Evaluación Profesional. */
    public static function puedeEditarTextos(): bool
    {
        return \App\Core\Auth::tieneRol('admin') || SubdireccionFirma::esSubdirector() || SubdireccionFirma::esJefeTitulacion();
    }

    /** Sustituye los marcadores. @param array $v valores ya calculados (ver valores()) */
    public static function sustituir(string $texto, array $v): string
    {
        return strtr($texto, $v);
    }

    /**
     * Valores de los marcadores para una constancia.
     * @param array $x datos del trámite (FormatosOficiales::datos o equivalente histórico)
     * @param array $d detalle: desde, hasta, vueltas, funcion, acta
     */
    public static function valores(string $tipo, array $x, array $profesor, array $pasante, array $d, array $firmante, string $cargoFirmante): array
    {
        $f = static fn(array $p, string $m, string $fe): string => Persona::f($p, $m, $fe);
        $desde = (string) ($d['desde'] ?? $d['hasta'] ?? '');
        $hasta = (string) ($d['hasta'] ?? '');
        return [
            '{profesor}' => Persona::tratamiento($profesor), '{el_profesor}' => $f($profesor, 'el profesor', 'la profesora'), '{al_profesor}' => $f($profesor, 'al', 'a la'),
            '{el_interesado}' => $f($profesor, 'el interesado', 'la interesada'), '{del_interesado}' => $f($profesor, 'del interesado', 'de la interesada'),
            '{rol}' => str_replace('(A)', $f($profesor, '', 'A'), mb_strtoupper(self::TIPOS[$tipo][1])), '{director}' => $f($profesor, 'DIRECTOR', 'DIRECTORA'),
            '{funcion}' => ($d['funcion'] ?? '') !== '' ? Oficios::funcionJurado((string) $d['funcion'], $profesor) : 'sinodal',
            '{revisor}' => $f($profesor, 'revisor', 'revisora'), '{pasante}' => (string) $x['nombre'], '{el_pasante}' => $f($pasante, 'el alumno', 'la alumna'),
            '{cuenta}' => (string) $x['cuenta'], '{licenciatura}' => (string) $x['carrera'], '{modalidad}' => (string) $x['modalidad_nombre'], '{titulo}' => (string) $x['titulo'],
            '{fecha_examen}' => Oficios::fecha($hasta), '{periodo}' => $desde === $hasta || $desde === '' ? 'el ' . Oficios::fecha($hasta) : 'del ' . Oficios::fecha($desde) . ' al ' . Oficios::fecha($hasta),
            '{vueltas}' => self::vueltasTexto((string) ($d['vueltas'] ?? '')), '{acta}' => (string) ($d['acta'] ?? ''),
            '{firmante}' => Persona::tratamiento($firmante), '{cargo_firmante}' => $cargoFirmante, '{suscribe}' => $f($firmante, 'El que suscribe', 'La que suscribe'),
            '{la_presente}' => $tipo === 'nombramiento_director' ? 'el presente nombramiento' : 'la presente constancia de cumplimiento', '{fecha_letras}' => Oficios::fechaEnLetras(),
        ];
    }

    private static function vueltasTexto(string $v): string
    {
        $n = array_values(array_filter(array_map('intval', explode(',', $v))));
        if (!$n) {
            return 'revisión del protocolo';
        }
        $o = array_map(static fn(int $i): string => Mensajes::ordinal($i), $n);
        return (count($o) > 1 ? implode(', ', array_slice($o, 0, -1)) . ' y ' . end($o) . ' vueltas' : $o[0] . ' vuelta') . ' de revisión';
    }

    /**
     * Genera la constancia si aún no existe para esa persona, trámite y tipo, y la pone en la cola de firmas.
     * @param array $persona ['nombre','email', y si se conocen 'grado','genero']
     * @param array $detalle desde, hasta (fecha que avala), vueltas, funcion, acta
     */
    public static function emitir(array $tramite, string $tipo, array $persona, array $detalle, int $usuarioId): bool
    {
        $tid = (int) $tramite['id'];
        $email = strtolower(trim((string) $persona['email']));
        if (!isset(self::TIPOS[$tipo]) || $email === '' || Database::valor('SELECT id FROM tit_constancias WHERE tramite_id = ? AND email = ? AND tipo = ?', [$tid, $email, $tipo])) {
            return false;
        }
        $p = Persona::completar($persona, $email);
        $emisor = self::TIPOS[$tipo][2];
        [$bytes, $caja] = Oficios::constancia($tramite, $tipo, $p, $detalle, self::texto($tipo));
        $ruta = DocumentosGenerados::guardar($tramite, 'constancia_' . $tipo, $bytes);
        $clave = 'constancia_' . substr(hash('sha256', $tipo . '|' . $email), 0, 16);
        $docId = TitulacionRepository::registrarDocumento($tid, 'constancias', $clave, 'personal', 'aprobado', $ruta, 'constancia_' . $tipo . '_' . $tramite['folio'] . '.pdf', $usuarioId);
        Database::ejecutar(
            'INSERT INTO tit_constancias (tramite_id, email, nombre, tipo, emisor, vueltas, documento_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tid, $email, mb_substr((string) $p['nombre'], 0, 160), $tipo, $emisor, mb_substr((string) ($detalle['vueltas'] ?? ''), 0, 40), $docId]
        );
        SubdireccionFirma::solicitar($tramite, $docId, 'constancia', self::TIPOS[$tipo][0] . ': ' . $p['nombre'], $caja, $emisor);
        return true;
    }

    /** Protocolo aprobado: una constancia por revisor que firmó al menos una vuelta. */
    public static function revisoresProtocolo(array $tramite, string $fechaAprobacion, int $usuarioId): void
    {
        $personas = [];
        foreach (Database::todas(
            "SELECT m.email, m.nombre, r.numero, a.asignada_en FROM tit_revision_asignaciones a
             JOIN tit_revisiones r ON r.id = a.revision_id JOIN tit_comision_miembros m ON m.id = a.miembro_id
             WHERE r.tramite_id = ? AND a.estado = 'firmada' ORDER BY r.numero, a.id",
            [(int) $tramite['id']]
        ) as $f) {
            $e = strtolower((string) $f['email']);
            $personas[$e] ??= ['nombre' => (string) $f['nombre'], 'email' => $e, 'desde' => substr((string) $f['asignada_en'], 0, 10), 'vueltas' => []];
            $personas[$e]['vueltas'][] = (int) $f['numero'];
        }
        foreach ($personas as $p) {
            self::emitir($tramite, 'revisor_protocolo', $p, ['desde' => $p['desde'], 'hasta' => $fechaAprobacion, 'vueltas' => implode(',', array_unique($p['vueltas']))], $usuarioId);
        }
    }

    /**
     * Evaluación profesional con trabajo escrito concluida: constancias del director y codirector (Subdirección),
     * cumplimiento y nombramiento del director (Jefatura) y de cada sinodal propietario del examen definitivo.
     */
    public static function resultado(array $tramite, string $fechaExamen, int $usuarioId): void
    {
        $proto = TitulacionRepository::hitos((int) $tramite['id'])['protocolo']['completado_en'] ?? $tramite['creado_en'];
        $desde = substr((string) $proto, 0, 10);
        $acta = (string) Database::valor("SELECT numero FROM tit_actas WHERE tramite_id = ? AND tipo = 'evaluacion'", [(int) $tramite['id']]);
        foreach (FirmasExternas::asesores($tramite, true) as $a) {
            $tipos = $a['rol'] === 'asesor' ? ['asesor_trabajo', 'cumplimiento_director', 'nombramiento_director'] : ['coasesor_trabajo'];
            foreach ($tipos as $tipo) {
                self::emitir($tramite, $tipo, $a, ['desde' => $desde, 'hasta' => $fechaExamen, 'acta' => $acta], $usuarioId);
            }
        }
        $asig = Database::una("SELECT * FROM tit_asignaciones_jurado WHERE tramite_id = ? AND tipo = 'definitivo' AND estado = 'firmada' ORDER BY id DESC LIMIT 1", [(int) $tramite['id']]);
        if ($asig) {
            foreach (Jurado::sinodales((int) $asig['id']) as $s) {
                if ($s['calidad'] === 'propietario') {
                    self::emitir($tramite, 'sinodal', $s, ['hasta' => $fechaExamen, 'funcion' => (string) ($s['funcion'] ?? ''), 'acta' => $acta], $usuarioId);
                }
            }
        }
    }

    /** La Subdirección o la Jefatura firmó una constancia: se avisa al profesor. */
    public static function firmada(int $docId): void
    {
        $c = Database::una('SELECT c.*, t.folio FROM tit_constancias c JOIN tit_tramites t ON t.id = c.tramite_id WHERE c.documento_id = ?', [$docId]);
        if ($c) {
            Correo::enviar((string) $c['email'], ['nombre' => $c['nombre']], (self::TIPOS[$c['tipo']][0] ?? 'Constancia') . ' · ' . $c['folio'],
                'Gracias por su participación en el proceso de titulación. Su ' . mb_strtolower(self::TIPOS[$c['tipo']][0] ?? 'constancia') . ' ya está firmada y disponible en Traza FI, en «Constancias → Titulación Lic».',
                'Ver mis constancias', Correo::enlace('titulacion/constancias'), 'constancia', (int) $c['tramite_id']);
        }
    }

    // ------------------------------------------------------------------ consulta

    /** Alcance de quien consulta: null = todas (Subdirector); si no, solo las que emite su departamento. */
    public static function alcance(): ?string
    {
        return SubdireccionFirma::esSubdirector() ? null : 'jefe_titulacion';
    }

    public static function puedeBuscar(): bool
    {
        return \App\Modules\Titulacion\TitulacionAccess::esPersonal() || SubdireccionFirma::esSubdirector() || SubdireccionFirma::esJefeTitulacion();
    }

    /** @param ?string $emisor null para todas */
    public static function dePersona(string $email, ?string $emisor = null): array
    {
        $p = [strtolower(trim($email))];
        $filtro = '';
        if ($emisor !== null) {
            $filtro = ' AND c.emisor = ?';
            $p[] = $emisor;
        }
        return Database::todas(
            "SELECT c.*, t.folio, t.titulo, t.modalidad, COALESCE(u.nombre, JSON_UNQUOTE(JSON_EXTRACT(t.datos, '$.datos.nombre'))) AS alumno_nombre, d.subdir_en, d.subdir_ruta, d.ruta
             FROM tit_constancias c JOIN tit_tramites t ON t.id = c.tramite_id LEFT JOIN usuarios u ON u.id = t.alumno_id
             LEFT JOIN tit_documentos d ON d.id = c.documento_id
             WHERE c.email = ?$filtro ORDER BY c.id DESC",
            $p
        );
    }

    /** Personas con constancias cuyo nombre o correo coincide. @return array<int,array{email:string,nombre:string,total:int}> */
    public static function buscarPersonas(string $q, ?string $emisor): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $p = [$like, $like];
        $filtro = '';
        if ($emisor !== null) {
            $filtro = ' AND c.emisor = ?';
            $p[] = $emisor;
        }
        return Database::todas(
            "SELECT c.email, COALESCE(MAX(u.nombre), MAX(c.nombre)) AS nombre, MAX(u.grado) AS grado, COUNT(*) AS total
             FROM tit_constancias c LEFT JOIN usuarios u ON u.email COLLATE utf8mb4_unicode_ci = c.email
             WHERE (c.nombre LIKE ? OR c.email LIKE ? OR u.nombre COLLATE utf8mb4_unicode_ci LIKE ?)$filtro GROUP BY c.email ORDER BY nombre LIMIT 12",
            [$like, $like, $like, ...array_slice($p, 2)]
        );
    }

    public static function titulo(string $tipo): string
    {
        return self::TIPOS[$tipo][0] ?? 'Constancia';
    }

    public static function nombreModalidad(string $m): string
    {
        return Catalogo::modalidad($m)['nombre'] ?? $m;
    }
}
