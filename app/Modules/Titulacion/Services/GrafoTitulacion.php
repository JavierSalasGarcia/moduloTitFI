<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;

/**
 * Proveedor del grafo de persona (docs/GRAFO_DE_PERSONA.md): estudiantes que asesora (licenciatura, maestría, doctorado),
 * protocolos que ha revisado, jurados, constancias y sus propios trámites. Todo se busca por la cuenta, la ficha de
 * profesor o cualquiera de sus correos verificados; nunca por nombre.
 */
final class GrafoTitulacion
{
    public static function de(int $usuarioId, array $correos, ?int $profesorId): array
    {
        $grupos = [];
        $marcas = $correos ? implode(',', array_fill(0, count($correos), '?')) : "''";
        $paso = static function (callable $f) use (&$grupos): void {
            try {
                if ($g = $f()) {
                    $grupos[] = $g;
                }
            } catch (\Throwable $e) {
                error_log('Grafo de titulación: ' . $e->getMessage());     // una tabla aún sin migrar no rompe el resto
            }
        };

        // Estudiantes con trámite en línea donde la persona es asesora o coasesora.
        $paso(static function () use ($correos, $marcas): ?array {
            if (!$correos) {
                return null;
            }
            $filas = Database::todas(
                "SELECT t.id, t.folio, t.nivel, t.estado, t.titulo, a.nombre AS alumno, p.nombre AS programa,
                        LOWER(JSON_UNQUOTE(JSON_EXTRACT(t.datos, '$.protocolo.asesor_correo'))) AS asesor
                   FROM tit_tramites t JOIN usuarios a ON a.id = t.alumno_id LEFT JOIN programas p ON p.id = a.programa_id
                  WHERE LOWER(JSON_UNQUOTE(JSON_EXTRACT(t.datos, '$.protocolo.asesor_correo'))) IN ($marcas)
                     OR LOWER(JSON_UNQUOTE(JSON_EXTRACT(t.datos, '$.protocolo.coasesor_correo'))) IN ($marcas)
                  ORDER BY t.actualizado_en DESC LIMIT 60",
                [...$correos, ...$correos]
            );
            return $filas ? ['titulo' => 'Estudiantes que asesora', 'items' => array_map(static fn(array $t): array => [
                'etiqueta' => $t['alumno'] . ' · ' . self::grado((string) $t['nivel'], (string) ($t['programa'] ?? '')),
                'detalle' => (in_array($t['asesor'], $correos, true) ? 'Asesoría' : 'Coasesoría') . ' · ' . $t['estado'] . ' · ' . mb_strimwidth((string) ($t['titulo'] ?: $t['folio']), 0, 90, '…'),
                'enlace' => 'titulacion/seguimiento'], $filas), 'total' => count($filas)] : null;
        });

        // Estudiantes dirigidos según los registros históricos del departamento (antes de la plataforma).
        $paso(static function () use ($correos, $marcas): ?array {
            if (!$correos) {
                return null;
            }
            $donde = "director_email IN ($marcas) OR codirector_email IN ($marcas)";
            $n = (int) Database::valor("SELECT COUNT(*) FROM tit_historico WHERE $donde", [...$correos, ...$correos]);
            $filas = $n ? Database::todas("SELECT alumno, carrera, modalidad, fecha_examen FROM tit_historico WHERE $donde ORDER BY fecha_examen DESC LIMIT 25", [...$correos, ...$correos]) : [];
            return $filas ? ['titulo' => 'Estudiantes dirigidos (histórico)', 'total' => $n, 'items' => array_map(static fn(array $h): array => [
                'etiqueta' => (string) $h['alumno'], 'detalle' => trim($h['carrera'] . ' · ' . $h['modalidad'] . ($h['fecha_examen'] ? ' · ' . $h['fecha_examen'] : '')), 'enlace' => null], $filas)] : null;
        });

        // Protocolos revisados en la plataforma (comités de revisión).
        $paso(static function () use ($correos, $marcas): ?array {
            if (!$correos) {
                return null;
            }
            $filas = Database::todas(
                "SELECT t.id, t.folio, t.titulo, r.carrera, a.estado, a.propuesta, a.firmada_en FROM tit_revision_asignaciones a
                   JOIN tit_comision_miembros m ON m.id = a.miembro_id JOIN tit_revisiones r ON r.id = a.revision_id JOIN tit_tramites t ON t.id = r.tramite_id
                  WHERE m.email IN ($marcas) AND a.estado <> 'cancelada' ORDER BY a.asignada_en DESC LIMIT 60",
                $correos
            );
            return $filas ? ['titulo' => 'Protocolos revisados', 'total' => count($filas), 'items' => array_map(static fn(array $a): array => [
                'etiqueta' => mb_strimwidth((string) ($a['titulo'] ?: $a['folio']), 0, 110, '…'),
                'detalle' => $a['carrera'] . ' · ' . ($a['estado'] === 'firmada' ? 'Dictamen: ' . str_replace('_', ' ', (string) $a['propuesta']) . ' · ' . substr((string) $a['firmada_en'], 0, 10) : 'En revisión'),
                'enlace' => 'titulacion/revision'], $filas)] : null;
        });

        // Protocolos revisados según el histórico del departamento.
        $paso(static function () use ($correos, $marcas): ?array {
            if (!$correos) {
                return null;
            }
            $donde = "revisor1_email IN ($marcas) OR revisor2_email IN ($marcas) OR revisor3_email IN ($marcas)";
            $par = [...$correos, ...$correos, ...$correos];
            $n = (int) Database::valor("SELECT COUNT(*) FROM tit_historico_protocolos WHERE $donde", $par);
            $filas = $n ? Database::todas("SELECT titulo, alumno, carrera, fecha_aprobacion FROM tit_historico_protocolos WHERE $donde ORDER BY fecha_aprobacion DESC LIMIT 25", $par) : [];
            return $filas ? ['titulo' => 'Protocolos revisados (histórico)', 'total' => $n, 'items' => array_map(static fn(array $h): array => [
                'etiqueta' => mb_strimwidth((string) $h['titulo'], 0, 110, '…'), 'detalle' => trim($h['alumno'] . ' · ' . $h['carrera'] . ($h['fecha_aprobacion'] ? ' · ' . $h['fecha_aprobacion'] : '')), 'enlace' => null], $filas)] : null;
        });

        // Jurados de examen (sinodal).
        $paso(static function () use ($correos, $marcas): ?array {
            if (!$correos) {
                return null;
            }
            $filas = Database::todas(
                "SELECT s.funcion, s.calidad, s.estado, t.folio, t.titulo FROM tit_sinodales s JOIN tit_tramites t ON t.id = s.tramite_id
                  WHERE s.email IN ($marcas) AND s.estado <> 'cancelado' ORDER BY s.id DESC LIMIT 60",
                $correos
            );
            return $filas ? ['titulo' => 'Jurados de examen', 'total' => count($filas), 'items' => array_map(static fn(array $s): array => [
                'etiqueta' => ucfirst((string) $s['funcion']) . ' ' . $s['calidad'], 'detalle' => mb_strimwidth((string) ($s['titulo'] ?: $s['folio']), 0, 100, '…'), 'enlace' => null], $filas)] : null;
        });

        // Constancias emitidas a la persona (por cuenta, ficha de profesor o correo).
        $paso(static function () use ($usuarioId, $correos, $marcas, $profesorId): ?array {
            $cond = ['c.usuario_id = ?'];
            $par = [$usuarioId];
            if ($profesorId) {
                $cond[] = 'c.profesor_id = ?';
                $par[] = $profesorId;
            }
            if ($correos) {
                $cond[] = "c.email IN ($marcas)";
                $par = [...$par, ...$correos];
            }
            $donde = implode(' OR ', $cond);
            $n = (int) Database::valor("SELECT COUNT(*) FROM tit_constancias c WHERE $donde", $par);
            $filas = $n ? Database::todas("SELECT c.tipo, c.rol, c.creado_en FROM tit_constancias c WHERE $donde ORDER BY c.creado_en DESC LIMIT 25", $par) : [];
            return $filas ? ['titulo' => 'Constancias', 'total' => $n, 'items' => array_map(static fn(array $c): array => [
                'etiqueta' => ucfirst(str_replace('_', ' ', (string) $c['tipo'])), 'detalle' => trim((string) $c['rol']) . ' · ' . substr((string) $c['creado_en'], 0, 10), 'enlace' => 'titulacion/constancias'], $filas)] : null;
        });

        // Sus propios trámites, si es pasante.
        $paso(static function () use ($usuarioId): ?array {
            $filas = Database::todas('SELECT id, folio, nivel, estado, titulo FROM tit_tramites WHERE alumno_id = ? ORDER BY actualizado_en DESC LIMIT 25', [$usuarioId]);
            return $filas ? ['titulo' => 'Trámites de titulación propios', 'items' => array_map(static fn(array $t): array => [
                'etiqueta' => (string) $t['folio'], 'detalle' => ucfirst((string) $t['nivel']) . ' · ' . $t['estado'] . ($t['titulo'] ? ' · ' . mb_strimwidth((string) $t['titulo'], 0, 80, '…') : ''), 'enlace' => 'titulacion/seguimiento'], $filas)] : null;
        });

        return $grupos;
    }

    /** Licenciatura, maestría o doctorado, a partir del nivel del trámite y el nombre del programa del estudiante. */
    private static function grado(string $nivel, string $programa): string
    {
        if ($nivel !== 'posgrado') {
            return 'Licenciatura';
        }
        return match (true) {
            (bool) preg_match('/doctorado/iu', $programa) => 'Doctorado',
            (bool) preg_match('/maestr[ií]a/iu', $programa) => 'Maestría',
            default => 'Posgrado',
        };
    }
}
