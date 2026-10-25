<?php
/**
 * Helpers globales de vistas y rutas.
 */
declare(strict_types=1);

/** Escapa HTML (usar SIEMPRE al imprimir datos de la BD o del usuario). */
function e(?string $texto): string
{
    return htmlspecialchars($texto ?? '', ENT_QUOTES, 'UTF-8');
}

/** Construye una URL interna respetando la subcarpeta donde viva la app. */
function url(string $ruta = ''): string
{
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
    return $base . '/' . ltrim($ruta, '/');
}

/** Redirige a una ruta interna y termina la ejecución. */
function redirigir(string $ruta): never
{
    if (($_SERVER['HTTP_X_FI_UPLOAD'] ?? '') === '1' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['redirect' => url($ruta)]);
        exit;
    }
    header('Location: ' . url($ruta));
    exit;
}

/** Guarda un mensaje flash de una sola lectura. */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['_flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

/** Recupera y consume el mensaje flash. */
function flash_leer(): ?array
{
    $f = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);
    return $f;
}

/**
 * Ícono SVG inline (sin dependencias externas). Trazos estilo Lucide.
 */
function icono(string $nombre, int $tam = 18): string
{
    static $iconos = [
        'panel'      => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'portafolio' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'flujo'      => '<circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="12" r="3"/><path d="M8.5 7.5 15.5 10.5M8.5 16.5 15.5 13.5"/>',
        'usuarios'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'salir'      => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/>',
        'buscar'     => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
        'enviar'     => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'entrada'    => '<path d="M21 12a9 9 0 1 1-9-9"/><path d="M21 3 12 12"/><path d="M15 3h6v6"/>',
        'reloj'      => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'ok'         => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'alerta'     => '<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>',
        'edificio'   => '<rect x="4" y="2" width="16" height="20" rx="1"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M12 6h.01M8 10h.01M16 10h.01M12 10h.01M8 14h.01M16 14h.01M12 14h.01"/>',
        'lista'      => '<path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/>',
        'flecha-der' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'flecha-izq' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'menu'       => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'cerrar'     => '<path d="M18 6 6 18M6 6l12 12"/>',
        'candado'    => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'usuario'    => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'mas'        => '<path d="M12 5v14M5 12h14"/>',
        'academia'   => '<path d="M22 10 12 5 2 10l10 5 10-5Z"/><path d="M6 12v5c0 1.7 2.7 3 6 3s6-1.3 6-3v-5"/>',
        'laboratorio'=> '<path d="M10 2v7.5L4.5 19a2 2 0 0 0 1.8 3h11.4a2 2 0 0 0 1.8-3L14 9.5V2"/><path d="M8.5 2h7"/><path d="M7 16h10"/>',
        'engrane'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-1-1.51 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.51-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33h.01a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51h.01a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82v.01a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>',
        'premio'     => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13 17 22l-5-3-5 3 1.5-9"/>',
    ];
    $trazo = $iconos[$nombre] ?? $iconos['lista'];
    return '<svg class="icono" width="' . $tam . '" height="' . $tam . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $trazo . '</svg>';
}

/**
 * Guarda un archivo subido en storage/ (FUERA del webroot) validando
 * extensión y tamaño. Devuelve la ruta relativa a storage/ o null si
 * no se envió archivo. Lanza InvalidArgumentException si es inválido.
 * $extPermitidas restringe las extensiones por debajo de la config
 * global (ej. ['pdf'] para documentos a firmar).
 */
function guardar_archivo(string $campo, string $subcarpeta, ?array $extPermitidas = null): ?string
{
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    return guardar_archivo_datos($_FILES[$campo], $subcarpeta, $extPermitidas);
}

/**
 * Guarda VARIOS archivos de un <input type="file" name="campo[]" multiple>.
 * Devuelve [['nombre_original' => ..., 'ruta' => ...], ...] (vacío si no
 * se envió ninguno). Mismas validaciones que guardar_archivo().
 */
