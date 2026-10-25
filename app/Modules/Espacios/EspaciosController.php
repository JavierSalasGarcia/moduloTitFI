<?php
declare(strict_types=1);

namespace App\Modules\Espacios;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/** Calendario semanal de cada auditorio o sala y registro de su ocupación por las personas autorizadas. */
final class EspaciosController extends Controller
{
    private function lunes(string $fecha): DateTimeImmutable
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha) ?: new DateTimeImmutable('today');
        return $d->modify('-' . ((int) $d->format('N') - 1) . ' days');
    }

    public function calendario(): void
    {
        $espacios = Agenda::espacios();
        $sel = (int) ($_GET['s'] ?? 0);
        $ids = array_map(static fn(array $e): int => (int) $e['id'], $espacios);
        if (!in_array($sel, $ids, true)) {
            $sel = $ids[0] ?? 0;
        }
        $lunes = $this->lunes((string) ($_GET['semana'] ?? date('Y-m-d')));
        $domingo = $lunes->modify('+6 days');
        $reservas = $sel ? Agenda::reservasEntre($sel, $lunes->format('Y-m-d'), $domingo->format('Y-m-d')) : [];
        $porDia = [];
        foreach ($reservas as $r) {
            $porDia[$r['fecha']][] = $r;
        }
        $this->vista('calendario', [
            'titulo' => 'Auditorios y salas',
            'espacios' => $espacios,
            'sel' => $sel,
            'espacio' => $sel ? Agenda::espacio($sel) : null,
            'horario' => $sel ? Agenda::horario($sel) : [],
            'lunes' => $lunes,
            'porDia' => $porDia,
            'puedeRegistrar' => $sel > 0 && EspaciosAccess::puedeRegistrar($sel),
            'autorizados' => EspaciosAccess::espaciosAutorizados(),
            'esAdmin' => EspaciosAccess::esAdmin(),
        ]);
    }

    public function registrar(): void
    {
        $espacioId = (int) $this->post('espacio');
        $fecha = $this->post('fecha');
        $volver = 'espacios?s=' . $espacioId . '&semana=' . rawurlencode($fecha);
        if (!EspaciosAccess::puedeRegistrar($espacioId)) {
            Audit::record('espacios::registrar', 'denegado', 'Espacio ' . $espacioId);
            flash('error', 'No estás autorizado para registrar la ocupación de este espacio.');
            redirigir($volver);
        }
        try {
            $id = Agenda::reservar($espacioId, [
                'fecha' => $fecha, 'inicio' => $this->post('inicio'), 'fin' => $this->post('fin'),
                'descripcion' => $this->post('descripcion'), 'responsable' => $this->post('responsable'), 'responsable_email' => $this->post('responsable_email'),
            ], Auth::id());
            Audit::record('espacios::registrar', 'correcto', 'Reserva ' . $id);
            flash('exito', 'Ocupación registrada.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        } catch (Throwable $e) {
            error_log('Espacios: error al registrar: ' . $e->getMessage());
            flash('error', 'No se pudo registrar. Intenta de nuevo.');
        }
        redirigir($volver);
    }

    public function cancelar(string $id): void
    {
        $r = ctype_digit($id) ? Agenda::reserva((int) $id) : null;
        if ($r === null || !EspaciosAccess::puedeCancelar($r)) {
            http_response_code(404);
            View::render('errores/404', ['titulo' => 'No disponible']);
            return;
        }
        if ($r['origen'] === 'titulacion' && !EspaciosAccess::esAdmin()) {
            flash('error', 'Los espacios apartados para exámenes de titulación solo los libera un administrador del módulo.');
        } else {
            Agenda::cancelar((int) $r['id'], Auth::id());
            Audit::record('espacios::cancelar', 'correcto', 'Reserva ' . (int) $r['id']);
            flash('exito', 'Se canceló el registro; el horario quedó libre.');
        }
        redirigir('espacios?s=' . (int) $r['espacio_id'] . '&semana=' . $r['fecha']);
    }

    /** Sugerencias de personas con cuenta (nombre o correo) para responsables y autorizados. */
    public function personas(): void
    {
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
        $salida = [];
        if (mb_strlen($q) >= 3) {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $salida = Database::todas(
                "SELECT id, nombre, email FROM usuarios WHERE activo = 1 AND rol <> 'alumno' AND (nombre LIKE ? OR email LIKE ?) ORDER BY nombre LIMIT 8",
                [$like, $like]
            );
        }
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($salida, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
