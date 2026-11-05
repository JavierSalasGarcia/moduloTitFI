<?php

declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Database;
use App\Modules\Titulacion\Catalogo;
use RuntimeException;

/**
 * Acceso de egresados sin correo institucional (docs/ACCESO_EGRESADOS.md).
 * Una persona solicita acceso con su correo personal, lo verifica con un código, el sistema compara lo que declara con los
 * registros del departamento y el personal de Titulación aprueba. Solo entonces existe una cuenta, limitada a Titulación.
 */
final class AccesoEgresados
{
    public const VIGENCIA_MESES = 24;
    public const ESTADOS = [
        'sin_verificar' => 'Correo sin verificar',
        'requiere_docs' => 'Esperando documentos',
        'pendiente' => 'Por validar',
        'aprobada' => 'Aprobada · falta contraseña',
        'activa' => 'Activa',
        'rechazada' => 'Rechazada',
    ];

    public static function tablasListas(): bool
    {
        try {
            return (int) Database::valor("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name IN ('egresado_solicitudes','egresado_documentos')") === 2
                && (int) Database::valor("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'usuarios' AND column_name = 'egresado_externo'") === 1;
        } catch (\Throwable) {
            return false;
        }
    }

    /** Cuenta con ceros a la izquierda a 7 dígitos, como en los registros del departamento. */
    public static function cuentaNorm(string $c): string
    {
        $d = preg_replace('/\D+/', '', $c) ?? '';
        return $d === '' ? '' : str_pad(ltrim($d, '0') === '' ? '0' : $d, 7, '0', STR_PAD_LEFT);
    }

    public static function hashToken(string $t): string
    {
        return hash('sha256', $t);
    }

