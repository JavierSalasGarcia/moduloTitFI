<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\View;
use App\Modules\Perfil\FirmaUaemex;
use App\Modules\Posgrado\Services\Firmas;
use RuntimeException;
use Throwable;

/**
 * Firma en línea de los documentos del trámite de evaluación de grado.
 *
 * - Por enlace (público, sin cuenta): /posgrado/firma/t/{token}. El token (256 bits) solo vive en el correo; en la base
 *   está su SHA-256. La página muestra únicamente lo necesario de ESE documento. Los POST llevan el token CSRF de la
 *   sesión anónima que abre la página (el Router lo exige en todo POST, igual que en los enlaces de Titulación).
 * - Con sesión del mismo correo: /posgrado/firma/mia/{id} y la lista «Posgrado: firmas».
 * - La Coordinación de Estudios Avanzados firma con su sesión en /posgrado/firmas.
 */
final class FirmaController extends PosgradoBase
{
    // ------------------------------------------------------------------ resolución

    private function desdeToken(string $token): array
    {
        // Con el módulo sin publicar los enlaces no operan, aunque haya sesión de administración.
        if (!PosgradoAccess::publicadoGlobal()) {
            $this->noEncontrado();
        }
        $this->privado();
        $f = Firmas::porToken($token);
        if ($f === null) {
            Audit::record('posgrado::token', 'denegado', 'Enlace de firma no válido, vencido o cancelado');
            if (AccessControl::ready() && AccessControl::limited('pos_token_fallo:' . ($_SERVER['REMOTE_ADDR'] ?? ''), 30, 900)) {
                http_response_code(429);
                header('Retry-After: 900');
                exit('Demasiados intentos. Intenta de nuevo en unos minutos.');
            }
            http_response_code(404);
            View::render('posgrado:firma_enlace_invalido', ['titulo' => 'Enlace no disponible', 'seccion' => 'Posgrado'], 'publico');
            exit;
        }
        $this->siEnPausa((int) $f['tramite_id']);
        return $f;
    }

    /** Enlaces de un trámite que el egresado dejó en pausa: no permiten firmar ni descargar hasta que lo retome. */
    private function siEnPausa(int $tramiteId): void
    {
        $t = \App\Core\Database::una('SELECT folio, estado FROM pos_tramites WHERE id = ?', [$tramiteId]);
        if ($t === null || $t['estado'] !== 'abandonado') {
            return;
        }
        Audit::record('posgrado::enlace_en_pausa', 'denegado', 'Trámite ' . $tramiteId);
        View::render('posgrado:firma_en_pausa', ['titulo' => 'Trámite en pausa', 'seccion' => 'Posgrado', 'folio' => $t['folio']], Auth::usuario() ? 'app' : 'publico');
        exit;
    }

    /** Firma pedida al correo de la sesión (por id, sin token). */
    private function propia(string $id): array
    {
        $this->privado();
        $f = ctype_digit($id) && strlen($id) < 19 ? Firmas::una((int) $id) : null;
        if ($f === null || $f['estado'] === 'cancelada' || $f['enviada_en'] === null || !in_array(strtolower((string) $f['email']), PosgradoAccess::correosSesion(), true)) {
            $this->noEncontrado();
        }
        $this->siEnPausa((int) $f['tramite_id']);
        return $f;
    }

    private function esDeLaSesion(array $f): bool
    {
        return Auth::usuario() !== null && in_array(strtolower((string) $f['email']), PosgradoAccess::correosSesion(), true);
    }

    // ------------------------------------------------------------------ página de firma

    private function mostrar(array $f, string $base, string $layout): void
    {
        $t = PosgradoRepository::obtener((int) $f['tramite_id']);
        $g = PosgradoRepository::generado((int) $f['generado_id']);
        if (!$t || !$g || ((int) $g['vigente'] !== 1 && $f['estado'] !== 'firmada')) {
            $this->noEncontrado();
        }
        $hitos = PosgradoRepository::hitos((int) $t['id']);
        $i = $t['datos']['interesado'] ?? [];
        $respuestas = json_decode((string) ($f['respuestas'] ?? ''), true) ?: [];
        $fechaExamen = Firmas::fechaExamen($t, $hitos);
        $sesion = $this->esDeLaSesion($f) ? Auth::id() : null;
        $vigencia = $f['documento'] === 'visto_bueno' ? Firmas::vigenciaConocida($f, $sesion) : null;
        $this->vista('firma', [
            'titulo' => Firmas::nombreDocumento((string) $f['documento']) . ' · ' . $t['folio'],
            'seccion' => 'Posgrado',
            'f' => $f, 'base' => $base,
            'documento' => Firmas::nombreDocumento((string) $f['documento']),
            'que' => Catalogo::firmables()[$f['documento']]['que'] ?? 'el documento',
            'comoQuien' => Firmas::etiquetaRol($f),
            'alumno' => trim((string) ($i['nombre'] ?? $t['alumno_nombre'])),
            'programa' => trim((string) ($i['programa'] ?? '')),
            'tituloTrabajo' => trim((string) ($i['titulo'] ?? $t['titulo'] ?? '')),
            'folio' => (string) $t['folio'],
            'abierto' => $t['estado'] === 'abierto' && (int) $g['vigente'] === 1,
            'respuestas' => $respuestas,
            'vigencia' => $vigencia,
            'fechaExamen' => $fechaExamen,
            'requerida' => FirmaUaemex::fechaValida($fechaExamen) ? FirmaUaemex::masUnMes($fechaExamen) : '',
            'alcanza' => $f['documento'] !== 'visto_bueno' || Firmas::vigenciaAlcanza($respuestas['vigencia'] ?? null, $fechaExamen),
            'conOficio' => $f['documento'] === 'acuse' && $f['rol'] === 'sinodal',
        ], $layout);
    }

