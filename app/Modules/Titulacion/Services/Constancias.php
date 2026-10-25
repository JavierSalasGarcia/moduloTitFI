<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;
use App\Core\Persona;
use App\Modules\Titulacion\Catalogo;
use App\Modules\Titulacion\TitulacionRepository;

/**
 * Constancias para profesores que se generan solas cuando se cumple lo que avalan:
 * revisor de protocolo (al aprobarse el protocolo), revisor de trabajo escrito (al firmarse el voto aprobatorio),
 * asesor del trabajo escrito, y constancia de cumplimiento y nombramiento como director (al registrarse el resultado).
 * Las de la Subdirección las firma el Subdirector; las de la Jefatura, el Jefe de Evaluación Profesional y Grado.
 */
final class Constancias
{
    /** tipo => [título, rol impreso, quién la emite y firma] */
    public const TIPOS = [
        'revisor_protocolo' => ['Revisor de protocolo', 'Revisor(a) de protocolo', 'subdirector'],
        'revisor_trabajo' => ['Revisor de trabajo escrito', 'Revisor(a) de trabajo escrito para evaluación profesional', 'subdirector'],
        'asesor_trabajo' => ['Asesor de trabajo escrito', 'Asesor(a) de trabajo escrito para evaluación profesional', 'subdirector'],
        'cumplimiento_director' => ['Constancia de cumplimiento como director', 'Director(a) del trabajo escrito', 'jefe_titulacion'],
        'nombramiento_director' => ['Nombramiento como director', 'Director(a) del trabajo escrito', 'jefe_titulacion'],
    ];

    public const EMISORES = ['subdirector' => 'Subdirección Académica', 'jefe_titulacion' => 'Departamento de Evaluación Profesional y Grado'];

    private static function rolImpreso(string $tipo, array $p): string
    {
        return str_replace('(A)', Persona::f($p, '', 'A'), mb_strtoupper(self::TIPOS[$tipo][1]));
    }

    /**
     * Genera la constancia si aún no existe para esa persona, trámite y tipo, y la pone en la cola de firmas.
     * @param array $persona ['nombre','email', y si se conocen 'grado','genero']
     */
    public static function emitir(array $tramite, string $tipo, array $persona, array $detalle, int $usuarioId): void
    {
        $tid = (int) $tramite['id'];
        $email = strtolower(trim((string) $persona['email']));
        if (!isset(self::TIPOS[$tipo]) || $email === '' || Database::valor('SELECT id FROM tit_constancias WHERE tramite_id = ? AND email = ? AND tipo = ?', [$tid, $email, $tipo])) {
            return;
        }
        $p = Persona::completar($persona, $email);
        $emisor = self::TIPOS[$tipo][2];
        if ($emisor === 'jefe_titulacion') {
            [$bytes, $caja] = Oficios::constanciaDirector($tramite, $p, $tipo === 'nombramiento_director' ? 'NOMBRAMIENTO' : 'CONSTANCIA', (string) $detalle['hasta']);
        } else {
            $x = FormatosOficiales::datos($tramite);
            [$bytes, $caja] = Oficios::constanciaProfesor((string) $tramite['folio'], $p, self::rolImpreso($tipo, $p), (string) $detalle['desde'], (string) $detalle['hasta'], [[
                'pasante' => $x['nombre'], 'modalidad' => $x['modalidad_nombre'], 'clave' => (string) $tramite['folio'],
                'fecha' => (string) $detalle['hasta'], 'titulo' => $x['titulo'],
            ]]);
        }
        $ruta = DocumentosGenerados::guardar($tramite, 'constancia_' . $tipo, $bytes);
        $clave = 'constancia_' . substr(hash('sha256', $tipo . '|' . $email), 0, 16);
        $docId = TitulacionRepository::registrarDocumento($tid, 'constancias', $clave, 'personal', 'aprobado', $ruta, 'constancia_' . $tipo . '_' . $tramite['folio'] . '.pdf', $usuarioId);
        Database::ejecutar(
            'INSERT INTO tit_constancias (tramite_id, email, nombre, tipo, emisor, vueltas, documento_id) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tid, $email, mb_substr((string) $p['nombre'], 0, 160), $tipo, $emisor, mb_substr((string) ($detalle['vueltas'] ?? ''), 0, 40), $docId]
        );
        SubdireccionFirma::solicitar($tramite, $docId, 'constancia', self::TIPOS[$tipo][0] . ': ' . $p['nombre'], $caja, $emisor);
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

    /** Voto aprobatorio firmado por todos: constancia para cada revisor del trabajo escrito. */
    public static function revisoresTrabajo(array $tramite, int $votoDocId, int $usuarioId): void
    {
        $previo = Jurado::vigente((int) $tramite['id'], 'revision');
        foreach (FirmasExternas::pendientesDe($votoDocId) as $f) {
            if ($f['rol'] === 'revisor' && $f['estado'] === 'firmada') {
                self::emitir($tramite, 'revisor_trabajo', ['nombre' => $f['nombre'], 'grado' => $f['grado'], 'genero' => $f['genero'], 'email' => $f['email']],
                    ['desde' => substr((string) ($previo['firmada_en'] ?? $f['creado_en']), 0, 10), 'hasta' => substr((string) $f['firmada_en'], 0, 10)], $usuarioId);
            }
        }
    }

    /** Evaluación profesional con trabajo escrito concluida: constancias del asesor (Subdirección y Jefatura). */
    public static function asesoria(array $tramite, string $fechaExamen, int $usuarioId): void
    {
        $asesor = FirmasExternas::asesores($tramite, false)[0] ?? null;
        if ($asesor === null) {
            return;
        }
        $proto = TitulacionRepository::hitos((int) $tramite['id'])['protocolo']['completado_en'] ?? $tramite['creado_en'];
        foreach (['asesor_trabajo', 'cumplimiento_director', 'nombramiento_director'] as $tipo) {
            self::emitir($tramite, $tipo, $asesor, ['desde' => substr((string) $proto, 0, 10), 'hasta' => $fechaExamen], $usuarioId);
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
            "SELECT c.*, t.folio, t.titulo, t.modalidad, u.nombre AS alumno_nombre, d.subdir_en, d.subdir_ruta, d.ruta
             FROM tit_constancias c JOIN tit_tramites t ON t.id = c.tramite_id JOIN usuarios u ON u.id = t.alumno_id
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
