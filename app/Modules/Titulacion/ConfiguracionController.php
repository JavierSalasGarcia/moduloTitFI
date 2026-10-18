<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Services\Formatos;
use App\Modules\Titulacion\Services\PdfFormato;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/** Configuración de formatos y requisitos documentales (solo administración). */
final class ConfiguracionController extends TitulacionBase
{
    private function admin(): void
    {
        if (!Auth::tieneRol('admin')) {
            $this->prohibido('Configuración de titulación');
        }
        $this->privado();
    }

    private function claveValida(string $clave): string
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,39}$/D', $clave)) {
            $this->noEncontrado();
        }
        return $clave;
    }

    private function storage(): string
    {
        return rtrim((string) App::config('archivos.storage'), '/\\');
    }

    // --------------------------------------------------------------------- inicio

    public function index(): void
    {
        $this->admin();
        $formatos = [];
        $uso = $this->usoDeFormatos();
        foreach (Catalogo::formatos() as $clave => $f) {
            $cfg = Configuracion::formatoCfg($clave);
            $r = $f['tipo'] === 'pdf' ? Configuracion::resolverPdf($clave) : null;
            $formatos[$clave] = $f + [
                'clave' => $clave,
                'modificado' => $cfg !== null && ($cfg['spec'] !== null || $cfg['plantilla_ruta'] !== null),
                'plantilla_nueva' => $cfg !== null && $cfg['plantilla_ruta'] !== null,
                'campos' => $r ? count($r['spec']['campos']) : null,
                'uso' => $uso[$clave] ?? [],
            ];
        }
        $this->vista('configuracion', [
            'titulo' => 'Titulación · Configurar formatos',
            'formatos' => $formatos,
            'etapas' => $this->etapasDisponibles(),
            'modalidades' => Catalogo::modalidades(),
            'bitacora' => Database::todas('SELECT b.*, u.nombre FROM tit_config_bitacora b JOIN usuarios u ON u.id = b.usuario_id ORDER BY b.id DESC LIMIT 15'),
        ]);
    }

    /** @return array<string,string> clave de etapa => título (la primera definición). */
    private function etapasDisponibles(): array
    {
        $r = [];
        foreach (array_keys(Catalogo::modalidades()) as $m) {
            foreach (Catalogo::etapasBase($m) as $e) {
                if ($e['tipo'] !== 'personal') {
                    $r[$e['clave']] ??= $e['titulo'];
                }
            }
        }
        return $r;
    }

    /** @return array<string,array<int,string>> formato => nombres de opciones donde se usa */
    private function usoDeFormatos(): array
    {
        $uso = [];
        foreach (Catalogo::modalidades() as $m => $def) {
            foreach (Catalogo::etapas($m, isset($def['variantes']) ? array_key_first($def['variantes']) : null) as $e) {
                $usados = $e['formatos'] ?? [];
                foreach ($e['documentos'] ?? [] as $d) {
                    if (!empty($d['formato'])) {
                        $usados[] = $d['formato'];
                    }
                }
                foreach (array_unique($usados) as $f) {
                    $uso[$f][$def['nombre']] = $def['nombre'];
                }
            }
        }
        // EGEL tiene un formato por convocatoria.
        foreach (['egel_50', 'egel_nacional'] as $f) {
            $uso[$f]['Examen General de Egreso (EGEL)'] = 'Examen General de Egreso (EGEL)';
        }
        return array_map('array_values', $uso);
    }

    // ------------------------------------------------------------------ documentos

    /** Documentos del ámbito con su definición base (sin configuración), para mostrar y guardar. */
    private function documentosDelAmbito(string $ambito): array
    {
        $lista = [];
        $modalidades = $ambito === '*' ? array_keys(Catalogo::modalidades()) : [$ambito];
        foreach ($modalidades as $m) {
            foreach (Catalogo::etapasBase($m) as $e) {
                foreach ($e['documentos'] ?? [] as $d) {
                    $lista[$d['clave']] ??= ['etapa' => $e['clave'], 'etapa_titulo' => $e['titulo'], 'base' => $d, 'en' => [], 'extra' => null];
                    $lista[$d['clave']]['en'][] = Catalogo::modalidad($m)['nombre'];
                }
            }
        }
        foreach (Configuracion::todosLosExtras() as $x) {
            if ($x['modalidad'] !== $ambito && !($x['modalidad'] === '*' && $ambito !== '*')) {
                continue;
            }
            $base = ['clave' => $x['clave'], 'nombre' => $x['nombre'], 'nota' => (string) $x['nota'], 'condicion' => $x['condicion'] ?: null,
                'formato' => $x['formato'] ?: null, 'activo' => true, 'fisico' => (int) $x['solo_fisico'] === 1, 'cotejo' => false, 'firma_cotejo' => 'sello'];
            $lista[$x['clave']] = ['etapa' => $x['etapa'], 'etapa_titulo' => $this->etapasDisponibles()[$x['etapa']] ?? $x['etapa'], 'base' => $base,
                'en' => [$x['modalidad'] === '*' ? 'Todas las opciones' : (Catalogo::modalidad($x['modalidad'])['nombre'] ?? $x['modalidad'])],
                'extra' => (int) $x['id'], 'solo_fisico' => (int) $x['solo_fisico'] === 1];
        }
        return $lista;
    }

    public function documentos(): void
    {
        $this->admin();
        $ambito = (string) ($_GET['modalidad'] ?? '*');
        if ($ambito !== '*' && Catalogo::modalidad($ambito) === null) {
            $ambito = '*';
        }
        $filas = [];
        foreach ($this->documentosDelAmbito($ambito) as $clave => $x) {
            $x['actual'] = Configuracion::documento($x['base'], $ambito);
            $filas[$x['etapa_titulo']][$clave] = $x;
        }
        $formatos = array_filter(Catalogo::formatos(), static fn(array $f): bool => $f['tipo'] === 'pdf' && $f['activo']);
        $this->vista('configuracion_documentos', [
            'titulo' => 'Titulación · Requisitos documentales',
            'ambito' => $ambito,
            'modalidades' => Catalogo::modalidades(),
            'filas' => $filas,
            'etapas' => $this->etapasDisponibles(),
            'formatos' => $formatos,
            'pki' => \App\Modules\Firmas\PadesSigner::habilitado(),
        ]);
    }

    public function guardarDocumentos(): void
    {
        $this->admin();
        $ambito = $this->post('modalidad', '*');
        if ($ambito !== '*' && Catalogo::modalidad($ambito) === null) {
            $this->noEncontrado();
        }
        $entrada = is_array($_POST['d'] ?? null) ? $_POST['d'] : [];
        $cambios = 0;
        foreach ($this->documentosDelAmbito($ambito) as $clave => $x) {
            $f = $entrada[$clave] ?? null;
            if (!is_array($f) || !isset($f['_'])) {
                continue;
            }
            $firma = ($f['firma_cotejo'] ?? 'sello') === 'pades' ? 'pades' : 'sello';
            Configuracion::guardarDocumento($ambito, $clave, [
                'activo' => !empty($f['activo']), 'fisico' => !empty($f['fisico']) || !empty($f['cotejo']),
                'cotejo' => !empty($f['cotejo']), 'firma_cotejo' => $firma,
                'nombre' => mb_substr((string) ($f['nombre'] ?? ''), 0, 250), 'nota' => mb_substr((string) ($f['nota'] ?? ''), 0, 500),
            ], $x['base'], Auth::id());
            $cambios++;
        }
        Configuracion::bitacora(Auth::id(), 'documentos', 'Requisitos guardados para ' . ($ambito === '*' ? 'todas las opciones' : $ambito) . ' (' . $cambios . ' documentos revisados)');
        Audit::record('titulacion::config', 'correcto', 'Documentos ' . $ambito);
        flash('exito', 'Requisitos documentales actualizados. Aplican desde ya a todos los trámites.');
        redirigir('titulacion/configuracion/documentos?modalidad=' . rawurlencode($ambito));
    }

    public function nuevoDocumento(): void
    {
        $this->admin();
        $ambito = $this->post('modalidad', '*');
        $etapa = $this->post('etapa');
        $nombre = mb_substr($this->post('nombre'), 0, 250);
        if (($ambito !== '*' && Catalogo::modalidad($ambito) === null) || !isset($this->etapasDisponibles()[$etapa]) || mb_strlen($nombre) < 3) {
            flash('error', 'Indica la etapa y un nombre de al menos 3 caracteres.');
            redirigir('titulacion/configuracion/documentos?modalidad=' . rawurlencode($ambito));
        }
        $formato = $this->post('formato');
        if ($formato !== '' && !isset(Catalogo::formatos()[$formato])) {
            $formato = '';
        }
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $nombre))) ?? '', '_');
        $clave = 'x_' . substr($slug, 0, 30) . '_' . bin2hex(random_bytes(2));
        $soloFisico = $this->post('solo_fisico') === '1' ? 1 : 0;
        $id = Database::ejecutar(
            'INSERT INTO tit_docs_extra (modalidad, etapa, clave, nombre, nota, condicion, formato, solo_fisico, creado_por) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$ambito, $etapa, $clave, $nombre, mb_substr($this->post('nota'), 0, 500) ?: null, mb_substr($this->post('condicion'), 0, 300) ?: null, $formato ?: null, $soloFisico, Auth::id()]
        );
        Configuracion::reiniciar();
        Configuracion::bitacora(Auth::id(), 'documento_nuevo', 'Documento añadido: ' . $nombre . ' (' . ($ambito === '*' ? 'todas' : $ambito) . ', etapa ' . $etapa . ')');
        flash('exito', 'Documento añadido. Ajusta abajo si requiere entrega física o cotejo.');
        redirigir('titulacion/configuracion/documentos?modalidad=' . rawurlencode($ambito) . '#fila-' . $id);
    }

    public function eliminarDocumento(string $id): void
    {
        $this->admin();
        $x = ctype_digit($id) ? Database::una('SELECT * FROM tit_docs_extra WHERE id = ?', [(int) $id]) : null;
        if ($x === null) {
            $this->noEncontrado();
        }
        Database::ejecutar('DELETE FROM tit_docs_cfg WHERE clave = ?', [$x['clave']]);
        Database::ejecutar('DELETE FROM tit_docs_extra WHERE id = ?', [(int) $id]);
        Configuracion::reiniciar();
        Configuracion::bitacora(Auth::id(), 'documento_eliminado', 'Documento eliminado: ' . $x['nombre']);
        flash('exito', 'Documento eliminado de la configuración. Los archivos ya subidos por pasantes se conservan en sus expedientes.');
        redirigir('titulacion/configuracion/documentos?modalidad=' . rawurlencode((string) $x['modalidad']));
    }

    // -------------------------------------------------------------------- formatos

    private function formatoDef(string $clave): array
    {
        $f = Catalogo::formatos()[$this->claveValida($clave)] ?? null;
        if ($f === null) {
            $this->noEncontrado();
        }
        return $f;
    }

    public function visor(string $clave): void
    {
        $this->admin();
        $f = $this->formatoDef($clave);
        if ($f['tipo'] !== 'pdf') {
            flash('error', 'Este formato es un documento Word generado sobre la hoja membretada; se reemplaza la hoja, no se editan coordenadas.');
            redirigir('titulacion/configuracion');
        }
        $r = Configuracion::resolverPdf($clave);
        if ($r === null) {
            flash('error', 'No se encontró la plantilla de este formato. Reemplázala.');
            redirigir('titulacion/configuracion');
        }
        $info = PdfFormato::inspeccionar($r['plantilla']);
        $spec = $r['spec'];
        $spec['paginas'] = $info['paginas'] ?? 1;
        $spec['ancho'] = $info['ancho'] ?? $spec['ancho'];
        $spec['alto'] = $info['alto'] ?? $spec['alto'];
        $this->vista('configuracion_visor', [
            'titulo' => 'Visor de coordenadas · ' . $f['nombre'],
            'clave' => $clave,
            'f' => $f,
            'spec' => $spec,
            'variables' => Formatos::variables(),
        ]);
    }

    public function plantilla(string $clave): void
    {
        $this->admin();
        $this->formatoDef($clave);
        $r = Configuracion::resolverPdf($clave);
        if ($r === null) {
            $this->noEncontrado();
        }
        $this->privado();
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="plantilla.pdf"');
        header('Content-Length: ' . filesize($r['plantilla']));
        readfile($r['plantilla']);
        exit;
    }

    /** @return array{0:array,1:array} [spec validada, resultado de resolverPdf] */
    private function specRecibida(string $clave): array
    {
        $r = Configuracion::resolverPdf($clave);
        if ($r === null) {
            $this->noEncontrado();
        }
        $json = json_decode((string) ($_POST['spec'] ?? ''), true);
        if (!is_array($json)) {
            throw new InvalidArgumentException('La especificación recibida no es válida.');
        }
        $info = PdfFormato::inspeccionar($r['plantilla']);
        if ($info === null) {
            throw new RuntimeException('La plantilla no se puede leer.');
        }
        $spec = Configuracion::validarSpec($json, Formatos::variables(), $info + $r['spec']);
        return [$spec, $r];
    }

    public function guardarSpec(string $clave): void
    {
        $this->admin();
        $this->formatoDef($clave);
        try {
            [$spec] = $this->specRecibida($clave);
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
        Database::ejecutar(
            'INSERT INTO tit_formatos_cfg (clave, spec, actualizado_por) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE spec = VALUES(spec), actualizado_por = VALUES(actualizado_por)',
            [$clave, json_encode($spec, JSON_UNESCAPED_UNICODE), Auth::id()]
        );
        Configuracion::reiniciar();
        Configuracion::bitacora(Auth::id(), 'formato_coordenadas', 'Coordenadas guardadas: ' . $clave . ' (' . count($spec['campos']) . ' campos)');
        Audit::record('titulacion::config', 'correcto', 'Coordenadas ' . $clave);
        $this->json(['ok' => true, 'mensaje' => 'Coordenadas guardadas. Los formatos nuevos ya salen con este ajuste.']);
    }

    public function probar(string $clave): void
    {
        $this->admin();
        $this->formatoDef($clave);
        try {
            [$spec, $r] = $this->specRecibida($clave);
            $pdf = PdfFormato::generarCon($spec, $r['plantilla'], Formatos::ejemplo());
        } catch (Throwable $e) {
            http_response_code(422);
            exit('No se pudo generar la vista previa: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }
        $this->privado();
        header('Content-Type: application/pdf');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: inline; filename="vista-previa.pdf"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
        exit;
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Guarda un PDF subido y lo deja en formato que FPDI pueda importar. @return array{0:string,1:array} [ruta relativa, información] */
    private function guardarPlantillaPdf(): array
    {
        try {
            $rel = guardar_archivo('plantilla', 'titulacion_formatos', ['pdf']);
        } catch (InvalidArgumentException $e) {
            throw new RuntimeException($e->getMessage());
        }
        if ($rel === null) {
            throw new RuntimeException('Selecciona el archivo PDF de la plantilla.');
        }
        $abs = $this->storage() . '/' . $rel;
        $info = PdfFormato::inspeccionar($abs);
        if ($info === null) {
            $info = $this->normalizarConGhostscript($abs);
        }
        if ($info === null) {
            @unlink($abs);
            throw new RuntimeException('El PDF usa una compresión que el sistema no puede leer. Guárdalo como «PDF compatible con Acrobat 5 / PDF 1.4» (o impórtalo con «Imprimir a PDF») y súbelo de nuevo.');
        }
        return [$rel, $info];
    }

    private function normalizarConGhostscript(string $abs): ?array
    {
        if (!function_exists('shell_exec') || in_array('shell_exec', array_map('trim', explode(',', (string) ini_get('disable_functions'))), true)) {
            return null;
        }
        $tmp = $abs . '.tmp.pdf';
        @shell_exec('gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dCompatibilityLevel=1.4 -sOutputFile=' . escapeshellarg($tmp) . ' ' . escapeshellarg($abs) . ' 2>/dev/null');
        if (is_file($tmp) && filesize($tmp) > 0 && PdfFormato::inspeccionar($tmp) !== null) {
            rename($tmp, $abs);
            return PdfFormato::inspeccionar($abs);
        }
        @unlink($tmp);
        return null;
    }

    public function reemplazar(string $clave): void
    {
        $this->admin();
        $f = $this->formatoDef($clave);
        $volver = 'titulacion/configuracion';
        try {
            if ($f['tipo'] === 'docx') {
                $rel = guardar_archivo('plantilla', 'titulacion_formatos', ['docx']);
                if ($rel === null) {
                    throw new RuntimeException('Selecciona el archivo .docx de la hoja membretada.');
                }
                $zip = new \ZipArchive();
                $ok = $zip->open($this->storage() . '/' . $rel) === true && $zip->getFromName('word/document.xml') !== false;
                if ($ok) {
                    $zip->close();
                }
                if (!$ok) {
                    @unlink($this->storage() . '/' . $rel);
                    throw new RuntimeException('El archivo no es un documento de Word válido.');
                }
                $info = null;
            } else {
                [$rel, $info] = $this->guardarPlantillaPdf();
            }
        } catch (RuntimeException | InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir($volver);
        }
        $anterior = Configuracion::formatoCfg($clave);
        Database::ejecutar(
            'INSERT INTO tit_formatos_cfg (clave, plantilla_ruta, actualizado_por) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE plantilla_ruta = VALUES(plantilla_ruta), actualizado_por = VALUES(actualizado_por)',
            [$clave, $rel, Auth::id()]
        );
        if ($anterior && !empty($anterior['plantilla_ruta']) && $anterior['plantilla_ruta'] !== $rel) {
            @unlink($this->storage() . '/' . $anterior['plantilla_ruta']);
        }
        Configuracion::reiniciar();
        Configuracion::bitacora(Auth::id(), 'formato_plantilla', 'Plantilla reemplazada: ' . $clave);
        Audit::record('titulacion::config', 'correcto', 'Plantilla ' . $clave);
        if ($f['tipo'] === 'pdf') {
            flash('exito', 'Plantilla reemplazada (' . $info['paginas'] . ' pág.). Revisa las coordenadas en el visor por si el diseño cambió.');
            redirigir('titulacion/configuracion/formatos/' . $clave);
        }
        flash('exito', 'Hoja membretada reemplazada. Los protocolos nuevos la usarán.');
        redirigir($volver);
    }

    public function datosFormato(string $clave): void
    {
        $this->admin();
        $f = $this->formatoDef($clave);
        $nombre = mb_substr($this->post('nombre'), 0, 200);
        $firma = mb_substr($this->post('firma'), 0, 200);
        $activo = $this->post('activo') === '1' ? 1 : 0;
        $etapa = null;
        $mods = null;
        if (!empty($f['personalizado'])) {
            $etapa = isset($this->etapasDisponibles()[$this->post('etapa')]) ? $this->post('etapa') : null;
            $sel = array_filter((array) ($_POST['modalidades'] ?? []), static fn($m): bool => is_string($m) && Catalogo::modalidad($m) !== null);
            $mods = $this->post('todas') === '1' || !$sel ? '*' : implode(',', $sel);
        }
        Database::ejecutar(
            'INSERT INTO tit_formatos_cfg (clave, activo, nombre, firma, etapa, modalidades, actualizado_por) VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE activo = VALUES(activo), nombre = VALUES(nombre), firma = VALUES(firma),
               etapa = IF(personalizado = 1, VALUES(etapa), etapa), modalidades = IF(personalizado = 1, VALUES(modalidades), modalidades),
               actualizado_por = VALUES(actualizado_por)',
            [$clave, $activo, $nombre ?: null, $firma ?: null, $etapa, $mods, Auth::id()]
        );
        Configuracion::reiniciar();
        Configuracion::bitacora(Auth::id(), 'formato_datos', ($activo ? 'Formato activo: ' : 'Formato retirado: ') . $clave);
        flash('exito', $activo ? 'Formato actualizado.' : 'Formato retirado: ya no se genera ni se pide en los trámites.');
        redirigir('titulacion/configuracion');
    }

    public function restablecer(string $clave): void
    {
        $this->admin();
        $f = $this->formatoDef($clave);
        if (!empty($f['personalizado'])) {
            $this->noEncontrado();
        }
        $cfg = Configuracion::formatoCfg($clave);
        if ($cfg && !empty($cfg['plantilla_ruta'])) {
            @unlink($this->storage() . '/' . $cfg['plantilla_ruta']);
        }
        Database::ejecutar('DELETE FROM tit_formatos_cfg WHERE clave = ?', [$clave]);
        Configuracion::reiniciar();
        Configuracion::bitacora(Auth::id(), 'formato_restablecido', 'Formato restablecido al original: ' . $clave);
        flash('exito', 'Formato restablecido a la versión que acompaña al módulo.');
        redirigir('titulacion/configuracion');
    }

    public function nuevoFormato(): void
    {
        $this->admin();
        $nombre = mb_substr($this->post('nombre'), 0, 200);
        $etapa = $this->post('etapa');
        if (mb_strlen($nombre) < 3 || !isset($this->etapasDisponibles()[$etapa])) {
            flash('error', 'Indica un nombre y la etapa donde se usará el formato.');
            redirigir('titulacion/configuracion');
        }
        try {
            [$rel, $info] = $this->guardarPlantillaPdf();
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('titulacion/configuracion');
        }
        $slug = trim(preg_replace('/[^a-z0-9]+/', '_', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $nombre))) ?? '', '_');
        $clave = 'f_' . substr($slug, 0, 24) . '_' . bin2hex(random_bytes(2));
        $sel = array_filter((array) ($_POST['modalidades'] ?? []), static fn($m): bool => is_string($m) && Catalogo::modalidad($m) !== null);
        $mods = $this->post('todas') === '1' || !$sel ? '*' : implode(',', $sel);
        $spec = ['plantilla' => '', 'ancho' => $info['ancho'], 'alto' => $info['alto'], 'campos' => [], 'subdirector' => [], 'subdirector_impreso' => 'DR. JAVIER SALAS GARCÍA'];
        Database::ejecutar(
            'INSERT INTO tit_formatos_cfg (clave, personalizado, activo, nombre, firma, etapa, modalidades, plantilla_ruta, spec, actualizado_por)
             VALUES (?, 1, 1, ?, ?, ?, ?, ?, ?, ?)',
            [$clave, $nombre, mb_substr($this->post('firma'), 0, 200) ?: 'el pasante', $etapa, $mods, $rel, json_encode($spec), Auth::id()]
        );
        Configuracion::reiniciar();
        Configuracion::bitacora(Auth::id(), 'formato_nuevo', 'Formato añadido: ' . $nombre);
        flash('exito', 'Formato añadido. Coloca ahora los campos de datos sobre la plantilla.');
        redirigir('titulacion/configuracion/formatos/' . $clave);
    }

    public function eliminarFormato(string $clave): void
    {
        $this->admin();
        $f = $this->formatoDef($clave);
        if (empty($f['personalizado'])) {
            flash('error', 'Los formatos oficiales no se eliminan; puedes retirarlos para que dejen de usarse.');
            redirigir('titulacion/configuracion');
        }
        $cfg = Configuracion::formatoCfg($clave);
        if ($cfg && !empty($cfg['plantilla_ruta'])) {
            @unlink($this->storage() . '/' . $cfg['plantilla_ruta']);
        }
        Database::ejecutar('UPDATE tit_docs_extra SET formato = NULL WHERE formato = ?', [$clave]);
        Database::ejecutar('DELETE FROM tit_formatos_cfg WHERE clave = ?', [$clave]);
        Configuracion::reiniciar();
        Configuracion::bitacora(Auth::id(), 'formato_eliminado', 'Formato eliminado: ' . $f['nombre']);
        flash('exito', 'Formato eliminado.');
        redirigir('titulacion/configuracion');
    }
}
