<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Titulacion\Services\AccesoEgresados;
use App\Modules\Titulacion\Services\Correo;
use RuntimeException;

/** Cola «Accesos de egresados»: el personal de Titulación valida la identidad de quien ya no tiene correo institucional. */
final class AccesosEgresadosController extends TitulacionBase
{
    private function solo(): void
    {
        if (!TitulacionAccess::esPersonal()) {
            $this->prohibido('Accesos de egresados');
        }
        if (!AccesoEgresados::tablasListas()) {
            flash('error', 'El acceso de egresados aún no está instalado (falta aplicar la migración 041).');
            redirigir('titulacion/pendientes');
        }
        $this->privado();
    }

    private function solicitud(string $id): array
    {
        $s = ctype_digit($id) ? Database::una('SELECT * FROM egresado_solicitudes WHERE id = ?', [(int) $id]) : null;
        return $s ?: $this->noEncontrado();
    }

    private function urlBase(): string
    {
        return rtrim(Correo::enlace(''), '/');
    }

    public function lista(): void
    {
        $this->solo();
        $estado = isset(AccesoEgresados::ESTADOS[(string) ($_GET['estado'] ?? '')]) ? (string) $_GET['estado'] : 'pendiente';
        $filas = Database::todas('SELECT * FROM egresado_solicitudes WHERE estado = ? ORDER BY creado_en DESC LIMIT 300', [$estado]);
        $cuentas = Database::todas("SELECT id,nombre,email,egresado_cuenta,egresado_carrera,egresado_vence,egresado_validado_en,activo FROM usuarios WHERE egresado_externo = 1 ORDER BY activo DESC, nombre LIMIT 300");
        $this->vista('accesos_egresados', ['titulo' => 'Accesos de egresados', 'estado' => $estado, 'filas' => $filas, 'conteos' => AccesoEgresados::conteos(), 'cuentas' => $cuentas]);
    }

    public function ver(string $id): void
    {
        $this->solo();
        $s = $this->solicitud($id);
        $this->vista('accesos_egresados_ver', ['titulo' => 'Solicitud de acceso', 's' => $s, 'docs' => AccesoEgresados::documentos((int) $s['id']),
            'otras' => Database::todas('SELECT id, estado, creado_en FROM egresado_solicitudes WHERE (cuenta = ? OR email = ?) AND id <> ? ORDER BY id DESC', [$s['cuenta'], $s['email'], $s['id']])]);
    }

    public function documento(string $id, string $doc): void
    {
        $this->solo();
        $s = $this->solicitud($id);
        $d = ctype_digit($doc) ? Database::una('SELECT * FROM egresado_documentos WHERE id = ? AND solicitud_id = ? AND borrado_en IS NULL', [(int) $doc, $s['id']]) : null;
        $base = realpath((string) \App\Core\App::config('archivos.storage'));
        $ruta = $d && $base !== false ? realpath($base . '/' . $d['ruta']) : false;
        if ($ruta === false || !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR) || !is_file($ruta) || !in_array($d['mime'], ['application/pdf', 'image/jpeg', 'image/png'], true)) {
            $this->noEncontrado();
        }
        Audit::record('egresado_documento_visto', 'correcto', 'solicitud ' . $s['id'] . ' ' . $d['tipo']);
        header('Content-Type: ' . $d['mime']);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'");
        header('Content-Disposition: inline; filename="' . $d['tipo'] . '.' . ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$d['mime']] . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    public function aprobar(string $id): void
    {
        $this->solo();
        $s = $this->solicitud($id);
        try {
            AccesoEgresados::aprobar((int) $s['id'], Auth::id(), $this->urlBase());
            flash('exito', 'Aprobada. Se creó la cuenta de ' . $s['nombre'] . ' y se le envió el enlace para fijar su contraseña.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('titulacion/accesos-egresados');
    }

    public function devolver(string $id): void
    {
        $this->solo();
        $s = $this->solicitud($id);
        $motivo = $this->post('motivo');
        if (mb_strlen($motivo) < 5) {
            flash('error', 'Escribe qué debe corregir o completar la persona.');
            redirigir('titulacion/accesos-egresados/' . $s['id']);
        }
        try {
            AccesoEgresados::devolver((int) $s['id'], Auth::id(), $motivo, $this->urlBase());
            flash('exito', 'Solicitud devuelta: la persona recibió el motivo y un enlace nuevo.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('titulacion/accesos-egresados');
    }

    public function rechazar(string $id): void
    {
        $this->solo();
        $s = $this->solicitud($id);
        $motivo = $this->post('motivo');
        if (mb_strlen($motivo) < 5) {
            flash('error', 'Escribe el motivo del rechazo.');
            redirigir('titulacion/accesos-egresados/' . $s['id']);
        }
        try {
            AccesoEgresados::rechazar((int) $s['id'], Auth::id(), $motivo);
            flash('exito', 'Solicitud rechazada y documentos borrados.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('titulacion/accesos-egresados');
    }

    public function desactivar(string $uid): void
    {
        $this->solo();
        if (!ctype_digit($uid) || !Database::valor('SELECT 1 FROM usuarios WHERE id = ? AND egresado_externo = 1', [(int) $uid])) {
            $this->noEncontrado();
        }
        AccesoEgresados::desactivar((int) $uid, Auth::id());
        flash('exito', 'Acceso desactivado.');
        redirigir('titulacion/accesos-egresados');
    }
}
