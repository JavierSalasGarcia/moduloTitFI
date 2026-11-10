<?php
declare(strict_types=1);

namespace App\Modules\Organizacion;

use App\Core\Database;

/** Proveedor del grafo de persona (docs/GRAFO_DE_PERSONA.md): programas, órganos académicos y responsabilidades. */
final class GrafoOrganizacion
{
    public static function de(int $usuarioId, array $correos, ?int $profesorId): array
    {
        $grupos = [];
        $vigente = '(x.activo = 1 AND (x.fecha_fin IS NULL OR x.fecha_fin >= CURDATE()))';

        $programas = Database::todas(
            "SELECT p.nombre, p.siglas, p.nivel, x.tipo_vinculo FROM programa_profesores x JOIN programas p ON p.id = x.programa_id
              WHERE x.usuario_id = ? AND $vigente ORDER BY p.nivel, p.nombre",
            [$usuarioId]
        );
        if ($programas) {
            $grupos[] = ['titulo' => 'Programas en los que imparte clases', 'items' => array_map(static fn(array $p): array => [
                'etiqueta' => $p['nombre'], 'detalle' => ucfirst((string) $p['nivel']) . ' · ' . str_replace('_', ' ', (string) $p['tipo_vinculo']), 'enlace' => null], $programas)];
        }

        // NAB (núcleo académico básico) y CAP (comité académico): los cuerpos colegiados del posgrado.
        $organos = Database::todas(
            "SELECT o.tipo, o.nombre, p.nombre AS programa, x.cargo FROM programa_organo_miembros x JOIN programa_organos o ON o.id = x.organo_id
               JOIN programas p ON p.id = o.programa_id WHERE x.usuario_id = ? AND $vigente AND o.activo = 1 ORDER BY o.tipo, p.nombre",
            [$usuarioId]
        );
        if ($organos) {
            $grupos[] = ['titulo' => 'Cuerpos y órganos académicos', 'items' => array_map(static fn(array $o): array => [
                'etiqueta' => $o['tipo'] . ' · ' . ($o['nombre'] ?: $o['programa']), 'detalle' => ucfirst((string) $o['cargo']) . ' · ' . $o['programa'], 'enlace' => null], $organos)];
        }

        $cargos = Database::todas(
            "SELECT p.nombre, x.cargo FROM programa_responsables x JOIN programas p ON p.id = x.programa_id WHERE x.usuario_id = ? AND $vigente ORDER BY p.nombre",
            [$usuarioId]
        );
        if ($cargos) {
            $grupos[] = ['titulo' => 'Responsabilidades de programa', 'items' => array_map(static fn(array $c): array => [
                'etiqueta' => ucfirst(str_replace('_', ' ', (string) $c['cargo'])), 'detalle' => (string) $c['nombre'], 'enlace' => null], $cargos)];
        }
        return $grupos;
    }
}
