<?php
declare(strict_types=1);

namespace App\Modules\Posgrado;

use App\Core\Database;

/**
 * Ajustes del módulo guardados en la tabla `ajustes` (claves posgrado_*): quién firma los oficios de la Coordinación de
 * Estudios Avanzados y los datos de contacto de Apoyo a Grado que van en la notificación al director.
 */
final class Configuracion
{
    /** clave => [etiqueta, valor por omisión, ayuda] */
    public const CAMPOS = [
        'posgrado_coordinador_nombre' => ['Nombre de quien firma los oficios', 'Dra. Lourdes Loza Hernández', 'Con grado, tal como va en el bloque de firma.'],
        'posgrado_coordinador_cargo' => ['Cargo de quien firma', 'Coordinadora de Estudios Avanzados F.I.', 'Por ejemplo «Coordinadora de Estudios Avanzados F.I.».'],
        'posgrado_coordinador_correo' => ['Correo de la cuenta que firma en línea por la Coordinación', '', 'Opcional. Esa cuenta firma los oficios y constancias en «Posgrado: firmas». También firma quien tenga rol titular en la Coordinación de Estudios Avanzados.'],
        'posgrado_token_dias' => ['Vigencia de los enlaces de firma (días)', '14', 'Días que dura el enlace personal que reciben el director y el sínodo; al reenviarlo se cuenta de nuevo.'],
        'posgrado_apoyo_nombre' => ['Nombre de la persona de Apoyo a Grado', '', 'Con grado. Si lo dejas vacío, los oficios dicen «el personal de Apoyo a Grado F.I.».'],
        'posgrado_apoyo_correos' => ['Correos de contacto de Apoyo a Grado', 'apoyoagraduacionfi@gmail.com y cea_fi@uaemex.mx', 'Como se escriben en la notificación al director, separados por «y».'],
        'posgrado_apoyo_telefono' => ['Teléfono de Apoyo a Grado', '722 2 14 08 55 extensión 1124', 'Como se escribe en la notificación al director.'],
    ];

    public static function valor(string $clave): string
    {
        $omision = self::CAMPOS[$clave][1] ?? '';
        try {
            $v = trim(\ajuste($clave, $omision));
        } catch (\Throwable) {
            $v = $omision;
        }
        return $v !== '' ? $v : $omision;
    }

    public static function firmante(): string
    {
        return self::valor('posgrado_coordinador_nombre');
    }

    public static function cargo(): string
    {
        return self::valor('posgrado_coordinador_cargo');
    }

    /** «La que suscribe» / «El que suscribe», según el cargo capturado. */
    public static function suscribe(): string
    {
        $c = self::cargo();
        if (preg_match('/^Coordinadora\b|^Directora\b|^Jefa\b|^Subdirectora\b/iu', $c)) {
            return 'La que suscribe';
        }
        if (preg_match('/^Coordinador\b|^Director\b|^Jefe\b|^Subdirector\b/iu', $c)) {
            return 'El que suscribe';
        }
        return 'Quien suscribe';
    }

    /** Cargo sin la abreviatura «F.I.», para la redacción de las constancias. */
    public static function cargoSinFi(): string
    {
        return trim((string) preg_replace('/\s*F\.\s?I\.?\s*$/u', '', self::cargo()));
    }

    /** Días de vigencia del enlace personal de firma (1 a 90). */
    public static function diasToken(): int
    {
        $n = (int) self::valor('posgrado_token_dias');
        return $n >= 1 && $n <= 90 ? $n : 14;
    }

    public static function apoyoNombre(): string
    {
        return self::valor('posgrado_apoyo_nombre');
    }

    public static function apoyoCorreos(): string
    {
        return self::valor('posgrado_apoyo_correos');
    }

    public static function apoyoTelefono(): string
    {
        return self::valor('posgrado_apoyo_telefono');
    }

    /** Guarda los valores (máximo 255 caracteres, el ancho de la columna). */
    public static function guardar(array $valores): void
    {
        foreach (self::CAMPOS as $clave => [$etiqueta]) {
            $v = trim((string) preg_replace('/\s+/u', ' ', (string) ($valores[$clave] ?? '')));
            Database::ejecutar(
                'INSERT INTO ajustes (clave, valor, descripcion) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)',
                [$clave, mb_substr($v, 0, 255), 'Posgrado: ' . $etiqueta]
            );
        }
    }
}
