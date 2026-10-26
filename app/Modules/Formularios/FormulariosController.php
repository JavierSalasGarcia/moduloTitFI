<?php
declare(strict_types=1);

namespace App\Modules\Formularios;

use App\Core\AccessControl;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/** Formularios temporales con enlace público (sin cuenta) y su consulta para la administración. */
final class FormulariosController extends Controller
{
    private function formulario(string $id): array
    {
        $f = ctype_digit($id) ? Formularios::porId((int) $id) : null;
        if (!$f || !Formularios::puedeVer($f)) {
            http_response_code(404);
            \App\Core\View::render('errores/404', ['titulo' => 'Página no encontrada']);
            exit;
        }
        return $f;
    }

    public function index(): void
    {
        $lista = array_filter(Database::todas('SELECT f.*, (SELECT COUNT(*) FROM frm_respuestas r WHERE r.formulario_id = f.id) AS total FROM frm_formularios f ORDER BY f.id DESC'), [Formularios::class, 'puedeVer']);
        $this->vista('index', ['titulo' => 'Formularios', 'lista' => $lista]);
    }

    public function respuestas(string $id): void
    {
        $f = $this->formulario($id);
        $this->vista('respuestas', ['titulo' => $f['titulo'], 'f' => $f, 'campos' => Formularios::campos($f), 'respuestas' => Formularios::respuestas($f)]);
    }

    public function ajustes(string $id): void
    {
        $f = $this->formulario($id);
        $estado = in_array($this->post('estado'), ['borrador', 'abierto', 'cerrado'], true) ? $this->post('estado') : $f['estado'];
        $cierra = $this->post('cierra_en') !== '' && strtotime(str_replace('T', ' ', $this->post('cierra_en'))) ? date('Y-m-d H:i:s', strtotime(str_replace('T', ' ', $this->post('cierra_en')))) : null;
        $resp = Auth::tieneRol('admin') ? mb_substr($this->post('responsables'), 0, 2000) : $f['responsables'];
        Database::ejecutar('UPDATE frm_formularios SET estado = ?, cierra_en = ?, responsables = ? WHERE id = ?', [$estado, $cierra, $resp, (int) $f['id']]);
        Audit::record('formularios::ajustes', 'correcto', $f['slug'] . ' · ' . $estado);
        flash('exito', 'Ajustes guardados.');
        redirigir('formularios/' . (int) $f['id']);
    }

    public function corregir(string $id, string $rid): void
    {
        $f = $this->formulario($id);
        $r = ctype_digit($rid) ? Database::una('SELECT * FROM frm_respuestas WHERE id = ? AND formulario_id = ?', [(int) $rid, (int) $f['id']]) : null;
        if (!$r) {
            redirigir('formularios/' . (int) $f['id']);
        }
        $entrada = is_array($_POST['d'] ?? null) ? $_POST['d'] : [];
        // Las confirmaciones de requisitos no se editan: se conservan las del registro.
        foreach (Formularios::campos($f) as $c) {
            if (($c['tipo'] ?? '') === 'confirmar') {
                $entrada[$c['k']] = (json_decode((string) $r['datos'], true)[$c['k']] ?? '') !== '' ? '1' : '';
            }
        }
        [$datos, $errores] = Formularios::validar($f, $entrada);
        $otro = Database::valor('SELECT id FROM frm_respuestas WHERE formulario_id = ? AND email = ? AND id <> ?', [(int) $f['id'], $datos['email'] ?? '', (int) $r['id']]);
        if ($errores || $otro) {
            flash('error', $otro ? 'Ese correo ya está en otro registro.' : implode(' ', array_slice($errores, 0, 3)));
            redirigir('formularios/' . (int) $f['id'] . '#r' . (int) $r['id']);
        }
        Database::ejecutar('UPDATE frm_respuestas SET email = ?, datos = ?, actualizado_por = ?, actualizado_en = NOW() WHERE id = ?', [$datos['email'] ?? $r['email'], json_encode($datos, JSON_UNESCAPED_UNICODE), Auth::id(), (int) $r['id']]);
        Audit::record('formularios::corregir', 'correcto', $f['slug'] . ' · ' . (int) $r['id']);
        flash('exito', 'Registro corregido.');
        redirigir('formularios/' . (int) $f['id'] . '#r' . (int) $r['id']);
    }

    public function eliminar(string $id, string $rid): void
    {
        $f = $this->formulario($id);
        Database::ejecutar('DELETE FROM frm_respuestas WHERE id = ? AND formulario_id = ?', [(int) $rid, (int) $f['id']]);
        Audit::record('formularios::eliminar', 'correcto', $f['slug'] . ' · ' . (int) $rid);
        flash('exito', 'Registro eliminado.');
        redirigir('formularios/' . (int) $f['id']);
    }

    public function excel(string $id): void
    {
        $f = $this->formulario($id);
        $bytes = Formularios::excel($f);
        Audit::record('formularios::excel', 'atendido', $f['slug']);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $f['slug'] . '.xlsx"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store, private');
        echo $bytes;
        exit;
    }

    // ------------------------------------------------------------------ público

    private function publico(string $slug): array
    {
        $f = Formularios::porSlug($slug);
        if (!$f || $f['estado'] === 'borrador') {
            http_response_code(404);
            \App\Core\View::render('errores/404', ['titulo' => 'Página no encontrada'], Auth::usuario() ? 'app' : 'auth');
            exit;
        }
        header('X-Robots-Tag: noindex, nofollow');
        return $f;
    }

    public function llenar(string $slug): void
    {
        $f = $this->publico($slug);
        $enviado = $_SESSION['frm_enviado_' . $f['id']] ?? null;
        unset($_SESSION['frm_enviado_' . $f['id']]);
        $previo = $_SESSION['frm_previo_' . $f['id']] ?? [];
        unset($_SESSION['frm_previo_' . $f['id']]);
        $this->vista('llenar', ['titulo' => $f['titulo'], 'f' => $f, 'campos' => Formularios::campos($f), 'abierto' => Formularios::abierto($f),
            'enviado' => $enviado, 'previo' => $previo, 'seccion' => 'Formularios'], Auth::usuario() ? 'app' : 'publico');
    }

    public function enviar(string $slug): void
    {
        $f = $this->publico($slug);
        if (!Formularios::abierto($f)) {
            flash('error', 'Este formulario ya no recibe registros.');
            redirigir('f/' . $f['slug']);
        }
        // Trampa para robots y límite de envíos por dirección.
        if ($this->post('sitio_web') !== '' || (AccessControl::ready() && AccessControl::limited('frm|' . $f['id'] . '|' . ($_SERVER['REMOTE_ADDR'] ?? ''), 30, 3600))) {
            flash('error', 'No se pudo registrar. Intenta más tarde.');
            redirigir('f/' . $f['slug']);
        }
        $entrada = is_array($_POST['d'] ?? null) ? $_POST['d'] : [];
        [$datos, $errores] = Formularios::validar($f, $entrada);
        if (!$errores) {
            try {
                Formularios::guardar($f, $datos);
            } catch (\InvalidArgumentException $e) {
                $errores[] = $e->getMessage();
            }
        }
        if ($errores) {
            $_SESSION['frm_previo_' . $f['id']] = $datos;
            flash('error', implode(' ', array_slice($errores, 0, 4)));
            redirigir('f/' . $f['slug']);
        }
        Audit::record('formularios::registro', 'correcto', $f['slug']);
        $_SESSION['frm_enviado_' . $f['id']] = trim(($datos['nombres'] ?? '') . ' ' . ($datos['paterno'] ?? ''));
        redirigir('f/' . $f['slug']);
    }
}
