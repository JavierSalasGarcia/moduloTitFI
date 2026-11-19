<?php
declare(strict_types=1);

namespace App\Modules\Eventos;

use App\Core\App;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\View;
use App\Modules\Comunicados\Avisos;

/**
 * Página de un aviso oficial con sus archivos adjuntos (p. ej. un PDF de exámenes por carrera).
 * Los avisos marcados como públicos se consultan sin sesión; un aviso en borrador (categoría «Ocultar»)
 * solo lo ve quien puede gestionarlo, que además puede verlo «como visitante» antes de publicarlo.
 */
final class AvisoController extends Controller
{
    public const CLASES = ['aviso' => 'Avisos oficiales', 'convocatoria' => 'Convocatorias', 'examenes' => 'Exámenes'];

    /** Alfabeto sin caracteres que se confunden (0/o, 1/l/i). */
    private const ALFABETO = 'abcdefghjkmnpqrstuvwxyz23456789';

    /** Token corto del aviso para su enlace público (/aviso/{token}); se crea si aún no tiene. */
    public static function token(array|int $c): string
    {
        $id = is_array($c) ? (int) $c['id'] : $c;
        $t = is_array($c) && !empty($c['token']) ? (string) $c['token'] : (string) Database::valor('SELECT token FROM comunicados WHERE id = ?', [$id]);
        while ($t === '') {
            $n = '';
            for ($i = 0; $i < 8; $i++) {
                $n .= self::ALFABETO[random_int(0, strlen(self::ALFABETO) - 1)];
            }
            try {
                Database::ejecutar('UPDATE comunicados SET token = ? WHERE id = ? AND token IS NULL', [$n, $id]);
            } catch (\Throwable) {
                continue;   // token repetido: se genera otro
            }
            $t = (string) Database::valor('SELECT token FROM comunicados WHERE id = ?', [$id]);
        }
        return $t;
    }

    /** Notificación push del aviso recién publicado a quienes pueden verlo y activaron las notificaciones. */
    public static function notificar(int $id): void
    {
        try {
            $c = Database::una('SELECT id, token, titulo, contenido, clase FROM comunicados WHERE id = ? AND categoria <> \'Ocultar\'', [$id]);
            if ($c) {
                $tipo = ['examenes' => 'Exámenes', 'convocatoria' => 'Convocatoria'][$c['clase'] ?? ''] ?? 'Aviso oficial';
                \App\Modules\Notificaciones\Push::aUsuarios(\App\Modules\Notificaciones\Push::destinatariosAviso($id), $tipo . ': ' . $c['titulo'],
                    trim((string) $c['contenido']) !== '' ? (string) $c['contenido'] : 'Toca para consultarlo en Traza FI.', self::ruta($c));
            }
        } catch (\Throwable) {
        }
    }

    public static function ruta(array|int $c): string
    {
        return 'aviso/' . self::token($c);
    }

