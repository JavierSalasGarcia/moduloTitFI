<?php
declare(strict_types=1);

namespace App\Modules\Oficios\Services;

use App\Core\{AccessControl, App, Database};

/**
 * API para archivar oficios desde fuera del navegador (sesión de VS Code, curl, scripts).
 *
 *   POST /api/oficios      multipart/form-data, cabecera «Authorization: Bearer <token>»
 *
 * Campos: tipo, folio, fecha (AAAA-MM-DD), asunto, destinatarios, firmante, notas (opcional) y archivo (PDF).
 * El oficio queda en «Oficios enviados» (/oficios) de la coordinación o departamento de la persona dueña del token.
 * Los tokens se crean con ApiOficios::crearToken() (docs/OFICIOS_API.md); solo se guarda su huella SHA-256.
 */
final class ApiOficios
{
    private const RUTA = '/api/oficios';
    private const ROLES = ['admin', 'titular', 'coordinador', 'operativo'];
    private const TIPOS = ['memorandum', 'oficio', 'circular', 'otro'];

    /** Crea un token para la persona y lo devuelve en claro (única vez). */
    public static function crearToken(int $usuarioId, string $nombre, int $diasVigencia = 365): string
    {
        $token = 'ofi_' . bin2hex(random_bytes(24));
        Database::ejecutar(
            'INSERT INTO oficios_api_tokens (usuario_id, nombre, token_hash, vence_en) VALUES (?,?,?, CURDATE() + INTERVAL ? DAY)',
            [$usuarioId, mb_substr($nombre, 0, 80), hash('sha256', $token), max(1, min(730, $diasVigencia))]
        );
        return $token;
    }

    public static function revocarToken(int $id): void
    {
        Database::ejecutar('UPDATE oficios_api_tokens SET revocado_en = NOW() WHERE id = ? AND revocado_en IS NULL', [$id]);
    }

