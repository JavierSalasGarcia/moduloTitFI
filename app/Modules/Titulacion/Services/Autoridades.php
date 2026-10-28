<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;

/** Quién ocupa cada cargo, tomado de Organización (responsables de departamentos y de programas), sin duplicar datos. */
final class Autoridades
{
    /**
     * Titular vigente de la dependencia. El cargo se asigna en Ajustes → Usuarios (puesto «Titular» de la cuenta o de un rol
     * adicional); los responsables con periodo de Organización, si los hay, tienen preferencia.
     * @return array{id:int,nombre:string,email:string}|null
     */
    private static function deDepartamento(array $claves): ?array
    {
        $marcas = implode(',', array_fill(0, count($claves), '?'));
        $f = Database::una(
            "SELECT u.id, u.nombre, u.grado, u.genero, u.email
             FROM (
               SELECT r.usuario_id, 0 AS prioridad, r.fecha_inicio AS desde, r.id AS orden
                 FROM departamento_responsables r JOIN departamentos d ON d.id = r.departamento_id
                WHERE d.clave IN ($marcas) AND r.cargo = 'titular' AND r.activo = 1
                  AND r.fecha_inicio <= CURDATE() AND (r.fecha_fin IS NULL OR r.fecha_fin >= CURDATE())
               UNION ALL
               SELECT x.usuario_id, 1, NULL, x.id
                 FROM usuario_roles x JOIN departamentos d ON d.id = x.departamento_id
                WHERE d.clave IN ($marcas) AND x.cargo = 'titular'
               UNION ALL
               SELECT c.id, 2, NULL, c.id
                 FROM usuarios c JOIN departamentos d ON d.id = c.departamento_id
                WHERE d.clave IN ($marcas) AND c.cargo = 'titular'
             ) t
             JOIN usuarios u ON u.id = t.usuario_id AND u.activo = 1
             ORDER BY t.prioridad, t.desde DESC, t.orden DESC LIMIT 1",
            [...$claves, ...$claves, ...$claves]
        );
        return $f ? ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre'], 'grado' => $f['grado'], 'genero' => $f['genero'], 'email' => strtolower((string) $f['email'])] : null;
    }

    public static function director(): ?array
    {
        static $c = false;
        return $c === false ? $c = self::deDepartamento(['direccion']) : $c;
    }

    public static function subdirector(): ?array
    {
        static $c = false;
        return $c === false ? $c = self::deDepartamento(['subdireccion_academica', 'titular_de_la_subdireccion_academica']) : $c;
    }

    /** Jefatura del Departamento de Evaluación Profesional y Grado (Vo. Bo. del oficio de aprobación de tema). */
    public static function jefeTitulacion(): ?array
    {
        static $c = false;
        return $c === false ? $c = self::deDepartamento(['departamento_de_evaluacion_profesional_y_grado']) : $c;
    }

    /** Coordinador de Docencia vigente de la licenciatura (clave de Catalogo::carreras()). */
    public static function coordinadorDocencia(string $carrera): ?array
    {
        $siglas = \App\Modules\Titulacion\Catalogo::carreras()[$carrera]['siglas'] ?? [];
        if (!$siglas) {
            return null;
        }
        $marcas = implode(',', array_fill(0, count($siglas), '?'));
        $f = Database::una(
            "SELECT u.id, u.nombre, u.grado, u.genero, u.email
             FROM programa_responsables r
             JOIN programas p ON p.id = r.programa_id
             JOIN usuarios u ON u.id = r.usuario_id AND u.activo = 1
             WHERE p.siglas IN ($marcas) AND p.nivel = 'licenciatura' AND r.cargo = 'coordinador' AND r.activo = 1
               AND r.fecha_inicio <= CURDATE() AND (r.fecha_fin IS NULL OR r.fecha_fin >= CURDATE())
             ORDER BY r.fecha_inicio DESC, r.id DESC LIMIT 1",
            array_values($siglas)
        );
        return $f ? ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre'], 'grado' => $f['grado'], 'genero' => $f['genero'], 'email' => strtolower((string) $f['email'])] : null;
    }
}
