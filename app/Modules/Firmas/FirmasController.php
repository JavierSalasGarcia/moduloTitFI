<?php
declare(strict_types=1);

namespace App\Modules\Firmas;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Totp;
use App\Core\View;

/**
 * Solicitudes de firma de documentos PDF: rutas secuenciales con
 * intermediarios (Bo.Vo.) hasta un destinatario final, o firma paralela
 * de un conjunto de personas. Cada firma queda estampada en el PDF
 * (cuando FPDI puede procesarlo) y SIEMPRE en la hoja de firmas y en la
 * BD con hash de integridad.
 */
final class FirmasController extends Controller
{
    private const ROLES_CREADORES = ['admin', 'titular', 'operativo', 'profesor'];
    private const PAGINAS    = ['primera', 'ultima'];
    private const POSICIONES = ['abajo_izquierda', 'abajo_centro', 'abajo_derecha'];

    // ── Bandejas ────────────────────────────────────────────────────

    public function index(): void
    {
        $bandeja = ($_GET['bandeja'] ?? '') === 'mias' ? 'mias' : 'firmar';
        $vista   = ($_GET['vista'] ?? '') === 'terminadas' ? 'terminadas' : 'activas';
        $filtroEstado = $vista === 'activas' ? "s.estado = 'en_ruta'" : "s.estado <> 'en_ruta'";

        if ($bandeja === 'firmar') {
            $solicitudes = Database::todas(
                "SELECT s.*, u.nombre AS creador, f.estado AS mi_estado, f.orden AS mi_orden,
                        (s.estado = 'en_ruta' AND f.estado = 'pendiente'
                         AND (s.modo = 'paralela' OR f.orden = s.paso_actual)) AS me_toca
                   FROM firma_firmantes f
                   JOIN firma_solicitudes s ON s.id = f.solicitud_id
                   JOIN usuarios u ON u.id = s.creador_id
                  WHERE f.usuario_id = ? AND {$filtroEstado}
                  ORDER BY me_toca DESC, s.actualizado_en DESC",
                [Auth::id()]);
        } else {
            $solicitudes = Database::todas(
                "SELECT s.*,
                        (SELECT COUNT(*) FROM firma_firmantes WHERE solicitud_id = s.id) AS total_firmantes,
                        (SELECT COUNT(*) FROM firma_firmantes WHERE solicitud_id = s.id AND estado = 'firmado') AS firmados
                   FROM firma_solicitudes s
                  WHERE s.creador_id = ? AND {$filtroEstado}
                  ORDER BY s.actualizado_en DESC",
                [Auth::id()]);
        }

        $this->vista('index', [
            'titulo'       => 'Firmas de documentos',
            'bandeja'      => $bandeja,
            'vistaFiltro'  => $vista,
            'solicitudes'  => $solicitudes,
            'puedeCrear'   => Auth::tieneRol(...self::ROLES_CREADORES),
        ]);
    }

    // ── Nueva solicitud ─────────────────────────────────────────────

    public function nuevo(): void
    {
        $this->exigirCreador();
        $this->vista('nuevo', [
            'titulo'   => 'Nueva solicitud de firma',
            'usuarios' => $this->usuariosActivos(),
            'grupos'   => Database::todas(
                "SELECT g.*, p.siglas FROM firma_grupos g
                   LEFT JOIN programas p ON p.id = g.programa_id
                  WHERE g.tipo <> 'personal' OR g.creador_id = ?
                  ORDER BY FIELD(g.tipo,'personal','nab','cap','cc','cuerpo_academico'), g.nombre",
                [Auth::id()]),
        ]);
    }

