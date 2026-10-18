<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Router con patrones tipo "GET /solicitudes/{id}".
 * Funciona con Apache (.htaccess) y con el servidor embebido de PHP.
 * Aplica sesión, roles de módulo y CSRF (todo POST) de forma central.
 */
final class Router
{
    /** @var array[] */
    private array $rutas = [];

    public function registrarModulo(array $modulo): void
    {
        foreach ($modulo['rutas'] as $patron => $destino) {
            [$metodo, $ruta] = explode(' ', $patron, 2);
            $partes = preg_split('/(\{[a-z][A-Za-z0-9_]*\})/', rtrim($ruta, '/'), -1, PREG_SPLIT_DELIM_CAPTURE);
            $regex = '';
            foreach ($partes as $parte) {
                if (preg_match('/^\{([a-z][A-Za-z0-9_]*)\}$/D', $parte, $parametro)) {
                    $regex .= '(?P<' . $parametro[1] . '>[^/]+)';
                } else {
                    $regex .= preg_quote($parte, '#');
                }
            }
            $regex = '#^' . $regex . '/?$#D';
            $this->rutas[] = [
                'metodo'  => strtoupper($metodo),
                'regex'   => $regex,
                'destino' => [$destino[0], $destino[1]],
                'publico' => !empty($modulo['publico']) || (($destino[2] ?? '') === 'publico'),
                'roles'   => $modulo['roles'] ?? null,
                'modulo'  => $modulo['clave'],
            ];
        }
    }

    public function despachar(): void
    {
        Auth::validarVigencia();
        AccessControl::refresh();
        $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $base   = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
        $ruta   = rawurldecode(parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
        if (str_contains($ruta, "\0") || str_contains($ruta, '\\')) {
            http_response_code(400);
            exit('Ruta no válida.');
        }
        if ($base && str_starts_with($ruta, $base)) {
            $ruta = substr($ruta, strlen($base));
        }
        $ruta = '/' . trim($ruta, '/');
        if ($ruta === '/index.php') {
            $ruta = '/';
        }

        foreach ($this->rutas as $r) {
            if ($r['metodo'] === $metodo && preg_match($r['regex'], $ruta, $m)) {
                $usuario = Auth::usuario();
                if (!$r['publico']) {
                    if (!$usuario) {
                        redirigir('login');
                    }
                    if ($r['roles'] && !in_array($usuario['rol'], $r['roles'], true)) {
                        Audit::record($r['modulo'].'::acceso','denegado','Permisos insuficientes');
                        http_response_code(403);
                        View::render('errores/403', ['titulo' => 'Acceso restringido']);
                        return;
                    }
                }
                if (in_array($metodo, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && !Auth::csrfValido()) {
                    Audit::record($r['modulo'].'::csrf','denegado','Sesión o formulario no válido');
                    http_response_code(419);
                    exit('Sesión expirada. Regresa y vuelve a intentarlo.');
                }
                [$clase, $accion] = $r['destino'];
                if (isset($_SESSION['impersonador']) && ($r['modulo']==='seguridad' || $r['modulo']==='auth') && !in_array($accion,['salir','volver'],true)) {
                    http_response_code(403); exit('Vuelve a tu cuenta para gestionar credenciales o verificar un correo.');
                }
                if ($metodo !== 'GET' && AccessControl::pending() && !in_array($ruta,['/cuenta/verificar','/cuenta/reenviar','/cuenta/volver','/consulta'],true)) AccessControl::deny();
                // El editor público guarda su propia bitácora de revisiones y debe
                // poder consultarse aunque la BD principal no esté disponible.
                if (!($r['modulo'] === 'plan' && !$usuario)) {
                    Audit::record($r['modulo'].'::'.$accion,'solicitado');
                }
                $params = array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
                (new $clase($r['modulo']))->$accion(...array_values($params));
                return;
            }
        }

        http_response_code(404);
        View::render('errores/404', ['titulo' => 'Página no encontrada'], Auth::usuario() ? 'app' : 'auth');
    }
}