    public static function porToken(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{40}$/D', $token)) {
            return null;
        }
        return Database::una('SELECT * FROM egresado_solicitudes WHERE token_hash = ?', [self::hashToken($token)]);
    }

    /** @return array{token:string,id:int} */
    public static function crear(array $d, string $ip): array
    {
        $token = bin2hex(random_bytes(20));
        $id = Database::ejecutar(
            'INSERT INTO egresado_solicitudes (token_hash,nombre,genero,carrera,cuenta,email,telefono,estado,ip_hash) VALUES (?,?,?,?,?,?,?,?,?)',
            [self::hashToken($token), $d['nombre'], $d['genero'], $d['carrera'], $d['cuenta'], $d['email'], $d['telefono'] ?: null, 'sin_verificar', hash('sha256', $ip . '|egresado')]
        );
        return ['token' => $token, 'id' => (int) $id];
    }

    /** ¿Ya hay una solicitud abierta con esa cuenta o ese correo? */
    public static function hayAbierta(string $cuenta, string $email): bool
    {
        return (bool) Database::valor("SELECT 1 FROM egresado_solicitudes WHERE (cuenta = ? OR email = ?) AND estado IN ('sin_verificar','requiere_docs','pendiente','aprobada') LIMIT 1", [$cuenta, $email]);
    }

    /** ¿Ese correo ya pertenece a una cuenta de la plataforma? */
    public static function correoConCuenta(string $email): bool
    {
        if (Database::valor('SELECT 1 FROM usuarios WHERE email = ? LIMIT 1', [$email])) {
            return true;
        }
        try {
            return (bool) Database::valor('SELECT 1 FROM usuario_correos WHERE email = ? LIMIT 1', [$email]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Envía (o reenvía) el código de verificación del correo. */
    public static function enviarCodigo(array $s, string $urlSolicitud): bool
    {
        if ($s['codigo_enviado'] && strtotime((string) $s['codigo_enviado']) > time() - 60) {
            return false;
        }
        $codigo = (string) random_int(100000, 999999);
        Database::ejecutar('UPDATE egresado_solicitudes SET codigo_hash = ?, codigo_expira = ?, codigo_intentos = 0, codigo_enviado = ? WHERE id = ?',
            [password_hash($codigo, PASSWORD_DEFAULT), date('Y-m-d H:i:s', time() + 600), date('Y-m-d H:i:s'), $s['id']]);
        return Correo::enviar((string) $s['email'], (string) $s['nombre'], 'Verifica tu correo · Acceso de egresados Traza FI',
            "Tu código para verificar el correo de tu solicitud de acceso es: $codigo\n\nVence en 10 minutos. Con tu solicitud podrás usar únicamente el módulo de Titulación. Si no la hiciste, ignora este mensaje: no se crea ninguna cuenta.",
            'Continuar mi solicitud', $urlSolicitud, 'acceso_egresado', null, false);
    }

    /** Valida el código; si es correcto, prevalida y deja la solicitud en la etapa que corresponde. */
    public static function verificarCodigo(array $s, string $codigo): bool
    {
        if ($s['estado'] !== 'sin_verificar' || !$s['codigo_hash'] || strtotime((string) $s['codigo_expira']) < time() || (int) $s['codigo_intentos'] >= 5) {
            return false;
        }
        Database::ejecutar('UPDATE egresado_solicitudes SET codigo_intentos = codigo_intentos + 1 WHERE id = ?', [$s['id']]);
        if (!preg_match('/^\d{6}$/D', $codigo) || !password_verify($codigo, (string) $s['codigo_hash'])) {
            return false;
        }
        $pre = self::prevalidar((string) $s['cuenta'], (string) $s['carrera'], (string) $s['email'], (string) $s['nombre']);
        Database::ejecutar('UPDATE egresado_solicitudes SET verificado_en = ?, codigo_hash = NULL, codigo_expira = NULL, estado = ?, prevalidacion = ?, prevalidacion_detalle = ? WHERE id = ?',
            [date('Y-m-d H:i:s'), $pre['resultado'] === 'coincide' ? 'pendiente' : 'requiere_docs', $pre['resultado'], mb_substr($pre['detalle'], 0, 500), $s['id']]);
        return true;
    }

    /**
     * Compara lo declarado con los registros del departamento (historial de actas, solicitudes, protocolos y artículo 5).
     * «coincide»: cuenta, licenciatura (si el registro la trae), correo y nombre; «media»: cuenta aparece pero falta algo;
     * «ninguna»: la cuenta no aparece en ningún registro.
     * @return array{resultado:string,detalle:string}
     */
    public static function prevalidar(string $cuenta, string $carrera, string $email, string $nombre): array
    {
        $cuenta = self::cuentaNorm($cuenta);
        $email = strtolower(trim($email));
        $filas = [];
        $consultas = [
            ['tit_historico', 'SELECT alumno, carrera, correos AS correo FROM tit_historico WHERE cuenta = ? OR cuenta = ?'],
            ['solicitudes de EGEL/aprovechamiento', 'SELECT alumno, carrera, correo FROM tit_solicitudes_historicas WHERE cuenta = ? OR cuenta = ?'],
            ['protocolos recibidos', 'SELECT alumno, carrera, correo FROM tit_protocolos_recibidos WHERE cuenta = ? OR cuenta = ?'],
            ['artículo 5', 'SELECT alumno, carrera, NULL AS correo FROM tit_articulo5 WHERE cuenta = ? OR cuenta = ?'],
        ];
        $sinCeros = ltrim($cuenta, '0');
        foreach ($consultas as [$fuente, $sql]) {
            try {
                foreach (Database::todas($sql, [$cuenta, $sinCeros === '' ? $cuenta : $sinCeros]) as $f) {
                    $f['fuente'] = $fuente;
                    $filas[] = $f;
                }
            } catch (\Throwable) {
                // tabla aún sin migrar: se ignora esa fuente
            }
        }
        if (!$filas) {
            return ['resultado' => 'ninguna', 'detalle' => 'La cuenta no aparece en los registros del departamento.'];
        }
        $palabras = self::palabras($nombre);
        $nombreOk = false;
        $carreraOk = false;
        $correoOk = false;
        $fuentes = [];
        foreach ($filas as $f) {
            $fuentes[$f['fuente']] = true;
            if (count(array_intersect($palabras, self::palabras((string) $f['alumno']))) >= 2) {
                $nombreOk = true;
            }
            if (($f['carrera'] ?? '') === '' || $f['carrera'] === $carrera) {
                $carreraOk = true;
            }
            if ($f['correo'] !== null && $email !== '' && str_contains(strtolower((string) $f['correo']), $email)) {
                $correoOk = true;
            }
        }
        $partes = ['cuenta encontrada en: ' . implode(', ', array_keys($fuentes)), 'nombre ' . ($nombreOk ? 'coincide' : 'NO coincide'), 'licenciatura ' . ($carreraOk ? 'coincide' : 'NO coincide'), 'correo ' . ($correoOk ? 'coincide con el que dio al departamento' : 'distinto o no registrado')];
        return ['resultado' => $nombreOk && $carreraOk && $correoOk ? 'coincide' : 'media', 'detalle' => implode('; ', $partes) . '.'];
    }

    /** @return list<string> */
    private static function palabras(string $s): array
    {
        $s = strtoupper(strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N', 'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']));
        $p = preg_split('/[^A-Z]+/', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter($p, static fn(string $w): bool => strlen($w) > 2 && !in_array($w, ['DEL', 'LAS', 'LOS'], true))));
    }

    /** Guarda un documento de identidad (PDF, JPG o PNG de hasta 5 MB) y reemplaza el anterior del mismo tipo. */
    public static function guardarDocumento(int $solicitudId, string $tipo, array $archivo): void
    {
        if (!in_array($tipo, ['identificacion', 'comprobante'], true)) {
            throw new RuntimeException('Tipo de documento no válido.');
        }
        $ruta = guardar_archivo_datos($archivo, 'egresados/' . $solicitudId, ['pdf', 'jpg', 'jpeg', 'png'], 5 * 1048576);
        $base = rtrim((string) \App\Core\App::config('archivos.storage'), '/\\');
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($base . '/' . $ruta) ?: 'application/octet-stream';
        foreach (Database::todas('SELECT id, ruta FROM egresado_documentos WHERE solicitud_id = ? AND tipo = ? AND borrado_en IS NULL', [$solicitudId, $tipo]) as $viejo) {
            self::borrarArchivo((string) $viejo['ruta']);
            Database::ejecutar('UPDATE egresado_documentos SET ruta = NULL, borrado_en = NOW() WHERE id = ?', [$viejo['id']]);
        }
        Database::ejecutar('INSERT INTO egresado_documentos (solicitud_id,tipo,ruta,nombre_original,mime,tamano,sha256) VALUES (?,?,?,?,?,?,?)',
            [$solicitudId, $tipo, $ruta, mb_substr((string) $archivo['name'], 0, 160), $mime, (int) filesize($base . '/' . $ruta), hash_file('sha256', $base . '/' . $ruta)]);
    }

    /** @return array<string,array<string,mixed>> documentos vigentes por tipo */
    public static function documentos(int $solicitudId): array
    {
        $r = [];
        foreach (Database::todas('SELECT * FROM egresado_documentos WHERE solicitud_id = ? AND borrado_en IS NULL ORDER BY id', [$solicitudId]) as $d) {
            $r[$d['tipo']] = $d;
        }
        return $r;
    }

    /** Pasa de «esperando documentos» a «por validar» cuando ya están ambos. */
    public static function enviarAValidar(array $s): bool
    {
        if ($s['estado'] !== 'requiere_docs' || count(self::documentos((int) $s['id'])) < 2) {
            return false;
        }
        Database::ejecutar("UPDATE egresado_solicitudes SET estado = 'pendiente', motivo = NULL WHERE id = ?", [$s['id']]);
        return true;
    }

    private static function borrarArchivo(string $ruta): void
    {
        $base = realpath((string) \App\Core\App::config('archivos.storage'));
        $real = $base === false ? false : realpath($base . '/' . $ruta);
        if ($real !== false && str_starts_with($real, $base . DIRECTORY_SEPARATOR) && is_file($real)) {
            @unlink($real);
        }
    }

    /** Borra los archivos de identidad al resolver; conserva el sello (huella, tipo y fechas). */
    public static function borrarDocumentos(int $solicitudId): void
    {
        foreach (Database::todas('SELECT id, ruta FROM egresado_documentos WHERE solicitud_id = ? AND borrado_en IS NULL', [$solicitudId]) as $d) {
            self::borrarArchivo((string) $d['ruta']);
            Database::ejecutar('UPDATE egresado_documentos SET ruta = NULL, borrado_en = NOW() WHERE id = ?', [$d['id']]);
        }
    }

    /**
     * Aprueba: crea la cuenta (rol alumno, alcance de egresado, correo ya verificado) y envía el enlace para fijar la contraseña.
     * @throws RuntimeException cuando no se puede aprobar (mensaje legible)
     */
    public static function aprobar(int $id, int $personalId, string $urlBase): void
    {
        $s = Database::una('SELECT * FROM egresado_solicitudes WHERE id = ?', [$id]);
        if (!$s || $s['estado'] !== 'pendiente') {
            throw new RuntimeException('La solicitud ya no está por validar.');
        }
        if (self::correoConCuenta((string) $s['email'])) {
            throw new RuntimeException('Ese correo ya pertenece a una cuenta. Pídele a la persona que inicie sesión o recupere su acceso.');
        }
        $programa = null;
        foreach ((array) (Catalogo::carreras()[$s['carrera']]['siglas'] ?? []) as $sig) {
            $programa = Database::valor('SELECT id FROM programas WHERE siglas = ?', [$sig]) ?: $programa;
        }
        $token = bin2hex(random_bytes(20));
        AccessControl::internal(function () use ($s, $personalId, $programa, $token): void {
            $pdo = Database::pdo();
            $pdo->beginTransaction();
            try {
                $uid = (int) Database::ejecutar(
                    "INSERT INTO usuarios (nombre,genero,email,password_hash,programa_id,rol,activo,permite_contrasena,logins_contrasena_restantes,egresado_externo,egresado_vence,egresado_cuenta,egresado_carrera,egresado_validado_por,egresado_validado_en)
                     VALUES (?,?,?,?,?,'alumno',1,1,20,1,?,?,?,?,NOW())",
                    [$s['nombre'], $s['genero'], $s['email'], password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), $programa,
                        date('Y-m-d', strtotime('+' . self::VIGENCIA_MESES . ' months')), $s['cuenta'], $s['carrera'], $personalId]
                );
                $ahora = date('Y-m-d H:i:s');
                Database::ejecutar('INSERT INTO cuenta_verificacion (usuario_id,verificado_en,creado_en) VALUES (?,?,?)', [$uid, $ahora, $ahora]);
                try {
                    Database::ejecutar("INSERT IGNORE INTO usuario_correos (usuario_id,email,principal,origen) VALUES (?,?,1,'cuenta')", [$uid, $s['email']]);
                } catch (\Throwable) {
                }
                Database::ejecutar("UPDATE egresado_solicitudes SET estado = 'aprobada', usuario_id = ?, resuelto_por = ?, resuelto_en = ?, motivo = NULL, token_hash = ?, clave_expira = ? WHERE id = ?",
                    [$uid, $personalId, $ahora, self::hashToken($token), date('Y-m-d H:i:s', time() + 7 * 86400), $s['id']]);
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                throw $e;
            }
        });
        self::borrarDocumentos($id);
        Audit::record('egresado_aprobado', 'correcto', 'solicitud ' . $id);
        Correo::enviar((string) $s['email'], (string) $s['nombre'], 'Tu acceso a Traza FI fue aprobado',
            "El Departamento de Evaluación Profesional validó tu identidad. Fija tu contraseña con el botón; el enlace vale 7 días. Tu cuenta usa tu correo personal, pide un código por correo al iniciar sesión y solo da acceso al módulo de Titulación.",
            'Fijar mi contraseña', rtrim($urlBase, '/') . '/registro/egresado/s/' . $token, 'acceso_egresado', null, false);
    }

    public static function devolver(int $id, int $personalId, string $motivo, string $urlBase): void
    {
        $s = Database::una('SELECT * FROM egresado_solicitudes WHERE id = ?', [$id]);
        if (!$s || $s['estado'] !== 'pendiente') {
            throw new RuntimeException('La solicitud ya no está por validar.');
        }
        Database::ejecutar("UPDATE egresado_solicitudes SET estado = 'requiere_docs', motivo = ?, resuelto_por = ? WHERE id = ?", [mb_substr($motivo, 0, 500), $personalId, $id]);
        Audit::record('egresado_devuelto', 'correcto', 'solicitud ' . $id);
        $token = bin2hex(random_bytes(20));
        Database::ejecutar('UPDATE egresado_solicitudes SET token_hash = ? WHERE id = ?', [self::hashToken($token), $id]);
        Correo::enviar((string) $s['email'], (string) $s['nombre'], 'Tu solicitud de acceso necesita una corrección',
            "El personal de Titulación revisó tu solicitud y necesita que la corrijas o completes tus documentos.\n\nMotivo: " . $motivo,
            'Revisar mi solicitud', rtrim($urlBase, '/') . '/registro/egresado/s/' . $token, 'acceso_egresado', null, false);
    }

    public static function rechazar(int $id, int $personalId, string $motivo): void
    {
        $s = Database::una('SELECT * FROM egresado_solicitudes WHERE id = ?', [$id]);
        if (!$s || !in_array($s['estado'], ['pendiente', 'requiere_docs'], true)) {
            throw new RuntimeException('La solicitud ya no se puede rechazar.');
        }
        Database::ejecutar("UPDATE egresado_solicitudes SET estado = 'rechazada', motivo = ?, resuelto_por = ?, resuelto_en = NOW() WHERE id = ?", [mb_substr($motivo, 0, 500), $personalId, $id]);
        self::borrarDocumentos($id);
        Audit::record('egresado_rechazado', 'correcto', 'solicitud ' . $id);
        Correo::enviar((string) $s['email'], (string) $s['nombre'], 'Tu solicitud de acceso no fue aprobada',
            "El personal de Titulación no pudo validar tu identidad con la información enviada.\n\nMotivo: " . $motivo . "\n\nSi crees que es un error, acude al Departamento de Evaluación Profesional.",
            null, null, 'acceso_egresado', null, false);
    }

    /** Fija la contraseña con el enlace de aprobación (vale 7 días y se usa una vez). */
    public static function fijarClave(array $s, string $clave): bool
    {
        if ($s['estado'] !== 'aprobada' || !$s['usuario_id'] || !$s['clave_expira'] || strtotime((string) $s['clave_expira']) < time()) {
            return false;
        }
        AccessControl::internal(function () use ($s, $clave): void {
            Database::ejecutar('UPDATE usuarios SET password_hash = ?, permite_contrasena = 1, logins_contrasena_restantes = 20 WHERE id = ? AND egresado_externo = 1', [password_hash($clave, PASSWORD_DEFAULT), $s['usuario_id']]);
            Database::ejecutar("UPDATE egresado_solicitudes SET estado = 'activa', clave_expira = NULL, token_hash = ? WHERE id = ?", [self::hashToken(bin2hex(random_bytes(20))), $s['id']]);
        });
        Audit::record('egresado_clave_fijada', 'correcto', 'solicitud ' . $s['id']);
        return true;
    }

    public static function desactivar(int $usuarioId, int $personalId): void
    {
        AccessControl::internal(fn() => Database::ejecutar('UPDATE usuarios SET activo = 0 WHERE id = ? AND egresado_externo = 1', [$usuarioId]));
        Audit::record('egresado_desactivado', 'correcto', 'usuario ' . $usuarioId . ' por ' . $personalId);
    }

    /** @return array<string,int> */
    public static function conteos(): array
    {
        $r = array_fill_keys(array_keys(self::ESTADOS), 0);
        foreach (Database::todas('SELECT estado, COUNT(*) n FROM egresado_solicitudes GROUP BY estado') as $f) {
            $r[$f['estado']] = (int) $f['n'];
        }
        return $r;
    }

    public static function pendientes(): int
    {
        try {
            return (int) Database::valor("SELECT COUNT(*) FROM egresado_solicitudes WHERE estado = 'pendiente'");
        } catch (\Throwable) {
            return 0;
        }
    }
}
