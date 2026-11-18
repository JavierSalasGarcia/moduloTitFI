<?php
/**
 * Carga en la base las relaciones académicas de posgrado a partir del JSON que produce tools/exceles_posgrado_a_json.py:
 * catálogo de asignaturas, asignaturas que imparte cada profesor en el periodo, adscripción de profesores a cada programa de
 * posgrado, coordinadores (solo quienes tienen cuenta) y comités tutorales. Es repetible: no duplica nada.
 *
 * Uso:  php tools/importar_posgrado_academico.php <datos.json> [--simular]
 * Requiere la migración 053_posgrado_academico.sql. El JSON tiene datos de personas: no se versiona.
 */
declare(strict_types=1);

use App\Core\AccessControl;
use App\Core\App;
use App\Core\Cifrado;
use App\Core\Database;
use App\Core\Profesores;

if (PHP_SAPI !== 'cli' && !defined('IMPORTAR_POSGRADO_DESDE_WEB')) {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/bootstrap.php';
(new ReflectionProperty(App::class, 'config'))->setValue(null, require APP_RAIZ . '/config.php');

/** Huella para buscar o evitar duplicados sin guardar el dato en claro: HMAC con la llave, o SHA-256 si no hay llave. */
function pos_huella(string $valor, string $contexto): string
{
    $valor = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $valor) ?? $valor), 'UTF-8');
    try {
        return 'i:' . Cifrado::indiceCiego($valor, $contexto);
    } catch (\Throwable) {
        return 'h:' . hash('sha256', 'trazafi|' . $contexto . '|' . $valor);
    }
}

/** Cifra si hay llave; si no, deja el valor en claro (mismo criterio que el resto de la plataforma). */
function pos_cifrar(?string $valor, string $contexto): ?string
{
    if ($valor === null || $valor === '') {
        return $valor;
    }
    try {
        return Cifrado::disponible() ? Cifrado::cifrar($valor, $contexto) : $valor;
    } catch (\Throwable) {
        return $valor;
    }
}

