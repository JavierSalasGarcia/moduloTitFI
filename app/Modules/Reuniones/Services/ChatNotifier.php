<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Services;

use App\Modules\Reuniones\Repositories\ReunionRepository;
use App\Core\Database;

final class ChatNotifier
{
    public function enviar(int $reunionId, int $emisorId, string $asunto, string $contenido, ReunionRepository $repositorio, string $tipo = 'normal'): void
    {
        $mailer = new HostingerMailer();
        $participantes = Database::todas(
            'SELECT id, correo FROM reuniones_participantes WHERE reunion_id = ? AND id <> ? AND estado_invitacion <> \'revocada\'',
            [$reunionId, $emisorId]
        );
        foreach ($participantes as $p) {
            $token = $repositorio->generarTokenExterno((int) $p['id']);
            $enlace = url('reuniones/invitacion/' . rawurlencode($token));
            $textoMail = "Se ha publicado un nuevo mensaje en el expediente de la reunión \"{$asunto}\".\n\nPor motivos de seguridad, el contenido no se incluye en este correo. Puedes consultar el mensaje directamente en el chat ingresando al siguiente enlace seguro:\n\n{$enlace}";
            $ok = $mailer->enviar($p['correo'], ($tipo === 'urgente' ? 'Mensaje urgente: ' : 'Nuevo mensaje: ') . $asunto, $textoMail);
            $repositorio->registrarNotificacion($reunionId, $p['correo'], $ok ? 'enviado' : 'fallido', $ok ? null : 'No se pudo enviar la notificación del chat.', 'chat', $tipo);
        }
    }
}
