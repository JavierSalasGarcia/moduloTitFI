<?php
declare(strict_types=1);

namespace App\Modules\Titulacion\Services;

use App\Core\Database;

/**
 * Enlaces de descarga sin números consecutivos: cada archivo (documento del expediente, dictamen, constancia,
 * expediente ZIP…) se descarga en titulacion/archivo/{token}, con un token aleatorio de 32 caracteres que no permite
 * deducir el de otros archivos. El token solo identifica el archivo: quien lo abre necesita además sesión y permiso,
 * que revisa la misma acción de siempre del controlador.
 */
final class Archivos
{
    /** acción => [controlador, método]; los parámetros guardados se pasan en orden al método. */
    public const ACCIONES = [
        'tramite_doc' => [\App\Modules\Titulacion\TramiteController::class, 'archivo'],
        'tramite_dictamen' => [\App\Modules\Titulacion\TramiteController::class, 'dictamen'],
        'ruta_doc' => [\App\Modules\Titulacion\RutaController::class, 'documento'],
        'ruta_dictamen' => [\App\Modules\Titulacion\RutaController::class, 'dictamen'],
        'comision_revision' => [\App\Modules\Titulacion\ComisionController::class, 'revisionPdf'],
        'protocolo' => [\App\Modules\Titulacion\ProtocolosController::class, 'documento'],
        'revision' => [\App\Modules\Titulacion\RevisionController::class, 'documento'],
        'firma' => [\App\Modules\Titulacion\FirmasController::class, 'descargar'],
        'expediente' => [\App\Modules\Titulacion\GestionController::class, 'expediente'],
        'constancia' => [\App\Modules\Titulacion\PanelController::class, 'constancia'],
        'firma_mia' => [\App\Modules\Titulacion\FirmaExternaController::class, 'documentoPropio'],
        'oficio_mio' => [\App\Modules\Titulacion\JuradoController::class, 'oficioSinodalMio'],
        'trabajo_coordinador' => [\App\Modules\Titulacion\JuradoController::class, 'trabajoCoordinadorMio'],
        'trabajo_sinodal' => [\App\Modules\Titulacion\JuradoController::class, 'trabajoSinodalMio'],
    ];

    /** @var array<string,string> caché por petición */
    private static array $cache = [];

    /** Ruta relativa (para url()) del archivo. */
    public static function url(string $accion, int ...$ids): string
    {
        if (!isset(self::ACCIONES[$accion])) {
            throw new \InvalidArgumentException('Acción de descarga desconocida.');
        }
        $params = implode(',', $ids);
        $k = $accion . '|' . $params;
        if (!isset(self::$cache[$k])) {
            $token = Database::valor('SELECT token FROM tit_archivo_tokens WHERE accion = ? AND params = ?', [$accion, $params]);
            if (!$token) {
                Database::ejecutar('INSERT IGNORE INTO tit_archivo_tokens (token, accion, params) VALUES (?, ?, ?)', [bin2hex(random_bytes(16)), $accion, $params]);
                $token = Database::valor('SELECT token FROM tit_archivo_tokens WHERE accion = ? AND params = ?', [$accion, $params]);
            }
            self::$cache[$k] = (string) $token;
        }
        return 'titulacion/archivo/' . self::$cache[$k];
    }

    /** @return ?array{0:string,1:string[]} [acción, parámetros] */
    public static function abrir(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{32}$/D', $token)) {
            return null;
        }
        $f = Database::una('SELECT accion, params FROM tit_archivo_tokens WHERE token = ?', [$token]);
        return $f && isset(self::ACCIONES[$f['accion']]) ? [(string) $f['accion'], $f['params'] === '' ? [] : explode(',', (string) $f['params'])] : null;
    }
}
