<?php
declare(strict_types=1);

namespace App\Modules\Asuntos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

final class AsuntosController extends Controller
{
    public function index(string $oficinaRuta): void
    {
        $this->cabecerasPrivadas();
        $oficina = AsuntosAccess::oficinaPorRuta($oficinaRuta);
        $folio = $oficina ? AsuntosAccess::folioSolicitud((string) $oficina['codigo']) : '';
        if ($folio === '') {
            $this->noEncontrado();
        }
        if (!Auth::usuario()) {
            Auth::recordarDestino($this->rutaRegistro($oficina, $folio));
            redirigir('login');
        }
        AsuntosAccess::limitarConsulta();

        $expediente = $this->buscar((string) $oficina['codigo'], $folio);
        if (!$expediente) {
            if (!AsuntosAccess::esGestor($oficina)) {
                Audit::record('asuntos::consulta', 'denegado', 'Folio no disponible');
                $this->noEncontrado();
            }
            $this->vista('abrir', ['titulo' => 'Abrir entrada', 'folio' => $folio, 'oficina' => $oficina]);
            return;
        }
        if (!AsuntosAccess::puedeVer((int) $expediente['id'])) {
            Audit::record('asuntos::consulta', 'denegado', 'Sin acceso al expediente');
            $this->noEncontrado();
        }
        $this->mostrar($expediente, $folio, $oficina);
    }

    public function procesar(string $oficinaRuta): void
    {
        $this->cabecerasPrivadas();
        $oficina = AsuntosAccess::oficinaPorRuta($oficinaRuta);
        $folio = $oficina ? AsuntosAccess::folioSolicitud((string) $oficina['codigo']) : '';
        if ($folio === '' || !Auth::usuario()) {
            $this->noEncontrado();
        }
        AsuntosAccess::limitarConsulta();
        $accion = $this->post('accion');
        if ($accion === 'crear') {
            $this->crear($folio, $oficina);
        }
        if ($accion === 'guardar') {
            $this->guardar($folio, $oficina);
        }
        $this->noEncontrado();
    }

    public function descargar(string $oficinaRuta, string $id): never
    {
        $this->cabecerasPrivadas();
        $oficina = AsuntosAccess::oficinaPorRuta($oficinaRuta);
        $archivo = ctype_digit($id) ? Database::una(
            'SELECT a.*, e.id AS expediente_id, e.oficina FROM asunto_expediente_archivos a JOIN asunto_expedientes e ON e.id = a.expediente_id WHERE a.id = ?',
            [(int) $id]
        ) : null;
        if (!$oficina || !$archivo || $archivo['oficina'] !== $oficina['codigo'] || !AsuntosAccess::puedeVer((int) $archivo['expediente_id'])) {
            Audit::record('asuntos::archivo', 'denegado', 'Archivo no disponible');
            $this->noEncontrado();
        }
        Audit::record('asuntos::archivo', 'atendido', 'Archivo ' . (int) $archivo['id']);
        descargar_archivo((string) $archivo['ruta']);
    }

    private function crear(string $folio, array $oficina): never
    {
        if (!AsuntosAccess::esGestor($oficina) || $this->buscar((string) $oficina['codigo'], $folio)) {
            $this->noEncontrado();
        }
        $u = Auth::usuario();
        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $id = Database::ejecutar(
                "INSERT INTO asunto_expedientes (oficina, folio, asunto, personas_caso, fecha, estado, creado_por, actualizado_por) VALUES (?, ?, '', '', CURDATE(), 'abierto', ?, ?)",
                [$oficina['codigo'], $folio, $u['id'], $u['id']]
            );
            Database::ejecutar(
                'INSERT INTO asunto_expediente_accesos (expediente_id, email, creado_por) VALUES (?, ?, ?)',
                [$id, strtolower((string) $u['email']), $u['id']]
            );
            Database::ejecutar(
                "INSERT INTO asunto_expediente_eventos (expediente_id, usuario_id, tipo, detalle) VALUES (?, ?, 'creacion', 'Entrada abierta desde código QR')",
                [$id, $u['id']]
            );
            $carpeta = rtrim((string) \App\Core\App::config('archivos.storage'), '/\\') . DIRECTORY_SEPARATOR . strtolower((string) $oficina['codigo']) . DIRECTORY_SEPARATOR . $folio;
            if (!is_dir($carpeta) && !mkdir($carpeta, 0750, true)) {
                throw new \RuntimeException('No se pudo crear la carpeta del expediente.');
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('No se pudo abrir el expediente de asuntos: ' . $error->getMessage());
            flash('error', 'No se pudo abrir la entrada. Intenta de nuevo.');
            redirigir($this->rutaRegistro($oficina, $folio));
        }
        Audit::record('asuntos::crear', 'correcto', $folio);
        flash('exito', 'Entrada abierta. Completa el expediente y su lista de acceso.');
        redirigir($this->rutaRegistro($oficina, $folio));
    }

