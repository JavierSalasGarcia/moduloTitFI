<?php
declare(strict_types=1);

namespace App\Modules\Portal;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

final class PortalController extends Controller
{
    public function index(): void
    {
        $this->renderizar(trim((string) ($_GET['q'] ?? '')));
    }

    public function consultar(): void
    {
        $consulta = $this->post('consulta');
        if (mb_strlen($consulta) < 4) {
            $this->renderizar($consulta, null, 'Describe tu necesidad con un poco más de detalle.');
            return;
        }
        if (mb_strlen($consulta) > 1200) {
            $this->renderizar(mb_substr($consulta, 0, 1200), null, 'La consulta es demasiado extensa. Resume lo esencial en menos de 1,200 caracteres.');
            return;
        }

        header('Cache-Control: no-store, private');
        // La persona decide en cada consulta si su pregunta puede salir a los servicios de IA (Jev y Gemini).
        $r = (new AsistenteConsulta())->resolver($consulta, ($_POST['usar_ia'] ?? '') === '1');
        $error = $r['aviso'] ?? ($r['resultado'] === null && !$r['enlaces'] ? 'No encontré una sección o función relacionada. Prueba con otras palabras.' : null);
        $this->renderizar($consulta, $r['resultado'], $error, $r);
    }

    private function renderizar(string $consulta = '', ?array $resultado = null, ?string $error = null, array $extra = []): void
    {
        $this->vista('index', [
            'titulo' => 'Consulta',
            'portalMinimal' => true,
            'consulta' => $consulta,
            'resultado' => $resultado,
            'errorConsulta' => $error,
            'notificaciones' => $this->notificaciones(),
            'resumen' => $resultado === null ? Resumen::de() : null,
            'iaExterna' => JevClient::activo() || GeminiClient::disponible(),
            'iaRestantes' => GeminiClient::restantes(),
            'iaLimite' => GeminiClient::limiteUsuario(),
            'enlaces' => $extra['enlaces'] ?? [],
            'limitado' => (bool) ($extra['limitado'] ?? false),
            'consultado' => $extra !== [],
            'departamentos' => $resultado && ($resultado['tipo']??'')!=='datos' ? Database::todas('SELECT id, nombre FROM departamentos ORDER BY nombre') : [],
        ]);
    }

    private function notificaciones(): array
    {
        $usuario = Auth::usuario();
        $esComunidad = Auth::tieneRol('alumno', 'profesor');

        $tickets = $esComunidad
            ? Database::valor(
                "SELECT COUNT(*) FROM tickets WHERE usuario_id = ? AND estado IN ('abierto','en_atencion')",
                [$usuario['id']]
            )
            : Database::valor(
                "SELECT COUNT(*) FROM tickets WHERE depto_destino_id = ? AND estado IN ('abierto','en_atencion')",
                [Auth::departamentoId()]
            );

        $reuniones = Database::valor(
            "SELECT COUNT(*) FROM reuniones r
               JOIN reuniones_participantes rp ON rp.reunion_id = r.id AND rp.usuario_id = ?
              WHERE rp.estado_invitacion <> 'revocada' AND rp.tipo <> 'organizador'
                AND rp.respondio_en IS NULL AND r.estado IN ('convocada','en_respuesta')",
            [$usuario['id']]
        );

        $comunicados = Database::valor(
            "SELECT COUNT(*) FROM comunicados WHERE creado_en > DATE_SUB(NOW(), INTERVAL 14 DAY) AND categoria <> 'Ocultar'
                AND NOT EXISTS (SELECT 1 FROM ajustes a WHERE a.clave=CONCAT('comunicado_vence_',comunicados.id) AND a.valor<>'' AND a.valor < CURRENT_DATE())" .
            $this->filtroComunicados($usuario)
        );

        return [
            ['ruta' => $esComunidad ? 'tickets' : 'tickets?bandeja=recibidos', 'icono' => 'lista', 'cantidad' => (int) $tickets, 'etiqueta' => 'Solicitudes activas', 'detalle' => 'solicitudes en seguimiento.'],
            ['ruta' => 'reuniones?tab=disponibilidad', 'icono' => 'alerta', 'cantidad' => (int) $reuniones, 'etiqueta' => 'Reuniones pendientes', 'detalle' => 'invitaciones esperan tus horarios.'],
            ['ruta' => 'eventos?recientes=1', 'icono' => 'entrada', 'cantidad' => (int) $comunicados, 'etiqueta' => 'Avisos recientes', 'detalle' => 'avisos publicados en los últimos 14 días.'],
            ['ruta' => 'encuestas?vista=pendientes', 'icono' => 'ok', 'cantidad' => (int) Database::valor("SELECT COUNT(*) FROM encuestas e WHERE e.estado = 'abierta' AND NOT EXISTS (SELECT 1 FROM encuesta_participantes p WHERE p.encuesta_id=e.id AND p.usuario_id=?)", [Auth::id()]), 'etiqueta' => 'Encuestas pendientes', 'detalle' => 'encuestas que todavía no has respondido.'],
        ];
    }

    private function filtroComunicados(array $usuario): string
    {
        if ($usuario['rol'] !== 'alumno' || !$usuario['programa_id']) {
            return '';
        }
        $nivel = $usuario['programa_nivel'] === 'posgrado' ? 'posgrado' : 'licenciatura';
        return " AND (alcance = 'todos' OR alcance = '{$nivel}'
                 OR (alcance = 'programa' AND programa_id = " . (int) $usuario['programa_id'] . '))';
    }
}
