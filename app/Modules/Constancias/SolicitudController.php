<?php
declare(strict_types=1);

namespace App\Modules\Constancias;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Identidad;
use App\Modules\Constancias\Services\Cuerpo;
use App\Modules\Constancias\Services\Docx;
use App\Modules\Constancias\Services\Firmantes;
use App\Modules\Constancias\Services\Folio;
use App\Modules\Constancias\Services\Adjuntos;
use App\Modules\Constancias\Services\Solicitudes;
use InvalidArgumentException;
use RuntimeException;

/** «Solicitar una constancia»: captura, vista previa, envío a firma, corrección tras una devolución y consulta. */
final class SolicitudController extends ConstanciasBase
{
    public function formulario(): void
    {
        $this->soloSolicitantes();
        $this->privado();
        $this->mostrarFormulario(null, [], null);
    }

    public function crear(): void
    {
        $this->soloSolicitantes();
        try {
            $d = $this->leer(null);
            $id = Solicitudes::guardar(null, $d, Auth::id());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->privado();
            $this->mostrarFormulario(null, $_POST, $e->getMessage());
            return;
        }
        $this->guardarEvidencia($id);
        Audit::record('constancias::solicitar', 'correcto', 'Constancia ' . $id . ' (borrador) · firma ' . $d['firmante']['usuario_id'] . ' · depto ' . $d['firmante']['departamento_id']);
        flash('exito', 'Revisa la vista previa. Cuando esté lista, envíala a firma.');
        redirigir('constancias/solicitud/' . $id);
    }

    /** Guarda la evidencia opcional que se subió con el formulario y avisa de lo que no se pudo guardar. */
    private function guardarEvidencia(int $id): void
    {
        $r = Adjuntos::guardarDelFormulario($id);
        if ($r['errores']) {
            flash('error', implode(' ', $r['errores']));
        }
    }

    public function editar(string $id): void
    {
        $s = $this->propiaEditable($id);
        $this->privado();
        $this->mostrarFormulario($s, [], null);
    }

    public function actualizar(string $id): void
    {
        $s = $this->propiaEditable($id);
        try {
            $d = $this->leer($s);
            Solicitudes::guardar((int) $s['id'], $d, Auth::id());
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->privado();
            $this->mostrarFormulario($s, $_POST, $e->getMessage());
            return;
        }
        $this->guardarEvidencia((int) $s['id']);
        Audit::record('constancias::editar', 'correcto', 'Constancia ' . (int) $s['id']);
        flash('exito', 'Cambios guardados. Revisa la vista previa.');
        redirigir('constancias/solicitud/' . (int) $s['id']);
    }

    public function ver(string $id): void
    {
        $s = $this->visible($id);
        $this->privado();
        $esSolicitante = (int) $s['solicitante_id'] === Auth::id();
        $this->vista('solicitud', [
            'titulo' => 'Constancia ' . ($s['folio'] ?: '(sin enviar)'),
            's' => $s,
            'historial' => Solicitudes::historial((int) $s['id']),
            'esSolicitante' => $esSolicitante,
            'esFirmante' => (int) $s['firmante_id'] === Auth::id(),
            'editable' => $esSolicitante && in_array($s['estado'], ['borrador', 'devuelta'], true),
            'folioPrevisto' => Folio::provisional((int) $s['departamento_id']),
            'evidencia' => Adjuntos::de((int) $s['id']),
        ]);
    }

