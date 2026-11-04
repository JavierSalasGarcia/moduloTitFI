<?php
declare(strict_types=1);

namespace App\Modules\Titulacion;

use App\Core\Audit;
use App\Core\Auth;
use App\Modules\Titulacion\Services\Constancias;
use App\Modules\Titulacion\Services\Expedientes;
use App\Modules\Titulacion\Services\Historico;
use App\Modules\Titulacion\Services\HistoricoProtocolos;
use App\Modules\Titulacion\Services\Oficios;
use Throwable;

/** «Constancias → Textos» (redacción editable de cada constancia) e «Constancias → Historial» (registros anteriores del libro de actas). */
final class ConstanciasController extends TitulacionBase
{
    private function soloTextos(): void
    {
        if (!Constancias::puedeEditarTextos()) {
            $this->prohibido('Textos de constancias');
        }
        $this->privado();
    }

    private function soloHistorial(): void
    {
        if (!Constancias::puedeBuscar()) {
            $this->prohibido('Historial de constancias');
        }
        $this->privado();
    }

    private function tipo(string $tipo): string
    {
        if (!isset(Constancias::TIPOS[$tipo])) {
            $this->noEncontrado();
        }
        return $tipo;
    }

    /** @return array{asunto:string,intro:string,cuerpo:string,cierre:string,tabla:int} */
    private function textoDelFormulario(): array
    {
        $t = [];
        foreach (['asunto' => 80, 'intro' => 2000, 'cuerpo' => 4000, 'cierre' => 2000] as $k => $max) {
            $t[$k] = mb_substr(str_replace("\r\n", "\n", (string) ($_POST[$k] ?? '')), 0, $max);
        }
        $t['tabla'] = !empty($_POST['tabla']) ? 1 : 0;
        return $t;
    }

    public function textos(): void
    {
        $this->soloTextos();
        $textos = [];
        foreach (Constancias::ACTIVOS as $tipo) {
            $textos[$tipo] = Constancias::texto($tipo);
        }
        $this->vista('constancias_textos', ['titulo' => 'Constancias · Textos', 'textos' => $textos, 'abierto' => (string) ($_GET['tipo'] ?? '')]);
    }

    public function guardarTexto(string $tipo): void
    {
        $this->soloTextos();
        $tipo = $this->tipo($tipo);
        $t = $this->textoDelFormulario();
        if (trim($t['cuerpo']) === '' || trim($t['intro']) === '') {
            flash('error', 'La introducción y el cuerpo no pueden quedar vacíos.');
            redirigir('titulacion/constancias/textos?tipo=' . $tipo . '#' . $tipo);
        }
        if (preg_match_all('/\{[a-z_]+\}/', $t['intro'] . $t['cuerpo'] . $t['cierre'] . $t['asunto'], $m) && ($raros = array_diff(array_unique($m[0]), array_keys(Constancias::MARCADORES)))) {
            flash('error', 'Marcadores desconocidos: ' . implode(', ', $raros) . '.');
            redirigir('titulacion/constancias/textos?tipo=' . $tipo . '#' . $tipo);
        }
        Constancias::guardarTexto($tipo, $t, Auth::id());
        Audit::record('titulacion::constancia_texto', 'correcto', $tipo);
        flash('exito', 'Texto guardado. Se usará en las constancias que se generen a partir de ahora.');
        redirigir('titulacion/constancias/textos?tipo=' . $tipo . '#' . $tipo);
    }

    public function restaurarTexto(string $tipo): void
    {
        $this->soloTextos();
        $tipo = $this->tipo($tipo);
        Constancias::restaurarTexto($tipo);
        Audit::record('titulacion::constancia_texto', 'correcto', 'restaurado ' . $tipo);
        flash('exito', 'Se restauró el texto original.');
        redirigir('titulacion/constancias/textos?tipo=' . $tipo . '#' . $tipo);
    }

    /** Vista previa con datos de ejemplo del texto del formulario (sin guardarlo). */
    public function muestra(string $tipo): void
    {
        $this->soloTextos();
        $tipo = $this->tipo($tipo);
        $t = [
            'id' => 0, 'folio' => 'TIT-' . date('Y') . '-00000', 'modalidad' => 'tesis', 'tipo' => 'con_trabajo', 'alumno_id' => 0, 'creado_en' => date('Y-m-d'),
            'titulo' => 'Diseño e implementación de un sistema para la validación en línea de trámites de titulación',
            'datos' => ['datos' => ['nombre' => 'María José Hernández Peña', 'cuenta' => '1912345', 'carrera' => 'computacion', 'genero' => 'femenino'],
                'protocolo' => ['asesor' => 'Juan Pérez Gómez', 'asesor_grado' => 'Dr.']],
        ];
        $profesor = ['nombre' => 'Ana López Ruiz', 'grado' => 'Dra.', 'genero' => 'femenino', 'email' => 'ejemplo@uaemex.mx'];
        try {
            [$bytes] = Oficios::constancia($t, $tipo, $profesor, ['desde' => date('Y-m-d', strtotime('-8 months')), 'hasta' => date('Y-m-d'), 'vueltas' => '1,2', 'funcion' => 'secretario', 'acta' => '1234'], $this->textoDelFormulario());
        } catch (Throwable $e) {
            error_log('Titulación: vista previa de constancia: ' . $e->getMessage());
            $this->noEncontrado();
        }
        $this->enviarBytes($bytes, 'muestra_' . $tipo . '.pdf', 'application/pdf');
    }

