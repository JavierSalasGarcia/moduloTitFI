<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\View;

/** Utilidades compartidas por los controladores del módulo. */
abstract class PosgradoBase extends Controller
{
    public function __construct(string $modulo)
    {
        parent::__construct($modulo);
        // Módulo aún no publicado: todas sus rutas muestran el aviso (la administración sí entra, para probarlo).
        if (!PosgradoAccess::publicado()) {
            $this->privado();
            if (!Auth::usuario()) {
                // Sin sesión (p. ej. un enlace de firma) el módulo no publicado simplemente no existe.
                $this->noEncontrado();
            }
            $this->vista('proximamente', ['titulo' => 'Posgrado · Obtención de grado']);
            exit;
        }
    }

    protected function privado(): void
    {
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
    }

    protected function noEncontrado(): never
    {
        http_response_code(404);
        View::render('errores/404', ['titulo' => 'Página no disponible'], Auth::usuario() ? 'app' : 'auth');
        exit;
    }

    protected function prohibido(string $detalle = 'Permisos insuficientes'): never
    {
        Audit::record('posgrado::acceso', 'denegado', $detalle);
        http_response_code(403);
        View::render('errores/403', ['titulo' => 'Acceso restringido']);
        exit;
    }

    /** Ver: personal de Apoyo a Grado o supervisión. Cambiar (POST): solo el personal que opera. */
    protected function soloPersonal(): void
    {
        if (!PosgradoAccess::esPersonal()) {
            $this->prohibido('Gestión de evaluación de grado');
        }
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' && !PosgradoAccess::esPersonalOperativo()) {
            $this->prohibido('Supervisión de evaluación de grado: solo consulta');
        }
    }

    /** Carga un trámite al que el usuario tiene acceso; si no, responde 404 (no revela su existencia). */
    protected function tramite(string $id, bool $soloPersonal = false): array
    {
        if ($soloPersonal) {
            $this->soloPersonal();
        }
        $t = ctype_digit($id) && strlen($id) < 19 ? PosgradoRepository::obtener((int) $id) : null;
        if ($t === null || !PosgradoAccess::puedeVer($t)) {
            $this->noEncontrado();
        }
        return $t;
    }

    /** @return array{0:array,1:array} [evaluación, documentos vigentes] */
    protected function evaluar(array $t): array
    {
        $docs = PosgradoRepository::documentosVigentes((int) $t['id']);
        $ev = Flujo::evaluar($t, $docs, PosgradoRepository::hitos((int) $t['id']), PosgradoRepository::generadosVigentes((int) $t['id']), Services\Firmas::estado((int) $t['id']));
        return [$ev, $docs];
    }

    /** Recalcula el momento, el estado y el resultado del trámite después de cualquier cambio. */
    protected function sincronizar(int $id): array
    {
        $t = PosgradoRepository::obtener($id);
        if ($t !== null && !in_array($t['estado'], ['cancelado', 'abandonado'], true)) {   // en pausa: el avance queda congelado
            [$ev] = $this->evaluar($t);
            $resultado = $ev['hitos']['resultado']['datos']['resultado'] ?? (!empty($t['datos']['aplazamientos']) ? 'aplazado' : null);
            PosgradoRepository::actualizarAvance($id, Flujo::momentoGuardado($ev), $ev['completo'] ? 'concluido' : 'abierto', $resultado);
            $t = PosgradoRepository::obtener($id);
        }
        return $t ?? [];
    }

    /** Envía un archivo de storage/ verificando que no salga de la carpeta de almacenamiento. */
    protected function enviarArchivo(string $rutaRelativa, string $nombre, bool $enLinea): never
    {
        $base = realpath((string) App::config('archivos.storage'));
        $ruta = $base === false ? false : realpath($base . '/' . $rutaRelativa);
        if ($base === false || $ruta === false || !is_file($ruta) || !str_starts_with($ruta, rtrim($base, '/\\') . DIRECTORY_SEPARATOR)) {
            $this->noEncontrado();
        }
        $this->privado();
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'documento.pdf';
        header('Content-Type: ' . ($enLinea ? 'application/pdf' : 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment') . '; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    protected function enviarBytes(string $bytes, string $nombre, string $mime = 'application/pdf', bool $enLinea = false): never
    {
        $this->privado();
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'formato.pdf';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment') . '; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    /** Texto de una línea: sin saltos ni espacios repetidos, recortado a $max caracteres. */
    protected static function linea(mixed $v, int $max): string
    {
        $v = is_string($v) ? trim((string) preg_replace('/\s+/u', ' ', $v)) : '';
        return mb_substr($v, 0, $max);
    }

    protected static function fechaValida(string $v): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d !== false && $d->format('Y-m-d') === $v;
    }

    protected static function horaValida(string $v): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/D', $v);
    }
}
