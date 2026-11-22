<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Modules\Consejos\Services\ActaWord;
use App\Modules\Consejos\Services\ExcelCasos;
use App\Modules\Consejos\Services\Importador;

/** Alta de casos en una reunión ya creada, desde un acta de Word o una hoja de Excel (no se crean reuniones desde un acta). */
final class ImportarController extends ConsejosBase
{
    /** Agrega casos (o completa sus acuerdos) en una reunión existente desde un acta de Word o un Excel. */
    public function casos(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $this->noCerrada((int) $r['id']);
        $f = $_FILES['archivo'] ?? null;
        try {
            if (!$f || ($f['error'] ?? 1) !== UPLOAD_ERR_OK) {
                throw new \RuntimeException('Elige un archivo .docx (acta en Word) o .xlsx (hoja de Excel).');
            }
            $ext = strtolower(pathinfo((string) $f['name'], PATHINFO_EXTENSION));
            if ($ext === 'xlsx') {
                $res = Importador::casos((int) $r['id'], ExcelCasos::leer($f['tmp_name'], (string) $f['name']));
                $origen = 'la hoja de Excel';
            } else {
                $acta = ActaWord::leer($f['tmp_name'], (string) $f['name']);
                Importador::completarReunion((int) $r['id'], $acta);
                $res = Importador::casos((int) $r['id'], $acta['casos'], $acta['consejo']);
                $origen = 'el acta del Consejo ' . ($acta['consejo'] === 'gobierno' ? 'de Gobierno' : 'Académico');
            }
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            redirigir('consejos/reunion/' . $r['id']);
        } catch (\Throwable $e) {
            error_log('Consejos importar: ' . $e->getMessage());
            flash('error', 'No fue posible importar el archivo.');
            redirigir('consejos/reunion/' . $r['id']);
        }
        Audit::record('consejos::importar', 'correcto', 'Reunión #' . $r['id'] . ': ' . json_encode($res));
        flash('exito', 'Importado desde ' . $origen . ': ' . $res['nuevos'] . ' casos nuevos, ' . $res['completados'] . ' completados con datos que les faltaban y ' . $res['sinCambio'] . ' sin cambios.');
        redirigir('consejos/reunion/' . $r['id']);
    }

    public function plantilla(): void
    {
        $this->gestor();
        $this->privado();
        $bin = ExcelCasos::plantilla();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('X-Content-Type-Options: nosniff');
        header('Content-Disposition: attachment; filename="Plantilla_casos_Consejos.xlsx"');
        header('Content-Length: ' . strlen($bin));
        echo $bin;
        exit;
    }
}
