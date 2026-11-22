<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;

/** Página de estadísticas de Avisos y eventos y lista de personas que pueden consultarlas. */
final class EstadisticasController extends Controller
{
    private function exigir(): void
    {
        if (!Estadisticas::puedeVer()) {
            http_response_code(403);
            View::render('errores/403', ['titulo' => 'Acceso restringido']);
            exit;
        }
    }

    public function index(): void
    {
        $this->exigir();
        $this->vista('estadisticas', [
            'titulo' => 'Estadísticas de avisos y eventos',
            'filas' => Estadisticas::resumen(),
            'gestor' => Estadisticas::esGestor(),
            'personas' => Estadisticas::esGestor() ? Database::todas('SELECT u.id, u.nombre, u.email FROM estadisticas_usuarios e JOIN usuarios u ON u.id = e.usuario_id ORDER BY u.nombre') : [],
        ]);
    }

    /** Exporta la tabla en CSV (se abre en Excel). */
    public function excel(): void
    {
        $this->exigir();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="estadisticas_avisos_' . date('Y-m-d') . '.csv"');
        $o = fopen('php://output', 'w');
        fwrite($o, "\xEF\xBB\xBF");
        fputcsv($o, ['Tipo', 'Título', 'Fecha', 'Área', 'Personas con sesión', 'Visitantes sin cuenta', 'Aperturas', 'Descargas', 'Enlace']);
        foreach (Estadisticas::resumen(12) as $f) {
            fputcsv($o, [$f['tipo'], $f['titulo'], substr((string) $f['fecha'], 0, 10), $f['depto'], $f['personas'], $f['visitantes'], $f['aperturas'], $f['descargas'], 'https://fingenieria.mx/' . $f['url']]);
        }
        exit;
    }

    /** Buscador de cuentas para dar acceso a estadísticas (SelectorPersonas): solo quien gestiona ese acceso. */
    public function buscar(): void
    {
        if (!Estadisticas::esGestor()) {
            http_response_code(403);
            exit;
        }
        \App\Core\BuscadorPersonas::responder();
    }

    public function agregar(): void
    {
        if (!Estadisticas::esGestor()) {
            redirigir('eventos/estadisticas');
        }
        $u = Database::una('SELECT id, nombre FROM usuarios WHERE email = ? AND activo = 1', [strtolower(trim($this->post('email')))]);
        if (!$u) {
            flash('error', 'No hay una cuenta activa con ese correo.');
        } else {
            Database::ejecutar('INSERT IGNORE INTO estadisticas_usuarios (usuario_id, creado_por) VALUES (?, ?)', [(int) $u['id'], Auth::id()]);
            flash('exito', $u['nombre'] . ' ya puede consultar las estadísticas.');
        }
        redirigir('eventos/estadisticas');
    }

    public function quitar(string $usuario): void
    {
        if (Estadisticas::esGestor() && ctype_digit($usuario)) {
            Database::ejecutar('DELETE FROM estadisticas_usuarios WHERE usuario_id = ?', [(int) $usuario]);
            flash('exito', 'Permiso retirado.');
        }
        redirigir('eventos/estadisticas');
    }
}