    public function crear(): void
    {
        $this->exigirCreador();
        $titulo  = $this->post('titulo');
        $mensaje = $this->post('mensaje');
        $modo    = $this->post('modo') === 'secuencial' ? 'secuencial' : 'paralela';

        if ($titulo === '') {
            flash('error', 'Escribe un título para la solicitud.');
            redirigir('firmas/nuevo');
        }

        // Firmantes según el modo
        [$firmantes, $error] = $this->resolverFirmantes($modo);
        if ($error !== null) {
            flash('error', $error);
            redirigir('firmas/nuevo');
        }

        // Documentos PDF
        try {
            $docs = guardar_archivos('documentos', 'firmas', ['pdf']);
        } catch (\InvalidArgumentException $ex) {
            flash('error', $ex->getMessage());
            redirigir('firmas/nuevo');
        }
        if (!$docs) {
            flash('error', 'Adjunta al menos un documento PDF.');
            redirigir('firmas/nuevo');
        }

        $id = Database::ejecutar(
            "INSERT INTO firma_solicitudes (folio, creador_id, titulo, mensaje, modo) VALUES ('', ?, ?, ?, ?)",
            [Auth::id(), $titulo, $mensaje ?: null, $modo]);
        $folio = sprintf('FD-%s-%04d', date('Y'), $id);
        Database::ejecutar('UPDATE firma_solicitudes SET folio = ? WHERE id = ?', [$folio, $id]);

        $storage = rtrim(App::config('archivos')['storage'], '/\\');
        foreach ($docs as $d) {
            Database::ejecutar(
                "INSERT INTO firma_documentos (solicitud_id, usuario_id, tipo, nombre, archivo, estampable)
                 VALUES (?, ?, 'original', ?, ?, ?)",
                [$id, Auth::id(), mb_strimwidth($d['nombre_original'], 0, 160),
                 $d['ruta'], PdfFirmador::esEstampable($storage . '/' . $d['ruta']) ? 1 : 0]);
        }
        foreach ($firmantes as $f) {
            Database::ejecutar(
                'INSERT INTO firma_firmantes (solicitud_id, usuario_id, orden) VALUES (?, ?, ?)',
                [$id, $f['id'], $f['orden']]);
        }

        // Guardar los destinatarios como grupo personal reutilizable
        $nombreGrupo = $this->post('guardar_grupo_nombre');
        if ($nombreGrupo !== '') {
            $grupoId = Database::ejecutar(
                "INSERT INTO firma_grupos (nombre, tipo, creador_id) VALUES (?, 'personal', ?)",
                [mb_strimwidth($nombreGrupo, 0, 160), Auth::id()]);
            foreach ($firmantes as $f) {
                Database::ejecutar(
                    'INSERT IGNORE INTO firma_grupo_miembros (grupo_id, usuario_id) VALUES (?, ?)',
                    [$grupoId, $f['id']]);
            }
        }

        Database::ejecutar(
            "INSERT INTO firma_eventos (solicitud_id, usuario_id, tipo, contenido) VALUES (?, ?, 'creacion', ?)",
            [$id, Auth::id(), 'Solicitud creada (' . $modo . ', ' . count($docs) . ' documento(s), '
                . count($firmantes) . ' firmante(s)).']);

        flash('exito', "Solicitud {$folio} creada y enviada a firma.");
        redirigir('firmas/' . $id);
    }

    // ── Detalle ─────────────────────────────────────────────────────

