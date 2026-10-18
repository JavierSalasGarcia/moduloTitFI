<?php
declare(strict_types=1);

require_once __DIR__ . '/util.php';

function base_url(): string
{
    return rtrim((string) (config()['base_url'] ?? '/citas'), '/');
}

function ruta(string $destino = ''): string
{
    return base_url() . ($destino ? '/' . ltrim($destino, '/') : '');
}

function csrf(): string
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = u_token(16);
    }
    return $_SESSION['csrf'];
}

function csrf_valido(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $enviado = (string) ($_POST['csrf'] ?? '');
    return $enviado !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $enviado);
}

function campo_csrf(): string
{
    return '<input type="hidden" name="csrf" value="' . u_escapar(csrf()) . '">';
}

/**
 * Conserva brevemente el destino de una confirmacion terminada.
 *
 * Los navegadores pueden enviar dos veces el formulario (por doble clic o por
 * reintento de red). La primera peticion consume el codigo; la segunda debe
 * volver al resultado ya creado, no afirmar que el codigo era incorrecto.
 */
function confirmacion_recordar(int $verificacionId, string $destino): void
{
    if ($verificacionId <= 0) return;
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();

    $ahora = time();
    $confirmaciones = is_array($_SESSION['fila_confirmaciones'] ?? null)
        ? $_SESSION['fila_confirmaciones'] : [];
    foreach ($confirmaciones as $id => $confirmacion) {
        if (!is_array($confirmacion) || (int) ($confirmacion['momento'] ?? 0) < $ahora - 3600) {
            unset($confirmaciones[$id]);
        }
    }
    $confirmaciones[$verificacionId] = ['destino'=>$destino, 'momento'=>$ahora];
    $_SESSION['fila_confirmaciones'] = array_slice($confirmaciones, -10, null, true);
}

function confirmacion_destino(int $verificacionId): ?string
{
    if ($verificacionId <= 0) return null;
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    $confirmacion = $_SESSION['fila_confirmaciones'][$verificacionId] ?? null;
    if (!is_array($confirmacion) || (int) ($confirmacion['momento'] ?? 0) < time() - 3600) {
        unset($_SESSION['fila_confirmaciones'][$verificacionId]);
        return null;
    }
    $destino = (string) ($confirmacion['destino'] ?? '');
    return $destino !== '' ? $destino : null;
}

function render(string $vista, array $datos = [], array $opciones = []): void
{
    $contenido = parcial($vista, $datos);
    $titulo = $opciones['titulo'] ?? 'Citas';
    $dep = $datos['dep'] ?? null;
    $desnudo = !empty($opciones['desnudo']);   // la pantalla del monitor va sin barra
    $ocultarPlan = !empty($opciones['ocultar_plan']);
    include __DIR__ . '/../vistas/base.php';
}

function parcial(string $vista, array $contexto = []): string
{
    extract($contexto, EXTR_SKIP);
    ob_start();
    include __DIR__ . '/../vistas/' . $vista . '.php';
    return (string) ob_get_clean();
}