    private function guardar(string $folio, array $oficina): never
    {
        $expediente = $this->buscar((string) $oficina['codigo'], $folio);
        if (!$expediente || !AsuntosAccess::puedeVer((int) $expediente['id']) || !AsuntosAccess::esGestor($oficina)) {
            $this->noEncontrado();
        }

        $asunto = $this->post('asunto');
        $personasCaso = $this->post('personas_caso');
        $fecha = $this->post('fecha');
        $estado = $this->post('estado');
        if ($asunto === '' || mb_strlen($asunto) > 500 || mb_strlen($personasCaso) > 5000) {
            flash('error', 'Indica el asunto y respeta la longitud máxima de los campos.');
            redirigir($this->rutaRegistro($oficina, $folio));
        }
        $fechaObj = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            flash('error', 'La fecha no es válida.');
            redirigir($this->rutaRegistro($oficina, $folio));
        }
        $estados = ['abierto', 'en_seguimiento', 'en_espera', 'atendido', 'cerrado'];
        if (!in_array($estado, $estados, true)) {
            flash('error', 'El estado no es válido.');
            redirigir($this->rutaRegistro($oficina, $folio));
        }

        $correosTexto = str_replace(["\r\n", "\r", ',', ';'], "\n", $this->post('correos_acceso'));
        $correos = array_values(array_unique(array_filter(array_map(
            static fn(string $email): string => strtolower(trim($email)),
            explode("\n", $correosTexto)
        ))));
        $correoActual = strtolower((string) Auth::usuario()['email']);
        if (!in_array($correoActual, $correos, true)) {
            $correos[] = $correoActual;
        }
        if (count($correos) > 50 || array_filter($correos, static fn(string $email): bool => !AsuntosAccess::emailInstitucional($email))) {
            flash('error', 'Usa como máximo 50 correos institucionales válidos, uno por línea.');
            redirigir($this->rutaRegistro($oficina, $folio));
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::ejecutar(
                'UPDATE asunto_expedientes SET asunto = ?, personas_caso = ?, fecha = ?, estado = ?, actualizado_por = ?, actualizado_en = NOW() WHERE id = ?',
                [$asunto, $personasCaso, $fecha, $estado, Auth::id(), $expediente['id']]
            );
            Database::ejecutar('DELETE FROM asunto_expediente_accesos WHERE expediente_id = ?', [$expediente['id']]);
            foreach ($correos as $email) {
                Database::ejecutar(
                    'INSERT INTO asunto_expediente_accesos (expediente_id, email, creado_por) VALUES (?, ?, ?)',
                    [$expediente['id'], $email, Auth::id()]
                );
            }
            foreach (guardar_archivos('archivos', strtolower((string) $oficina['codigo']) . '/' . $folio) as $archivo) {
                Database::ejecutar(
                    'INSERT INTO asunto_expediente_archivos (expediente_id, subido_por, nombre_original, ruta) VALUES (?, ?, ?, ?)',
                    [$expediente['id'], Auth::id(), $archivo['nombre_original'], $archivo['ruta']]
                );
            }
            Database::ejecutar(
                "INSERT INTO asunto_expediente_eventos (expediente_id, usuario_id, tipo, detalle) VALUES (?, ?, 'actualizacion', ?)",
                [$expediente['id'], Auth::id(), 'Expediente actualizado; estado: ' . $estado]
            );
            $pdo->commit();
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('No se pudo guardar el expediente de asuntos: ' . $error->getMessage());
            flash('error', 'No se pudo guardar el expediente. Revisa los datos y vuelve a intentarlo.');
            redirigir($this->rutaRegistro($oficina, $folio));
        }
        Audit::record('asuntos::guardar', 'correcto', $folio);
        flash('exito', 'Expediente actualizado.');
        redirigir($this->rutaRegistro($oficina, $folio));
    }

    private function mostrar(array $expediente, string $folio, array $oficina): void
    {
        $accesos = Database::todas(
            'SELECT a.email, u.nombre FROM asunto_expediente_accesos a LEFT JOIN usuarios u ON LOWER(u.email) COLLATE utf8mb4_unicode_ci = LOWER(a.email) COLLATE utf8mb4_unicode_ci AND u.activo = 1 WHERE a.expediente_id = ? ORDER BY COALESCE(u.nombre, a.email) COLLATE utf8mb4_unicode_ci',
            [$expediente['id']]
        );
        $archivos = Database::todas(
            'SELECT a.*, u.nombre AS autor FROM asunto_expediente_archivos a JOIN usuarios u ON u.id = a.subido_por WHERE a.expediente_id = ? ORDER BY a.creado_en DESC, a.id DESC',
            [$expediente['id']]
        );
        $this->vista('expediente', [
            'titulo' => $folio,
            'folio' => $folio,
            'expediente' => $expediente,
            'accesos' => $accesos,
            'archivos' => $archivos,
            'puedeEditar' => AsuntosAccess::esGestor($oficina),
            'oficina' => $oficina,
        ]);
    }

    private function buscar(string $oficina, string $folio): ?array
    {
        return Database::una('SELECT * FROM asunto_expedientes WHERE oficina = ? AND folio = ?', [$oficina, $folio]);
    }

    private function rutaRegistro(array $oficina, string $folio): string
    {
        return 'fila/' . $oficina['ruta'] . '/reg.php?' . $folio;
    }

    private function cabecerasPrivadas(): void
    {
        header('Cache-Control: no-store, private, max-age=0');
        header('Pragma: no-cache');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Referrer-Policy: no-referrer');
    }

    private function noEncontrado(): never
    {
        http_response_code(404);
        View::render('errores/404', ['titulo' => 'Expediente no disponible'], Auth::usuario() ? 'app' : 'auth');
        exit;
    }
}