    public function detalle(string $id): void
    {
        $s = $this->obtenerPropia((int) $id);
        $firmantes = Database::todas(
            'SELECT f.*, u.nombre, u.email FROM firma_firmantes f
               JOIN usuarios u ON u.id = f.usuario_id
              WHERE f.solicitud_id = ? ORDER BY f.orden, u.nombre',
            [$s['id']]);
        $documentos = Database::todas(
            "SELECT d.*, u.nombre AS subido_por FROM firma_documentos d
               JOIN usuarios u ON u.id = d.usuario_id
              WHERE d.solicitud_id = ? ORDER BY FIELD(d.tipo,'original','anexo','hoja_firmas'), d.id",
            [$s['id']]);
        $storage = rtrim(App::config('archivos')['storage'], '/\\');
        foreach ($documentos as &$documento) {
            if ($documento['tipo'] === 'hoja_firmas') continue;
            $vigente = $storage . '/' . ($documento['archivo_firmado'] ?: $documento['archivo']);
            $documento['sha256_actual'] = is_file($vigente) ? (hash_file('sha256', $vigente) ?: '') : '';
        }
        unset($documento);
        $eventos = Database::todas(
            'SELECT ev.*, u.nombre AS autor FROM firma_eventos ev
               JOIN usuarios u ON u.id = ev.usuario_id
              WHERE ev.solicitud_id = ? ORDER BY ev.creado_en, ev.id',
            [$s['id']]);

        $mio = null;
        foreach ($firmantes as $f) {
            if ((int) $f['usuario_id'] === Auth::id()) {
                $mio = $f;
            }
        }
        $meToca = $mio && $s['estado'] === 'en_ruta' && $mio['estado'] === 'pendiente'
                  && ($s['modo'] === 'paralela' || (int) $mio['orden'] === (int) $s['paso_actual']);
        $maxOrden = $firmantes ? max(array_map(fn($f) => (int) $f['orden'], $firmantes)) : 1;
        $esCreador = (int) $s['creador_id'] === Auth::id();
        $puedeAnexar = $s['estado'] === 'en_ruta'
            && ($esCreador || ($mio && (int) $mio['orden'] <= (int) $s['paso_actual']))
            && ($s['modo'] === 'paralela' || (int) $s['paso_actual'] < $maxOrden);
        $usuarioFirma = Database::una('SELECT totp_secret FROM usuarios WHERE id = ?', [Auth::id()]);
        $certificado = null;
        $pkiLista = false;
        if (PadesSigner::habilitado()) {
            try {
                $pkiLista = !empty(PadesSigner::estado()['ok']);
                $certificado = Database::una(
                    "SELECT serie, huella_sha256, valido_hasta, estado FROM firma_certificados
                      WHERE usuario_id = ? ORDER BY id DESC LIMIT 1", [Auth::id()]);
            } catch (\Throwable) {
                $pkiLista = false;
            }
        }

        $this->vista('detalle', [
            'titulo'      => $s['folio'],
            's'           => $s,
            'firmantes'   => $firmantes,
            'documentos'  => $documentos,
            'eventos'     => $eventos,
            'meToca'      => $meToca,
            'esCreador'   => $esCreador,
            'puedeAnexar' => $puedeAnexar,
            'puedeCancelar' => $s['estado'] === 'en_ruta' && ($esCreador || Auth::tieneRol('admin')),
            'tengoSello'  => (bool) Database::una(
                'SELECT 1 FROM usuario_firmas WHERE usuario_id = ?', [Auth::id()]),
            'paginas'     => self::PAGINAS,
            'posiciones'  => self::POSICIONES,
            'pkiLista'    => $pkiLista,
            'tieneTotp'   => !empty($usuarioFirma['totp_secret']),
            'certificado' => $certificado,
        ]);
    }

    // ── Firmar / rechazar ───────────────────────────────────────────

    public function firmar(string $id): void
    {
        $s = $this->obtenerPropia((int) $id);
        $mio = $this->firmantePendiente($s);

        if (!PadesSigner::habilitado()) {
            flash('error', 'La firma digital PAdES todavía no está habilitada por la administración.');
            redirigir('firmas/' . $s['id']);
        }
        try {
            PadesSigner::estado();
        } catch (\Throwable $ex) {
            flash('error', 'La infraestructura criptográfica no está disponible: ' . $ex->getMessage());
            redirigir('firmas/' . $s['id']);
        }

        $usuario = Database::una('SELECT id, nombre, email, totp_secret FROM usuarios WHERE id = ?', [Auth::id()]);
        $codigo = preg_replace('/\D+/', '', $this->post('codigo_2fa'));
        if (!$usuario || empty($usuario['totp_secret'])) {
            flash('error', 'Debes activar la contraseña de un solo uso basada en tiempo antes de firmar.');
            redirigir('firmas/' . $s['id']);
        }
        if (!Totp::verificar($usuario['totp_secret'], $codigo)) {
            flash('error', 'El código de segundo factor no es válido o ya expiró.');
            redirigir('firmas/' . $s['id']);
        }
        try {
            $this->registrarUsoSegundoFactor((int) $usuario['id'], $codigo);
        } catch (\Throwable $ex) {
            flash('error', $ex->getMessage());
            redirigir('firmas/' . $s['id']);
        }

        $pagina   = in_array($this->post('pagina'), self::PAGINAS, true) ? $this->post('pagina') : 'ultima';
        $posicion = in_array($this->post('posicion'), self::POSICIONES, true) ? $this->post('posicion') : 'abajo_derecha';

        $storage = rtrim(App::config('archivos')['storage'], '/\\');
        $docs    = Database::todas(
            "SELECT * FROM firma_documentos WHERE solicitud_id = ? AND tipo <> 'hoja_firmas'", [$s['id']]);

        $bloqueo = 'traza_firma_' . (int) $s['id'];
        if ((int) Database::valor('SELECT GET_LOCK(?, 15)', [$bloqueo]) !== 1) {
            flash('error', 'Otra firma está procesándose. Intenta de nuevo en unos segundos.');
            redirigir('firmas/' . $s['id']);
        }
        $salidas = [];
        try {
            $estadoActual = Database::valor('SELECT estado FROM firma_firmantes WHERE id = ?', [$mio['id']]);
            if ($estadoActual !== 'pendiente') {
                throw new \RuntimeException('Esta solicitud ya fue atendida en otra sesión.');
            }
            $certificado = $this->certificadoActivo($usuario);
            $retoPartes = [$s['folio'], (string) $usuario['id']];
            foreach ($docs as $d) {
                $fuente = $storage . '/' . ($d['archivo_firmado'] ?: $d['archivo']);
                if (!is_file($fuente)) throw new \RuntimeException('No se encontró el documento ' . $d['nombre'] . '.');
                $retoPartes[] = hash_file('sha256', $fuente) ?: '';
            }
            $desafio = hash('sha256', implode('|', $retoPartes));
            if (!is_dir($storage . '/firmas/firmados')) mkdir($storage . '/firmas/firmados', 0750, true);

            foreach ($docs as $d) {
                $fuente = $storage . '/' . ($d['archivo_firmado'] ?: $d['archivo']);
                $nivel = (int) Database::valor(
                    'SELECT COUNT(*) FROM firma_operaciones WHERE documento_id = ? AND resultado = \'firmado\'', [$d['id']]);
                $sufijo = bin2hex(random_bytes(5));
                $relSalida = 'firmas/firmados/doc' . $d['id'] . '-u' . $usuario['id'] . '-' . $sufijo . '.pdf';
                $campo = 'TrazaFI_D' . $d['id'] . '_U' . $usuario['id'] . '_' . $sufijo;
                $resultado = PadesSigner::firmar([
                    'input_pdf' => $fuente, 'output_pdf' => $storage . '/' . $relSalida,
                    'pfx_path' => $certificado['pfx_path'], 'wrapped_secret' => $certificado['secreto_envuelto'],
                    'field_name' => $campo, 'signer_name' => $usuario['nombre'], 'folio' => $s['folio'],
                    'reason' => 'Firma institucional en Traza FI', 'page' => $pagina,
                    'position' => $posicion, 'level' => $nivel,
                ]);
                $salidas[] = ['documento' => $d, 'rel' => $relSalida, 'resultado' => $resultado,
                    'campo' => $campo, 'desafio' => $desafio];
            }

            $pdo = Database::pdo();
            $pdo->beginTransaction();
            foreach ($salidas as $item) {
                $d = $item['documento']; $resultado = $item['resultado'];
                Database::ejecutar(
                    'INSERT INTO firma_estampas (firmante_id, documento_id, pagina, posicion, estampada, sha256)
                     VALUES (?, ?, ?, ?, 1, ?)',
                    [$mio['id'], $d['id'], $pagina, $posicion, $resultado['sha256_before']]);
                Database::ejecutar(
                    "INSERT INTO firma_operaciones
                     (solicitud_id, firmante_id, documento_id, certificado_id, campo_pdf, segundo_factor,
                      desafio_sha256, entrada_sha256, salida_sha256, resultado)
                     VALUES (?, ?, ?, ?, ?, 'totp', ?, ?, ?, 'firmado')",
                    [$s['id'], $mio['id'], $d['id'], $certificado['id'], $item['campo'], $item['desafio'],
                     $resultado['sha256_before'], $resultado['sha256_after']]);
                Database::ejecutar('UPDATE firma_documentos SET archivo_firmado = ?, estampable = 1 WHERE id = ?',
                    [$item['rel'], $d['id']]);
            }
            Database::ejecutar(
                "UPDATE firma_firmantes SET estado = 'firmado', respondido_en = NOW() WHERE id = ?", [$mio['id']]);
            Database::ejecutar(
                "INSERT INTO firma_eventos (solicitud_id, usuario_id, tipo, contenido) VALUES (?, ?, 'firma', ?)",
                [$s['id'], Auth::id(), 'Documento(s) firmado(s) mediante PAdES con certificado individual y segundo factor.']);
            $pdo->commit();
        } catch (\Throwable $ex) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            foreach ($salidas as $item) @unlink($storage . '/' . $item['rel']);
            Database::valor('SELECT RELEASE_LOCK(?)', [$bloqueo]);
            flash('error', 'No se realizó ninguna firma: ' . $ex->getMessage());
            redirigir('firmas/' . $s['id']);
        }
        Database::valor('SELECT RELEASE_LOCK(?)', [$bloqueo]);

        $this->avanzarRuta($s);
        $this->regenerarHoja((int) $s['id']);

        flash('exito', 'Tu firma digital PAdES quedó registrada y vinculada a tu certificado individual.');
        redirigir('firmas/' . $s['id']);
    }

