<?php
declare(strict_types=1);

namespace App\Modules\Consejos\Services;

use App\Core\Database;

/**
 * Avisos por correo y en la bandeja de Notificaciones a los profesores mencionados en un caso. Usa el mismo envío que Constancias
 * (`Constancias\Services\Aviso`): el correo no lleva adjuntos (el servicio de la plataforma no los admite); trae un enlace a
 * «Comisiones», que pide sesión, donde la persona descarga su dictamen firmado. Un fallo del envío nunca interrumpe la acción.
 */
final class Avisar
{
    private static function enviar(string $correo, string $nombre, string $asunto, string $texto): bool
    {
        if (!class_exists(\App\Modules\Constancias\Services\Aviso::class) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        \App\Modules\Constancias\Services\Aviso::enviar($correo, $nombre, $asunto, $texto, 'consejos/comisiones', 'Ver mis comisiones y dictámenes');
        return true;
    }

    /** Avisa del dictamen de un oficio a los profesores mencionados en su caso y, si se indica, a otro correo. @return int avisos enviados */
    public static function oficio(array $oficio, array $asunto, array $reunion, string $otroCorreo = ''): int
    {
        $clave = Texto::clave($asunto['grupo'], (int) $asunto['numero']);
        $texto = 'Ya está disponible el dictamen ' . $oficio['numero'] . ' (caso ' . $clave . ', reunión ' . $reunion['etiqueta'] . ') de los HH. Consejos Académico y de Gobierno de la Facultad de Ingeniería. '
            . 'Puedes consultarlo y descargarlo, firmado y sellado, en Constancias → Comisiones.';
        $n = 0;
        $vistos = [];
        foreach (Database::todas('SELECT u.nombre, u.email FROM consejos_asunto_profesores p JOIN usuarios u ON u.id = p.usuario_id WHERE p.asunto_id = ? AND u.activo = 1', [(int) $asunto['id']]) as $u) {
            $vistos[strtolower((string) $u['email'])] = true;
            $n += self::enviar((string) $u['email'], (string) $u['nombre'], 'Dictamen de los HH. Consejos · ' . $oficio['numero'], $texto) ? 1 : 0;
        }
        $otroCorreo = strtolower(trim($otroCorreo));
        if ($otroCorreo !== '' && empty($vistos[$otroCorreo])) {
            $n += self::enviar($otroCorreo, '', 'Dictamen de los HH. Consejos · ' . $oficio['numero'], $texto) ? 1 : 0;
        }
        if ($n > 0) {
            Database::ejecutar('UPDATE consejos_oficios SET avisado_en = NOW() WHERE id = ?', [(int) $oficio['id']]);
        }
        return $n;
    }

    /** Un aviso por profesor mencionado en la reunión: cuántas comisiones tiene. @return int personas avisadas */
    public static function reunion(array $reunion): int
    {
        $n = 0;
        $filas = Database::todas(
            'SELECT u.nombre, u.email, COUNT(*) AS casos FROM consejos_asunto_profesores p JOIN consejos_asuntos a ON a.id = p.asunto_id JOIN usuarios u ON u.id = p.usuario_id
              WHERE a.reunion_id = ? AND u.activo = 1 GROUP BY u.id, u.nombre, u.email',
            [(int) $reunion['id']]
        );
        foreach ($filas as $f) {
            $casos = (int) $f['casos'];
            $texto = 'Los HH. Consejos Académico y de Gobierno de la Facultad de Ingeniería trataron ' . ($casos === 1 ? 'un asunto' : $casos . ' asuntos') . ' en que se te menciona (reunión ' . $reunion['etiqueta']
                . '). Puedes consultarlos en Constancias → Comisiones; ahí aparecerá tu dictamen firmado en cuanto esté disponible.';
            $n += self::enviar((string) $f['email'], (string) $f['nombre'], 'Comisiones de los HH. Consejos · ' . $reunion['etiqueta'], $texto) ? 1 : 0;
        }
        return $n;
    }
}
