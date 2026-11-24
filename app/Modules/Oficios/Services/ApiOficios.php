<?php
declare(strict_types=1);

namespace App\Modules\Oficios\Services;

use App\Core\{AccessControl, Acceso, App, Database};
use App\Modules\Titulacion\Services\SubdireccionFirma;
use RuntimeException;

/**
 * API con token para redactar oficios de la Subdirección Académica desde fuera del navegador (sesión de VS Code, curl).
 * Docs: docs/OFICIOS_API.md. Se atiende en App::run antes del router: sin sesión ni CSRF, solo el token.
 *
 *   POST /api/oficios/subdireccion               crea el borrador (y con enviar=1 lo manda a firma con número de la serie única)
 *   POST /api/oficios/subdireccion/{id}/adjuntos agrega anexos a un borrador o a un oficio en firma
 *
 * La API nunca firma: la firma es del titular, con su e.firma, en /firmas → «Por firmar». Los anexos se ven en la ficha del oficio
 * y no se unen al PDF firmado. Tokens: oficios_api_tokens (solo su huella SHA-256); se crean con ApiOficios::crearToken().
 */
final class ApiOficios
{
    private const RUTA = '#^/api/oficios/subdireccion(?:/(\d+)/adjuntos)?$#D';
    private const EXT_ADJUNTOS = ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'pptx', 'zip'];
    private const MAX_ADJUNTOS = 10;

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

    /** Misma regla que OficiosAccess::puedeRedactar, pero para una persona identificada por token (sin sesión). */
    public static function puedeRedactar(int $usuarioId, string $email): bool
    {
        $email = strtolower(trim($email));
        if ($usuarioId <= 0 || $email === '') {
            return false;
        }
        try {
            return $email === strtolower(SubdireccionFirma::correoSubdirector())
                || Acceso::tiene($usuarioId, 'consejos.gestor')
                || (bool) Database::valor('SELECT 1 FROM tit_asistentes WHERE email = ? AND activo = 1', [$email]);
        } catch (\Throwable) {
            return false;
        }
    }

    /** Atiende la petición si es de esta API; devuelve false si no lo es. */
    public static function dispatch(): bool
    {
        $base = rtrim(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')), '/\\');
        $ruta = '/' . trim(rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)), '/');
        if ($base !== '' && str_starts_with($ruta, $base)) {
            $ruta = '/' . trim(substr($ruta, strlen($base)), '/');
        }
        if (!preg_match(self::RUTA, $ruta, $m)) {
            return false;
        }
        header('Cache-Control: no-store');
        header('Content-Type: application/json; charset=utf-8');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            header('Allow: POST');
            return self::responder(405, ['error' => 'Usa POST con multipart/form-data.']);
        }
        $guardados = [];
        try {
            $u = self::autenticar();
            if (!$u) {
                usleep(400000); // frena la adivinanza de tokens
                header('WWW-Authenticate: Bearer');
                return self::responder(401, ['error' => 'Token ausente, vencido o revocado.']);
            }
            if (!self::puedeRedactar((int) $u['id'], (string) $u['email'])) {
                self::auditar($u, 'denegado', 'Sin permiso para redactar oficios de la Subdirección');
                return self::responder(403, ['error' => 'Esta cuenta no puede redactar oficios de la Subdirección Académica.']);
            }
            return isset($m[1]) ? self::soloAdjuntos((int) $u['id'], $u, (int) $m[1], $guardados) : self::crear($u, $guardados);
        } catch (RuntimeException | \InvalidArgumentException $e) {
            self::descartar($guardados);
            return self::responder(422, ['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            self::descartar($guardados);
            error_log('Oficios API: ' . $e->getMessage());
            return self::responder(500, ['error' => 'No se pudo completar la operación.']);
        }
    }

    /** @param array<string,mixed> $u @param array<int,string> $guardados */
    private static function crear(array $u, array &$guardados): bool
    {
        $asunto = trim((string) ($_POST['asunto'] ?? ''));
        $cuerpo = self::cuerpo();
        $ids = self::destinatarios((int) $u['id']);
        $enviar = in_array(strtolower(trim((string) ($_POST['enviar'] ?? ''))), ['1', 'true', 'si', 'sí'], true);

        // Valida y guarda los anexos antes de crear nada: si algo falla, no queda un borrador a medias.
        $archivos = self::archivosSubidos();
        foreach ($archivos as $f) {
            $guardados[] = self::guardarAdjunto($f);
        }
        $r = OficiosSub::comoActor((int) $u['id'], function () use ($asunto, $cuerpo, $ids, $enviar, $archivos, &$guardados, $u): array {
            $id = OficiosSub::guardar(null, $asunto, $cuerpo, $ids);
            foreach ($archivos as $i => $f) {
                self::registrarAdjunto($id, $f, $guardados[$i], (int) $u['id']);
            }
            $guardados = [];   // ya están ligados al borrador: no se borran si falla el envío a firma
            return $enviar ? OficiosSub::enviarAFirma($id) : (OficiosSub::obtener($id) ?? []);
        });
        self::auditar($u, 'correcto', 'Oficio #' . $r['id'] . ($r['numero_texto'] ? ' · ' . $r['numero_texto'] : ' (borrador)'));
        return self::responder(201, self::ficha($r));
    }

    /** @param array<string,mixed> $u @param array<int,string> $guardados */
    private static function soloAdjuntos(int $usuarioId, array $u, int $id, array &$guardados): bool
    {
        $o = OficiosSub::obtener($id);
        if (!$o) {
            return self::responder(404, ['error' => 'Oficio no encontrado.']);
        }
        if (!in_array($o['estado'], ['borrador', 'en_firma'], true)) {
            throw new RuntimeException('A un oficio firmado o cancelado ya no se le agregan anexos.');
        }
        $archivos = self::archivosSubidos();
        if (!$archivos) {
            throw new RuntimeException('Adjunta al menos un archivo en «adjuntos[]».');
        }
        $actuales = (int) Database::valor('SELECT COUNT(*) FROM ofs_adjuntos WHERE oficio_id = ?', [$id]);
        if ($actuales + count($archivos) > 30) {
            throw new RuntimeException('Un oficio admite hasta 30 anexos.');
        }
        foreach ($archivos as $f) {
            $guardados[] = self::guardarAdjunto($f);
        }
        OficiosSub::comoActor($usuarioId, function () use ($id, $archivos, $guardados, $usuarioId): void {
            foreach ($archivos as $i => $f) {
                self::registrarAdjunto($id, $f, $guardados[$i], $usuarioId);
            }
        });
        $guardados = [];
        self::auditar($u, 'correcto', 'Agrega ' . count($archivos) . ' anexo(s) al oficio #' . $id);
        return self::responder(201, self::ficha(OficiosSub::obtener($id) ?? []));
    }

    /** @param array<string,mixed> $o */
    private static function ficha(array $o): array
    {
        $id = (int) ($o['id'] ?? 0);
        $adj = Database::todas('SELECT id, nombre, bytes, sha256 FROM ofs_adjuntos WHERE oficio_id = ? ORDER BY id', [$id]);
        return ['ok' => true, 'id' => $id, 'estado' => $o['estado'] ?? '', 'numero' => $o['numero_texto'] ?? null,
            'adjuntos' => array_map(static fn (array $a): array => ['id' => (int) $a['id'], 'nombre' => $a['nombre'], 'bytes' => (int) $a['bytes'], 'sha256' => $a['sha256']], $adj),
            'url' => rtrim((string) App::config('app.url_publica', 'https://fingenieria.mx'), '/') . '/oficios/subdireccion/' . $id];
    }

    // ------------------------------------------------------------------ entradas

    private static function cuerpo(): string
    {
        $html = trim((string) ($_POST['cuerpo_html'] ?? ''));
        if ($html !== '') {
            return $html;
        }
        $texto = trim(str_replace("\r\n", "\n", (string) ($_POST['cuerpo'] ?? '')));
        if ($texto === '') {
            return '';
        }
        $parrafos = preg_split('/\n{2,}/', $texto) ?: [];
        return implode('', array_map(static fn (string $p): string => '<p>' . nl2br(htmlspecialchars(trim($p), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</p>', $parrafos));
    }

    /**
     * «destinatarios» es un JSON: lista de ids existentes (de GET /oficios/subdireccion/destinatarios) o de objetos
     * {nombre, cargo, dependencia} que se crean o reutilizan en el catálogo.
     * @return array<int,int>
     */
    private static function destinatarios(int $usuarioId): array
    {
        $lista = json_decode((string) ($_POST['destinatarios'] ?? ''), true);
        if (!is_array($lista) || !$lista) {
            throw new RuntimeException('«destinatarios» debe ser un JSON con al menos un destinatario: [{"nombre":"Dra. Ana López","cargo":"…","dependencia":"…"}] o ids.');
        }
        $ids = [];
        OficiosSub::comoActor($usuarioId, function () use ($lista, &$ids): void {
            foreach (array_slice($lista, 0, 40) as $d) {
                if (is_int($d) || (is_string($d) && ctype_digit($d))) {
                    $ids[] = (int) $d;
                } elseif (is_array($d)) {
                    $ids[] = Destinatarios::crear((string) ($d['nombre'] ?? ''), (string) ($d['cargo'] ?? ''), (string) ($d['dependencia'] ?? ''))['id'];
                }
            }
        });
        return $ids;
    }

    /** @return array<int,array<string,mixed>> */
    private static function archivosSubidos(): array
    {
        $r = [];
        $l = $_FILES['adjuntos'] ?? null;
        if (!$l) {
            return [];
        }
        if (!is_array($l['name'])) {
            $l = array_map(static fn ($v) => [$v], $l);
        }
        foreach ($l['name'] as $i => $nombre) {
            if (($l['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $r[] = ['name' => $nombre, 'type' => $l['type'][$i], 'tmp_name' => $l['tmp_name'][$i], 'error' => $l['error'][$i], 'size' => $l['size'][$i]];
        }
        if (count($r) > self::MAX_ADJUNTOS) {
            throw new RuntimeException('Máximo ' . self::MAX_ADJUNTOS . ' anexos por petición.');
        }
        return $r;
    }

    /** @param array<string,mixed> $f */
    private static function guardarAdjunto(array $f): string
    {
        return guardar_archivo_datos($f, 'oficios_sub/adjuntos', self::EXT_ADJUNTOS);
    }

    /** @param array<string,mixed> $f */
    private static function registrarAdjunto(int $oficioId, array $f, string $ruta, int $usuarioId): void
    {
        $abs = rtrim((string) App::config('archivos')['storage'], '/\\') . '/' . $ruta;
        $nombre = mb_substr(basename(str_replace('\\', '/', (string) $f['name'])), 0, 200);
        Database::ejecutar(
            'INSERT INTO ofs_adjuntos (oficio_id, nombre, ruta, sha256, bytes, subido_por) VALUES (?,?,?,?,?,?)',
            [$oficioId, $nombre, $ruta, (string) hash_file('sha256', $abs), (int) filesize($abs), $usuarioId]
        );
        OficiosSub::anotar($oficioId, 'anexo', $nombre);
    }

    private static function descartar(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            $f = rtrim((string) App::config('archivos')['storage'], '/\\') . '/' . $ruta;
            if (is_file($f)) {
                unlink($f);
            }
        }
    }

    // ------------------------------------------------------------------ token, auditoría, respuesta

    /** @return array<string,mixed>|null */
    private static function autenticar(): ?array
    {
        $h = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (!preg_match('/^Bearer\s+(ofi_[0-9a-f]{48})$/D', trim($h), $m)) {
            return null;
        }
        $u = Database::una(
            'SELECT t.id AS token_id, u.id, u.nombre, u.email
               FROM oficios_api_tokens t JOIN usuarios u ON u.id = t.usuario_id
              WHERE t.token_hash = ? AND t.revocado_en IS NULL AND t.vence_en >= CURDATE() AND u.activo = 1',
            [hash('sha256', $m[1])]
        );
        if ($u) {
            AccessControl::internal(fn () => Database::ejecutar('UPDATE oficios_api_tokens SET ultimo_uso = NOW() WHERE id = ?', [$u['token_id']]));
        }
        return $u;
    }

    /** @param array<string,mixed> $u */
    private static function auditar(array $u, string $resultado, string $detalle): void
    {
        try {
            AccessControl::internal(fn () => Database::ejecutar(
                'INSERT INTO auditoria_acciones (actor_id,actor_email,usuario_id,usuario_email,accion,resultado,detalle,creado_en) VALUES (?,?,?,?,?,?,?,NOW())',
                [$u['id'], $u['email'], $u['id'], $u['email'], 'oficios_api_subdireccion', $resultado, mb_substr($detalle, 0, 500)]
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