    public function rechazar(string $id): void
    {
        $s = $this->obtenerPropia((int) $id);
        $mio = $this->firmantePendiente($s);
        $motivo = $this->post('motivo');
        if ($motivo === '') {
            flash('error', 'Indica el motivo del rechazo.');
            redirigir('firmas/' . $s['id']);
        }

        Database::ejecutar(
            "UPDATE firma_firmantes SET estado = 'rechazado', motivo_rechazo = ?, respondido_en = NOW() WHERE id = ?",
            [mb_strimwidth($motivo, 0, 500), $mio['id']]);
        Database::ejecutar(
            "INSERT INTO firma_eventos (solicitud_id, usuario_id, tipo, contenido) VALUES (?, ?, 'rechazo', ?)",
            [$s['id'], Auth::id(), $motivo]);

        if ($s['modo'] === 'secuencial') {
            // Un rechazo detiene la ruta completa: el creador corrige y
            // levanta una nueva solicitud (sin reanudación).
            Database::ejecutar("UPDATE firma_solicitudes SET estado = 'rechazada' WHERE id = ?", [$s['id']]);
        } else {
            $this->avanzarRuta($s);
        }
        $this->regenerarHoja((int) $s['id']);

        flash('exito', 'Rechazo registrado; se notificó en la bitácora.');
        redirigir('firmas/' . $s['id']);
    }

    // ── Anexos e interrupciones ─────────────────────────────────────

