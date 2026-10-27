<?php
declare(strict_types=1);

namespace App\Modules\Oficios;

use App\Core\{App, Audit, Auth, Controller, Database};

/**
 * Archivo de oficios enviados. Cada departamento/coordinación conserva y
 * consulta los suyos; el administrador ve todos.
 */
final class OficiosController extends Controller
{
    private const TIPOS = [
        'memorandum' => 'Memorándum',
        'oficio'     => 'Oficio',
        'circular'   => 'Circular',
        'otro'       => 'Otro',
    ];

    private function esAdmin(): bool
    {
        return Auth::tieneRol('admin');
    }

    /** @return array<int,array<string,mixed>> */
    private function departamentosPermitidos(): array
    {
        if ($this->esAdmin()) {
            return Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre');
        }
        return Database::todas('SELECT id, nombre FROM departamentos WHERE id = ?', [Auth::departamentoId()]);
    }

    private function noDisponible(): never
    {
        Audit::record('oficios_acceso', 'denegado');
        http_response_code(404);
        exit('Oficio no disponible.');
    }

    private function obtener(int $id): array
    {
        $o = Database::una(
            'SELECT o.*, d.nombre AS departamento, u.nombre AS registrado_por
               FROM oficios_enviados o
               JOIN departamentos d ON d.id = o.departamento_id
               JOIN usuarios u ON u.id = o.creado_por
              WHERE o.id = ?',
            [$id]
        );
        if (!$o || (!$this->esAdmin() && (int) $o['departamento_id'] !== Auth::departamentoId())) {
            $this->noDisponible();
        }
        return $o;
    }

