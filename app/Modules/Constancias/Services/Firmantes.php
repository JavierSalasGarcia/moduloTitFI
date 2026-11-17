<?php
declare(strict_types=1);

namespace App\Modules\Constancias\Services;

use App\Core\Database;
use App\Core\Persona;

/**
 * Directorio de quienes pueden firmar una constancia: titulares, jefaturas, coordinaciones y encargadurías vigentes de
 * departamento_responsables, coordinaciones vigentes de programa_responsables (archivadas en el departamento que
 * administra el programa) y, como hace Titulación (Autoridades), los titulares marcados en Ajustes → Usuarios
 * (usuarios.cargo o usuario_roles.cargo = 'titular') cuando su dependencia no tiene nombramiento con periodo.
 * Cada opción tiene una clave estable: d<responsable>, p<responsable> o u<usuario>-<departamento>.
 */
final class Firmantes
{
    private const CARGOS_DEPTO = ['titular', 'jefe', 'coordinador', 'encargado'];

    /** @return array<string,array{clave:string,usuario_id:int,nombre:string,email:string,cargo:string,departamento_id:int,departamento:string,depto_clave:string}> */
    public static function todos(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $r = [];
        $vistos = [];
        foreach (Database::todas(
            "SELECT dr.id, dr.cargo, u.id AS usuario_id, u.nombre, u.grado, u.genero, u.email, d.id AS depto_id, d.nombre AS depto, d.clave AS depto_clave
               FROM departamento_responsables dr
               JOIN departamentos d ON d.id = dr.departamento_id
               JOIN usuarios u ON u.id = dr.usuario_id AND u.activo = 1
              WHERE dr.activo = 1 AND dr.cargo IN ('titular','jefe','coordinador','encargado')
                AND dr.fecha_inicio <= CURDATE() AND (dr.fecha_fin IS NULL OR dr.fecha_fin >= CURDATE())
              ORDER BY d.nombre, FIELD(dr.cargo, 'titular','jefe','coordinador','encargado'), u.nombre"
        ) as $f) {
            $r['d' . $f['id']] = self::opcion('d' . $f['id'], $f, self::cargoDepartamento((string) $f['cargo'], $f, (string) $f['depto'], (string) $f['depto_clave']));
            $vistos[(int) $f['usuario_id'] . '-' . (int) $f['depto_id']] = true;
        }
        foreach (Database::todas(
            "SELECT pr.id, u.id AS usuario_id, u.nombre, u.grado, u.genero, u.email, p.nombre AS programa, p.nivel, d.id AS depto_id, d.nombre AS depto, d.clave AS depto_clave
               FROM programa_responsables pr
               JOIN programas p ON p.id = pr.programa_id
               JOIN departamentos d ON d.id = p.depto_id
               JOIN usuarios u ON u.id = pr.usuario_id AND u.activo = 1
              WHERE pr.activo = 1 AND pr.cargo = 'coordinador'
                AND pr.fecha_inicio <= CURDATE() AND (pr.fecha_fin IS NULL OR pr.fecha_fin >= CURDATE())
              ORDER BY p.nombre, u.nombre"
        ) as $f) {
            $prog = (string) $f['programa'];
            $de = $f['nivel'] === 'licenciatura' && !str_starts_with($prog, 'Licenciatura') ? 'de la Licenciatura en ' : 'de la ';
            if ($f['nivel'] !== 'licenciatura' && !preg_match('/^(Maestr|Doctor|Especial)/u', $prog)) {
                $de = 'del programa ';
            }
            $r['p' . $f['id']] = self::opcion('p' . $f['id'], $f, Persona::f($f, 'Coordinador', 'Coordinadora') . ' ' . $de . $prog);
        }
        foreach (Database::todas(
            "SELECT x.usuario_id, u.nombre, u.grado, u.genero, u.email, d.id AS depto_id, d.nombre AS depto, d.clave AS depto_clave
               FROM (SELECT id AS usuario_id, departamento_id FROM usuarios WHERE cargo = 'titular' AND departamento_id IS NOT NULL
                     UNION SELECT usuario_id, departamento_id FROM usuario_roles WHERE cargo = 'titular' AND departamento_id IS NOT NULL) x
               JOIN usuarios u ON u.id = x.usuario_id AND u.activo = 1
               JOIN departamentos d ON d.id = x.departamento_id
              ORDER BY d.nombre, u.nombre"
        ) as $f) {
            $k = (int) $f['usuario_id'] . '-' . (int) $f['depto_id'];
            if (isset($vistos[$k])) {
                continue;
            }
            $vistos[$k] = true;
            $r['u' . $k] = self::opcion('u' . $k, $f, self::cargoDepartamento('titular', $f, (string) $f['depto'], (string) $f['depto_clave']));
        }
        uasort($r, static fn(array $a, array $b): int => [$a['departamento'], $a['nombre']] <=> [$b['departamento'], $b['nombre']]);
        return $cache = $r;
    }

    public static function una(string $clave): ?array
    {
        return self::todos()[$clave] ?? null;
    }

    private static function opcion(string $clave, array $f, string $cargo): array
    {
        return [
            'clave' => $clave, 'usuario_id' => (int) $f['usuario_id'], 'nombre' => Persona::tratamiento($f), 'email' => strtolower((string) $f['email']),
            'cargo' => $cargo, 'departamento_id' => (int) $f['depto_id'], 'departamento' => (string) $f['depto'], 'depto_clave' => (string) $f['depto_clave'],
        ];
    }

    /** «Subdirector Académico», «Titular de la Coordinación de Estudios Avanzados», «Jefa del Departamento de …». */
    public static function cargoDepartamento(string $cargo, array $p, string $depto, string $clave): string
    {
        if ($cargo === 'titular' && in_array($clave, ['subdireccion_academica', 'titular_de_la_subdireccion_academica'], true)) {
            return Persona::f($p, 'Subdirector Académico', 'Subdirectora Académica');
        }
        if ($cargo === 'titular' && $clave === 'subdireccion_administrativa') {
            return Persona::f($p, 'Subdirector Administrativo', 'Subdirectora Administrativa');
        }
        if ($cargo === 'titular' && $clave === 'direccion') {
            return Persona::f($p, 'Director', 'Directora') . ' de la Facultad';
        }
        if (str_starts_with($depto, 'Titular ')) {
            return $depto;
        }
        $de = preg_match('/^(Departamento|Centro|Consejo)\b/u', $depto) ? 'del ' : 'de la ';
        $puesto = match ($cargo) {
            'jefe' => Persona::f($p, 'Jefe', 'Jefa'),
            'coordinador' => Persona::f($p, 'Coordinador', 'Coordinadora'),
            'encargado' => Persona::f($p, 'Encargado', 'Encargada'),
            default => str_starts_with($depto, 'Departamento') ? Persona::f($p, 'Jefe', 'Jefa') : 'Titular',
        };
        return $puesto . ' ' . $de . $depto;
    }
}
