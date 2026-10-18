<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Services;

interface MailerInterface
{
    public function enviar(string $destinatario, string $asunto, string $texto, string $html = ''): bool;
}
