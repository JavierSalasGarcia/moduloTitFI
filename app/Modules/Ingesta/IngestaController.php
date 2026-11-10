<?php
declare(strict_types=1);

namespace App\Modules\Ingesta;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\DocumentArchive;
use App\Core\GrafoPersona;

/** Ingesta y consulta de documentos, y grafo de relaciones de la persona (docs/INGESTA_DOCUMENTAL.md, docs/GRAFO_DE_PERSONA.md). */
final class IngestaController extends Controller
{
    private function lista(): void
    {
        if (!Ingesta::lista()) {
            $this->vista('index', ['titulo' => 'Documentos indizados', 'migrada' => false]);
            exit;
        }
    }

    private function exigirIngesta(): void
    {
        if (!Ingesta::puedeIngresar() || isset($_SESSION['impersonador'])) {
            Audit::record('ingesta_acceso', 'denegado');
            http_response_code(403);
            exit('Tu cuenta no puede ingresar documentos. Solicita el acceso a la administración o al titular de tu dependencia.');
        }
    }

    private function negar(): never
    {
        http_response_code(404);
        exit('Documento no disponible.');
    }

    private function departamentos(): array
    {
        $todos = Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre');
        if (Auth::tieneRol('admin')) {
            return $todos;
        }
        $mias = Ingesta::dependencias();
        return array_values(array_filter($todos, static fn(array $d): bool => in_array((int) $d['id'], $mias, true)));
    }

    private function meta(): array
    {
        return ['titulo' => $this->post('titulo'), 'tipo_documento' => $this->post('tipo_documento'), 'autor' => $this->post('autor'),
            'fecha_documento' => $this->post('fecha_documento'), 'palabras_clave' => $this->post('palabras_clave'), 'resumen' => $this->post('resumen')];
    }

    // ------------------------------------------------------------------ consulta

