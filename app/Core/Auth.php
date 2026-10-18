<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Autenticación por sesión y token CSRF.
 */
final class Auth
{
    /**
     * Acepta el usuario institucional solo o un correo completo de uaemex.mx
     * (incluidos sus subdominios) y devuelve el correo en minúsculas.
     * Cualquier otro dominio, carácter de control o formato ambiguo se rechaza.
     */
    public static function normalizarCorreoInstitucional(string $entrada): ?string
    {
        $entrada = strtolower(trim($entrada));
        if ($entrada === '' || strlen($entrada) > 75 || preg_match('/[\x00-\x20\x7f]/', $entrada)) {
            return null;
        }

        if (!str_contains($entrada, '@')) {
            $entrada .= '@uaemex.mx';
        }

        if (substr_count($entrada, '@') !== 1) {
            return null;
        }

        [$usuario, $dominio] = explode('@', $entrada, 2);
        $esDominioUaemex = $dominio === 'uaemex.mx'
            || str_ends_with($dominio, '.uaemex.mx');
        if (!$esDominioUaemex
            || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*uaemex\.mx$/D', $dominio)
            || filter_var($entrada, FILTER_VALIDATE_EMAIL) === false) {
            return null;
        }

        return $entrada;
    }

    public static function intentar(string $email, string $clave): bool
    {
        $email = self::normalizarCorreoInstitucional($email) ?? '';
        if ($email === '') {
            return false;
        }
        $u = Database::una(
            'SELECT u.*, d.nombre AS departamento, d.clave AS depto_clave, a.nombre AS area,
                    p.siglas AS programa_siglas, p.nombre AS programa, p.nivel AS programa_nivel
               FROM usuarios u
               LEFT JOIN departamentos d ON d.id = u.departamento_id
               LEFT JOIN areas a ON a.id = d.area_id
               LEFT JOIN programas p ON p.id = u.programa_id
              WHERE u.email = ? AND u.activo = 1',
            [$email]
        );
        if ($u && (int)$u['permite_contrasena'] === 0) {
            return false;
        }
        if ($u && password_verify($clave, $u['password_hash'])) {
            $nuevosRestantes = max(0, ((int)$u['logins_contrasena_restantes']) - 1);
            $nuevoPermite = $nuevosRestantes > 0 ? 1 : 0;
            AccessControl::internal(fn()=>Database::ejecutar(
                'UPDATE usuarios SET logins_contrasena_restantes = ?, permite_contrasena = ? WHERE id = ?',
                [$nuevosRestantes, $nuevoPermite, $u['id']]
            ));
            $u['logins_contrasena_restantes'] = $nuevosRestantes;
            $u['permite_contrasena'] = $nuevoPermite;

            unset($u['password_hash']);
            session_regenerate_id(true);
            $_SESSION['usuario'] = $u;
            $_SESSION['_ultima_actividad'] = time();
            Audit::record('inicio_sesion','correcto','Contraseña');
            return true;
        }
        return false;
    }

    public static function iniciarSesion(array $u): void
    {
        if (isset($u['id']) && (int)$u['logins_contrasena_restantes'] > 0) {
            $nuevosRestantes = max(0, ((int)$u['logins_contrasena_restantes']) - 1);
            $nuevoPermite = $nuevosRestantes > 0 ? 1 : 0;
            AccessControl::internal(fn()=>Database::ejecutar(
                'UPDATE usuarios SET logins_contrasena_restantes = ?, permite_contrasena = ? WHERE id = ?',
                [$nuevosRestantes, $nuevoPermite, $u['id']]
            ));
            $u['logins_contrasena_restantes'] = $nuevosRestantes;
            $u['permite_contrasena'] = $nuevoPermite;
        }

        unset($u['password_hash']);
        session_regenerate_id(true);
        $_SESSION['usuario'] = $u;
        $_SESSION['_ultima_actividad'] = time();
        Audit::record('inicio_sesion','correcto','Acceso verificado por el método de autenticación');
    }

    /** Expira sesiones autenticadas inactivas para reducir secuestros persistentes. */
    public static function validarVigencia(): void
    {
        if (!self::usuario()) return;
        $ultima = (int) ($_SESSION['_ultima_actividad'] ?? time());
        if (time() - $ultima > 7200) {
            self::salir();
            redirigir('login');
        }
        $_SESSION['_ultima_actividad'] = time();
    }

    public static function salir(): void
    {
        Audit::record('cierre_sesion');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /** Usuario en sesión (o null). */
    public static function usuario(): ?array
    {
        return $_SESSION['usuario'] ?? null;
    }

    public static function departamentoId(): int
    {
        return (int) (self::usuario()['departamento_id'] ?? 0);
    }

    public static function id(): int
    {
        return (int) (self::usuario()['id'] ?? 0);
    }

    public static function rol(): string
    {
        return self::usuario()['rol'] ?? '';
    }

    /** ¿El usuario tiene alguno de estos roles? */
    public static function tieneRol(string ...$roles): bool
    {
        return in_array(self::rol(), $roles, true);
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['_csrf'];
    }

    public static function csrfValido(): bool
    {
        return hash_equals(self::csrfToken(), $_POST['_csrf'] ?? '');
    }

    /** Conserva únicamente destinos internos reconocidos después del login. */
    public static function recordarDestino(string $ruta): void
    {
        if (self::destinoPermitido($ruta)) {
            $_SESSION['_destino_login'] = $ruta;
        }
    }

    /** Recupera una sola vez el destino pendiente; nunca acepta una URL externa. */
    public static function consumirDestino(): string
    {
        $ruta = (string) ($_SESSION['_destino_login'] ?? '');
        unset($_SESSION['_destino_login']);
        return self::destinoPermitido($ruta) ? $ruta : '';
    }

    /** Destinos internos que pueden retomarse tras iniciar sesión (folios de la fila y revisión de protocolos). */
    private static function destinoPermitido(string $ruta): bool
    {
        return (bool) preg_match('#^fila/[a-z0-9_-]{2,30}/reg\.php\?[A-Z][A-Z0-9]{1,5}-[A-Z2-9-]{30,40}$#', $ruta)
            || (bool) preg_match('#^titulacion/revision/t/[A-Za-z0-9_-]{40,50}$#', $ruta);
    }
}