/** Palabras normalizadas de un nombre: minúsculas, sin acentos, sin grado (Dr., Mtra., M. en I.) ni partículas. */
function pos_palabras(string $s): array
{
    $s = mb_strtolower(trim($s), 'UTF-8');
    $s = strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
    $s = preg_replace('/(^|\s)(dra|dr|mtra|mtro|m\.? ?en ?[a-z]\.?|mc|ing|lic)\.?(?=\s)/u', ' ', ' ' . $s . ' ') ?? $s;
    $t = preg_split('/[\s.]+/u', trim(preg_replace('/[^a-z.\s]/u', ' ', $s) ?? $s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    return array_values(array_filter($t, static fn(string $x): bool => !in_array($x, ['de', 'del', 'la', 'las', 'los', 'y'], true)));
}

/**
 * Ficha del directorio para un nombre escrito con variaciones («Adriana H. Vilchis González», «Ivan Martínez Cienfuegos»):
 * exige el primer apellido, el segundo si la ficha lo tiene y el nombre lo trae, y al menos un nombre (completo, inicial o con
 * una letra de diferencia). Solo devuelve la ficha si es la única con el mejor puntaje y este es suficiente; si no, null.
 */
function pos_ficha_aproximada(string $nombre): ?int
{
    static $dir = null;
    if ($dir === null) {
        $dir = [];
        foreach (Database::todas('SELECT id, nombre, apellido1, apellido2 FROM profesores') as $p) {
            $dir[] = ['id' => (int) $p['id'], 'nom' => pos_palabras((string) $p['nombre']), 'a1' => implode(' ', pos_palabras((string) $p['apellido1'])),
                      'a2' => implode(' ', pos_palabras((string) ($p['apellido2'] ?? ''))), 'a1p' => pos_palabras((string) $p['apellido1']), 'a2p' => pos_palabras((string) ($p['apellido2'] ?? ''))];
        }
    }
    $t = pos_palabras($nombre);
    $txt = ' ' . implode(' ', $t) . ' ';
    $mejor = [];
    foreach ($dir as $p) {
        if ($p['a1'] === '' || !str_contains($txt, ' ' . $p['a1'] . ' ')) {
            continue;
        }
        $puntos = 2;
        if ($p['a2'] !== '') {
            if (!str_contains($txt, ' ' . $p['a2'] . ' ')) {
                continue;   // el segundo apellido de la ficha no aparece: no es la misma persona
            }
            $puntos += 2;
        }
        $nombres = 0;
        foreach (array_diff($t, $p['a1p'], $p['a2p']) as $x) {
            foreach ($p['nom'] as $n) {
                if ($x === $n || (strlen($x) === 1 && $n[0] === $x) || (strlen($x) >= 4 && levenshtein($x, $n) <= 1)) {
                    $nombres++;
                    break;
                }
            }
        }
        if ($nombres === 0) {
            continue;
        }
        $mejor[] = [$puntos + $nombres, $p['id']];
    }
    rsort($mejor);
    if (!$mejor || $mejor[0][0] < 5 || (isset($mejor[1]) && $mejor[1][0] === $mejor[0][0])) {
        return null;
    }
    return (int) $mejor[0][1];
}

function importar_posgrado_academico(array $d, bool $simular = false): array
{
    $r = ['asignaturas' => 0, 'materias' => 0, 'materias_sin_profesor' => [], 'materias_sin_asignatura' => [], 'adscripciones' => 0,
          'docentes_con_cuenta' => 0, 'coordinadores' => [], 'comites' => 0, 'miembros' => 0, 'miembros_ligados' => 0,
          'miembros_sin_ficha' => [], 'comites_sin_programa' => 0];
    $periodo = (string) ($d['periodo'] ?? '2026B');
    $inicioPeriodo = '2026-08-01';
    $prog = [];
    foreach (Database::todas('SELECT id, siglas FROM programas') as $p) {
        $prog[(string) $p['siglas']] = (int) $p['id'];
    }
    $pdo = Database::pdo();
    $pdo->beginTransaction();
    try {
        // 1) Catálogo de asignaturas.
        foreach ($d['asignaturas'] ?? [] as $a) {
            Database::ejecutar(
                'INSERT INTO asignaturas (clave, nombre, programa_id, horas_teoricas, horas_practicas, activa) VALUES (?, ?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), programa_id = VALUES(programa_id), horas_teoricas = VALUES(horas_teoricas), horas_practicas = VALUES(horas_practicas)',
                [$a['clave'], $a['nombre'], $prog[$a['programa'] ?? ''] ?? null, (int) $a['ht'], (int) $a['hp']]
            );
            $r['asignaturas']++;
        }
        $asig = [];
        foreach (Database::todas('SELECT id, clave FROM asignaturas') as $a) {
            $asig[(string) $a['clave']] = (int) $a['id'];
        }
        $ficha = static fn(string $rfc): ?array => Database::una('SELECT id, usuario_id FROM profesores WHERE rfc10 = ?', [substr($rfc, 0, 10)]);

        // 2) Asignaturas que imparte cada profesor en el periodo.
        foreach ($d['materias'] ?? [] as $m) {
            $f = $ficha((string) $m['rfc']);
            if (!$f) {
                $r['materias_sin_profesor'][] = $m['rfc'];
                continue;
            }
            foreach ($m['claves'] as $clave) {
                if (!isset($asig[$clave])) {
                    $r['materias_sin_asignatura'][] = $clave;
                    continue;
                }
                Database::ejecutar('INSERT IGNORE INTO profesor_asignaturas (profesor_id, asignatura_id, periodo) VALUES (?, ?, ?)', [(int) $f['id'], $asig[$clave], $periodo]);
                $r['materias']++;
            }
        }

        // 3) Adscripción de cada profesor al programa de posgrado específico.
        foreach ($d['profesores'] ?? [] as $p) {
            $f = $ficha((string) $p['rfc']);
            if (!$f) {
                continue;
            }
            foreach ($p['programas'] as $siglas) {
                Database::ejecutar(
                    'INSERT IGNORE INTO profesor_adscripciones (profesor_id, unidad_id, periodo, fecha_inicio)
                     SELECT ?, u.id, ?, ? FROM profesor_unidades u WHERE u.clave = ?',
                    [(int) $f['id'], $periodo, $inicioPeriodo, $siglas]
                );
                $r['adscripciones']++;
            }
        }
        // Quienes ya tienen cuenta quedan como docentes del programa (lo mismo que hace Profesores::vincularCuenta).
        $r['docentes_con_cuenta'] = Database::ejecutar(
            "INSERT IGNORE INTO programa_profesores (programa_id, usuario_id, tipo_vinculo, fecha_inicio, activo, observaciones)
             SELECT u.programa_id, p.usuario_id, 'docente', a.fecha_inicio, 1, 'Directorio de profesores'
               FROM profesor_adscripciones a JOIN profesores p ON p.id = a.profesor_id JOIN profesor_unidades u ON u.id = a.unidad_id
              WHERE u.nivel = 'posgrado' AND u.programa_id IS NOT NULL AND p.usuario_id IS NOT NULL"
        );

        // 4) Coordinadores: la tabla exige cuenta; los demás quedan reportados.
        foreach ($d['coordinadores'] ?? [] as $c) {
            $siglas = (string) ($c['programa'] ?? '');
            if (empty($c['rfc'])) {
                $r['coordinadores'][$siglas] = 'sin RFC en el Excel';
                continue;
            }
            $f = $ficha((string) $c['rfc']);
            if (!$f) {
                $r['coordinadores'][$siglas] = 'no está en el directorio';
                continue;
            }
            if (!$f['usuario_id']) {
                $r['coordinadores'][$siglas] = 'sin cuenta';
                continue;
            }
            $ya = Database::valor("SELECT 1 FROM programa_responsables WHERE programa_id = ? AND usuario_id = ? AND cargo = 'coordinador' AND activo = 1", [$prog[$siglas] ?? 0, (int) $f['usuario_id']]);
            if (!$ya && isset($prog[$siglas])) {
                Database::ejecutar(
                    "INSERT INTO programa_responsables (programa_id, usuario_id, cargo, fecha_inicio, activo, observaciones) VALUES (?, ?, 'coordinador', ?, 1, 'Coordinadores 2026B')",
                    [$prog[$siglas], (int) $f['usuario_id'], $c['inicio'] ?: $inicioPeriodo]
                );
            }
            $r['coordinadores'][$siglas] = 'registrado';
        }

        // 5) Comités tutorales (nombre y cuenta del estudiante cifrados; integrantes ligados a su ficha si el nombre coincide de forma única).
        foreach ($d['comites'] ?? [] as $c) {
            $pid = $prog[(string) ($c['programa'] ?? '')] ?? null;
            if (!$pid || empty($c['nombre'])) {
                $r['comites_sin_programa']++;
                continue;
            }
            $huella = pos_huella((string) $c['nombre'], 'pos_comites.alumno_nombre');
            $id = (int) Database::valor('SELECT id FROM pos_comites WHERE programa_id = ? AND alumno_huella = ?', [$pid, $huella]);
            $titulo = $c['titulo'] ?? null;
            if ($id === 0) {
                Database::ejecutar(
                    'INSERT INTO pos_comites (programa_id, alumno_nombre, alumno_huella, genero, titulo, periodo) VALUES (?, ?, ?, ?, ?, ?)',
                    [$pid, '-', $huella, $c['genero'] ?? null, $titulo, $periodo]
                );
                $id = (int) Database::valor('SELECT id FROM pos_comites WHERE programa_id = ? AND alumno_huella = ?', [$pid, $huella]);
            }
            $cuenta = $c['cuenta'] ?? null;
            Database::ejecutar(
                'UPDATE pos_comites SET alumno_nombre = ?, numero_cuenta = ?, cuenta_huella = ?, genero = COALESCE(?, genero), titulo = COALESCE(?, titulo) WHERE id = ?',
                [pos_cifrar((string) $c['nombre'], 'pos_comites.alumno_nombre#' . $id), pos_cifrar($cuenta, 'pos_comites.numero_cuenta#' . $id),
                 $cuenta ? pos_huella((string) $cuenta, 'pos_comites.numero_cuenta') : null, $c['genero'] ?? null, $titulo, $id]
            );
            $r['comites']++;
            foreach ($c['miembros'] as $m) {
                $f = Profesores::porNombre((string) $m['nombre']);
                $profesorId = ($f['profesor_id'] ?? null) ?: pos_ficha_aproximada((string) $m['nombre']);
                Database::ejecutar(
                    'INSERT INTO pos_comite_miembros (comite_id, rol, orden, nombre, profesor_id, externo, nota) VALUES (?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), profesor_id = COALESCE(VALUES(profesor_id), profesor_id), externo = VALUES(externo), nota = VALUES(nota)',
                    [$id, $m['rol'], (int) $m['orden'], $m['nombre'], $profesorId, $m['externo'] ? 1 : 0, $m['nota']]
                );
                $r['miembros']++;
                if ($profesorId) {
                    $r['miembros_ligados']++;
                } else {
                    $r['miembros_sin_ficha'][$m['nombre']] = true;
                }
            }
        }
        $r['miembros_sin_ficha'] = array_keys($r['miembros_sin_ficha']);
        $r['materias_sin_asignatura'] = array_values(array_unique($r['materias_sin_asignatura']));
        $simular ? $pdo->rollBack() : $pdo->commit();
    } catch (\Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
    return $r;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    if (($argv[1] ?? '') === '') {
        fwrite(STDERR, "Uso: php tools/importar_posgrado_academico.php <datos.json> [--simular]\n");
        exit(1);
    }
    $datos = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
    $res = AccessControl::internal(fn() => importar_posgrado_academico($datos, in_array('--simular', $argv, true)));
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
}