    public function index(): void
    {
        $this->lista();
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 160);
        $pagina = max(1, (int) ($_GET['pagina'] ?? 1));
        $filas = Ingesta::buscar($q, null, 21, ($pagina - 1) * 20);
        $this->vista('index', [
            'titulo' => 'Documentos indizados', 'migrada' => true, 'q' => $q, 'pagina' => $pagina, 'hayMas' => count($filas) > 20,
            'documentos' => array_slice($filas, 0, 20), 'carpetas' => $q === '' && $pagina === 1 ? Ingesta::carpetas() : [], 'puedeIngresar' => Ingesta::puedeIngresar(),
        ]);
    }

    public function carpeta(string $id): void
    {
        $this->lista();
        $c = Ingesta::carpeta((int) $id) ?? $this->negar();
        $this->vista('carpeta', ['titulo' => $c['nombre'], 'carpeta' => $c, 'documentos' => Ingesta::buscar(mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 160), (int) $id, 200),
            'q' => (string) ($_GET['q'] ?? ''), 'esDueno' => Ingesta::esDueno($c), 'departamentos' => $this->departamentos()]);
    }

    public function documento(string $id): void
    {
        $this->lista();
        $d = Ingesta::documento((int) $id) ?? $this->negar();
        $this->vista('documento', ['titulo' => $d['titulo'], 'd' => $d, 'esDueno' => Ingesta::esDueno($d)]);
    }

    public function archivo(string $id): void
    {
        $this->lista();
        $d = Ingesta::documento((int) $id) ?? $this->negar();
        if ($d['origen'] !== 'pdf' || empty($d['clave']) || !is_file($ruta = DocumentArchive::path((string) $d['clave']))) {
            $this->negar();
        }
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Content-Type: application/pdf');
        header('Content-Disposition: ' . (($_GET['vista'] ?? '') === '1' ? 'inline' : 'attachment') . '; filename="documento-' . (int) $d['id'] . '.pdf"');
        header('Content-Length: ' . filesize($ruta));
        Audit::record('ingesta_descargar', 'correcto', 'Documento #' . (int) $d['id']);
        readfile($ruta);
    }

    // ------------------------------------------------------------------ ingesta

    public function nueva(): void
    {
        $this->lista();
        $this->exigirIngesta();
        $this->vista('nueva', ['titulo' => 'Ingresar documentos', 'departamentos' => $this->departamentos(),
            'maxMb' => (int) min(Ingesta::MAX_BYTES / 1048576, self::megas((string) ini_get('upload_max_filesize')), self::megas((string) ini_get('post_max_size')))]);
    }

    /** PDF particular o enlace particular de Teams. */
    public function crearDocumento(): void
    {
        $this->lista();
        $this->exigirIngesta();
        $json = ($_POST['formato'] ?? '') === 'json';
        try {
            [$alcance, $depto] = Ingesta::destino($this->post('alcance'), (int) $this->post('departamento_id'));
            if ($this->post('origen') === 'teams') {
                $r = Ingesta::ingresarEnlaces(($this->post('titulo') !== '' ? $this->post('titulo') . ' | ' : '') . $this->post('enlace'), null, $alcance, $depto, $this->meta());
                if ($r['nuevos'] !== 1) {
                    throw new \InvalidArgumentException($r['rechazados'] ? 'El enlace debe ser https y de Teams, SharePoint u OneDrive.' : 'Ese enlace ya estaba registrado.');
                }
                Audit::record('ingesta_enlace', 'correcto');
                flash('exito', 'Enlace registrado e indizado.');
                if ($json) {
                    $this->json(['ok' => true, 'url' => url('ingesta')]);
                }
                redirigir('ingesta');
            }
            $r = Ingesta::ingresarPdf($this->subido('archivo'), $this->meta(), null, $alcance, $depto);
            Audit::record('ingesta_pdf', 'correcto', 'Documento #' . $r['id']);
            flash('exito', $r['duplicado'] ? 'Ese PDF ya lo habías ingresado; se abre el registro existente.' : 'PDF indizado. Revisa y completa sus metadatos.');
            if ($json) {
                $this->json(['ok' => true, 'url' => url('ingesta/documento/' . $r['id'])]);
            }
            redirigir('ingesta/documento/' . $r['id']);
        } catch (\InvalidArgumentException $e) {
            if ($json) {
                $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
            redirigir('ingesta/nueva');
        }
    }

    /** Carpeta de PDFs (los archivos llegan después, uno por petición) o carpeta de Teams con sus enlaces. */
    public function crearCarpeta(): void
    {
        $this->lista();
        $this->exigirIngesta();
        $json = ($_POST['formato'] ?? '') === 'json';
        try {
            [$alcance, $depto] = Ingesta::destino($this->post('alcance'), (int) $this->post('departamento_id'));
            $teams = $this->post('origen') === 'teams';
            $enlace = null;
            if ($teams && $this->post('enlace_carpeta') !== '') {
                $enlace = Ingesta::enlaceValido($this->post('enlace_carpeta')) ?? throw new \InvalidArgumentException('El enlace de la carpeta debe ser https y de Teams, SharePoint u OneDrive.');
            }
            $id = Ingesta::crearCarpeta($this->post('nombre'), $this->post('descripcion'), $teams ? 'teams' : 'pdf', $enlace, $alcance, $depto);
            $mensaje = 'Carpeta creada.';
            if ($teams) {
                $r = Ingesta::ingresarEnlaces((string) ($_POST['enlaces'] ?? ''), ['id' => $id], $alcance, $depto, ['tipo_documento' => $this->post('tipo_documento'), 'palabras_clave' => $this->post('palabras_clave')]);
                $mensaje = 'Carpeta creada con ' . $r['nuevos'] . ' enlace(s).' . ($r['rechazados'] ? ' No se aceptaron ' . count($r['rechazados']) . ' línea(s) por no ser enlaces https de Teams, SharePoint u OneDrive.' : '');
            }
            Audit::record('ingesta_carpeta', 'correcto', 'Carpeta #' . $id);
            if ($json) {
                $this->json(['ok' => true, 'id' => $id, 'url' => url('ingesta/carpeta/' . $id)]);
            }
            flash('exito', $mensaje);
            redirigir('ingesta/carpeta/' . $id);
        } catch (\InvalidArgumentException $e) {
            if ($json) {
                $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
            }
            flash('error', $e->getMessage());
            redirigir('ingesta/nueva');
        }
    }

    /** Un PDF de la carpeta por petición: la subida por carpetas no depende de los límites de PHP por envío. */
    public function agregarArchivo(string $id): void
    {
        $this->lista();
        $this->exigirIngesta();
        $c = Ingesta::carpeta((int) $id);
        if (!$c || !Ingesta::esDueno($c) || $c['origen'] !== 'pdf') {
            $this->json(['ok' => false, 'error' => 'Carpeta no disponible.'], 404);
        }
        try {
            $r = Ingesta::ingresarPdf($this->subido('archivo'), ['ruta_relativa' => dirname(str_replace('\\', '/', $this->post('ruta'))) === '.' ? '' : dirname(str_replace('\\', '/', $this->post('ruta')))],
                $c, (string) $c['alcance'], $c['departamento_id'] !== null ? (int) $c['departamento_id'] : null);
            $this->json(['ok' => true, 'duplicado' => $r['duplicado']]);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function agregarEnlaces(string $id): void
    {
        $this->lista();
        $this->exigirIngesta();
        $c = Ingesta::carpeta((int) $id);
        if (!$c || !Ingesta::esDueno($c) || $c['origen'] !== 'teams') {
            $this->negar();
        }
        $r = Ingesta::ingresarEnlaces((string) ($_POST['enlaces'] ?? ''), $c, (string) $c['alcance'], $c['departamento_id'] !== null ? (int) $c['departamento_id'] : null);
        flash($r['nuevos'] ? 'exito' : 'error', $r['nuevos'] . ' enlace(s) agregados.' . ($r['rechazados'] ? ' ' . count($r['rechazados']) . ' línea(s) no son enlaces https de Teams, SharePoint u OneDrive.' : ''));
        redirigir('ingesta/carpeta/' . (int) $id);
    }

    public function guardarDocumento(string $id): void
    {
        $this->lista();
        $d = Ingesta::documento((int) $id);
        if (!$d || !Ingesta::esDueno($d)) {
            $this->negar();
        }
        $titulo = mb_substr($this->post('titulo'), 0, 250);
        if ($titulo === '') {
            flash('error', 'El título no puede quedar vacío.');
            redirigir('ingesta/documento/' . (int) $id);
        }
        Database::ejecutar(
            'UPDATE ing_documentos SET titulo = ?, tipo_documento = ?, autor = ?, fecha_documento = ?, palabras_clave = ?, resumen = ?, actualizado_en = ? WHERE id = ?',
            [$titulo, mb_substr($this->post('tipo_documento'), 0, 100), mb_substr($this->post('autor'), 0, 200), Ingesta::fecha($this->post('fecha_documento')),
                mb_substr($this->post('palabras_clave'), 0, 2000), mb_substr($this->post('resumen'), 0, 4000), date('Y-m-d H:i:s'), (int) $id]
        );
        Audit::record('ingesta_editar', 'correcto', 'Documento #' . (int) $id);
        flash('exito', 'Metadatos guardados.');
        redirigir('ingesta/documento/' . (int) $id);
    }

    public function eliminarDocumento(string $id): void
    {
        $this->lista();
        $d = Ingesta::documento((int) $id);
        if (!$d || !Ingesta::esDueno($d)) {
            $this->negar();
        }
        Ingesta::eliminarDocumento($d);
        Audit::record('ingesta_eliminar', 'correcto', 'Documento #' . (int) $id);
        flash('exito', 'Documento eliminado del índice.');
        redirigir($d['carpeta_id'] ? 'ingesta/carpeta/' . (int) $d['carpeta_id'] : 'ingesta');
    }

    public function eliminarCarpeta(string $id): void
    {
        $this->lista();
        $c = Ingesta::carpeta((int) $id);
        if (!$c || !Ingesta::esDueno($c)) {
            $this->negar();
        }
        Ingesta::eliminarCarpeta($c);
        Audit::record('ingesta_eliminar', 'correcto', 'Carpeta #' . (int) $id);
        flash('exito', 'Carpeta y sus documentos eliminados del índice.');
        redirigir('ingesta');
    }

    // ------------------------------------------------------------------ grafo de la persona

    public function grafo(string $id = ''): void
    {
        $objetivo = $id === '' ? Auth::id() : (int) $id;
        if (!GrafoPersona::puedeVer($objetivo)) {
            Audit::record('grafo_persona', 'denegado', 'Persona #' . $objetivo);
            $this->negar();
        }
        $grafo = GrafoPersona::de($objetivo) ?? $this->negar();
        if ($objetivo !== Auth::id()) {
            Audit::record('grafo_persona', 'correcto', 'Persona #' . $objetivo);
        }
        // Buscador: la administración busca entre todas las cuentas; quien coordina, solo entre las personas a su cargo.
        $buscar = GrafoPersona::puedeBuscar();
        $todas = GrafoPersona::idsVisibles() === null;
        $texto = (string) ($_GET['persona'] ?? '');
        $this->vista('grafo', ['titulo' => 'Grafo de relaciones', 'grafo' => $grafo, 'svg' => GrafoPersona::svg($grafo), 'propio' => $objetivo === Auth::id(),
            'puedeBuscar' => $buscar, 'veTodas' => $todas, 'otros' => $buscar ? GrafoPersona::buscarPersonas($texto, $todas ? 20 : 50) : [], 'busqueda' => $texto]);
    }

    // ------------------------------------------------------------------ utilidades

    private function subido(string $campo): array
    {
        $f = $_FILES[$campo] ?? null;
        if (!is_array($f) || is_array($f['name'] ?? null) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
            throw new \InvalidArgumentException(in_array($f['error'] ?? 0, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                ? 'El archivo supera el tamaño que admite el servidor.' : 'No se recibió el archivo PDF completo.');
        }
        return ['tmp' => (string) $f['tmp_name'], 'nombre' => (string) $f['name']];
    }

    private function json(array $datos, int $http = 200): never
    {
        http_response_code($http);
        header('Content-Type: application/json');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function megas(string $ini): float
    {
        $n = (float) $ini;
        return match (strtolower(substr(trim($ini), -1))) { 'g' => $n * 1024, 'k' => $n / 1024, 'm' => $n, default => $n > 0 ? $n / 1048576 : 9999 };
    }
}
