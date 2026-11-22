<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Buscador de personas para el componente SelectorPersonas (docs/SELECTOR_DE_PERSONAS.md). Cada palabra escrita debe aparecer en el
 * nombre o en el correo, en cualquier orden. El módulo que lo usa decide quién puede llamarlo (autorización en su propio endpoint)
 * y a quién se puede encontrar ($roles, $alumnos).
 */
final class BuscadorPersonas
{
    /** @param string[] $roles si se indican, solo cuentas con esos roles @return array<int,array{id:int,nombre:string,email:string}> */
    public static function buscar(string $q, array $roles = [], bool $alumnos = false, int $limite = 15): array
    {
        $q = mb_substr(trim($q), 0, 60);
        if (mb_strlen($q) < 2) {
            return [];
        }
        $donde = [];
        $params = [];
        foreach (array_slice(preg_split('/\s+/u', $q) ?: [], 0, 5) as $palabra) {
            $like = '%' . strtr($palabra, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
            $donde[] = '(nombre LIKE ? OR email LIKE ?)';
            array_push($params, $like, $like);
        }
        $filtro = '';
        if ($roles) {
            $filtro .= ' AND rol IN (' . implode(',', array_fill(0, count($roles), '?')) . ')';
            $paramsRol = $roles;
        } elseif (!$alumnos) {
            $filtro .= " AND rol <> 'alumno'";
        }
        $params = array_merge($paramsRol ?? [], $params);   // los roles van antes que las palabras en el SQL
        $filas = Database::todas('SELECT id, nombre, email FROM usuarios WHERE activo = 1' . $filtro . ' AND ' . implode(' AND ', $donde) . ' ORDER BY nombre LIMIT ' . max(1, min(30, $limite)), $params);
        return array_map(static fn(array $f): array => ['id' => (int) $f['id'], 'nombre' => (string) $f['nombre'], 'email' => (string) $f['email']], $filas);
    }

    /** Responde el JSON {"personas":[…]} para `?q=` y termina. */
    public static function responder(array $roles = [], bool $alumnos = false): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['personas' => self::buscar((string) ($_GET['q'] ?? ''), $roles, $alumnos)], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