function guardar_archivos(string $campo, string $subcarpeta, ?array $extPermitidas = null): array
{
    if (empty($_FILES[$campo]) || !is_array($_FILES[$campo]['name'])) {
        return [];
    }
    $lote = $_FILES[$campo];
    $guardados = [];
    foreach ($lote['name'] as $i => $nombre) {
        if ($lote['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $f = [
            'name'     => $nombre,
            'type'     => $lote['type'][$i],
            'tmp_name' => $lote['tmp_name'][$i],
            'error'    => $lote['error'][$i],
            'size'     => $lote['size'][$i],
        ];
        $guardados[] = [
            'nombre_original' => $nombre,
            'ruta'            => guardar_archivo_datos($f, $subcarpeta, $extPermitidas),
        ];
    }
    return $guardados;
}

/** Núcleo compartido de guardar_archivo/guardar_archivos. */
function guardar_archivo_datos(array $f, string $subcarpeta, ?array $extPermitidas = null): string
{
    $cfg = \App\Core\App::config('archivos');
    $permitidas = $extPermitidas !== null
        ? array_values(array_intersect($cfg['ext_permitidas'], $extPermitidas))
        : $cfg['ext_permitidas'];

    if (!preg_match('#^[a-zA-Z0-9_-]+(?:/[a-zA-Z0-9_-]+)*$#D', trim($subcarpeta, '/\\')) || str_contains($subcarpeta, '..')) {
        throw new InvalidArgumentException('La carpeta de destino no es válida.');
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException('Error al subir el archivo (código ' . $f['error'] . ').');
    }
    if ((int) $f['size'] <= 0 || (int) $f['size'] > $cfg['max_bytes']) {
        throw new InvalidArgumentException('El archivo excede el tamaño máximo de ' . round($cfg['max_bytes'] / 1048576) . ' MB.');
    }
    if (!is_uploaded_file((string) $f['tmp_name'])) {
        throw new InvalidArgumentException('El archivo no proviene de una carga HTTP válida.');
    }
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $permitidas, true)) {
        throw new InvalidArgumentException('Extensión no permitida. Usa: ' . implode(', ', $permitidas) . '.');
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $f['tmp_name']) ?: '';
    $mimes = [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
    ];
    if (!isset($mimes[$ext]) || !in_array($mime, $mimes[$ext], true)) {
        throw new InvalidArgumentException('El contenido del archivo no coincide con su extensión.');
    }

    $dir = rtrim($cfg['storage'], '/\\') . '/' . trim($subcarpeta, '/\\');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('No se pudo crear la carpeta de almacenamiento.');
    }
    $nombreBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($f['name'], PATHINFO_FILENAME));
    $nombre = substr($nombreBase, 0, 60) . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "{$dir}/{$nombre}")) {
        throw new RuntimeException('No se pudo guardar el archivo.');
    }
    return trim($subcarpeta, '/\\') . '/' . $nombre;
}

/** Enlace de descarga con token (archivo/{token}) para una ruta de descarga marcada «token» en su módulo. */
function url_archivo(string $ruta): string
{
    return url(\App\Core\Descargas::url($ruta));
}

/** Envía al navegador un archivo de storage/ (descarga controlada con permisos). */
function descargar_archivo(string $rutaRelativa): never
{
    $base = realpath(\App\Core\App::config('archivos')['storage']);
    $ruta = realpath($base . '/' . $rutaRelativa);
    $base = $base === false ? '' : rtrim($base, '/\\');
    if ($base === '' || $ruta === false || !is_file($ruta)
        || ($ruta !== $base && !str_starts_with($ruta, $base . DIRECTORY_SEPARATOR))) {
        http_response_code(404);
        exit('Archivo no encontrado.');
    }
    $nombre = preg_replace('/-[0-9a-f]{8}(\.\w+)$/', '$1', basename($ruta));
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
    exit;
}

/** Lee un ajuste de la tabla `ajustes` (con valor por defecto). */
function ajuste(string $clave, string $defecto = ''): string
{
    $v = \App\Core\Database::valor('SELECT valor FROM ajustes WHERE clave = ?', [$clave]);
    return $v === false || $v === null ? $defecto : (string) $v;
}

/** Fecha corta legible en español: 08 jul 2026, 14:32 */
function fecha_corta(string $ts): string
{
    $meses = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
    $t = strtotime($ts);
    return sprintf('%02d %s %d, %02d:%02d', (int)date('d', $t), $meses[(int)date('n', $t) - 1], (int)date('Y', $t), (int)date('H', $t), (int)date('i', $t));
}
