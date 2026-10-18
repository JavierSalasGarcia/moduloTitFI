<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;

/** Quién ocupa cada cargo, tomado de Organización (responsables de departamentos y de programas), sin duplicar datos. */
final class Autoridades
{
    /** @return array{id:int,nombre:string,email:string}|null */
    private static function deDepartamento(array $claves): ?array
    {
        $marcas = implode(',', array_fill(0, count($claves), '?'));
        $f = Database::una(
            "SELECT u.id, u.nombre, u.email
             FROM departamento_responsables r
             JOIN departamentos d ON d.id = r.departamento_id
             JOIN usuarios u ON u.id = r.usuario_id AND u.activo = 1
             WHERE d.clave IN ($marcas) AND r.cargo = 'titular' AND r.activo = 1
               AND r.fecha_inicio <= CURDATE() AND (r.fecha_fin IS NULL OR r.fecha_fin >= CURDATE())
             ORDER BY r.fecha_inicio DESC, r.id DESC LIMIT 1",
            $claves
        );
        return $f ? ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre'], 'email' => strtolower((string) $f['email'])] : null;
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

    /** Coordinador de Docencia vigente de la licenciatura (clave de Catalogo::carreras()). */
    public static function coordinadorDocencia(string $carrera): ?array
    {
        $siglas = \App\Modules\Titulacion\Catalogo::carreras()[$carrera]['siglas'] ?? [];
        if (!$siglas) {
            return null;
        }
        $marcas = implode(',', array_fill(0, count($siglas), '?'));
        $f = Database::una(
            "SELECT u.id, u.nombre, u.email
             FROM programa_responsables r
             JOIN programas p ON p.id = r.programa_id
             JOIN usuarios u ON u.id = r.usuario_id AND u.activo = 1
             WHERE p.siglas IN ($marcas) AND p.nivel = 'licenciatura' AND r.cargo = 'coordinador' AND r.activo = 1
               AND r.fecha_inicio <= CURDATE() AND (r.fecha_fin IS NULL OR r.fecha_fin >= CURDATE())
             ORDER BY r.fecha_inicio DESC, r.id DESC LIMIT 1",
            array_values($siglas)
        );
        return $f ? ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre'], 'email' => strtolower((string) $f['email'])] : null;
    }
}
