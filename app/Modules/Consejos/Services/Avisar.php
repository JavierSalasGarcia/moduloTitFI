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
    private static function enviar(string $correo, string $nombre, string $asunto, string $texto, string $ruta = 'consejos/comisiones', string $boton = 'Ver mis comisiones y dictámenes'): bool
    {
        if (!class_exists(\App\Modules\Constancias\Services\Aviso::class) || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        \App\Modules\Constancias\Services\Aviso::enviar($correo, $nombre, $asunto, $texto, $ruta, $boton);
        return true;
    }

    /**
     * «Notificar dictamen»: correo a los profesores mencionados en el caso y a los correos que se indiquen, cada uno con su propio enlace
     * con token (`Services/Enlaces`). El enlace pide iniciar sesión con ese correo (o crear la cuenta si no existe) y da el PDF.
     * @param string[] $otros correos adicionales
     * @return int avisos enviados
     */
    public static function oficio(array $oficio, array $asunto, array $reunion, array $otros = [], ?int $por = null): int
    {
        $clave = Texto::clave($asunto['grupo'], (int) $asunto['numero']);
        $texto = 'Ya está disponible el dictamen ' . $oficio['numero'] . ' (caso ' . $clave . ', reunión ' . $reunion['etiqueta'] . ') de los HH. Consejos Académico y de Gobierno de la Facultad de Ingeniería. '
            . "Para verlo y descargarlo, firmado y sellado, abre el enlace e inicia sesión en Traza FI con este mismo correo; si todavía no tienes cuenta, el enlace te lleva a crearla.\n"
            . 'El enlace vence en ' . Enlaces::VIGENCIA_DIAS . ' días y solo funciona con la cuenta de este correo.';
        $destinos = [];
        foreach (Database::todas('SELECT u.nombre, u.email FROM consejos_asunto_profesores p JOIN usuarios u ON u.id = p.usuario_id WHERE p.asunto_id = ? AND u.activo = 1', [(int) $asunto['id']]) as $u) {
            $destinos[strtolower((string) $u['email'])] = (string) $u['nombre'];
        }
        foreach ($otros as $c) {
            $c = strtolower(trim((string) $c));
            if ($c !== '' && !isset($destinos[$c])) {
                $destinos[$c] = '';
            }
        }
        $n = 0;
        foreach ($destinos as $correo => $nombre) {
            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $token = Enlaces::crear((int) $oficio['id'], $correo, $por);
            $n += self::enviar($correo, $nombre, 'Dictamen de los HH. Consejos · ' . $oficio['numero'], $texto, 'consejos/dictamen/' . $token, 'Ver y descargar el dictamen') ? 1 : 0;
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
