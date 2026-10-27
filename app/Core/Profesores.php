<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Directorio de profesores (migración 026): una persona por RFC aunque imparta en varias licenciaturas y posgrados. Existe antes de
 * que el profesor se registre; al verificar un correo del directorio su cuenta se enlaza al mismo registro, de modo que no hay
 * dos personas (ni dos nombres) para el mismo profesor. Mientras no tenga cuenta, el directorio da su nombre y género a las constancias.
 */
final class Profesores
{
    /** «Maria de Lourdes Alcantara Garcia». */
    public static function nombreCompleto(array $p): string
    {
        return trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter([$p['nombre'] ?? '', $p['apellido1'] ?? '', $p['apellido2'] ?? '']))) ?? '');
    }

    /** Minúsculas sin acentos (la ñ se conserva): sirve para comparar nombres y para comprobar que una corrección solo cambia acentos y mayúsculas. */
    public static function base(string $s): string
    {
        $s = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $s) ?? ''));
        return strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'à' => 'a', 'è' => 'e', 'ì' => 'i', 'ò' => 'o', 'ù' => 'u']);
    }

    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'van', 'von', 'da', 'di', 'dos'];

    /** Nombre sin grado, acentos, mayúsculas ni orden: «M. en I. Pérez Juan» y «Juan Perez» dan la misma huella. */
    public static function huellaNombre(string $nombre): string
    {
        $t = preg_split('/\s+/', trim(preg_replace('/^(?:(?:[a-zA-Zñáéíóú]{1,5}\.\s*)|(?:en\s))+/iu', '', trim($nombre)) ?? '')) ?: [];
        $t = array_values(array_filter(
            array_map(static fn($x) => preg_replace('/[^a-z0-9ñ]/u', '', self::base($x)) ?? '', $t),
            static fn($x) => $x !== '' && !in_array($x, self::PARTICULAS, true)
        ));
        sort($t);
        return implode(' ', $t);
    }

    /**
     * Quién es por su nombre (directorio de profesores y, si no, cuentas), solo si la coincidencia es única. Devuelve su correo vigente
     * (puede ser '' si el profesor del directorio no tiene), el profesor_id si es del directorio y el género. Sirve para las constancias
     * históricas, donde el libro trae el nombre pero no el correo.
     */
    public static function porNombre(string $nombre): ?array
    {
        static $dir = null, $cuentas = null;
        $h = self::huellaNombre($nombre);
        if ($h === '') {
            return null;
        }
        try {
            if ($dir === null) {
                $dir = [];
                foreach (Database::todas(
                    'SELECT p.id, p.usuario_id, p.nombre, p.apellido1, p.apellido2, p.genero,
                            (SELECT c.email FROM profesor_correos c WHERE c.profesor_id = p.id ORDER BY c.principal DESC, c.id LIMIT 1) AS email,
                            (SELECT u.email FROM usuarios u WHERE u.id = p.usuario_id) AS email_cuenta
                     FROM profesores p'
                ) as $p) {
                    $dir[self::huellaNombre(self::nombreCompleto($p))][] = $p;
                }
                $cuentas = [];
                foreach (Database::todas('SELECT nombre, email, genero FROM usuarios WHERE activo = 1') as $u) {
                    $cuentas[self::huellaNombre((string) $u['nombre'])][] = $u;
                }
            }
        } catch (\Throwable) {
            return null;   // aún sin la migración
        }
        if (isset($dir[$h]) && count($dir[$h]) === 1) {
            $p = $dir[$h][0];
            return ['email' => (string) ($p['email_cuenta'] ?: $p['email']), 'profesor_id' => (int) $p['id'], 'genero' => $p['genero']];
        }
        if (isset($cuentas[$h]) && count($cuentas[$h]) === 1) {
            return ['email' => (string) $cuentas[$h][0]['email'], 'profesor_id' => null, 'genero' => $cuentas[$h][0]['genero']];
        }
        return null;
    }

    /** Profesor del directorio por uno de sus correos (para constancias y correos de quien aún no tiene cuenta). */
    public static function porEmail(string $email): ?array
    {
        try {
            $p = Database::una(
                'SELECT p.nombre, p.apellido1, p.apellido2, p.grado, p.genero FROM profesor_correos c JOIN profesores p ON p.id = c.profesor_id WHERE c.email = ?',
                [strtolower(trim($email))]
            );
        } catch (\Throwable) {
            return null;   // aún sin la migración
        }
        return $p ? ['nombre' => self::nombreCompleto($p), 'grado' => $p['grado'], 'genero' => $p['genero']] : null;
    }

    /**
     * Si el correo es de un profesor del directorio que YA tiene cuenta (con otro correo), devuelve el correo de esa cuenta.
     * Sirve para no crear una segunda cuenta cuando cambia el dominio del correo institucional.
     */
    public static function cuentaConOtroCorreo(string $email): ?string
    {
        try {
            $u = Identidad::usuarioPorCorreo($email);
            if ($u) {
                return (string) $u['email'];
            }
            $c = Database::valor(
                'SELECT u.email FROM profesor_correos c JOIN profesores p ON p.id = c.profesor_id JOIN usuarios u ON u.id = p.usuario_id WHERE c.email = ? AND u.activo = 1',
                [strtolower(trim($email))]
            );
            return $c ? (string) $c : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Registro del directorio enlazado a la cuenta, con lo que imparte. El RFC y el número de empleado son internos: no se devuelven. */
    public static function deUsuario(int $usuarioId): ?array
    {
        try {
            $p = Database::una('SELECT id, nombre, apellido1, apellido2, grado, genero FROM profesores WHERE usuario_id = ?', [$usuarioId]);
            if (!$p) {
                return null;
            }
            $p['imparte'] = Database::todas(
                'SELECT u.clave, u.nombre, u.nivel, a.periodo FROM profesor_adscripciones a JOIN profesor_unidades u ON u.id = a.unidad_id
                 WHERE a.profesor_id = ? ORDER BY FIELD(u.nivel, \'licenciatura\', \'otro\', \'posgrado\'), u.nombre',
                [$p['id']]
            );
            return $p;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Enlaza la cuenta (con su correo ya verificado) al profesor del directorio que tiene ese correo, si lo hay y no está enlazado.
     * Pasa sus programas a programa_profesores y completa el género de la cuenta. Es seguro repetirla.
     */
    public static function vincularCuenta(int $usuarioId, string $email): bool
    {
        try {
            if (Database::valor('SELECT id FROM profesores WHERE usuario_id = ?', [$usuarioId])) {
                return true;
            }
            $id = Database::valor(
                'SELECT p.id FROM profesor_correos c JOIN profesores p ON p.id = c.profesor_id WHERE c.email = ? AND p.usuario_id IS NULL',
                [strtolower(trim($email))]
            );
            if (!$id) {
                return false;
            }
            Database::ejecutar('UPDATE profesores SET usuario_id = ? WHERE id = ? AND usuario_id IS NULL', [$usuarioId, $id]);
            Database::ejecutar('UPDATE usuarios u JOIN profesores p ON p.usuario_id = u.id SET u.genero = p.genero WHERE u.id = ? AND u.genero IS NULL AND p.genero IS NOT NULL', [$usuarioId]);
            Database::ejecutar(
                "INSERT IGNORE INTO programa_profesores (programa_id, usuario_id, tipo_vinculo, fecha_inicio, activo, observaciones)
                 SELECT un.programa_id, ?, 'docente', a.fecha_inicio, 1, 'Directorio de profesores'
                 FROM profesor_adscripciones a JOIN profesor_unidades un ON un.id = a.unidad_id AND un.programa_id IS NOT NULL WHERE a.profesor_id = ?",
                [$usuarioId, $id]
            );
            return true;
        } catch (\Throwable) {
            return false;   // aún sin la migración: el registro sigue su curso
        }
    }

    /**
     * El profesor corrige acentos y mayúsculas de su nombre. No puede cambiar las letras (es la misma persona del directorio):
     * cada parte, sin acentos ni mayúsculas, debe seguir siendo igual. @throws \InvalidArgumentException
     */
    public static function corregirNombre(int $usuarioId, string $nombre, string $apellido1, string $apellido2): void
    {
        $p = Database::una('SELECT * FROM profesores WHERE usuario_id = ?', [$usuarioId]) ?? throw new \InvalidArgumentException('Tu cuenta no está enlazada al directorio de profesores.');
        $nuevo = ['nombre' => $nombre, 'apellido1' => $apellido1, 'apellido2' => $apellido2];
        foreach ($nuevo as $k => $v) {
            $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
            $nuevo[$k] = $v;
            if (mb_strlen($v) > ($k === 'nombre' ? 80 : 60) || ($k !== 'apellido2' && $v === '')) {
                throw new \InvalidArgumentException('Revisa tu nombre y apellidos: no pueden estar vacíos ni ser demasiado largos.');
            }
            if (self::base($v) !== self::base((string) ($p[$k] ?? ''))) {
                throw new \InvalidArgumentException('Solo puedes corregir acentos y mayúsculas; si tu nombre o apellidos son otros, avisa a la Subdirección Académica.');
            }
        }
        $anterior = self::nombreCompleto($p);
        Database::ejecutar('UPDATE profesores SET nombre = ?, apellido1 = ?, apellido2 = ? WHERE id = ?', [$nuevo['nombre'], $nuevo['apellido1'], $nuevo['apellido2'] ?: null, $p['id']]);
        // El nombre de la cuenta se actualiza solo si seguía siendo el del directorio (no se pisa un nombre que la persona ya escribió distinto).
        $cuenta = (string) Database::valor('SELECT nombre FROM usuarios WHERE id = ?', [$usuarioId]);
        if (self::base($cuenta) === self::base($anterior)) {
            Database::ejecutar('UPDATE usuarios SET nombre = ? WHERE id = ?', [self::nombreCompleto($nuevo), $usuarioId]);
        }
    }
}
