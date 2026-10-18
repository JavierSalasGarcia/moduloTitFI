<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Services;

use App\Modules\Reuniones\Repositories\ReunionRepository;
use App\Core\Database;

final class ConfirmacionNotifier
{
    public function enviar(array $reunion, int $id, ReunionRepository $repositorio): void
    {
        $asunto = 'Fecha confirmada: ' . $reunion['asunto'];
        $mailer = new HostingerMailer();
        $participantes = Database::todas(
            'SELECT id, correo, nombre FROM reuniones_participantes WHERE reunion_id = ? AND estado_invitacion <> \'revocada\'',
            [$id]
        );
        foreach ($participantes as $p) {
            $token = $repositorio->generarTokenExterno((int) $p['id']);
            $enlace = url('reuniones/invitacion/' . rawurlencode($token));
            $texto = "La reunión #{$id} tiene una fecha y hora confirmadas.\n\nConsulta el expediente y detalles aquí: {$enlace}";
            $ok = $mailer->enviar($p['correo'], $asunto, $texto);
            $repositorio->registrarNotificacion($id, $p['correo'], $ok ? 'enviado' : 'fallido', $ok ? null : 'No se pudo enviar la confirmación.', 'confirmacion');
        }
    }
}