    public function anexar(string $id): void
    {
        $s = $this->obtenerPropia((int) $id);
        $mio = Database::una(
            'SELECT * FROM firma_firmantes WHERE solicitud_id = ? AND usuario_id = ?',
            [$s['id'], Auth::id()]);
        $maxOrden = (int) Database::valor(
            'SELECT MAX(orden) FROM firma_firmantes WHERE solicitud_id = ?', [$s['id']]);

        $puede = $s['estado'] === 'en_ruta'
            && ((int) $s['creador_id'] === Auth::id() || ($mio && (int) $mio['orden'] <= (int) $s['paso_actual']))
            && ($s['modo'] === 'paralela' || (int) $s['paso_actual'] < $maxOrden);
        if (!$puede) {
            flash('error', 'Ya no se pueden añadir anexos a esta solicitud.');
            redirigir('firmas/' . $s['id']);
        }

        try {
            $docs = guardar_archivos('documentos', 'firmas', ['pdf']);
        } catch (\InvalidArgumentException $ex) {
            flash('error', $ex->getMessage());
            redirigir('firmas/' . $s['id']);
        }
        if (!$docs) {
            flash('error', 'Adjunta al menos un PDF como anexo.');
            redirigir('firmas/' . $s['id']);
        }

        $storage = rtrim(App::config('archivos')['storage'], '/\\');
        foreach ($docs as $d) {
            Database::ejecutar(
                "INSERT INTO firma_documentos (solicitud_id, usuario_id, tipo, nombre, archivo, estampable)
                 VALUES (?, ?, 'anexo', ?, ?, ?)",
                [$s['id'], Auth::id(), mb_strimwidth($d['nombre_original'], 0, 160),
                 $d['ruta'], PdfFirmador::esEstampable($storage . '/' . $d['ruta']) ? 1 : 0]);
        }
        Database::ejecutar(
            "INSERT INTO firma_eventos (solicitud_id, usuario_id, tipo, contenido) VALUES (?, ?, 'anexo', ?)",
            [$s['id'], Auth::id(), count($docs) . ' anexo(s): '
                . implode(', ', array_map(fn($d) => $d['nombre_original'], $docs))]);
        Database::ejecutar('UPDATE firma_solicitudes SET actualizado_en = NOW() WHERE id = ?', [$s['id']]);

        flash('exito', 'Anexo(s) agregado(s) a la solicitud.');
        redirigir('firmas/' . $s['id']);
    }

    public function cancelar(string $id): void
    {
        $s = $this->obtenerPropia((int) $id);
        if ($s['estado'] !== 'en_ruta' || ((int) $s['creador_id'] !== Auth::id() && !Auth::tieneRol('admin'))) {
            flash('error', 'Solo quien creó la solicitud puede cancelarla mientras está en ruta.');
            redirigir('firmas/' . $s['id']);
        }
        Database::ejecutar("UPDATE firma_solicitudes SET estado = 'cancelada' WHERE id = ?", [$s['id']]);
        Database::ejecutar(
            "INSERT INTO firma_eventos (solicitud_id, usuario_id, tipo, contenido) VALUES (?, ?, 'cancelacion', 'Solicitud cancelada.')",
            [$s['id'], Auth::id()]);
        flash('exito', 'Solicitud cancelada.');
        redirigir('firmas');
    }

    // ── Documentos ──────────────────────────────────────────────────

    public function descargarDocumento(string $id): void
    {
        $d = Database::una(
            'SELECT d.*, s.creador_id FROM firma_documentos d
               JOIN firma_solicitudes s ON s.id = d.solicitud_id
              WHERE d.id = ?', [(int) $id]);
        $esParte = $d && ((int) $d['creador_id'] === Auth::id()
            || Auth::tieneRol('admin')
            || Database::una('SELECT 1 FROM firma_firmantes WHERE solicitud_id = ? AND usuario_id = ?',
                [$d['solicitud_id'], Auth::id()]));
        if (!$esParte) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No encontrado']);
            exit;
        }
        $version = ($_GET['v'] ?? '') === 'firmado' && $d['archivo_firmado'] ? $d['archivo_firmado'] : $d['archivo'];
        descargar_archivo($version);
    }

    // ── Mi firma (imagen del sello) ─────────────────────────────────

    public function miFirma(): void
    {
        $this->vista('mi_firma', [
            'titulo' => 'Mi firma',
            'firma'  => Database::una('SELECT * FROM usuario_firmas WHERE usuario_id = ?', [Auth::id()]),
        ]);
    }

    public function ayuda(): void { $this->vista('ayuda', ['titulo' => 'Ayuda: firma digital institucional']); }

    public function descargarCertificado(): void
    {
        $ruta = PadesSigner::certificadoRaiz();
        if (!PadesSigner::habilitado() || $ruta === '' || !is_file($ruta)) {
            http_response_code(503);
            exit('El certificado público raíz todavía no ha sido publicado por la Facultad.');
        }
        header('Content-Type: application/x-x509-ca-cert');
        header('Content-Length: ' . filesize($ruta));
        header('Content-Disposition: attachment; filename="traza-fi-raiz-publica.crt"');
        header('X-Content-Type-Options: nosniff');
        readfile($ruta); exit;
    }