    /** PDF: el firmado si ya se firmó; si no, la versión sin firma (solo para quien trabaja en ella). */
    public function pdf(string $id): void
    {
        $s = $this->visible($id);
        if ($s['estado'] === 'firmada' && !empty($s['ruta_firmado'])) {
            Audit::record('constancias::descargar', 'atendido', 'constancias ' . (int) $s['id']);
            $this->enviarArchivo(Solicitudes::absoluta((string) $s['ruta_firmado']), 'constancia_' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $s['folio']) . '.pdf');
        }
        if (empty($s['ruta']) || !$this->trabajaEnElla($s)) {
            $this->noEncontrado();
        }
        $this->enviarArchivo(Solicitudes::absoluta((string) $s['ruta']), 'constancia_' . (int) $s['id'] . '_SIN_FIRMA.pdf');
    }

    public function enviar(string $id): void
    {
        $s = $this->propiaEditable($id);
        try {
            $s = Solicitudes::enviar((int) $s['id'], Auth::id());
            flash('exito', 'Enviada a firma con el folio ' . $s['folio'] . '. Avisamos por correo a ' . $s['firmante_nombre'] . '.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('constancias/solicitud/' . (int) $s['id']);
    }

    public function cancelar(string $id): void
    {
        $s = $this->propiaEditable($id);
        try {
            Solicitudes::cancelar((int) $s['id'], Auth::id());
            flash('exito', 'Constancia descartada.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('constancias/solicitud/' . (int) $s['id']);
        }
        redirigir('constancias/solicitar');
    }

    // ------------------------------------------------------------------ permisos

    /** Solicitante (en borrador solo él); en firma o firmada también firmante, personal del departamento y supervisión; firmada, la persona. */
    private function visible(string $id): array
    {
        $n = self::id($id);
        $s = $n !== null ? Solicitudes::obtener($n) : null;
        if (!$s) {
            $this->noEncontrado();
        }
        $yo = Auth::id();
        if ((int) $s['solicitante_id'] === $yo) {
            return $s;
        }
        if (in_array($s['estado'], ['borrador', 'cancelada'], true)) {
            $this->noEncontrado();
        }
        if ($this->trabajaEnElla($s)) {
            return $s;
        }
        if ($s['estado'] === 'firmada' && $this->esLaPersona($s)) {
            return $s;
        }
        $this->noEncontrado();
    }

    private function trabajaEnElla(array $s): bool
    {
        return (int) $s['solicitante_id'] === Auth::id() || (int) $s['firmante_id'] === Auth::id() || ConstanciasAccess::esSupervision()
            || in_array((int) $s['departamento_id'], ConstanciasAccess::departamentosPropios(), true);
    }

    private function esLaPersona(array $s): bool
    {
        $a = ConstanciasAccess::alcance();
        return ($a['usuario_id'] && (int) $s['persona_usuario_id'] === (int) $a['usuario_id'])
            || ($a['profesor_id'] && (int) $s['persona_profesor_id'] === (int) $a['profesor_id'])
            || ((string) $s['persona_email'] !== '' && in_array(Identidad::normalizar((string) $s['persona_email']), $a['correos'], true));
    }

    private function propiaEditable(string $id): array
    {
        $this->soloSolicitantes();
        $n = self::id($id);
        $s = $n !== null ? Solicitudes::obtener($n) : null;
        if (!$s || (int) $s['solicitante_id'] !== Auth::id()) {
            $this->noEncontrado();
        }
        if (!in_array($s['estado'], ['borrador', 'devuelta'], true)) {
            flash('error', 'Esta constancia ya no se puede modificar.');
            redirigir('constancias/solicitud/' . (int) $s['id']);
        }
        return $s;
    }

    // ------------------------------------------------------------------ formulario

    private function mostrarFormulario(?array $s, array $post, ?string $error): void
    {
        if ($error !== null) {
            http_response_code(422);
        }
        $firmantes = Firmantes::paraConstancias();
        $firmanteActual = '';
        if ($s) {
            foreach (Firmantes::todos() as $f) {
                if ($f['usuario_id'] === (int) $s['firmante_id'] && $f['departamento_id'] === (int) $s['departamento_id']) {
                    $firmanteActual = $f['clave'];
                    break;
                }
            }
        }
        $v = [
            'dirigido_a' => (string) ($post['dirigido_a'] ?? ($s['dirigido_a'] ?? 'A QUIEN CORRESPONDA')),
            'asunto' => (string) ($post['asunto'] ?? ($s['asunto'] ?? 'CONSTANCIA')),
            'texto' => (string) ($post['texto'] ?? ($s['texto'] ?? '')),
            'firmante' => (string) ($post['firmante'] ?? $firmanteActual),
            'modo' => (string) ($post['modo'] ?? (($s['origen'] ?? 'texto') === 'docx' ? 'docx' : 'texto')),
        ];
        $this->vista('solicitar', [
            'titulo' => $s ? 'Corregir la constancia' : 'Solicitar una constancia',
            's' => $s, 'v' => $v, 'error' => $error,
            'firmantes' => $firmantes,
            'docx' => Docx::disponible(),
            'mias' => $s ? [] : Solicitudes::delSolicitante(Auth::id()),
            'evidencia' => $s ? Adjuntos::de((int) $s['id']) : [],
        ]);
    }

    /** Valida lo capturado; el cuerpo sale del archivo de Word (si se subió uno) o del campo de texto. */
    private function leer(?array $previo): array
    {
        $linea = static fn(string $v, int $max): string => mb_substr(trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? ''), 0, $max);
        $dirigido = str_replace("\r", '', $linea((string) ($_POST['dirigido_a'] ?? ''), 600));
        $dirigido = implode("\n", array_slice(array_values(array_filter(array_map('trim', explode("\n", $dirigido)), static fn(string $l): bool => $l !== '')), 0, 5));
        if ($dirigido === '') {
            $dirigido = 'A QUIEN CORRESPONDA';
        }
        $asunto = $linea((string) ($_POST['asunto'] ?? ''), 160);
        $asunto = preg_replace('/\s+/u', ' ', $asunto) ?: 'CONSTANCIA';
        // La constancia se extiende a quien la solicita: nombre y correo salen de su cuenta.
        $yo = Auth::usuario() ?? [];
        $personaNombre = preg_replace('/\s+/u', ' ', trim((string) ($yo['nombre'] ?? ''))) ?? '';
        $personaEmail = strtolower(trim((string) ($yo['email'] ?? '')));
        $firmante = Firmantes::paraConstancias()[(string) ($_POST['firmante'] ?? '')] ?? null;
        if ($firmante === null) {
            throw new InvalidArgumentException('Elige quién firma la constancia.');
        }
        $texto = str_replace("\r\n", "\n", (string) ($_POST['texto'] ?? ''));
        if (mb_strlen($texto) > Cuerpo::MAX_TEXTO) {
            throw new InvalidArgumentException('El texto es demasiado largo (máximo ' . number_format(Cuerpo::MAX_TEXTO) . ' caracteres).');
        }
        $modo = ($_POST['modo'] ?? 'texto') === 'docx' ? 'docx' : 'texto';
        $archivo = $_FILES['archivo'] ?? null;
        $subido = is_array($archivo) && ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
        $avisos = [];
        $nombreArchivo = '';
        if ($modo === 'docx' && $subido) {
            if (($archivo['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($archivo['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE) {
                throw new InvalidArgumentException('El archivo de Word pesa más de 2 MB.');
            }
            if (($archivo['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $archivo['tmp_name'])) {
                throw new InvalidArgumentException('No se recibió bien el archivo. Vuelve a elegirlo.');
            }
            $leido = Docx::leer((string) $archivo['tmp_name'], (string) $archivo['name']);
            $bloques = $leido['bloques'];
            $avisos = $leido['avisos'];
            $nombreArchivo = mb_substr(basename((string) $archivo['name']), 0, 120);
            $origen = 'docx';
            $texto = '';
        } elseif ($modo === 'docx' && $previo && $previo['origen'] === 'docx' && $previo['bloques']) {
            // Corrección sin subir otro archivo: se conserva el cuerpo leído del Word anterior.
            $bloques = $previo['bloques'];
            $avisos = $previo['avisos'];
            $nombreArchivo = $previo['archivo'];
            $origen = 'docx';
            $texto = '';
        } elseif ($modo === 'docx') {
            throw new InvalidArgumentException('Elige el archivo de Word con el cuerpo de la constancia, o cambia a «Escribir el texto».');
        } else {
            $bloques = Cuerpo::desdeTexto($texto);
            $origen = 'texto';
        }
        if (Cuerpo::plano($bloques) === '') {
            throw new InvalidArgumentException($origen === 'docx' ? 'El archivo de Word no tiene texto que se pueda usar.' : 'Escribe el cuerpo de la constancia.');
        }
        return [
            'dirigido_a' => $dirigido, 'asunto' => $asunto, 'bloques' => $bloques, 'texto' => $texto, 'avisos' => $avisos, 'archivo' => $nombreArchivo,
            'origen' => $origen, 'persona_nombre' => $personaNombre, 'persona_email' => $personaEmail, 'firmante' => $firmante,
        ];
    }
}