    private static function cargar(string $token): ?array
    {
        if (!preg_match('/^[a-z0-9]{8}$/D', $token)) {
            return null;
        }
        return Database::una(
            "SELECT c.*, d.nombre AS depto, u.nombre AS autor, p.siglas,
                    (SELECT a.valor FROM ajustes a WHERE a.clave = CONCAT('comunicado_vence_', c.id)) AS vence_en
               FROM comunicados c JOIN departamentos d ON d.id = c.depto_id JOIN usuarios u ON u.id = c.autor_id
               LEFT JOIN programas p ON p.id = c.programa_id WHERE c.token = ?", [$token]);
    }

    /** ¿Puede ver este aviso quien hace la petición? (sin sesión: solo públicos y publicados). */
    private static function puedeVer(array $c): bool
    {
        $gestiona = Auth::id() && Avisos::puedeGestionar($c);
        if ($c['categoria'] === 'Ocultar') {
            return $gestiona;
        }
        if (!Auth::id()) {
            return (int) $c['publico'] === 1;
        }
        if ($gestiona || (int) $c['publico'] === 1) {
            return true;
        }
        return in_array((int) $c['id'], array_map('intval', array_column(Avisos::visibles(false), 'id')), true);
    }

    private function noEncontrado(): never
    {
        http_response_code(404);
        $this->vista('no_disponible', ['titulo' => 'Aviso no disponible', 'seccion' => 'Avisos', 'visitante' => !Auth::id()], Auth::id() ? 'app' : 'publico');
        exit;
    }

    public static function adjuntos(int $id): array
    {
        try {
            return Database::todas('SELECT * FROM comunicado_adjuntos WHERE comunicado_id = ? ORDER BY grupo = \'\', orden, etiqueta', [$id]);
        } catch (\Throwable) {
            return [];
        }
    }

    public function ver(string $id): void
    {
        $c = self::cargar($id);
        if (!$c || !self::puedeVer($c)) {
            $this->noEncontrado();
        }
        $gestiona = Auth::id() && Avisos::puedeGestionar($c);
        $comoVisitante = !Auth::id() || ($gestiona && ($_GET['como'] ?? '') === 'visitante');
        if (Auth::id() && !$comoVisitante) {
            Lecturas::marcar('comunicado', (int) $c['id']);
        }
        if (!($comoVisitante && Auth::id())) {   // la vista previa del gestor no cuenta
            Estadisticas::visita('comunicado', (int) $c['id']);
        }
        $c['programas_lista'] = array_column(Database::todas('SELECT p.siglas FROM comunicado_programas cp JOIN programas p ON p.id = cp.programa_id WHERE cp.comunicado_id = ? ORDER BY p.siglas', [(int) $c['id']]), 'siglas');
        // Otros calendarios de exámenes (p. ej. licenciatura ↔ posgrado): se muestran como botones grandes.
        $relacionados = [];
        if (($c['clase'] ?? '') === 'examenes') {
            foreach (Database::todas("SELECT * FROM comunicados WHERE clase = 'examenes' AND id <> ? ORDER BY creado_en DESC", [(int) $c['id']]) as $r) {
                $r['vence_en'] = Database::valor("SELECT valor FROM ajustes WHERE clave = ?", ['comunicado_vence_' . $r['id']]);
                $vigente = empty($r['vence_en']) || $r['vence_en'] >= date('Y-m-d');
                // Sin sesión: solo públicos y publicados; en la vista previa «como visitante», también los borradores públicos.
                $ok = $comoVisitante ? ((int) $r['publico'] === 1 && ($r['categoria'] !== 'Ocultar' || Auth::id())) : self::puedeVer($r);
                if ($vigente && $ok) {
                    $relacionados[] = $r;
                }
            }
        }
        $datos = [
            'relacionados' => $relacionados,
            'estad' => !$comoVisitante && Estadisticas::puedeVer() ? Estadisticas::de('comunicado', (int) $c['id']) : null, 'estadTipo' => 'comunicado',
            'titulo' => $c['titulo'], 'seccion' => 'Avisos', 'c' => $c, 'adjuntos' => self::adjuntos((int) $c['id']),
            'gestiona' => $gestiona && !$comoVisitante, 'comoVisitante' => $comoVisitante, 'clases' => self::CLASES,
        ];
        if ($comoVisitante) {
            $this->vista('aviso', $datos + ['visitante' => true], 'publico');
        } else {
            $this->vista('aviso', $datos + ['visitante' => false]);
        }
    }

    public function archivo(string $id, string $aid): void
    {
        $c = self::cargar($id);
        if (!$c || !self::puedeVer($c) || !ctype_digit($aid)) {
            $this->noEncontrado();
        }
        $a = Database::una('SELECT * FROM comunicado_adjuntos WHERE id = ? AND comunicado_id = ?', [(int) $aid, (int) $c['id']]);
        $base = realpath((string) App::config('archivos.storage'));
        $ruta = $a ? realpath($base . '/' . $a['ruta']) : false;
        if (!$a || $base === false || $ruta === false || !is_file($ruta) || !str_starts_with($ruta, rtrim($base, '/\\') . DIRECTORY_SEPARATOR)) {
            $this->noEncontrado();
        }
        Estadisticas::descarga((int) $c['id'], (int) $a['id']);
        $nombre = preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $a['nombre']) ?: 'archivo.pdf';
        header('Content-Type: ' . (str_ends_with(strtolower($nombre), '.pdf') ? 'application/pdf' : 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=300');
        header('Content-Disposition: ' . (($_GET['descargar'] ?? '') === '1' ? 'attachment' : 'inline') . '; filename="' . $nombre . '"');
        header('Content-Length: ' . filesize($ruta));
        readfile($ruta);
        exit;
    }

    private function gestionable(string $id): array
    {
        $c = self::cargar($id);
        if (!$c || !Avisos::puedeGestionar($c)) {
            flash('error', 'Solo el área emisora puede modificar este aviso.');
            redirigir('eventos');
        }
        return $c;
    }

    public function adjuntar(string $id): void
    {
        $c = $this->gestionable($id);
        $etiqueta = mb_substr(trim($this->post('etiqueta')), 0, 120);
        $f = $_FILES['archivo'] ?? null;
        if ($etiqueta === '' || !$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || $f['size'] > 20 * 1024 * 1024) {
            flash('error', 'Escribe la etiqueta (por ejemplo, «Ingeniería Civil (ICI)») y elige un PDF de hasta 20 MB.');
            redirigir(self::ruta($c));
        }
        $cabeza = (string) file_get_contents($f['tmp_name'], false, null, 0, 5);
        if ($cabeza !== '%PDF-') {
            flash('error', 'El archivo debe ser PDF.');
            redirigir(self::ruta($c));
        }
        $rel = 'avisos/' . (int) $c['id'] . '/' . bin2hex(random_bytes(8)) . '.pdf';
        $dest = rtrim((string) App::config('archivos.storage'), '/\\') . '/' . $rel;
        if (!is_dir(dirname($dest))) {
            mkdir(dirname($dest), 0750, true);
        }
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            flash('error', 'No fue posible guardar el archivo.');
            redirigir(self::ruta($c));
        }
        $nombre = preg_replace('/[^A-Za-z0-9._ -]+/u', '_', (string) $f['name']) ?: 'archivo.pdf';
        $orden = (int) Database::valor('SELECT COALESCE(MAX(orden), 0) + 1 FROM comunicado_adjuntos WHERE comunicado_id = ?', [(int) $c['id']]);
        $grupo = mb_substr(trim($this->post('grupo')), 0, 60);
        Database::ejecutar('INSERT INTO comunicado_adjuntos (comunicado_id, grupo, etiqueta, nombre, ruta, bytes, orden) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [(int) $c['id'], $grupo, $etiqueta, $nombre, $rel, (int) $f['size'], $orden]);
        flash('exito', 'Archivo agregado: ' . $etiqueta . '.');
        redirigir(self::ruta($c));
    }

    public function quitarAdjunto(string $id, string $aid): void
    {
        $c = $this->gestionable($id);
        $a = ctype_digit($aid) ? Database::una('SELECT * FROM comunicado_adjuntos WHERE id = ? AND comunicado_id = ?', [(int) $aid, (int) $c['id']]) : null;
        if ($a) {
            Database::ejecutar('DELETE FROM comunicado_adjuntos WHERE id = ?', [(int) $a['id']]);
            $ruta = rtrim((string) App::config('archivos.storage'), '/\\') . '/' . $a['ruta'];
            if (is_file($ruta)) {
                @unlink($ruta);
            }
            flash('exito', 'Archivo retirado.');
        }
        redirigir(self::ruta($c));
    }

    /** Publicar (o volver a borrador) y abrir o cerrar la consulta sin sesión. */
    public function estado(string $id): void
    {
        $c = $this->gestionable($id);
        $accion = $this->post('accion');
        if ($accion === 'publicar') {
            Database::ejecutar("UPDATE comunicados SET categoria = 'General', creado_en = NOW() WHERE id = ?", [(int) $c['id']]);
            flash('exito', 'Aviso publicado.');
            self::notificar((int) $c['id']);
        } elseif ($accion === 'borrador') {
            Database::ejecutar("UPDATE comunicados SET categoria = 'Ocultar' WHERE id = ?", [(int) $c['id']]);
            flash('alerta', 'El aviso volvió a borrador: solo lo ve tu área.');
        } elseif ($accion === 'publico' || $accion === 'privado') {
            Database::ejecutar('UPDATE comunicados SET publico = ? WHERE id = ?', [$accion === 'publico' ? 1 : 0, (int) $c['id']]);
            flash('exito', $accion === 'publico' ? 'El aviso se puede consultar sin iniciar sesión.' : 'El aviso ya solo se ve con sesión iniciada.');
        } elseif (isset(self::CLASES[$accion])) {
            Database::ejecutar('UPDATE comunicados SET clase = ? WHERE id = ?', [$accion, (int) $c['id']]);
        }
        redirigir(self::ruta($c));
    }

    /** Marca un aviso o evento como no leído (vuelve a contar en el globito). */
    public function noLeido(): void
    {
        $tipo = $this->post('tipo');
        $id = (int) $this->post('id');
        if (in_array($tipo, ['comunicado', 'evento'], true) && $id > 0) {
            Database::ejecutar('DELETE FROM aviso_lecturas WHERE usuario_id = ? AND tipo = ? AND item_id = ?', [Auth::id(), $tipo, $id]);
            flash('exito', 'Marcado como no leído.');
        }
        redirigir('eventos');
    }

    public function marcarLeidos(): void
    {
        Lecturas::marcarTodos();
        flash('exito', 'Todo quedó marcado como leído.');
        redirigir('eventos');
    }
}