    public function guardarMiFirma(): void
    {
        $ruta = null;
        $canvas = (string) ($_POST['firma_canvas'] ?? '');
        if (str_starts_with($canvas, 'data:image/png;base64,')) {
            $binario = base64_decode(substr($canvas, 22), true);
            if ($binario !== false && strlen($binario) <= 2 * 1024 * 1024) {
                $storage = rtrim(App::config('archivos')['storage'], '/\\');
                if (!is_dir($storage . '/firmas/sellos')) mkdir($storage . '/firmas/sellos', 0755, true);
                $ruta = 'firmas/sellos/canvas-' . bin2hex(random_bytes(8)) . '.png';
                file_put_contents($storage . '/' . $ruta, $binario);
            }
        }
        if ($ruta !== null) {
            Database::ejecutar('INSERT INTO usuario_firmas (usuario_id, imagen) VALUES (?, ?) ON DUPLICATE KEY UPDATE imagen = VALUES(imagen)', [Auth::id(), $ruta]);
            flash('exito', 'Tu trazo quedó guardado.'); redirigir('firmas/mi-firma');
        }
        try {
            $ruta = guardar_archivo('imagen', 'firmas/sellos', ['png', 'jpg', 'jpeg']);
        } catch (\InvalidArgumentException $ex) {
            flash('error', $ex->getMessage());
            redirigir('firmas/mi-firma');
        }
        if ($ruta === null) {
            flash('error', 'Selecciona una imagen png o jpg de tu firma.');
            redirigir('firmas/mi-firma');
        }
        Database::ejecutar(
            'INSERT INTO usuario_firmas (usuario_id, imagen) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE imagen = VALUES(imagen)',
            [Auth::id(), $ruta]);
        flash('exito', 'Tu imagen de firma quedó guardada; se usará al estampar tus firmas.');
        redirigir('firmas/mi-firma');
    }

    public function miFirmaImagen(): never
    {
        $firma = Database::una('SELECT imagen FROM usuario_firmas WHERE usuario_id = ?', [Auth::id()]);
        $storage = rtrim(App::config('archivos')['storage'], '/\\');
        $ruta = $firma ? $storage . '/' . ltrim($firma['imagen'], '/\\') : '';
        if (!$ruta || !is_file($ruta)) { http_response_code(404); exit; }
        $mime = mime_content_type($ruta) ?: 'image/png';
        header('Content-Type: ' . ($mime === 'image/jpeg' ? 'image/jpeg' : 'image/png'));
        header('Cache-Control: private, max-age=300');
        readfile($ruta); exit;
    }

    // ── Auxiliares ──────────────────────────────────────────────────

    private function registrarUsoSegundoFactor(int $usuarioId, string $codigo): void
    {
        $ruta = (string) App::config('firma_pki.audit_hmac_key', '');
        $clave = $ruta !== '' && is_file($ruta) ? trim((string) file_get_contents($ruta)) : '';
        if (strlen($clave) < 32) {
            throw new \RuntimeException('Falta configurar la llave de auditoría del segundo factor.');
        }
        $ventana = (string) intdiv(time(), 30);
        $huella = hash_hmac('sha256', $usuarioId . '|' . $codigo . '|' . $ventana, $clave);
        try {
            Database::ejecutar(
                'INSERT INTO firma_segundo_factor_usos (usuario_id, codigo_hash) VALUES (?, ?)',
                [$usuarioId, $huella]);
        } catch (\Throwable) {
            throw new \RuntimeException('Ese código de segundo factor ya fue usado para firmar. Espera el siguiente código.');
        }
    }

