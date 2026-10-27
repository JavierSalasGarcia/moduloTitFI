<?php
declare(strict_types=1);

namespace App\Modules\Profesores\Services;

use App\Core\Database;
use App\Core\Identidad;
use App\Core\Profesores;

/**
 * Carga que hace Recursos Humanos: un archivo (CSV o Excel) con el RFC de cada profesor y, si se tienen, su número de empleado y sus
 * correos (incluido el nuevo @uaemex.mx). El RFC es solo un desambiguador INTERNO: identifica sin error a la misma persona aunque cambie
 * el correo o se escriba distinto su nombre; nunca se pide ni se muestra al profesor, ni aquí se repite en los resultados.
 *
 * Columnas reconocidas por su encabezado (sin importar mayúsculas ni acentos): RFC o Clave; Número de empleado; Correo (puede haber
 * varias columnas o separarse con «;»); y, solo para dar de alta a quien falte, Nombre, Primer apellido, Segundo apellido y Género.
 */
final class CargaRrhh
{
    private const PARTICULAS = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'van', 'von', 'da', 'di', 'dos'];

    /** @return array<int,array<int,string>> filas (la primera es el encabezado) */
    public static function leer(string $ruta, string $nombreOriginal): array
    {
        return strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION)) === 'xlsx' ? self::leerXlsx($ruta) : self::leerCsv($ruta);
    }

    private static function leerCsv(string $ruta): array
    {
        $texto = (string) file_get_contents($ruta);
        $texto = preg_replace('/^\xEF\xBB\xBF/', '', $texto) ?? $texto;
        if (!mb_check_encoding($texto, 'UTF-8')) {
            $texto = mb_convert_encoding($texto, 'UTF-8', 'Windows-1252');
        }
        $primera = strtok($texto, "\n") ?: '';
        $sep = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $texto);
        rewind($flujo);
        $filas = [];
        while (($f = fgetcsv($flujo, 0, $sep, '"', '')) !== false) {
            if ($f !== [null]) {
                $filas[] = array_map(static fn($v): string => trim((string) $v), $f);
            }
        }
        fclose($flujo);
        return $filas;
    }

    private static function leerXlsx(string $ruta): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($ruta) !== true) {
            throw new \InvalidArgumentException('No se pudo abrir el archivo de Excel.');
        }
        $cadenas = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (simplexml_load_string($xml)->si ?? [] as $si) {
                $t = '';
                foreach ($si->xpath('.//*[local-name()="t"]') ?: [] as $nodo) {
                    $t .= (string) $nodo;
                }
                $cadenas[] = $t;
            }
        }
        $hoja = null;
        for ($i = 1; $i <= 30 && $hoja === null; $i++) {
            $hoja = $zip->getFromName("xl/worksheets/sheet$i.xml") ?: null;
        }
        $zip->close();
        if ($hoja === null) {
            throw new \InvalidArgumentException('El Excel no tiene hojas con datos.');
        }
        $filas = [];
        foreach (simplexml_load_string($hoja)->sheetData->row ?? [] as $row) {
            $fila = [];
            foreach ($row->c as $c) {
                preg_match('/^([A-Z]+)/', (string) $c['r'], $m);
                $col = 0;
                foreach (str_split($m[1] ?? 'A') as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $v = (string) ($c->v ?? '');
                $valor = match ((string) $c['t']) {
                    's' => $cadenas[(int) $v] ?? '',
                    'inlineStr' => (string) ($c->is->t ?? ''),
                    default => $v,
                };
                $fila[$col - 1] = trim($valor);
            }
            if ($fila) {
                $max = max(array_keys($fila));
                $filas[] = array_map(static fn($i) => $fila[$i] ?? '', range(0, $max));
            }
        }
        return $filas;
    }

    private static function plano(string $s): string
    {
        return preg_replace('/[^a-z0-9]+/', ' ', Profesores::base($s)) ?? '';
    }

    /** Mayúscula inicial en cada nombre y apellido («de», «del»… en minúscula). */
    public static function mayusculaInicial(string $s): string
    {
        $p = [];
        foreach (preg_split('/\s+/u', mb_strtolower(trim($s))) ?: [] as $w) {
            $p[] = in_array($w, self::PARTICULAS, true) ? $w : preg_replace_callback("/(^|[-'])(\\p{L})/u", static fn(array $m): string => $m[1] . mb_strtoupper($m[2]), $w);
        }
        return implode(' ', array_filter($p));
    }

    private static function rfc(string $v): ?string
    {
        $v = strtoupper(preg_replace('/\s+/', '', $v) ?? '');
        return preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{0,3}$/u', $v) ? $v : null;
    }

    private static function esCorreoValido(string $c): bool
    {
        return (bool) preg_match('/^[^@\s]+@([a-z0-9-]+\.)*uaemex\.mx$/', $c) && !preg_match('/@(alumno\.uaemex\.mx|alumnouaemex\.onmicrosoft\.com)$/', $c);
    }

    /**
     * Aplica la carga. No pisa nombres ni género ya registrados; no crea cuentas. @return array{resumen:array<string,int>,avisos:string[]}
     */
    public static function aplicar(array $filas): array
    {
        $enc = array_map(fn($h) => self::plano((string) $h), array_shift($filas) ?? []);
        $col = static function (array $nombres) use ($enc): ?int {
            foreach ($enc as $i => $h) {
                if (in_array($h, $nombres, true)) {
                    return $i;
                }
            }
            return null;
        };
        $cRfc = $col(['rfc', 'clave']);
        $cEmp = $col(['numero de empleado', 'no de empleado', 'num empleado', 'empleado', 'numero empleado']);
        $cNom = $col(['nombre', 'nombres', 'nombre s']);
        $cAp1 = $col(['primer apellido', 'apellido paterno', 'apellido1']);
        $cAp2 = $col(['segundo apellido', 'apellido materno', 'apellido2']);
        $cGen = $col(['genero', 'sexo']);
        $cMail = array_keys(array_filter($enc, static fn($h) => in_array($h, ['correo', 'email', 'correo institucional', 'correo electronico', 'correo 2', 'correo 3'], true) || str_starts_with($h, 'correo')));
        if ($cRfc === null) {
            throw new \InvalidArgumentException('El archivo debe traer una columna «RFC» (o «Clave»).');
        }
        $r = ['filas' => 0, 'actualizados' => 0, 'creados' => 0, 'correos' => 0, 'cuentas_enlazadas' => 0, 'con_aviso' => 0];
        $avisos = [];
        foreach ($filas as $n => $f) {
            $num = $n + 2;
            if (!array_filter($f)) {
                continue;
            }
            $r['filas']++;
            $rfc = self::rfc($f[$cRfc] ?? '');
            $emp = $cEmp !== null ? trim((string) ($f[$cEmp] ?? '')) : '';
            if ($rfc === null) {
                $avisos[] = "Fila $num: el RFC no es válido.";
                $r['con_aviso']++;
                continue;
            }
            $rfc10 = substr($rfc, 0, 10);
            $p = Database::una('SELECT * FROM profesores WHERE rfc10 = ?', [$rfc10]);
            if (!$p && $emp !== '') {
                $p = Database::una('SELECT * FROM profesores WHERE num_empleado = ?', [$emp]);
            }
            $nom = $cNom !== null ? trim((string) ($f[$cNom] ?? '')) : '';
            $ap1 = $cAp1 !== null ? trim((string) ($f[$cAp1] ?? '')) : '';
            $ap2 = $cAp2 !== null ? trim((string) ($f[$cAp2] ?? '')) : '';
            $gen = $cGen !== null ? strtoupper(trim((string) ($f[$cGen] ?? ''))) : '';
            $genero = in_array($gen, ['H', 'M', 'MASCULINO', 'FEMENINO'], true) ? ($gen[0] === 'H' || $gen === 'MASCULINO' ? 'masculino' : 'femenino') : null;
            if (!$p) {
                if ($nom === '' || $ap1 === '') {
                    $avisos[] = "Fila $num: no está en el directorio; para darlo de alta agrega Nombre y Primer apellido.";
                    $r['con_aviso']++;
                    continue;
                }
                $id = Database::ejecutar(
                    'INSERT INTO profesores (rfc10, rfc, nombre, apellido1, apellido2, genero, observaciones) VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$rfc10, $rfc, self::mayusculaInicial($nom), self::mayusculaInicial($ap1), self::mayusculaInicial($ap2) ?: null, $genero, 'Alta por Recursos Humanos']
                );
                $p = Database::una('SELECT * FROM profesores WHERE id = ?', [$id]);
                $r['creados']++;
            } else {
                $cambios = [];
                $par = [];
                if (strlen($rfc) > strlen((string) $p['rfc']) && substr($rfc, 0, 10) === $p['rfc10']) {
                    $cambios[] = 'rfc = ?';
                    $par[] = $rfc;
                }
                if ($emp !== '' && $p['num_empleado'] === null) {
                    if (Database::valor('SELECT id FROM profesores WHERE num_empleado = ? AND id <> ?', [$emp, $p['id']])) {
                        $avisos[] = "Fila $num: ese número de empleado ya pertenece a otro profesor.";
                        $r['con_aviso']++;
                    } else {
                        $cambios[] = 'num_empleado = ?';
                        $par[] = $emp;
                    }
                }
                if ($genero !== null && $p['genero'] === null) {
                    $cambios[] = 'genero = ?';
                    $par[] = $genero;
                }
                if ($cambios) {
                    Database::ejecutar('UPDATE profesores SET ' . implode(', ', $cambios) . ' WHERE id = ?', [...$par, $p['id']]);
                    $r['actualizados']++;
                }
            }
            $correos = [];
            foreach ($cMail as $c) {
                foreach (preg_split('/[;\s,]+/', strtolower((string) ($f[$c] ?? ''))) ?: [] as $m) {
                    if ($m !== '') {
                        $correos[$m] = true;
                    }
                }
            }
            foreach (array_keys($correos) as $m) {
                if (!self::esCorreoValido($m)) {
                    $avisos[] = "Fila $num: el correo «$m» no es un correo institucional válido y se omitió.";
                    $r['con_aviso']++;
                    continue;
                }
                $dueño = Database::valor('SELECT profesor_id FROM profesor_correos WHERE email = ?', [$m]);
                if ($dueño && (int) $dueño !== (int) $p['id']) {
                    $avisos[] = "Fila $num: el correo «$m» ya pertenece a otro profesor del directorio y se omitió.";
                    $r['con_aviso']++;
                    continue;
                }
                $cuenta = Database::una('SELECT id FROM usuarios WHERE email = ?', [$m]) ?: Database::una('SELECT usuario_id AS id FROM usuario_correos WHERE email = ?', [$m]);
                if ($cuenta && $p['usuario_id'] !== null && (int) $cuenta['id'] !== (int) $p['usuario_id']) {
                    $avisos[] = "Fila $num: el correo «$m» ya es de otra cuenta y se omitió.";
                    $r['con_aviso']++;
                    continue;
                }
                if (!$dueño) {
                    $tiene = Database::valor('SELECT 1 FROM profesor_correos WHERE profesor_id = ? AND principal = 1', [$p['id']]);
                    Database::ejecutar('INSERT INTO profesor_correos (profesor_id, email, principal) VALUES (?, ?, ?)', [$p['id'], $m, $tiene ? 0 : 1]);
                    $r['correos']++;
                }
                // Si ya existía una cuenta con ese correo y el profesor aún no tenía cuenta, son la misma persona: se enlazan.
                if ($cuenta && $p['usuario_id'] === null && !Database::valor('SELECT 1 FROM profesores WHERE usuario_id = ?', [$cuenta['id']])) {
                    Database::ejecutar('UPDATE profesores SET usuario_id = ? WHERE id = ? AND usuario_id IS NULL', [$cuenta['id'], $p['id']]);
                    $p['usuario_id'] = (int) $cuenta['id'];
                    $r['cuentas_enlazadas']++;
                }
            }
        }
        return ['resumen' => $r, 'avisos' => array_slice($avisos, 0, 60)];
    }

    /** Cuentas sin enlazar con un profesor del directorio de nombre igual (ignorando acentos, mayúsculas y grado). @return array<int,array> */
    public static function cuentasPorEnlazar(int $limite = 40): array
    {
        $libres = [];
        foreach (Database::todas('SELECT id, nombre, apellido1, apellido2 FROM profesores WHERE usuario_id IS NULL') as $p) {
            $libres[self::huella(Profesores::nombreCompleto($p))][] = ['id' => (int) $p['id'], 'nombre' => Profesores::nombreCompleto($p)];
        }
        $salida = [];
        foreach (Database::todas(
            'SELECT u.id, u.nombre, u.email, u.rol FROM usuarios u WHERE u.activo = 1 AND NOT EXISTS (SELECT 1 FROM profesores p WHERE p.usuario_id = u.id) ORDER BY u.nombre'
        ) as $u) {
            $h = self::huella((string) $u['nombre']);
            if (isset($libres[$h])) {
                $salida[] = $u + ['candidatos' => $libres[$h]];
                if (count($salida) >= $limite) {
                    break;
                }
            }
        }
        return $salida;
    }

    /** Nombre sin grado, acentos ni orden: «Dr. Pérez Juan» y «Juan Perez» coinciden. */
    private static function huella(string $nombre): string
    {
        $t = preg_split('/\s+/', trim(preg_replace('/^(?:(?:[a-zA-Zñáéíóú]{1,5}\.\s*)|(?:en\s))+/iu', '', trim($nombre)) ?? '')) ?: [];
        $t = array_values(array_filter(array_map(static fn($x) => preg_replace('/[^a-z0-9ñ]/u', '', Profesores::base($x)) ?? '', $t), static fn($x) => $x !== '' && !in_array($x, self::PARTICULAS, true)));
        sort($t);
        return implode(' ', $t);
    }

    /** Profesores con cuenta cuyo directorio trae un correo distinto del de su cuenta (p. ej. el @uaemex.mx nuevo). @return array<int,array> */
    public static function correosPorPasar(int $limite = 60): array
    {
        return Database::todas(
            "SELECT u.id AS usuario_id, u.nombre, u.email AS correo_actual, pc.email AS correo_directorio
             FROM profesores p JOIN usuarios u ON u.id = p.usuario_id AND u.activo = 1
             JOIN profesor_correos pc ON pc.profesor_id = p.id AND pc.email COLLATE utf8mb4_spanish_ci <> u.email COLLATE utf8mb4_spanish_ci
             WHERE NOT EXISTS (SELECT 1 FROM usuario_correos uc WHERE uc.email = pc.email)
               AND NOT EXISTS (SELECT 1 FROM usuarios x WHERE x.email = pc.email)
               AND pc.email LIKE '%@uaemex.mx' AND pc.email NOT LIKE '%@%.uaemex.mx'
             ORDER BY u.nombre LIMIT $limite"
        );
    }

    /** RR. HH. enlaza una cuenta con un profesor y suma el correo de la cuenta al directorio. */
    public static function enlazar(int $usuarioId, int $profesorId): void
    {
        $p = Database::una('SELECT id, usuario_id FROM profesores WHERE id = ?', [$profesorId]);
        $u = Database::una('SELECT id, email FROM usuarios WHERE id = ? AND activo = 1', [$usuarioId]);
        if (!$p || !$u || $p['usuario_id'] !== null || Database::valor('SELECT 1 FROM profesores WHERE usuario_id = ?', [$usuarioId])) {
            throw new \InvalidArgumentException('Esa cuenta o ese profesor ya no están disponibles para enlazar.');
        }
        Database::ejecutar('UPDATE profesores SET usuario_id = ? WHERE id = ? AND usuario_id IS NULL', [$usuarioId, $profesorId]);
        if (!Database::valor('SELECT 1 FROM profesor_correos WHERE email = ?', [$u['email']])) {
            Database::ejecutar('INSERT INTO profesor_correos (profesor_id, email, principal) VALUES (?, ?, 0)', [$profesorId, $u['email']]);
        }
        Database::ejecutar('UPDATE usuarios u JOIN profesores p ON p.usuario_id = u.id SET u.genero = p.genero WHERE u.id = ? AND u.genero IS NULL AND p.genero IS NOT NULL', [$usuarioId]);
        Database::ejecutar(
            "INSERT IGNORE INTO programa_profesores (programa_id, usuario_id, tipo_vinculo, fecha_inicio, activo, observaciones)
             SELECT un.programa_id, ?, 'docente', a.fecha_inicio, 1, 'Directorio de profesores'
             FROM profesor_adscripciones a JOIN profesor_unidades un ON un.id = a.unidad_id AND un.programa_id IS NOT NULL WHERE a.profesor_id = ?",
            [$usuarioId, $profesorId]
        );
        // Las constancias ya emitidas a este profesor pasan a su cuenta.
        Database::ejecutar('UPDATE tit_constancias SET usuario_id = ? WHERE profesor_id = ? AND usuario_id IS NULL', [$usuarioId, $profesorId]);
    }

    /** La cuenta pasa a usar como correo vigente un correo de su registro del directorio (conserva el anterior como alias). */
    public static function usarCorreoDelDirectorio(int $usuarioId, string $correo): void
    {
        $correo = strtolower(trim($correo));
        $ok = Database::valor(
            'SELECT 1 FROM profesor_correos c JOIN profesores p ON p.id = c.profesor_id WHERE c.email = ? AND p.usuario_id = ?',
            [$correo, $usuarioId]
        );
        if (!$ok || !Identidad::disponible($correo, $usuarioId)) {
            throw new \InvalidArgumentException('Ese correo no se puede asignar a la cuenta.');
        }
        Identidad::asignarCorreo($usuarioId, $correo, 'recursos_humanos');
    }
}
