<?php
declare(strict_types=1);

namespace App\Modules\Teams;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;
use InvalidArgumentException;

final class TeamsController extends Controller
{
    public function index(): void
    {
        $u = Auth::usuario();
        $q = trim($_GET['q'] ?? '');

        // Intercambiar cuenta activa si viene por parámetro
        if (isset($_GET['active_account'])) {
            $_SESSION['active_teams_email'] = $_GET['active_account'];
            redirigir('teams');
        }

        $cuentas = Database::todas('SELECT DISTINCT microsoft_email FROM teams_sitios WHERE usuario_id = ? AND microsoft_email IS NOT NULL AND microsoft_email != \'\'', [$u['id']]);
        $emailsEnLista = array_column($cuentas, 'microsoft_email');
        
        // Agregar correo personal a la lista si no está y es válido
        if ($u['email'] !== '' && !in_array($u['email'], $emailsEnLista, true)) {
            array_unshift($cuentas, ['microsoft_email' => $u['email']]);
        }
        
        // También nos aseguramos de que jsalasg@uaemex.mx esté en la lista para facilitar las pruebas del usuario
        if ($u['email'] === 'proyectos@javiersalasg.com' && !in_array('jsalasg@uaemex.mx', array_column($cuentas, 'microsoft_email'), true)) {
            $cuentas[] = ['microsoft_email' => 'jsalasg@uaemex.mx'];
        }

        $activeEmail = $_SESSION['active_teams_email'] ?? $u['email'];

        $resultados = [];
        if ($q !== '') {
            // Buscar carpetas cuyas descripciones o rutas coincidan
            $resultados = Database::todas(
                "SELECT c.id, c.ruta_relativa, c.descripcion, s.nombre AS sitio_nombre, s.url_base
                   FROM teams_carpetas c
                   JOIN teams_sitios s ON s.id = c.sitio_id
                  WHERE s.microsoft_email = ?
                    AND (c.descripcion LIKE ? OR c.ruta_relativa LIKE ? OR s.nombre LIKE ?)
                  ORDER BY s.nombre, c.ruta_relativa",
                [$activeEmail, "%{$q}%", "%{$q}%", "%{$q}%"]
            );
        }

        $sitios = Database::todas(
            "SELECT s.*, 
                    (SELECT COUNT(*) FROM teams_carpetas WHERE sitio_id = s.id) AS total_carpetas
               FROM teams_sitios s 
              WHERE s.microsoft_email = ?
              ORDER BY s.nombre",
            [$activeEmail]
        );

        $carpetasPorSitio = [];
        $todasCarpetas = Database::todas(
            "SELECT c.* FROM teams_carpetas c 
               JOIN teams_sitios s ON s.id = c.sitio_id 
              WHERE s.microsoft_email = ?
              ORDER BY c.ruta_relativa",
            [$activeEmail]
        );
        foreach ($todasCarpetas as $c) {
            $carpetasPorSitio[$c['sitio_id']][] = $c;
        }

        View::render('teams:index', [
            'titulo' => 'Explorar Teams',
            'sitios' => $sitios,
            'carpetasPorSitio' => $carpetasPorSitio,
            'q' => $q,
            'resultados' => $resultados,
            'cuentas' => $cuentas,
            'activeEmail' => $activeEmail,
            'usuarios' => Database::todas("SELECT id, nombre, email, rol FROM usuarios WHERE activo = 1 ORDER BY nombre"),
            'ticketsRecibidos' => Database::todas(
                "SELECT t.*, c.ruta_relativa, s.nombre AS sitio_nombre, s.url_base, 
                        u.nombre AS creador_nombre, td.completado, td.archivo_subido, 
                        td.fecha_completado, td.id AS dest_id
                   FROM teams_tickets t
                   JOIN teams_carpetas c ON c.id = t.carpeta_id
                   JOIN teams_sitios s ON s.id = c.sitio_id
                   JOIN usuarios u ON u.id = t.creador_id
                   JOIN teams_ticket_destinatarios td ON td.ticket_id = t.id
                  WHERE td.usuario_id = ?
                  ORDER BY t.creado_en DESC",
                [$u['id']]
            ),
            'ticketsEmitidos' => array_map(function($te) {
                $te['destinatarios'] = Database::todas(
                    "SELECT td.*, u.nombre AS destinatario_nombre, u.email AS destinatario_email
                       FROM teams_ticket_destinatarios td
                       JOIN usuarios u ON u.id = td.usuario_id
                      WHERE td.ticket_id = ?",
                    [$te['id']]
                );
                return $te;
            }, Database::todas(
                "SELECT t.*, c.ruta_relativa, s.nombre AS sitio_nombre
                   FROM teams_tickets t
                   JOIN teams_carpetas c ON c.id = t.carpeta_id
                   JOIN teams_sitios s ON s.id = c.sitio_id
                  WHERE t.creador_id = ?
                  ORDER BY t.creado_en DESC",
                [$u['id']]
            )),
            'moduloActivo' => 'teams'
        ]);
    }