    private function certificadoActivo(array $usuario): array
    {
        $certificado = Database::una(
            "SELECT * FROM firma_certificados
              WHERE usuario_id = ? AND estado = 'activo' AND valido_hasta > NOW()
              ORDER BY id DESC LIMIT 1", [(int) $usuario['id']]);
        if ($certificado && is_file($certificado['pfx_path'])) return $certificado;
        if ($certificado) {
            Database::ejecutar(
                "UPDATE firma_certificados SET estado = 'revocado', revocado_en = NOW(),
                 motivo_revocacion = 'Archivo criptográfico ausente' WHERE id = ?", [$certificado['id']]);
        }

        $emitido = PadesSigner::emitir((int) $usuario['id'], $usuario['nombre'], $usuario['email']);
        $desde = (new \DateTimeImmutable($emitido['valid_from']))->format('Y-m-d H:i:s');
        $hasta = (new \DateTimeImmutable($emitido['valid_until']))->format('Y-m-d H:i:s');
        $id = Database::ejecutar(
            "INSERT INTO firma_certificados
             (usuario_id, serie, sujeto, huella_sha256, pfx_path, secreto_envuelto, valido_desde, valido_hasta)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [(int) $usuario['id'], $emitido['serial'], $emitido['subject'], $emitido['fingerprint_sha256'],
             $emitido['pfx_path'], $emitido['wrapped_secret'], $desde, $hasta]);
        return Database::una('SELECT * FROM firma_certificados WHERE id = ?', [$id])
            ?? throw new \RuntimeException('No fue posible registrar el certificado individual.');
    }

    /** Avanza la ruta tras la respuesta de un firmante. */
    private function avanzarRuta(array $s): void
    {
        if ($s['modo'] === 'secuencial') {
            $pendientesPaso = (int) Database::valor(
                "SELECT COUNT(*) FROM firma_firmantes WHERE solicitud_id = ? AND orden = ? AND estado = 'pendiente'",
                [$s['id'], $s['paso_actual']]);
            if ($pendientesPaso > 0) {
                return;
            }
            $siguiente = Database::valor(
                'SELECT MIN(orden) FROM firma_firmantes WHERE solicitud_id = ? AND orden > ?',
                [$s['id'], $s['paso_actual']]);
            if ($siguiente !== null && $siguiente !== false) {
                Database::ejecutar('UPDATE firma_solicitudes SET paso_actual = ? WHERE id = ?',
                    [(int) $siguiente, $s['id']]);
                Database::ejecutar(
                    "INSERT INTO firma_eventos (solicitud_id, usuario_id, tipo, contenido) VALUES (?, ?, 'avance_paso', ?)",
                    [$s['id'], Auth::id(), 'La solicitud avanzó al paso ' . (int) $siguiente . '.']);
            } else {
                Database::ejecutar("UPDATE firma_solicitudes SET estado = 'completada' WHERE id = ?", [$s['id']]);
            }
            return;
        }

        // Paralela: cierra cuando el último responde
        $pendientes = (int) Database::valor(
            "SELECT COUNT(*) FROM firma_firmantes WHERE solicitud_id = ? AND estado = 'pendiente'", [$s['id']]);
        if ($pendientes === 0) {
            $rechazos = (int) Database::valor(
                "SELECT COUNT(*) FROM firma_firmantes WHERE solicitud_id = ? AND estado = 'rechazado'", [$s['id']]);
            Database::ejecutar('UPDATE firma_solicitudes SET estado = ? WHERE id = ?',
                [$rechazos > 0 ? 'rechazada' : 'completada', $s['id']]);
        }
    }

    /** Regenera la hoja de firmas (PDF de respaldo, solo FPDF). */
    private function regenerarHoja(int $solicitudId): void
    {
        $s = Database::una('SELECT * FROM firma_solicitudes WHERE id = ?', [$solicitudId]);
        $storage = rtrim(App::config('archivos')['storage'], '/\\');

        $documentos = array_map(function (array $d) use ($storage): array {
            $vigente = $storage . '/' . ($d['archivo_firmado'] ?: $d['archivo']);
            $d['sha256'] = is_file($vigente) ? (hash_file('sha256', $vigente) ?: '') : '';
            return $d;
        }, Database::todas(
            "SELECT * FROM firma_documentos WHERE solicitud_id = ? AND tipo <> 'hoja_firmas' ORDER BY id",
            [$solicitudId]));

        $firmantes = array_map(function (array $f) use ($storage): array {
            $f['imagen'] = $f['imagen'] ? $storage . '/' . $f['imagen'] : null;
            return $f;
        }, Database::todas(
            'SELECT f.*, u.nombre, u.email, uf.imagen
               FROM firma_firmantes f
               JOIN usuarios u ON u.id = f.usuario_id
               LEFT JOIN usuario_firmas uf ON uf.usuario_id = f.usuario_id
              WHERE f.solicitud_id = ? ORDER BY f.orden, u.nombre',
            [$solicitudId]));

        if (!is_dir($storage . '/firmas/hojas')) {
            mkdir($storage . '/firmas/hojas', 0755, true);
        }
        $rel = 'firmas/hojas/hoja' . $solicitudId . '.pdf';
        PdfFirmador::hojaFirmas($s, $documentos, $firmantes, $storage . '/' . $rel);

        $hoja = Database::una(
            "SELECT id FROM firma_documentos WHERE solicitud_id = ? AND tipo = 'hoja_firmas'", [$solicitudId]);
        if (!$hoja) {
            Database::ejecutar(
                "INSERT INTO firma_documentos (solicitud_id, usuario_id, tipo, nombre, archivo, estampable)
                 VALUES (?, ?, 'hoja_firmas', 'Hoja de firmas', ?, 0)",
                [$solicitudId, Auth::id(), $rel]);
        }
    }

    /** Firmantes desde el formulario según el modo. [lista, error] */
    private function resolverFirmantes(string $modo): array
    {
        $firmantes = [];
        if ($modo === 'secuencial') {
            $orden = 0;
            for ($i = 1; $i <= 4; $i++) {
                $texto = $this->post('paso' . $i);
                if ($texto === '') {
                    continue;
                }
                $u = $this->resolverUsuario($texto);
                if (!$u) {
                    return [[], "No se encontró un usuario único para «{$texto}»; elígelo del listado."];
                }
                $firmantes[] = ['id' => (int) $u['id'], 'orden' => ++$orden];
            }
            if (count($firmantes) < 1) {
                return [[], 'Indica al menos un firmante en la ruta.'];
            }
        } else {
            $grupoId = (int) $this->post('grupo_id');
            if ($grupoId) {
                $legacy=Database::una('SELECT tipo,creador_id FROM firma_grupos WHERE id=?',[$grupoId]);
                if(!$legacy || ($legacy['tipo']==='personal' && (int)$legacy['creador_id']!==Auth::id() && !Auth::tieneRol('admin'))) return [[], 'Ese grupo de firmantes no está disponible para tu cuenta.'];
                foreach (Database::todas(
                    'SELECT usuario_id FROM firma_grupo_miembros WHERE grupo_id = ?', [$grupoId]) as $m) {
                    $firmantes[] = ['id' => (int) $m['usuario_id'], 'orden' => 1];
                }
            }
            try { foreach(\App\Core\RecipientGroups::recipients('grupos_firma') as $person) $firmantes[]=['id'=>$person,'orden'=>1]; }
            catch(\InvalidArgumentException $ex) { return [[], $ex->getMessage()]; }
            for ($i = 1; $i <= 10; $i++) {
                $texto = $this->post('firmante' . $i);
                if ($texto === '') {
                    continue;
                }
                $u = $this->resolverUsuario($texto);
                if (!$u) {
                    return [[], "No se encontró un usuario único para «{$texto}»; elígelo del listado."];
                }
                $firmantes[] = ['id' => (int) $u['id'], 'orden' => 1];
            }
            if (!$firmantes) {
                return [[], 'Elige un grupo o escribe al menos un firmante.'];
            }
        }

        // Deduplicar conservando el primer orden asignado
        $vistos = [];
        $unicos = [];
        foreach ($firmantes as $f) {
            if (!isset($vistos[$f['id']])) {
                $vistos[$f['id']] = true;
                $unicos[] = $f;
            } elseif ($modo === 'secuencial') {
                return [[], 'Un mismo firmante no puede aparecer dos veces en la ruta.'];
            }
        }
        return [$unicos, null];
    }

    /** Resuelve "Nombre — email" (datalist) a un usuario activo único. */
    private function resolverUsuario(string $texto): ?array
    {
        if (preg_match('/[a-z0-9._%+-]+@[a-z0-9.-]+/i', $texto, $m)) {
            return Database::una('SELECT id, nombre FROM usuarios WHERE email = ? AND activo = 1', [$m[0]]);
        }
        $coincidencias = Database::todas(
            'SELECT id, nombre FROM usuarios WHERE nombre LIKE ? AND activo = 1 LIMIT 2',
            ['%' . $texto . '%']);
        return count($coincidencias) === 1 ? $coincidencias[0] : null;
    }

    private function usuariosActivos(): array
    {
        return Database::todas(
            'SELECT u.nombre, u.email FROM usuarios u WHERE u.activo = 1 ORDER BY u.nombre');
    }

    /** Mi fila de firmante si me toca actuar; corta con error si no. */
    private function firmantePendiente(array $s): array
    {
        $mio = Database::una(
            "SELECT * FROM firma_firmantes WHERE solicitud_id = ? AND usuario_id = ? AND estado = 'pendiente'",
            [$s['id'], Auth::id()]);
        $meToca = $mio && $s['estado'] === 'en_ruta'
                  && ($s['modo'] === 'paralela' || (int) $mio['orden'] === (int) $s['paso_actual']);
        if (!$meToca) {
            flash('error', 'No tienes una firma pendiente en este paso de la solicitud.');
            redirigir('firmas/' . $s['id']);
        }
        return $mio;
    }

    /** Solo el creador, los firmantes o admin ven la solicitud. */
    private function obtenerPropia(int $id): array
    {
        $s = Database::una(
            'SELECT s.*, u.nombre AS creador FROM firma_solicitudes s
               JOIN usuarios u ON u.id = s.creador_id WHERE s.id = ?', [$id]);
        $esParte = $s && ((int) $s['creador_id'] === Auth::id()
            || Auth::tieneRol('admin')
            || Database::una('SELECT 1 FROM firma_firmantes WHERE solicitud_id = ? AND usuario_id = ?',
                [$id, Auth::id()]));
        if (!$esParte) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No encontrado']);
            exit;
        }
        return $s;
    }

    private function exigirCreador(): void
    {
        if (!Auth::tieneRol(...self::ROLES_CREADORES)) {
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }
}
