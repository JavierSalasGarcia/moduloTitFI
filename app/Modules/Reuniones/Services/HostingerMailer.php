<?php
declare(strict_types=1);

namespace App\Modules\Reuniones\Services;

use App\Core\App;

final class HostingerMailer implements MailerInterface
{
    public function enviar(string $destinatario, string $asunto, string $texto, string $html = ''): bool
    {
        $token = (string) App::config('correo.api_token', '');
        $remitente = (string) App::config('correo.remitente', '');
        $nombre = (string) App::config('correo.nombre_remitente', 'Traza FI');
        if (!function_exists('curl_init') || $token === '' || !filter_var($destinatario, FILTER_VALIDATE_EMAIL) || $remitente === '') return false;

        $mailboxId = $this->mailboxId($token, $remitente);
        if ($mailboxId === null) return false;

        $payload = [
            'to' => [$destinatario], 'displayName' => $nombre, 'subject' => $asunto,
            'text' => $texto, 'html' => $html !== '' ? $html : nl2br(htmlspecialchars($texto, ENT_QUOTES, 'UTF-8')),
        ];
        $ch = curl_init('https://api.mail.hostinger.com/api/v1/mailboxes/' . rawurlencode($mailboxId) . '/send');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_POSTFIELDS => json_encode($payload), CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Content-Type: application/json']]);
        $response = curl_exec($ch);
        if ($response === false) { curl_close($ch); return false; }
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return in_array($status, [200, 204], true);
    }

    private function mailboxId(string $token, string $remitente): ?string
    {
        $ch = curl_init('https://api.mail.hostinger.com/api/v1/me');
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_HTTPHEADER => ["Authorization: Bearer {$token}", 'Content-Type: application/json']]);
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($status !== 200 || !$response) return null;
        foreach ((array) (json_decode($response, true)['data']['mailboxes'] ?? []) as $mailbox) {
            if (strcasecmp((string) ($mailbox['address'] ?? ''), $remitente) === 0) return (string) ($mailbox['resourceId'] ?? '');
        }
        return null;
    }
}
