<?php
declare(strict_types=1);

namespace App\Modules\Oficios;

use App\Core\AccessControl;
use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Modules\Oficios\Services\Cms;
use App\Modules\Oficios\Services\Destinatarios;
use App\Modules\Oficios\Services\OficiosSub;
use App\Modules\Oficios\Services\Pades;
use RuntimeException;

/**
 * Oficios de la Subdirección Académica: la asistente (o el titular) los redacta con destinatarios reutilizables y un lienzo de negritas y
 * cursivas, los envía a firma (aquí se reserva el número del contador único) y el titular los firma con su e.firma del SAT, que se procesa
 * únicamente en su navegador. Verificación pública por QR y kit de confianza del SAT.
 */
final class SubdireccionController extends Controller
{
    // ------------------------------------------------------------------ permisos y utilidades

    private function redactar(): void
    {
        if (!OficiosAccess::puedeRedactar()) {
            Audit::record('oficios_sub', 'denegado');
            http_response_code(404);
            exit('No disponible.');
        }
    }

    private function titular(): void
    {
        if (!OficiosAccess::puedeFirmar()) {
            Audit::record('oficios_sub_firma', 'denegado');
            http_response_code(404);
            exit('No disponible.');
        }
    }

    private function oficio(string $id): array
    {
        $o = ctype_digit($id) ? OficiosSub::obtener((int) $id) : null;
        if (!$o) {
            http_response_code(404);
            exit('Oficio no encontrado.');
        }
        return $o;
    }

    private function json(array $datos, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }

    private static function carpeta(): string
    {
        $d = rtrim((string) App::config('archivos')['storage'], '/\\') . '/oficios_sub';
        if (!is_dir($d) && !mkdir($d, 0750, true) && !is_dir($d)) {
            throw new RuntimeException('No se pudo crear la carpeta de oficios.');
        }
        return $d;
    }

    // ------------------------------------------------------------------ lista, captura, vista

    public function index(): void
    {
        $this->redactar();
        header('Cache-Control: no-store, private');
        $estado = (string) ($_GET['estado'] ?? '');
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $this->vista('sub_lista', ['titulo' => 'Oficios de la Subdirección', 'filas' => OficiosSub::lista($estado, $q), 'estado' => $estado, 'q' => $q,
            'puedeFirmar' => OficiosAccess::puedeFirmar(), 'siguiente' => \App\Modules\Titulacion\Services\NumeroOficio::siguiente(OficiosSub::SERIE)]);
    }

    public function nuevo(): void
    {
        $this->redactar();
        $this->vista('sub_form', ['titulo' => 'Nuevo oficio', 'o' => null, 'dest' => []]);
    }

    public function crear(): void
    {
        $this->redactar();
        try {
            $id = OficiosSub::guardar(null, $this->post('asunto'), (string) ($_POST['cuerpo_html'] ?? ''), (array) ($_POST['destinatarios'] ?? []));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('oficios/subdireccion/nuevo');
        }
        Audit::record('oficios_sub', 'correcto', 'Crea oficio #' . $id);
        flash('exito', 'Borrador guardado. Revisa la vista previa y envíalo a firma cuando esté listo.');
        redirigir('oficios/subdireccion/' . $id);
    }

    public function editar(string $id): void
    {
        $this->redactar();
        $o = $this->oficio($id);
        if (!in_array($o['estado'], ['borrador', 'en_firma'], true)) {
            flash('error', 'Este oficio ya no se puede modificar.');
            redirigir('oficios/subdireccion/' . $o['id']);
        }
        $this->vista('sub_form', ['titulo' => 'Editar oficio', 'o' => $o, 'dest' => Destinatarios::idsDe($o['dest'])]);
    }

    public function actualizar(string $id): void
    {
        $this->redactar();
        $o = $this->oficio($id);
        try {
            OficiosSub::guardar((int) $o['id'], $this->post('asunto'), (string) ($_POST['cuerpo_html'] ?? ''), (array) ($_POST['destinatarios'] ?? []));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('oficios/subdireccion/' . $o['id'] . '/editar');
        }
        Audit::record('oficios_sub', 'correcto', 'Edita oficio #' . $o['id']);
        $ahora = OficiosSub::obtener((int) $o['id']);
        flash('exito', 'Cambios guardados.');
        redirigir('oficios/subdireccion/' . $o['id'] . ($ahora && $ahora['estado'] === 'en_firma' && OficiosAccess::puedeFirmar() ? '/firmar' : ''));
    }