    private function normalizarUrlBase(string $urlBase): string
    {
        // Si tiene parámetros de consulta generales, primero los quitamos
        if (str_contains($urlBase, '?')) {
            $partesUrl = explode('?', $urlBase);
            $urlBase = $partesUrl[0];
        }

        // Si es una URL de SharePoint de Microsoft, la normalizamos de forma inteligente
        if (str_contains($urlBase, '.sharepoint.com')) {
            $urlDecoded = urldecode($urlBase);
            // Extraer la raíz del sitio de SharePoint (ej: https://dominio.sharepoint.com/sites/NombreSitio)
            if (preg_match('/(https:\/\/[^\/]+\/sites\/[^\/]+)/i', $urlDecoded, $matches)) {
                $siteRoot = $matches[1];
                // Detectar si utiliza el nombre de biblioteca "Documentos compartidos" o "Documentos" o "Shared Documents"
                if (str_contains(strtolower($urlDecoded), 'documentos compartidos')) {
                    $urlBase = $siteRoot . '/Documentos compartidos';
                } else if (str_contains(strtolower($urlDecoded), 'documentos')) {
                    $urlBase = $siteRoot . '/Documentos';
                } else {
                    $urlBase = $siteRoot . '/Shared Documents';
                }
            }
        }

        return rtrim($urlBase, '/');
    }

    public function crearSitio(): void
    {
        $u = Auth::usuario();
        $nombre = trim($_POST['nombre'] ?? '');
        $urlBase = trim($_POST['url_base'] ?? '');

        if ($nombre === '' || $urlBase === '') {
            flash('danger', 'Todos los campos son obligatorios.');
            redirigir('teams');
        }

        $urlBase = $this->normalizarUrlBase($urlBase);
        $activeEmail = $_SESSION['active_teams_email'] ?? $u['email'];

        try {
            Database::ejecutar(
                "INSERT INTO teams_sitios (usuario_id, nombre, url_base, microsoft_email) VALUES (?, ?, ?, ?)",
                [$u['id'], $nombre, $urlBase, $activeEmail]
            );
            flash('success', 'Grupo de Teams/Sitio agregado con éxito.');
        } catch (\Throwable $e) {
            flash('danger', 'Error al guardar el sitio: ' . $e->getMessage());
        }

        redirigir('teams');
    }

    public function editarSitio(string $id): void
    {
        $u = Auth::usuario();
        $id = (int) $id;
        $nombre = trim($_POST['nombre'] ?? '');
        $urlBase = trim($_POST['url_base'] ?? '');

        if ($nombre === '' || $urlBase === '') {
            flash('danger', 'Todos los campos son obligatorios.');
            redirigir('teams');
        }

        $urlBase = $this->normalizarUrlBase($urlBase);

        Database::ejecutar(
            "UPDATE teams_sitios SET nombre = ?, url_base = ? WHERE id = ? AND usuario_id = ?",
            [$nombre, $urlBase, $id, $u['id']]
        );

        flash('success', 'Sitio actualizado con éxito.');
        redirigir('teams');
    }

