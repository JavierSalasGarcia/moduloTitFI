<?php
declare(strict_types=1);

namespace App\Modules\Justificantes;

use App\Core\{App, Audit, Auth, Controller, Database};

/** Oficios para justificar faltas de alumnos: redacción sobre la hoja membretada, con anexos, y consulta de los generados. */
final class JustificantesController extends Controller
{
    private function soloAutorizados(): array
    {
        $firmantes = Justificantes::firmantes();
        if (!$firmantes) {
            Audit::record('justificantes_acceso', 'denegado');
            http_response_code(403);
            \App\Core\View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
        return $firmantes;
    }

    private static function fechaValida(string $v): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        return $d && $d->format('Y-m-d') === $v;
    }

    private function raiz(): string
    {
        return rtrim((string) App::config('archivos')['storage'], '/\\');
    }

    public function index(): void
    {
        header('Cache-Control: no-store, private');
        $firmantes = $this->soloAutorizados();
        $admin = Auth::tieneRol('admin');
        $claves = array_keys($firmantes);
        $marcas = implode(',', array_fill(0, count($claves), '?'));
        $lista = Database::todas(
            'SELECT o.*, u.nombre AS redactor, (SELECT COUNT(*) FROM jus_adjuntos a WHERE a.oficio_id = o.id) AS anexos
               FROM jus_oficios o JOIN usuarios u ON u.id = o.usuario_id
              WHERE ' . ($admin ? '1 = 1' : "o.firmante IN ($marcas)") . ' ORDER BY o.id DESC LIMIT 200',
            $admin ? [] : $claves
        );
        $this->vista('index', ['titulo' => 'Justificar faltas de alumnos', 'lista' => $lista, 'admin' => $admin, 'nuevoId' => (int) ($_GET['nuevo'] ?? 0)]);
    }

    public function nuevo(): void
    {
        header('Cache-Control: no-store, private');
        $firmantes = $this->soloAutorizados();
        $previo = $_SESSION['justificante_borrador'] ?? null;
        unset($_SESSION['justificante_borrador']);
        $sugeridos = [];
        foreach ($firmantes as $k => $f) {
            $sugeridos[$k] = Justificantes::numeroSugerido($f);
        }
        $this->vista('nuevo', [
            'titulo' => 'Nuevo oficio para justificar faltas', 'firmantes' => $firmantes, 'sugeridos' => $sugeridos, 'previo' => $previo,
        ]);
    }

