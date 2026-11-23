<?php
declare(strict_types=1);

namespace App\Modules\Consejos;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Modules\Consejos\Services\Avisar;
use App\Modules\Consejos\Services\Dictamenes;
use App\Modules\Consejos\Services\Texto;

/** Oficios de dictamen de una reunión: generarlos desde los acuerdos, revisarlos, descargarlos en Word y adjuntar el escaneado firmado. */
final class OficioController extends ConsejosBase
{
    public function index(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $oficios = Database::todas(
            "SELECT o.*, a.grupo, a.numero AS asunto_numero, a.version_acuerdo AS version_asunto, a.aprob_gobierno,
                    (SELECT f.id FROM consejos_archivos f WHERE f.oficio_id = o.id ORDER BY f.id DESC LIMIT 1) AS archivo_id
               FROM consejos_oficios o JOIN consejos_asuntos a ON a.id = o.asunto_id WHERE a.reunion_id = ?
              ORDER BY FIELD(a.grupo, 'profesor', 'alumno', 'general'), a.numero, o.id",
            [(int) $r['id']]
        );
        $sinOficio = (int) Database::valor('SELECT COUNT(*) FROM consejos_asuntos a WHERE a.reunion_id = ? AND NOT EXISTS (SELECT 1 FROM consejos_oficios o WHERE o.asunto_id = a.id)', [(int) $r['id']]);
        $sinAcuerdoGobierno = (int) Database::valor("SELECT COUNT(*) FROM consejos_asuntos WHERE reunion_id = ? AND (acuerdo_gobierno IS NULL OR acuerdo_gobierno = '')", [(int) $r['id']]);
        $this->vista('oficios', [
            'titulo' => 'Dictámenes · ' . $r['etiqueta'], 'r' => $r, 'oficios' => $oficios, 'sinOficio' => $sinOficio, 'sinAcuerdoGobierno' => $sinAcuerdoGobierno,
            'siguiente' => \App\Modules\Titulacion\Services\NumeroOficio::siguiente('subdireccion'),
        ]);
    }