    /** Atiende la petición si es de esta API; devuelve false si no lo es. */
    public static function dispatch(): bool
    {
        $base = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
        $ruta = '/' . trim(rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)), '/');
        if ($base !== '' && str_starts_with($ruta, $base)) {
            $ruta = '/' . trim(substr($ruta, strlen($base)), '/');
        }
        if ($ruta !== self::RUTA) {
            return false;
        }
        header('Cache-Control: no-store');
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            return self::responder(405, ['error' => 'Usa POST con multipart/form-data.']);
        }
        $subido = null;
        try {
            $u = self::autenticar();
            if (!$u) {
                usleep(400000); // frena la adivinanza de tokens
                header('WWW-Authenticate: Bearer');
                return self::responder(401, ['error' => 'Token ausente, vencido o revocado.']);
            }
            if (!in_array($u['rol'], self::ROLES, true) || (int) $u['departamento_id'] <= 0) {
                self::auditar($u, 'denegado', 'Sin acceso a Oficios');
                return self::responder(403, ['error' => 'Esta cuenta no tiene acceso a Oficios.']);
            }
            $d = self::datos();
            $dept = (int) $u['departamento_id'];
            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new \InvalidArgumentException('Adjunta el PDF del oficio en el campo «archivo».');
            }
            $sha = (string) hash_file('sha256', (string) $_FILES['archivo']['tmp_name']);
            $nombre = mb_substr(basename(str_replace('\\', '/', (string) $_FILES['archivo']['name'])), 0, 200);
            $subido = guardar_archivo('archivo', 'oficios/' . $dept, ['pdf']);
            $id = Database::ejecutar(
                'INSERT INTO oficios_enviados (departamento_id, tipo, folio, fecha, asunto, destinatarios, firmante, notas, archivo, archivo_nombre, sha256, creado_por)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$dept, $d['tipo'], $d['folio'], $d['fecha'], $d['asunto'], $d['destinatarios'], $d['firmante'], $d['notas'] ?: null, $subido, $nombre, $sha, (int) $u['id']]
            );
            self::auditar($u, 'correcto', 'Oficio #' . $id . ' ' . $d['folio']);
            return self::responder(201, ['ok' => true, 'id' => $id, 'folio' => $d['folio'], 'sha256' => $sha,
                'url' => rtrim((string) App::config('app.url_publica', 'https://fingenieria.mx'), '/') . '/oficios/' . $id]);
        } catch (\PDOException $e) {
            self::descartar($subido);
            if (($e->errorInfo[1] ?? 0) === 1062) {
                return self::responder(409, ['error' => 'Ya existe un oficio con ese folio en esta coordinación.']);
            }
            error_log('Oficios API: ' . $e->getMessage());
            return self::responder(500, ['error' => 'No se pudo guardar el oficio.']);
        } catch (\InvalidArgumentException $e) {
            self::descartar($subido);
            return self::responder(422, ['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            self::descartar($subido);
            error_log('Oficios API: ' . $e->getMessage());
            return self::responder(500, ['error' => 'No se pudo guardar el oficio.']);
        }
    }

    /** @return array<string,mixed>|null */
    private static function autenticar(): ?array
    {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer\s+(ofi_[0-9a-f]{48})$/D', trim($h), $m)) {
            return null;
        }
        $u = Database::una(
            'SELECT t.id AS token_id, u.id, u.nombre, u.email, u.rol, u.departamento_id
               FROM oficios_api_tokens t JOIN usuarios u ON u.id = t.usuario_id
              WHERE t.token_hash = ? AND t.revocado_en IS NULL AND t.vence_en >= CURDATE() AND u.activo = 1',
            [hash('sha256', $m[1])]
        );
        if ($u) {
            AccessControl::internal(fn () => Database::ejecutar('UPDATE oficios_api_tokens SET ultimo_uso = NOW() WHERE id = ?', [$u['token_id']]));
        }
        return $u;
    }

    /** @return array<string,string> */
    private static function datos(): array
    {
        $g = static fn (string $k): string => trim((string) ($_POST[$k] ?? ''));
        $d = ['tipo' => $g('tipo') ?: 'oficio', 'folio' => $g('folio'), 'fecha' => $g('fecha'), 'asunto' => $g('asunto'),
              'destinatarios' => $g('destinatarios'), 'firmante' => $g('firmante'), 'notas' => $g('notas')];
        if (!in_array($d['tipo'], self::TIPOS, true)) {
            throw new \InvalidArgumentException('tipo debe ser: ' . implode(', ', self::TIPOS) . '.');
        }
        if ($d['folio'] === '' || mb_strlen($d['folio']) > 60) {
            throw new \InvalidArgumentException('Indica el folio (máximo 60 caracteres).');
        }
        $f = \DateTimeImmutable::createFromFormat('!Y-m-d', $d['fecha']);
        if (!$f || $f->format('Y-m-d') !== $d['fecha']) {
            throw new \InvalidArgumentException('La fecha debe tener el formato AAAA-MM-DD.');
        }
        foreach (['asunto' => 250, 'destinatarios' => 500, 'firmante' => 160, 'notas' => 4000] as $campo => $max) {
            if (mb_strlen($d[$campo]) > $max || (in_array($campo, ['asunto', 'destinatarios'], true) && $d[$campo] === '')) {
                throw new \InvalidArgumentException('Revisa asunto y destinatarios (obligatorios) y la longitud de los campos.');
            }
        }
        return $d;
    }

    private static function descartar(?string $ruta): void
    {
        if ($ruta) {
            $f = rtrim((string) App::config('archivos')['storage'], '/\\') . '/' . $ruta;
            if (is_file($f)) {
                unlink($f);
            }
        }
    }

    /** @param array<string,mixed> $u */
    private static function auditar(array $u, string $resultado, string $detalle): void
    {
        try {
            AccessControl::internal(fn () => Database::ejecutar(
                'INSERT INTO auditoria_acciones (actor_id,actor_email,usuario_id,usuario_email,accion,resultado,detalle,creado_en) VALUES (?,?,?,?,?,?,?,NOW())',
                [$u['id'], $u['email'], $u['id'], $u['email'], 'oficios_api_archivar', $resultado, mb_substr($detalle, 0, 500)]
            ));
        } catch (\Throwable) {
        }
    }

    private static function responder(int $codigo, array $cuerpo): bool
    {
        http_response_code($codigo);
        echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return true;
    }
}