    private static function fechaValida(string $v): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v;
    }

    public function index(): void
    {
        header('Cache-Control: no-store, private');
        $q      = mb_substr($this->consulta('q'), 0, 120);
        $tipo   = array_key_exists($this->consulta('tipo'), self::TIPOS) ? $this->consulta('tipo') : '';
        $desde  = self::fechaValida($this->consulta('desde')) ? $this->consulta('desde') : '';
        $hasta  = self::fechaValida($this->consulta('hasta')) ? $this->consulta('hasta') : '';
        $depto  = $this->esAdmin() ? (int) $this->consulta('departamento') : Auth::departamentoId();
        $pagina = max(1, min(10000, (int) $this->consulta('pagina', '1')));

        $donde = [];
        $params = [];
        if ($depto) {
            $donde[] = 'o.departamento_id = ?';
            $params[] = $depto;
        }
        if ($q !== '') {
            $like = '%' . strtr($q, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $donde[] = "(o.folio LIKE ? ESCAPE '!' OR o.asunto LIKE ? ESCAPE '!' OR o.destinatarios LIKE ? ESCAPE '!')";
            array_push($params, $like, $like, $like);
        }
        if ($tipo !== '') {
            $donde[] = 'o.tipo = ?';
            $params[] = $tipo;
        }
        if ($desde !== '') {
            $donde[] = 'o.fecha >= ?';
            $params[] = $desde;
        }
        if ($hasta !== '') {
            $donde[] = 'o.fecha <= ?';
            $params[] = $hasta;
        }

        $filas = Database::todas(
            'SELECT o.id, o.tipo, o.folio, o.fecha, o.asunto, o.destinatarios, d.nombre AS departamento
               FROM oficios_enviados o JOIN departamentos d ON d.id = o.departamento_id'
            . ($donde ? ' WHERE ' . implode(' AND ', $donde) : '')
            . ' ORDER BY o.fecha DESC, o.id DESC LIMIT 21 OFFSET ' . (($pagina - 1) * 20),
            $params
        );

        $this->vista('index', [
            'titulo' => 'Oficios enviados',
            'filas' => array_slice($filas, 0, 20),
            'hayMas' => count($filas) > 20,
            'pagina' => $pagina,
            'tipos' => self::TIPOS,
            'esAdmin' => $this->esAdmin(),
            'departamentos' => $this->esAdmin() ? $this->departamentosPermitidos() : [],
            'filtros' => ['q' => $q, 'tipo' => $tipo, 'desde' => $desde, 'hasta' => $hasta, 'departamento' => $this->esAdmin() ? $depto : 0],
        ]);
    }

    public function nuevo(): void
    {
        $this->vista('nuevo', [
            'titulo' => 'Archivar oficio enviado',
            'tipos' => self::TIPOS,
            'departamentos' => $this->departamentosPermitidos(),
            'esAdmin' => $this->esAdmin(),
        ]);
    }

    public function crear(): void
    {
        $ruta = null;
        try {
            $datos = $this->datosFormulario();
            $dept = $this->esAdmin() ? (int) $this->post('departamento_id') : Auth::departamentoId();
            if (!in_array($dept, array_map('intval', array_column($this->departamentosPermitidos(), 'id')), true)) {
                throw new \InvalidArgumentException('Selecciona la coordinación o departamento que envía el oficio.');
            }
            if (empty($_FILES['archivo']) || $_FILES['archivo']['error'] === UPLOAD_ERR_NO_FILE) {
                throw new \InvalidArgumentException('Adjunta el PDF del oficio firmado.');
            }
            $sha = (string) hash_file('sha256', (string) $_FILES['archivo']['tmp_name']);
            $nombre = $this->nombreSubido();
            $ruta = guardar_archivo('archivo', 'oficios/' . $dept, ['pdf']);

            $id = Database::ejecutar(
                'INSERT INTO oficios_enviados (departamento_id, tipo, folio, fecha, asunto, destinatarios, firmante, notas, archivo, archivo_nombre, sha256, creado_por)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',
                [$dept, $datos['tipo'], $datos['folio'], $datos['fecha'], $datos['asunto'], $datos['destinatarios'],
                 $datos['firmante'], $datos['notas'] ?: null, $ruta, $nombre, $sha, Auth::id()]
            );
            Audit::record('oficios_archivar', 'correcto', 'Oficio #' . $id . ' ' . $datos['folio']);
            flash('exito', 'Oficio archivado.');
            redirigir('oficios/' . $id);
        } catch (\Throwable $e) {
            $this->descartar($ruta);
            $this->fallar($e, 'oficios/nuevo');
        }
    }

    public function detalle(string $id): void
    {
        header('Cache-Control: no-store, private');
        $o = $this->obtener((int) $id);
        Audit::record('oficios_ver', 'correcto', 'Oficio #' . $o['id']);
        $this->vista('detalle', ['titulo' => $o['folio'], 'o' => $o, 'tipos' => self::TIPOS]);
    }

    public function actualizar(string $id): void
    {
        $o = $this->obtener((int) $id);
        $ruta = null;
        try {
            $datos = $this->datosFormulario();
            $sets = ['tipo = ?', 'folio = ?', 'fecha = ?', 'asunto = ?', 'destinatarios = ?', 'firmante = ?', 'notas = ?'];
            $params = [$datos['tipo'], $datos['folio'], $datos['fecha'], $datos['asunto'], $datos['destinatarios'], $datos['firmante'], $datos['notas'] ?: null];
            if (!empty($_FILES['archivo']) && $_FILES['archivo']['error'] !== UPLOAD_ERR_NO_FILE) {
                $sha = (string) hash_file('sha256', (string) $_FILES['archivo']['tmp_name']);
                $nombre = $this->nombreSubido();
                $ruta = guardar_archivo('archivo', 'oficios/' . $o['departamento_id'], ['pdf']);
                array_push($sets, 'archivo = ?', 'archivo_nombre = ?', 'sha256 = ?');
                array_push($params, $ruta, $nombre, $sha);
            }
            $params[] = $o['id'];
            Database::ejecutar('UPDATE oficios_enviados SET ' . implode(', ', $sets) . ' WHERE id = ?', $params);
            if ($ruta) {
                $this->descartar($o['archivo']);
            }
            Audit::record('oficios_editar', 'correcto', 'Oficio #' . $o['id']);
            flash('exito', 'Oficio actualizado.');
            redirigir('oficios/' . $o['id']);
        } catch (\Throwable $e) {
            $this->descartar($ruta);
            $this->fallar($e, 'oficios/' . $o['id']);
        }
    }

    public function archivo(string $id): void
    {
        $o = $this->obtener((int) $id);
        $base = realpath(App::config('archivos')['storage']);
        $ruta = $base ? realpath($base . '/' . $o['archivo']) : false;
        if (!$ruta || !is_file($ruta) || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }
        Audit::record('oficios_descargar', 'correcto', 'Oficio #' . $o['id']);
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', str_replace('/', '-', $o['folio'])) . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($ruta));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        readfile($ruta);
        exit;
    }

    /** @return array<string,string> */
    private function datosFormulario(): array
    {
        $d = [
            'tipo' => $this->post('tipo'),
            'folio' => $this->post('folio'),
            'fecha' => $this->post('fecha'),
            'asunto' => $this->post('asunto'),
            'destinatarios' => $this->post('destinatarios'),
            'firmante' => $this->post('firmante'),
            'notas' => $this->post('notas'),
        ];
        if (!array_key_exists($d['tipo'], self::TIPOS)) {
            throw new \InvalidArgumentException('Selecciona el tipo de documento.');
        }
        if ($d['folio'] === '' || mb_strlen($d['folio']) > 60) {
            throw new \InvalidArgumentException('Indica el folio (máximo 60 caracteres).');
        }
        if (!self::fechaValida($d['fecha'])) {
            throw new \InvalidArgumentException('La fecha del documento no es válida.');
        }
        foreach (['asunto' => 250, 'destinatarios' => 500, 'firmante' => 160, 'notas' => 4000] as $campo => $max) {
            if (mb_strlen($d[$campo]) > $max || (in_array($campo, ['asunto', 'destinatarios'], true) && $d[$campo] === '')) {
                throw new \InvalidArgumentException('Revisa el asunto, los destinatarios y la longitud de los campos.');
            }
        }
        return $d;
    }

    private function nombreSubido(): string
    {
        return mb_substr(basename(str_replace('\\', '/', (string) $_FILES['archivo']['name'])), 0, 200);
    }

    private function consulta(string $campo, string $defecto = ''): string
    {
        return trim((string) ($_GET[$campo] ?? $defecto));
    }

    private function descartar(?string $ruta): void
    {
        if ($ruta) {
            $f = rtrim(App::config('archivos')['storage'], '/\\') . '/' . $ruta;
            if (is_file($f)) {
                unlink($f);
            }
        }
    }

    private function fallar(\Throwable $e, string $destino): never
    {
        if ($e instanceof \PDOException && ($e->errorInfo[1] ?? 0) === 1062) {
            $mensaje = 'Ya existe un oficio con ese folio en esta coordinación.';
        } elseif ($e instanceof \InvalidArgumentException) {
            $mensaje = $e->getMessage();
        } else {
            error_log('Oficios: ' . $e->getMessage());
            $mensaje = 'No se pudo guardar el oficio. Intenta de nuevo.';
        }
        Audit::record('oficios_error', 'error', get_class($e));
        flash('error', $mensaje);
        redirigir($destino);
    }
}