    public function generar(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $fecha = $this->post('fecha');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || date('Y-m-d', strtotime($fecha)) !== $fecha) {
            flash('error', 'Escribe la fecha de los oficios.');
            redirigir('consejos/reunion/' . $r['id'] . '/dictamenes');
        }
        $inicio = ctype_digit($this->post('inicio')) && (int) $this->post('inicio') > 0 ? (int) $this->post('inicio') : null;
        try {
            $res = Dictamenes::generar((int) $r['id'], $fecha, $inicio);
        } catch (\Throwable $e) {
            error_log('Consejos dictámenes: ' . $e->getMessage());
            flash('error', 'No fue posible generar los oficios. No se asignó ningún número.');
            redirigir('consejos/reunion/' . $r['id'] . '/dictamenes');
        }
        Audit::record('consejos::dictamenes', 'correcto', 'Genera ' . $res['creados'] . ' oficios reunión #' . $r['id']);
        flash($res['creados'] ? 'exito' : 'alerta', $res['creados'] ? 'Se generaron ' . $res['creados'] . ' oficios (' . $res['primero'] . ' a ' . $res['ultimo'] . '). Revisa los resaltados.' : 'Todos los casos ya tienen oficio.');
        redirigir('consejos/reunion/' . $r['id'] . '/dictamenes');
    }

    public function editar(string $id): void
    {
        $this->gestor();
        [$o, $a, $r] = $this->cargar($id);
        $archivos = Database::todas('SELECT * FROM consejos_archivos WHERE oficio_id = ? ORDER BY id DESC', [(int) $o['id']]);
        $this->vista('oficio', ['titulo' => 'Oficio ' . $o['numero'], 'o' => $o, 'a' => $a, 'r' => $r, 'clave' => Texto::clave($a['grupo'], (int) $a['numero']), 'archivos' => $archivos]);
    }

    public function actualizar(string $id): void
    {
        $this->gestor();
        [$o, $a, $r] = $this->cargar($id);
        $destinatario = implode("\n", Texto::lineas(mb_substr($this->post('destinatario'), 0, 600)));
        $cargo = implode("\n", Texto::lineas(mb_substr($this->post('cargo'), 0, 600)));
        $cuerpo = mb_substr($this->post('cuerpo'), 0, 4000);
        $acuerdo = mb_substr($this->post('acuerdo'), 0, 6000);
        $estado = in_array($this->post('estado'), ['borrador', 'revisado', 'emitido'], true) ? $this->post('estado') : 'borrador';
        $fecha = $this->post('fecha');
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) && date('Y-m-d', strtotime($fecha)) === $fecha ? $fecha : $o['fecha'];
        $incompleto = $destinatario === '' || str_contains($destinatario, '[') || str_contains($cargo, '[') || trim($acuerdo) === '' || trim($cuerpo) === '';
        if ($estado !== 'borrador' && $incompleto) {
            flash('error', 'Falta el destinatario (o quedó un campo entre corchetes), el cuerpo o el acuerdo: el oficio no puede pasar a «' . $estado . '».');
            $estado = 'borrador';
        }
        Database::ejecutar('UPDATE consejos_oficios SET destinatario = ?, cargo = ?, cuerpo = ?, acuerdo = ?, fecha = ?, estado = ?, revisar = ? WHERE id = ?',
            [$destinatario, $cargo, $cuerpo, $acuerdo, $fecha, $estado, $estado === 'borrador' ? (int) $o['revisar'] : 0, (int) $o['id']]);
        Audit::record('consejos::oficio', 'correcto', 'Actualiza oficio ' . $o['numero'] . ' → ' . $estado);
        flash('exito', 'Oficio guardado.');
        redirigir('consejos/reunion/' . $r['id'] . '/dictamenes');
    }

    public function regenerar(string $id): void
    {
        $this->gestor();
        [$o, , $r] = $this->cargar($id);
        $ok = Dictamenes::regenerar((int) $o['id']);
        flash($ok ? 'exito' : 'error', $ok ? 'Oficio ' . $o['numero'] . ' redactado de nuevo con los acuerdos actuales.' : 'No se pudo regenerar (los oficios emitidos no se tocan).');
        redirigir('consejos/reunion/' . $r['id'] . '/dictamenes');
    }

    /** Todos los oficios de la reunión en un solo Word, uno por página. */
    public function descargar(string $id): void
    {
        $this->gestor();
        $r = $this->reunion($id);
        $oficios = Database::todas(
            "SELECT o.* FROM consejos_oficios o JOIN consejos_asuntos a ON a.id = o.asunto_id WHERE a.reunion_id = ?
              ORDER BY FIELD(a.grupo, 'profesor', 'alumno', 'general'), a.numero, o.id",
            [(int) $r['id']]
        );
        if (!$oficios) {
            flash('error', 'Aún no hay oficios: genera los dictámenes primero.');
            redirigir('consejos/reunion/' . $r['id'] . '/dictamenes');
        }
        Audit::record('consejos::dictamenes', 'correcto', 'Descarga ' . count($oficios) . ' oficios reunión #' . $r['id']);
        $this->enviarDocx(Dictamenes::docx($oficios), 'Dictamenes_' . preg_replace('/[^A-Za-z0-9]+/', '', $r['etiqueta']) . '.docx');
    }

    /** Un solo oficio en Word. */
    public function descargarUno(string $id): void
    {
        $this->gestor();
        [$o] = $this->cargar($id);
        $this->enviarDocx(Dictamenes::docx([$o]), 'Oficio_' . preg_replace('/[^A-Za-z0-9]+/', '-', (string) $o['numero']) . '.docx');
    }

    /** Escaneado del oficio firmado (se mostrará a los profesores mencionados en «Comisiones»). */
    public function subirEscaneado(string $id): void
    {
        $this->gestor();
        [$o, $a, $r] = $this->cargar($id);
        [$rel, $bytes, $error] = $this->guardarPdf($_FILES['archivo'] ?? [], (int) $r['id']);
        if ($rel === null) {
            flash('error', $error);
            redirigir('consejos/oficio/' . $o['id']);
        }
        foreach (Database::todas('SELECT id, ruta FROM consejos_archivos WHERE oficio_id = ?', [(int) $o['id']]) as $viejo) {
            if ($abs = self::rutaAbsoluta((string) $viejo['ruta'])) {
                @unlink($abs);
            }
            Database::ejecutar('DELETE FROM consejos_archivos WHERE id = ?', [(int) $viejo['id']]);
        }
        $nombre = mb_substr(preg_replace('/[^\p{L}\p{N}._ -]+/u', '_', (string) ($_FILES['archivo']['name'] ?? 'dictamen.pdf')) ?: 'dictamen.pdf', 0, 150);
        Database::ejecutar('INSERT INTO consejos_archivos (reunion_id, asunto_id, oficio_id, tipo, nombre, ruta, bytes, subido_por) VALUES (?, ?, ?, \'dictamen\', ?, ?, ?, ?)',
            [(int) $r['id'], (int) $a['id'], (int) $o['id'], $nombre, $rel, $bytes, Auth::id()]);
        Audit::record('consejos::archivo', 'correcto', 'Sube dictamen oficio ' . $o['numero']);
        $avisados = 0;
        if ($this->post('avisar') === '1' && !empty($r['gobierno_fin'])) {
            $avisados = Avisar::oficio($o, $a, $r, $this->correos(), Auth::id());
        }
        flash('exito', 'Dictamen escaneado guardado.' . ($avisados ? ' Se avisó a ' . $avisados . ' persona' . ($avisados > 1 ? 's' : '') . ' por correo.' : ''));
        redirigir('consejos/oficio/' . $o['id']);
    }

    /** Avisa por correo (y en la bandeja) que el dictamen ya está en «Comisiones». */
    public function avisar(string $id): void
    {
        $this->gestor();
        [$o, $a, $r] = $this->cargar($id);
        if (!Database::valor("SELECT 1 FROM consejos_archivos WHERE oficio_id = ? AND tipo = 'dictamen' LIMIT 1", [(int) $o['id']])) {
            flash('error', 'Primero sube el dictamen escaneado: el correo lleva el enlace para verlo y descargarlo.');
            redirigir('consejos/oficio/' . $o['id']);
        }
        $n = Avisar::oficio($o, $a, $r, $this->correos(), Auth::id());
        Audit::record('consejos::avisar', 'correcto', 'Oficio ' . $o['numero'] . ': ' . $n);
        flash($n ? 'exito' : 'alerta', $n ? 'Dictamen notificado a ' . $n . ' persona' . ($n > 1 ? 's' : '') . '.' : 'No hay a quién notificar: el caso no menciona profesores con cuenta y no agregaste a nadie más.');
        redirigir('consejos/oficio/' . $o['id']);
    }

    /** Correos adicionales elegidos en el selector de personas (solo direcciones válidas). @return string[] */
    private function correos(): array
    {
        $crudo = $_POST['correos'] ?? [];
        $lista = [];
        foreach ((array) $crudo as $c) {
            $c = strtolower(trim((string) $c));
            if ($c !== '' && filter_var($c, FILTER_VALIDATE_EMAIL)) {
                $lista[] = $c;
            }
        }
        return array_slice(array_values(array_unique($lista)), 0, 10);
    }

    /** @return array{0:array,1:array,2:array} oficio, asunto y reunión */
    private function cargar(string $id): array
    {
        $n = self::id($id);
        $o = $n ? Database::una('SELECT * FROM consejos_oficios WHERE id = ?', [$n]) : null;
        $a = $o ? Database::una('SELECT * FROM consejos_asuntos WHERE id = ?', [(int) $o['asunto_id']]) : null;
        $r = $a ? Database::una('SELECT * FROM consejos_reuniones WHERE id = ?', [(int) $a['reunion_id']]) : null;
        if (!$o || !$a || !$r) {
            $this->noEncontrado();
        }
        return [$o, $a, $r];
    }
}
