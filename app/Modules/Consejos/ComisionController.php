<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Consejos\Services\Html;
use App\Modules\Consejos\Services\Texto;

/**
 * «Comisiones»: para cada profesor, los dictámenes y comisiones de los HH. Consejos en que se le menciona, con el escaneado firmado
 * y un botón para descargar el acta del Consejo de Gobierno junto con la del Consejo Académico asociada. Solo aparece lo que ya
 * concluyó (sesión del Consejo de Gobierno terminada) y lo que el gestor asoció a la persona.
 */
final class ComisionController extends ConsejosBase
{
    public function index(): void
    {
        $this->privado();
        $filas = Database::todas(
            "SELECT a.*, r.etiqueta, r.tipo_sesion, r.academico_fecha, r.gobierno_fecha
               FROM consejos_asunto_profesores p
               JOIN consejos_asuntos a ON a.id = p.asunto_id JOIN consejos_reuniones r ON r.id = a.reunion_id
              WHERE p.usuario_id = ? AND r.gobierno_fin IS NOT NULL
              ORDER BY r.gobierno_fecha DESC, r.id DESC, FIELD(a.grupo, 'profesor', 'alumno', 'general'), a.numero",
            [Auth::id()]
        );
        $reuniones = [];
        foreach ($filas as $a) {
            $rid = (int) $a['reunion_id'];
            $reuniones[$rid] ??= ['id' => $rid, 'etiqueta' => $a['etiqueta'], 'tipo_sesion' => $a['tipo_sesion'], 'academico_fecha' => $a['academico_fecha'], 'gobierno_fecha' => $a['gobierno_fecha'], 'asuntos' => [], 'actas' => []];
            $a['clave'] = Texto::clave($a['grupo'], (int) $a['numero']);
            $a['resumen'] = mb_strimwidth(Texto::limpio(Html::texto((string) $a['solicitud_html'])), 0, 420, '…');
            $a['dictamenes'] = Database::todas("SELECT id, nombre FROM consejos_archivos WHERE asunto_id = ? AND tipo = 'dictamen' ORDER BY id DESC", [(int) $a['id']]);
            [$a['acuerdo']] = Services\Dictamenes::acuerdoFinal($a);
            $reuniones[$rid]['asuntos'][] = $a;
        }
        foreach ($reuniones as $rid => $_) {
            foreach (Database::todas("SELECT tipo FROM consejos_archivos WHERE reunion_id = ? AND tipo IN ('acta_academico','acta_gobierno')", [$rid]) as $f) {
                $reuniones[$rid]['actas'][$f['tipo']] = true;
            }
        }
        $this->vista('comisiones', ['titulo' => 'Comisiones', 'reuniones' => array_values($reuniones), 'gestor' => ConsejosAccess::esGestor()]);
    }

    /** ZIP con el acta firmada del Consejo de Gobierno y la del Consejo Académico asociada. */
    public function actas(string $id): void
    {
        $this->privado();
        $n = self::id($id);
        $r = $n ? Database::una('SELECT * FROM consejos_reuniones WHERE id = ? AND gobierno_fin IS NOT NULL', [$n]) : null;
        if (!$r || !(ConsejosAccess::esGestor() || ConsejosAccess::mencionadoEn((int) $r['id']))) {
            $this->noEncontrado();
        }
        $archivos = [];
        foreach (['acta_gobierno' => 'Acta_Consejo_de_Gobierno', 'acta_academico' => 'Acta_Consejo_Academico'] as $tipo => $nombre) {
            $f = Database::una('SELECT ruta FROM consejos_archivos WHERE reunion_id = ? AND tipo = ? ORDER BY id DESC LIMIT 1', [(int) $r['id'], $tipo]);
            if ($f && ($abs = self::rutaAbsoluta((string) $f['ruta']))) {
                $archivos[$nombre . '_' . preg_replace('/[^A-Za-z0-9]+/', '', (string) $r['etiqueta']) . '.pdf'] = $abs;
            }
        }
        if (!$archivos || !class_exists(\ZipArchive::class)) {
            flash('alerta', 'Las actas firmadas de esta reunión aún no están disponibles.');
            redirigir('consejos/comisiones');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'act');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) {
            flash('error', 'No fue posible preparar la descarga.');
            redirigir('consejos/comisiones');
        }
        foreach ($archivos as $nombre => $abs) {
            $zip->addFile($abs, $nombre);
        }
        $zip->close();
        Audit::record('consejos::descarga', 'correcto', 'Actas reunión #' . $r['id']);
        header('Content-Type: application/zip');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="Actas_Consejos_' . preg_replace('/[^A-Za-z0-9]+/', '', (string) $r['etiqueta']) . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}
