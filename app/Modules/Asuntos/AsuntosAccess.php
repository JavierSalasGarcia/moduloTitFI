<?php
declare(strict_types=1);

namespace App\Modules\Asuntos;

use App\Core\AccessControl;
use App\Core\App;
use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;

final class AsuntosAccess
{
    public const CUERPO_FOLIO = '(?:[2-9A-HJ-NP-Z]{5}-){4}[2-9A-HJ-NP-Z]{6}';

    public static function oficinaPorRuta(string $ruta): ?array
    {
        $ruta = strtolower(trim($ruta));
        foreach ((array) App::config('asuntos.oficinas', []) as $codigo => $oficina) {
            if (strtolower((string) ($oficina['ruta'] ?? '')) === $ruta) {
                $oficina['codigo'] = strtoupper((string) $codigo);
                return $oficina;
            }
        }
        return null;
    }

    public static function folioSolicitud(string $codigoOficina): string
    {
        $consulta = rawurldecode((string) ($_SERVER['QUERY_STRING'] ?? ''));
        $folio = strtoupper(trim($consulta));
        $regex = '/^' . preg_quote(strtoupper($codigoOficina), '/') . '-' . self::CUERPO_FOLIO . '$/D';
        return preg_match($regex, $folio) ? $folio : '';
    }

    public static function esGestor(array $oficina): bool
    {
        $u = Auth::usuario();
        if (!$u) {
            return false;
        }
        $email = strtolower(trim((string) ($u['email'] ?? '')));
        $gestores = array_map(static fn($valor): string => strtolower(trim((string) $valor)), (array) ($oficina['gestores'] ?? []));
        if (in_array($email, $gestores, true)) {
            return true;
        }
        return in_array((string) ($u['rol'] ?? ''), ['admin', 'titular', 'operativo'], true)
            && (($u['rol'] ?? '') === 'admin'
                || in_array((string) ($u['depto_clave'] ?? ''), (array) ($oficina['departamentos'] ?? []), true));
    }

    public static function puedeVer(int $expedienteId): bool
    {
        $email = strtolower(trim((string) (Auth::usuario()['email'] ?? '')));
        return $email !== '' && (int) Database::valor(
            'SELECT COUNT(*) FROM asunto_expediente_accesos WHERE expediente_id = ? AND email = ?',
            [$expedienteId, $email]
        ) > 0;
    }

    public static function limitarConsulta(): void
    {
        $limite = (int) App::config('asuntos.max_consultas_15_min', 120);
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'sin-ip');
        $usuario = Auth::id();
        $excedido = AccessControl::limited('asuntos:ip:' . $ip, max(30, $limite * 2), 900)
            || ($usuario > 0 && AccessControl::limited('asuntos:usuario:' . $usuario, max(20, $limite), 900));
        if ($excedido) {
            Audit::record('asuntos::limite', 'denegado', 'Demasiadas consultas de folios');
            http_response_code(429);
            header('Retry-After: 900');
            exit('Demasiadas consultas. Intenta de nuevo dentro de 15 minutos.');
        }
    }

    public static function emailInstitucional(string $email): bool
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }
        $dominio = substr(strrchr($email, '@') ?: '', 1);
        foreach ((array) App::config('asuntos.dominios_correo', ['uaemex.mx']) as $permitido) {
            $permitido = strtolower(trim((string) $permitido));
            if ($dominio === $permitido || str_ends_with($dominio, '.' . $permitido)) {
                return true;
            }
        }
        return false;
    }
}