    private function entregarPdf(array $f): never
    {
        $g = PosgradoRepository::generado((int) $f['generado_id']);
        if (!$g) {
            $this->noEncontrado();
        }
        Audit::record('posgrado::firma_pdf', 'atendido', 'Firma ' . (int) $f['id']);
        $this->enviarArchivo((string) $g['ruta'], $f['documento'] . '.pdf', true);
    }

    private function entregarOficio(array $f): never
    {
        $bytes = null;
        try {
            $bytes = Firmas::paginaInvitacion($f);
        } catch (Throwable $e) {
            error_log('Posgrado: no se extrajo la invitación: ' . $e->getMessage());
        }
        if ($bytes === null) {
            $this->noEncontrado();
        }
        Audit::record('posgrado::firma_oficio', 'atendido', 'Firma ' . (int) $f['id']);
        $this->enviarBytes($bytes, 'Oficio_de_invitacion.pdf', 'application/pdf', true);
    }

    private function firmarComo(array $f, string $volver): never
    {
        if ($this->post('leido') !== '1') {
            flash('error', 'Marca «He leído el documento» para firmar.');
            redirigir($volver);
        }
        $respuestas = null;
        if ($f['documento'] === 'visto_bueno') {
            $respuestas = ['vigencia' => trim($this->post('vigencia'))];
            foreach (array_keys(Catalogo::preguntasVistoBueno()) as $k) {
                $respuestas[$k] = $this->post('r_' . $k);
            }
        }
        try {
            Firmas::firmar($f, ['usuario_id' => $this->esDeLaSesion($f) ? Auth::id() : null, 'respuestas' => $respuestas]);
            $f = Firmas::una((int) $f['id']) ?? $f;
            $t = PosgradoRepository::obtener((int) $f['tramite_id']);
            $fecha = $t ? Firmas::fechaExamen($t, PosgradoRepository::hitos((int) $t['id'])) : '';
            if ($respuestas !== null && $fecha !== '' && !Firmas::vigenciaAlcanza($respuestas['vigencia'], $fecha)) {
                flash('error', 'Registramos tu visto bueno, pero la vigencia de tu firma electrónica UAEMéx no alcanza: renuévala en https://fise.uaemex.mx/ y vuelve a este enlace para actualizar la fecha.');
            } else {
                flash('exito', $f['documento'] === 'acuse' && $f['rol'] === 'sinodal' ? 'Acusaste de recibido tu invitación. ¡Gracias!' : 'Documento firmado. ¡Gracias!');
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Posgrado: error al firmar: ' . $e->getMessage());
            flash('error', 'No se pudo firmar. Intenta de nuevo.');
        }
        redirigir($volver);
    }

    private function vigenciaComo(array $f, string $volver): never
    {
        try {
            Firmas::corregirVigencia($f, trim($this->post('vigencia')), $this->esDeLaSesion($f) ? Auth::id() : null);
            flash('exito', 'Actualizamos la vigencia de tu firma electrónica UAEMéx.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir($volver);
    }

    // ------------------------------------------------------------------ por enlace

    public function ver(string $token): void
    {
        $this->mostrar($this->desdeToken($token), 'posgrado/firma/t/' . $token, 'publico');
    }

    public function pdf(string $token): void
    {
        $this->entregarPdf($this->desdeToken($token));
    }

    public function oficio(string $token): void
    {
        $this->entregarOficio($this->desdeToken($token));
    }

    public function firmar(string $token): void
    {
        $this->firmarComo($this->desdeToken($token), 'posgrado/firma/t/' . $token);
    }

    public function vigencia(string $token): void
    {
        $this->vigenciaComo($this->desdeToken($token), 'posgrado/firma/t/' . $token);
    }

    // ------------------------------------------------------------------ con sesión (mismo correo)

    public function verPropia(string $id): void
    {
        $this->mostrar($this->propia($id), 'posgrado/firma/mia/' . (int) $id, 'app');
    }

    public function pdfPropia(string $id): void
    {
        $this->entregarPdf($this->propia($id));
    }

    public function oficioPropia(string $id): void
    {
        $this->entregarOficio($this->propia($id));
    }

    public function firmarPropia(string $id): void
    {
        $this->firmarComo($this->propia($id), 'posgrado/firma/mia/' . (int) $id);
    }

    public function vigenciaPropia(string $id): void
    {
        $this->vigenciaComo($this->propia($id), 'posgrado/firma/mia/' . (int) $id);
    }

    // ------------------------------------------------------------------ «Posgrado: firmas»

    public function index(): void
    {
        $this->privado();
        if (!PosgradoAccess::tieneFirmas()) {
            $this->noEncontrado();
        }
        $coord = PosgradoAccess::puedeFirmarCoordinacion();
        $ve = $coord || PosgradoAccess::esSupervision() || PosgradoAccess::esPersonal();
        $this->vista('firmas', [
            'titulo' => 'Posgrado: firmas',
            'propias' => Firmas::deCorreos(PosgradoAccess::correosSesion(), false),
            'coord' => $coord,
            'veCoordinacion' => $ve,
            'pendientes' => $ve ? Firmas::pendientesCoordinacion() : [],
            'firmadas' => $ve ? Firmas::firmadasCoordinacion() : [],
            'firmante' => Configuracion::firmante(),
            'cargo' => Configuracion::cargo(),
        ]);
    }

    /** PDF de un documento en firma de la Coordinación (para revisarlo antes de firmar). */
    public function pdfCoordinacion(string $id): void
    {
        $this->privado();
        if (!PosgradoAccess::puedeFirmarCoordinacion() && !PosgradoAccess::esPersonal()) {
            $this->noEncontrado();
        }
        $f = ctype_digit($id) && strlen($id) < 19 ? Firmas::una((int) $id) : null;
        if ($f === null || $f['rol'] !== 'coordinacion' || $f['estado'] === 'cancelada') {
            $this->noEncontrado();
        }
        $this->entregarPdf($f);
    }

    /** La Coordinación firma uno o varios documentos. */
    public function firmarCoordinacion(): void
    {
        if (!PosgradoAccess::puedeFirmarCoordinacion()) {
            $this->prohibido('Firma por la Coordinación de Estudios Avanzados');
        }
        $ids = array_values(array_unique(array_filter(array_map('strval', (array) ($_POST['ids'] ?? [])), static fn(string $v): bool => ctype_digit($v) && strlen($v) < 19)));
        if (!$ids) {
            flash('error', 'Elige al menos un documento.');
            redirigir('posgrado/firmas');
        }
        if ($this->post('leido') !== '1') {
            flash('error', 'Marca «Revisé los documentos seleccionados» para firmar.');
            redirigir('posgrado/firmas');
        }
        $ok = 0;
        $errores = [];
        foreach (array_slice($ids, 0, 50) as $id) {
            $f = Firmas::una((int) $id);
            if ($f === null || $f['rol'] !== 'coordinacion' || $f['estado'] !== 'pendiente') {
                continue;
            }
            try {
                Firmas::firmar($f, ['usuario_id' => Auth::id()]);
                $ok++;
                $t = PosgradoRepository::obtener((int) $f['tramite_id']);
                if ($t) {
                    $this->sincronizar((int) $t['id']);
                }
            } catch (RuntimeException $e) {
                $errores[] = $e->getMessage();
            } catch (Throwable $e) {
                error_log('Posgrado: error al firmar por la Coordinación: ' . $e->getMessage());
                $errores[] = 'No se pudo firmar un documento.';
            }
        }
        flash($errores && !$ok ? 'error' : 'exito', ($ok === 1 ? 'Firmaste 1 documento.' : 'Firmaste ' . $ok . ' documentos.') . ($errores ? ' ' . implode(' ', array_unique($errores)) : ''));
        redirigir('posgrado/firmas');
    }

    /** Proveedor `por_firmar` de la bandeja única (docs/FIRMAS_UNICA.md): lo que espera mi firma en Posgrado. */
    public static function porFirmar(): array
    {
        return array_values(array_filter(self::pendientesDeSesion(), static fn(array $i): bool => in_array($i['tipo'], ['Firma', 'Firmas'], true)));
    }

    /** Proveedor «pendientes» del módulo (Notificaciones y la insignia del menú). */
    public static function pendientesDeSesion(): array
    {
        if (!Auth::usuario() || !PosgradoAccess::publicado()) {
            return [];
        }
        $items = [];
        foreach (Firmas::deCorreos(PosgradoAccess::correosSesion()) as $f) {
            $items[] = ['tipo' => 'Firma', 'texto' => ($f['documento'] === 'acuse' ? 'Acusar de recibido tu invitación al sínodo de ' : 'Firmar ' . (Catalogo::firmables()[$f['documento']]['que'] ?? 'un documento') . ' de ') . $f['alumno_nombre'],
                'enlace' => 'posgrado/firma/mia/' . (int) $f['id'], 'fecha' => $f['enviada_en']];
        }
        if (PosgradoAccess::puedeFirmarCoordinacion() && ($n = count(Firmas::pendientesCoordinacion())) > 0) {
            $items[] = ['tipo' => 'Firmas', 'texto' => $n === 1 ? '1 documento de posgrado espera la firma de la Coordinación' : $n . ' documentos de posgrado esperan la firma de la Coordinación', 'enlace' => 'posgrado/firmas', 'fecha' => null];
        }
        return $items;
    }
}
