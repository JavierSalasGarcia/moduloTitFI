<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\View;

/** Utilidades compartidas por los controladores del módulo. */
abstract class TitulacionBase extends Controller
{
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
        View::render('errores/404', ['titulo' => 'Trámite no disponible'], Auth::usuario() ? 'app' : 'auth');
        exit;
    }

    protected function prohibido(string $detalle = 'Permisos insuficientes'): never
    {
        Audit::record('titulacion::acceso', 'denegado', $detalle);
        http_response_code(403);
        View::render('errores/403', ['titulo' => 'Acceso restringido']);
        exit;
    }

    /** Carga un trámite al que el usuario tiene acceso; si no, responde 404 (no revela su existencia). */
    protected function tramite(string $id, bool $soloPersonal = false): array
    {
        $t = ctype_digit($id) ? TitulacionRepository::obtener((int) $id) : null;
        if ($t === null || !TitulacionAccess::puedeVer($t)) {
            $this->noEncontrado();
        }
        if ($soloPersonal && !TitulacionAccess::esPersonal()) {
            $this->prohibido('Gestión de titulación');
        }
        return $t;
    }

    /** @return array{0:array,1:array} [flujo, documentos vigentes] */
    protected function evaluar(array $t): array
    {
        $docs = TitulacionRepository::documentosVigentes((int) $t['id']);
        $hitos = TitulacionRepository::hitos((int) $t['id']);
        $flujo = Flujo::evaluar($t, $docs, $hitos);
        $flujo['hitos'] = $hitos;
        return [$flujo, $docs];
    }

    /** Recalcula la etapa actual y el estado del trámite después de cualquier cambio. */
    protected function sincronizar(int $id): array
    {
        $t = TitulacionRepository::obtener($id);
        [$flujo] = $this->evaluar($t);
        if ($t['estado'] !== 'cancelado') {
            $etapas = $flujo['etapas'];
            $ultima = $etapas ? $etapas[array_key_last($etapas)]['clave'] : 'datos';
            $actual = $flujo['actual'] ?? $ultima;
            $estado = $flujo['completo'] ? 'concluido' : 'abierto';
            $resultado = $flujo['completo'] ? ($flujo['hitos']['resultado']['datos']['resultado'] ?? null) : null;
            TitulacionRepository::actualizarAvance($id, $actual, $estado, $resultado);
        }
        return TitulacionRepository::obtener($id);
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

    protected function enviarBytes(string $bytes, string $nombre, string $mime): never
    {
        $this->privado();
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', $nombre) ?: 'formato';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($bytes));
        echo $bytes;
        exit;
    }

    /**
     * Añade semáforo y días hábiles restantes a cada asignación.
     *
     * @param array<int,array<string,mixed>> $asigs
     */
    protected function decorarAsignaciones(array $asigs): array
    {
        $hoy = new \DateTimeImmutable('today');
        foreach ($asigs as &$a) {
            $a['semaforo'] = Services\Calendario::semaforo((string) $a['estado'], (string) $a['fecha_limite'], $hoy);
            $a['restan'] = Services\Calendario::restantes($hoy, new \DateTimeImmutable((string) $a['fecha_limite']));
        }
        return $asigs;
    }
}
