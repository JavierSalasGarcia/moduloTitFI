<?php
/**
 * Configuración de la Plataforma FI.
 * Los valores por defecto son los del hosting de producción.
 * Para desarrollo local crea app/config.local.php (NO subirlo al hosting):
 *   <?php return ['db' => ['nombre' => 'plataforma_fi', 'usuario' => 'root', 'clave' => '']];
 */
$config = [
    'app' => [
        'nombre'  => 'Plataforma FI',
        'entidad' => 'Facultad de Ingeniería · UAEMéx',
        'url_publica' => 'https://fingenieria.mx',
        'debug'   => false,
    ],
    'db' => [
        'host'    => getenv('TRAZAFI_DB_HOST') ?: 'localhost',
        'nombre'  => getenv('TRAZAFI_DB_NAME') ?: '',
        'usuario' => getenv('TRAZAFI_DB_USER') ?: '',
        'clave'   => getenv('TRAZAFI_DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'correo' => [
        'proveedor' => 'hostinger',
        'remitente' => 'contacto@fingenieria.mx',
        'nombre_remitente' => 'Traza FI',
        'api_token' => getenv('TRAZAFI_MAIL_TOKEN') ?: '',
    ],
    'archivos' => [
        // Fuera del webroot (public/), conforme a la plantilla de seguridad
        'storage'        => dirname(__DIR__) . '/storage',
        'ext_permitidas' => ['pdf', 'jpg', 'jpeg', 'png', 'docx', 'xlsx', 'pptx', 'zip'],
        'max_bytes'      => 10 * 1024 * 1024, // 10 MB
    ],
    'firma_pki' => [
        // Activar únicamente después de la ceremonia institucional y de aplicar
        // database/migrations/009_firma_pades_pki.sql.
        'enabled'           => false,
        'python'            => PHP_OS_FAMILY === 'Windows' ? 'python' : '/opt/traza-fi-pades/bin/python3',
        'service_script'    => dirname(__DIR__) . '/services/pades/pades_service.py',
        // Todos estos archivos están fuera de public/. La llave raíz NO reside en este servidor.
        'root_cert'         => dirname(__DIR__) . '/storage/pki/public/traza-fi-root-ca.crt.pem',
        'intermediate_cert' => dirname(__DIR__) . '/storage/pki/public/traza-fi-intermediate-ca.crt.pem',
        'intermediate_key'  => dirname(__DIR__) . '/storage/pki/private/traza-fi-intermediate-ca.key.pem',
        'intermediate_pass' => dirname(__DIR__) . '/storage/pki/secrets/intermediate-ca.pass',
        'master_key'        => dirname(__DIR__) . '/storage/pki/secrets/master.key',
        'audit_hmac_key'    => dirname(__DIR__) . '/storage/pki/secrets/audit-hmac.key',
        'user_dir'          => dirname(__DIR__) . '/storage/pki/users',
        'user_cert_days'    => 365,
    ],
    'titulacion' => [
        // Personal que valida trámites de titulación: cuentas de estas dependencias
        // (roles titular, operativo o coordinador) y los correos listados como gestores.
        'departamentos' => [
            'departamento_de_evaluacion_profesional_y_grado',
            'subdireccion_academica',
            'titular_de_la_subdireccion_academica',
        ],
        'gestores' => ['jsalasg@uaemex.mx'],
    ],
    'asuntos' => [
        // Se aceptan el dominio exacto y sus subdominios institucionales.
        'dominios_correo' => ['uaemex.mx', 'fingenieria.mx'],
        'max_consultas_15_min' => 120,
        // Cada oficina define su prefijo, segmento de URL y gestores. Agregar
        // oficinas aquí no requiere crear controladores ni tablas adicionales.
        'oficinas' => [
            'SA' => [
                'ruta' => 'sa',
                'nombre' => 'Subdirección Académica',
                'departamentos' => [
                    'subdireccion_academica',
                    'titular_de_la_subdireccion_academica',
                ],
                'gestores' => ['academica_fi@uaemex.mx', 'jsalasg@uaemex.mx'],
            ],
        ],
    ],
];

$local = __DIR__ . '/config.local.php';
if (is_file($local)) {
    $config = array_replace_recursive($config, require $local);
}

return $config;
