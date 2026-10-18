<?php
declare(strict_types=1);

namespace App\Modules\Firmas;

use App\Core\App;

/** Puente con el servicio criptográfico PAdES; nunca recibe llaves privadas. */
final class PadesSigner
{
    public static function habilitado(): bool
    {
        return (bool) App::config('firma_pki.enabled', false);
    }

    public static function estado(): array
    {
        if (!self::habilitado()) {
            return ['ok' => false, 'message' => 'La infraestructura de firma aún no está habilitada.'];
        }
        return self::ejecutar(['command' => 'status']);
    }

    public static function emitir(int $usuarioId, string $nombre, string $email): array
    {
        return self::ejecutar([
            'command' => 'issue', 'user_id' => $usuarioId, 'name' => $nombre,
            'email' => $email, 'valid_days' => (int) App::config('firma_pki.user_cert_days', 365),
        ]);
    }

    public static function firmar(array $datos): array
    {
        return self::ejecutar(['command' => 'sign'] + $datos);
    }

    public static function certificadoRaiz(): string
    {
        return (string) App::config('firma_pki.root_cert', '');
    }

    private static function ejecutar(array $peticion): array
    {
        if (!self::habilitado()) {
            throw new \RuntimeException('La firma PAdES con PKI interna no está habilitada.');
        }
        $base = [
            'root_cert' => App::config('firma_pki.root_cert'),
            'intermediate_cert' => App::config('firma_pki.intermediate_cert'),
            'intermediate_key' => App::config('firma_pki.intermediate_key'),
            'intermediate_pass' => App::config('firma_pki.intermediate_pass'),
            'master_key' => App::config('firma_pki.master_key'),
            'user_dir' => App::config('firma_pki.user_dir'),
        ];
        $python = (string) App::config('firma_pki.python', 'python3');
        $script = (string) App::config('firma_pki.service_script');
        $command = escapeshellarg($python) . ' ' . escapeshellarg($script);
        $pipes = [];
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null, [
            'PYTHONUNBUFFERED' => '1', 'PATH' => getenv('PATH') ?: '',
        ]);
        if (!is_resource($process)) {
            throw new \RuntimeException('No fue posible iniciar el servicio criptográfico.');
        }
        fwrite($pipes[0], json_encode($base + $peticion, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        $exit = proc_close($process);
        $respuesta = json_decode($stdout ?: '', true);
        if ($exit !== 0 || !is_array($respuesta) || empty($respuesta['ok'])) {
            $mensaje = is_array($respuesta) ? ($respuesta['message'] ?? 'Error criptográfico.') : trim($stderr);
            throw new \RuntimeException($mensaje ?: 'El servicio criptográfico no respondió correctamente.');
        }
        return $respuesta;
    }
}