    public function verSitio(string $id): void
    {
        $u = Auth::usuario();
        $id = (int) $id;

        $sitio = Database::una(
            "SELECT * FROM teams_sitios WHERE id = ? AND usuario_id = ?",
            [$id, $u['id']]
        );

        if (!$sitio) {
            flash('danger', 'Sitio no encontrado o sin acceso.');
            redirigir('teams');
        }

        $carpetas = Database::todas(
            "SELECT * FROM teams_carpetas WHERE sitio_id = ? ORDER BY ruta_relativa",
            [$id]
        );

        View::render('teams:sitio', [
            'titulo' => 'Gestionar Sitio · ' . $sitio['nombre'],
            'sitio' => $sitio,
            'carpetas' => $carpetas,
            'moduloActivo' => 'teams'
        ]);
    }

    public function eliminarSitio(string $id): void
    {
        $u = Auth::usuario();
        $id = (int) $id;

        Database::ejecutar(
            "DELETE FROM teams_sitios WHERE id = ? AND usuario_id = ?",
            [$id, $u['id']]
        );

        flash('success', 'Sitio y sus carpetas indexadas eliminadas.');
        redirigir('teams');
    }

    public function crearCarpeta(string $id): void
    {
        $u = Auth::usuario();
        $sitioId = (int) $id;
        $ruta = trim($_POST['ruta_relativa'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');

        $sitio = Database::una(
            "SELECT id FROM teams_sitios WHERE id = ? AND usuario_id = ?",
            [$sitioId, $u['id']]
        );

        if (!$sitio) {
            flash('danger', 'Acceso no autorizado.');
            redirigir('teams');
        }

        if ($ruta === '') {
            flash('danger', 'La ruta de la carpeta no puede estar vacía.');
            redirigir('teams/sitio/' . $sitioId);
        }

        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            $ruta = trim($ruta);
        } else {
            $ruta = trim(str_replace('\\', '/', $ruta), '/');
        }

        try {
            Database::ejecutar(
                "INSERT INTO teams_carpetas (sitio_id, ruta_relativa, descripcion) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)",
                [$sitioId, $ruta, $descripcion]
            );
            flash('success', 'Carpeta agregada con éxito.');
        } catch (\Throwable $e) {
            flash('danger', 'Error al guardar la carpeta: ' . $e->getMessage());
        }

        redirigir('teams/sitio/' . $sitioId);
    }

    private function limpiarRuta(string $ruta): string
    {
        if (str_starts_with($ruta, 'http://') || str_starts_with($ruta, 'https://')) {
            return $ruta;
        }

        // Normalizar a barras inclinadas
        $ruta = str_replace('\\', '/', $ruta);

        // Limpiar tokens comunes de OneDrive / SharePoint
        $tokensRuptura = ['Shared Documents', 'Documentos compartidos', 'Documentos'];
        foreach ($tokensRuptura as $token) {
            $pos = mb_stripos($ruta, '/' . $token);
            if ($pos !== false) {
                return trim(mb_substr($ruta, $pos + mb_strlen($token) + 1), '/');
            }
            if (mb_stripos($ruta, $token) === 0) {
                return trim(mb_substr($ruta, mb_strlen($token)), '/');
            }
        }

        // Si la ruta contiene un segmento de sincronización de canal de Teams del tipo "NombreGrupo - NombreCanal"
        $partes = explode('/', $ruta);
        for ($i = 0; $i < count($partes); $i++) {
            if (str_contains($partes[$i], ' - ')) {
                $subPartes = explode(' - ', $partes[$i]);
                $canal = trim(end($subPartes));
                
                if (in_array(strtolower($canal), ['documents', 'documentos', 'documentos compartidos'])) {
                    $partes = array_slice($partes, $i + 1);
                } else {
                    $partes = array_slice($partes, $i);
                    $partes[0] = $canal;
                }
                $ruta = implode('/', $partes);
                break;
            }
        }

        // Si aún empieza por una unidad de disco local (ej: C:/), limpiamos
        if (preg_match('/^[a-zA-Z]:\//', $ruta)) {
            $partes = explode('/', $ruta);
            if (count($partes) > 3) {
                $ruta = implode('/', array_slice($partes, -3));
            }
        }

        return trim($ruta, '/');
    }

    public function indexarLote(string $id): void
    {
        $u = Auth::usuario();
        $sitioId = (int) $id;

        $sitio = Database::una(
            "SELECT * FROM teams_sitios WHERE id = ? AND usuario_id = ?",
            [$sitioId, $u['id']]
        );

        if (!$sitio) {
            flash('danger', 'Acceso no autorizado.');
            redirigir('teams');
        }

        $contador = 0;

        if (isset($_POST['rutas']) && is_array($_POST['rutas'])) {
            $rutas = $_POST['rutas'];
            $descripciones = $_POST['descripciones'] ?? [];
            $rutasLimpias = [];

            for ($i = 0; $i < count($rutas); $i++) {
                $rutaOriginal = trim($rutas[$i]);
                $rutaLimpia = $this->limpiarRuta($rutaOriginal);
                $desc = trim($descripciones[$rutaOriginal] ?? '');

                if ($rutaLimpia === '') {
                    continue;
                }

                $rutasLimpias[] = $rutaLimpia;

                try {
                    Database::ejecutar(
                        "INSERT INTO teams_carpetas (sitio_id, ruta_relativa, descripcion) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)",
                        [$sitioId, $rutaLimpia, $desc]
                    );
                    $contador++;
                } catch (\Throwable $e) {
                    // Omitir
                }
            }

            // Sincronizar de forma completa (borrar las deseleccionadas)
            if (isset($_POST['sincronizar_completo']) && $_POST['sincronizar_completo'] == '1') {
                if (!empty($rutasLimpias)) {
                    $placeholders = implode(',', array_fill(0, count($rutasLimpias), '?'));
                    Database::ejecutar(
                        "DELETE FROM teams_carpetas WHERE sitio_id = ? AND ruta_relativa NOT IN ($placeholders)",
                        array_merge([$sitioId], $rutasLimpias)
                    );
                } else {
                    Database::ejecutar("DELETE FROM teams_carpetas WHERE sitio_id = ?", [$sitioId]);
                }
            }
        } else {
            $loteText = $_POST['lote'] ?? '';
            $lineas = explode("\n", $loteText);

            foreach ($lineas as $linea) {
                $lineaOriginal = trim($linea, " \t\n\r\0\x0B\"");
                $rutaLimpia = $this->limpiarRuta($lineaOriginal);

                if ($rutaLimpia === '') {
                    continue;
                }

                try {
                    Database::ejecutar(
                        "INSERT INTO teams_carpetas (sitio_id, ruta_relativa) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE ruta_relativa = ruta_relativa",
                        [$sitioId, $rutaLimpia]
                    );
                    $contador++;
                } catch (\Throwable $e) {
                    // Omitir
                }
            }
        }

        flash('success', "Se procesaron e indexaron {$contador} carpetas.");
        redirigir('teams/sitio/' . $sitioId);
    }

    public function actualizarDescripcion(string $id): void
    {
        $u = Auth::usuario();
        $carpetaId = (int) $id;
        $descripcion = trim($_POST['descripcion'] ?? '');

        // Validar propiedad de la carpeta a través del sitio
        $carpeta = Database::una(
            "SELECT c.*, s.usuario_id 
               FROM teams_carpetas c
               JOIN teams_sitios s ON s.id = c.sitio_id
              WHERE c.id = ?",
            [$carpetaId]
        );

        if (!$carpeta || (int)$carpeta['usuario_id'] !== (int)$u['id']) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'No autorizado']);
            exit;
        }

        Database::ejecutar(
            "UPDATE teams_carpetas SET descripcion = ? WHERE id = ?",
            [$descripcion, $carpetaId]
        );

        // Responder con json si viene de fetch, o redireccionar
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
        }

        flash('success', 'Descripción actualizada.');
        redirigir('teams/sitio/' . $carpeta['sitio_id']);
    }

    public function desindexarBulk(string $id): void
    {
        $u = Auth::usuario();
        $sitioId = (int) $id;

        $sitio = Database::una(
            "SELECT * FROM teams_sitios WHERE id = ? AND usuario_id = ?",
            [$sitioId, $u['id']]
        );

        if (!$sitio) {
            flash('danger', 'Acceso no autorizado.');
            redirigir('teams');
        }

        $ids = $_POST['ids'] ?? [];
        if (is_array($ids) && !empty($ids)) {
            $ids = array_map('intval', $ids);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            
            Database::ejecutar(
                "DELETE FROM teams_carpetas WHERE sitio_id = ? AND id IN ($placeholders)",
                array_merge([$sitioId], $ids)
            );
            
            flash('success', "Se desindexaron " . count($ids) . " carpetas correctamente.");
        }

        redirigir('teams/sitio/' . $sitioId);
    }

    public function eliminarCarpeta(string $id): void
    {
        $u = Auth::usuario();
        $id = (int) $id;

        $carpeta = Database::una(
            "SELECT c.*, s.usuario_id 
               FROM teams_carpetas c
               JOIN teams_sitios s ON s.id = c.sitio_id
              WHERE c.id = ?",
            [$id]
        );

        if (!$carpeta || (int)$carpeta['usuario_id'] !== (int)$u['id']) {
            flash('danger', 'Acceso no autorizado.');
            redirigir('teams');
        }

        Database::ejecutar("DELETE FROM teams_carpetas WHERE id = ?", [$id]);
        flash('success', 'Carpeta desindexada.');
        redirigir('teams/sitio/' . $carpeta['sitio_id']);
    }

    public function crearTicket(string $id): void
    {
        $u = Auth::usuario();
        $carpetaId = (int) $id;
        $notas = trim($_POST['notas'] ?? '');
        $semaforo = $_POST['semaforo'] ?? 'verde';
        $fechaLimite = $_POST['fecha_limite'] ?? '';
        $destinatarios = $_POST['destinatarios'] ?? [];

        if ($notas === '') {
            flash('danger', 'Las notas del ticket no pueden estar vacías.');
            redirigir('teams');
        }

        $carpeta = Database::una("SELECT id FROM teams_carpetas WHERE id = ?", [$carpetaId]);
        if (!$carpeta) {
            flash('danger', 'La carpeta asociada no existe.');
            redirigir('teams');
        }

        $fechaLimiteDb = ($fechaLimite !== '') ? $fechaLimite : null;

        try {
            $ticketId = Database::ejecutar(
                "INSERT INTO teams_tickets (carpeta_id, creador_id, notas, semaforo, fecha_limite) VALUES (?, ?, ?, ?, ?)",
                [$carpetaId, $u['id'], $notas, $semaforo, $fechaLimiteDb]
            );

            if (is_array($destinatarios) && !empty($destinatarios)) {
                foreach ($destinatarios as $destId) {
                    Database::ejecutar(
                        "INSERT INTO teams_ticket_destinatarios (ticket_id, usuario_id) VALUES (?, ?)",
                        [$ticketId, (int)$destId]
                    );
                }
            } else {
                Database::ejecutar(
                    "INSERT INTO teams_ticket_destinatarios (ticket_id, usuario_id) VALUES (?, ?)",
                    [$ticketId, $u['id']]
                );
            }

            flash('success', 'Ticket creado y asignado con éxito.');
        } catch (\Throwable $e) {
            flash('danger', 'Error al crear el ticket: ' . $e->getMessage());
        }

        redirigir('teams');
    }

    public function completarTicket(string $id): void
    {
        $u = Auth::usuario();
        $ticketId = (int) $id;

        $dest = Database::una(
            "SELECT * FROM teams_ticket_destinatarios WHERE ticket_id = ? AND usuario_id = ?",
            [$ticketId, $u['id']]
        );

        if (!$dest) {
            flash('danger', 'No tienes asignado este ticket o ya fue completado.');
            redirigir('teams');
        }

        $archivo = null;
        try {
            if (isset($_FILES['archivo_evidencia']) && $_FILES['archivo_evidencia']['error'] !== UPLOAD_ERR_NO_FILE) {
                $archivo = guardar_archivo('archivo_evidencia', 'teams_tickets');
            }
        } catch (\Throwable $e) {
            flash('danger', 'Error al subir el archivo: ' . $e->getMessage());
            redirigir('teams');
        }

        Database::ejecutar(
            "UPDATE teams_ticket_destinatarios 
                SET completado = 1, archivo_subido = ?, fecha_completado = CURRENT_TIMESTAMP
              WHERE ticket_id = ? AND usuario_id = ?",
            [$archivo, $ticketId, $u['id']]
        );

        flash('success', '¡Ticket completado con éxito!');
        redirigir('teams');
    }

    public function eliminarTicket(string $id): void
    {
        $u = Auth::usuario();
        $ticketId = (int) $id;

        $ticket = Database::una(
            "SELECT * FROM teams_tickets WHERE id = ? AND creador_id = ?",
            [$ticketId, $u['id']]
        );

        if (!$ticket) {
            flash('danger', 'No tienes permisos para eliminar este ticket.');
            redirigir('teams');
        }

        Database::ejecutar("DELETE FROM teams_tickets WHERE id = ?", [$ticketId]);
        flash('success', 'Ticket eliminado.');
        redirigir('teams');
    }

    public function descargarEvidencia(string $id): never
    {
        $u = Auth::usuario();
        $destId = (int) $id;

        $dest = Database::una(
            "SELECT td.*, t.creador_id 
               FROM teams_ticket_destinatarios td
               JOIN teams_tickets t ON t.id = td.ticket_id
              WHERE td.id = ?",
            [$destId]
        );

        if (!$dest || ((int)$dest['usuario_id'] !== (int)$u['id'] && (int)$dest['creador_id'] !== (int)$u['id'])) {
            http_response_code(403);
            echo "Acceso denegado.";
            exit;
        }

        if (empty($dest['archivo_subido'])) {
            http_response_code(404);
            echo "No se ha subido ningún archivo.";
            exit;
        }

        descargar_archivo($dest['archivo_subido']);
    }

    public function vincular(): void
    {
        $u = Auth::usuario();
        $email = trim($this->post('email'));
        if ($email === '' || !str_contains($email, '@')) {
            flash('error', 'Correo institucional no válido.');
            redirigir('teams');
        }

        try {
            $existe = Database::una('SELECT 1 FROM teams_sitios WHERE usuario_id = ? AND microsoft_email = ?', [$u['id'], $email]);
            if (!$existe) {
                // Registrar sitio de ejemplo
                $sitioId = Database::ejecutar(
                    "INSERT INTO teams_sitios (usuario_id, nombre, url_base, microsoft_email) VALUES (?, ?, ?, ?)",
                    [$u['id'], 'Equipo ' . explode('@', $email)[0], 'https://alumnouaemex.sharepoint.com/sites/' . explode('@', $email)[0], $email]
                );
                // Registrar carpetas de ejemplo
                Database::ejecutar(
                    "INSERT INTO teams_carpetas (sitio_id, ruta_relativa, descripcion) VALUES (?, ?, ?)",
                    [$sitioId, 'Documentos Compartidos/General', 'Carpeta general de la cuenta ' . $email]
                );
                Database::ejecutar(
                    "INSERT INTO teams_carpetas (sitio_id, ruta_relativa, descripcion) VALUES (?, ?, ?)",
                    [$sitioId, 'Documentos Compartidos/Proyectos', 'Carpeta de proyectos vinculada']
                );
            }
            $_SESSION['active_teams_email'] = $email;
            flash('exito', "Cuenta $email vinculada y activada correctamente.");
        } catch (\Exception $e) {
            flash('error', 'Error al vincular cuenta: ' . $e->getMessage());
        }

        redirigir('teams');
    }

    public function desvincular(): void
    {
        $u = Auth::usuario();
        $email = trim($this->post('email'));
        if ($email === '') {
            flash('error', 'Cuenta no especificada.');
            redirigir('teams');
        }

        if ($email === $u['email']) {
            flash('error', 'No puedes desvincular tu cuenta personal principal de la plataforma.');
            redirigir('teams');
        }

        try {
            Database::ejecutar('DELETE FROM teams_sitios WHERE microsoft_email = ? AND usuario_id = ?', [$email, $u['id']]);
            if (isset($_SESSION['active_teams_email']) && $_SESSION['active_teams_email'] === $email) {
                $_SESSION['active_teams_email'] = $u['email'];
            }
            flash('exito', "Cuenta $email desvinculada correctamente.");
        } catch (\Exception $e) {
            flash('error', 'Error al desvincular cuenta: ' . $e->getMessage());
        }

        redirigir('teams');
    }
}