    public function ver(string $id): void
    {
        $this->redactar();
        header('Cache-Control: no-store, private');
        $o = $this->oficio($id);
        $this->vista('sub_ver', ['titulo' => $o['numero_texto'] ?: 'Oficio (borrador)', 'o' => $o, 'historial' => OficiosSub::historial((int) $o['id']),
            'puedeFirmar' => OficiosAccess::puedeFirmar()]);
    }

    /** PDF: el firmado si ya se firmó; si no, la vista previa. */
    public function pdf(string $id): void
    {
        $this->redactar();
        $o = $this->oficio($id);
        if ($o['estado'] === 'firmado' && $o['pdf_firmado']) {
            $f = self::carpeta() . '/' . basename((string) $o['pdf_firmado']);
            if (!is_file($f)) {
                http_response_code(404);
                exit('Archivo no encontrado.');
            }
            Audit::record('oficios_sub', 'correcto', 'Descarga oficio #' . $o['id']);
            $bytes = (string) file_get_contents($f);
        } else {
            $bytes = OficiosSub::pdf($o, true);
        }
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($o['numero_texto'] ?: 'oficio-' . $o['id'])) . ($o['estado'] === 'firmado' ? '' : '-vista-previa') . '.pdf';
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $nombre . '"');
        header('Content-Length: ' . strlen($bytes));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store, private');
        echo $bytes;
        exit;
    }

    // ------------------------------------------------------------------ destinatarios (JSON)

    public function buscarDestinatarios(): void
    {
        $this->redactar();
        $this->json(['destinatarios' => Destinatarios::buscar((string) ($_GET['q'] ?? ''))]);
    }

    public function crearDestinatario(): void
    {
        $this->redactar();
        try {
            $this->json(['ok' => true, 'destinatario' => Destinatarios::crear($this->post('nombre'), $this->post('cargo'), $this->post('dependencia'), ctype_digit($this->post('profesor_id')) && $this->post('profesor_id') !== '' ? (int) $this->post('profesor_id') : null)]);
        } catch (RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    // ------------------------------------------------------------------ flujo

    public function enviar(string $id): void
    {
        $this->redactar();
        $o = $this->oficio($id);
        try {
            $o = OficiosSub::enviarAFirma((int) $o['id']);
            Audit::record('oficios_sub', 'correcto', 'Envía a firma oficio #' . $o['id'] . ' · ' . $o['numero_texto']);
            flash('exito', 'Oficio ' . $o['numero_texto'] . ' enviado a firma del titular.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('oficios/subdireccion/' . $o['id']);
    }

    /** Envía a firma varios borradores a la vez (cada uno recibe el siguiente número consecutivo, en orden de creación). */
    public function enviarLote(): void
    {
        $this->redactar();
        $ids = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['ids'] ?? [])), static fn($i) => $i > 0)));
        sort($ids);
        $ok = 0;
        $fallas = 0;
        foreach (array_slice($ids, 0, 500) as $id) {
            try {
                OficiosSub::enviarAFirma($id);
                $ok++;
            } catch (RuntimeException) {
                $fallas++;
            }
        }
        Audit::record('oficios_sub', 'correcto', 'Envía a firma en lote: ' . $ok . ' oficio(s)');
        flash($ok > 0 ? 'exito' : 'error', $ok . ' oficio(s) enviado(s) a firma' . ($fallas ? '; ' . $fallas . ' no se pudieron enviar (¿ya no eran borrador?).' : '.'));
        redirigir('oficios/subdireccion' . ($ok > 0 ? '?estado=en_firma' : ''));
    }

    public function devolver(string $id): void
    {
        $this->titular();
        $o = $this->oficio($id);
        $nota = trim($this->post('observacion'));
        if (mb_strlen($nota) < 5) {
            flash('error', 'Escribe qué se debe corregir.');
            redirigir('oficios/subdireccion/' . $o['id'] . '/firmar');
        }
        try {
            OficiosSub::devolver((int) $o['id'], $nota);
            Audit::record('oficios_sub', 'correcto', 'Devuelve oficio #' . $o['id']);
            flash('exito', 'Oficio devuelto a quien lo redactó, con tu observación.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('oficios/subdireccion');
    }

    public function cancelar(string $id): void
    {
        $this->redactar();
        $o = $this->oficio($id);
        try {
            OficiosSub::cancelar((int) $o['id'], $this->post('motivo') ?: 'Cancelado');
            Audit::record('oficios_sub', 'correcto', 'Cancela oficio #' . $o['id'] . ($o['numero_texto'] ? ' · ' . $o['numero_texto'] : ''));
            flash('exito', 'Oficio cancelado.' . ($o['numero_texto'] ? ' Su número queda registrado como cancelado.' : ''));
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('oficios/subdireccion');
    }

    // ------------------------------------------------------------------ firma con e.firma (el navegador firma; aquí solo se prepara y se verifica)

    public function firmar(string $id): void
    {
        $this->titular();
        header('Cache-Control: no-store, private');
        $o = $this->oficio($id);
        if ($o['estado'] !== 'en_firma') {
            flash('error', 'Este oficio no está esperando tu firma.');
            redirigir('oficios/subdireccion/' . $o['id']);
        }
        $this->vista('sub_firmar', ['titulo' => 'Firmar ' . $o['numero_texto'], 'o' => $o, 'rfcRegistrado' => OficiosSub::rfcTitular() !== '',
            'certificadosSat' => count(OficiosSub::certificadosPem())]);
    }

    /** Cola de oficios por firmar: se eligen todos o algunos y se firman con una sola apertura de la e.firma. */
    public function firmarLote(): void
    {
        $this->titular();
        header('Cache-Control: no-store, private');
        $this->vista('sub_firmar_lote', ['titulo' => 'Firmar oficios en lote', 'cola' => OficiosSub::enFirma(), 'rfcRegistrado' => OficiosSub::rfcTitular() !== '',
            'certificadosSat' => count(OficiosSub::certificadosPem())]);
    }

    /** Recibe SOLO el certificado público (.cer) y devuelve el PDF preparado para que el navegador lo firme. */
    public function firmaPreparar(string $id): void
    {
        $this->titular();
        $o = $this->oficio($id);
        // En lote (hasta cientos de oficios con una sola e.firma) el límite es mayor; sigue acotado por hora.
        $lote = ($_POST['lote'] ?? '') === '1';
        if ($lote ? AccessControl::limited('ofs_firma_lote:' . (int) Auth::id(), 1500, 3600) : AccessControl::limited('ofs_firma:' . (int) Auth::id(), 20, 300)) {
            $this->json(['ok' => false, 'error' => 'Demasiados intentos. Espera unos minutos.'], 429);
        }
        try {
            if ($o['estado'] !== 'en_firma') {
                throw new RuntimeException('Este oficio no está esperando tu firma.');
            }
            $der = base64_decode((string) ($_POST['certificado'] ?? ''), true);
            if ($der === false || $der === '') {
                throw new RuntimeException('No se recibió el certificado.');
            }
            $cert = Cms::certificado($der);
            $this->validarCertificado($cert);
            $ahora = time();
            $sello = [
                'Firmado electrónicamente con e.firma (SAT)',
                'Certificado No. ' . $cert['serie'] . ' · ' . date('d/m/Y H:i', $ahora) . ' h',
                'Verifique la firma con el código QR de esta hoja',
            ];
            $pdf = OficiosSub::pdf($o, false, $sello);
            $firmante = OficiosSub::firmante();
            $p = Pades::preparar($pdf, $firmante['nombre'], 'Firma del oficio ' . $o['numero_texto'], $ahora);
            $ruta = 'prep-' . (int) $o['id'] . '-' . bin2hex(random_bytes(6)) . '.pdf';
            file_put_contents(self::carpeta() . '/' . $ruta, $p['pdf']);
            Database::ejecutar(
                'UPDATE ofs_oficios SET pdf_preparado = ?, cert_serie = ?, cert_nombre = ?, cert_emisor = ?, cert_vigencia = ? WHERE id = ?',
                [$ruta, $cert['serie'], mb_substr($cert['nombre'], 0, 190), mb_substr($cert['emisor'], 0, 255), date('Y-m-d', $cert['desde']) . ' a ' . date('Y-m-d', $cert['hasta']), (int) $o['id']]
            );
            [$a, $b, $c, $d] = Pades::rango($p['pdf']);
            $this->json(['ok' => true, 'pdf' => base64_encode($p['pdf']), 'rango' => [$a, $b, $c, $d],
                'resumen' => ['nombre' => $cert['nombre'], 'serie' => $cert['serie'], 'emisor' => $cert['emisor'], 'vigencia' => date('d/m/Y', $cert['desde']) . ' al ' . date('d/m/Y', $cert['hasta'])]]);
        } catch (RuntimeException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Recibe la firma (CMS en hexadecimal), la verifica contra el PDF que se preparó y guarda el oficio firmado. */
    public function firmaGuardar(string $id): void
    {
        $this->titular();
        $o = $this->oficio($id);
        try {
            if ($o['estado'] !== 'en_firma' || !$o['pdf_preparado']) {
                throw new RuntimeException('Primero prepara la firma del oficio.');
            }
            $f = self::carpeta() . '/' . basename((string) $o['pdf_preparado']);
            if (!is_file($f)) {
                throw new RuntimeException('El PDF preparado ya no existe; vuelve a firmar.');
            }
            $final = Pades::incrustar((string) file_get_contents($f), (string) ($_POST['firma'] ?? ''));
            $v = Cms::verificar(Pades::firmaDe($final), Pades::contenido($final));
            if (!$v['ok']) {
                throw new RuntimeException('La firma no es válida: ' . $v['error']);
            }
            $cert = Cms::certificado((string) $v['certificado']);
            if ($cert['serie'] !== $o['cert_serie']) {
                throw new RuntimeException('La firma no corresponde al certificado con el que se preparó el oficio.');
            }
            $this->validarCertificado($cert);
            $cadena = Cms::cadena((string) $v['certificado'], OficiosSub::certificadosPem());
            $nombre = ($o['codigo'] ?: bin2hex(random_bytes(8))) . '.pdf';
            file_put_contents(self::carpeta() . '/' . $nombre, $final);
            @unlink($f);
            Database::ejecutar(
                "UPDATE ofs_oficios SET estado = 'firmado', firmado_por = ?, firmado_en = NOW(), pdf_firmado = ?, pdf_preparado = NULL, sha256 = ?, cadena_estado = ? WHERE id = ? AND estado = 'en_firma'",
                [(int) Auth::id(), $nombre, hash('sha256', $final), $cadena['detalle'], (int) $o['id']]
            );
            OficiosSub::anotar((int) $o['id'], 'firmado', 'Certificado No. ' . $cert['serie']);
            try {
                Destinatarios::indexar((int) $o['id'], (array) $o['dest']);   // queda en «Avisos y eventos → Oficios» de sus destinatarios
            } catch (\Throwable $e) {
                Audit::record('oficios_sub', 'error', 'No se pudo registrar a los destinatarios del oficio #' . $o['id'] . ': ' . $e->getMessage());
            }
            Audit::record('oficios_sub_firma', 'correcto', 'Firma oficio #' . $o['id'] . ' · ' . $o['numero_texto'] . ' · cert ' . $cert['serie']);
            $this->json(['ok' => true, 'cadena' => $cadena['detalle'], 'url' => url('oficios/subdireccion/' . $o['id'])]);
        } catch (RuntimeException $e) {
            Audit::record('oficios_sub_firma', 'error', 'Oficio #' . $o['id'] . ': ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    /** Reglas del certificado: vigente, para firmar, emitido por el SAT y del titular (por RFC, que nunca se muestra). */
    private function validarCertificado(array $c): void
    {
        $ahora = time();
        if ($c['desde'] > $ahora || $c['hasta'] < $ahora) {
            throw new RuntimeException('El certificado no está vigente.');
        }
        if (!$c['firma_digital']) {
            throw new RuntimeException('Este certificado no sirve para firmar documentos (¿es el de sellos digitales?).');
        }
        if (!$c['sat']) {
            throw new RuntimeException('El certificado no fue emitido por el SAT.');
        }
        $registrado = OficiosSub::rfcTitular();
        if ($registrado === '') {
            throw new RuntimeException('Falta registrar el RFC del titular en Oficios de la Subdirección → Configuración.');
        }
        if ($c['rfc'] === '' || !hash_equals($registrado, $c['rfc'])) {
            throw new RuntimeException('La e.firma no corresponde al titular de la Subdirección Académica.');
        }
    }

    /** Proveedor `por_firmar` de la bandeja única: los oficios que esperan la e.firma del titular. */
    public static function porFirmar(): array
    {
        if (!OficiosAccess::puedeFirmar()) {
            return [];
        }
        $r = [];
        foreach (OficiosSub::enFirma() as $o) {
            $r[] = ['tipo' => 'Oficio', 'texto' => 'Oficio ' . $o['numero_texto'] . ' · ' . $o['asunto'],
                'enlace' => 'oficios/subdireccion/' . (int) $o['id'] . '/firmar', 'fecha' => $o['enviado_en']];
        }
        return $r;
    }

    // ------------------------------------------------------------------ configuración

    public function configuracion(): void
    {
        if (!OficiosAccess::puedeConfigurar()) {
            http_response_code(404);
            exit('No disponible.');
        }
        header('Cache-Control: no-store, private');
        $this->vista('sub_config', ['titulo' => 'Configuración de los oficios', 'rfc' => OficiosSub::rfcTitular() !== '',
            'certificados' => Database::todas('SELECT id, nombre, huella, creado_en FROM ofs_confianza ORDER BY id')]);
    }

    public function guardarConfiguracion(): void
    {
        if (!OficiosAccess::puedeConfigurar()) {
            http_response_code(404);
            exit('No disponible.');
        }
        try {
            if (trim($this->post('rfc')) !== '') {
                OficiosSub::guardarRfcTitular($this->post('rfc'));
                flash('exito', 'RFC del titular registrado (cifrado; no se vuelve a mostrar).');
            }
            foreach (['certificado' => $_FILES['certificado'] ?? null] as $f) {
                if (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK && is_uploaded_file((string) $f['tmp_name'])) {
                    OficiosSub::agregarCertificado((string) file_get_contents((string) $f['tmp_name']), (string) $f['name']);
                    flash('exito', 'Certificado agregado al kit de confianza.');
                }
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('oficios/subdireccion/configuracion');
    }

    public function quitarCertificado(string $id): void
    {
        if (!OficiosAccess::puedeConfigurar() || !ctype_digit($id)) {
            http_response_code(404);
            exit('No disponible.');
        }
        Database::ejecutar('DELETE FROM ofs_confianza WHERE id = ?', [(int) $id]);
        Audit::record('oficios_config', 'correcto', 'Quita un certificado del kit de confianza');
        redirigir('oficios/subdireccion/configuracion');
    }

    // ------------------------------------------------------------------ páginas públicas

    public function verificar(string $codigo): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (AccessControl::limited('ofs_verificar|' . $ip, 60, 600)) {
            http_response_code(429);
            exit('Demasiadas consultas. Intenta de nuevo en unos minutos.');
        }
        header('Cache-Control: no-store, private');
        $codigo = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $codigo) ?? '');
        $o = OficiosSub::porCodigo($codigo);
        $comprobacion = null;
        if ($o && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && isset($_GET['sha'])) {
            $comprobacion = hash_equals((string) $o['sha256'], strtolower(preg_replace('/[^0-9a-f]/i', '', (string) $_GET['sha']) ?? ''));
        }
        if ($o === null) {
            http_response_code(404);
        }
        $this->vista('sub_verificar', ['titulo' => 'Verificación de oficios', 'o' => $o, 'codigo' => $codigo, 'comprobacion' => $comprobacion, 'firmante' => $o ? OficiosSub::firmante() : null],
            Auth::usuario() ? 'app' : 'publico');
    }

    public function confianza(): void
    {
        $this->vista('sub_confianza', ['titulo' => 'Kit de confianza del SAT', 'certificados' => Database::todas('SELECT id, nombre, huella FROM ofs_confianza ORDER BY id')],
            Auth::usuario() ? 'app' : 'publico');
    }

    /** Descarga un certificado del kit en formato .cer (DER). */
    public function descargarCertificado(string $id): void
    {
        $c = ctype_digit($id) ? Database::una('SELECT nombre, pem FROM ofs_confianza WHERE id = ?', [(int) $id]) : null;
        if (!$c) {
            http_response_code(404);
            exit('No encontrado.');
        }
        $der = base64_decode((string) preg_replace('/-----(BEGIN|END) CERTIFICATE-----|\s+/', '', (string) $c['pem']), true);
        header('Content-Type: application/pkix-cert');
        header('Content-Disposition: attachment; filename="' . (preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $c['nombre']) ?: 'certificado') . '.cer"');
        header('Content-Length: ' . strlen((string) $der));
        echo $der;
        exit;
    }
}