    // ------------------------------------------------------------------ historial

    public function historico(): void
    {
        $this->soloHistorial();
        $f = [
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80), 'carrera' => (string) ($_GET['carrera'] ?? ''),
            'faltantes' => (string) ($_GET['faltantes'] ?? ''), 'estado' => (string) ($_GET['estado'] ?? ''),
            'orden' => in_array((string) ($_GET['orden'] ?? ''), Historico::ORDENABLES, true) ? (string) $_GET['orden'] : '',
            'dir' => ($_GET['dir'] ?? '') === 'desc' ? 'desc' : 'asc',
        ];
        foreach (['examen_desde', 'examen_hasta', 'protocolo_desde', 'protocolo_hasta'] as $k) {
            $f[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$k] ?? '')) ? (string) $_GET[$k] : '';
        }
        $pagina = max(1, (int) ($_GET['p'] ?? 1));
        $r = Historico::listar($f, 100, ($pagina - 1) * 100);
        $this->vista('constancias_historico', ['titulo' => 'Constancias · Historial', 'f' => $f, 'filas' => $r['filas'], 'total' => $r['total'], 'pagina' => $pagina]);
    }

    /** Alta manual de un registro histórico que no venía en el libro. */
    public function nuevo(): void
    {
        $this->soloHistorial();
        $acta = trim($this->post('acta'));
        try {
            $id = Historico::capturar(['carrera' => $this->post('carrera'), 'alumno' => $this->post('alumno'), 'acta' => $acta,
                'fecha_examen' => $this->post('fecha_examen'), 'fecha_protocolo' => $this->post('fecha_protocolo')], Auth::id());
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            redirigir('titulacion/constancias/historial');
        }
        Audit::record('titulacion::historial', 'correcto', 'Registro histórico agregado ' . $id);
        flash('exito', 'Registro agregado. Completa sus datos con doble clic en cada celda.');
        redirigir('titulacion/constancias/historial?q=' . rawurlencode($this->post('alumno')));
    }

    // ------------------------------------------------------------------ captura de lo hecho fuera del sistema

    public function captura(): void
    {
        $this->soloHistorial();
        $f = ['q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80), 'carrera' => (string) ($_GET['carrera'] ?? ''), 'estado' => (string) ($_GET['estado'] ?? '')];
        foreach (['desde', 'hasta'] as $k) {
            $f[$k] = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET[$k] ?? '')) ? (string) $_GET[$k] : '';
        }
        $pagina = max(1, (int) ($_GET['p'] ?? 1));
        $r = HistoricoProtocolos::listar($f, 50, ($pagina - 1) * 50);
        // Búsqueda del expediente (primer paso de toda captura): licenciatura + cuenta o nombre.
        $b = ['carrera' => (string) ($_GET['b_carrera'] ?? ''), 'cuenta' => mb_substr(trim((string) ($_GET['b_cuenta'] ?? '')), 0, 12), 'alumno' => mb_substr(trim((string) ($_GET['b_alumno'] ?? '')), 0, 160)];
        $expedientes = null;
        $tramites = [];
        if (isset($_GET['buscar']) && isset(Catalogo::carreras()[$b['carrera']]) && ($b['cuenta'] !== '' || mb_strlen($b['alumno']) >= 3)) {
            $expedientes = Expedientes::buscar($b['carrera'], $b['cuenta'], $b['alumno']);
            $tramites = Expedientes::tramitesPlataforma($b['carrera'], $b['cuenta'], $b['alumno']);
        }
        $this->vista('constancias_captura', ['titulo' => 'Constancias · Registrar o completar un expediente', 'protocolos' => $r['filas'], 'total' => $r['total'],
            'pendientes' => $r['ids_pendientes'], 'f' => $f, 'pagina' => $pagina, 'b' => $b, 'expedientes' => $expedientes, 'tramites' => $tramites]);
    }

    /** Vuelve a la búsqueda del expediente recién tocado para ver cómo quedó. */
    private function volverAlExpediente(): never
    {
        $q = http_build_query(['buscar' => 1, 'b_carrera' => (string) ($_POST['carrera'] ?? ''), 'b_cuenta' => (string) ($_POST['cuenta'] ?? ''), 'b_alumno' => (string) ($_POST['alumno'] ?? '')]);
        redirigir('titulacion/constancias/captura?' . $q);
    }

    /** Examen definitivo: completa el registro existente o crea uno; con «generar» marcado también se generan sus constancias. */
    public function capturaExamen(): void
    {
        $this->soloHistorial();
        try {
            $id = Expedientes::registrarExamen($_POST, Auth::id());
            $texto = 'Examen definitivo guardado.';
            if (!empty($_POST['generar'])) {
                try {
                    $texto .= ' Constancias generadas: ' . Historico::generar($id, Auth::id()) . '; ya están en la cola de firmas.';
                } catch (\InvalidArgumentException $e) {
                    $texto .= ' Aún no se generaron las constancias: ' . $e->getMessage();
                }
            }
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            $this->volverAlExpediente();
        }
        Audit::record('titulacion::historial', 'correcto', 'Examen definitivo capturado ' . $id);
        flash('exito', $texto);
        $this->volverAlExpediente();
    }

    /** Examen previo del trabajo escrito. */
    public function capturaPrevio(): void
    {
        $this->soloHistorial();
        try {
            $r = Expedientes::registrarPrevio($_POST, Auth::id());
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            $this->volverAlExpediente();
        }
        Audit::record('titulacion::historial', 'correcto', 'Examen previo capturado ' . json_encode($r));
        flash('exito', 'Examen previo guardado.');
        $this->volverAlExpediente();
    }

    /** Une un protocolo con un examen que son la misma persona (confirmación de una coincidencia probable). */
    public function capturaLigar(): void
    {
        $this->soloHistorial();
        try {
            Expedientes::ligar((int) ($_POST['protocolo_id'] ?? 0), (int) ($_POST['historico_id'] ?? 0), Auth::id());
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            $this->volverAlExpediente();
        }
        Audit::record('titulacion::historial', 'correcto', 'Expediente ligado: protocolo ' . (int) $_POST['protocolo_id'] . ' con examen ' . (int) $_POST['historico_id']);
        flash('exito', 'Protocolo y examen ligados en un solo expediente.');
        $this->volverAlExpediente();
    }

    public function capturaProtocolo(): void
    {
        $this->soloHistorial();
        try {
            $id = Expedientes::registrarProtocolo($_POST, Auth::id());
            $texto = 'Protocolo guardado.';
            if (!empty($_POST['generar'])) {
                $texto .= ' Constancias generadas: ' . HistoricoProtocolos::generar($id, Auth::id()) . '; ya están en la cola de firmas.';
            }
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
            $this->volverAlExpediente();
        }
        Audit::record('titulacion::historial', 'correcto', 'Protocolo externo capturado ' . $id);
        flash('exito', $texto);
        $this->volverAlExpediente();
    }

    public function protocoloGenerar(string $id): void
    {
        $this->soloHistorial();
        if (!empty($_POST['json'])) {
            try {
                $n = HistoricoProtocolos::generar((int) $id, Auth::id());
            } catch (\Throwable $e) {
                $this->json(['ok' => false, 'error' => $e instanceof \InvalidArgumentException ? $e->getMessage() : 'No se pudo generar.'], 422);
            }
            Audit::record('titulacion::historial', 'correcto', 'Constancias de protocolo histórico ' . (int) $id . ': ' . $n);
            $this->json(['ok' => true, 'generadas' => $n]);
        }
        try {
            flash('exito', 'Constancias generadas: ' . HistoricoProtocolos::generar((int) $id, Auth::id()) . '.');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('titulacion/constancias/captura');
    }

    public function protocoloEliminar(string $id): void
    {
        $this->soloHistorial();
        try {
            HistoricoProtocolos::eliminar((int) $id);
            Audit::record('titulacion::historial', 'correcto', 'Protocolo externo eliminado ' . (int) $id);
            flash('exito', 'Registro eliminado.');
        } catch (\InvalidArgumentException $e) {
            flash('error', $e->getMessage());
        }
        redirigir('titulacion/constancias/captura');
    }

    private function json(array $d, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /** Edición en línea de un campo del visor (JSON). */
    public function campo(string $id): void
    {
        $this->soloHistorial();
        try {
            $fila = Historico::guardarCampo((int) $id, (string) ($_POST['campo'] ?? ''), (string) ($_POST['valor'] ?? ''), Auth::id());
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        $this->json(['ok' => true, 'fila' => $fila]);
    }

    /** Elimina un registro sin constancias generadas (JSON). */
    public function eliminar(string $id): void
    {
        $this->soloHistorial();
        try {
            $h = Historico::eliminar((int) $id);
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
        Audit::record('titulacion::historial', 'correcto', 'Registro histórico eliminado ' . (int) $id . ': ' . $h['alumno'] . ' (acta ' . ($h['acta'] ?? '—') . ', ' . $h['carrera'] . ')');
        $this->json(['ok' => true]);
    }

    /** Genera las constancias de un registro completo (JSON). */
    public function generar(string $id): void
    {
        $this->soloHistorial();
        try {
            $n = Historico::generar((int) $id, Auth::id());
        } catch (\InvalidArgumentException $e) {
            $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            error_log('Titulación: constancias históricas: ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'No se pudieron generar las constancias.'], 500);
        }
        Audit::record('titulacion::historial', 'correcto', 'Constancias históricas del registro ' . (int) $id . ': ' . $n);
        $this->json(['ok' => true, 'generadas' => $n]);
    }

    /** Busca en los trámites de la plataforma el título de los registros que no lo tienen (el definitivo antes que el del protocolo). */
    public function titulos(): void
    {
        $this->soloHistorial();
        $r = Historico::buscarTitulos();
        Audit::record('titulacion::historial', 'correcto', 'Títulos del historial desde trámites: ' . json_encode($r));
        $n = $r['revision'] + $r['protocolo'];
        flash($n > 0 ? 'exito' : 'alerta', $n > 0
            ? $n . ' títulos completados: ' . $r['revision'] . ' del trabajo definitivo (solicitud de revisión) y ' . $r['protocolo'] . ' del protocolo (márcalos con la vista en ámbar punteado y confirma que sean los definitivos). ' . $r['sin_tramite'] . ' registros no tienen trámite en la plataforma.'
            : 'No se encontró ningún título nuevo: ' . $r['sin_tramite'] . ' registros sin título no tienen un trámite en la plataforma (los de antes de la plataforma se capturan a mano o vienen del sistema anterior).');
        redirigir('titulacion/constancias/historial');
    }

    /** Rehace las constancias que esperan firma (quita las repetidas y toma a quien firma hoy); las firmadas no cambian. */
    public function rehacerPendientes(): void
    {
        // También desde «Firmas pendientes»: quien firma puede rehacer lo que va a firmar.
        if (!Constancias::puedeBuscar() && !\App\Modules\Titulacion\Services\SubdireccionFirma::puedeFirmar()) {
            $this->prohibido('Constancias pendientes de firma');
        }
        $this->privado();
        $volver = (string) ($_POST['volver'] ?? '') === 'firmas' ? 'titulacion/firmas' : 'titulacion/constancias/historial';
        @set_time_limit(600);
        $quitadas = (int) \App\Core\Database::valor("SELECT COUNT(*) FROM tit_constancias");
        foreach (\App\Core\Database::todas(
            "SELECT DISTINCT h.id FROM tit_historico h JOIN tit_firmas_subdireccion f ON f.tramite_id = h.tramite_id AND f.formato = 'constancia' AND f.estado = 'pendiente'"
        ) as $h) {
            try {
                Historico::generar((int) $h['id'], Auth::id());
            } catch (Throwable $e) {
                error_log('Titulación: revisar constancias del registro ' . $h['id'] . ': ' . $e->getMessage());
            }
        }
        $quitadas -= (int) \App\Core\Database::valor("SELECT COUNT(*) FROM tit_constancias");
        $n = Constancias::rehacerPendientes(Auth::id());
        Audit::record('titulacion::historial', 'correcto', 'Constancias pendientes de firma rehechas: ' . $n . '; repetidas quitadas: ' . max(0, $quitadas));
        flash('exito', $n . ' constancias pendientes de firma se rehicieron con el nombre de quien firma' . ($quitadas > 0 ? ' y se quitaron ' . $quitadas . ' repetidas' : '') . '. Las ya firmadas no cambiaron.');
        redirigir($volver);
    }

    /** Genera las constancias de todos los registros completos que aún no las tienen. */
    public function generarTodas(): void
    {
        $this->soloHistorial();
        @set_time_limit(600);
        $registros = 0;
        $constancias = 0;
        foreach (\App\Core\Database::todas("SELECT * FROM tit_historico WHERE generado_en IS NULL") as $h) {
            if (!Historico::aplica($h) || Historico::faltantes($h)) {
                continue;
            }
            try {
                $constancias += Historico::generar((int) $h['id'], Auth::id());
                $registros++;
            } catch (Throwable $e) {
                error_log('Titulación: constancias históricas del registro ' . $h['id'] . ': ' . $e->getMessage());
            }
        }
        Audit::record('titulacion::historial', 'correcto', 'Constancias históricas en lote: ' . $registros . ' registros, ' . $constancias . ' constancias');
        flash('exito', $constancias . ' constancias generadas de ' . $registros . ' registros completos; quedan en la cola de firmas.');
        redirigir('titulacion/constancias/historial');
    }
}
