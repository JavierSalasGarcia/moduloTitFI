<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Único lugar donde se leen y guardan las claves de los servicios de IA (docs/ASISTENTE_JEV_GEMINI.md).
 *
 * Orden de lectura (gana el primero que exista):
 *   1. Variables de entorno del servidor: TRAZAFI_JEV_API_KEY y TRAZAFI_GEMINI_API_KEY.
 *   2. app/config.local.php → 'ia' => ['jev_api_key' => '…', 'gemini_api_key' => '…']   (solo desarrollo; no se sube al hosting)
 *   3. Ajustes → Asistente (tabla `ajustes`, claves ia_jev_api_key / ia_gemini_api_key), cifradas en reposo con Cifrado.
 * Las claves nunca se devuelven a un formulario, a JavaScript ni a la bitácora.
 */
final class SecretosIa
{
    public const SERVICIOS = ['jev', 'gemini'];

    public static function clave(string $servicio): string
    {
        if (!in_array($servicio, self::SERVICIOS, true)) {
            return '';
        }
        $local = self::externa($servicio);
        if ($local !== '') {
            return self::limpia($local);
        }
        try {
            $v = ajuste('ia_' . $servicio . '_api_key');
            return self::limpia((string) Cifrado::descifrar($v, self::contexto($servicio)));
        } catch (\Throwable $e) {
            error_log('Clave de IA ilegible (' . $servicio . '): ' . $e->getMessage());
            return '';
        }
    }

    /** Clave definida fuera de la base: variable de entorno o config.local.php. */
    private static function externa(string $servicio): string
    {
        $env = getenv('TRAZAFI_' . strtoupper($servicio) . '_API_KEY');
        return trim(is_string($env) && $env !== '' ? $env : (string) App::config('ia.' . $servicio . '_api_key', ''));
    }

    /** 'config' (variable de entorno o config.local.php), 'ajustes' (pantalla de Ajustes) o '' si no hay clave. */
    public static function origen(string $servicio): string
    {
        if (in_array($servicio, self::SERVICIOS, true) && self::externa($servicio) !== '') {
            return 'config';
        }
        return self::clave($servicio) !== '' ? 'ajustes' : '';
    }

    /** Guarda (cifrada si hay llave de cifrado) o elimina la clave en Ajustes. */
    public static function guardar(string $servicio, string $valor): void
    {
        if (!in_array($servicio, self::SERVICIOS, true)) {
            throw new \InvalidArgumentException('Servicio de IA desconocido.');
        }
        $valor = trim($valor);
        if ($valor !== '' && self::limpia($valor) === '') {
            throw new \InvalidArgumentException('La clave tiene un formato no válido.');
        }
        if ($valor !== '' && !Cifrado::disponible()) {
            // Regla del proyecto: ninguna llave se guarda en claro (docs/CIFRADO_DE_DATOS.md).
            throw new \RuntimeException('Falta la llave de cifrado del servidor; sin ella la clave no se guarda en la base.');
        }
        Database::ejecutar(
            'INSERT INTO ajustes (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
            ['ia_' . $servicio . '_api_key', (string) Cifrado::cifrar($valor, self::contexto($servicio)), 'Control del asistente']
        );
    }

    /** ¿Se pueden guardar claves desde Ajustes? Requiere la llave de cifrado del servidor. */
    public static function puedeGuardar(): bool
    {
        return Cifrado::disponible();
    }

    /** Cifra las claves heredadas que aún estén en claro en `ajustes` (se llama al abrir la pantalla de Ajustes). */
    public static function asegurarCifrado(): void
    {
        if (!Cifrado::disponible()) {
            return;
        }
        foreach (self::SERVICIOS as $servicio) {
            $v = ajuste('ia_' . $servicio . '_api_key');
            if ($v !== '' && !Cifrado::estaCifrado($v) && self::limpia($v) !== '') {
                self::guardar($servicio, $v);
            }
        }
    }

    private static function contexto(string $servicio): string
    {
        return 'ajustes.ia_' . $servicio . '_api_key';
    }

    /** Una clave válida cabe en un encabezado HTTP: sin saltos de línea ni espacios, hasta 512 caracteres. */
    private static function limpia(string $v): string
    {
        return ($v !== '' && strlen($v) <= 512 && !preg_match('/[\s\x00-\x1f]/', $v)) ? $v : '';
    }
}