    public function crear(): void
    {
        $firmantes = $this->soloAutorizados();
        $guardados = [];
        $pdfRuta = null;
        $d = [];
        foreach (['firmante', 'numero', 'fecha', 'asunto', 'destinatario', 'saludo', 'cuerpo', 'periodo', 'motivo', 'alumnos'] as $c) {
            $d[$c] = $c === 'cuerpo' || $c === 'alumnos' ? trim(str_replace("\r\n", "\n", (string) ($_POST[$c] ?? ''))) : $this->post($c);
        }
        try {
            if (!isset($firmantes[$d['firmante']])) {
                throw new \InvalidArgumentException('Elige quién firma el oficio.');
            }
            $f = $firmantes[$d['firmante']];
            if (!self::fechaValida($d['fecha'])) {
                throw new \InvalidArgumentException('La fecha del oficio no es válida.');
            }
            foreach (['numero' => 80, 'asunto' => 200, 'destinatario' => 200, 'saludo' => 200, 'periodo' => 250, 'motivo' => 250] as $campo => $max) {
                if (mb_strlen($d[$campo]) > $max) {
                    throw new \InvalidArgumentException('Revisa la longitud de los campos (máximo ' . $max . ' caracteres en «' . $campo . '»).');
                }
            }
            if (mb_strlen($d['asunto']) < 3 || mb_strlen($d['cuerpo']) < 20 || mb_strlen($d['cuerpo']) > 4000) {
                throw new \InvalidArgumentException('Escribe el asunto y un mensaje de 20 a 4,000 caracteres.');
            }
            $alumnos = Justificantes::alumnos($d['alumnos']);
            if (!$alumnos || count($alumnos) > 60) {
                throw new \InvalidArgumentException('Anota de 1 a 60 alumnos, uno por renglón: nombre, número de cuenta y grupo.');
            }
            if (!empty($_FILES['adjuntos']['error']) && is_array($_FILES['adjuntos']['error'])
                && count(array_filter($_FILES['adjuntos']['error'], static fn($e): bool => $e !== UPLOAD_ERR_NO_FILE)) > Justificantes::MAX_ADJUNTOS) {
                throw new \InvalidArgumentException('Puedes adjuntar hasta ' . Justificantes::MAX_ADJUNTOS . ' archivos.');
            }
            $guardados = guardar_archivos('adjuntos', 'justificantes/' . date('Y'), ['pdf', 'jpg', 'jpeg', 'png']);
            $base = realpath($this->raiz());
            $adjuntos = [];
            foreach ($guardados as $g) {
                $adjuntos[] = [
                    'nombre' => mb_substr(basename(str_replace('\\', '/', (string) $g['nombre_original'])), 0, 200),
                    'ruta' => $base . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $g['ruta']),
                ];
            }
            $bytes = Justificantes::pdf($d, $f, $adjuntos);

            $carpeta = $this->raiz() . '/justificantes/' . date('Y');
            if (!is_dir($carpeta) && !mkdir($carpeta, 0755, true)) {
                throw new \RuntimeException('No se pudo crear la carpeta de almacenamiento.');
            }
            $nombre = 'oficio-' . bin2hex(random_bytes(8)) . '.pdf';
            file_put_contents($carpeta . '/' . $nombre, $bytes);
            $pdfRuta = 'justificantes/' . date('Y') . '/' . $nombre;

            $id = Database::ejecutar(
                'INSERT INTO jus_oficios (usuario_id, firmante, numero, fecha, asunto, destinatario, saludo, cuerpo, periodo, motivo, alumnos, firmante_nombre, firmante_cargo, archivo)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [Auth::id(), $d['firmante'], $d['numero'], $d['fecha'], $d['asunto'], $d['destinatario'], $d['saludo'], $d['cuerpo'], $d['periodo'], $d['motivo'], $d['alumnos'], $f['nombre'], $f['cargo'], $pdfRuta]
            );
            foreach ($guardados as $i => $g) {
                Database::ejecutar('INSERT INTO jus_adjuntos (oficio_id, nombre, ruta) VALUES (?,?,?)', [$id, $adjuntos[$i]['nombre'], $g['ruta']]);
            }
            Audit::record('justificantes_crear', 'correcto', 'Oficio #' . $id . ' · ' . count($alumnos) . ' alumno(s) · ' . count($adjuntos) . ' anexo(s)');
            flash('exito', 'Oficio generado' . ($adjuntos ? ' con ' . count($adjuntos) . ' anexo(s) incluido(s)' : '') . '. Descárgalo para imprimirlo o enviarlo.');
            redirigir('justificantes?nuevo=' . $id);
        } catch (\Throwable $e) {
            foreach ($guardados as $g) {
                $archivo = $this->raiz() . '/' . $g['ruta'];
                if (is_file($archivo)) {
                    unlink($archivo);
                }
            }
            if ($pdfRuta && is_file($this->raiz() . '/' . $pdfRuta)) {
                unlink($this->raiz() . '/' . $pdfRuta);
            }
            if (!($e instanceof \InvalidArgumentException)) {
                error_log('Justificantes: ' . $e->getMessage());
            }
            Audit::record('justificantes_error', 'error', get_class($e));
            $_SESSION['justificante_borrador'] = $d;
            flash('error', $e instanceof \InvalidArgumentException ? $e->getMessage() : 'No se pudo generar el oficio. Intenta de nuevo.');
            redirigir('justificantes/nuevo');
        }
    }

    public function archivo(string $id): void
    {
        $firmantes = $this->soloAutorizados();
        $o = Database::una('SELECT * FROM jus_oficios WHERE id = ?', [(int) $id]);
        if (!$o || (!Auth::tieneRol('admin') && !isset($firmantes[$o['firmante']]))) {
            Audit::record('justificantes_acceso', 'denegado');
            http_response_code(404);
            exit('Oficio no disponible.');
        }
        $base = realpath($this->raiz());
        $ruta = $base ? realpath($base . '/' . $o['archivo']) : false;
        if (!$ruta || !is_file($ruta) || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR)) {
            http_response_code(404);
            exit('Archivo no encontrado.');
        }
        Audit::record('justificantes_descargar', 'correcto', 'Oficio #' . $o['id']);
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="justificacion-faltas-' . (int) $o['id'] . '.pdf"');
        header('Content-Length: ' . filesize($ruta));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        readfile($ruta);
        exit;
    }
}
