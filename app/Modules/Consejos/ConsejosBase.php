<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/** Utilidades comunes de los controladores de Consejos. */
abstract class ConsejosBase extends Controller
{
    public const MAX_PDF = 40 * 1024 * 1024;

    protected function privado(): void
    {
        header('Cache-Control: no-store, private, max-age=0');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
    }

    protected function noEncontrado(): never
    {
        http_response_code(404);
        View::render('errores/404', ['titulo' => 'Página no disponible'], Auth::usuario() ? 'app' : 'auth');
        exit;
    }

    /** Un caso queda cerrado cuando se sube el acta escaneada, firmada y sellada, de cualquiera de los dos Consejos. Quitar el escaneado lo reabre. */
    public static function cerrada(int $reunionId): bool
    {
        return (bool) Database::valor("SELECT 1 FROM consejos_archivos WHERE reunion_id = ? AND tipo IN ('acta_academico', 'acta_gobierno') LIMIT 1", [$reunionId]);
    }

    /** Corta la operación si la reunión ya tiene acta firmada: lo firmado no se edita. */
    protected function noCerrada(int $reunionId): void
    {
        if (self::cerrada($reunionId)) {
            flash('error', 'Esta reunión ya tiene el acta firmada y sellada: sus casos quedaron cerrados y no se pueden modificar. Para corregir algo, primero retira el acta escaneada.');
            redirigir('consejos/reunion/' . $reunionId);
        }
    }

    protected function gestor(): void
    {
        $this->privado();
        if (!ConsejosAccess::esGestor()) {
            Audit::record('consejos::acceso', 'denegado', 'Gestión de Consejos');
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }

    protected static function id(string $id): ?int
    {
        return ctype_digit($id) && strlen($id) < 10 ? (int) $id : null;
    }

    protected function reunion(string $id): array
    {
        $n = self::id($id);
        $r = $n ? Database::una('SELECT * FROM consejos_reuniones WHERE id = ?', [$n]) : null;
        return $r ?: $this->noEncontrado();
    }

    protected function asunto(string $id): array
    {
        $n = self::id($id);
        $a = $n ? Database::una('SELECT * FROM consejos_asuntos WHERE id = ?', [$n]) : null;
        return $a ?: $this->noEncontrado();
    }

    protected function json(array $datos, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Guarda un PDF subido en storage/consejos/<reunión>/. @return array{0:?string,1:int,2:string} [ruta relativa, bytes, mensaje de error] */
    protected function guardarPdf(array $f, int $reunionId): array
    {
        if (($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
            return [null, 0, 'No llegó el archivo (revisa que pese menos de ' . (int) (self::MAX_PDF / 1048576) . ' MB).'];
        }
        if ($f['size'] > self::MAX_PDF) {
            return [null, 0, 'El PDF pesa más de ' . (int) (self::MAX_PDF / 1048576) . ' MB.'];
        }
        if ((string) file_get_contents($f['tmp_name'], false, null, 0, 5) !== '%PDF-') {
            return [null, 0, 'El archivo debe ser un PDF (el escaneado de la hoja firmada y sellada).'];
        }
        $rel = 'consejos/' . $reunionId . '/' . bin2hex(random_bytes(10)) . '.pdf';
        $dest = rtrim((string) App::config('archivos.storage'), '/\\') . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0750, true);
        }
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            return [null, 0, 'No fue posible guardar el archivo.'];
        }
        return [$rel, (int) $f['size'], ''];
    }

    /** Ruta absoluta dentro de storage/ o null si el archivo no existe o se sale de ella. */
    public static function rutaAbsoluta(string $rel): ?string
    {
        $base = realpath((string) App::config('archivos.storage'));
        $ruta = $base !== false ? realpath($base . '/' . $rel) : false;
        if ($base === false || $ruta === false || !is_file($ruta) || !str_starts_with($ruta, rtrim($base, '/\\') . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $ruta;
    }

    protected function enviarPdf(string $absoluta, string $nombre, bool $enLinea = true): never
    {
        $this->privado();
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: ' . ($enLinea ? 'inline' : 'attachment') . '; filename="' . (preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'archivo.pdf') . '"');
        header('Content-Length: ' . filesize($absoluta));
        readfile($absoluta);
        exit;
    }

    protected function enviarDocx(string $binario, string $nombre): never
    {
        $this->privado();
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . (preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'documento.docx') . '"');
        header('Content-Length: ' . strlen($binario));
        echo $binario;
        exit;
    }
}
